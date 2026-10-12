<?php

namespace Tests\Unit;

use App\Mail\DoctorAppointmentMail;
use App\Models\AnalyticsOutbox;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

// ТЗ по аналитике, этап А: принятая заявка фиксируется после сохранения, повтор не создаёт
// вторую заявку, наружу уходят только form_id/lead_type и только при разрешении и согласии.
class AnalyticsLeadOutboxTest extends TestCase
{
    use RefreshDatabase;

    private const COOKIES = [
        'alex_analytics_consent' => '1:granted',
        '_ga' => 'GA1.1.123456789.1700000000',
        '_ym_uid' => '1700000000123456789',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::fake();
        $this->withoutDefer();
        config([
            'analytics.ga4.api_secret' => null,
            'analytics.metrika.oauth_token' => null,
            'analytics.export.doctor_appointment' => false,
            'analytics.export.test_order' => false,
        ]);
    }

    private function appointment(array $extra = [], array $cookies = [])
    {
        return $this->withCredentials()->withUnencryptedCookies($cookies)->postJson('/api/doctor-appointment', $extra + [
            'full_name' => 'Иван Тестов',
            'phone' => '+7 (900) 000-00-00',
            'city' => 'Москва',
            'agreed_to_terms' => true,
            'quiz_answers' => [],
        ]);
    }

    public function test_repeated_submission_returns_the_same_order(): void
    {
        $first = $this->appointment(['submission_id' => 'sub-0001-aaaa'])->assertOk()->json('order_id');
        $second = $this->appointment(['submission_id' => 'sub-0001-aaaa'])->assertOk()->json('order_id');

        $this->assertSame($first, $second);
        $this->assertSame(1, Order::count());
        $this->assertSame(2, AnalyticsOutbox::count());
        Mail::assertSent(DoctorAppointmentMail::class, 1);

        $this->appointment(['submission_id' => 'sub-0002-bbbb'])->assertOk();
        $this->assertSame(2, Order::count());
    }

    public function test_quiz_doctor_form_is_logged_but_not_exported_by_default(): void
    {
        config(['analytics.ga4.api_secret' => 'secret', 'analytics.metrika.oauth_token' => 'token']);

        $this->appointment([], self::COOKIES)->assertOk();

        $rows = AnalyticsOutbox::orderBy('platform')->get();
        $this->assertSame(['ga4', 'metrika'], $rows->pluck('platform')->all());
        $this->assertSame(['suppressed'], $rows->pluck('status')->unique()->values()->all());
        $this->assertSame(['export_disabled'], $rows->pluck('reason')->unique()->values()->all());
        $this->assertSame('site_order:'.Order::first()->id, $rows[0]->inquiry_ref);
        Http::assertNothingSent();
    }

    public function test_allowed_lead_is_sent_without_personal_data(): void
    {
        config([
            'analytics.export.doctor_appointment' => true,
            'analytics.ga4.api_secret' => 'secret',
            'analytics.metrika.oauth_token' => 'token',
        ]);

        $this->appointment([], self::COOKIES)->assertOk();

        $this->assertSame(['sent'], AnalyticsOutbox::pluck('status')->unique()->values()->all());

        Http::assertSent(function (HttpRequest $request) {
            if (! str_contains($request->url(), 'google-analytics.com/mp/collect')) {
                return false;
            }
            $body = $request->data();

            return $body['client_id'] === '123456789.1700000000'
                && $body['events'][0]['name'] === 'generate_lead'
                && $body['events'][0]['params']['form_id'] === 'doctor_appointment'
                && ! str_contains($request->body(), 'Иван')
                && ! str_contains($request->body(), '900');
        });
        Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'offline_conversions/upload')
            && $request->hasHeader('Authorization', 'OAuth token'));
    }

    public function test_no_consent_means_nothing_leaves_the_site(): void
    {
        config(['analytics.export.doctor_appointment' => true, 'analytics.ga4.api_secret' => 'secret']);

        $this->appointment([], ['_ga' => self::COOKIES['_ga']])->assertOk();

        $this->assertSame(['no_consent'], AnalyticsOutbox::pluck('reason')->unique()->values()->all());
        Http::assertNothingSent();
    }

    public function test_pending_events_are_delivered_once_keys_appear(): void
    {
        config(['analytics.export.doctor_appointment' => true]);

        $this->appointment([], self::COOKIES)->assertOk();
        $this->assertSame(['pending'], AnalyticsOutbox::pluck('status')->unique()->values()->all());

        config(['analytics.ga4.api_secret' => 'secret']);
        $this->artisan('analytics:deliver')->assertSuccessful();

        $this->assertSame('sent', AnalyticsOutbox::where('platform', 'ga4')->value('status'));
        $this->assertSame('pending', AnalyticsOutbox::where('platform', 'metrika')->value('status'));
    }

    public function test_frontend_forms_send_a_submission_id(): void
    {
        foreach (['resources/js/Pages/Public/Quiz.vue', 'resources/js/Pages/Public/Cart.vue'] as $file) {
            $source = file_get_contents(base_path($file));
            $this->assertStringContainsString('newSubmissionId()', $source, $file);
            $this->assertMatchesRegularExpression('/submission_id: \w*[sS]ubmissionId/', $source, $file);
        }
    }
}
