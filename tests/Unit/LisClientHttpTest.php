<?php

namespace Tests\Unit;

use App\Lis\AlfaLab\AlfaLabClient;
use App\Lis\AlfaLab\Data\Patient;
use App\Lis\AlfaLab\Data\Payload;
use App\Lis\AlfaLab\Data\ReferralRequest;
use App\Lis\AlfaLab\ExchangeLogger;
use App\Lis\AlfaLab\Exceptions\LisException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LisClientHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'alfalab.driver' => 'http',
            'alfalab.http.base_url' => 'https://lis.example.test:8443/',
            'alfalab.http.sender' => '20229',
            'alfalab.http.password' => 'secret-pass',
        ]);
    }

    private function client(): AlfaLabClient
    {
        return app(AlfaLabClient::class);
    }

    private function request(): ReferralRequest
    {
        return new ReferralRequest('mis-77', new Patient('Тестовая'), [['code' => 'ALEX2']], new \DateTimeImmutable('2026-10-23 10:00:00'), '5000099800');
    }

    public function test_sends_json_with_basic_auth_to_message_url(): void
    {
        Http::fake(['*' => Http::response([
            'MessageType' => 'result-import-referral',
            'Referral' => ['MisId' => 'mis-77', 'Nr' => '5000099800', 'LisId' => 16383332],
        ])]);

        $result = $this->client()->createReferral($this->request());

        // Ответ в PascalCase (как в таблицах протокола) читается так же, как camelCase.
        $this->assertSame(16383332, $result->lisId);
        $this->assertSame('5000099800', $result->nr);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://lis.example.test:8443/query-create-referral?format=json'
                && $request->method() === 'POST'
                && $request->header('Authorization')[0] === 'Basic '.base64_encode('20229:secret-pass')
                && $request['patient']['lastName'] === 'Тестовая'
                && $request['referral']['orders'][0]['code'] === 'ALEX2';
        });
    }

    public function test_http_errors_map_to_kinds(): void
    {
        $cases = [403 => [LisException::AUTH, false], 404 => [LisException::PROTOCOL, false], 500 => [LisException::SERVER, true]];

        Http::fake(['*' => Http::sequence()->push('', 403)->push('', 404)->push('', 500)]);

        foreach ($cases as $status => [$kind, $retryable]) {
            try {
                $this->client()->createReferral($this->request());
                $this->fail("expected exception for {$status}");
            } catch (LisException $e) {
                $this->assertSame($kind, $e->kind, "status {$status}");
                $this->assertSame($retryable, $e->retryable(), "status {$status}");
                $this->assertSame($status, $e->httpStatus);
            }
        }
    }

    public function test_error_attribute_is_business_error(): void
    {
        Http::fake(['*' => Http::response(['Error' => 'Направление с номером 5000099800 уже существует'])]);

        $this->expectExceptionObject(new LisException(LisException::BUSINESS, 'Направление с номером 5000099800 уже существует'));

        $this->client()->createReferral($this->request());
    }

    public function test_timeout_means_outcome_unknown(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));

        try {
            $this->client()->createReferral($this->request());
            $this->fail('expected LisException');
        } catch (LisException $e) {
            $this->assertSame(LisException::TRANSPORT, $e->kind);
            $this->assertTrue($e->outcomeUnknown());
        }
    }

    public function test_missing_credentials_never_hit_network(): void
    {
        config(['alfalab.http.password' => null]);
        Http::fake();

        try {
            $this->client()->dictionariesVersion();
            $this->fail('expected LisException');
        } catch (LisException $e) {
            $this->assertSame(LisException::DISABLED, $e->kind);
        }
        Http::assertNothingSent();
    }

    public function test_empty_queue_and_blank_file(): void
    {
        Http::fake([
            '*/query-next-referral-results*' => Http::response('', 200),
            '*/query-blank-file*' => Http::response('%PDF-1.4 test', 200, ['Content-Type' => 'application/pdf']),
        ]);

        $this->assertNull($this->client()->nextReferralResults());

        $blank = $this->client()->blankFile('{0903AE0C-2666-4704-B502-272FC9593E84}');
        $this->assertTrue($blank->isPdf());
        $this->assertSame('application/pdf', $blank->contentType);
    }

    public function test_results_dates_in_both_formats(): void
    {
        $this->assertSame('2021-04-20 17:45:00', Payload::date(['d' => '2021-04-20T17:45:00+03:00'], 'd')->format('Y-m-d H:i:s'));
        $this->assertSame('2012-11-08 14:56:00', Payload::date(['d' => '08.11.2012 14:56:00'], 'd')->format('Y-m-d H:i:s'));
        $this->assertSame('2013-04-12', Payload::date(['d' => '12.04.2013'], 'd')->format('Y-m-d'));
        $this->assertNull(Payload::date(['d' => 'не дата'], 'd'));
        $this->assertSame(2.25, Payload::float(['v' => '2,25'], 'v'));
    }

    public function test_error_text_is_scrubbed(): void
    {
        $text = ExchangeLogger::scrub('Пациент ivan@mail.ru, 10.12.1978, тел. +7 (999) 123-45-67 / 89991234567, направление 5000099800');

        $this->assertStringNotContainsString('ivan@mail.ru', $text);
        $this->assertStringNotContainsString('1978', $text);
        $this->assertStringNotContainsString('123-45-67', $text);
        $this->assertStringNotContainsString('89991234567', $text);
        $this->assertStringContainsString('5000099800', $text);
    }
}
