<?php

namespace App\Support;

use App\Models\QuizAnswer;
use App\Models\QuizQuestion;
use App\Models\QuizResult;

/**
 * Turns the quiz payload stored on a doctor appointment (question id => answer
 * id or ids) into the question and answer wording a doctor can read.
 */
class QuizAnswerSheet
{
    /**
     * Build the readable rows from raw ids plus lookup tables.
     *
     * Rows follow the quiz order; anything the quiz no longer knows about is
     * kept with its id so a stale appointment still tells the doctor something.
     *
     * @param  array<array-key, mixed>  $raw  question id => answer id or ids
     * @param  array<int, array{text: string, order: int}>  $questions
     * @param  array<int, string>  $answers  answer id => wording
     * @return list<array{question: string, answers: list<string>}>
     */
    public static function compose(array $raw, array $questions, array $answers): array
    {
        $rows = [];

        foreach ($raw as $questionId => $picked) {
            $id = (int) $questionId;
            $question = $questions[$id] ?? null;

            $wording = [];
            foreach (is_array($picked) ? $picked : [$picked] as $answerId) {
                if ($answerId === null || $answerId === '' || is_array($answerId)) {
                    continue;
                }
                $answer = (int) $answerId;
                $wording[] = $answers[$answer] ?? "ответ №{$answer} (удалён из квиза)";
            }

            $rows[] = [
                'id' => $id,
                // Deleted questions sort last, after everything still in the quiz.
                'order' => $question['order'] ?? PHP_INT_MAX,
                'question' => $question['text'] ?? "Вопрос №{$id} (удалён из квиза)",
                'answers' => $wording === [] ? ['—'] : $wording,
            ];
        }

        usort($rows, fn ($a, $b) => [$a['order'], $a['id']] <=> [$b['order'], $b['id']]);

        return array_values(array_map(
            fn (array $row) => ['question' => $row['question'], 'answers' => $row['answers']],
            $rows
        ));
    }

    /**
     * @param  array<array-key, mixed>  $raw
     * @return list<array{question: string, answers: list<string>}>
     */
    public static function fromRaw(array $raw): array
    {
        if ($raw === []) {
            return [];
        }

        $questionIds = array_map('intval', array_keys($raw));
        $answerIds = [];
        foreach ($raw as $picked) {
            foreach (is_array($picked) ? $picked : [$picked] as $answerId) {
                if ($answerId !== null && $answerId !== '' && ! is_array($answerId)) {
                    $answerIds[] = (int) $answerId;
                }
            }
        }

        $questions = QuizQuestion::query()
            ->whereIn('id', $questionIds)
            ->get(['id', 'question_text', 'order'])
            ->mapWithKeys(fn ($q) => [
                (int) $q->id => ['text' => (string) $q->question_text, 'order' => (int) $q->order],
            ])
            ->all();

        $answers = QuizAnswer::query()
            ->whereIn('id', array_unique($answerIds))
            ->pluck('answer_text', 'id')
            ->mapWithKeys(fn ($text, $id) => [(int) $id => (string) $text])
            ->all();

        return self::compose($raw, $questions, $answers);
    }

    /**
     * Readable rows for an order, resolving on the fly when an appointment was
     * stored before the readable copy existed.
     *
     * @param  array<array-key, mixed>|null  $items
     * @return list<array{question: string, answers: list<string>}>
     */
    public static function forOrderItems(?array $items): array
    {
        $stored = $items['quiz_answers_readable'] ?? null;
        if (is_array($stored) && $stored !== []) {
            return array_values(array_filter(array_map(
                fn ($row) => is_array($row) && isset($row['question'])
                    ? [
                        'question' => (string) $row['question'],
                        'answers' => array_values(array_map('strval', (array) ($row['answers'] ?? []))),
                    ]
                    : null,
                $stored
            )));
        }

        $raw = $items['quiz_answers'] ?? null;

        return is_array($raw) ? self::fromRaw($raw) : [];
    }

    /**
     * The result the quiz gave the patient, resolved on the fly when missing.
     *
     * @param  array<array-key, mixed>|null  $items
     */
    public static function resultTitleForOrderItems(?array $items): ?string
    {
        $stored = $items['quiz_result_title'] ?? null;
        if (is_string($stored) && trim($stored) !== '') {
            return trim($stored);
        }

        return self::resultTitle($items['quiz_result_id'] ?? null);
    }

    public static function resultTitle(mixed $resultId): ?string
    {
        if ($resultId === null || $resultId === '') {
            return null;
        }

        $title = QuizResult::query()->whereKey((int) $resultId)->value('title');

        return $title === null ? null : (string) $title;
    }
}
