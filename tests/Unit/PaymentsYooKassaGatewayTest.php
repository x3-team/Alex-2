<?php

namespace Tests\Unit;

use App\Payments\Data\Money;
use App\Payments\Data\Receipt;
use App\Payments\Data\ReceiptItem;
use App\Payments\Gateway\PaymentGateway;
use App\Payments\PaymentGatewayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentsYooKassaGatewayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.driver' => 'yookassa',
            'payments.yookassa.shop_id' => '123456',
            'payments.yookassa.secret_key' => 'test_secret',
            'payments.receipt.vat_code' => 1,
            'payments.receipt.tax_system_code' => 2,
        ]);
    }

    private function receipt(): Receipt
    {
        return new Receipt([
            new ReceiptItem('Исследование ALEX2 (аллергочип, 300 аллергокомпонентов)', Money::fromRubles(21900)),
            new ReceiptItem('Взятие крови из вены', Money::fromRubles('300.00')),
        ], 'patient@example.ru', '+7 (999) 123-45-67');
    }

    public function test_money_and_receipt_format(): void
    {
        $this->assertSame(2190000, Money::fromRubles(21900));
        $this->assertSame('219.05', Money::format(21905));
        $this->assertSame('79991234567', Receipt::normalizePhone('8 (999) 123-45-67'));
        $this->assertNull(Receipt::normalizePhone('123'));

        $receipt = $this->receipt()->toYooKassa(config('payments.receipt'));
        $this->assertSame(['email' => 'patient@example.ru', 'phone' => '79991234567'], $receipt['customer']);
        $this->assertCount(2, $receipt['items']);
        $this->assertSame(['value' => '21900.00', 'currency' => 'RUB'], $receipt['items'][0]['amount']);
        $this->assertSame('full_prepayment', $receipt['items'][1]['payment_mode']);
        $this->assertSame('service', $receipt['items'][1]['payment_subject']);
        $this->assertSame(1, $receipt['items'][1]['vat_code']);
        $this->assertSame(2, $receipt['tax_system_code']);
    }

    public function test_receipt_needs_contact_and_positive_items(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Receipt([new ReceiptItem('ALEX2', 100)]);
    }

    public function test_create_payment_request(): void
    {
        Http::fake(['https://api.yookassa.ru/v3/payments' => Http::response([
            'id' => '2d3e7f2a-000f-5000-8000-1a2b3c4d5e6f',
            'status' => 'pending',
            'amount' => ['value' => '22200.00', 'currency' => 'RUB'],
            'confirmation' => ['type' => 'redirect', 'confirmation_url' => 'https://yoomoney.ru/checkout/payments/v2/contract?orderId=2d3e'],
            'test' => true,
        ])]);

        $payment = app(PaymentGateway::class)->createPayment('uuid-1', 2220000, 'Заказ ALEX2 №15', 'https://alexallergotest.ru/cart/order/abc', $this->receipt(), ['order' => '15']);

        $this->assertSame('pending', $payment->status);
        $this->assertSame(2220000, $payment->amountKopecks);
        $this->assertTrue($payment->test);
        $this->assertStringStartsWith('https://yoomoney.ru/', $payment->confirmationUrl);

        Http::assertSent(fn (Request $r) => $r->header('Idempotence-Key')[0] === 'uuid-1'
            && $r->header('Authorization')[0] === 'Basic '.base64_encode('123456:test_secret')
            && $r['amount'] === ['value' => '22200.00', 'currency' => 'RUB']
            && $r['capture'] === true
            && $r['confirmation'] === ['type' => 'redirect', 'return_url' => 'https://alexallergotest.ru/cart/order/abc']
            && count($r['receipt']['items']) === 2
            && $r['metadata'] === ['order' => '15']);
    }

    public function test_errors(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push(['type' => 'error', 'code' => 'invalid_request', 'description' => 'Receipt is missing'], 400)
            ->push(['type' => 'error', 'code' => 'internal_server_error'], 500)
            ->push(['type' => 'error', 'code' => 'too_many_requests'], 429)]);

        foreach ([[400, false], [500, true], [429, true]] as [$status, $retryable]) {
            try {
                app(PaymentGateway::class)->getPayment('x');
                $this->fail("expected exception for {$status}");
            } catch (PaymentGatewayException $e) {
                $this->assertSame($status, $e->httpStatus);
                $this->assertSame($retryable, $e->retryable);
            }
        }
    }

    public function test_connection_error_is_retryable_and_keys_are_required(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));
        try {
            app(PaymentGateway::class)->getPayment('x');
            $this->fail('expected exception');
        } catch (PaymentGatewayException $e) {
            $this->assertTrue($e->retryable);
        }

        config(['payments.yookassa.secret_key' => '']);
        $this->app->forgetInstance(PaymentGateway::class);
        $this->expectException(PaymentGatewayException::class);
        app(PaymentGateway::class)->getPayment('x');
    }

    public function test_refund_and_settlement_receipt(): void
    {
        Http::fake([
            'https://api.yookassa.ru/v3/refunds' => Http::response(['id' => 'r-1', 'status' => 'succeeded', 'amount' => ['value' => '22200.00', 'currency' => 'RUB']]),
            'https://api.yookassa.ru/v3/receipts' => Http::response(['id' => 'rc-1', 'status' => 'pending']),
        ]);

        $refund = app(PaymentGateway::class)->createRefund('ref-1', 'pay-1', 2220000, 'Отмена заказа', $this->receipt());
        $this->assertSame('succeeded', $refund->status);

        $this->assertSame('rc-1', app(PaymentGateway::class)->createSettlementReceipt('settle-1', 'pay-1', $this->receipt()));

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/refunds')
            && $r['payment_id'] === 'pay-1'
            && $r['amount']['value'] === '22200.00'
            && count($r['receipt']['items']) === 2);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/receipts')
            && $r['type'] === 'payment'
            && $r['send'] === true
            && $r['settlements'] === [['type' => 'prepayment', 'amount' => ['value' => '22200.00', 'currency' => 'RUB']]]
            && $r['items'][0]['payment_mode'] === 'full_payment');
    }
}
