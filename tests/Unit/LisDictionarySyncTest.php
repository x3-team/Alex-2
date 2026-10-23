<?php

namespace Tests\Unit;

use App\Lis\AlfaLab\DictionarySync;
use App\Lis\AlfaLab\Exceptions\LisException;
use App\Lis\AlfaLab\Transport\LisResponse;
use App\Lis\AlfaLab\Transport\LisTransport;
use App\Models\Lis\LabService;
use App\Models\Lis\LisExchangeLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LisDictionarySyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'alfalab.enabled' => true,
            'alfalab.driver' => 'fake',
            'alfalab.services.alex2' => 'ALEX2',
            'alfalab.services.sampling' => 'VZ1',
        ]);
    }

    private function lisJson(array $data): LisResponse
    {
        return new LisResponse(200, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    public function test_sync_stores_services_prices_and_reports_ready(): void
    {
        $result = app(DictionarySync::class)->sync();

        $this->assertTrue($result['changed']);
        $alex2 = LabService::where('code', 'ALEX2')->first();
        $this->assertSame('21900.00', $alex2->price);
        $this->assertSame(['87'], $alex2->biomaterial_codes);
        $this->assertSame('days', $alex2->duration_unit);
        $this->assertSame('300.00', LabService::where('code', 'VZ1')->first()->price);
        $this->assertSame([], DictionarySync::problems());

        // Та же версия — полную выгрузку не качаем.
        $this->assertFalse(app(DictionarySync::class)->sync()['changed']);
        $this->assertSame(1, LisExchangeLog::where('message_type', 'query-dictionaries')->count());
    }

    public function test_suspended_or_missing_service_blocks_orders(): void
    {
        $transport = app(LisTransport::class);
        $transport->pushNext($this->lisJson(['version' => ['version' => 7]]));
        $transport->pushNext($this->lisJson([
            'analyses' => [['id' => 1, 'code' => 'ALEX2', 'name' => 'ALEX2']],
            'prices' => [['serviceCode' => 'ALEX2', 'price' => 21900]],
            'delayedTargets' => [['targetCode' => 'ALEX2', 'startDate' => now()->subDay()->toDateString(), 'reasonName' => 'Нет реагентов']],
        ]));

        app(DictionarySync::class)->sync();

        $problems = DictionarySync::problems();
        $this->assertCount(2, $problems);
        $this->assertStringContainsString('Нет реагентов', $problems[0]);
        $this->assertStringContainsString('VZ1', $problems[1]);
    }

    public function test_suspension_with_past_end_date_does_not_block(): void
    {
        $service = new LabService(['suspended_from' => now()->subDays(10), 'suspended_till' => now()->subDay(), 'removed' => false]);

        $this->assertTrue($service->isOrderable());
    }

    public function test_empty_dictionary_keeps_existing_cache(): void
    {
        app(DictionarySync::class)->sync();

        $transport = app(LisTransport::class);
        $transport->pushNext($this->lisJson(['version' => ['version' => 2]]));
        $transport->pushNext($this->lisJson(['analyses' => []]));

        try {
            app(DictionarySync::class)->sync();
            $this->fail('expected LisException');
        } catch (LisException $e) {
            $this->assertSame(LisException::PROTOCOL, $e->kind);
        }

        $this->assertFalse(LabService::where('code', 'ALEX2')->first()->removed);
    }

    public function test_missing_codes_in_config_are_reported(): void
    {
        config(['alfalab.services.alex2' => null]);

        $this->assertStringContainsString('ALFALAB_ALEX2_CODE', DictionarySync::problems()[0]);
    }

    public function test_command_does_nothing_when_disabled(): void
    {
        config(['alfalab.enabled' => false]);

        $this->artisan('lis:dictionaries')->assertSuccessful();
        $this->assertSame(0, LisExchangeLog::count());
    }

    public function test_command_syncs_and_fake_advance_moves_referral(): void
    {
        $this->artisan('lis:dictionaries')->assertSuccessful();
        $this->assertSame(2, LabService::count());

        $row = \App\Models\Lis\LisFakeReferral::create(['nr' => '7000000001', 'orders' => []]);
        $this->artisan('lis:fake-advance', ['ref' => '7000000001'])->assertSuccessful();
        $this->assertSame('activated', $row->fresh()->stage);
    }
}
