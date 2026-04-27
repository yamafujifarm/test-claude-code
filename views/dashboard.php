<?php
/** @var PDO $pdo */
$entries = fetch_customers_with_prediction($pdo);

// 予測日でソート
usort($entries, function ($a, $b) {
    $da = $a['prediction']['next_date'] ?? '9999-12-31';
    $db = $b['prediction']['next_date'] ?? '9999-12-31';
    return strcmp($da, $db);
});

$soon       = array_filter($entries, fn($e) => $e['days_until'] !== null && $e['days_until'] <= 7);
$thisMonth  = array_filter($entries, fn($e) => $e['days_until'] !== null && $e['days_until'] > 7 && $e['days_until'] <= 30);
$noData     = array_filter($entries, fn($e) => $e['prediction']['confidence'] === 'none');

$totalCustomers = count($entries);
$predictable    = $totalCustomers - count($noData);

$pageTitle = 'ダッシュボード';
require __DIR__ . '/_header.php';
?>

<section class="page-section">
    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-card__num"><?= count($soon) ?></div>
            <div class="summary-card__label">1週間以内に注文予測</div>
        </div>
        <div class="summary-card">
            <div class="summary-card__num"><?= count($thisMonth) ?></div>
            <div class="summary-card__label">1ヶ月以内に予測</div>
        </div>
        <div class="summary-card">
            <div class="summary-card__num"><?= $totalCustomers ?></div>
            <div class="summary-card__label">登録顧客</div>
        </div>
        <div class="summary-card">
            <div class="summary-card__num"><?= $predictable ?></div>
            <div class="summary-card__label">予測可能件数</div>
        </div>
    </div>
</section>

<section class="page-section">
    <h2 class="section-title">そろそろ注文が来そうな顧客</h2>
    <?php if (empty($soon)): ?>
        <p class="muted">1週間以内に予測される注文はありません。</p>
    <?php else: ?>
        <ul class="customer-list">
            <?php foreach ($soon as $e):
                $c = $e['customer']; $p = $e['prediction']; ?>
                <li class="customer-card <?= h(urgency_class($e['days_until'])) ?>">
                    <a class="customer-card__link" href="<?= h(url('', ['p' => 'customer_detail', 'id' => $c['id']])) ?>">
                        <div class="customer-card__head">
                            <span class="badge <?= h(category_badge_class($c['category'])) ?>"><?= h(category_label($c['category'])) ?></span>
                            <span class="customer-card__name"><?= h($c['name']) ?></span>
                        </div>
                        <div class="customer-card__body">
                            <div class="customer-card__date">
                                <span class="label">次回予測:</span>
                                <strong><?= h(format_date($p['next_date'])) ?></strong>
                                <span class="days"><?= h(days_until_label($e['days_until'])) ?></span>
                            </div>
                            <div class="customer-card__meta">
                                最終購入: <?= h(format_date($p['last_purchase'])) ?>
                                / 平均間隔: <?= $p['avg_interval'] !== null ? h((string)$p['avg_interval']) . '日' : '-' ?>
                                / 確信度: <?= h(confidence_label($p['confidence'])) ?>
                            </div>
                        </div>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="page-section">
    <h2 class="section-title">今月中に予測される顧客</h2>
    <?php if (empty($thisMonth)): ?>
        <p class="muted">該当なし。</p>
    <?php else: ?>
        <ul class="customer-list">
            <?php foreach ($thisMonth as $e):
                $c = $e['customer']; $p = $e['prediction']; ?>
                <li class="customer-card <?= h(urgency_class($e['days_until'])) ?>">
                    <a class="customer-card__link" href="<?= h(url('', ['p' => 'customer_detail', 'id' => $c['id']])) ?>">
                        <div class="customer-card__head">
                            <span class="badge <?= h(category_badge_class($c['category'])) ?>"><?= h(category_label($c['category'])) ?></span>
                            <span class="customer-card__name"><?= h($c['name']) ?></span>
                        </div>
                        <div class="customer-card__body">
                            <div class="customer-card__date">
                                <span class="label">次回予測:</span>
                                <strong><?= h(format_date($p['next_date'])) ?></strong>
                                <span class="days"><?= h(days_until_label($e['days_until'])) ?></span>
                            </div>
                        </div>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/_footer.php'; ?>
