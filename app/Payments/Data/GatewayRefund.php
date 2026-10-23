<?php

namespace App\Payments\Data;

final class GatewayRefund
{
    public function __construct(
        public readonly string $id,
        public readonly string $status, // pending | succeeded | canceled
        public readonly int $amountKopecks,
    ) {}

    public static function fromYooKassa(array $data): self
    {
        return new self(
            (string) ($data['id'] ?? ''),
            (string) ($data['status'] ?? 'pending'),
            Money::fromRubles($data['amount']['value'] ?? 0),
        );
    }
}
