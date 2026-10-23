<?php

namespace Tests\Unit;

use App\Models\Payments\Payment;
use App\Models\Payments\PaymentRefund;
use App\Models\User;
use App\Payments\Data\Money;
use App\Payments\Data\Receipt;
use App\Payments\Data\ReceiptItem;
use App\Payments\Events\PaymentCanceled;
use App\Payments\Events\PaymentSucceeded;
use App\Payments\Events\RefundSucceeded;
use App\Payments\Gateway\FakeGateway;
use App\Payments\Gateway\PaymentGateway;
use App\Payments\PaymentGatewayException;
use App\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PaymentsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['payments.driver' => 'fake', 'app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }

    private function receipt(): Receipt
    {
        return new Receipt([
            new ReceiptItem('Исследование ALEX2', Money::fromRubles(21900)),
            new ReceiptItem('Взятие крови из вены', Money::fromRubles(300)),
        ], 'patient@example.ru');
    }

    private function pay(): Payment
    {
        return app(PaymentService::class)->create(null, $this->receipt(), 'Заказ ALEX2', 'https://alexallergotest.ru/cart/order/x');
    }

    public function test_create_takes_amount_from_receipt_and_hides_contact(): void
    {
        $payment = $this->pay();

        $this->assertSame(Payment::PENDING, $payment->status);
        $this->assertSame(2220000, $payment->amount_kopecks);
        $this->assertStringContainsString('fake_payment=', $payment->confirmation_url);
        $this->assertCount(2, $payment->receipt_items);

        // E-mail для чека в БД только зашифрованным и не уходит в сериализацию.
        $raw = DB::table('payments')->value('receipt_contact');
        $this->assertStringNotContainsString('patient@example.ru', $raw);
        $this->assertSame('patient@example.ru', $payment->fresh()->receipt_contact['email']);
        $this->assertArrayNotHasKey('receipt_contact', $payment->toArray());
    }

    public function test_success_event_fires_once(): void
    {
        Event::fake([PaymentSucceeded::class, PaymentCanceled::class]);
        $payment = $this->pay();

        app(PaymentService::class)->sync($payment);
        Event::assertNotDispatched(PaymentSucceeded::class);

        FakeGateway::complete($payment->provider_payment_id, 'succeeded');
        app(PaymentService::class)->sync($payment);
        app(PaymentService::class)->sync($payment);

        $this->assertSame(Payment::SUCCEEDED, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->paid_at);
        Event::assertDispatchedTimes(PaymentSucceeded::class, 1);
    }

    public function test_canceled_payment_is_final(): void
    {
        Event::fake([PaymentCanceled::class, PaymentSucceeded::class]);
        $payment = $this->pay();

        FakeGateway::complete($payment->provider_payment_id, 'canceled');
        app(PaymentService::class)->sync($payment);
        FakeGateway::complete($payment->provider_payment_id, 'succeeded');
        app(PaymentService::class)->sync($payment);

        $this->assertSame(Payment::CANCELED, $payment->fresh()->status);
        $this->assertSame('expired_on_confirmation', $payment->fresh()->cancellation_reason);
        Event::assertDispatchedTimes(PaymentCanceled::class, 1);
        Event::assertNotDispatched(PaymentSucceeded::class);
    }

    public function test_gateway_failure_keeps_payment_and_retry_uses_same_key(): void
    {
        app(PaymentGateway::class)->failNext(new PaymentGatewayException('Нет ответа от ЮKassa', retryable: true));

        try {
            $this->pay();
            $this->fail('expected exception');
        } catch (PaymentGatewayException) {
        }

        $payment = Payment::first();
        $this->assertSame(Payment::NEW, $payment->status);
        $this->assertSame('Нет ответа от ЮKassa', $payment->last_error);

        $payment = app(PaymentService::class)->start($payment, 'https://alexallergotest.ru/cart/order/x');
        $this->assertSame(Payment::PENDING, $payment->status);
        $this->assertNull($payment->last_error);
        // Повтор с тем же ключом не создаёт второй платёж в ЮKassa.
        $this->assertSame($payment->provider_payment_id, app(PaymentGateway::class)->createPayment($payment->uuid, 2220000, 'x', 'https://x', $this->receipt())->id);
    }

    public function test_full_refund_and_no_double_refund(): void
    {
        Event::fake([RefundSucceeded::class]);
        $payment = $this->pay();
        FakeGateway::complete($payment->provider_payment_id);
        app(PaymentService::class)->sync($payment);

        $refund = app(PaymentService::class)->refund($payment->fresh(), 'Отмена до сдачи крови');

        $this->assertSame(PaymentRefund::SUCCEEDED, $refund->status);
        $this->assertSame(2220000, $refund->amount_kopecks);
        $this->assertSame(2220000, $payment->fresh()->refunded_kopecks);
        Event::assertDispatchedTimes(RefundSucceeded::class, 1);

        $this->expectException(\InvalidArgumentException::class);
        app(PaymentService::class)->refund($payment->fresh(), 'ещё раз');
    }

    public function test_unpaid_payment_cannot_be_refunded_or_settled(): void
    {
        $payment = $this->pay();

        $this->assertNull(app(PaymentService::class)->settle($payment)->settled_at);
        $this->expectException(\InvalidArgumentException::class);
        app(PaymentService::class)->refund($payment, 'x');
    }

    public function test_settlement_receipt_once(): void
    {
        $payment = $this->pay();
        FakeGateway::complete($payment->provider_payment_id);
        app(PaymentService::class)->sync($payment);

        $first = app(PaymentService::class)->settle($payment->fresh());
        $this->assertNotNull($first->settled_at);
        $id = $first->settlement_receipt_id;

        $this->assertSame($id, app(PaymentService::class)->settle($first->fresh())->settlement_receipt_id);
    }

    public function test_sync_command_picks_up_pending_payments(): void
    {
        $payment = $this->pay();
        Payment::whereKey($payment->id)->update(['synced_at' => now()->subMinutes(5)]);
        FakeGateway::complete($payment->provider_payment_id);

        $this->artisan('payments:sync')->assertSuccessful();

        $this->assertSame(Payment::SUCCEEDED, $payment->fresh()->status);
    }

    public function test_admin_pages_and_refund(): void
    {
        $payment = $this->pay();
        FakeGateway::complete($payment->provider_payment_id);
        app(PaymentService::class)->sync($payment);

        $this->get('/admin/payments')->assertRedirect();
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user)->get('/admin/payments')->assertStatus(403);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->withoutVite();
        $this->actingAs($admin)->get('/admin/payments')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Payments/Index')
                ->where('payments.data.0.status', 'succeeded')
                ->where('payments.data.0.refundable', true)
                ->missing('payments.data.0.receipt_contact'));

        $this->actingAs($admin)->post(route('admin.payments.refund', $payment), ['reason' => 'Отмена'])
            ->assertSessionHas('success');
        $this->assertSame($admin->id, PaymentRefund::first()->user_id);
    }
}
