<?php

namespace Tests\Unit;

use App\Http\Controllers\Doctors\DoctorAuthController;
use App\Http\Controllers\Doctors\DoctorMaterialsController;
use App\Services\DetectSite;
use App\Support\DoctorsCopy;
use App\Support\DoctorsRedirect;
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
            'doctors.theme_color' => '#cba98e',
            'doctors.subdomain_redirect' => false,
        ]);
    }

    #[DataProvider('doctorSiteProvider')]
    public function test_detects_doctors_site_from_host_or_path(string $host, string $path, bool $expected): void
    {
        $request = Request::create('https://'.$host.'/'.ltrim($path, '/'), 'GET');
        $detect = DetectSite::make($request);

        $this->assertSame($expected, $detect->isDoctorsSite());
    }

    public static function doctorSiteProvider(): array
    {
        return [
            'doc host root' => ['doc.alexallergotest.ru', '', true],
            'doc host materials' => ['doc.alexallergotest.ru', 'materials', true],
            'doc wildcard staging' => ['doc.local.test', 'materials', true],
            'apex doctors prefix' => ['alexallergotest.ru', 'doctors/materials', true],
            'apex doctors login' => ['alexallergotest.ru', 'doctors/login', true],
            'apex doctors register' => ['alexallergotest.ru', 'doctors/register', true],
            'apex patient blog' => ['alexallergotest.ru', 'blog', false],
            'apex patient home' => ['alexallergotest.ru', '', false],
            'docs is not doc' => ['docs.alexallergotest.ru', '', false],
        ];
    }

    public function test_doctors_url_on_apex_uses_prefix(): void
    {
        $request = Request::create('https://alexallergotest.ru/doctors/materials', 'GET');
        $detect = DetectSite::make($request);

        $this->assertSame('/doctors/materials', $detect->doctorsUrl('/materials'));
        $this->assertSame('/doctors/login', $detect->doctorsUrl('/login'));
        $this->assertSame('#cba98e', $detect->themeColor());
    }

    public function test_doctors_url_on_subdomain_has_no_prefix(): void
    {
        $request = Request::create('https://doc.alexallergotest.ru/materials', 'GET');
        $detect = DetectSite::make($request);

        $this->assertSame('/materials', $detect->doctorsUrl('/materials'));
        $this->assertTrue($detect->isDoctorsHost());
    }

    public function test_path_preview_can_be_disabled(): void
    {
        $request = Request::create('https://alexallergotest.ru/doctors/materials', 'GET');
        $detect = DetectSite::make($request, ['path_preview' => false, 'host' => 'doc.alexallergotest.ru']);

        $this->assertFalse($detect->isDoctorsSite());
    }

    public function test_redirect_helper_strips_prefix(): void
    {
        $this->assertSame('/materials', DoctorsRedirect::stripPathPrefix('doctors/materials', 'doctors'));
        $this->assertSame('/', DoctorsRedirect::stripPathPrefix('doctors', 'doctors'));
        $this->assertNull(DoctorsRedirect::stripPathPrefix('blog', 'doctors'));
    }

    public function test_password_create_skips_hash_when_cast_exists(): void
    {
        $user = new class
        {
            public function getCasts(): array
            {
                return ['password' => 'hashed'];
            }
        };

        $this->assertSame('secret', DoctorAuthController::passwordForCreate($user, 'secret'));
    }

    public function test_document_pluralization(): void
    {
        $this->assertSame('1 документ', DoctorsCopy::documentLabel(1));
        $this->assertSame('2 документа', DoctorMaterialsController::documentLabel(2));
        $this->assertSame('5 документов', DoctorsCopy::documentLabel(5));
        $this->assertSame('21 документ', DoctorsCopy::documentLabel(21));
        $this->assertSame('11 документов', DoctorsCopy::documentLabel(11));
    }
}
