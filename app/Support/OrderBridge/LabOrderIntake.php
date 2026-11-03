<?php

namespace App\Support\OrderBridge;

use App\Models\LabOrder;
use App\Models\LabOrderLine;
use App\Models\LabPatient;
use App\Support\CartCatalog;
use App\Support\LeadIntake;
use App\Support\LeadQuizPayload;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LabOrderIntake
{
    public function __construct(private YooKassaClient $payments, private LabOrderPush $push) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{lab_order: LabOrder, confirmation_url: ?string}
     */
    public function accept(Request $request, array $data): array
    {
        $submissionId = $data['submission_id'] ?? null;
        if (is_string($submissionId) && $submissionId !== '') {
            $existing = LabOrder::query()->where('submission_id', $submissionId)->first();
            if ($existing) {
                return [
                    'lab_order' => $existing,
                    'confirmation_url' => $this->resumePayment($existing),
                ];
            }
        }

        try {
            $order = DB::transaction(fn () => $this->create($request, $data));
        } catch (QueryException $e) {
            if (is_string($submissionId) && $submissionId !== '') {
                $existing = LabOrder::query()->where('submission_id', $submissionId)->first();
                if ($existing) {
                    return [
                        'lab_order' => $existing,
                        'confirmation_url' => $existing->yookassa_confirmation_url,
                    ];
                }
            }
            throw $e;
        }

        return [
            'lab_order' => $order->refresh(),
            'confirmation_url' => $this->afterCreate($order),
        ];
    }

    private function afterCreate(LabOrder $order): ?string
    {
        if (! BridgeConfig::outboundReady()) {
            $order->forceFill([
                'status' => 'failed',
                'failure_reason' => 'config_incomplete',
            ])->save();

            return null;
        }

        if ($order->payment_mode === 'online') {
            return $this->resumePayment($order);
        }

        $this->push->enqueue($order);

        return null;
    }

    private function resumePayment(LabOrder $order): ?string
    {
        if ($order->payment_mode !== 'online' || $order->payment_status === 'paid') {
            return null;
        }
        if (! BridgeConfig::outboundReady()) {
            return $order->yookassa_confirmation_url;
        }
        if (is_string($order->yookassa_confirmation_url) && $order->yookassa_confirmation_url !== '') {
            return $order->yookassa_confirmation_url;
        }
        if (! BridgeConfig::paymentReady()) {
            $order->forceFill([
                'payment_status' => 'failed',
                'status' => 'failed',
                'failure_reason' => 'payment_not_configured',
            ])->save();

            return null;
        }

        try {
            return $this->payments->start($order);
        } catch (LabBridgeException $e) {
            $order->forceFill([
                'payment_status' => 'failed',
                'status' => 'failed',
                'failure_reason' => $e->reason,
            ])->save();

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function create(Request $request, array $data): LabOrder
    {
        $isTest = PreviewAccess::active($request);
        $hash = LabPatientIdentity::phoneHash((string) $data['phone_digits']);
        $patient = LabPatient::query()->where('phone_lookup', $hash)->where('is_test', $isTest)->first();
        if ($patient && $patient->birth_date !== $data['birth_ymd']) {
            throw ValidationException::withMessages([
                'phone' => 'Телефон уже указан у другого пациента.',
            ]);
        }

        $actor = $request->user() ? 'user:'.$request->user()->id : 'site';
        $created = false;
        if (! $patient) {
            $patient = LabPatient::query()->create([
                'last_name' => $data['last_name'],
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'],
                'birth_date' => $data['birth_ymd'],
                'sex' => $data['sex'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'is_test' => $isTest,
            ]);
            $created = true;
        }

        $catalog = CartCatalog::mainProduct();
        $bloodTitle = (string) config('order_bridge.blood_draw_title');
        $bloodPrice = (float) config('order_bridge.blood_draw_price');

        $order = LabOrder::query()->create([
            'lab_patient_id' => $patient->id,
            'submission_id' => $data['submission_id'] ?? null,
            'payment_mode' => $data['payment_mode'],
            'payment_status' => $data['payment_mode'] === 'online' ? 'pending' : 'not_required',
            'visit_date' => $data['visit_ymd'],
            'visit_time' => $data['visit_time'],
            'status' => 'created',
            'consent_at' => now(),
            'consent_text_version' => (string) config('order_bridge.consent_text_version'),
            'is_test' => $isTest,
        ]);

        foreach ([
            ['role' => 'test', 'title' => $catalog['title'], 'price' => (float) $catalog['price'], 'code' => 'order_bridge.dnkom.code_test', 'service' => 'order_bridge.medmis.service_test_id'],
            ['role' => 'blood_draw', 'title' => $bloodTitle, 'price' => $bloodPrice, 'code' => 'order_bridge.dnkom.code_blood', 'service' => 'order_bridge.medmis.service_blood_id'],
        ] as $line) {
            $code = trim((string) config($line['code']));
            $service = trim((string) config($line['service']));
            LabOrderLine::query()->create([
                'lab_order_id' => $order->id,
                'role' => $line['role'],
                'title' => $line['title'],
                'price' => $line['price'],
                'dnkom_code' => $code === '' ? null : $code,
                'medmis_service_id' => $service === '' ? null : $service,
            ]);
        }

        AccessLogger::write($patient->id, $order->id, $created ? 'create' : 'read', $actor);

        $pay = $data['payment_mode'] === 'online' ? 'онлайн' : 'на месте';
        $when = \Carbon\Carbon::parse($data['visit_ymd'])->format('d.m.Y').' '.$data['visit_time'];
        $fullName = trim($data['last_name'].' '.$data['first_name'].' '.($data['middle_name'] ?? ''));
        $testNote = $isTest ? 'TEST: тестовый заказ по скрытой ссылке, не выполнять. ' : '';
        $site = LeadIntake::accept($request, [
            'customer_name' => ($isTest ? 'TEST ' : '').$fullName,
            'customer_phone' => (string) $data['phone'],
            'customer_email' => $data['email'] ?? null,
            'comment' => "{$testNote}Оплата: {$pay}. Желаемое время визита: {$when}. Лабораторный заказ #{$order->id}.",
            'items' => array_merge([
                'type' => 'test_order',
                'title' => 'Запись на тест',
                'payment_mode' => $data['payment_mode'],
                'lines' => [
                    ['title' => $catalog['title'], 'price' => (float) $catalog['price']],
                    ['title' => $bloodTitle, 'price' => $bloodPrice],
                ],
            ], LeadQuizPayload::store(
                $data['quiz_answers'] ?? [],
                $data['quiz_result_id'] ?? null,
                $data['quiz_result_title'] ?? null,
            )),
            'total_amount' => (float) $catalog['price'] + $bloodPrice,
            'status' => 'new',
        ], 'test_order');

        $order->forceFill(['site_order_id' => $site->id])->save();

        return $order;
    }
}
