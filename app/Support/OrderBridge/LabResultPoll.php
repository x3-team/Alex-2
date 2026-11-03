<?php

namespace App\Support\OrderBridge;

use App\Models\LabOrder;
use App\Models\LabResultBlank;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class LabResultPoll
{
    public function __construct(
        private DnkomClient $dnkom,
        private MedmisClient $medmis,
    ) {}

    public function run(): void
    {
        if (! BridgeConfig::backgroundEnabled() || ! BridgeConfig::outboundReady()) {
            return;
        }

        $senders = array_values(array_unique(array_filter([
            trim((string) config('order_bridge.dnkom.sender_unpaid')),
            trim((string) config('order_bridge.dnkom.sender_paid')),
        ])));
        foreach ($senders as $sender) {
            $this->drain($sender);
        }
        $this->syncMedmisStatuses();
    }

    private function drain(string $sender): void
    {
        $limit = (int) config('order_bridge.poll_limit');
        for ($i = 0; $i < $limit; $i++) {
            $raw = $this->dnkom->exchange(DnkomXml::envelope('query-next-referral-results', $sender));
            $dom = DnkomXml::document($raw);
            if (! $dom) {
                return;
            }
            $referrals = DnkomXml::referrals($dom);
            if ($referrals === []) {
                return;
            }
            foreach ($referrals as $referral) {
                $order = $this->match($referral);
                if (! $order) {
                    return;
                }
                $this->apply($order, $dom, $referral, $sender);
                $nr = (string) ($referral['nr'] ?: $referral['lis_id']);
                $lis = (string) ($referral['lis_id'] ?: $referral['nr']);
                $this->dnkom->exchange(DnkomXml::ack($sender, (string) $order->id, $nr, $lis));
            }
        }
    }

    /**
     * @param  array{mis_id: ?string, nr: ?string, lis_id: ?string}  $referral
     */
    private function match(array $referral): ?LabOrder
    {
        if ($referral['mis_id'] && ctype_digit($referral['mis_id'])) {
            $order = LabOrder::query()->find((int) $referral['mis_id']);
            if ($order) {
                return $order;
            }
        }
        $nr = $referral['lis_id'] ?: $referral['nr'];
        if (! $nr) {
            return null;
        }

        return LabOrder::query()->where('dnkom_nr', $nr)->first();
    }

    /**
     * @param  array{mis_id: ?string, nr: ?string, lis_id: ?string}  $referral
     */
    private function apply(LabOrder $order, \DOMDocument $dom, array $referral, string $sender): void
    {
        $nr = $referral['lis_id'] ?: $referral['nr'];
        if ($nr && ! $order->dnkom_nr) {
            $order->dnkom_nr = $nr;
        }
        $status = DnkomXml::resultStatus($dom);
        if ($this->outranks($status, (string) $order->status)) {
            $order->status = $status;
        }
        $order->save();
        AccessLogger::write($order->lab_patient_id, $order->id, 'read', 'order-bridge:poll-results');

        foreach (DnkomXml::blanks($dom) as $blank) {
            $this->fetchBlank($order, $blank, $sender);
        }
    }

    /**
     * @param  array{id: string, guid: string}  $blank
     */
    private function fetchBlank(LabOrder $order, array $blank, string $sender): void
    {
        $row = LabResultBlank::query()->firstOrCreate([
            'lab_order_id' => $order->id,
            'blank_id' => $blank['id'],
            'blank_guid' => $blank['guid'],
        ]);
        if ($row->storage_path) {
            return;
        }

        $raw = $this->dnkom->exchange(DnkomXml::blank($sender, $blank['id'], $blank['guid']));
        if (! str_starts_with(ltrim($raw), '%PDF')) {
            throw new LabBridgeException('dnkom_blank');
        }

        $path = 'lab-results/'.$order->id.'/'.$blank['id'].'.bin';
        Storage::disk('local')->put($path, Crypt::encryptString($raw));
        $row->forceFill([
            'storage_path' => $path,
            'fetched_at' => now(),
        ])->save();
        AccessLogger::write($order->lab_patient_id, $order->id, 'read_pdf', 'order-bridge:poll-results');
    }

    private function syncMedmisStatuses(): void
    {
        LabOrder::query()
            ->whereNotNull('medmis_appointment_id')
            ->where(function ($query) {
                $query->whereNull('medmis_status')
                    ->orWhereColumn('medmis_status', '!=', 'status');
            })
            ->orderBy('id')
            ->limit(20)
            ->get()
            ->each(function (LabOrder $order) {
                $statusId = $this->medmis->statusIdFor((string) $order->status);
                if ($statusId === null || ! $order->medmis_appointment_id) {
                    return;
                }
                $this->medmis->patchAppointment(
                    $order->medmis_appointment_id,
                    VisitNote::text($order, $order->dnkom_nr),
                    $statusId
                );
                $order->forceFill(['medmis_status' => $order->status])->save();
            });
    }

    private function outranks(string $new, string $current): bool
    {
        $rank = ['failed' => 0, 'created' => 1, 'in_progress' => 2, 'done' => 3];

        return ($rank[$new] ?? 0) > ($rank[$current] ?? 0);
    }
}
