<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\QuizQuestion;
use App\Models\QuizRule;
use App\Models\QuizResult;
use App\Models\QuizMeta;
use Illuminate\Http\Request;
use Inertia\Inertia;

class QuizController extends Controller
{
    public function index()
    {
        $seo = QuizMeta::first();

        $questions = QuizQuestion::with('answers')
            ->where('is_active', true)
            ->orderBy('order')
            ->get()
            ->map(function ($q) {
                return [
                    'id' => $q->id,
                    'text' => $q->question_text,
                    'type' => $q->question_type, // 'single' или 'multiple'
                    'audience_target' => $q->audience_target ?? $q->target_audience ?? 'all',
                    'order' => $q->order,

                    'options' => $q->answers->map(function ($a) {
                        return [
                            'id' => $a->id,
                            'text' => $a->answer_text,
                            'target_audience' => $a->target_audience ?? null,
                            'next_question_id' => $a->next_question_id ?? null,
                        ];
                    })->values()->toArray()
                ];
            });

        $rules = QuizRule::with('result')
            ->orderBy('priority', 'asc')
            ->get()
            ->map(function ($r) {
                return [
                    'result_id' => $r->result_id,
                    'conditions' => $r->conditions,
                    'result' => $r->result
                ];
            });

        $seoTitle = trim((string) ($seo?->title ?? ''));
        $seoDescription = trim((string) ($seo?->description ?? ''));
        $publicQuizTitle = 'Нужно ли мне сдать тест на аллергию?';
        $publicQuizDescription = 'Пройдите короткий тест за 1 минуту и получите персональную рекомендацию по сдаче теста на аллергию.';
        if ($seoTitle === '' || preg_match('/настройк|управлен|мета-тег|админ|квиза/iu', $seoTitle)) {
            $seoTitle = $publicQuizTitle;
        }
        if ($seoDescription === '' || preg_match('/настройк|управлен|мета-тег|админ|квиза/iu', $seoDescription)) {
            $seoDescription = $publicQuizDescription;
        }

        return Inertia::render('Public/Quiz', [
            'questions' => $questions,
            'rules' => $rules,
            'meta' => [
                'title'       => $seoTitle,
                'description' => $seoDescription,
                'keywords'    => $seo?->keywords ?? '',
                'og_title'    => $seoTitle,
                'og_desc'     => $seoDescription,
                'og_image'    => config('app.url') . '/og-image.png', // Ссылка на картинку превью
                'og_url'      => config('app.url') . '/quiz',
            ],
        ]);
    }

    public function calculate(Request $request)
    {
        $answers = $request->input('answers', []);

        $rules = QuizRule::with('result')
            ->whereHas('result', function($q) {
                $q->where('is_active', true);
            })
            ->orderBy('priority', 'asc')
            ->get();

        $matchedResult = null;
        $bestMatchScore = 0;

        foreach ($rules as $rule) {
            $conditions = $rule->conditions;

            if (empty($conditions)) {
                continue;
            }

            $matchedConditionsCount = 0;

            foreach ($conditions as $condition) {
                $questionId = (string) ($condition['question_id'] ?? '');
                $userAnswer = $answers[$questionId] ?? null;

                if (!$userAnswer) {
                    continue;
                }

                $userAnswersArray = array_map('intval', is_array($userAnswer) ? $userAnswer : [$userAnswer]);
                $conditionAnswerIds = array_map('intval', $condition['answer_ids'] ?? []);

                $operator = $condition['operator'] ?? 'OR';
                $conditionMatched = false;

                if ($operator === 'AND') {
                    $missing = array_diff($conditionAnswerIds, $userAnswersArray);
                    if (empty($missing)) {
                        $conditionMatched = true;
                    }
                } else { // OR
                    $match = array_intersect($conditionAnswerIds, $userAnswersArray);
                    if (!empty($match)) {
                        $conditionMatched = true;
                    }
                }

                if ($conditionMatched) {
                    $matchedConditionsCount++;
                }
            }

            if ($matchedConditionsCount > $bestMatchScore) {
                $bestMatchScore = $matchedConditionsCount;
                $matchedResult = $rule->result;
            }
        }

        if (!$matchedResult) {
            $matchedResult = QuizResult::where('is_active', true)
                ->orderBy('priority', 'desc')
                ->first();
        }

        return response()->json([
            'result' => $matchedResult
        ]);
    }
}