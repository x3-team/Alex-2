<?php

namespace App\Support\OrderBridge;

use App\Models\LabOrder;
use App\Models\LabPatient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MedmisClient
{
    private float $lastCall = 0;

    private ?string $access = null;

    private ?array $statusItems = null;

    public function ensurePatient(LabPatient $patient): string
    {
        if (is_string($patient->medmis_patient_id) && $patient->medmis_patient_id !== '') {
            return $patient->medmis_patient_id;
        }

        $digits = LabPatientIdentity::tenDigits($patient->phone);
        if ($digits === null) {
            throw new LabBridgeException('medmis_phone');
        }

        $found = $this->findPatient($digits, (string) $patient->birth_date);
        if ($found === null) {
            try {
                $found = $this->createPatient($patient, $digits);
            } catch (LabBridgeException $e) {
                if ($e->reason !== 'medmis_conflict') {
                    throw $e;
                }
                $found = $this->findPatient($digits, (string) $patient->birth_date);
                if ($found === null) {
                    throw $e;
                }
            }
        }

        $patient->forceFill(['medmis_patient_id' => $found])->save();

        return $found;
    }

    public function createAppointment(LabOrder $order, string $patientId, ?int $statusId): string
    {
        $from = (string) $order->visit_time;
        $body = [
            'scheduleId' => config('order_bridge.medmis.schedule_id'),
            'employeeSpecializationID' => config('order_bridge.medmis.employee_specialization_id'),
            'timeFrom' => $from,
            'timeTo' => LabPatientIdentity::timeTo($from, (int) config('order_bridge.visit_duration_minutes')),
            'patientId' => $patientId,
            'comment' => VisitNote::text($order, null),
        ];
        if ($statusId !== null) {
            $body['statusId'] = $statusId;
        }

        $data = $this->request('POST', '/v1/Appointments', [], $body);
        $id = $data['id'] ?? null;
        if (! is_string($id) || $id === '') {
            throw new LabBridgeException('medmis_http');
        }

        return $id;
    }

    public function createInvoice(LabOrder $order, string $patientId, string $appointmentId): int
    {
        $services = [];
        foreach ($order->lines as $line) {
            $services[] = [
                'serviceId' => $line->medmis_service_id,
                'amount' => (float) $line->price,
                'count' => 1,
                'discount' => 0,
                'discountAsValue' => false,
            ];
        }

        $visit = \Carbon\Carbon::parse($order->visit_date->format('Y-m-d').' '.$order->visit_time, 'Europe/Moscow')
            ->utc()
            ->format('Y-m-d\TH:i:s');

        $data = $this->request('POST', '/v1/PatientInvoices', [], [
            'branchId' => config('order_bridge.medmis.branch_id'),
            'patientId' => $patientId,
            'appointmentId' => $appointmentId,
            'date' => $visit,
            'notes' => VisitNote::text($order, null),
            'services' => $services,
        ]);
        $id = $data['id'] ?? null;
        if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
            throw new LabBridgeException('medmis_http');
        }

        return (int) $id;
    }

    public function patchAppointment(string $appointmentId, string $comment, ?int $statusId): void
    {
        $body = ['comment' => $comment];
        if ($statusId !== null) {
            $body['statusId'] = $statusId;
        }
        $this->request('PATCH', '/v1/Appointments/'.$appointmentId, [], $body);
    }

    public function patchInvoice(int $invoiceId, string $patientId, string $notes): void
    {
        $this->request('PATCH', '/v1/PatientInvoices/'.$invoiceId, [], [
            'branchId' => config('order_bridge.medmis.branch_id'),
            'patientId' => $patientId,
            'notes' => $notes,
        ]);
    }

    public function statusIdFor(string $local): ?int
    {
        $configured = config('order_bridge.medmis.status_ids.'.$local);
        if (is_int($configured)) {
            return $configured;
        }
        if (is_string($configured) && ctype_digit($configured)) {
            return (int) $configured;
        }

        $hints = [
            'created' => ['создан'],
            'in_progress' => ['работ'],
            'done' => ['сделан', 'выполн'],
        ][$local] ?? [];

        foreach ($this->statuses() as $item) {
            if (! is_array($item) || ! isset($item['id'], $item['name'])) {
                continue;
            }
            $name = mb_strtolower((string) $item['name']);
            foreach ($hints as $hint) {
                if (str_contains($name, $hint)) {
                    return (int) $item['id'];
                }
            }
        }

        return null;
    }

    private function findPatient(string $digits, string $birthDate): ?string
    {
        $data = $this->request('GET', '/v1/Patients', [
            'mobilephone' => $digits,
            'page' => 1,
            'pageSize' => 20,
        ]);
        foreach ($data['items'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $birthday = (string) ($item['birthday'] ?? '');
            $id = $item['id'] ?? null;
            if ($birthday !== '' && str_starts_with($birthday, $birthDate) && is_string($id) && $id !== '') {
                return $id;
            }
        }

        return null;
    }

    private function createPatient(LabPatient $patient, string $digits): string
    {
        $payload = [
            'lastname' => $patient->last_name,
            'firstname' => $patient->first_name,
            'birthday' => $patient->birth_date.'T00:00:00',
            'mobilePhone' => $digits,
            'sex' => $patient->sex === 'female' ? 'Female' : 'Male',
            'agreeEmail' => false,
            'agreeSms' => false,
            'agreeWhatsapp' => false,
            'agreePush' => false,
            'branchId' => config('order_bridge.medmis.branch_id'),
        ];
        if ($patient->middle_name) {
            $payload['middlename'] = $patient->middle_name;
        }
        if ($patient->email) {
            $payload['email'] = $patient->email;
        }

        $data = $this->request('POST', '/v1/Patients', [], $payload);
        $id = $data['id'] ?? null;
        if (! is_string($id) || $id === '') {
            throw new LabBridgeException('medmis_http');
        }

        return $id;
    }

    private function statuses(): array
    {
        if ($this->statusItems !== null) {
            return $this->statusItems;
        }

        try {
            $json = $this->request('GET', '/v1/Appointments/statuses', ['page' => 1, 'pageSize' => 100]);
            $this->statusItems = is_array($json['items'] ?? null) ? $json['items'] : [];
        } catch (LabBridgeException $e) {
            $this->statusItems = [];
        }

        return $this->statusItems;
    }

    private function request(string $method, string $path, array $query = [], ?array $json = null): array
    {
        return $this->send($method, $path, $query, $json, true);
    }

    private function send(string $method, string $path, array $query, ?array $json, bool $retryAuth): array
    {
        $this->pause(false);
        $pending = Http::timeout(20)
            ->withToken($this->accessToken())
            ->accept('application/json; v=1.0');

        try {
            $response = match ($method) {
                'GET' => $pending->get($this->url($path), $query),
                'POST' => $pending->withBody(
                    json_encode($json ?? [], JSON_UNESCAPED_UNICODE),
                    'application/json; v=1.0'
                )->post($this->url($path)),
                'PATCH' => $pending->withBody(
                    json_encode($json ?? [], JSON_UNESCAPED_UNICODE),
                    'application/json; v=1.0'
                )->patch($this->url($path)),
                default => throw new LabBridgeException('medmis_http'),
            };
        } catch (LabBridgeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new LabBridgeException('transport');
        }

        $this->lastCall = microtime(true);

        if ($response->status() === 401 && $retryAuth) {
            Cache::forget($this->cacheKey());
            $this->access = null;

            return $this->send($method, $path, $query, $json, false);
        }
        if ($response->status() === 409) {
            throw new LabBridgeException('medmis_conflict');
        }
        if (! $response->successful()) {
            throw new LabBridgeException('medmis_http');
        }

        $decoded = $response->json();

        return is_array($decoded) ? $decoded : [];
    }

    private function accessToken(): string
    {
        if ($this->access) {
            return $this->access;
        }
        $cached = Cache::get($this->cacheKey());
        if (is_string($cached) && $cached !== '') {
            return $this->access = $cached;
        }

        return $this->login();
    }

    private function login(): string
    {
        $this->pause(true);
        try {
            $response = Http::timeout(20)
                ->accept('application/json; v=1.0')
                ->withBody(json_encode([
                    'login' => config('order_bridge.medmis.login'),
                    'password' => config('order_bridge.medmis.password'),
                    'systemName' => config('order_bridge.medmis.system_name'),
                ], JSON_UNESCAPED_UNICODE), 'application/json; v=1.0')
                ->post($this->url('/v1/Auth/login'));
        } catch (\Throwable $e) {
            throw new LabBridgeException('transport');
        }
        $this->lastCall = microtime(true);

        if (! $response->successful()) {
            throw new LabBridgeException('medmis_auth');
        }
        $token = $response->json('accessToken');
        if (! is_string($token) || $token === '') {
            throw new LabBridgeException('medmis_auth');
        }
        $ttl = (int) $response->json('expiresIn');
        Cache::put($this->cacheKey(), $token, max(60, $ttl > 120 ? $ttl - 60 : 300));

        return $this->access = $token;
    }

    private function pause(bool $login): void
    {
        $ms = (int) config($login ? 'order_bridge.medmis.login_pause_ms' : 'order_bridge.medmis.pause_ms');
        if ($ms <= 0 || $this->lastCall <= 0) {
            return;
        }
        $wait = ($ms / 1000) - (microtime(true) - $this->lastCall);
        if ($wait > 0) {
            usleep((int) round($wait * 1_000_000));
        }
    }

    private function url(string $path): string
    {
        return rtrim((string) config('order_bridge.medmis.base_url'), '/').$path;
    }

    private function cacheKey(): string
    {
        return 'order_bridge.medmis.access.'.sha1(
            (string) config('order_bridge.medmis.base_url').'|'.(string) config('order_bridge.medmis.login')
        );
    }
}
