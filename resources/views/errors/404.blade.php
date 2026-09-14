<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ошибка 404 — ALEX2</title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="Страница не найдена. Вернитесь на главную или воспользуйтесь меню сайта.">
    <link rel="icon" href="/favicon.ico?v=20260826x" sizes="any">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    {{-- Vite entry pulls site.css; Error404 mounts when #error-404-app is present (see app.js). --}}
    @vite(['resources/js/app.js'])
    <style>
        :root {
            --font-sans: "Roboto", "Helvetica Neue", "Segoe UI", system-ui, -apple-system, Arial, sans-serif;
            --font-size-h1: 42px;
            --font-size-h4: 21px;
            --font-size-body: 17px;
            --color-white: #fff;
            --color-black: #000;
            --color-ink: #121212;
            --space-warm: #9d8d74;
            --space-mid: #857d70;
            --space-cool: #5b6675;
            --space-deep: #46556a;
            --space-glow-warm: #e4bc7c8c;
            --space-glow-cool: #44608480;
            --btn-bg: #fff;
            --btn-bg-hover: #f1f5ee;
            --btn-border: #dfdfdf;
            --btn-border-hover: #cfd6cb;
            --btn-primary-bg: #121212;
            --btn-primary-bg-hover: #2a2a2a;
            --radius-sm: 10px;
            --radius-md: 14px;
            --radius-lg: 18px;
            --ease-out-soft: cubic-bezier(.22, .61, .36, 1);
            --duration-fast: .18s;
            --duration-base: .32s;
        }
        html, body {
            margin: 0;
            padding: 0;
            min-height: 100%;
            background-color: #0b0f19;
        }
    </style>
</head>
<body>
<div id="error-404-app"></div>
</body>
</html>
