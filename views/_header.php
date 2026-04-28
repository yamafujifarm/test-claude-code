<?php /** @var string $pageTitle */ ?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="theme-color" content="#5c4033">
    <title><?= h($pageTitle ?? APP_NAME) ?> | <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/style.css?v=2">
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="米注文予測">
    <script>
        // Service Worker を登録（プッシュ通知の受信に必須）
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('./sw.js').catch(() => {});
            });
        }
    </script>
</head>
<body>
<header class="app-header">
    <div class="app-header__inner">
        <a href="<?= h(url('', ['p' => 'dashboard'])) ?>" class="app-header__title">
            <span class="app-header__brand">山藤</span>
            <span class="app-header__sub">やまふじ農園 お米 注文予測</span>
        </a>
    </div>
</header>
<main class="app-main">
