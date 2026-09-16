<script setup>
import { ref, computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  quizMeta: { type: Object, default: () => ({ title: '', description: '', keywords: '' }) },
  questions: { type: Array, default: () => [] },
  results: { type: Array, default: () => [] },
  rules: { type: Array, default: () => [] },
  settings: { type: Object, default: () => ({ is_split_enabled: true }) }
})

// Хелпер генерации числовых временных ID (отрицательные числа)
let tempIdCounter = -1
const generateTempId = () => tempIdCounter--

// 🔹 Основная форма Inertia
const form = useForm({
  meta_title: props.quizMeta?.title || '',
  meta_description: props.quizMeta?.description || '',
  meta_keywords: props.quizMeta?.keywords || '',

  is_split_enabled: props.settings?.is_split_enabled ?? true,

  results: props.results.map(r => ({
    ...r,
    id: r.id || generateTempId(),
    priority: r.priority || 1,
  })),

  questions: props.questions.map(q => {
    const qId = q.id || generateTempId()
    return {
      ...q,
      id: qId,
      audience_target: q.audience_target || 'all',
      answers: (q.answers || []).map(a => ({
        ...a,
        id: a.id || generateTempId(),
        next_question_id: a.next_question_id || null,
        target_audience: a.target_audience || null, // 'adult' | 'child' | null
      })),
    }
  }),

  rules: props.rules ? props.rules.map(r => ({
    ...r,
    id: r.id || generateTempId(),
    conditions: (r.conditions || []).map(c => ({
      ...c,
      answer_ids: c.answer_ids || []
    }))
  })) : [],
})

const isEditingSeo = ref(false)
const activeAudienceTab = ref('all')

const addQuestion = (target = 'all') => {
  form.questions.push({
    id: generateTempId(),
    order: form.questions.length,
    question_text: '',
    question_type: 'single',
    audience_target: form.is_split_enabled ? target : 'all',
    is_active: true,
    answers: [],
  })
}

const removeQuestion = (originalIndex) => {
  const removedQuestion = form.questions[originalIndex]
  form.questions.splice(originalIndex, 1)

  if (removedQuestion?.id) {
    form.rules.forEach(rule => {
      rule.conditions = rule.conditions.filter(c => c.question_id !== removedQuestion.id)
    })
  }
}

const addAnswer = (questionOriginalIndex) => {
  form.questions[questionOriginalIndex].answers.push({
    id: generateTempId(),
    answer_text: '',
    order: form.questions[questionOriginalIndex].answers.length,
    next_question_id: null,
    target_audience: null,
    result_id: null,
  })
}

const removeAnswer = (questionOriginalIndex, answerIndex) => {
  const removedAnswer = form.questions[questionOriginalIndex].answers[answerIndex]
  form.questions[questionOriginalIndex].answers.splice(answerIndex, 1)

  if (removedAnswer?.id) {
    form.rules.forEach(rule => {
      rule.conditions.forEach(c => {
        if (Array.isArray(c.answer_ids)) {
          c.answer_ids = c.answer_ids.filter(id => id !== removedAnswer.id)
        }
      })
    })
  }
}

const addRule = () => {
  form.rules.push({
    id: generateTempId(),
    result_id: form.results.length > 0 ? form.results[0].id : null,
    priority: form.rules.length + 1,
    conditions: [],
  })
}

const removeRule = (index) => form.rules.splice(index, 1)

const addConditionToRule = (ruleIndex) => {
  form.rules[ruleIndex].conditions.push({
    question_id: null,
    answer_ids: [],
    operator: 'AND'
  })
}

const removeConditionFromRule = (ruleIndex, condIndex) => {
  form.rules[ruleIndex].conditions.splice(condIndex, 1)
}

const getAnswersForQuestion = (questionId) => {
  if (!questionId) return []
  const question = form.questions.find(q => q.id === questionId)
  return question?.answers || []
}

const submit = () => {
  // 1. Очистка пустых ответов и вопросов перед отправкой
  form.questions = form.questions
      .filter(q => q.question_text && q.question_text.trim() !== '')
      .map(q => {
        q.answers = (q.answers || []).filter(a => a.answer_text && a.answer_text.trim() !== '')
        return q
      })

  form.rules = form.rules
      .map(rule => {
        rule.conditions = (rule.conditions || []).filter(
            c => c.question_id !== null && c.question_id !== undefined && Array.isArray(c.answer_ids) && c.answer_ids.length > 0
        )
        return rule
      })
      .filter(rule => rule.result_id !== null && rule.conditions.length > 0)

  // Временные отрицательные ID превращаем в null для БД
  const cleanTempId = (id) => (typeof id === 'number' && id < 0 ? null : id)

  const payload = JSON.parse(JSON.stringify(form.data()))

  payload.is_split_enabled = form.is_split_enabled

  payload.results = payload.results.map(r => ({
    ...r,
    id: cleanTempId(r.id)
  }))

  // 🔹 Явно прописываем audience_target для каждого вопроса
  payload.questions = payload.questions.map(q => ({
    ...q,
    id: cleanTempId(q.id),
    audience_target: q.audience_target || 'all', // 👈 Явное сохранение ветки вопроса ('all' | 'adult' | 'child')
    is_active: q.is_active ?? true,
    answers: (q.answers || []).map(a => ({
      ...a,
      id: cleanTempId(a.id),
      target_audience: a.target_audience || null,
      next_question_id: a.next_question_id ? cleanTempId(Number(a.next_question_id)) : null
    }))
  }))

  payload.rules = payload.rules.map(r => ({
    ...r,
    id: cleanTempId(r.id),
    result_id: cleanTempId(r.result_id),
    conditions: (r.conditions || []).map(c => ({
      ...c,
      question_id: cleanTempId(c.question_id),
      answer_ids: (c.answer_ids || []).map(cleanTempId)
    }))
  }))

  form.transform(() => payload).post(route('admin.quiz.store'), {
    preserveScroll: true,
    onSuccess: () => {
      isEditingSeo.value = false
    },
    onError: (errors) => {
      console.error('Ошибки валидации:', errors)
    }
  })
}
</script>

<template>
  <Head title="Конструктор Квиза" />

  <AdminLayout>
    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div v-if="$page.props.flash?.success" class="p-4 bg-green-100 border border-green-300 text-green-700 rounded-lg">
          {{ $page.props.flash.success }}
        </div>

        <!-- 🔹 1. SEO Настройки -->
        <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
          <div class="flex items-start justify-between mb-4">
            <div>
              <h3 class="text-lg font-semibold text-gray-800">SEO настройки Квиза (Meta-теги)</h3>
              <p class="text-xs text-gray-500">Мета-данные для поисковых систем и соцсетей</p>
            </div>
            <button v-if="!isEditingSeo" type="button" @click="isEditingSeo = true" class="text-sm text-blue-600 font-medium">Редактировать</button>
            <button v-else type="button" @click="isEditingSeo = false" class="text-sm text-gray-600">Отмена</button>
          </div>

          <div v-if="isEditingSeo" class="space-y-4">
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Meta Title</label>
              <input v-model="form.meta_title" type="text" class="w-full border-gray-300 rounded-md text-sm" />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Meta Description</label>
              <textarea v-model="form.meta_description" rows="2" class="w-full border-gray-300 rounded-md text-sm"></textarea>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Meta Keywords</label>
              <input v-model="form.meta_keywords" type="text" class="w-full border-gray-300 rounded-md text-sm" />
            </div>
          </div>

          <div v-else class="space-y-2 text-sm">
            <div><span class="font-medium text-gray-500 text-xs">Title: </span><span>{{ form.meta_title || '—' }}</span></div>
            <div><span class="font-medium text-gray-500 text-xs">Description: </span><span>{{ form.meta_description || '—' }}</span></div>
            <div><span class="font-medium text-gray-500 text-xs">Keywords: </span><span>{{ form.meta_keywords || '—' }}</span></div>
          </div>
        </div>

        <!-- 🔹 Главный блок Конструктора -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
          <div class="flex flex-col md:flex-row md:items-center justify-between border-b pb-4 mb-6 gap-4">
            <h1 class="text-2xl font-bold text-gray-800">Конструктор Квиза</h1>

            <div class="flex items-center gap-3 bg-gray-50 p-3 rounded-lg border border-gray-200">
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="form.is_split_enabled" class="sr-only peer">
                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
              </label>
              <div>
                <span class="text-sm font-semibold text-gray-700 block">Разделение (Взрослый / Ребенок)</span>
                <span class="text-xs text-gray-500">{{ form.is_split_enabled ? 'Включено' : 'Отключено' }}</span>
              </div>
            </div>
          </div>

          <form @submit.prevent="submit">

            <!-- 1. Результаты квиза -->
            <div class="mb-10">
              <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold text-gray-700">1. Результаты квиза</h2>
                <button type="button" @click="form.results.push({ id: generateTempId(), title: '', description: '', is_active: true, priority: form.results.length + 1 })" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600 text-sm">
                  + Добавить результат
                </button>
              </div>

              <div v-for="(result, index) in form.results" :key="result.id || index" class="mb-4 p-4 border border-gray-200 rounded-lg bg-gray-50">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Приоритет (1 = высший)</label>
                    <input v-model.number="result.priority" type="number" min="1" class="w-full border-gray-300 rounded-md shadow-sm" />
                  </div>
                  <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Заголовок результата</label>
                    <input v-model="result.title" type="text" class="w-full border-gray-300 rounded-md shadow-sm" />
                  </div>
                </div>
                <div class="mb-4">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Описание</label>
                  <textarea v-model="result.description" rows="2" class="w-full border-gray-300 rounded-md shadow-sm"></textarea>
                </div>
                <div class="flex items-center justify-between">
                  <label class="flex items-center">
                    <input v-model="result.is_active" type="checkbox" class="rounded border-gray-300 text-blue-600" />
                    <span class="ml-2 text-sm text-gray-700">Активен</span>
                  </label>
                  <button type="button" @click="form.results.splice(index, 1)" class="text-red-500 hover:text-red-700 text-sm">Удалить результат</button>
                </div>
              </div>
            </div>

            <!-- 2. Вопросы и варианты ответов -->
            <div class="mb-10">
              <div class="flex flex-col md:flex-row md:items-center justify-between mb-4 gap-4">
                <h2 class="text-xl font-semibold text-gray-700">2. Вопросы и варианты ответов</h2>
                <button type="button" @click="addQuestion(activeAudienceTab)" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm">
                  + Добавить вопрос
                </button>
              </div>

              <!-- Переключатель аудитории вопросов -->
              <div v-if="form.is_split_enabled" class="flex border-b border-gray-200 mb-6 space-x-4">
                <button type="button" @click="activeAudienceTab = 'all'" class="py-2 px-4 text-sm font-medium border-b-2" :class="activeAudienceTab === 'all' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500'">
                  Все вопросы ({{ form.questions.length }})
                </button>
                <button type="button" @click="activeAudienceTab = 'adult'" class="py-2 px-4 text-sm font-medium border-b-2" :class="activeAudienceTab === 'adult' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500'">
                  👨 Только Взрослый ({{ form.questions.filter(q => q.audience_target === 'adult').length }})
                </button>
                <button type="button" @click="activeAudienceTab = 'child'" class="py-2 px-4 text-sm font-medium border-b-2" :class="activeAudienceTab === 'child' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500'">
                  👶 Только Ребенок ({{ form.questions.filter(q => q.audience_target === 'child').length }})
                </button>
              </div>

              <!-- Список вопросов -->
              <div
                  v-for="(question, qIndex) in form.questions"
                  :key="question.id || qIndex"
                  v-show="!form.is_split_enabled || activeAudienceTab === 'all' || question.audience_target === activeAudienceTab || question.audience_target === 'all'"
                  class="mb-6 p-4 border border-gray-200 rounded-lg bg-blue-50/50 relative"
              >
                <div class="flex flex-col md:flex-row justify-between items-start mb-4 gap-4">
                  <div class="flex-1 w-full">
                    <div class="flex items-center gap-2 mb-2">
                      <span class="font-bold text-gray-700">Вопрос #{{ qIndex + 1 }}</span>
                      <span v-if="form.is_split_enabled" class="ml-2">
                        <select v-model="question.audience_target" class="text-xs border-gray-300 rounded px-2 py-1 font-semibold bg-white">
                          <option value="all">🌐 Для всех</option>
                          <option value="adult">👨 Только Взрослый</option>
                          <option value="child">👶 Только Ребенок</option>
                        </select>
                      </span>
                    </div>

                    <input v-model="question.question_text" type="text" class="w-full border-gray-300 rounded-md shadow-sm" placeholder="Введите текст вопроса..." />
                  </div>

                  <button type="button" @click="removeQuestion(qIndex)" class="text-red-500 hover:text-red-700 text-sm">Удалить</button>
                </div>

                <div class="mb-4">
                  <label class="block text-xs font-medium text-gray-600 mb-1">Тип выбора ответов</label>
                  <select v-model="question.question_type" class="border-gray-300 rounded-md shadow-sm text-sm">
                    <option value="single">Один вариант</option>
                    <option value="multiple">Несколько вариантов</option>
                  </select>
                </div>

                <!-- Варианты ответов -->
                <div class="ml-2 md:ml-4 border-l-2 border-blue-200 pl-4 space-y-3">
                  <div class="flex justify-between items-center mb-2">
                    <h3 class="text-xs font-semibold text-gray-600">Варианты ответов</h3>
                    <button type="button" @click="addAnswer(qIndex)" class="text-xs bg-green-500 text-white px-2 py-1 rounded hover:bg-green-600">
                      + Добавить ответ
                    </button>
                  </div>

                  <div v-for="(answer, aIndex) in question.answers" :key="answer.id || aIndex" class="p-3 bg-white border border-gray-200 rounded-md space-y-2">
                    <div class="flex items-center gap-2">
                      <span class="text-xs text-gray-500 w-6">{{ aIndex + 1 }}.</span>
                      <input v-model="answer.answer_text" type="text" class="flex-1 border-gray-300 rounded-md shadow-sm text-sm" placeholder="Текст ответа (например: Для себя / Для ребенка)" />
                      <button type="button" @click="removeAnswer(qIndex, aIndex)" class="text-red-500 hover:text-red-700 text-sm">✕</button>
                    </div>

                    <!-- 🔹 Перенаправление / Ветвление для ответа -->
                    <div v-if="form.is_split_enabled" class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs bg-gray-50 p-2 rounded">
                      <div>
                        <label class="block text-gray-500 font-medium mb-1">Переключить ветку аудитории:</label>
                        <select v-model="answer.target_audience" class="w-full text-xs border-gray-300 rounded">
                          <option :value="null">По умолчанию (Не менять)</option>
                          <option value="adult">👨 Перейти к вопросам Взрослого</option>
                          <option value="child">👶 Перейти к вопросам Ребенка</option>
                        </select>
                      </div>
                      <div>
                        <label class="block text-gray-500 font-medium mb-1">Или перейти сразу к вопросу:</label>
                        <select v-model.number="answer.next_question_id" class="w-full text-xs border-gray-300 rounded">
                          <option :value="null">К следующему по порядку</option>
                          <option v-for="(q, targetQIdx) in form.questions.filter(item => item.id !== question.id)" :key="q.id" :value="q.id">
                            #{{ targetQIdx + 1 }} [{{ q.audience_target?.toUpperCase() }}] {{ q.question_text || 'Без названия' }}
                          </option>
                        </select>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- 3. Правила логики (Правила результатов) -->
            <div class="mb-8">
              <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold text-gray-700">3. Логика выдачи результатов (Правила)</h2>
                <button type="button" @click="addRule" class="px-4 py-2 bg-purple-500 text-white rounded hover:bg-purple-600 text-sm">
                  + Добавить правило
                </button>
              </div>

              <div v-if="form.rules.length === 0" class="text-center py-8 text-gray-400 bg-gray-50 rounded-lg border border-dashed border-gray-300">
                Нет добавленных правил. Нажмите "+ Добавить правило", чтобы настроить логику результатов.
              </div>

              <div v-for="(rule, ruleIndex) in form.rules" :key="ruleIndex" class="mb-6 p-5 bg-white rounded-lg border-2 border-purple-100">
                <div class="flex justify-between items-start mb-4">
                  <h3 class="text-lg font-semibold text-purple-700">Правило #{{ ruleIndex + 1 }}</h3>
                  <button type="button" @click="removeRule(ruleIndex)" class="text-red-500 hover:text-red-700 text-sm">Удалить правило</button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Показать результат</label>
                    <select v-model="rule.result_id" class="w-full border-gray-300 rounded-md shadow-sm">
                      <option :value="null">— Выберите результат —</option>
                      <option v-for="res in form.results" :key="res.id" :value="res.id">
                        {{ res.title }} (Приоритет: {{ res.priority }})
                      </option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Приоритет правила (1 = высший)</label>
                    <input v-model.number="rule.priority" type="number" min="1" class="w-full border-gray-300 rounded-md shadow-sm" />
                  </div>
                </div>

                <div class="bg-gray-50 p-4 rounded-md border border-gray-200">
                  <div class="flex justify-between items-center mb-3">
                    <h4 class="text-sm font-semibold text-gray-600">Условия (ЕСЛИ выполнены следующие условия):</h4>
                    <button type="button" @click="addConditionToRule(ruleIndex)" class="text-xs bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-600">
                      + Добавить условие
                    </button>
                  </div>

                  <div v-for="(condition, condIndex) in rule.conditions" :key="condIndex" class="mb-4 p-3 bg-white rounded border border-gray-200">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                      <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Если на вопрос:</label>
                        <select v-model="condition.question_id" class="w-full border-gray-300 rounded-md shadow-sm text-sm">
                          <option :value="null">— Выберите вопрос —</option>
                          <option v-for="q in form.questions" :key="q.id" :value="q.id">
                            [{{ q.audience_target?.toUpperCase() || 'ALL' }}] {{ q.question_text || 'Без названия' }}
                          </option>
                        </select>
                      </div>
                      <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Выбран один из ответов:</label>
                        <div class="max-h-32 overflow-y-auto border border-gray-200 rounded-md p-2 bg-gray-50">
                          <div v-if="!condition.question_id" class="text-xs text-gray-400 italic p-2">Сначала выберите вопрос выше</div>
                          <div v-else-if="getAnswersForQuestion(condition.question_id).length === 0" class="text-xs text-gray-400 italic p-2">Нет ответов</div>
                          <label v-else v-for="ans in getAnswersForQuestion(condition.question_id)" :key="ans.id" class="flex items-center mb-1 cursor-pointer">
                            <input type="checkbox" :value="ans.id" v-model="condition.answer_ids" class="rounded border-gray-300 text-blue-600 mr-2" />
                            <span class="text-sm text-gray-700">{{ ans.answer_text }}</span>
                          </label>
                        </div>
                      </div>
                    </div>
                    <button type="button" @click="removeConditionFromRule(ruleIndex, condIndex)" class="text-xs text-red-500 hover:text-red-700">Удалить условие</button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Сохранить -->
            <div class="flex justify-end pt-4 border-t">
              <button type="submit" :disabled="form.processing" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 font-semibold">
                {{ form.processing ? 'Сохранение...' : 'Сохранить весь квиз и настройки' }}
              </button>
            </div>
          </form>
        </div>

      </div>
    </div>
  </AdminLayout>
</template>