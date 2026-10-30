<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Staging (ANALYTICS_CLIENT_ENABLED=false): теги Метрики и GA4 не грузятся, на проде всё как было.
class StagingAnalyticsSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $views = dirname(__DIR__, 2).'/storage/framework/views';
        if (! is_dir($views)) {
            mkdir($views, 0777, true);
        }

        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->withoutVite();
    }

    public function test_analytics_enabled_by_default(): void
    {
        $html = $this->get('/alex-lab/consent')->assertOk()->getContent();

        $this->assertStringContainsString('var ANALYTICS_ENABLED = true;', $html);
        $this->assertStringContainsString('!ANALYTICS_ENABLED || loaded', $html);
    }

    public function test_staging_switch_turns_analytics_off(): void
    {
        config(['analytics.client_enabled' => false]);

        $html = $this->get('/alex-lab/consent')->assertOk()->getContent();

        $this->assertStringContainsString('var ANALYTICS_ENABLED = false;', $html);
        $this->assertStringContainsString('window.alexAnalytics', $html);
    }
}
