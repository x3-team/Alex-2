<?php

namespace Tests\Unit;

use App\Models\Payments\Payment;
use App\Models\Payments\PaymentWebhookLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentsWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const YOOKASSA_IP = '185.71.76.10';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'payments.enabled' => true,
            'payments.driver' => 'yookassa',
            'payments.yookassa.shop_id' => '123456',
            'payments.yookassa.secret_key' => 'test_secret',
        ]);
    }

    private function payment(): Payment
    {
        return Payment::create([
            'uuid' => (string) Str::uuid(),
            'provider' => 'yookassa',
            'provider_payment_id' => 'pay-1',
            'status' => Payment::PENDING,
            'amount_kopecks' => 2220000,
            'description' => 'Заказ ALEX2',
            'receipt_items' => [['description' => 'ALEX2', 'amount_kopecks' => 2220000, 'quantity' => 1]],
            'receipt_contact' => ['email' => 'patient@example.ru'],
        ]);
    }

    private function notify(string $event, string $id, string $ip = self::YOOKASSA_IP, string $status = 'succeeded')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/api/payments/yookassa', [
            'type' => 'notification',
            'event' => $event,
            'object' => ['id' => $id, 'status' => $status, 'amount' => ['value' => '1.00', 'currency' => 'RUB']],
        ]);
    }

    public function test_disabled_returns_404(): void
    {
        config(['payments.enabled' => false]);

        $this->notify('payment.succeeded', 'pay-1')->assertNotFound();
        $this->assertSame('disabled', PaymentWebhookLog::first()->outcome);
    }

    public function test_foreign_ip_is_rejected(): void
    {
        $this->payment();
        Http::fake();

        $this->notify('payment.succeeded', 'pay-1', '203.0.113.5')->assertForbidden();

        Http::assertNothingSent();
        $this->assertSame(Payment::PENDING, Payment::first()->status);
    }

    public function test_status_is_taken_from_api_not_from_body(): void
    {
        $this->payment();
        Http::fake(['https://api.yookassa.ru/v3/payments/pay-1' => Http::response([
            'id' => 'pay-1', 'status' => 'pending', 'amount' => ['value' => '22200.00', 'currency' => 'RUB'],
        ])]);

        // В теле «succeeded», но ЮKassa по API говорит «pending» — платёж не оплачен.
        $this->notify('payment.succeeded', 'pay-1')->assertOk();

        $this->assertSame(Payment::PENDING, Payment::first()->status);
    }

    public function test_succeeded_payment_is_applied(): void
    {
        $this->payment();
        Http::fake(['https://api.yookassa.ru/v3/payments/pay-1' => Http::response([
            'id' => 'pay-1', 'status' => 'succeeded', 'amount' => ['value' => '22200.00', 'currency' => 'RUB'], 'test' => true,
        ])]);

        $this->notify('payment.succeeded', 'pay-1')->assertOk();

        $this->assertSame(Payment::SUCCEEDED, Payment::first()->status);
        $this->assertSame('processed', PaymentWebhookLog::latest('id')->first()->outcome);
    }

    public function test_unknown_payment_is_acknowledged(): void
    {
        Http::fake();

        $this->notify('payment.succeeded', 'someone-else')->assertOk();

        Http::assertNothingSent();
        $this->assertSame('unknown_object', PaymentWebhookLog::first()->outcome);
    }

    public function test_bad_body_and_api_outage(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => self::YOOKASSA_IP])
            ->postJson('/api/payments/yookassa', ['event' => 'payment.succeeded'])
            ->assertStatus(400);

        $this->payment();
        Http::fake(['*' => Http::response(['type' => 'error', 'code' => 'internal_server_error'], 500)]);

        // ЮKassa недоступна — отвечаем 500, уведомление придёт повторно.
        $this->notify('payment.succeeded', 'pay-1')->assertStatus(500);
        $this->assertSame(Payment::PENDING, Payment::first()->status);
    }
}
