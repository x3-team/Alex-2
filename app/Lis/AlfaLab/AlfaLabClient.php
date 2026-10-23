<?php

namespace App\Lis\AlfaLab;

use App\Lis\AlfaLab\Data\BlankFile;
use App\Lis\AlfaLab\Data\ImportResult;
use App\Lis\AlfaLab\Data\Payload;
use App\Lis\AlfaLab\Data\ReferralRequest;
use App\Lis\AlfaLab\Data\ReferralState;
use App\Lis\AlfaLab\Exceptions\LisException;
use App\Lis\AlfaLab\Transport\LisResponse;
use App\Lis\AlfaLab\Transport\LisTransport;

/**
 * Клиент JSON веб-сервиса ЛИС «АльфаЛАБ» (протокол v3.1, §8.2.2, §11).
 * Один путь разбора ответов для стенда и эмулятора. Каждый вызов попадает в журнал.
 */
class AlfaLabClient
{
    public function __construct(
        private readonly LisTransport $transport,
        private readonly ExchangeLogger $logger,
        private readonly ?string $hospitalCode = null,
    ) {}

    /** query-create-referral → result-import-referral (§11.1, §11.2). */
    public function createReferral(ReferralRequest $request): ImportResult
    {
        $json = $this->call('query-create-referral', $request->toPayload(), $request->misId);
        $result = ImportResult::fromPayload($json ?? []);

        if ($result->lisId === null) {
            throw new LisException(LisException::PROTOCOL, 'ЛИС не вернула LisId созданного направления');
        }

        return $result;
    }

    /** query-referral-remove (§11.4). */
    public function removeReferral(?int $lisId = null, ?string $misId = null, ?string $nr = null): void
    {
        $this->call('query-referral-remove', ['query' => self::ref($lisId, $misId, $nr)], $misId, $lisId);
    }

    /** query-referral-results с ReturnState (§11.9): точечная проверка и сверка. */
    public function referralResults(?int $lisId = null, ?string $misId = null, ?string $nr = null): ReferralState
    {
        $json = $this->call('query-referral-results', [
            'query' => self::ref($lisId, $misId, $nr) + ['returnState' => true],
        ], $misId, $lisId);

        return ReferralState::fromPayload($json ?? []);
    }

    /** query-next-referral-results (§11.10). null — очередь пуста. */
    public function nextReferralResults(): ?ReferralState
    {
        $json = $this->call('query-next-referral-results', [], emptyIsOk: true);
        if ($json === null || Payload::arr($json, 'referral') === null) {
            return null;
        }

        return ReferralState::fromPayload($json);
    }

    /**
     * result-referral-results-import (§11.18). state: 2 — успешно, 3 — предупреждение
     * (с delayMin направление откладывается в очереди), 4 — ошибка.
     */
    public function ackReferralResults(int $lisId, int $version, int $state = 2, ?string $message = null, ?int $delayMin = null): void
    {
        $this->call('result-referral-results-import', [
            'version' => ['version' => $version],
            'referral' => array_filter([
                'lisId' => $lisId,
                'state' => $state,
                'message' => $message,
                'delayMin' => $delayMin,
            ], fn ($v) => $v !== null),
        ], lisId: $lisId, emptyIsOk: true);
    }

    /** query-blank-file (§11.19): PDF «как есть». */
    public function blankFile(string $blankGuid): BlankFile
    {
        $response = $this->send('query-blank-file', ['query' => ['blankGuid' => $blankGuid]], binary: true);

        return new BlankFile($response->body, $response->contentType);
    }

    /** query-dictionaries-version (§11.21). */
    public function dictionariesVersion(): int
    {
        $json = $this->call('query-dictionaries-version', $this->hospitalQuery());
        $version = Payload::get($json, 'version');
        $version = is_array($version) ? Payload::int($version, 'version') : (is_numeric($version) ? (int) $version : null);
        if ($version === null) {
            throw new LisException(LisException::PROTOCOL, 'ЛИС не вернула версию номенклатуры');
        }

        return $version;
    }

    /** query-dictionaries (§11.23–11.24): номенклатура целиком, разбирает DictionarySync. */
    public function dictionaries(): array
    {
        return $this->call('query-dictionaries', $this->hospitalQuery()) ?? [];
    }

    private function hospitalQuery(): array
    {
        return $this->hospitalCode ? ['hospitalCode' => $this->hospitalCode] : [];
    }

    private static function ref(?int $lisId, ?string $misId, ?string $nr): array
    {
        $ref = array_filter(['lisId' => $lisId, 'misId' => $misId, 'nr' => $nr], fn ($v) => $v !== null && $v !== '');
        if ($ref === []) {
            throw new \InvalidArgumentException('Нужен LisId, MisId или номер направления');
        }

        return $ref;
    }

    private function call(string $messageType, array $body, ?string $misId = null, ?int $lisId = null, bool $emptyIsOk = false): ?array
    {
        $response = $this->send($messageType, $body, $misId, $lisId, $emptyIsOk);

        return $response->json();
    }

    private function send(string $messageType, array $body, ?string $misId = null, ?int $lisId = null, bool $emptyIsOk = false, bool $binary = false): LisResponse
    {
        $started = hrtime(true);
        $encoded = json_encode($body, JSON_UNESCAPED_UNICODE);
        $ms = fn () => (int) ((hrtime(true) - $started) / 1_000_000);

        try {
            $response = $this->transport->send($messageType, $body);
        } catch (LisException $e) {
            $this->logger->record($messageType, $e->kind, null, $ms(), $misId, $lisId, $e->getMessage(), $encoded);
            throw $e;
        }

        $error = $this->failure($response, $binary, $emptyIsOk);
        if ($error !== null) {
            $this->logger->record($messageType, $error->kind, $response->status, $ms(), $misId, $lisId, $error->getMessage(), $encoded);
            throw $error;
        }

        $json = $binary ? null : $response->json();
        $warnings = $json === null ? [] : Payload::list($json, 'warnings');
        $outcome = $json === null && ! $binary ? 'empty' : ($warnings === [] ? 'ok' : 'warning');
        $warningText = $warnings === [] ? null : implode('; ', array_filter(array_map(fn ($w) => Payload::str($w, 'text'), $warnings)));
        $referral = Payload::arr($json, 'referral');

        $this->logger->record(
            $messageType,
            $outcome,
            $response->status,
            $ms(),
            $misId ?? Payload::str($referral, 'misId'),
            $lisId ?? Payload::int($referral, 'lisId'),
            $warningText,
            $encoded,
        );

        return $response;
    }

    private function failure(LisResponse $response, bool $binary, bool $emptyIsOk): ?LisException
    {
        $json = $response->json();
        $text = Payload::str($json, 'error');

        return match (true) {
            $response->status === 401, $response->status === 403 => new LisException(LisException::AUTH, $text ?? 'ЛИС отклонила логин или пароль', $response->status),
            $response->status === 404 => new LisException(LisException::PROTOCOL, $text ?? 'ЛИС не знает такого сообщения', $response->status),
            $response->status >= 500 => new LisException(LisException::SERVER, $text ?? 'Ошибка сервера ЛИС', $response->status),
            $response->status >= 400 => new LisException(LisException::PROTOCOL, $text ?? 'ЛИС отклонила запрос', $response->status),
            $text !== null => new LisException(LisException::BUSINESS, $text, $response->status),
            $binary && $response->body === '' => new LisException(LisException::PROTOCOL, 'ЛИС вернула пустой бланк', $response->status),
            ! $binary && ! $emptyIsOk && $json === null => new LisException(LisException::PROTOCOL, 'ЛИС вернула пустой или не-JSON ответ', $response->status),
            default => null,
        };
    }
}
