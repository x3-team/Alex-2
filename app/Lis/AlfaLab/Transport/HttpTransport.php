<?php

namespace App\Lis\AlfaLab\Transport;

use App\Lis\AlfaLab\Exceptions\LisException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class HttpTransport implements LisTransport
{
    public function __construct(private readonly array $config) {}

    public function send(string $messageType, array $body): LisResponse
    {
        $baseUrl = rtrim((string) ($this->config['base_url'] ?? ''), '/');
        $sender = (string) ($this->config['sender'] ?? '');
        $password = (string) ($this->config['password'] ?? '');

        if ($baseUrl === '' || $sender === '' || $password === '') {
            throw new LisException(LisException::DISABLED, 'Не заданы ALFALAB_BASE_URL, ALFALAB_SENDER или ALFALAB_PASSWORD');
        }

        try {
            $response = Http::withBasicAuth($sender, $password)
                ->connectTimeout((int) ($this->config['connect_timeout'] ?? 5))
                ->timeout((int) ($this->config['timeout'] ?? 30))
                ->acceptJson()
                ->withBody(json_encode($body === [] ? new \stdClass : $body, JSON_UNESCAPED_UNICODE), 'application/json; charset=utf-8')
                ->post($baseUrl.'/'.$messageType.'?format=json');
        } catch (ConnectionException $e) {
            throw new LisException(LisException::TRANSPORT, 'Нет ответа от ЛИС: '.$e->getMessage());
        }

        return new LisResponse(
            $response->status(),
            $response->body(),
            strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0])) ?: 'application/json',
        );
    }
}
