<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const page = usePage()
const currentUser = computed(() => page.props.auth?.user)

// 🔹 Структура меню с URL-префиксами для проверки активности
const menuItems = [
  {
    title: 'Блог',
    icon: '',
    children: [
      {
        title: 'Список статей',
        route: 'admin.blog.index',
        urlPrefix: '/admin/blog'
      },
      {
        title: 'Категории статей',
        route: 'admin.categories.index',
        urlPrefix: '/admin/categories'
      },
      {
        title: 'Теги статей',
        route: 'admin.blog-tags.index',
        urlPrefix: '/admin/blog-tags'
      }
    ]
  },
  {
    title: 'Авторы',
    icon: '',
    children: [
      {
        title: 'Список авторов',
        route: 'admin.authors.index',
        urlPrefix: '/admin/authors'
      },
      {
        title: 'Категории авторов',
        route: 'admin.author-categories.index',
        urlPrefix: '/admin/author-categories'
      }
    ]
  },
  {
    title: 'Лаборатория',
    icon: '',
    children: [
      {
        title: 'Управление страницами',
        route: 'admin.alex-lab.index',
        urlPrefix: '/admin/alex-lab'
      }
    ]
  },
  {
    title: 'Главная',
    icon: '',
    children: [
      {
        title: 'Управление главной',
        route: 'admin.home.edit',
        urlPrefix: '/admin/home'
      }
    ]
  },
  {
    title: 'Квиз',
    icon: '',
    children: [
      {
        title: 'Настройки квиза',
        route: 'admin.quiz.index',
        urlPrefix: '/admin/quiz'
      }
    ]
  },
  {
    title: 'Аллергены',
    icon: '', 
    children: [
      {
        title: 'Поиск и база ALEX2',
        route: 'admin.allergens.index',
        urlPrefix: '/admin/allergens'
      }
    ]
  },
  {
    title: 'Заказы',
    icon: '',
    children: [
      {
        title: 'Все заявки',
        route: 'admin.orders.index',
        urlPrefix: '/admin/orders'
      },
      {
        title: 'Настройки корзины',
        route: 'admin.cart-settings.edit',
        urlPrefix: '/admin/cart-settings'
      }
    ]
  },
  {
    title: 'Демо-результат',
    icon: '',
    children: [
      {
        title: 'Загрузка PDF',
        route: 'admin.demo-result.index',
        urlPrefix: '/admin/demo-result'
      }
    ]
  },
  {
    title: 'Материалы для врачей',
    icon: '', // Можно добавить иконку, например: '👨‍⚕️' или SVG
    children: [
      {
        title: 'Документы',
        route: 'admin.doctor-materials.index',
        urlPrefix: '/admin/doctor-materials'
      },
      {
        title: 'Видеолекции',
        route: 'admin.doctor-videos.index',
        urlPrefix: '/admin/doctor-videos'
      }
    ]
  },
  {
    title: 'SEO',
    icon: '',
    children: [
      {
        title: 'Sitemap.xml',
        external: true, // 🔹 Пометка — внешняя ссылка
        url: '/sitemap.xml',
        urlPrefix: '/sitemap.xml'
      },
      {
        title: 'Robots',
        route: 'admin.robots.index', // 🔹 Теперь это внутренняя страница админки
        urlPrefix: '/admin/robots'
      }
      ,
      {
        title: 'Редиректы',
        route: 'admin.redirects.index',
        urlPrefix: '/admin/redirects'
      }
    ]
  }
]

// 🔹 Состояние раскрытия каждого родительского пункта
const expandedMenus = ref({})

// 🔹 Загрузка состояния из sessionStorage при монтировании
onMounted(() => {
  const savedState = sessionStorage.getItem('expandedMenus')
  if (savedState) {
    try {
      expandedMenus.value = JSON.parse(savedState)
    } catch (e) {
      console.error('Ошибка загрузки состояния меню:', e)
    }
  }

  // 🔹 Автоматически раскрываем пункт с активной страницей
  menuItems.forEach((item, index) => {
    if (isParentActive(item)) {
      expandedMenus.value[index] = true
    }
  })
})

// 🔹 Сохранение состояния в sessionStorage при изменениях
watch(expandedMenus, (newState) => {
  sessionStorage.setItem('expandedMenus', JSON.stringify(newState))
}, { deep: true })

// 🔹 Проверка активности по URL (исправлено!)
const isChildActive = (child) => {
  const currentUrl = page.url.split('?')[0]
  if (!child.urlPrefix) return false
  return currentUrl === child.urlPrefix || currentUrl.startsWith(child.urlPrefix + '/')
}

// 🔹 Проверка, активен ли родительский пункт
const isParentActive = (parent) => {
  return parent.children?.some(child => isChildActive(child))
}

// 🔹 Toggle раскрытия меню
const toggleMenu = (index) => {
  expandedMenus.value[index] = !expandedMenus.value[index]
}

// 🔹 Проверка, раскрыто ли меню
const isExpanded = (index) => {
  // 🔹 Автоматически раскрываем, если активен дочерний пункт
  if (isParentActive(menuItems[index])) {
    return true
  }
  return expandedMenus.value[index] || false
}

// Мобильное меню
const sidebarOpen = ref(false)
</script>

<template>
  <div class="min-h-screen bg-gray-100 flex" style="flex-wrap: wrap;">

    <!-- Sidebar -->
    <!-- Sidebar -->
    <aside
        :class="{'translate-x-0': sidebarOpen, '-translate-x-full': !sidebarOpen}"
        class="fixed inset-y-0 left-0 z-50 w-64 bg-gray-900 text-white transform transition-transform duration-200 ease-in-out lg:translate-x-0 lg:inset-0 flex flex-col h-screen" style="width: 20%;"
    >
      <!-- Шапка сайдбара (фиксированная) -->
      <div class="flex items-center justify-between h-16 px-4 border-b border-gray-700 shrink-0">
        <span class="text-xl font-bold">Админ-панель</span>
        <button @click="sidebarOpen = false" class="lg:hidden text-gray-400 hover:text-white">
          ✕
        </button>
      </div>

      <!-- Скроллируемая область навигации -->
      <nav class="flex-1 overflow-y-auto py-5 px-2 space-y-1 custom-scrollbar">
        <div v-for="(item, index) in menuItems" :key="index">
          <!-- 🔹 Родительский пункт -->
          <button
              @click="toggleMenu(index)"
              :class="[
              isParentActive(item)
                ? 'bg-gray-800 text-white border-l-4 border-blue-500'
                : 'text-gray-300 hover:bg-gray-800 hover:text-white border-l-4 border-transparent',
              'group flex items-center justify-between w-full px-3 py-3 text-sm font-medium rounded-r-md transition-colors'
            ]"
          >
            <div class="flex items-center">
              <span class="mr-3 text-lg">{{ item.icon }}</span>
              {{ item.title }}
            </div>
            <!-- 🔹 Стрелка раскрытия -->
            <svg
                :class="{'rotate-90': isExpanded(index)}"
                class="w-4 h-4 transition-transform duration-200"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </button>

          <!-- 🔹 Дочерние пункты -->
          <div
              v-show="isExpanded(index)"
              class="ml-4 mt-1 space-y-1 border-l-2 border-gray-700"
          >
            <template v-for="child in item.children" :key="child.title">

              <!-- 🔹 Внешняя ссылка (Sitemap, Robots.txt) -->
              <a
                  v-if="child.external"
                  :href="child.url"
                  target="_blank"
                  rel="noopener noreferrer"
                  :class="[
          isChildActive(child)
            ? 'bg-gray-800 text-white border-l-4 border-blue-500'
            : 'text-gray-400 hover:bg-gray-800 hover:text-white border-l-4 border-transparent',
          'group flex items-center justify-between px-3 py-2 text-sm font-medium rounded-r-md transition-colors'
        ]"
              >
                <span>{{ child.title }}</span>
                <svg class="w-3.5 h-3.5 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                </svg>
              </a>

              <!-- 🔹 Обычная ссылка Inertia -->
              <Link
                  v-else
                  :href="route(child.route)"
                  :class="[
          isChildActive(child)
            ? 'bg-gray-800 text-white border-l-4 border-blue-500'
            : 'text-gray-400 hover:bg-gray-800 hover:text-white border-l-4 border-transparent',
          'group flex items-center px-3 py-2 text-sm font-medium rounded-r-md transition-colors'
        ]"
              >
                {{ child.title }}
              </Link>

            </template>
          </div>
        </div>
      </nav>

      <!-- Фиксированный профиль внизу (без absolute) -->
      <div class="p-4 border-t border-gray-700 bg-gray-900 shrink-0">
        <div class="flex items-center gap-3">
          <img
              v-if="currentUser?.avatar"
              :src="`/storage/${currentUser.avatar}`"
              class="w-10 h-10 rounded-full object-cover"
          />
          <div v-else class="w-10 h-10 rounded-full bg-gray-600 flex items-center justify-center font-medium">
            {{ currentUser?.name?.charAt(0)?.toUpperCase() }}
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium truncate">{{ currentUser?.name }}</p>
            <p class="text-xs text-gray-400 truncate">{{ currentUser?.email }}</p>
          </div>
        </div>
        <Link
            :href="route('logout')"
            method="post"
            as="button"
            class="mt-3 w-full text-left text-xs text-gray-400 hover:text-white"
        >
          Выйти
        </Link>
      </div>
    </aside>

    <!-- Overlay для мобильного -->
    <div
        v-if="sidebarOpen"
        @click="sidebarOpen = false"
        class="fixed inset-0 z-40 bg-black bg-opacity-50 lg:hidden"
    ></div>

    <!-- Основной контент -->
    <div class="flex-1 flex flex-col min-w-0" style="flex: 0 0 80%; margin-left: auto;">

      <!-- Верхняя панель (мобильная) -->
      <header class="lg:hidden bg-white shadow-sm">
        <div class="flex items-center justify-between h-16 px-4">
          <button @click="sidebarOpen = true" class="text-gray-500 hover:text-gray-700">
            ☰
          </button>
          <span class="font-semibold">Админ-панель</span>
          <div class="w-8"></div>
        </div>
      </header>

      <!-- Контент страницы -->
      <main class="flex-1 p-6 overflow-y-auto">
        <slot />
      </main>

    </div>
  </div>
</template>