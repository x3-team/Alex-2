<script setup>
import { Link } from '@inertiajs/vue3'
</script>

<template>
  <footer class="w-full footer_cust" >

    <!-- H1 заголовок -->
    <div class="footer_h">
      Тест на аллергию ALEX² — один анализ, который даёт ответы
    </div>

    <!-- Две колонки: Контакты и Навигация -->
    <div class="flex flex-col md:flex-row gap-8 mt-[44px] xl:mt-[64px]">

      <!-- Контакты (30%) -->
      <div class="w-full md:w-[30%]">
        <h4 style="color: #000; font-family: Roboto; font-size: 21px; font-style: normal; font-weight: 400; line-height: normal;">
          Контакты
        </h4>

        <!-- Разделительная линия -->
        <div class="mt-[21px]" style="border-top: 1px solid #000; opacity: 0.2;"></div>

        <!-- Контактная информация -->
        <div class="mt-[21px] flex flex-col gap-2">
          <p style="color: #000; font-family: Roboto; font-size: 16px; font-style: normal; font-weight: 400; line-height: normal;">
            Москва, Таганская улица, 3
          </p>
          <a href="tel:+79774474907" style="color: #000; font-family: Roboto; font-size: 16px; font-style: normal; font-weight: 400; line-height: normal;">
            +7 (977) 447 49 07
          </a>
          <a href="mailto:info@alexallergotest.ru" style="color: #000; font-family: Roboto; font-size: 16px; font-style: normal; font-weight: 400; line-height: normal;">
            info@alexallergotest.ru
          </a>
        </div>

        <!-- Реквизиты -->
        <div class="mt-[21px] flex flex-col gap-2 opacity-[0.4]" >
          <p style="color: #000; font-family: Roboto; font-size: 14px; font-style: normal; font-weight: 400; line-height: normal;">
            ИНН: 7733352084
          </p>
          <p style="color: #000; font-family: Roboto; font-size: 14px; font-style: normal; font-weight: 400; line-height: normal;">
            ОГРН: 1207700075032
          </p>
          <p style="color: #000; font-family: Roboto; font-size: 14px; font-style: normal; font-weight: 400; line-height: normal;">
            КПП: 773301001
          </p>
        </div>
      </div>

      <!-- Навигация по сайту (70%) -->
      <div class="w-full md:w-[70%]">
        <h4 style="color: #000; font-family: Roboto; font-size: 21px; font-style: normal; font-weight: 400; line-height: normal;">
          Навигация по сайту
        </h4>

        <!-- Разделительная линия -->
        <div class="mt-[21px]" style="border-top: 1px solid #000; opacity: 0.2;"></div>

        <!-- Навигация -->
        <div class="mt-[21px] flex flex-col gap-2">




          <Link href="/search" style="color: #000; font-family: Roboto; font-size: 16px; font-style: normal; font-weight: 400; line-height: normal;">Поиск аллергенов</Link>
          <Link href="/demo-result" style="color: #000; font-family: Roboto; font-size: 16px; font-style: normal; font-weight: 400; line-height: normal;">Демо-результат</Link>
          <Link href="/quiz" style="color: #000; font-family: Roboto; font-size: 16px; font-style: normal; font-weight: 400; line-height: normal;">КВИЗ: Нужен ли вам тест</Link>

          <Link href="/alex-lab" style="color: #000; font-family: Roboto; font-size: 16px; font-style: normal; font-weight: 400; line-height: normal;">
            О лаборатории ALEXLAB
          </Link>
          <Link href="/blog" style="color: #000; font-family: Roboto; font-size: 16px; font-style: normal; font-weight: 400; line-height: normal;">
            Блог
          </Link>
        </div>
      </div>
    </div>


    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-8 mt-[44px] xl:mt-[64px]">


      <div class="flex flex-col gap-2">
        <Link href="/alex-lab/privacy" style="color: #000; font-family: Roboto; font-size: 16px; font-style: normal; font-weight: 400; line-height: normal;">
          Политика конфиденциальности
        </Link>
        <Link href="/consent" style="color: #000; font-family: Roboto; font-size: 16px; font-style: normal; font-weight: 400; line-height: normal;">
          Условия использования материалов сайта
        </Link>
      </div>


      <a href="https://t.me/cartonasunsetbeach" class="wrap_icon_and_text">

        <svg xmlns="http://www.w3.org/2000/svg" width="50" height="20" viewBox="0 0 79 35" fill="none">
          <path d="M78.9062 1.99427C78.6982 2.56358 78.1758 2.93966 77.593 2.93966H76.7636C75.6534 2.93966 74.6647 3.67398 74.299 4.77008L73.9582 5.7918L69.7479 20.3136C69.5017 21.163 69.3765 22.0457 69.3765 22.9332V25.8011C69.3765 27.4634 68.0877 28.811 66.4979 28.811H47.4218C46.8009 28.811 46.2536 28.3851 46.0753 27.7633C45.8048 26.8203 46.4802 25.8713 47.4218 25.8713H65.1187C65.9175 25.8713 66.5651 25.1942 66.5651 24.3589C66.5651 23.5237 65.9175 22.8466 65.1187 22.8466L46.8073 22.8466C45.536 22.8466 44.4152 21.9745 44.05 20.7012L39.8889 6.19109C39.5566 5.03243 40.3864 3.86645 41.5433 3.86645L69.6648 3.86644C70.8374 3.86644 71.8816 3.09088 72.2678 1.93322C72.654 0.775558 73.6983 0 74.8708 0H77.593C78.5781 0 79.2577 1.03202 78.9062 1.99427Z" fill="black" fill-opacity="0.3"/>
          <path d="M52.7719 32.5319C52.7719 33.895 51.7151 35 50.4114 35C49.1078 35 48.051 33.895 48.051 32.5319C48.051 31.1688 49.1078 30.0638 50.4114 30.0638C51.7151 30.0638 52.7719 31.1688 52.7719 32.5319Z" fill="black" fill-opacity="0.3"/>
          <path d="M67.4815 32.5319C67.4815 33.895 66.4247 35 65.1211 35C63.8174 35 62.7606 33.895 62.7606 32.5319C62.7606 31.1688 63.8174 30.0638 65.1211 30.0638C66.4247 30.0638 67.4815 31.1688 67.4815 32.5319Z" fill="black" fill-opacity="0.3"/>
          <path d="M33.4789 29.2713C33.2857 29.8651 32.7524 30.2644 32.1526 30.2644H7.06326C6.46339 30.2644 5.93009 29.8651 5.73694 29.2713C5.42792 28.3211 6.10348 27.3348 7.06326 27.3348H32.1526C33.1124 27.3348 33.7879 28.3211 33.4789 29.2713Z" fill="black" fill-opacity="0.3"/>
          <path d="M26.9244 33.3083C26.7399 33.8135 26.2763 34.1472 25.7591 34.1472H13.4532C12.936 34.1472 12.4725 33.8135 12.2879 33.3083C11.9761 32.4544 12.5791 31.5386 13.4532 31.5386H25.7591C26.6333 31.5386 27.2363 32.4544 26.9244 33.3083Z" fill="black" fill-opacity="0.3"/>
          <path d="M39.133 25.0674C38.9399 25.6613 38.4066 26.0606 37.8067 26.0606H1.40202C0.802147 26.0606 0.268847 25.6613 0.0757053 25.0674C-0.233317 24.1173 0.442241 23.131 1.40202 23.131H37.8067C38.7665 23.131 39.442 24.1173 39.133 25.0674Z" fill="black" fill-opacity="0.3"/>
          <path d="M35.2652 21.2599C36.0601 21.2599 36.7108 20.5848 36.644 19.7565C36.3059 15.5641 34.5619 11.6118 31.695 8.61413C28.4874 5.26027 24.137 3.37609 19.6009 3.37609C15.0647 3.37609 10.7143 5.26027 7.50674 8.61412C4.63981 11.6118 2.89577 15.5641 2.55769 19.7565C2.4909 20.5848 3.14159 21.2599 3.93649 21.2599L35.2652 21.2599Z" fill="black" fill-opacity="0.3"/>
        </svg>
        <p  class="fut_cust">
          сайт сделан в студии «созерцание»
        </p>
      </a>
    </div>
  </footer>
</template>
<style>



.wrap_icon_and_text{
  display: flex;
  align-items: center;
  gap: 18px ;
  color: #0000004d;
}
.fut_cust{
  display: block;
  width: 145px;
  font-family: Roboto, Arial, sans-serif;
  font-size: 12px;
  font-weight: 400;
  line-height: 14px;
  color: #0000004d;
  text-transform: uppercase;
  white-space: normal;
  word-wrap: break-word;
}
.footer_cust{
  padding: 0 24px 24px;
  background: unset;
}
.footer_h{
  align-self: stretch;
  color: #000;
  leading-trim: both;
  text-edge: cap;
  font-family: Roboto;
  font-size: 24px;
  font-style: normal;
  font-weight: 400;
  line-height: normal;
}
@media (min-width: 1240px) {
  .footer_cust {
    background: #ECECEC;
    padding: 83px 64px 74px 64px;

  }
  .footer_h{
    color: #000;
    font-family: Roboto;
    font-size: 42px;
    font-style: normal;
    font-weight: 400;
    line-height: normal;
    max-width: 954px;
  }
}
</style>