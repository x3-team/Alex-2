<?php

namespace App\Support\OrderBridge;

use App\Models\LabOrder;
use App\Models\LabOutboundJob;
use Illuminate\Support\Facades\DB;

class LabOrderPush
{
    public function __construct(
        private MedmisClient $medmis,
        private DnkomClient $dnkom,
    ) {}

    public function enqueue(LabOrder $order): void
    {
        $max = (int) config('order_bridge.max_attempts');
        $row = LabOutboundJob::query()->firstOrNew([
            'lab_order_id' => $order->id,
            'step' => 'medmis_dnkom',
        ]);
        if ($row->exists && ($row->status === 'sent' || ($row->status === 'failed' && $row->attempts >= $max))) {
            return;
        }
        $row->status = 'pending';
        if (! $row->exists) {
            $row->attempts = 0;
        }
        $row->save();

        if (! app()->runningInConsole()) {
            $rowId = $row->id;
            defer(function () use ($rowId) {
                app(self::class)->run($rowId);
            });
        }
    }

    public function run(int $rowId): void
    {
        if (! BridgeConfig::enabled() || ! BridgeConfig::outboundReady()) {
            return;
        }

        $claimed = DB::transaction(function () use ($rowId) {
            $row = LabOutboundJob::query()->lockForUpdate()->find($rowId);
            if (! $row || $row->status === 'sent') {
                return false;
            }
            if ($row->status === 'processing' && $row->updated_at && $row->updated_at->gt(now()->subMinutes(10))) {
                return false;
            }
            $row->forceFill(['status' => 'processing'])->save();

            return true;
        });
        if (! $claimed) {
            return;
        }

        $row = LabOutboundJob::query()->find($rowId);
        $order = $row ? LabOrder::query()->find($row->lab_order_id) : null;
        if (! $row || ! $order) {
            return;
        }
        if ($order->payment_mode === 'online' && $order->payment_status !== 'paid') {
            $row->forceFill(['status' => 'pending'])->save();

            return;
        }

        try {
            $this->send($order);
            $row->forceFill(['status' => 'sent', 'reason' => null])->save();
            if ($order->status === 'failed') {
                $order->forceFill(['status' => 'created'])->save();
            }
            $order->forceFill(['failure_reason' => null])->save();
        } catch (LabBridgeException $e) {
            $this->fail($row, $order, $e->reason);
        } catch (\Throwable $e) {
            $this->fail($row, $order, 'transport');
        }
    }

    public function resurrect(): void
    {
        if (! BridgeConfig::enabled() || ! BridgeConfig::outboundReady()) {
            return;
        }

        LabOrder::query()
            ->where('status', 'failed')
            ->where('failure_reason', 'config_incomplete')
            ->where(function ($query) {
                $query->where('payment_mode', 'on_site')
                    ->orWhere('payment_status', 'paid');
            })
            ->orderBy('id')
            ->limit(20)
            ->get()
            ->each(function (LabOrder $order) {
                $order->forceFill(['status' => 'created', 'failure_reason' => null])->save();
                $this->enqueue($order);
            });
    }

    public function reclaim(): void
    {
        LabOutboundJob::query()
            ->where('status', 'processing')
            ->where('updated_at', '<', now()->subMinutes(10))
            ->update(['status' => 'pending']);
    }

    private function send(LabOrder $order): void
    {
        $order->load(['patient', 'lines']);
        $patientId = $order->patient?->medmis_patient_id;
        if ($order->dnkom_nr && $order->medmis_dnkom_reported_at && $patientId && $order->medmis_appointment_id && $order->medmis_invoice_id) {
            return;
        }
        if (! $order->patient) {
            throw new LabBridgeException('medmis_http');
        }

        AccessLogger::write($order->lab_patient_id, $order->id, 'send_medmis', 'order-bridge:push');
        $this->snapshotCodes($order);

        if (! $patientId) {
            $patientId = $this->medmis->ensurePatient($order->patient);
        }

        $statusId = $this->medmis->statusIdFor('created');
        if (! $order->medmis_appointment_id) {
            $appointmentId = $this->medmis->createAppointment($order, $patientId, $statusId);
            $order->forceFill(['medmis_appointment_id' => $appointmentId])->save();
        }
        if (! $order->medmis_invoice_id) {
            $invoiceId = $this->medmis->createInvoice($order, $patientId, (string) $order->medmis_appointment_id);
            $order->forceFill(['medmis_invoice_id' => $invoiceId])->save();
        }

        if (! $order->dnkom_nr) {
            AccessLogger::write($order->lab_patient_id, $order->id, 'send_dnkom', 'order-bridge:push');
            $sender = DnkomXml::sender($order);
            $order->forceFill(['dnkom_sender' => $sender])->save();
            $raw = $this->dnkom->exchange(DnkomXml::referral($order));
            $dom = DnkomXml::document($raw);
            if (! $dom || DnkomXml::messageError($dom)) {
                throw new LabBridgeException('dnkom_rejected');
            }
            $referrals = DnkomXml::referrals($dom);
            $lis = $referrals[0]['lis_id'] ?? $referrals[0]['nr'] ?? null;
            if (! is_string($lis) || $lis === '') {
                throw new LabBridgeException('dnkom_rejected');
            }
            $order->forceFill(['dnkom_nr' => $lis])->save();
        }

        if (! $order->medmis_dnkom_reported_at) {
            $note = VisitNote::text($order, $order->dnkom_nr);
            $this->medmis->patchAppointment((string) $order->medmis_appointment_id, $note, $statusId);
            $this->medmis->patchInvoice((int) $order->medmis_invoice_id, $patientId, $note);
            $order->forceFill([
                'medmis_dnkom_reported_at' => now(),
                'medmis_status' => $statusId !== null ? 'created' : $order->medmis_status,
            ])->save();
        }
    }

    private function snapshotCodes(LabOrder $order): void
    {
        foreach ($order->lines as $line) {
            $codeKey = $line->role === 'blood_draw' ? 'order_bridge.dnkom.code_blood' : 'order_bridge.dnkom.code_test';
            $serviceKey = $line->role === 'blood_draw' ? 'order_bridge.medmis.service_blood_id' : 'order_bridge.medmis.service_test_id';
            $dirty = false;
            if (! is_string($line->dnkom_code) || trim($line->dnkom_code) === '') {
                $line->dnkom_code = trim((string) config($codeKey));
                $dirty = true;
            }
            if (! is_string($line->medmis_service_id) || trim($line->medmis_service_id) === '') {
                $line->medmis_service_id = trim((string) config($serviceKey));
                $dirty = true;
            }
            if ($dirty) {
                $line->save();
            }
        }
        $order->unsetRelation('lines');
        $order->load('lines');
    }

    private function fail(LabOutboundJob $row, LabOrder $order, string $reason): void
    {
        $attempts = $row->attempts + 1;
        $max = (int) config('order_bridge.max_attempts');
        $row->forceFill([
            'attempts' => $attempts,
            'reason' => $reason,
            'status' => $attempts >= $max ? 'failed' : 'pending',
        ])->save();
        $order->forceFill([
            'failure_reason' => $reason,
            'status' => $attempts >= $max ? 'failed' : $order->status,
        ])->save();
    }
}
