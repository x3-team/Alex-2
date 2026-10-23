<?php

namespace App\Lis\AlfaLab\Data;

use DateTimeInterface;
use InvalidArgumentException;

/**
 * Направление без проб (§11.1, пример 2): кровь берут в офисе лаборатории, мы передаём
 * только заказанные услуги. misId — наш UUID заказа: по нему проверяем, создалось ли
 * направление, если ответ не дошёл.
 *
 * @param  list<array{code: string, biomaterialCode?: string|null}>  $orders
 */
final class ReferralRequest
{
    public function __construct(
        public readonly string $misId,
        public readonly Patient $patient,
        public readonly array $orders,
        public readonly DateTimeInterface $date,
        public readonly ?string $nr = null,
        public readonly ?string $hospitalCode = null,
        public readonly ?string $externalNr = null,
        public readonly array $extraFields = [],
    ) {
        if ($orders === []) {
            throw new InvalidArgumentException('В направлении нет услуг');
        }
    }

    public function toPayload(): array
    {
        $referral = array_filter([
            'misId' => $this->misId,
            'nr' => $this->nr,
            'autoNr' => $this->nr === null ? true : null,
            'date' => $this->date->format('Y-m-d\TH:i:sP'),
            'hospitalCode' => $this->hospitalCode,
            'externalNr' => $this->externalNr,
        ], fn ($v) => $v !== null && $v !== '') + $this->extraFields;

        // Orders вложены в Referral, как в XML-примерах 2 и 4 §11.1.
        $referral['orders'] = array_map(fn (array $o) => array_filter([
            'code' => $o['code'],
            'biomaterialCode' => $o['biomaterialCode'] ?? null,
        ], fn ($v) => $v !== null && $v !== ''), $this->orders);

        return [
            'patient' => $this->patient->toPayload(),
            'referral' => $referral,
        ];
    }
}
