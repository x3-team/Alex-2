<?php

namespace Tests\Unit;

use Tests\TestCase;

class DoctorSitemapTest extends TestCase
{
    public function test_sitemap_controller_includes_doctors_generator(): void
    {
        $src = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/SitemapController.php');

        $this->assertIsString($src);
        $this->assertStringContainsString('generateDoctorsSitemap', $src);
        $this->assertStringContainsString("'/materials/'", $src);
        $this->assertStringContainsString("'/video/'", $src);
    }
}
