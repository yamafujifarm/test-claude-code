<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/helpers.php';
require __DIR__ . '/lib/predictor.php';
require __DIR__ . '/lib/csv.php';

try {
    $pdo = db();
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h1>データベース接続エラー</h1>';
    if (APP_DEBUG) {
        echo '<pre>' . h($e->getMessage()) . '</pre>';
    } else {
        echo '<p>config.php の接続情報を確認してください。</p>';
    }
    exit;
}

$pages = [
    'dashboard'       => 'dashboard.php',
    'customers'       => 'customers.php',
    'customer_new'    => 'customer_form.php',
    'customer_edit'   => 'customer_form.php',
    'customer_detail' => 'customer_detail.php',
    'customer_delete' => 'customer_delete.php',
    'purchase_new'    => 'purchase_form.php',
    'purchase_delete' => 'purchase_delete.php',
    'export'          => 'export.php',
    'import'          => 'import.php',
];

$page = (string)($_GET['p'] ?? 'dashboard');

if (!isset($pages[$page])) {
    http_response_code(404);
    $page = 'dashboard';
}

require __DIR__ . '/views/' . $pages[$page];
