<?php
/** @var PDO $pdo */

$categoryFilter = (string)($_GET['cat'] ?? '');
$keyword        = trim((string)($_GET['q'] ?? ''));

$entries = fetch_customers_with_prediction($pdo, $categoryFilter !== '' ? $categoryFilter : null);

if ($keyword !== '') {
    $entries = array_filter($entries, function ($e) use ($keyword) {
        return mb_stripos($e['customer']['name'], $keyword) !== false
            || mb_stripos((string)$e['customer']['phone'], $keyword) !== false;
    });
}

// 予測日順
usort($entries, function ($a, $b) {
    $da = $a['prediction']['next_date'] ?? '9999-12-31';
    $db = $b['prediction']['next_date'] ?? '9999-12-31';
    return strcmp($da, $db);
});

$pageTitle = '顧客一覧';
require __DIR__ . '/_header.php';
?>

<section class="page-section">
    <div class="page-actions">
        <a class="btn btn-primary" href="<?= h(url('', ['p' => 'customer_new'])) ?>">+ 新規顧客</a>
    </div>

    <form method="get" action="index.php" class="filter-form">
        <input type="hidden" name="p" value="customers">
        <input type="search" name="q" value="<?= h($keyword) ?>" placeholder="顧客名・電話番号で検索" class="filter-form__input">
        <div class="filter-tabs">
            <a class="filter-tab <?= $categoryFilter === '' ? 'is-active' : '' ?>"
               href="<?= h(url('', ['p' => 'customers', 'q' => $keyword])) ?>">すべて</a>
            <?php foreach (CATEGORY_LABELS as $key => $label): ?>
                <a class="filter-tab <?= $categoryFilter === $key ? 'is-active' : '' ?>"
                   href="<?= h(url('', ['p' => 'customers', 'cat' => $key, 'q' => $keyword])) ?>"><?= h($label) ?></a>
            <?php endforeach; ?>
        </div>
    </form>

    <?php if (empty($entries)): ?>
        <p class="muted">該当する顧客がいません。</p>
    <?php else: ?>
        <ul class="customer-list">
            <?php foreach ($entries as $e):
                $c = $e['customer']; $p = $e['prediction']; ?>
                <li class="customer-card <?= h(urgency_class($e['days_until'])) ?>">
                    <a class="customer-card__link" href="<?= h(url('', ['p' => 'customer_detail', 'id' => $c['id']])) ?>">
                        <div class="customer-card__head">
                            <span class="badge <?= h(category_badge_class($c['category'])) ?>"><?= h(category_label($c['category'])) ?></span>
                            <span class="customer-card__name"><?= h($c['name']) ?></span>
                        </div>
                        <div class="customer-card__body">
                            <?php if ($p['next_date']): ?>
                                <div class="customer-card__date">
                                    <span class="label">次回予測:</span>
                                    <strong><?= h(format_date($p['next_date'])) ?></strong>
                                    <span class="days"><?= h(days_until_label($e['days_until'])) ?></span>
                                </div>
                            <?php else: ?>
                                <div class="customer-card__date muted">予測には2件以上の購入履歴が必要です</div>
                            <?php endif; ?>
                            <div class="customer-card__meta">
                                最終購入: <?= h(format_date($p['last_purchase'])) ?>
                                / 購入回数: <?= h((string)$p['purchase_count']) ?>
                                / 確信度: <?= h(confidence_label($p['confidence'])) ?>
                            </div>
                        </div>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/_footer.php'; ?>
