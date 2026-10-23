<?php

namespace App\Lis\AlfaLab\Exceptions;

use RuntimeException;

/**
 * Ошибка обмена с ЛИС. retryable — можно ли повторить тот же запрос позже;
 * outcomeUnknown — ответа нет, и неизвестно, выполнила ли ЛИС запрос (§8.2: «повторить»).
 */
class LisException extends RuntimeException
{
    public const AUTH = 'auth_error';
    public const PROTOCOL = 'protocol_error';
    public const BUSINESS = 'business_error';
    public const SERVER = 'server_error';
    public const TRANSPORT = 'transport_error';
    public const DISABLED = 'disabled';

    public function __construct(
        public readonly string $kind,
        string $message,
        public readonly ?int $httpStatus = null,
    ) {
        parent::__construct($message);
    }

    public function retryable(): bool
    {
        return in_array($this->kind, [self::SERVER, self::TRANSPORT], true);
    }

    public function outcomeUnknown(): bool
    {
        return $this->kind === self::TRANSPORT;
    }
}
