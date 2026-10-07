<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AlexLabConsentRoutesTest extends TestCase
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

    public function test_consent_lives_under_alex_lab(): void
    {
        $this->assertSame(url('/alex-lab/consent'), route('consent'));
        $this->assertSame(url('/alex-lab/consent'), route('alex-lab.section', ['section' => 'consent']));

        $this->get('/alex-lab/consent')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/AlexLab')
                ->where('activeSection', 'consent')
                ->where('meta.title', 'Согласие на обработку ПД — ALEX LAB')
            );
    }

    public function test_alex_lab_tabs_open_with_their_own_section(): void
    {
        $this->get('/alex-lab')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/AlexLab')
                ->where('activeSection', 'about')
            );

        foreach (['licenses', 'doctors', 'contacts', 'privacy'] as $section) {
            $this->get('/alex-lab/'.$section)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Public/AlexLab')
                    ->where('activeSection', $section)
                );
        }
    }

    public function test_old_consent_url_redirects_once_keeping_query(): void
    {
        $this->get('/consent')
            ->assertStatus(301)
            ->assertRedirect('/alex-lab/consent');

        $response = $this->get('/consent?utm_source=mail&x=1');
        $response->assertStatus(301)
            ->assertRedirect('/alex-lab/consent?utm_source=mail&x=1');

        // Один хоп: цель редиректа сразу отдаёт 200
        $this->get($response->headers->get('Location'))->assertOk();
    }

    public function test_about_redirects_to_alex_lab(): void
    {
        $this->get('/alex-lab/about')
            ->assertStatus(301)
            ->assertRedirect('/alex-lab');
    }

    public function test_doctors_domain_serves_the_same_consent_urls(): void
    {
        $this->withServerVariables(['HTTP_HOST' => 'doc.alexallergotest.ru'])
            ->get('http://doc.alexallergotest.ru/alex-lab/consent')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/AlexLab')
                ->where('activeSection', 'consent')
            );

        $response = $this->withServerVariables(['HTTP_HOST' => 'doc.alexallergotest.ru'])
            ->get('http://doc.alexallergotest.ru/consent?a=1');
        $response->assertStatus(301);
        $this->assertSame(
            'http://doc.alexallergotest.ru/alex-lab/consent?a=1',
            $response->headers->get('Location')
        );
    }

    public function test_sitemap_lists_alex_lab_consent_not_bare_consent(): void
    {
        Cache::flush();

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('/alex-lab/consent</loc>', $xml);
        $this->assertDoesNotMatchRegularExpression('#<loc>https?://[^/<]+/consent</loc>#', $xml);
    }
}
