<?php

namespace App\Payments\Data;

/** Платёж глазами ЮKassa (объект payment API v3). */
final class GatewayPayment
{
    public function __construct(
        public readonly string $id,
        public readonly string $status, // pending | waiting_for_capture | succeeded | canceled
        public readonly int $amountKopecks,
        public readonly ?string $confirmationUrl = null,
        public readonly bool $test = false,
        public readonly ?string $cancellationReason = null,
        public readonly ?string $cancellationParty = null,
        public readonly int $refundedKopecks = 0,
    ) {}

    public static function fromYooKassa(array $data): self
    {
        return new self(
            (string) ($data['id'] ?? ''),
            (string) ($data['status'] ?? 'pending'),
            Money::fromRubles($data['amount']['value'] ?? 0),
            $data['confirmation']['confirmation_url'] ?? null,
            (bool) ($data['test'] ?? false),
            $data['cancellation_details']['reason'] ?? null,
            $data['cancellation_details']['party'] ?? null,
            Money::fromRubles($data['refunded_amount']['value'] ?? 0),
        );
    }
}
