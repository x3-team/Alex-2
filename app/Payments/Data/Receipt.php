<?php

namespace App\Payments\Data;

use InvalidArgumentException;

/**
 * Чек 54-ФЗ для ЮKassa. Контакт покупателя (e-mail или телефон) нужен, чтобы отправить
 * чек; в нашей БД его не храним — только позиции.
 *
 * @param  list<ReceiptItem>  $items
 */
final class Receipt
{
    public function __construct(
        public readonly array $items,
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
    ) {
        if ($items === []) {
            throw new InvalidArgumentException('В чеке нет позиций');
        }
        if (($email === null || $email === '') && ($phone === null || $phone === '')) {
            throw new InvalidArgumentException('Для чека нужен e-mail или телефон покупателя');
        }
    }

    public function totalKopecks(): int
    {
        return array_sum(array_map(fn (ReceiptItem $i) => $i->totalKopecks(), $this->items));
    }

    /** Телефон для чека: только цифры, 7XXXXXXXXXX. */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if ($digits === '') {
            return null;
        }
        if (strlen($digits) === 11 && $digits[0] === '8') {
            $digits = '7'.substr($digits, 1);
        }
        if (strlen($digits) === 10) {
            $digits = '7'.$digits;
        }

        return strlen($digits) === 11 ? $digits : null;
    }

    /** Формат receipt API v3. paymentMode: full_prepayment для оплаты, full_payment для второго чека. */
    public function toYooKassa(array $config, ?string $paymentMode = null): array
    {
        $receipt = [
            'customer' => array_filter([
                'email' => $this->email ?: null,
                'phone' => self::normalizePhone($this->phone),
            ]),
            'items' => array_map(fn (ReceiptItem $i) => [
                'description' => $i->description,
                'quantity' => $i->quantity,
                'amount' => ['value' => Money::format($i->amountKopecks), 'currency' => 'RUB'],
                'vat_code' => (int) ($config['vat_code'] ?? 1),
                'payment_subject' => $config['payment_subject'] ?? 'service',
                'payment_mode' => $paymentMode ?? ($config['payment_mode'] ?? 'full_prepayment'),
            ], $this->items),
        ];
        if (! empty($config['tax_system_code'])) {
            $receipt['tax_system_code'] = (int) $config['tax_system_code'];
        }

        return $receipt;
    }

    /** @param list<array> $items */
    public static function fromStoredItems(array $items, ?string $email, ?string $phone): self
    {
        return new self(array_map(fn (array $i) => ReceiptItem::fromArray($i), $items), $email, $phone);
    }
}
