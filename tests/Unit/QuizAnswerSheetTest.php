<?php

namespace Tests\Unit;

use App\Support\QuizAnswerSheet;
use PHPUnit\Framework\TestCase;

class QuizAnswerSheetTest extends TestCase
{
    /** @return array<int, array{text: string, order: int}> */
    private function questions(): array
    {
        return [
            21 => ['text' => 'Для кого вы ищете решение?', 'order' => 0],
            71 => ['text' => 'Какие проявления у вас бывают?', 'order' => 0],
            72 => ['text' => 'Как часто это происходит?', 'order' => 0],
        ];
    }

    /** @return array<int, string> */
    private function answers(): array
    {
        return [
            111 => 'Для себя',
            420 => 'Насморк, чихание, заложенность носа или реакция глаз',
            422 => 'Реакции после употребления продуктов',
            430 => 'Возникает преимущественно в определённый сезон',
        ];
    }

    public function test_single_and_multiple_answers_become_text(): void
    {
        $rows = QuizAnswerSheet::compose(
            [21 => 111, 71 => [420, 422]],
            $this->questions(),
            $this->answers()
        );

        $this->assertSame([
            ['question' => 'Для кого вы ищете решение?', 'answers' => ['Для себя']],
            [
                'question' => 'Какие проявления у вас бывают?',
                'answers' => [
                    'Насморк, чихание, заложенность носа или реакция глаз',
                    'Реакции после употребления продуктов',
                ],
            ],
        ], $rows);
    }

    public function test_rows_follow_quiz_order_not_payload_order(): void
    {
        $questions = [
            21 => ['text' => 'Первый', 'order' => 1],
            71 => ['text' => 'Второй', 'order' => 2],
            72 => ['text' => 'Третий', 'order' => 3],
        ];

        $rows = QuizAnswerSheet::compose(
            [72 => 430, 21 => 111, 71 => [420]],
            $questions,
            $this->answers()
        );

        $this->assertSame(['Первый', 'Второй', 'Третий'], array_column($rows, 'question'));
    }

    public function test_deleted_question_keeps_its_answers_and_sorts_last(): void
    {
        $rows = QuizAnswerSheet::compose(
            [999 => 111, 21 => 111],
            $this->questions(),
            $this->answers()
        );

        $this->assertSame('Для кого вы ищете решение?', $rows[0]['question']);
        $this->assertSame('Вопрос №999 (удалён из квиза)', $rows[1]['question']);
        $this->assertSame(['Для себя'], $rows[1]['answers']);
    }

    public function test_deleted_answer_is_named_by_id(): void
    {
        $rows = QuizAnswerSheet::compose([21 => 555], $this->questions(), $this->answers());

        $this->assertSame(['ответ №555 (удалён из квиза)'], $rows[0]['answers']);
    }

    public function test_question_without_a_usable_answer_is_still_listed(): void
    {
        $rows = QuizAnswerSheet::compose([21 => [], 71 => null], $this->questions(), $this->answers());

        $this->assertSame(['—'], $rows[0]['answers']);
        $this->assertSame(['—'], $rows[1]['answers']);
    }

    public function test_string_ids_from_json_payloads_resolve(): void
    {
        $rows = QuizAnswerSheet::compose(['21' => '111'], $this->questions(), $this->answers());

        $this->assertSame(['Для себя'], $rows[0]['answers']);
    }

    public function test_stored_readable_rows_are_reused(): void
    {
        $items = [
            'quiz_answers' => [21 => 111],
            'quiz_answers_readable' => [
                ['question' => 'Сохранённый вопрос', 'answers' => ['Сохранённый ответ']],
            ],
        ];

        $this->assertSame(
            [['question' => 'Сохранённый вопрос', 'answers' => ['Сохранённый ответ']]],
            QuizAnswerSheet::forOrderItems($items)
        );
    }

    public function test_missing_quiz_payload_gives_no_rows(): void
    {
        $this->assertSame([], QuizAnswerSheet::forOrderItems(null));
        $this->assertSame([], QuizAnswerSheet::forOrderItems([]));
        $this->assertSame([], QuizAnswerSheet::forOrderItems(['quiz_answers' => []]));
    }

    public function test_stored_result_title_wins_over_lookup(): void
    {
        $items = ['quiz_result_id' => 3, 'quiz_result_title' => 'Следующий шаг лучше определить вместе с аллергологом'];

        $this->assertSame(
            'Следующий шаг лучше определить вместе с аллергологом',
            QuizAnswerSheet::resultTitleForOrderItems($items)
        );
    }
}
