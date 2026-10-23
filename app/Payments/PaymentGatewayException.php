<?php

namespace App\Payments;

use RuntimeException;

/**
 * Ошибка обращения к ЮKassa. retryable: 5xx, 429 и обрыв связи — повторяем с тем же
 * Idempotence-Key, второго списания не будет.
 */
class PaymentGatewayException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retryable = false,
        public readonly ?int $httpStatus = null,
        public readonly ?string $errorCode = null,
    ) {
        parent::__construct($message);
    }
}
