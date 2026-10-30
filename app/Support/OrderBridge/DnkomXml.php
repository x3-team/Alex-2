<?php

namespace App\Support\OrderBridge;

use App\Models\LabOrder;
use App\Models\LabOrderLine;
use DOMDocument;
use DOMElement;

class DnkomXml
{
    public static function referral(LabOrder $order): string
    {
        $order->loadMissing(['patient', 'lines']);
        $patient = $order->patient;
        $date = self::now();
        $patientAttrs = [
            'MisId' => (string) $patient->id,
            'LastName' => (string) $patient->last_name,
            'FirstName' => (string) $patient->first_name,
            'Gender' => $patient->sex === 'female' ? '2' : '1',
            'BirthDate' => self::birth($patient->birth_date),
        ];
        if ($patient->middle_name) {
            $patientAttrs['MiddleName'] = (string) $patient->middle_name;
        }
        $digits = LabPatientIdentity::tenDigits($patient->phone);
        if ($digits) {
            $patientAttrs['Phone'] = '+7'.$digits;
        }
        if ($patient->email) {
            $patientAttrs['Email'] = (string) $patient->email;
        }

        $referralAttrs = [
            'MisId' => (string) $order->id,
            'Date' => $date,
            'AutoNr' => 'true',
            'Comment' => VisitNote::text($order, null),
        ];
        if ($order->visit_date && $order->visit_time) {
            $referralAttrs['SamplingDate'] = $order->visit_date->format('d.m.Y').' '.$order->visit_time.':00';
        }

        $items = '';
        foreach ($order->lines as $line) {
            $items .= '<Item Code="'.self::e(self::codeFor($line)).'"/>';
        }

        $xml = '<?xml version="1.0" encoding="windows-1251"?>'
            .'<Message '.self::attrs(self::message('query-create-referral', self::sender($order), $date)).'>'
            .'<Patient '.self::attrs($patientAttrs).'/>'
            .'<Referral '.self::attrs($referralAttrs).'>'
            .'<Assays><Item><Orders>'.$items.'</Orders></Item></Assays>'
            .'</Referral></Message>';

        return self::encode($xml);
    }

    public static function envelope(string $type, string $sender, string $inner = ''): string
    {
        $xml = '<?xml version="1.0" encoding="windows-1251"?>'
            .'<Message '.self::attrs(self::message($type, $sender, self::now())).'>'
            .$inner
            .'</Message>';

        return self::encode($xml);
    }

    public static function ack(string $sender, string $misId, string $nr, string $lisId): string
    {
        $inner = '<Referral '.self::attrs([
            'MisId' => $misId,
            'Nr' => $nr,
            'LisId' => $lisId,
        ]).'/>';

        return self::envelope('result-referral-results-import', $sender, $inner);
    }

    public static function blank(string $sender, string $blankId, string $blankGuid): string
    {
        $inner = '<Blank '.self::attrs([
            'BlankId' => $blankId,
            'BlankGUID' => $blankGuid,
            'FileType' => '0',
        ]).'/>';

        return self::envelope('query-blank-file', $sender, $inner);
    }

    public static function sender(LabOrder $order): string
    {
        $key = $order->payment_mode === 'online'
            ? 'order_bridge.dnkom.sender_paid'
            : 'order_bridge.dnkom.sender_unpaid';

        return trim((string) config($key));
    }

    public static function codeFor(LabOrderLine $line): string
    {
        if (is_string($line->dnkom_code) && trim($line->dnkom_code) !== '') {
            return trim($line->dnkom_code);
        }
        $key = $line->role === 'blood_draw'
            ? 'order_bridge.dnkom.code_blood'
            : 'order_bridge.dnkom.code_test';

        return trim((string) config($key));
    }

    public static function document(string $raw): ?DOMDocument
    {
        if ($raw === '' || str_starts_with(ltrim($raw), '%PDF')) {
            return null;
        }
        $utf8 = self::toUtf8($raw);
        $utf8 = preg_replace('/encoding\s*=\s*["\'][^"\']+["\']/', 'encoding="UTF-8"', $utf8, 1) ?? $utf8;
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $ok = $dom->loadXML($utf8);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $ok ? $dom : null;
    }

    public static function toUtf8(string $raw): string
    {
        $head = substr($raw, 0, 240);
        if (preg_match('/encoding\s*=\s*["\']([^"\']+)["\']/i', $head, $match) && ! preg_match('/utf-?8/i', $match[1])) {
            $converted = @iconv($match[1], 'UTF-8//IGNORE', $raw);
            if ($converted !== false) {
                return $converted;
            }
        }

        return $raw;
    }

    public static function messageError(DOMDocument $dom): bool
    {
        $message = $dom->getElementsByTagName('Message')->item(0);

        return $message instanceof DOMElement && trim($message->getAttribute('Error')) !== '';
    }

    /**
     * @return list<array{mis_id: ?string, nr: ?string, lis_id: ?string}>
     */
    public static function referrals(DOMDocument $dom): array
    {
        $out = [];
        foreach ($dom->getElementsByTagName('Referral') as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }
            $out[] = [
                'mis_id' => self::blankToNull($node->getAttribute('MisId')),
                'nr' => self::blankToNull($node->getAttribute('Nr')),
                'lis_id' => self::blankToNull($node->getAttribute('LisId')),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{id: string, guid: string}>
     */
    public static function blanks(DOMDocument $dom): array
    {
        $out = [];
        foreach ($dom->getElementsByTagName('Item') as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }
            $id = trim($node->getAttribute('BlankId'));
            $guid = trim($node->getAttribute('BlankGUID'));
            if ($id !== '' && $guid !== '') {
                $out[] = ['id' => $id, 'guid' => $guid];
            }
        }

        return $out;
    }

    public static function resultStatus(DOMDocument $dom): string
    {
        $referral = $dom->getElementsByTagName('Referral')->item(0);
        if ($referral instanceof DOMElement) {
            $label = mb_strtolower($referral->getAttribute('Status').$referral->getAttribute('State'));
            if (str_contains($label, 'сделан') || str_contains($label, 'выполн')) {
                return 'done';
            }
            if (str_contains($label, 'работ')) {
                return 'in_progress';
            }
            if (str_contains($label, 'создан')) {
                return 'created';
            }
        }

        $seen = false;
        $complete = true;
        foreach ($dom->getElementsByTagName('Tests') as $group) {
            foreach ($group->getElementsByTagName('Item') as $item) {
                if (! $item instanceof DOMElement) {
                    continue;
                }
                $seen = true;
                $value = trim($item->getAttribute('Value'));
                if ($value === '' && $item->getElementsByTagName('Value')->length > 0) {
                    $value = trim($item->getElementsByTagName('Value')->item(0)?->textContent ?? '');
                }
                if ($value === '') {
                    $complete = false;
                }
            }
        }

        if (! $seen || ! $complete) {
            return 'in_progress';
        }

        return 'done';
    }

    public static function version(DOMDocument $dom): ?string
    {
        $node = $dom->getElementsByTagName('Version')->item(0);
        if (! $node instanceof DOMElement) {
            return null;
        }
        $version = trim($node->getAttribute('Version'));

        return $version === '' ? null : $version;
    }

    public static function encode(string $utf8): string
    {
        $bytes = iconv('UTF-8', 'Windows-1251//IGNORE', $utf8);
        if ($bytes === false) {
            throw new LabBridgeException('dnkom_encoding');
        }

        return $bytes;
    }

    private static function now(): string
    {
        return now()->timezone('Europe/Moscow')->format('d.m.Y H:i:s');
    }

    private static function birth(mixed $value): string
    {
        $ymd = is_string($value) ? $value : (string) $value;

        return \Carbon\Carbon::parse($ymd)->format('d.m.Y');
    }

    private static function message(string $type, string $sender, string $date): array
    {
        return [
            'MessageType' => $type,
            'Date' => $date,
            'Sender' => $sender,
            'Receiver' => trim((string) config('order_bridge.dnkom.receiver')),
            'Password' => (string) config('order_bridge.dnkom.password'),
        ];
    }

    private static function attrs(array $attrs): string
    {
        $parts = [];
        foreach ($attrs as $key => $value) {
            $parts[] = $key.'="'.self::e((string) $value).'"';
        }

        return implode(' ', $parts);
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function blankToNull(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
