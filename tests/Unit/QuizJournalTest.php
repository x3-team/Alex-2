<?php

namespace Tests\Unit;

use App\Models\QuizEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Внутренний журнал квиза: только с согласия, только поля из справочника, без ответов.
class QuizJournalTest extends TestCase
{
    use RefreshDatabase;

    private function send(array $events, ?string $consent = '1:granted')
    {
        $cookies = $consent ? ['alex_analytics_consent' => $consent] : [];

        return $this->withCredentials()->withUnencryptedCookies($cookies)
            ->postJson('/api/quiz-events', ['events' => $events]);
    }

    private function event(array $extra = []): array
    {
        return $extra + [
            'event' => 'quiz_start',
            'attempt_id' => 'attempt-0001',
            'quiz_version' => 'vabc123',
            'placement' => 'page',
            'device' => 'mobile',
            'at' => now()->toIso8601String(),
        ];
    }

    public function test_events_are_stored_only_with_consent(): void
    {
        $this->send([$this->event()], null)->assertNoContent();
        $this->send([$this->event()], '1:denied')->assertNoContent();
        $this->assertSame(0, QuizEvent::count());

        $this->send([
            $this->event(),
            $this->event(['event' => 'quiz_answer', 'step_position' => 1, 'step_visit_id' => 'visit-0001']),
            $this->event(['event' => 'quiz_complete', 'result_route_id' => 'r5']),
        ])->assertNoContent();

        $this->assertSame(['quiz_start', 'quiz_answer', 'quiz_complete'], QuizEvent::orderBy('id')->pluck('event')->all());
    }

    public function test_answers_and_free_text_are_rejected(): void
    {
        $this->send([$this->event(['event' => 'quiz_answer_text'])])->assertStatus(422);
        $this->send([$this->event(['question_key' => 'Есть ли у вас астма?'])])->assertStatus(422);
        $this->send([$this->event(['result_route_id' => 'Обратитесь к аллергологу'])])->assertStatus(422);

        $this->send([$this->event(['answer_id' => 42, 'phone' => '+79000000000'])])->assertNoContent();
        $row = QuizEvent::first()->getAttributes();
        $this->assertArrayNotHasKey('answer_id', $row);
        $this->assertArrayNotHasKey('phone', $row);
    }

    public function test_funnel_counts_abandoned_attempts(): void
    {
        $old = now()->subDays(2);
        QuizEvent::insert([
            ['attempt_id' => 'a1', 'event' => 'quiz_start', 'quiz_version' => 'v1', 'placement' => 'page', 'device' => 'mobile', 'occurred_at' => $old],
            ['attempt_id' => 'a1', 'event' => 'quiz_complete', 'quiz_version' => 'v1', 'placement' => 'page', 'device' => 'mobile', 'occurred_at' => $old],
            ['attempt_id' => 'a2', 'event' => 'quiz_start', 'quiz_version' => 'v1', 'placement' => 'page', 'device' => 'mobile', 'occurred_at' => $old],
        ]);

        $this->artisan('quiz:funnel')
            ->expectsTable(['Этап', 'Попыток'], [
                ['Показ вступления', 0],
                ['Старт', 2],
                ['Расчёт результата', 1],
                ['Результат показан', 0],
                ['Главное действие результата', 0],
                ['Действие без контакта (поиск)', 0],
                ['Открыта форма к врачу', 0],
                ['Принятые заявки к врачу', 0],
                ['Технические ошибки', 0],
                ['Брошено (старт > 24 ч назад без расчёта)', 1],
            ])
            ->assertSuccessful();
    }

    public function test_quiz_page_logs_through_the_journal(): void
    {
        $quiz = file_get_contents(base_path('resources/js/Pages/Public/Quiz.vue'));
        foreach (['journal.started()', 'journal.answered(', 'journal.back(', 'journal.completed(', 'journal.resultShown(', 'journal.formOpened()', "journal.failed('calculate'"] as $call) {
            $this->assertStringContainsString($call, $quiz);
        }

        $journal = file_get_contents(base_path('resources/js/analytics/quizJournal.js'));
        $this->assertStringContainsString("window.alexAnalytics?.consent() === 'granted'", $journal);
        $this->assertStringNotContainsString('reachGoal', $journal);
        $this->assertStringNotContainsString('gtag', $journal);
    }
}
