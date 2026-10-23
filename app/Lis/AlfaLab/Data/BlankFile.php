<?php

namespace App\Lis\AlfaLab\Data;

/** Бланк результатов «как есть» (§11.20). Содержимое не пишем ни в журнал, ни на диск. */
final class BlankFile
{
    public function __construct(
        public readonly string $content,
        public readonly string $contentType,
    ) {}

    public function isPdf(): bool
    {
        return $this->contentType === 'application/pdf' || str_starts_with($this->content, '%PDF');
    }
}
