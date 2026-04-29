<?php
/** @var PDO $pdo */

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$msg = (string)($_GET['msg'] ?? '');

$stmt = $pdo->prepare('SELECT * FROM customers WHERE id = :id');
$stmt->execute([':id' => $id]);
$customer = $stmt->fetch();
if (!$customer) {
    http_response_code(404);
    echo '顧客が見つかりません。';
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM purchases WHERE customer_id = :id ORDER BY purchased_at ASC');
$stmt->execute([':id' => $id]);
$purchases = $stmt->fetchAll();

$prediction = predict_next_order($customer['category'], $purchases);
$daysUntil  = days_until($prediction['next_date']);

$historyDesc = array_reverse($purchases);
$totalKg = array_sum(array_map(fn($p) => (float)$p['quantity_kg'], $purchases));

$pageTitle = $customer['name'];
require __DIR__ . '/_header.php';
?>

<section class="page-section">
    <?php if ($msg === 'created'): ?>
        <div class="alert alert-success">顧客を登録しました。</div>
    <?php elseif ($msg === 'updated'): ?>
        <div class="alert alert-success">顧客情報を更新しました。</div>
    <?php elseif ($msg === 'purchase_added'): ?>
        <div class="alert alert-success">購入履歴を追加しました。</div>
    <?php elseif ($msg === 'purchase_deleted'): ?>
        <div class="alert alert-success">購入履歴を削除しました。</div>
    <?php endif; ?>

    <div class="customer-header">
        <span class="badge <?= h(category_badge_class($customer['category'])) ?>"><?= h(category_label($customer['category'])) ?></span>
        <h2 class="customer-header__name"><?= h($customer['name']) ?></h2>
        <?php if (!empty($customer['phone'])): ?>
            <div class="customer-header__phone">
                <a href="tel:<?= h($customer['phone']) ?>"><?= h($customer['phone']) ?></a>
            </div>
        <?php endif; ?>
    </div>

    <div class="prediction-box <?= h(urgency_class($daysUntil)) ?>">
        <div class="prediction-box__label">次回注文予測</div>
        <?php if ($prediction['next_date']): ?>
            <div class="prediction-box__date"><?= h(format_date($prediction['next_date'])) ?></div>
            <div class="prediction-box__days"><?= h(days_until_label($daysUntil)) ?></div>
            <div class="prediction-box__meta">
                確信度: <strong><?= h(confidence_label($prediction['confidence'])) ?></strong>
                / 平均間隔: <strong><?= h((string)$prediction['avg_interval']) ?>日</strong>
                / 購入回数: <strong><?= h((string)$prediction['purchase_count']) ?>回</strong>
            </div>
            <?php if (!empty($prediction['recent_intervals'])): ?>
                <div class="prediction-box__meta">
                    直近の購入間隔: <?= h(implode(' / ', array_map(fn($d) => $d . '日', $prediction['recent_intervals']))) ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="prediction-box__date">予測不可</div>
            <div class="prediction-box__meta">
                購入履歴が <?= h((string)$prediction['purchase_count']) ?> 件のため予測できません。<br>
                2件以上記録すると予測できるようになります。
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($customer['note'])): ?>
        <div class="note-box">
            <div class="note-box__label">メモ</div>
            <div class="note-box__text"><?= nl2br(h($customer['note'])) ?></div>
        </div>
    <?php endif; ?>

    <div class="page-actions">
        <a class="btn btn-primary" href="<?= h(url('', ['p' => 'purchase_new', 'customer_id' => $id])) ?>">+ 購入を記録</a>
        <a class="btn btn-secondary" href="<?= h(url('', ['p' => 'customer_edit', 'id' => $id])) ?>">顧客情報を編集</a>
    </div>
</section>

<section class="page-section">
    <h3 class="section-title">購入履歴 (<?= count($purchases) ?>件)</h3>

    <?php if (!empty($purchases)): ?>
        <div class="customer-total">
            <span class="customer-total__label">累計</span>
            <span class="customer-total__kg"><?= h(number_format($totalKg, 1)) ?> kg</span>
            <span class="customer-total__genmai">玄米 <?= h(number_format(genmai_count($totalKg), 1)) ?> 本</span>
        </div>
    <?php endif; ?>

    <?php if (empty($historyDesc)): ?>
        <p class="muted">購入履歴がありません。「+ 購入を記録」ボタンから追加してください。</p>
    <?php else: ?>
        <ul class="history-list">
            <?php foreach ($historyDesc as $row): ?>
                <li class="history-item">
                    <div class="history-item__main">
                        <div class="history-item__date"><?= h(format_datetime($row['purchased_at'])) ?></div>
                        <div class="history-item__qty">
                            <?= h(format_kg((float)$row['quantity_kg'])) ?>
                            <span class="history-item__genmai">玄米 <?= h(number_format(genmai_count((float)$row['quantity_kg']), 1)) ?> 本</span>
                        </div>
                    </div>
                    <?php if (!empty($row['note'])): ?>
                        <div class="history-item__note"><?= nl2br(h($row['note'])) ?></div>
                    <?php endif; ?>
                    <form method="post" action="<?= h(url('', ['p' => 'purchase_delete'])) ?>" class="history-item__delete"
                          onsubmit="return confirm('この購入履歴を削除しますか？');">
                        <input type="hidden" name="id" value="<?= h((string)$row['id']) ?>">
                        <input type="hidden" name="customer_id" value="<?= h((string)$id) ?>">
                        <button type="submit" class="btn-icon" aria-label="削除">×</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="page-section danger-zone">
    <form method="post" action="<?= h(url('', ['p' => 'customer_delete'])) ?>"
          onsubmit="return confirm('本当にこの顧客を削除しますか？\n購入履歴もすべて削除されます。');">
        <input type="hidden" name="id" value="<?= h((string)$id) ?>">
        <button type="submit" class="btn btn-danger">この顧客を削除</button>
    </form>
</section>

<?php require __DIR__ . '/_footer.php'; ?>
