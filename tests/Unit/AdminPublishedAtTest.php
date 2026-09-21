<?php

namespace Tests\Unit;

use App\Support\AdminPublishedAt;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminPublishedAtTest extends TestCase
{
    #[DataProvider('parseCases')]
    public function test_parse(string $input, string $expectedUtc): void
    {
        config(['app.admin_timezone' => 'Europe/Moscow']);

        $parsed = AdminPublishedAt::parse($input);
        $this->assertNotNull($parsed);
        $this->assertSame($expectedUtc, $parsed->format('Y-m-d H:i:s'));
    }

    public static function parseCases(): array
    {
        return [
            'naive datetime-local as MSK' => ['2026-09-18T18:26', '2026-09-18 15:26:00'],
            'ISO Z from browser' => ['2026-09-18T15:26:00.000Z', '2026-09-18 15:26:00'],
            'ISO with offset' => ['2026-09-18T18:26:00+03:00', '2026-09-18 15:26:00'],
        ];
    }

    public function test_parse_empty_returns_null(): void
    {
        $this->assertNull(AdminPublishedAt::parse(null));
        $this->assertNull(AdminPublishedAt::parse(''));
        $this->assertNull(AdminPublishedAt::parse('   '));
    }
}
