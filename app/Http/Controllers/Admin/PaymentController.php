<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payments\Payment;
use App\Payments\Data\Money;
use App\Payments\PaymentGatewayException;
use App\Payments\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $payments = Payment::query()
            ->with('refunds')
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Payment $p) => [
                'id' => $p->id,
                'uuid' => $p->uuid,
                'provider' => $p->provider,
                'provider_payment_id' => $p->provider_payment_id,
                'status' => $p->status,
                'amount' => Money::toRubles($p->amount_kopecks),
                'refunded' => Money::toRubles($p->refunded_kopecks),
                'refundable' => $p->refundableKopecks() > 0 && $p->refunded_kopecks === 0,
                'description' => $p->description,
                'items' => array_map(fn ($i) => ['description' => $i['description'], 'amount' => Money::toRubles($i['amount_kopecks'] * ($i['quantity'] ?? 1))], $p->receipt_items),
                'test' => $p->test,
                'cancellation_reason' => $p->cancellation_reason,
                'last_error' => $p->last_error,
                'created_at' => $p->created_at?->toIso8601String(),
                'paid_at' => $p->paid_at?->toIso8601String(),
                'settled_at' => $p->settled_at?->toIso8601String(),
                'refunds' => $p->refunds->map(fn ($r) => [
                    'id' => $r->id,
                    'status' => $r->status,
                    'amount' => Money::toRubles($r->amount_kopecks),
                    'reason' => $r->reason,
                    'last_error' => $r->last_error,
                    'created_at' => $r->created_at?->toIso8601String(),
                ])->all(),
            ]);

        return Inertia::render('Admin/Payments/Index', [
            'payments' => $payments,
            'filters' => ['status' => $request->string('status')->toString()],
            'enabled' => (bool) config('payments.enabled'),
            'driver' => (string) config('payments.driver'),
        ]);
    }

    public function sync(Payment $payment, PaymentService $service): RedirectResponse
    {
        try {
            $service->sync($payment);
        } catch (PaymentGatewayException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Статус платежа обновлён.');
    }

    public function refund(Request $request, Payment $payment, PaymentService $service): RedirectResponse
    {
        $validated = $request->validate(['reason' => 'required|string|max:250']);

        try {
            $service->refund($payment, $validated['reason'], $request->user()?->id);
        } catch (InvalidArgumentException|PaymentGatewayException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Возврат оформлен.');
    }
}
