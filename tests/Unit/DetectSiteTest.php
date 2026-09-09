<?php

namespace Tests\Unit;

use App\Services\DetectSite;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DetectSiteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'doctors.host' => 'doc.alexallergotest.ru',
            'doctors.path_preview' => true,
            'doctors.path_prefix' => 'doctors',
        ]);
    }

    #[DataProvider('doctorSiteProvider')]
    public function test_detects_doctors_site_from_host_or_path(string $host, string $path, bool $expected): void
    {
        $request = Request::create("https://{$host}/{$path}", 'GET');
        $detect = DetectSite::make($request);

        $this->assertSame($expected, $detect->isDoctorsSite());
    }

    public static function doctorSiteProvider(): array
    {
        return [
            'doc host root' => ['doc.alexallergotest.ru', '', true],
            'doc host materials' => ['doc.alexallergotest.ru', 'materials', true],
            'apex doctors prefix' => ['alexallergotest.ru', 'doctors/materials', true],
            'apex patient blog' => ['alexallergotest.ru', 'blog', false],
            'apex patient home' => ['alexallergotest.ru', '', false],
        ];
    }

    public function test_doctors_url_on_apex_uses_prefix(): void
    {
        $request = Request::create('https://alexallergotest.ru/doctors/materials', 'GET');
        $detect = DetectSite::make($request);

        $this->assertSame('/doctors/materials', $detect->doctorsUrl('/materials'));
    }

    public function test_doctors_url_on_subdomain_has_no_prefix(): void
    {
        $request = Request::create('https://doc.alexallergotest.ru/materials', 'GET');
        $detect = DetectSite::make($request);

        $this->assertSame('/materials', $detect->doctorsUrl('/materials'));
    }
}
