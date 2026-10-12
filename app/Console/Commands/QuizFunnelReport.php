<?php

namespace App\Console\Commands;

use App\Models\AnalyticsOutbox;
use App\Models\QuizEvent;
use Illuminate\Console\Command;

// Воронка квиза по внутреннему журналу (ТЗ §2.4, §9.2): попытки по этапам и брошенные.
// Брошенной считается попытка со стартом старше 24 часов без завершения (зрелое окно).
class QuizFunnelReport extends Command
{
    protected $signature = 'quiz:funnel {--days=30}';

    protected $description = 'Воронка квиза по внутреннему журналу за последние N дней';

    public function handle(): int
    {
        $since = now()->subDays((int) $this->option('days'));
        $attempts = fn (string $event) => QuizEvent::where('event', $event)
            ->where('occurred_at', '>=', $since)->distinct()->count('attempt_id');

        $started = QuizEvent::where('event', 'quiz_start')->where('occurred_at', '>=', $since)
            ->where('occurred_at', '<=', now()->subDay())->pluck('attempt_id')->unique();
        $completed = QuizEvent::where('event', 'quiz_complete')->whereIn('attempt_id', $started)
            ->distinct()->pluck('attempt_id');

        $rows = [
            ['Показ вступления', $attempts('quiz_view')],
            ['Старт', $attempts('quiz_start')],
            ['Расчёт результата', $attempts('quiz_complete')],
            ['Результат показан', $attempts('quiz_result_view')],
            ['Главное действие результата', $attempts('primary_cta_click')],
            ['Действие без контакта (поиск)', $attempts('no_contact_action_click')],
            ['Открыта форма к врачу', $attempts('allergist_form_open')],
            ['Принятые заявки к врачу', AnalyticsOutbox::where('platform', 'ga4')
                ->where('params->form_id', 'doctor_appointment')->where('occurred_at', '>=', $since)->count()],
            ['Технические ошибки', $attempts('quiz_error')],
            ['Брошено (старт > 24 ч назад без расчёта)', $started->count() - $completed->count()],
        ];

        $this->table(['Этап', 'Попыток'], $rows);

        return self::SUCCESS;
    }
}
