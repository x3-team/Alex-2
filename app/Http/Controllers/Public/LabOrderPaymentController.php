<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\LabOrder;
use App\Support\OrderBridge\BridgeConfig;
use App\Support\OrderBridge\LabBridgeException;
use App\Support\OrderBridge\LabOrderPush;
use App\Support\OrderBridge\YooKassaClient;
use Illuminate\Http\Request;

class LabOrderPaymentController extends Controller
{
    public function __invoke(Request $request, LabOrderPush $push, YooKassaClient $payments)
    {
        if (! BridgeConfig::backgroundEnabled()) {
            abort(404);
        }

        $paymentId = $request->input('object.id');
        $status = $request->input('object.status');
        $labOrderId = $request->input('object.metadata.lab_order_id');
        if ($status !== 'succeeded' || ! is_string($paymentId) || $paymentId === '' || ! ctype_digit((string) $labOrderId)) {
            return response()->json(['ok' => true]);
        }

        $order = LabOrder::query()->find((int) $labOrderId);
        if (! $order || ($order->yookassa_payment_id && $order->yookassa_payment_id !== $paymentId)) {
            return response()->json(['ok' => true]);
        }

        if ($order->payment_status !== 'paid') {
            // Вебхук открыт без авторизации: оплату подтверждаем запросом в API ЮKassa.
            try {
                $payment = $payments->fetch($paymentId);
            } catch (LabBridgeException $e) {
                return response()->json(['ok' => false], 503);
            }
            if ($payment['status'] !== 'succeeded' || $payment['lab_order_id'] !== (string) $order->id) {
                return response()->json(['ok' => true]);
            }

            $order->forceFill([
                'payment_status' => 'paid',
                'yookassa_payment_id' => $paymentId,
            ])->save();
            if (BridgeConfig::outboundReady()) {
                $push->enqueue($order);
            }
        }

        return response()->json(['ok' => true]);
    }
}
