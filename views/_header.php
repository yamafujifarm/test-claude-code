<?php /** @var string $pageTitle */ ?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="theme-color" content="#5c4033">
    <title><?= h($pageTitle ?? APP_NAME) ?> | <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/style.css?v=1">
    <link rel="apple-touch-icon" href="assets/icon.png">
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
