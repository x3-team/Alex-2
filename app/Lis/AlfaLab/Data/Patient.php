<?php

namespace App\Lis\AlfaLab\Data;

use DateTimeInterface;

/** Patient из §11.1.1 и расширения §11.1.5. В ЛИС уходит только то, что нужно офису. */
final class Patient
{
    public const GENDER_UNKNOWN = 0;
    public const GENDER_MALE = 1;
    public const GENDER_FEMALE = 2;

    public function __construct(
        public readonly string $lastName,
        public readonly ?string $firstName = null,
        public readonly ?string $middleName = null,
        public readonly int $gender = self::GENDER_UNKNOWN,
        public readonly ?DateTimeInterface $birthDate = null,
        public readonly ?string $misId = null,
        public readonly ?string $phone = null,
        public readonly ?string $email = null,
    ) {}

    public function toPayload(): array
    {
        return array_filter([
            'misId' => $this->misId,
            'lastName' => $this->lastName,
            'firstName' => $this->firstName,
            'middleName' => $this->middleName,
            'gender' => $this->gender,
            // В JSON-примере §11.1 поле написано «birthdate», в таблице — BirthDate. Уточняется у лаборатории.
            'birthDate' => $this->birthDate?->format('Y-m-d'),
            'phone' => $this->phone,
            'email' => $this->email,
        ], fn ($v) => $v !== null && $v !== '');
    }
}
