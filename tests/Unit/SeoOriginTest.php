<?php

namespace Tests\Unit;

use App\Support\SeoOrigin;
use Illuminate\Http\Request;
use Tests\TestCase;

class SeoOriginTest extends TestCase
{
    public function test_site_base_url_uses_request_host_on_doctors_subdomain(): void
    {
        config(['doctors.host' => 'doc.example.test']);

        $request = Request::create('https://doc.example.test/materials', 'GET');
        $this->app->instance('request', $request);

        $this->assertSame(
            'https://doc.example.test',
            SeoOrigin::make($request)->siteBaseUrl()
        );
    }

    public function test_site_base_url_uses_app_url_on_patient_host(): void
    {
        config(['app.url' => 'https://patient.example.test']);

        $request = Request::create('https://patient.example.test/blog', 'GET');
        $this->app->instance('request', $request);

        $this->assertSame(
            'https://patient.example.test',
            SeoOrigin::make($request)->siteBaseUrl()
        );
    }
}
