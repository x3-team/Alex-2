<?php

namespace App\Lis\AlfaLab\Transport;

use App\Lis\AlfaLab\Data\Payload;
use App\Lis\AlfaLab\Exceptions\LisException;
use App\Models\Lis\LisFakeReferral;
use Illuminate\Support\Facades\DB;

/**
 * Эмулятор сервиса ЛИС для работы без стенда лаборатории (ALFALAB_DRIVER=fake).
 * Отвечает так, как описано в протоколе v3.1: номер направления, предупреждение о
 * неизвестном коде, ошибка дубля, очередь результатов с подтверждением, PDF-бланк.
 * Данные пациента не хранит: только номер, misId и коды услуг.
 * Шаги «кровь взята» и «результат готов» двигает команда lis:fake-advance.
 */
class FakeTransport implements LisTransport
{
    /** @var list<LisResponse|LisException> подменённые ответы для тестов, по одному на вызов */
    private array $scripted = [];

    public function __construct(private readonly array $config) {}

    public function pushNext(LisResponse|LisException $next): void
    {
        $this->scripted[] = $next;
    }

    public function send(string $messageType, array $body): LisResponse
    {
        if ($this->scripted !== []) {
            $next = array_shift($this->scripted);
            if ($next instanceof LisException) {
                throw $next;
            }

            return $next;
        }

        return match ($messageType) {
            'query-create-referral' => $this->create($body),
            'query-referral-remove' => $this->remove(Payload::arr($body, 'query') ?? []),
            'query-referral-results' => $this->results(Payload::arr($body, 'query') ?? []),
            'query-next-referral-results' => $this->next(),
            'result-referral-results-import' => $this->ack($body),
            'query-blank-file' => $this->blank(Payload::arr($body, 'query') ?? []),
            'query-dictionaries-version' => self::json(['messageType' => 'dictionaries-version', 'version' => ['version' => 1]]),
            'query-dictionaries' => self::json($this->dictionaries()),
            default => new LisResponse(404, ''),
        };
    }

    private function create(array $body): LisResponse
    {
        $patient = Payload::arr($body, 'patient');
        $referral = Payload::arr($body, 'referral') ?? [];
        $misId = Payload::str($referral, 'misId');
        $nr = Payload::str($referral, 'nr');

        if (Payload::str($patient, 'lastName') === null) {
            return self::error('Не указана фамилия пациента');
        }
        if (Payload::str($referral, 'date') === null) {
            return self::error('Не указана дата направления');
        }
        if (! Payload::bool($referral, 'autoNr')) {
            $length = (int) ($this->config['referral_nr']['length'] ?? 10);
            if ($nr === null || ! preg_match('/^[1-9]\d{'.($length - 1).'}$/', $nr)) {
                return self::error("Номер направления {$nr} не соответствует правилам нумерации");
            }
            if (LisFakeReferral::where('nr', $nr)->exists()) {
                return self::error("Направление с номером {$nr} уже существует", ['referral' => ['misId' => $misId, 'nr' => $nr]]);
            }
        }
        if ($misId !== null && LisFakeReferral::where('mis_id', $misId)->exists()) {
            return self::error("Направление с идентификатором МИС {$misId} уже существует");
        }

        $known = array_filter([
            $this->config['fake']['alex2_code'] ?? null,
            $this->config['fake']['sampling_code'] ?? null,
            $this->config['services']['alex2'] ?? null,
            $this->config['services']['sampling'] ?? null,
        ]);
        $warnings = [];
        $orders = [];
        foreach (Payload::list($referral, 'orders') ?: Payload::list($body, 'orders') as $order) {
            $code = Payload::str($order, 'code');
            if ($code === null || ! in_array($code, $known, true)) {
                $warnings[] = ['text' => "Исследование с кодом {$code} не найдено при поиске по коду"];

                continue;
            }
            $orders[] = ['code' => $code, 'biomaterialCode' => Payload::str($order, 'biomaterialCode')];
        }

        $row = DB::transaction(function () use ($nr, $misId, $orders) {
            $row = LisFakeReferral::create(['nr' => $nr ?? 'pending-'.uniqid(), 'mis_id' => $misId, 'orders' => $orders]);
            if (str_starts_with($row->nr, 'pending-')) {
                $length = (int) ($this->config['referral_nr']['length'] ?? 10);
                $row->update(['nr' => '7'.str_pad((string) $row->id, $length - 1, '0', STR_PAD_LEFT)]);
            }

            return $row;
        });

        return self::json(array_filter([
            'messageType' => 'result-import-referral',
            'warnings' => $warnings ?: null,
            'referral' => ['misId' => $misId, 'nr' => $row->nr, 'lisId' => $row->id],
        ]));
    }

    private function remove(array $query): LisResponse
    {
        $row = $this->find($query);
        if (! $row) {
            return self::error('Направление не найдено');
        }
        if ($row->stage !== 'created') {
            return self::error('Направление уже в работе, коррекция невозможна');
        }
        $row->update(['stage' => 'removed', 'version' => $row->version + 1, 'queued' => true]);

        return self::json(['messageType' => 'result-import-referral', 'referral' => ['misId' => $row->mis_id, 'nr' => $row->nr, 'lisId' => $row->id]]);
    }

    private function results(array $query): LisResponse
    {
        $row = $this->find($query);
        if (! $row) {
            return Payload::bool($query, 'returnState')
                ? self::json(['messageType' => 'referral-results', 'state' => 1])
                : self::error('Направление не найдено');
        }

        return self::json($this->resultsPayload($row) + ['state' => 3]);
    }

    private function next(): LisResponse
    {
        $row = LisFakeReferral::where('queued', true)->orderBy('updated_at')->orderBy('id')->first();

        return $row ? self::json($this->resultsPayload($row)) : new LisResponse(200, '');
    }

    private function ack(array $body): LisResponse
    {
        $referral = Payload::arr($body, 'referral') ?? [];
        $version = Payload::int(Payload::arr($body, 'version'), 'version');
        $row = $this->find($referral);
        // Подтверждение старой версии не снимает направление с очереди.
        if ($row && $version === $row->version && Payload::int($referral, 'state') !== 4) {
            $row->update(['queued' => false]);
        }

        return new LisResponse(200, '');
    }

    private function blank(array $query): LisResponse
    {
        $guid = Payload::str($query, 'blankGuid');
        $row = $guid && preg_match('/^fake-(\d+)$/', $guid, $m) ? LisFakeReferral::find((int) $m[1]) : null;
        if (! $row || $row->stage !== 'done') {
            return self::error('Бланк не найден');
        }

        return new LisResponse(200, self::pdf($row->nr), 'application/pdf');
    }

    /** Следующий шаг жизни направления: created → activated → done. */
    public static function advance(LisFakeReferral $row): LisFakeReferral
    {
        $next = match ($row->stage) {
            'created' => ['stage' => 'activated', 'activated_at' => now()],
            'activated' => ['stage' => 'done', 'done_at' => now()],
            default => null,
        };
        if ($next !== null) {
            $row->update($next + ['version' => $row->version + 1, 'queued' => true]);
        }

        return $row->refresh();
    }

    private function resultsPayload(LisFakeReferral $row): array
    {
        $done = $row->stage === 'done';
        $date = fn ($d) => $d?->copy()->timezone('Europe/Moscow')->format('Y-m-d\TH:i:sP');

        return [
            'messageType' => 'referral-results',
            'version' => ['version' => $row->version],
            'referral' => [
                'lisId' => $row->id,
                'misId' => $row->mis_id,
                'nr' => $row->nr,
                'removed' => $row->stage === 'removed',
                'done' => $done,
                'createDate' => $date($row->created_at),
                'doneDate' => $date($row->done_at),
                'activated' => match ($row->stage) { 'activated', 'done' => 3, default => 1 },
                'activationDate' => $date($row->activated_at),
                'minPlanDate' => $date($row->created_at?->copy()->addDays(3)),
                'maxPlanDate' => $date($row->created_at?->copy()->addDays(5)),
                'manual' => false,
            ],
            'orders' => array_map(fn (array $o) => [
                'code' => $o['code'],
                'biomaterialCode' => $o['biomaterialCode'] ?? null,
                'state' => match ($row->stage) { 'done' => 4, 'removed' => 5, default => 1 },
            ], $row->orders ?? []),
            'blanks' => $done ? [[
                'blankId' => $row->id,
                'BlankGUID' => 'fake-'.$row->id,
                'version' => 1,
                'blankType' => 1,
                'name' => 'Аллергочип ALEX2',
                'done' => true,
                'doneDate' => $date($row->done_at),
                'fileName' => $row->nr.'.pdf',
            ]] : [],
        ];
    }

    private function dictionaries(): array
    {
        $alex2 = $this->config['fake']['alex2_code'] ?? 'ALEX2';
        $sampling = $this->config['fake']['sampling_code'] ?? 'VZ1';

        return [
            'messageType' => 'dictionaries',
            'version' => ['version' => 1],
            'biomaterials' => [['id' => 1, 'code' => '87', 'name' => 'Сыворотка крови']],
            'analyses' => [
                ['id' => 101, 'code' => $alex2, 'name' => 'Аллергочип ALEX2 (300 аллергокомпонентов)', 'analysisBiomaterials' => [['biomaterialCode' => '87', 'isDefault' => true]]],
                ['id' => 102, 'code' => $sampling, 'name' => 'Взятие крови из периферической вены'],
            ],
            'panels' => [],
            'prices' => [
                ['serviceId' => 101, 'serviceCode' => $alex2, 'price' => (float) ($this->config['fake']['alex2_price'] ?? 0), 'minDuration' => 3, 'maxDuration' => 5, 'durationUnit' => 2],
                ['serviceId' => 102, 'serviceCode' => $sampling, 'price' => (float) ($this->config['fake']['sampling_price'] ?? 0)],
            ],
            'delayedTargets' => [],
        ];
    }

    private function find(array $ref): ?LisFakeReferral
    {
        if ($id = Payload::int($ref, 'lisId')) {
            return LisFakeReferral::find($id);
        }
        if ($misId = Payload::str($ref, 'misId')) {
            return LisFakeReferral::where('mis_id', $misId)->first();
        }
        if ($nr = Payload::str($ref, 'nr')) {
            return LisFakeReferral::where('nr', $nr)->first();
        }

        return null;
    }

    private static function json(array $data): LisResponse
    {
        return new LisResponse(200, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    private static function error(string $text, array $extra = []): LisResponse
    {
        return self::json(['error' => $text] + $extra);
    }

    /** Минимальный валидный PDF с номером направления — для проверки выдачи пациенту. */
    private static function pdf(string $nr): string
    {
        $text = "(Test result, referral {$nr})";
        $stream = "BT /F1 18 Tf 72 720 Td {$text} Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }
}
