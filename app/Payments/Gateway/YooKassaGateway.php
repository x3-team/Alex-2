<?php

namespace App\Payments\Gateway;

use App\Payments\Data\GatewayPayment;
use App\Payments\Data\GatewayRefund;
use App\Payments\Data\Money;
use App\Payments\Data\Receipt;
use App\Payments\PaymentGatewayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/** ЮKassa API v3: Basic shopId:secretKey, заголовок Idempotence-Key на каждую операцию. */
class YooKassaGateway implements PaymentGateway
{
    public function __construct(
        private readonly array $config,
        private readonly array $receiptConfig,
    ) {}

    public function createPayment(string $idempotenceKey, int $amountKopecks, string $description, string $returnUrl, Receipt $receipt, array $metadata = []): GatewayPayment
    {
        $data = $this->post('payments', $idempotenceKey, array_filter([
            'amount' => ['value' => Money::format($amountKopecks), 'currency' => 'RUB'],
            'capture' => true,
            'confirmation' => ['type' => 'redirect', 'return_url' => $returnUrl],
            'description' => mb_substr($description, 0, 128),
            'metadata' => $metadata ?: null,
            'receipt' => $receipt->toYooKassa($this->receiptConfig),
        ]));

        return GatewayPayment::fromYooKassa($data);
    }

    public function getPayment(string $paymentId): GatewayPayment
    {
        return GatewayPayment::fromYooKassa($this->get('payments/'.rawurlencode($paymentId)));
    }

    public function createRefund(string $idempotenceKey, string $paymentId, int $amountKopecks, string $description, Receipt $receipt): GatewayRefund
    {
        $data = $this->post('refunds', $idempotenceKey, [
            'payment_id' => $paymentId,
            'amount' => ['value' => Money::format($amountKopecks), 'currency' => 'RUB'],
            'description' => mb_substr($description, 0, 250),
            'receipt' => $receipt->toYooKassa($this->receiptConfig),
        ]);

        return GatewayRefund::fromYooKassa($data);
    }

    public function getRefund(string $refundId): GatewayRefund
    {
        return GatewayRefund::fromYooKassa($this->get('refunds/'.rawurlencode($refundId)));
    }

    public function createSettlementReceipt(string $idempotenceKey, string $paymentId, Receipt $receipt): string
    {
        $body = $receipt->toYooKassa($this->receiptConfig, 'full_payment') + [
            'type' => 'payment',
            'payment_id' => $paymentId,
            'send' => true,
            'settlements' => [[
                'type' => 'prepayment',
                'amount' => ['value' => Money::format($receipt->totalKopecks()), 'currency' => 'RUB'],
            ]],
        ];

        return (string) ($this->post('receipts', $idempotenceKey, $body)['id'] ?? '');
    }

    private function post(string $path, string $idempotenceKey, array $body): array
    {
        return $this->send(fn () => $this->http()->withHeaders(['Idempotence-Key' => $idempotenceKey])->post($this->url($path), $body));
    }

    private function get(string $path): array
    {
        return $this->send(fn () => $this->http()->get($this->url($path)));
    }

    private function http()
    {
        $shopId = (string) ($this->config['shop_id'] ?? '');
        $secret = (string) ($this->config['secret_key'] ?? '');
        if ($shopId === '' || $secret === '') {
            throw new PaymentGatewayException('Не заданы YOOKASSA_SHOP_ID или YOOKASSA_SECRET_KEY');
        }

        return Http::withBasicAuth($shopId, $secret)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout((int) ($this->config['timeout'] ?? 20));
    }

    private function url(string $path): string
    {
        return rtrim((string) ($this->config['api_url'] ?? 'https://api.yookassa.ru/v3'), '/').'/'.$path;
    }

    private function send(callable $request): array
    {
        try {
            /** @var Response $response */
            $response = $request();
        } catch (ConnectionException $e) {
            throw new PaymentGatewayException('Нет ответа от ЮKassa: '.$e->getMessage(), retryable: true);
        }

        if ($response->successful()) {
            return (array) $response->json();
        }

        $status = $response->status();
        $code = $response->json('code');
        $description = $response->json('description') ?? 'Ошибка ЮKassa';

        throw new PaymentGatewayException(
            "ЮKassa {$status}".($code ? " {$code}" : '').": {$description}",
            retryable: $status >= 500 || $status === 429,
            httpStatus: $status,
            errorCode: is_string($code) ? $code : null,
        );
    }
}
