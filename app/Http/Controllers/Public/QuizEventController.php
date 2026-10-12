<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\QuizEvent;
use App\Support\Analytics\AcceptedLead;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

// Приём внутреннего журнала квиза (resources/js/analytics/quizJournal.js).
// Без согласия на аналитику ничего не сохраняется. Поля — только из фиксированных справочников.
class QuizEventController extends Controller
{
    private const EVENTS = [
        'quiz_view', 'quiz_start', 'quiz_step_view', 'quiz_answer', 'quiz_back', 'quiz_complete',
        'quiz_result_view', 'primary_cta_click', 'no_contact_action_click', 'allergist_form_open', 'quiz_error',
    ];

    public function store(Request $request)
    {
        if (AcceptedLead::consent($request) !== 'granted') {
            return response()->noContent();
        }

        $key = '/^[A-Za-z0-9_-]{8,64}$/';
        $data = $request->validate([
            'events' => 'required|array|max:20',
            'events.*.event' => 'required|string|in:'.implode(',', self::EVENTS),
            'events.*.attempt_id' => ['required', 'string', 'regex:'.$key],
            'events.*.quiz_version' => ['required', 'string', 'regex:/^v[a-z0-9]{1,14}$/'],
            'events.*.placement' => 'required|string|in:page,modal',
            'events.*.device' => 'required|string|in:desktop,tablet,mobile,unknown',
            'events.*.at' => 'required|date',
            'events.*.step_position' => 'nullable|integer|min:0|max:200',
            'events.*.to_step_position' => 'nullable|integer|min:0|max:200',
            'events.*.step_visit_id' => ['nullable', 'string', 'regex:'.$key],
            'events.*.question_key' => ['nullable', 'string', 'regex:/^q\d{1,10}$/'],
            'events.*.result_route_id' => ['nullable', 'string', 'regex:/^r\d{1,10}$/'],
            'events.*.action_code' => 'nullable|string|in:labs,search,doctor_form,demo,restart',
            'events.*.action_role' => 'nullable|string|in:primary,no_contact',
            'events.*.error_stage' => 'nullable|string|in:load,calculate',
            'events.*.error_code' => 'nullable|string|in:no_questions,rejected,invalid_response,network',
            'events.*.form_id' => 'nullable|string|in:allergist_request',
        ]);

        $now = now();
        $rows = [];
        foreach ($data['events'] as $event) {
            $occurred = Carbon::parse($event['at']);
            $rows[] = [
                'attempt_id' => $event['attempt_id'],
                'event' => $event['event'],
                'quiz_version' => $event['quiz_version'],
                'placement' => $event['placement'],
                'device' => $event['device'],
                'step_position' => $event['step_position'] ?? null,
                'to_step_position' => $event['to_step_position'] ?? null,
                'step_visit_id' => $event['step_visit_id'] ?? null,
                'question_key' => $event['question_key'] ?? null,
                'result_route_id' => $event['result_route_id'] ?? null,
                'action_code' => $event['action_code'] ?? null,
                'action_role' => $event['action_role'] ?? null,
                'error_stage' => $event['error_stage'] ?? null,
                'error_code' => $event['error_code'] ?? null,
                // Часы браузера могут врать: время не из будущего и не старше суток.
                'occurred_at' => $occurred->between($now->copy()->subDay(), $now) ? $occurred : $now,
                'created_at' => $now,
            ];
        }

        QuizEvent::insert($rows);

        return response()->noContent();
    }
}
