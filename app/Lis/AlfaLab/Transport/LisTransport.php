<?php

namespace App\Lis\AlfaLab\Transport;

use App\Lis\AlfaLab\Exceptions\LisException;

/**
 * Доставка одного сообщения в ЛИС: POST <url>/<messageType>?format=json (§8.2.2).
 * Реализации: HttpTransport (стенд и бой) и FakeTransport (эмулятор в нашей БД).
 */
interface LisTransport
{
    /** @throws LisException только при сбое связи (TRANSPORT); HTTP-коды разбирает клиент */
    public function send(string $messageType, array $body): LisResponse;
}
