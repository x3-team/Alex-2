<?php

namespace App\Lis\AlfaLab\Transport;

/** Сырой ответ сервиса ЛИС: HTTP-код, тело и тип содержимого (для бланков — PDF). */
final class LisResponse
{
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly string $contentType = 'application/json',
    ) {}

    public function json(): ?array
    {
        $trimmed = trim($this->body);
        if ($trimmed === '') {
            return null;
        }
        $decoded = json_decode($trimmed, true);

        return is_array($decoded) ? $decoded : null;
    }
}
