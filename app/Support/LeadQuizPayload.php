<?php

namespace App\Support;

class LeadQuizPayload
{
    /**
     * Ответы квиза рядом с заявкой: сырые id и читаемый текст на момент отправки.
     *
     * @param  array<array-key, mixed>  $answers
     * @return array{quiz_answers: array<array-key, mixed>, quiz_answers_readable: list<array{question: string, answers: list<string>}>, quiz_result_id: int|null, quiz_result_title: string|null}
     */
    public static function store(array $answers, mixed $resultId, ?string $resultTitle = null): array
    {
        $id = is_numeric($resultId) ? (int) $resultId : null;
        $lookedUp = $id ? QuizAnswerSheet::resultTitle($id) : null;
        $title = is_string($lookedUp) && trim($lookedUp) !== ''
            ? trim($lookedUp)
            : (is_string($resultTitle) && trim($resultTitle) !== '' ? trim($resultTitle) : null);

        return [
            'quiz_answers' => $answers,
            'quiz_answers_readable' => QuizAnswerSheet::fromRaw($answers),
            'quiz_result_id' => $id,
            'quiz_result_title' => $title,
        ];
    }
}
