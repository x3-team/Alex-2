<?php

namespace App\Lis\AlfaLab\Data;

/** Ответ result-import-referral (§11.2). */
final class ImportResult
{
    /** @param list<string> $warnings */
    public function __construct(
        public readonly ?int $lisId,
        public readonly ?string $nr,
        public readonly ?string $misId,
        public readonly array $warnings,
    ) {}

    public static function fromPayload(array $json): self
    {
        $referral = Payload::arr($json, 'referral');

        return new self(
            Payload::int($referral, 'lisId'),
            Payload::str($referral, 'nr'),
            Payload::str($referral, 'misId'),
            array_values(array_filter(array_map(
                fn (array $w) => Payload::str($w, 'text'),
                Payload::list($json, 'warnings'),
            ))),
        );
    }

    /**
     * Направление создаётся, даже если код услуги не найден: проблема приходит только
     * предупреждением (§11.2, пример 1). Для нас это заказ без услуги — ошибка.
     *
     * @return list<string>
     */
    public function missingServiceWarnings(): array
    {
        return array_values(array_filter($this->warnings, fn (string $text) => mb_stripos($text, 'не найден') !== false));
    }
}
