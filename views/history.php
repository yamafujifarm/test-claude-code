<?php
/**
 * 注文履歴ページ（統計付き）
 *
 *  - 合計の精米キロ数
 *  - 当月の精米キロ数
 *  - カテゴリー別の精米キロ数
 *  - 月別の精米キロ数（直近24ヶ月）
 *  - 全注文履歴（フィルタ付き）
 *
 * @var PDO $pdo
 */

$ymFilter  = trim((string)($_GET['ym']  ?? ''));   // 例: 2026-04
$catFilter = trim((string)($_GET['cat'] ?? ''));   // business / regular / retail

// ---- 集計 ----------
$totalKg = (float)$pdo->query('SELECT COALESCE(SUM(quantity_kg), 0) FROM purchases')->fetchColumn();

$thisMonth = date('Y-m');
$thisMonthStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(quantity_kg), 0) FROM purchases
     WHERE DATE_FORMAT(purchased_at, '%Y-%m') = :ym"
);
$thisMonthStmt->execute([':ym' => $thisMonth]);
$thisMonthKg = (float)$thisMonthStmt->fetchColumn();

// カテゴリー別
$byCategory = ['business' => 0.0, 'regular' => 0.0, 'retail' => 0.0];
$catStmt = $pdo->query(
    'SELECT c.category, COALESCE(SUM(p.quantity_kg), 0) AS kg
     FROM purchases p
     INNER JOIN customers c ON c.id = p.customer_id
     GROUP BY c.category'
);
foreach ($catStmt as $r) {
    $byCategory[$r['category']] = (float)$r['kg'];
}

// 月別（直近24ヶ月）
$monthlyStmt = $pdo->query(
    "SELECT DATE_FORMAT(purchased_at, '%Y-%m') AS ym,
            COALESCE(SUM(quantity_kg), 0) AS kg,
            COUNT(*) AS cnt
     FROM purchases
     GROUP BY ym
     ORDER BY ym DESC
     LIMIT 24"
);
$monthly = $monthlyStmt->fetchAll();

// ---- 履歴一覧（フィルタ適用） ----------
$where  = [];
$params = [];
if ($ymFilter !== '' && preg_match('/^\d{4}-\d{2}$/', $ymFilter)) {
    $where[] = "DATE_FORMAT(p.purchased_at, '%Y-%m') = :ym";
    $params[':ym'] = $ymFilter;
}
if ($catFilter !== '' && isset(CATEGORY_LABELS[$catFilter])) {
    $where[] = 'c.category = :cat';
    $params[':cat'] = $catFilter;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$listSql = "SELECT p.id, p.purchased_at, p.quantity_kg, p.note,
                   c.id AS customer_id, c.name AS customer_name, c.category
            FROM purchases p
            INNER JOIN customers c ON c.id = p.customer_id
            $whereSql
            ORDER BY p.purchased_at DESC
            LIMIT 300";
$listStmt = $pdo->prepare($listSql);
$listStmt->execute($params);
$history = $listStmt->fetchAll();

$filteredKg = array_sum(array_map(fn($h) => (float)$h['quantity_kg'], $history));

$pageTitle = '注文履歴';
require __DIR__ . '/_header.php';
?>

<section class="page-section">
    <h2 class="section-title">合計</h2>
    <div class="summary-grid summary-grid--2">
        <div class="summary-card">
            <div class="summary-card__num"><?= h(number_format($totalKg, 1)) ?><span class="unit"> kg</span></div>
            <div class="summary-card__label">累計の精米数量</div>
        </div>
        <div class="summary-card">
            <div class="summary-card__num"><?= h(number_format($thisMonthKg, 1)) ?><span class="unit"> kg</span></div>
            <div class="summary-card__label">今月（<?= h($thisMonth) ?>）</div>
        </div>
    </div>
</section>

<section class="page-section">
    <h2 class="section-title">カテゴリー別</h2>
    <div class="category-stat-grid">
        <?php foreach (CATEGORY_LABELS as $key => $label):
            $kg = $byCategory[$key] ?? 0.0;
            $pct = $totalKg > 0 ? round($kg / $totalKg * 100) : 0;
        ?>
            <div class="category-stat">
                <div class="category-stat__head">
                    <span class="badge <?= h(category_badge_class($key)) ?>"><?= h($label) ?></span>
                    <span class="category-stat__pct"><?= $pct ?>%</span>
                </div>
                <div class="category-stat__num"><?= h(number_format($kg, 1)) ?><span class="unit"> kg</span></div>
                <div class="category-stat__bar">
                    <div class="category-stat__bar-fill" style="width: <?= $pct ?>%; background: <?php
                        echo $key === 'business' ? 'var(--color-business)' :
                            ($key === 'regular' ? 'var(--color-regular)' : 'var(--color-retail)');
                    ?>;"></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="page-section">
    <h2 class="section-title">月別の精米数量（直近24ヶ月）</h2>
    <?php if (empty($monthly)): ?>
        <p class="muted">データがありません。</p>
    <?php else: ?>
        <?php
        $maxKg = max(array_map(fn($m) => (float)$m['kg'], $monthly));
        ?>
        <div class="month-list">
            <?php foreach ($monthly as $m):
                $pct = $maxKg > 0 ? (float)$m['kg'] / $maxKg * 100 : 0;
                $isCurrent = $m['ym'] === $thisMonth;
            ?>
                <a class="month-row <?= $isCurrent ? 'is-current' : '' ?>"
                   href="<?= h(url('', ['p' => 'history', 'ym' => $m['ym']])) ?>">
                    <div class="month-row__label">
                        <?= h($m['ym']) ?>
                        <?php if ($isCurrent): ?><span class="badge badge-new">今月</span><?php endif; ?>
                    </div>
                    <div class="month-row__bar">
                        <div class="month-row__bar-fill" style="width: <?= $pct ?>%;"></div>
                    </div>
                    <div class="month-row__num"><?= h(number_format((float)$m['kg'], 1)) ?> kg</div>
                    <div class="month-row__cnt"><?= (int)$m['cnt'] ?>件</div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="page-section">
    <h2 class="section-title">注文履歴</h2>

    <form method="get" action="index.php" class="filter-form">
        <input type="hidden" name="p" value="history">
        <div class="filter-form__row">
            <select name="ym" class="form-input" onchange="this.form.submit()">
                <option value="">すべての月</option>
                <?php foreach ($monthly as $m): ?>
                    <option value="<?= h($m['ym']) ?>" <?= $ymFilter === $m['ym'] ? 'selected' : '' ?>>
                        <?= h($m['ym']) ?>（<?= h(number_format((float)$m['kg'], 1)) ?> kg）
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-tabs">
            <a class="filter-tab <?= $catFilter === '' ? 'is-active' : '' ?>"
               href="<?= h(url('', ['p' => 'history', 'ym' => $ymFilter])) ?>">すべて</a>
            <?php foreach (CATEGORY_LABELS as $key => $label): ?>
                <a class="filter-tab <?= $catFilter === $key ? 'is-active' : '' ?>"
                   href="<?= h(url('', ['p' => 'history', 'ym' => $ymFilter, 'cat' => $key])) ?>"><?= h($label) ?></a>
            <?php endforeach; ?>
        </div>
    </form>

    <?php if ($ymFilter !== '' || $catFilter !== ''): ?>
        <div class="filter-summary">
            該当 <strong><?= count($history) ?></strong> 件 / 合計 <strong><?= h(number_format($filteredKg, 1)) ?> kg</strong>
            <a class="filter-summary__clear" href="<?= h(url('', ['p' => 'history'])) ?>">フィルタ解除</a>
        </div>
    <?php endif; ?>

    <?php if (empty($history)): ?>
        <p class="muted">該当する注文履歴がありません。</p>
    <?php else: ?>
        <?php if (count($history) >= 300): ?>
            <p class="muted">最新 300 件を表示しています。古いデータは月フィルタで絞り込んでください。</p>
        <?php endif; ?>

        <ul class="history-global-list">
            <?php
            $prevDate = null;
            foreach ($history as $row):
                $d = format_date($row['purchased_at']);
                $showDate = $d !== $prevDate;
                $prevDate = $d;
            ?>
                <?php if ($showDate): ?>
                    <li class="history-global-list__date-sep"><?= h($d) ?></li>
                <?php endif; ?>
                <li class="history-global-item">
                    <a href="<?= h(url('', ['p' => 'customer_detail', 'id' => $row['customer_id']])) ?>"
                       class="history-global-item__link">
                        <div class="history-global-item__main">
                            <span class="badge <?= h(category_badge_class($row['category'])) ?>"><?= h(category_label($row['category'])) ?></span>
                            <span class="history-global-item__name"><?= h($row['customer_name']) ?></span>
                        </div>
                        <div class="history-global-item__side">
                            <span class="history-global-item__qty"><?= h(format_kg((float)$row['quantity_kg'])) ?></span>
                            <span class="history-global-item__time"><?= h(date('H:i', strtotime($row['purchased_at']))) ?></span>
                        </div>
                        <?php if (!empty($row['note'])): ?>
                            <div class="history-global-item__note"><?= nl2br(h($row['note'])) ?></div>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/_footer.php'; ?>
