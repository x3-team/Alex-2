<?php

namespace App\Support\OrderBridge;

use App\Models\LabPatientAccessLog;

class AccessLogger
{
    public static function write(int $patientId, ?int $orderId, string $action, string $actor): void
    {
        $ip = null;
        $userAgent = null;
        if (! app()->runningInConsole()) {
            $ip = request()->ip();
            $agent = request()->userAgent();
            $userAgent = is_string($agent) ? mb_substr($agent, 0, 255) : null;
        }

        LabPatientAccessLog::query()->create([
            'lab_patient_id' => $patientId,
            'lab_order_id' => $orderId,
            'actor' => mb_substr($actor, 0, 64),
            'action' => $action,
            'ip' => $ip,
            'user_agent' => $userAgent,
        ]);
    }
}
