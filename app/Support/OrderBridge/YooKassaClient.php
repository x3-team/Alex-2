<?php

namespace App\Support\OrderBridge;

use App\Models\LabOrder;
use Illuminate\Support\Facades\Http;

class YooKassaClient
{
    public function start(LabOrder $order): string
    {
        $amount = number_format((float) $order->lines()->sum('price'), 2, '.', '');
        if ((float) $amount <= 0) {
            throw new LabBridgeException('payment_amount');
        }

        try {
            $response = Http::timeout(20)
                ->withBasicAuth(
                    (string) config('order_bridge.yookassa.shop_id'),
                    (string) config('order_bridge.yookassa.secret_key')
                )
                ->withHeaders(['Idempotence-Key' => 'lab-order-'.$order->id])
                ->acceptJson()
                ->post('https://api.yookassa.ru/v3/payments', [
                    'amount' => ['value' => $amount, 'currency' => 'RUB'],
                    'capture' => true,
                    'confirmation' => [
                        'type' => 'redirect',
                        'return_url' => rtrim((string) config('app.url'), '/').'/cart',
                    ],
                    'description' => 'Оплата заказа на исследование',
                    'metadata' => ['lab_order_id' => (string) $order->id],
                ]);
        } catch (LabBridgeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new LabBridgeException('transport');
        }

        if (! $response->successful()) {
            throw new LabBridgeException('payment_http');
        }

        $url = $response->json('confirmation.confirmation_url');
        $id = $response->json('id');
        if (! is_string($url) || $url === '' || ! is_string($id) || $id === '') {
            throw new LabBridgeException('payment_http');
        }

        $order->forceFill([
            'yookassa_payment_id' => $id,
            'yookassa_confirmation_url' => $url,
            'payment_status' => 'pending',
        ])->save();

        return $url;
    }
}
