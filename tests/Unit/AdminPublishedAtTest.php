<?php

namespace Tests\Unit;

use App\Support\AdminPublishedAt;
use Carbon\Carbon;
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

    public function test_publishing_a_draft_uses_now_and_ignores_the_old_clock(): void
    {
        $now = Carbon::parse('2026-09-28 10:35:00', 'UTC');
        Carbon::setTestNow($now);

        $draftClock = Carbon::parse('2026-08-17 13:27:58', 'UTC');
        $resolved = AdminPublishedAt::forSave(
            true,
            false,
            $draftClock,
            '2026-09-28T13:27:00.000Z',
            false,
        );

        $this->assertSame('2026-09-28 10:35:00', $resolved?->utc()->format('Y-m-d H:i:s'));
        Carbon::setTestNow();
    }

    public function test_explicit_date_on_publish_is_kept(): void
    {
        config(['app.admin_timezone' => 'Europe/Moscow']);
        Carbon::setTestNow(Carbon::parse('2026-09-28 10:35:00', 'UTC'));

        $resolved = AdminPublishedAt::forSave(
            true,
            false,
            null,
            '2026-09-29T18:00',
            true,
        );

        $this->assertSame('2026-09-29 15:00:00', $resolved?->utc()->format('Y-m-d H:i:s'));
        Carbon::setTestNow();
    }

    public function test_editing_a_live_article_keeps_its_date_until_the_field_changes(): void
    {
        $published = Carbon::parse('2026-09-01 08:00:00', 'UTC');

        $kept = AdminPublishedAt::forSave(true, true, $published, '2026-09-28T13:27:00.000Z', false);
        $this->assertSame('2026-09-01 08:00:00', $kept?->utc()->format('Y-m-d H:i:s'));

        $changed = AdminPublishedAt::forSave(true, true, $published, '2026-09-28T13:27:00.000Z', true);
        $this->assertSame('2026-09-28 13:27:00', $changed?->utc()->format('Y-m-d H:i:s'));
    }
}
