<?php

namespace App\Lis\AlfaLab\Data;

use Carbon\CarbonImmutable;

/**
 * Состояние направления из referral-results (§11.17). Таблицу тестов не разбираем:
 * пациенту отдаём PDF-бланк, результаты по аллергенам у себя не храним.
 */
final class ReferralState
{
    public const FOUND = 3;
    public const NOT_FOUND = 1;

    public const ACTIVATED_NO = 1;
    public const ACTIVATED_PARTLY = 2;
    public const ACTIVATED_FULLY = 3;

    public const ORDER_NEW = 1;
    public const ORDER_DONE_UNAPPROVED = 3;
    public const ORDER_DONE = 4;
    public const ORDER_CANCELLED = 5;

    public const BLANK_RESULTS = 1;
    public const BLANK_DEFECT = 2;
    public const BLANK_DOCUMENT = 3;

    /**
     * @param  list<array{code: string|null, biomaterialCode: string|null, state: int|null, defected: bool, defects: string|null}>  $orders
     * @param  list<array{guid: string|null, id: int|null, version: int|null, type: int|null, name: string|null, done: bool, doneDate: CarbonImmutable|null, fileName: string|null, signed: bool}>  $blanks
     */
    public function __construct(
        public readonly ?int $state,
        public readonly ?int $version,
        public readonly ?int $lisId,
        public readonly ?string $misId,
        public readonly ?string $nr,
        public readonly bool $done,
        public readonly bool $removed,
        public readonly ?int $activated,
        public readonly ?CarbonImmutable $activationDate,
        public readonly ?CarbonImmutable $doneDate,
        public readonly ?CarbonImmutable $planFrom,
        public readonly ?CarbonImmutable $planTo,
        public readonly ?float $totalPrice,
        public readonly bool $manual,
        public readonly array $orders,
        public readonly array $blanks,
    ) {}

    public static function fromPayload(array $json): self
    {
        $referral = Payload::arr($json, 'referral');
        $version = Payload::get($json, 'version');
        $version = is_array($version) ? Payload::int($version, 'version') : (is_numeric($version) ? (int) $version : null);

        return new self(
            Payload::int($json, 'state'),
            $version,
            Payload::int($referral, 'lisId'),
            Payload::str($referral, 'misId'),
            Payload::str($referral, 'nr'),
            Payload::bool($referral, 'done'),
            Payload::bool($referral, 'removed'),
            Payload::int($referral, 'activated'),
            Payload::date($referral, 'activationDate'),
            Payload::date($referral, 'doneDate'),
            Payload::date($referral, 'minPlanDate'),
            Payload::date($referral, 'maxPlanDate'),
            Payload::float($referral, 'totalPrice'),
            Payload::bool($referral, 'manual'),
            array_map(fn (array $o) => [
                'code' => Payload::str($o, 'code'),
                'biomaterialCode' => Payload::str($o, 'biomaterialCode'),
                'state' => Payload::int($o, 'state'),
                'defected' => Payload::bool($o, 'defected'),
                'defects' => Payload::str($o, 'defects'),
            ], Payload::list($json, 'orders') ?: Payload::list($referral, 'orders')),
            array_map(fn (array $b) => [
                'guid' => Payload::str($b, 'blankGuid'),
                'id' => Payload::int($b, 'blankId'),
                'version' => Payload::int($b, 'version'),
                'type' => Payload::int($b, 'blankType'),
                'name' => Payload::str($b, 'name'),
                'done' => Payload::bool($b, 'done'),
                'doneDate' => Payload::date($b, 'doneDate'),
                'fileName' => Payload::str($b, 'fileName'),
                'signed' => Payload::bool($b, 'signed'),
            ], Payload::list($json, 'blanks')),
        );
    }

    public function found(): bool
    {
        return $this->state === null ? $this->lisId !== null : $this->state === self::FOUND;
    }

    public function defected(): bool
    {
        foreach ($this->orders as $order) {
            if ($order['defected']) {
                return true;
            }
        }
        foreach ($this->blanks as $blank) {
            if ($blank['type'] === self::BLANK_DEFECT) {
                return true;
            }
        }

        return false;
    }

    /** Готовые бланки с результатами — их отдаём пациенту в PDF. */
    public function readyResultBlanks(): array
    {
        return array_values(array_filter(
            $this->blanks,
            fn (array $b) => $b['done'] && $b['type'] === self::BLANK_RESULTS && $b['guid'] !== null,
        ));
    }
}
