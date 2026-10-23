<?php

namespace Tests\Unit;

use App\Lis\AlfaLab\AlfaLabClient;
use App\Lis\AlfaLab\Data\Patient;
use App\Lis\AlfaLab\Data\ReferralRequest;
use App\Lis\AlfaLab\Data\ReferralState;
use App\Lis\AlfaLab\Exceptions\LisException;
use App\Lis\AlfaLab\Transport\FakeTransport;
use App\Models\Lis\LisExchangeLog;
use App\Models\Lis\LisFakeReferral;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LisFakeFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['alfalab.driver' => 'fake', 'alfalab.referral_nr.length' => 10]);
    }

    private function client(): AlfaLabClient
    {
        return app(AlfaLabClient::class);
    }

    private function request(array $codes = ['ALEX2', 'VZ1'], ?string $nr = null, ?string $misId = null): ReferralRequest
    {
        return new ReferralRequest(
            misId: $misId ?? (string) Str::uuid(),
            patient: new Patient('Тестовая', 'Марина', 'Сергеевна', Patient::GENDER_FEMALE, new \DateTimeImmutable('1978-12-10'), phone: '+79991234567'),
            orders: array_map(fn ($c) => ['code' => $c], $codes),
            date: new \DateTimeImmutable('2026-10-23 10:00:00', new \DateTimeZone('Europe/Moscow')),
            nr: $nr,
        );
    }

    public function test_request_payload_follows_protocol_example(): void
    {
        $payload = $this->request(nr: '5000099800', misId: 'mis-1')->toPayload();

        $this->assertSame('Тестовая', $payload['patient']['lastName']);
        $this->assertSame(2, $payload['patient']['gender']);
        $this->assertSame('1978-12-10', $payload['patient']['birthDate']);
        $this->assertSame('5000099800', $payload['referral']['nr']);
        $this->assertArrayNotHasKey('autoNr', $payload['referral']);
        $this->assertSame('2026-10-23T10:00:00+03:00', $payload['referral']['date']);
        $this->assertSame([['code' => 'ALEX2'], ['code' => 'VZ1']], $payload['referral']['orders']);
        $this->assertTrue($this->request()->toPayload()['referral']['autoNr']);
    }

    public function test_full_lifecycle_through_queue(): void
    {
        $client = $this->client();
        $request = $this->request();

        $created = $client->createReferral($request);
        $this->assertNotNull($created->lisId);
        $this->assertMatchesRegularExpression('/^[1-9]\d{9}$/', $created->nr);
        $this->assertSame([], $created->warnings);

        // Новое направление попадает в очередь: ждём пациента.
        $state = $client->nextReferralResults();
        $this->assertSame($created->lisId, $state->lisId);
        $this->assertSame($request->misId, $state->misId);
        $this->assertSame(ReferralState::ACTIVATED_NO, $state->activated);
        $client->ackReferralResults($state->lisId, $state->version);
        $this->assertNull($client->nextReferralResults());

        // Кровь взята.
        FakeTransport::advance(LisFakeReferral::find($created->lisId));
        $state = $client->nextReferralResults();
        $this->assertSame(ReferralState::ACTIVATED_FULLY, $state->activated);
        $this->assertFalse($state->done);
        $client->ackReferralResults($state->lisId, $state->version);

        // Результат готов: бланк с GUID (ключ BlankGUID в другом регистре тоже читается).
        FakeTransport::advance(LisFakeReferral::find($created->lisId));
        $state = $client->nextReferralResults();
        $this->assertTrue($state->done);
        $blanks = $state->readyResultBlanks();
        $this->assertCount(1, $blanks);

        $pdf = $client->blankFile($blanks[0]['guid']);
        $this->assertTrue($pdf->isPdf());
        $this->assertStringContainsString($created->nr, $pdf->content);
    }

    public function test_ack_of_stale_version_keeps_referral_in_queue(): void
    {
        $client = $this->client();
        $created = $client->createReferral($this->request());
        FakeTransport::advance(LisFakeReferral::find($created->lisId));

        $client->ackReferralResults($created->lisId, 1);

        $this->assertSame(2, $client->nextReferralResults()->version);
    }

    public function test_unknown_service_code_is_only_a_warning(): void
    {
        $result = $this->client()->createReferral($this->request(['ALEX2', 'NOPE']));

        $this->assertNotNull($result->lisId);
        $this->assertCount(1, $result->missingServiceWarnings());
        $this->assertStringContainsString('NOPE', $result->missingServiceWarnings()[0]);
        $this->assertSame('warning', LisExchangeLog::latest('id')->first()->outcome);
    }

    public function test_duplicate_number_is_business_error(): void
    {
        $this->client()->createReferral($this->request(nr: '5000099800'));

        try {
            $this->client()->createReferral($this->request(nr: '5000099800'));
            $this->fail('expected LisException');
        } catch (LisException $e) {
            $this->assertSame(LisException::BUSINESS, $e->kind);
            $this->assertFalse($e->retryable());
            $this->assertStringContainsString('уже существует', $e->getMessage());
        }
    }

    public function test_number_must_follow_rules(): void
    {
        $this->expectException(LisException::class);

        $this->client()->createReferral($this->request(nr: '0123456789'));
    }

    public function test_lookup_by_mis_id_after_lost_answer(): void
    {
        $client = $this->client();
        $request = $this->request();

        $this->assertFalse($client->referralResults(misId: $request->misId)->found());

        $created = $client->createReferral($request);
        $state = $client->referralResults(misId: $request->misId);

        $this->assertTrue($state->found());
        $this->assertSame($created->lisId, $state->lisId);
    }

    public function test_remove_only_before_activation(): void
    {
        $client = $this->client();
        $first = $client->createReferral($this->request());
        $client->removeReferral(lisId: $first->lisId);
        $this->assertSame('removed', LisFakeReferral::find($first->lisId)->stage);

        $second = $client->createReferral($this->request());
        FakeTransport::advance(LisFakeReferral::find($second->lisId));

        $this->expectException(LisException::class);
        $client->removeReferral(lisId: $second->lisId);
    }

    public function test_scripted_transport_failure_is_logged_without_patient_data(): void
    {
        app(\App\Lis\AlfaLab\Transport\LisTransport::class)
            ->pushNext(new LisException(LisException::TRANSPORT, 'timeout'));

        try {
            $this->client()->createReferral($this->request());
            $this->fail('expected LisException');
        } catch (LisException $e) {
            $this->assertTrue($e->outcomeUnknown());
            $this->assertTrue($e->retryable());
        }

        $this->client()->createReferral($this->request());

        $log = LisExchangeLog::orderBy('id')->get();
        $this->assertSame(['transport_error', 'ok'], $log->pluck('outcome')->all());
        $dump = json_encode($log->toArray(), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('Тестовая', $dump);
        $this->assertStringNotContainsString('1978', $dump);
        $this->assertStringNotContainsString('9991234567', $dump);
        $this->assertSame(64, strlen($log->last()->request_hash));
    }
}
