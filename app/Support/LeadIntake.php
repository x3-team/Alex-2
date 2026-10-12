<?php

namespace App\Support;

use App\Models\Order;
use App\Support\Analytics\AcceptedLead;
use App\Support\Analytics\OutboxSender;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use function Illuminate\Support\defer;

/**
 * Приём заявки с сайта. Повтор с тем же submission_id (двойной клик, повтор после таймаута)
 * возвращает уже созданную заявку: без второй записи, второго письма и второй конверсии.
 * Заявка и факт lead_accepted сохраняются в одной транзакции; письмо и отправка в
 * аналитику — после ответа и не могут её откатить.
 */
class LeadIntake
{
    public const SUBMISSION_RULE = ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]{8,64}$/'];

    public static function accept(Request $request, array $attributes, string $formId): Order
    {
        $submissionId = $request->input('submission_id') ?: null;

        if ($submissionId && ($existing = Order::where('submission_id', $submissionId)->first())) {
            return $existing;
        }

        try {
            [$order, $events] = DB::transaction(function () use ($request, $attributes, $formId, $submissionId) {
                $order = Order::create($attributes + ['submission_id' => $submissionId]);

                return [$order, AcceptedLead::record($order, $request, $formId)];
            });
        } catch (QueryException $e) {
            // Параллельный повтор успел первым — отдаём его заявку.
            if ($submissionId && ($existing = Order::where('submission_id', $submissionId)->first())) {
                return $existing;
            }
            throw $e;
        }

        LeadMailer::notify($order);

        defer(function () use ($events) {
            $sender = app(OutboxSender::class);
            foreach ($events as $event) {
                $sender->deliver($event);
            }
        });

        return $order;
    }
}
