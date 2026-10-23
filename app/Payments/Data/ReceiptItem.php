<?php

namespace App\Payments\Data;

use InvalidArgumentException;

/** Позиция чека 54-ФЗ: исследование ALEX2 или взятие биоматериала. */
final class ReceiptItem
{
    public function __construct(
        public readonly string $description,
        public readonly int $amountKopecks,
        public readonly int $quantity = 1,
    ) {
        if (trim($description) === '' || mb_strlen($description) > 128) {
            throw new InvalidArgumentException('Название позиции чека: от 1 до 128 символов');
        }
        if ($amountKopecks <= 0 || $quantity <= 0) {
            throw new InvalidArgumentException('Цена и количество позиции чека должны быть больше нуля');
        }
    }

    public function totalKopecks(): int
    {
        return $this->amountKopecks * $this->quantity;
    }

    public static function fromArray(array $item): self
    {
        return new self((string) $item['description'], (int) $item['amount_kopecks'], (int) ($item['quantity'] ?? 1));
    }

    public function toArray(): array
    {
        return ['description' => $this->description, 'amount_kopecks' => $this->amountKopecks, 'quantity' => $this->quantity];
    }
}
