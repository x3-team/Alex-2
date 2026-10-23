<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Payments\Payment;
use App\Models\Payments\PaymentRefund;
use App\Models\Payments\PaymentWebhookLog;
use App\Payments\PaymentGatewayException;
use App\Payments\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Уведомления ЮKassa (payment.succeeded, payment.canceled, refund.succeeded и др.).
 * Подписи у уведомлений нет, поэтому: принимаем только с адресов ЮKassa, из тела берём
 * лишь id объекта, а статус перечитываем из API. Ответ 200 — «принято»; 500 — ЮKassa
 * повторит уведомление позже (до суток).
 */
class YooKassaWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentService $payments): Response
    {
        $event = is_string($request->input('event')) ? mb_substr($request->input('event'), 0, 48) : null;
        $objectId = is_string($request->input('object.id')) ? mb_substr($request->input('object.id'), 0, 64) : null;
        $log = fn (string $outcome) => PaymentWebhookLog::create([
            'event' => $event, 'object_id' => $objectId, 'outcome' => $outcome, 'ip' => $request->ip(),
        ]);

        if (! config('payments.enabled')) {
            $log('disabled');
            abort(404);
        }

        $allowed = config('payments.yookassa.webhook_ips', []);
        if (config('payments.driver') === 'yookassa' && ! IpUtils::checkIp((string) $request->ip(), $allowed)) {
            $log('rejected_ip');
            abort(403);
        }

        if ($event === null || $objectId === null || $request->input('type') !== 'notification') {
            $log('bad_request');
            abort(400);
        }

        try {
            if (str_starts_with($event, 'payment.')) {
                $payment = Payment::where('provider_payment_id', $objectId)->first();
                if (! $payment) {
                    $log('unknown_object');

                    return response('', 200);
                }
                $payments->sync($payment);
            } elseif (str_starts_with($event, 'refund.')) {
                $refund = PaymentRefund::where('provider_refund_id', $objectId)->first();
                if (! $refund) {
                    $log('unknown_object');

                    return response('', 200);
                }
                $payments->syncRefund($refund);
            }
        } catch (PaymentGatewayException $e) {
            $log('error');
            report($e);
            if ($e->retryable) {
                abort(500);
            }

            return response('', 200);
        }

        $log('processed');

        return response('', 200);
    }
}
