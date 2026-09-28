<?php

namespace Tests\Unit;

use Tests\TestCase;

class QuizNextButtonTest extends TestCase
{
    public function test_next_button_stays_under_the_answers(): void
    {
        $vue = file_get_contents(base_path('resources/js/Pages/Public/Quiz.vue'));

        $this->assertIsString($vue);
        $this->assertStringContainsString("align-items: flex-start;", $vue);
        $this->assertStringNotContainsString('align-items: stretch', $vue);
        $this->assertStringContainsString(".quiz-card-wrapper.quiz-state {\n  height: auto;\n  max-height: 100%;", $vue);
        $this->assertStringContainsString(".quiz-state .options-list {\n  flex: 0 1 auto;", $vue);

        $start = strpos($vue, '.quiz-state .options-list {');
        $block = substr($vue, $start, strpos($vue, '}', $start) - $start);
        $this->assertStringNotContainsString('scrollbar-gutter', $block);
    }
}
