<?php

namespace App\Support;

use App\Models\LabOffice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Импорт и шаблон списка офисов в CSV. Понимает разделители «;» и «,», UTF-8 (с BOM)
 * и Windows-1251 (как сохраняет Excel), заголовки по-русски или по-английски.
 * Офис ищется по коду в ЛИС, а без кода — по городу и адресу; найденный обновляется.
 * Офисы, которых нет в файле, не трогаются (выключать их — руками в админке).
 */
class LabOfficeCsv
{
    /** Колонка БД => заголовок шаблона и допустимые синонимы. */
    public const COLUMNS = [
        'city' => ['Город', 'city'],
        'name' => ['Название', 'name', 'Офис'],
        'address' => ['Адрес', 'address'],
        'metro' => ['Метро', 'metro'],
        'phone' => ['Телефон', 'phone'],
        'hours_weekdays' => ['Часы будни', 'hours_weekdays', 'Пн-Пт'],
        'hours_weekend' => ['Часы выходные', 'hours_weekend', 'Сб-Вс'],
        'lat' => ['Широта', 'lat', 'latitude'],
        'lng' => ['Долгота', 'lng', 'lon', 'longitude'],
        'lis_code' => ['Код в ЛИС', 'lis_code', 'Код офиса'],
        'accepts_alex2' => ['Принимает ALEX2', 'accepts_alex2'],
        'is_active' => ['Активен', 'is_active'],
        'badge' => ['Бейдж', 'badge', 'Акция'],
        'note' => ['Примечание', 'note'],
    ];

    public const MAX_ROWS = 2000;

    public static function template(): string
    {
        $header = array_map(fn (array $names) => $names[0], self::COLUMNS);
        $example = ['Москва', 'ALEX, Маршала Бирюзова 9', 'ул. Маршала Бирюзова, 9', 'Октябрьское поле', '+7 495 000-00-00',
            '9:00 – 19:00', 'Выходной', '55.793500', '37.493900', '', 'да', 'да', '', 'Вход со двора'];

        return "\xEF\xBB\xBF".self::line($header).self::line($example);
    }

    /**
     * @return array{created: int, updated: int, errors: list<string>}
     */
    public static function import(string $content): array
    {
        $content = self::toUtf8($content);
        $lines = preg_split('/\r\n|\n|\r/', $content);
        $lines = array_values(array_filter($lines, fn ($l) => trim($l) !== ''));
        if ($lines === []) {
            return ['created' => 0, 'updated' => 0, 'errors' => ['Файл пустой']];
        }
        if (count($lines) - 1 > self::MAX_ROWS) {
            return ['created' => 0, 'updated' => 0, 'errors' => ['Слишком много строк: не больше '.self::MAX_ROWS]];
        }

        $delimiter = substr_count($lines[0], ';') >= substr_count($lines[0], ',') ? ';' : ',';
        $map = self::headerMap(str_getcsv($lines[0], $delimiter, '"', '\\'));
        $missing = array_diff(['city', 'name', 'address'], array_values($map));
        if ($missing !== []) {
            $names = array_map(fn ($c) => self::COLUMNS[$c][0], $missing);

            return ['created' => 0, 'updated' => 0, 'errors' => ['Нет обязательных колонок: '.implode(', ', $names)]];
        }

        $created = $updated = 0;
        $errors = [];

        DB::transaction(function () use ($lines, $delimiter, $map, &$created, &$updated, &$errors) {
            foreach (array_slice($lines, 1) as $i => $line) {
                $rowNumber = $i + 2;
                $cells = str_getcsv($line, $delimiter, '"', '\\');
                $row = [];
                foreach ($map as $index => $column) {
                    $row[$column] = isset($cells[$index]) ? trim($cells[$index]) : null;
                }

                $data = self::normalize($row);
                $validator = Validator::make($data, self::rules());
                if ($validator->fails()) {
                    $errors[] = "Строка {$rowNumber}: ".implode(' ', $validator->errors()->all());

                    continue;
                }

                $office = self::findExisting($data);
                if ($office) {
                    $office->update($data);
                    $updated++;
                } else {
                    LabOffice::create($data);
                    $created++;
                }
            }
        });

        return ['created' => $created, 'updated' => $updated, 'errors' => $errors];
    }

    public static function rules(): array
    {
        return [
            'city' => 'required|string|max:100',
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'metro' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'hours_weekdays' => 'nullable|string|max:100',
            'hours_weekend' => 'nullable|string|max:100',
            'lat' => 'nullable|numeric|between:-90,90|required_with:lng',
            'lng' => 'nullable|numeric|between:-180,180|required_with:lat',
            'lis_code' => 'nullable|string|max:64',
            'accepts_alex2' => 'boolean',
            'is_active' => 'boolean',
            'badge' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:2000',
        ];
    }

    private static function normalize(array $row): array
    {
        $data = [];
        foreach (array_keys(self::COLUMNS) as $column) {
            if (! array_key_exists($column, $row)) {
                continue;
            }
            $value = $row[$column];
            $value = $value === '' ? null : $value;
            if (in_array($column, ['lat', 'lng'], true) && $value !== null) {
                $value = str_replace(',', '.', $value);
            }
            if (in_array($column, ['accepts_alex2', 'is_active'], true)) {
                $value = $value === null ? true : in_array(mb_strtolower($value), ['1', 'да', 'yes', 'true', '+'], true);
            }
            $data[$column] = $value;
        }

        return $data;
    }

    private static function findExisting(array $data): ?LabOffice
    {
        if (! empty($data['lis_code'])) {
            return LabOffice::where('lis_code', $data['lis_code'])->first();
        }

        $key = fn (string $s) => mb_strtolower(preg_replace('/\s+/u', ' ', trim($s)));

        return LabOffice::whereNull('lis_code')
            ->where('city', $data['city'])
            ->get()
            ->first(fn (LabOffice $o) => $key($o->address) === $key($data['address']));
    }

    /** @param list<string> $header */
    private static function headerMap(array $header): array
    {
        $lookup = [];
        foreach (self::COLUMNS as $column => $names) {
            foreach ($names as $name) {
                $lookup[mb_strtolower($name)] = $column;
            }
        }

        $map = [];
        foreach ($header as $index => $title) {
            $title = mb_strtolower(trim(str_replace("\xEF\xBB\xBF", '', (string) $title)));
            if (isset($lookup[$title]) && ! in_array($lookup[$title], $map, true)) {
                $map[$index] = $lookup[$title];
            }
        }

        return $map;
    }

    private static function toUtf8(string $content): string
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            return substr($content, 3);
        }

        return mb_check_encoding($content, 'UTF-8') ? $content : mb_convert_encoding($content, 'UTF-8', 'Windows-1251');
    }

    private static function line(array $cells): string
    {
        return implode(';', array_map(fn ($c) => '"'.str_replace('"', '""', (string) $c).'"', $cells))."\r\n";
    }
}
