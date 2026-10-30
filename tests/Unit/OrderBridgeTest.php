<?php

namespace Tests\Unit;

use App\Models\LabOrder;
use App\Models\LabPatient;
use App\Models\LabResultBlank;
use App\Models\Order;
use App\Models\User;
use App\Support\OrderBridge\DnkomXml;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrderBridgeTest extends TestCase
{
    use RefreshDatabase;

    private int $nextResults = 0;

    private string $yookassaStatus = 'succeeded';

    private const PREVIEW_KEY = 'preview-key-0123456789abcdefghijklmnop';

    protected function setUp(): void
    {
        $views = dirname(__DIR__, 2).'/storage/framework/views';
        if (! is_dir($views)) {
            mkdir($views, 0777, true);
        }

        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->withoutVite();
        $this->nextResults = 0;
    }

    public function test_flag_off_hides_the_bridge(): void
    {
        config(['order_bridge.enabled' => false]);
        Http::fake();

        $this->postJson('/api/lab-order', $this->payload())->assertNotFound();
        $this->postJson('/api/lab-order/payment', ['event' => 'payment.succeeded'])->assertNotFound();
        $this->assertSame(0, LabOrder::query()->count());

        $this->artisan('order-bridge:push')->assertOk();
        $this->artisan('order-bridge:poll-results')->assertOk();
        $this->artisan('order-bridge:sync-dictionaries')->assertOk();
        Http::assertNothingSent();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/cart')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('orderBridge.enabled', false)
            ->where('orderBridge.consentText', ''));
    }

    public function test_flag_on_keeps_cart_hidden_from_guests_and_shows_consent_to_admin(): void
    {
        config(['order_bridge.enabled' => true]);

        $this->get('/cart')->assertNotFound();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/cart')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('orderBridge.enabled', true)
            ->where('orderBridge.consentText', config('order_bridge.consent_text')));
    }

    public function test_empty_codes_save_locally_without_http(): void
    {
        config(['order_bridge.enabled' => true]);
        Http::fake();

        $response = $this->postJson('/api/lab-order', $this->payload())->assertOk();
        $lab = LabOrder::query()->firstOrFail();
        $site = Order::query()->firstOrFail();

        $this->assertSame('config_incomplete', $lab->failure_reason);
        $this->assertSame('failed', $lab->status);
        $this->assertSame($site->id, $lab->site_order_id);
        $this->assertSame($response->json('order_id'), $site->id);
        $this->assertStringNotContainsString('1990', (string) $site->comment);
        $this->assertStringNotContainsString('01.02.1990', json_encode($site->items, JSON_UNESCAPED_UNICODE));
        $this->assertStringNotContainsString('male', json_encode($site->items, JSON_UNESCAPED_UNICODE));
        $this->assertStringNotContainsString('ДНКОМ', (string) $site->comment);
        $this->assertSame('Иванов Иван Иванович', $site->customer_name);
        $this->assertSame('test_order', $site->items['type']);
        $this->assertCount(2, $site->items['lines']);

        $stored = (string) DB::table('lab_patients')->value('last_name');
        $this->assertStringNotContainsString('Иванов', $stored);
        $this->assertSame('Иванов', LabPatient::query()->firstOrFail()->last_name);
        $this->assertSame('create', DB::table('lab_patient_access_logs')->value('action'));
        $this->assertStringNotContainsString('Иванов', json_encode(DB::table('lab_patient_access_logs')->first(), JSON_UNESCAPED_UNICODE));
        Http::assertNothingSent();
    }

    public function test_phone_and_consent_are_required(): void
    {
        config(['order_bridge.enabled' => true]);

        $this->postJson('/api/lab-order', $this->payload(['phone' => '123']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('phone');

        $this->postJson('/api/lab-order', $this->payload(['consent_lab' => false]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('consent_lab');
    }

    public function test_referral_xml_is_windows_1251_and_is_not_sent(): void
    {
        config(['order_bridge.enabled' => true, 'order_bridge.dnkom.password' => 'pw-dnkom']);
        Http::fake();
        $this->postJson('/api/lab-order', $this->payload())->assertOk();
        $lab = LabOrder::query()->firstOrFail();

        $bytes = DnkomXml::referral($lab->fresh(['patient', 'lines']));
        $this->assertStringContainsString('encoding="windows-1251"', $bytes);
        $this->assertStringNotContainsString('Иванов', $bytes);
        $utf = iconv('Windows-1251', 'UTF-8', $bytes);
        $this->assertStringContainsString('Иванов', $utf);
        $this->assertStringContainsString('Gender="1"', $utf);
        $this->assertStringContainsString('AutoNr="true"', $utf);
        $this->assertStringContainsString('Sender="', $utf);

        Artisan::call('order-bridge:referral-xml', ['lab_order_id' => $lab->id]);
        $output = Artisan::output();
        $this->assertStringContainsString('encoding="windows-1251"', $output);
        $this->assertStringContainsString('Иванов', iconv('Windows-1251', 'UTF-8', $output));
        $this->assertSame('read', DB::table('lab_patient_access_logs')->orderByDesc('id')->value('action'));
        Http::assertNothingSent();
    }

    public function test_on_site_push_sends_unpaid_sender_and_puts_lis_number_in_medmis(): void
    {
        $this->ready();
        Http::fake(fn (Request $request) => $this->fakeHttp($request));

        $this->postJson('/api/lab-order', $this->payload())->assertOk();
        $this->artisan('order-bridge:push')->assertOk();

        $lab = LabOrder::query()->firstOrFail();
        $this->assertSame('555', $lab->dnkom_nr);
        $this->assertSame('UNPAID', $lab->dnkom_sender);
        $this->assertSame('created', $lab->status);
        $this->assertNotNull($lab->medmis_dnkom_reported_at);
        $this->assertStringNotContainsString('555', (string) Order::query()->firstOrFail()->comment);

        $dnkom = $this->recordedBody(fn (string $body) => str_contains($body, 'query-create-referral'));
        $utf = iconv('Windows-1251', 'UTF-8', $dnkom);
        $this->assertStringContainsString('Sender="UNPAID"', $utf);
        $this->assertStringContainsString('Code="TESTCODE"', $utf);
        $this->assertStringContainsString('Code="BLOODCODE"', $utf);

        $notes = json_decode($this->recordedBody(fn (string $body, Request $request) => $request->method() === 'PATCH' && str_contains($request->url(), 'PatientInvoices')), true);
        $comment = json_decode($this->recordedBody(fn (string $body, Request $request) => $request->method() === 'PATCH' && str_contains($request->url(), '/v1/Appointments/')), true);
        $this->assertStringContainsString('555', $notes['notes']);
        $this->assertStringContainsString('15.03.2026 10:30', $notes['notes']);
        $this->assertStringContainsString('555', $comment['comment']);
        $this->assertSame(4, $comment['statusId']);
    }

    public function test_online_payment_waits_for_webhook_before_dnkom(): void
    {
        $this->ready();
        Http::fake(fn (Request $request) => $this->fakeHttp($request));

        $response = $this->postJson('/api/lab-order', $this->payload([
            'payment_mode' => 'online',
            'submission_id' => 'sub-bridge-online',
        ]))->assertOk();

        $this->assertSame('https://yookassa.example/pay', $response->json('confirmation_url'));
        $this->assertNull($this->recordedBodyOrNull(fn (string $body) => str_contains($body, 'query-create-referral')));

        $this->postJson('/api/lab-order/payment', [
            'event' => 'payment.succeeded',
            'object' => [
                'id' => 'pay-1',
                'status' => 'succeeded',
                'metadata' => ['lab_order_id' => (string) $response->json('lab_order_id')],
            ],
        ])->assertOk();
        $this->artisan('order-bridge:push')->assertOk();

        $utf = iconv('Windows-1251', 'UTF-8', $this->recordedBody(fn (string $body) => str_contains($body, 'query-create-referral')));
        $this->assertStringContainsString('Sender="PAID"', $utf);
        $this->assertSame('paid', LabOrder::query()->firstOrFail()->payment_status);
    }

    public function test_forged_webhook_does_not_mark_paid(): void
    {
        $this->ready();
        $this->yookassaStatus = 'pending';
        Http::fake(fn (Request $request) => $this->fakeHttp($request));

        $response = $this->postJson('/api/lab-order', $this->payload([
            'payment_mode' => 'online',
            'submission_id' => 'sub-bridge-forged',
        ]))->assertOk();

        $this->postJson('/api/lab-order/payment', [
            'event' => 'payment.succeeded',
            'object' => [
                'id' => 'pay-1',
                'status' => 'succeeded',
                'metadata' => ['lab_order_id' => (string) $response->json('lab_order_id')],
            ],
        ])->assertOk();

        $this->assertSame('pending', LabOrder::query()->firstOrFail()->payment_status);
    }

    public function test_preview_link_needs_the_exact_long_key(): void
    {
        config(['order_bridge.enabled' => false, 'order_bridge.preview_key' => null]);
        $this->get('/cart/preview/'.self::PREVIEW_KEY)->assertNotFound();

        config(['order_bridge.preview_key' => 'short-key']);
        $this->get('/cart/preview/short-key')->assertNotFound();

        config(['order_bridge.preview_key' => self::PREVIEW_KEY]);
        $this->get('/cart/preview/wrong-'.self::PREVIEW_KEY)->assertNotFound();

        $this->get('/cart/preview/'.self::PREVIEW_KEY)
            ->assertRedirect('/cart')
            ->assertCookie(\App\Support\OrderBridge\PreviewAccess::COOKIE, null, false);
    }

    public function test_preview_cookie_turns_the_bridge_on_only_for_that_browser(): void
    {
        config(['order_bridge.enabled' => false, 'order_bridge.preview_key' => self::PREVIEW_KEY]);
        Http::fake();

        $this->get('/cart')->assertNotFound();
        $this->postJson('/api/lab-order', $this->payload())->assertNotFound();

        $cookie = $this->get('/cart/preview/'.self::PREVIEW_KEY)
            ->getCookie(\App\Support\OrderBridge\PreviewAccess::COOKIE, false)->getValue();
        $this->assertNotSame(self::PREVIEW_KEY, $cookie);

        $this->withUnencryptedCookie(\App\Support\OrderBridge\PreviewAccess::COOKIE, $cookie)
            ->get('/cart')->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('orderBridge.enabled', true));
        $this->withUnencryptedCookie(\App\Support\OrderBridge\PreviewAccess::COOKIE, $cookie)
            ->withCredentials()->postJson('/api/lab-order', $this->payload())->assertOk();

        $order = LabOrder::query()->firstOrFail();
        $this->assertTrue($order->is_test);
        $this->assertTrue($order->patient->is_test);
        $this->assertStringStartsWith('TEST ', Order::query()->findOrFail($order->site_order_id)->customer_name);

        // Другой браузер (без cookie) и подделанная cookie — сайт как сейчас.
        $this->withUnencryptedCookie(\App\Support\OrderBridge\PreviewAccess::COOKIE, 'forged')
            ->get('/cart')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_preview_order_goes_to_medmis_and_dnkom_marked_test(): void
    {
        $this->ready();
        config(['order_bridge.enabled' => false, 'order_bridge.preview_key' => self::PREVIEW_KEY]);
        Http::fake(fn (Request $request) => $this->fakeHttp($request));

        $cookie = $this->get('/cart/preview/'.self::PREVIEW_KEY)
            ->getCookie(\App\Support\OrderBridge\PreviewAccess::COOKIE, false)->getValue();
        $this->withUnencryptedCookie(\App\Support\OrderBridge\PreviewAccess::COOKIE, $cookie)
            ->withCredentials()->postJson('/api/lab-order', $this->payload(['payment_mode' => 'on_site']))->assertOk();
        $this->artisan('order-bridge:push')->assertOk();

        $patient = json_decode($this->recordedBody(fn (string $body, Request $request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/v1/Patients')), true);
        $this->assertStringStartsWith('TEST ', $patient['lastname']);
        $this->assertSame('|TEST|', $patient['tags']);

        $utf = iconv('Windows-1251', 'UTF-8', $this->recordedBody(fn (string $body) => str_contains($body, 'query-create-referral')));
        $this->assertStringContainsString('TEST: тестовый заказ', $utf);
        $this->assertSame('555', LabOrder::query()->firstOrFail()->dnkom_nr);
    }

    public function test_poll_stores_encrypted_pdf_and_marks_done(): void
    {
        $this->ready();
        Http::fake(fn (Request $request) => $this->fakeHttp($request, true));
        $this->postJson('/api/lab-order', $this->payload(['submission_id' => 'sub-bridge-poll']))->assertOk();
        $this->artisan('order-bridge:push')->assertOk();
        $this->artisan('order-bridge:poll-results')->assertOk();

        $lab = LabOrder::query()->firstOrFail();
        $this->assertSame('done', $lab->status);
        $blank = LabResultBlank::query()->firstOrFail();
        $this->assertStringStartsWith('%PDF', Crypt::decryptString(
            (string) \Illuminate\Support\Facades\Storage::disk('local')->get($blank->storage_path)
        ));
        $this->assertTrue(DB::table('lab_patient_access_logs')->where('action', 'read_pdf')->exists());
    }

    public function test_dictionary_sync_follows_version_and_stays_quiet_when_disabled(): void
    {
        $this->ready();
        Http::fake(fn (Request $request) => $this->fakeHttp($request));
        $this->artisan('order-bridge:sync-dictionaries')->assertOk();
        $this->artisan('order-bridge:sync-dictionaries')->assertOk();

        $version = 0;
        $full = 0;
        foreach (Http::recorded() as [$request]) {
            $body = $request->body();
            if (str_contains($body, 'query-dictionaries-version')) {
                $version++;
            } elseif (str_contains($body, 'query-dictionaries')) {
                $full++;
            }
        }
        $this->assertSame(2, $version);
        $this->assertSame(1, $full);
        $this->assertSame('10600002', DB::table('lab_dictionary_snapshots')->value('version'));

        Http::fake();
        config(['order_bridge.enabled' => false]);
        $this->artisan('order-bridge:sync-dictionaries')->assertOk();
        Http::assertNothingSent();
    }

    private function payload(array $extra = []): array
    {
        return $extra + [
            'full_name' => 'Иванов Иван Иванович',
            'phone' => '+7 (999) 123-45-67',
            'email' => 'ivan@example.com',
            'birth_date' => '01.02.1990',
            'sex' => 'male',
            'visit_date' => '15.03.2026',
            'visit_time' => '10:30',
            'payment_mode' => 'on_site',
            'agreed_to_terms' => true,
            'consent_lab' => true,
            'submission_id' => 'sub-bridge-0001',
        ];
    }

    private function ready(): void
    {
        config([
            'order_bridge.enabled' => true,
            'order_bridge.medmis.base_url' => 'https://medmis.test/apix',
            'order_bridge.medmis.login' => 'medmis-user',
            'order_bridge.medmis.password' => 'medmis-secret',
            'order_bridge.medmis.system_name' => 'alex',
            'order_bridge.medmis.branch_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'order_bridge.medmis.schedule_id' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
            'order_bridge.medmis.employee_specialization_id' => 'cccccccc-cccc-cccc-cccc-cccccccccccc',
            'order_bridge.medmis.service_test_id' => 'dddddddd-dddd-dddd-dddd-dddddddddddd',
            'order_bridge.medmis.service_blood_id' => 'eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee',
            'order_bridge.medmis.pause_ms' => 0,
            'order_bridge.medmis.login_pause_ms' => 0,
            'order_bridge.dnkom.url' => 'https://dnkom.test/lis',
            'order_bridge.dnkom.sender_paid' => 'PAID',
            'order_bridge.dnkom.sender_unpaid' => 'UNPAID',
            'order_bridge.dnkom.receiver' => 'LAB',
            'order_bridge.dnkom.password' => 'dnkom-secret',
            'order_bridge.dnkom.code_test' => 'TESTCODE',
            'order_bridge.dnkom.code_blood' => 'BLOODCODE',
            'order_bridge.yookassa.shop_id' => 'shop',
            'order_bridge.yookassa.secret_key' => 'yk-secret',
        ]);
    }

    private function fakeHttp(Request $request, bool $withResult = false)
    {
        $url = $request->url();
        $method = $request->method();
        $body = $request->body();

        if (str_contains($url, '/v1/Auth/login')) {
            return Http::response(['accessToken' => 'tok', 'refreshToken' => 'ref', 'expiresIn' => 3600]);
        }
        if (str_contains($url, '/v1/Appointments/statuses')) {
            return Http::response(['items' => [
                ['id' => 4, 'name' => 'Создана'],
                ['id' => 5, 'name' => 'В работе'],
                ['id' => 6, 'name' => 'Сделана'],
            ]]);
        }
        if (str_contains($url, '/v1/Patients')) {
            if ($method === 'GET') {
                return Http::response(['items' => []]);
            }

            return Http::response(['id' => '11111111-1111-1111-1111-111111111111']);
        }
        if (str_contains($url, '/v1/PatientInvoices')) {
            return Http::response(['id' => 77]);
        }
        if (str_contains($url, '/v1/Appointments')) {
            return Http::response(['id' => '22222222-2222-2222-2222-222222222222']);
        }
        if (str_contains($url, 'yookassa.ru/v3/payments/') && $method === 'GET') {
            return Http::response([
                'id' => 'pay-1',
                'status' => $this->yookassaStatus,
                'metadata' => ['lab_order_id' => (string) LabOrder::query()->value('id')],
            ]);
        }
        if (str_contains($url, 'yookassa.ru')) {
            return Http::response([
                'id' => 'pay-1',
                'status' => 'pending',
                'confirmation' => ['confirmation_url' => 'https://yookassa.example/pay'],
            ]);
        }
        if (str_contains($url, 'dnkom.test')) {
            if (str_contains($body, 'query-dictionaries-version')) {
                return Http::response('<?xml version="1.0" encoding="UTF-8"?><Message MessageType="dictionaries-version"><Version Version="10600002"/></Message>');
            }
            if (str_contains($body, 'query-dictionaries')) {
                return Http::response('<?xml version="1.0" encoding="UTF-8"?><Message MessageType="dictionaries"><Item Code="x"/></Message>');
            }
            if (str_contains($body, 'query-blank-file')) {
                return Http::response('%PDF-1.4 blank', 200, ['Content-Type' => 'application/pdf']);
            }
            if (str_contains($body, 'query-next-referral-results')) {
                if (! $withResult || $this->nextResults > 0) {
                    return Http::response('<?xml version="1.0" encoding="UTF-8"?><Message MessageType="referral-results"/>');
                }
                $this->nextResults++;

                return Http::response('<?xml version="1.0" encoding="UTF-8"?><Message MessageType="referral-results"><Referral MisId="'.LabOrder::query()->value('id').'" Nr="555" LisId="555"><Blanks><Item BlankId="10" BlankGUID="abc"/></Blanks><Tests><Item Code="TESTCODE" Value="1.2"/></Tests></Referral></Message>');
            }
            if (str_contains($body, 'query-create-referral')) {
                return Http::response('<?xml version="1.0" encoding="UTF-8"?><Message MessageType="result-import-referral"><Referral MisId="1" Nr="555" LisId="555"/></Message>');
            }

            return Http::response('<?xml version="1.0" encoding="UTF-8"?><Message MessageType="ok"/>');
        }

        return Http::response('unexpected', 500);
    }

    private function recordedBody(callable $filter): string
    {
        $body = $this->recordedBodyOrNull($filter);
        $this->assertNotNull($body);

        return $body;
    }

    private function recordedBodyOrNull(callable $filter): ?string
    {
        foreach (Http::recorded() as [$request]) {
            if ($filter($request->body(), $request)) {
                return $request->body();
            }
        }

        return null;
    }
}
