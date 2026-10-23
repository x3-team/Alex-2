<?php

namespace App\Lis\AlfaLab;

use App\Lis\AlfaLab\Data\Payload;
use App\Lis\AlfaLab\Exceptions\LisException;
use App\Models\Lis\LabService;
use App\Models\Lis\LisSyncState;
use Illuminate\Support\Facades\DB;

/**
 * Синхронизация номенклатуры ЛИС (§9.2.3, §11.21–11.24): исследования и профили,
 * цены контрагента, обязательные доп. поля и приостановленные исследования.
 * Сначала спрашиваем версию; полную выгрузку качаем, только если она изменилась.
 */
class DictionarySync
{
    public const VERSION_KEY = 'dictionaries_version';
    public const SYNCED_AT_KEY = 'dictionaries_synced_at';

    public function __construct(private readonly AlfaLabClient $client) {}

    /** @return array{version: int, changed: bool, services: int} */
    public function sync(bool $force = false): array
    {
        $version = $this->client->dictionariesVersion();
        $known = LisSyncState::value(self::VERSION_KEY);

        if (! $force && $known !== null && (int) $known === $version) {
            LisSyncState::put(self::SYNCED_AT_KEY, now()->toIso8601String());

            return ['version' => $version, 'changed' => false, 'services' => LabService::count()];
        }

        $data = $this->client->dictionaries();
        $count = DB::transaction(fn () => $this->store($data, $version));

        LisSyncState::put(self::VERSION_KEY, (string) $version);
        LisSyncState::put(self::SYNCED_AT_KEY, now()->toIso8601String());

        return ['version' => $version, 'changed' => true, 'services' => $count];
    }

    /**
     * Готовность к приёму заказов: обе услуги (ALEX2 и взятие биоматериала) есть в
     * номенклатуре, не удалены и не приостановлены.
     *
     * @return list<string> проблемы; пустой список — можно принимать заказы
     */
    public static function problems(): array
    {
        $problems = [];
        $services = [
            'alex2' => ['ALEX2', 'ALFALAB_ALEX2_CODE'],
            'sampling' => ['взятие биоматериала', 'ALFALAB_SAMPLING_CODE'],
        ];
        foreach ($services as $key => [$label, $env]) {
            $code = config('alfalab.services.'.$key);
            if (! $code) {
                $problems[] = "Не задан код услуги «{$label}» ({$env})";

                continue;
            }
            $service = LabService::where('code', $code)->first();
            if (! $service) {
                $problems[] = "Услуги «{$label}» с кодом {$code} нет в номенклатуре ЛИС";
            } elseif (! $service->isOrderable()) {
                $problems[] = "Услуга «{$label}» ({$code}) удалена или приостановлена лабораторией"
                    .($service->suspended_reason ? ': '.$service->suspended_reason : '');
            }
        }

        return $problems;
    }

    private function store(array $data, int $version): int
    {
        $fieldsRequired = fn (?array $item) => array_values(array_filter(array_map(
            fn (array $f) => Payload::bool($f, 'mandatory') ? Payload::str($f, 'fieldCode') : null,
            Payload::list($item, 'fields'),
        )));

        $seen = [];
        $upsert = function (array $item, string $kind, array $biomaterials) use (&$seen, $version, $fieldsRequired) {
            $code = Payload::str($item, 'code');
            if ($code === null) {
                return;
            }
            $seen[] = $code;
            LabService::updateOrCreate(['code' => $code], [
                'kind' => $kind,
                'lis_id' => Payload::int($item, 'id'),
                'name' => Payload::str($item, 'name') ?? $code,
                'biomaterial_codes' => array_values(array_unique(array_filter($biomaterials))),
                'required_fields' => $fieldsRequired($item),
                'removed' => Payload::bool($item, 'removed'),
                'dictionary_version' => $version,
                // Цены и приостановка выставляются ниже из своих справочников.
                'price' => null,
                'min_duration' => null,
                'max_duration' => null,
                'duration_unit' => null,
                'suspended_from' => null,
                'suspended_till' => null,
                'suspended_reason' => null,
            ]);
        };

        foreach (Payload::list($data, 'analyses') as $item) {
            $upsert($item, 'analysis', array_map(fn ($b) => Payload::str($b, 'biomaterialCode'), Payload::list($item, 'analysisBiomaterials')));
        }
        foreach (Payload::list($data, 'panels') as $item) {
            $upsert($item, 'panel', array_map(fn ($b) => Payload::str($b, 'biomaterialCode'), Payload::list($item, 'panelAnalyses')));
        }

        foreach (Payload::list($data, 'prices') as $price) {
            $code = Payload::str($price, 'serviceCode');
            if ($code === null) {
                continue;
            }
            LabService::where('code', $code)->update([
                'price' => Payload::float($price, 'price'),
                'min_duration' => Payload::int($price, 'minDuration'),
                'max_duration' => Payload::int($price, 'maxDuration'),
                'duration_unit' => match (Payload::int($price, 'durationUnit')) { 1 => 'hours', 2 => 'days', default => null },
            ]);
        }

        foreach (Payload::list($data, 'delayedTargets') as $delayed) {
            $code = Payload::str($delayed, 'targetCode');
            if ($code === null) {
                continue;
            }
            LabService::where('code', $code)->update([
                'suspended_from' => Payload::date($delayed, 'startDate')?->toDateString(),
                'suspended_till' => Payload::date($delayed, 'endDate')?->toDateString(),
                'suspended_reason' => Payload::str($delayed, 'reasonName'),
            ]);
        }

        if ($seen === []) {
            // Пустая выгрузка — скорее сбой, чем лаборатория без услуг. Кэш не трогаем.
            throw new LisException(LisException::PROTOCOL, 'Выгрузка номенклатуры пуста');
        }

        // Пропавшее из выгрузки считаем удалённым (выгрузка содержит всю актуальную номенклатуру).
        LabService::whereNotIn('code', $seen)->update(['removed' => true]);

        return count($seen);
    }
}
