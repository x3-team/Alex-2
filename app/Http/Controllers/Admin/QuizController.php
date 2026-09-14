<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuizQuestion;
use App\Models\QuizAnswer;
use App\Models\QuizResult;
use App\Models\QuizRule;
use App\Models\QuizMeta;
use Illuminate\Http\Request;
use Inertia\Inertia;

class QuizController extends Controller
{
    public function index()
    {
        $questions = QuizQuestion::with(['answers.nextQuestion', 'answers.result'])
            ->orderBy('order')
            ->get();

        $results = QuizResult::orderBy('priority')->get();

        $rules = QuizRule::orderBy('priority')->get();

        // Получение SEO и основных настроек
        $quizMeta = QuizMeta::first() ?? (object)[
            'title' => '',
            'description' => '',
            'keywords' => '',
            'is_split_enabled' => true,
        ];

        return Inertia::render('Admin/Quiz/Index', [
            'quizMeta' => [
                'title' => $quizMeta->title ?? '',
                'description' => $quizMeta->description ?? '',
                'keywords' => $quizMeta->keywords ?? '',
            ],
            'settings' => [
                'is_split_enabled' => (bool) ($quizMeta->is_split_enabled ?? true),
            ],
            'questions' => $questions,
            'results' => $results,
            'rules' => $rules,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'meta_keywords' => 'nullable|string|max:500',
            'is_split_enabled' => 'nullable|boolean',

            'questions' => 'required|array',
            'questions.*.id' => 'nullable|integer',
            'questions.*.question_text' => 'required|string',
            'questions.*.question_type' => 'required|in:single,multiple',
            'questions.*.audience_target' => 'nullable|string|in:all,adult,child',
            'questions.*.is_active' => 'boolean',
            'questions.*.answers' => 'required|array',
            'questions.*.answers.*.id' => 'nullable|integer',
            'questions.*.answers.*.answer_text' => 'required|string',
            'questions.*.answers.*.target_audience' => 'nullable|string|in:adult,child',
            'questions.*.answers.*.next_question_id' => 'nullable',
            'questions.*.answers.*.result_id' => 'nullable|integer',

            'results' => 'required|array',
            'results.*.id' => 'nullable|integer',
            'results.*.title' => 'required|string',
            'results.*.description' => 'required|string',
            'results.*.is_active' => 'boolean',
            'results.*.priority' => 'required|integer',

            'rules' => 'nullable|array',
            'rules.*.id' => 'nullable|integer',
            'rules.*.result_id' => 'required|integer',
            'rules.*.priority' => 'required|integer',
            'rules.*.conditions' => 'required|array',
            'rules.*.conditions.*.question_id' => 'required|integer',
            'rules.*.conditions.*.answer_ids' => 'required|array',
        ]);

        // 🔴 0. СОХРАНЕНИЕ SEO И НАСТРОЕК (FIX)
        $meta = QuizMeta::first();
        if (!$meta) {
            $meta = new QuizMeta();
        }
        $meta->title = $validated['meta_title'] ?? '';
        $meta->description = $validated['meta_description'] ?? '';
        $meta->keywords = $validated['meta_keywords'] ?? '';
        $meta->is_split_enabled = $validated['is_split_enabled'] ?? true;
        $meta->save();

        // 1. Сохранение вопросов и ответов
        $incomingQuestionIds = collect($validated['questions'])->pluck('id')->filter();
        QuizQuestion::whereNotIn('id', $incomingQuestionIds)->delete();

        foreach ($validated['questions'] as $qData) {
            $question = QuizQuestion::updateOrCreate(
                ['id' => $qData['id']],
                [
                    'question_text' => $qData['question_text'],
                    'question_type' => $qData['question_type'],
                    'audience_target' => $qData['audience_target'] ?? 'all',
                    'is_active' => $qData['is_active'] ?? true,
                    'order' => $qData['order'] ?? 0,
                ]
            );

            $incomingAnswerIds = collect($qData['answers'])->pluck('id')->filter();
            QuizAnswer::where('question_id', $question->id)->whereNotIn('id', $incomingAnswerIds)->delete();

            foreach ($qData['answers'] as $aData) {
                QuizAnswer::updateOrCreate(
                    ['id' => $aData['id']],
                    [
                        'question_id' => $question->id,
                        'answer_text' => $aData['answer_text'],
                        'order' => $aData['order'] ?? 0,
                        'target_audience' => !empty($aData['target_audience']) ? $aData['target_audience'] : null,
                        'next_question_id' => !empty($aData['next_question_id']) ? (int)$aData['next_question_id'] : null,
                        'result_id' => $aData['result_id'] ?? null,
                    ]
                );
            }
        }

        // 2. Сохранение результатов
        $incomingResultIds = collect($validated['results'])->pluck('id')->filter();
        QuizResult::whereNotIn('id', $incomingResultIds)->delete();

        foreach ($validated['results'] as $rData) {
            QuizResult::updateOrCreate(
                ['id' => $rData['id']],
                [
                    'title' => $rData['title'],
                    'description' => $rData['description'],
                    'is_active' => $rData['is_active'] ?? true,
                    'priority' => $rData['priority'] ?? 1,
                ]
            );
        }

        // 3. Сохранение правил
        if (isset($validated['rules'])) {
            $incomingRuleIds = collect($validated['rules'])->pluck('id')->filter();
            QuizRule::whereNotIn('id', $incomingRuleIds)->delete();

            foreach ($validated['rules'] as $ruleData) {
                QuizRule::updateOrCreate(
                    ['id' => $ruleData['id']],
                    [
                        'result_id' => $ruleData['result_id'],
                        'priority' => $ruleData['priority'] ?? 1,
                        'conditions' => $ruleData['conditions'] ?? [],
                    ]
                );
            }
        }

        return redirect()->back()->with('success', 'Квиз и настройки успешно сохранены!');
    }
}