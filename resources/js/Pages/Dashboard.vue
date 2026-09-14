<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({
  auth: Object
})

// Структура разделов, полностью совпадающая с боковым меню
const sections = [
  {
    title: 'Блог',
    children: [
      { title: 'Список статей', route: 'admin.blog.index', desc: 'Управление публикациями' },
      { title: 'Категории статей', route: 'admin.categories.index', desc: 'Категории для постов' },
      { title: 'Теги статей', route: 'admin.blog-tags.index', desc: 'Ключевые теги постов' }
    ]
  },
  {
    title: 'Авторы',
    children: [
      { title: 'Список авторов', route: 'admin.authors.index', desc: 'Редактирование профилей авторов' },
      { title: 'Категории авторов', route: 'admin.author-categories.index', desc: 'Специализации и категории' }
    ]
  },
  {
    title: 'Лаборатория',
    children: [
      { title: 'Управление страницами', route: 'admin.alex-lab.index', desc: 'Контент и блоки Alex Lab' }
    ]
  },
  {
    title: 'Главная страница',
    children: [
      { title: 'Управление главной', route: 'admin.home.edit', desc: 'Редактирование блоков главной страницы' }
    ]
  },
  {
    title: 'Квиз',
    children: [
      { title: 'Настройки квиза', route: 'admin.quiz.index', desc: 'Управление вопросами и логикой' }
    ]
  },
  {
    title: 'Заказы и Корзина',
    children: [
      { title: 'Все заявки', route: 'admin.orders.index', desc: 'Просмотр и обработка заказов' },
      { title: 'Настройки корзины', route: 'admin.cart-settings.edit', desc: 'Параметры и правила корзины' }
    ]
  },
  {
    title: 'Демо-результат',
    children: [
      { title: 'Загрузка PDF', route: 'admin.demo-result.index', desc: 'Управление демонстрационными файлами' }
    ]
  },
  {
    title: 'Материалы для врачей',
    children: [
      { title: 'Управление материалами', route: 'admin.doctor-materials.index', desc: 'Загрузка и редактирование документов' }
    ]
  },
  {
    title: 'SEO и Настройки',
    children: [
      { title: 'Sitemap.xml', external: true, url: '/sitemap.xml', desc: 'Просмотр сгенерированного файла sitemap' },
      { title: 'Robots.txt', route: 'admin.robots.index', desc: 'Настройка директив индексации' },
      { title: 'Редиректы', route: 'admin.redirects.index', desc: 'Управление перенаправлениями URL' }
    ]
  }
]
</script>

<template>
  <Head title="Панель управления" />

  <AdminLayout>
    <div class="max-w-7xl mx-auto space-y-8">

      <!-- Заголовок -->
      <div>
        <h1 class="text-3xl font-bold text-gray-900 mb-1">
          Добро пожаловать, {{ auth.user?.name }}!
        </h1>
        <p class="text-gray-600">
          Выберите нужный раздел для перехода к управлению контентом.
        </p>
      </div>

      <!-- Быстрый переход по всем разделам -->
      <div class="space-y-8">
        <section v-for="section in sections" :key="section.title">
          <h2 class="text-xl font-bold text-gray-800 mb-3 pb-2 border-b border-gray-200">
            {{ section.title }}
          </h2>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <template v-for="item in section.children" :key="item.title">

              <!-- Внешняя ссылка (например, Sitemap.xml) -->
              <a
                  v-if="item.external"
                  :href="item.url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="group p-5 bg-white rounded-xl shadow-sm hover:shadow-md transition-all border border-gray-200 flex flex-col justify-between hover:border-blue-400"
              >
                <div>
                  <div class="flex items-center justify-between mb-1">
                    <h3 class="font-semibold text-gray-900 group-hover:text-blue-600 transition-colors">
                      {{ item.title }}
                    </h3>
                    <svg class="w-4 h-4 text-gray-400 group-hover:text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                    </svg>
                  </div>
                  <p class="text-sm text-gray-500">{{ item.desc }}</p>
                </div>
              </a>

              <!-- Внутренний Inertia-маршрут -->
              <Link
                  v-else
                  :href="route(item.route)"
                  class="group p-5 bg-white rounded-xl shadow-sm hover:shadow-md transition-all border border-gray-200 flex flex-col justify-between hover:border-blue-400"
              >
                <div>
                  <div class="flex items-center justify-between mb-1">
                    <h3 class="font-semibold text-gray-900 group-hover:text-blue-600 transition-colors">
                      {{ item.title }}
                    </h3>
                    <svg class="w-4 h-4 text-gray-400 group-hover:text-blue-600 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                  </div>
                  <p class="text-sm text-gray-500">{{ item.desc }}</p>
                </div>
              </Link>

            </template>
          </div>
        </section>
      </div>

    </div>
  </AdminLayout>
</template>