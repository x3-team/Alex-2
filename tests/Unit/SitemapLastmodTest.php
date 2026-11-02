<?php

namespace Tests\Unit;

use App\Http\Controllers\SitemapController;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SitemapLastmodTest extends TestCase
{
    private const W3C = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/';

    public function test_raw_db_datetime_becomes_w3c(): void
    {
        // Так max('updated_at') отдавал дату категорий блога в sitemap.
        $this->assertSame('2026-10-26T07:25:18+00:00', SitemapController::w3cLastmod('2026-10-26 07:25:18'));
    }

    public function test_static_date_and_carbon_and_empty(): void
    {
        $this->assertSame('2026-08-26T00:00:00+03:00', SitemapController::w3cLastmod('2026-08-26'));
        $this->assertSame('2026-10-30T16:15:16+00:00', SitemapController::w3cLastmod(Carbon::parse('2026-10-30 16:15:16', 'UTC')));
        $this->assertSame('2026-10-30T16:15:16+00:00', SitemapController::w3cLastmod('2026-10-30T16:15:16+00:00'));
        $this->assertNull(SitemapController::w3cLastmod(null));
        $this->assertNull(SitemapController::w3cLastmod(''));
        $this->assertNull(SitemapController::w3cLastmod('не дата'));
    }

    public function test_every_lastmod_goes_through_formatter_and_blog_uses_latest_article(): void
    {
        $src = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/SitemapController.php');

        $this->assertStringContainsString('$lastmod = self::w3cLastmod($lastmod);', $src);
        $this->assertStringContainsString("\$this->addUrl(\$baseUrl . '/blog', \$blogIndexLastmod", $src);
        $this->assertStringContainsString("\$this->addUrl(\$baseUrl . '/materials', \$materialsLastmod ?: \$staticLastmod", $src);
        $this->assertMatchesRegularExpression(self::W3C, (string) SitemapController::w3cLastmod('2026-01-01 00:00:00'));
    }

    public function test_admin_blog_save_clears_real_sitemap_cache(): void
    {
        $src = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Admin/BlogController.php');

        $this->assertSame(4, substr_count($src, 'SitemapController::forgetCache()'));
    }
}
