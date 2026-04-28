<?php
/**
 * 担当者マスタ管理画面。
 *
 * @var PDO $pdo
 */

$errors = [];
$msg    = (string)($_GET['msg'] ?? '');

if (is_post()) {
    $action = (string)post('action', '');
    if ($action === 'create') {
        $name = trim((string)post('name', ''));
        if ($name === '') {
            $errors[] = '名前を入力してください。';
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO staff (name) VALUES (:name)');
                $stmt->execute([':name' => $name]);
                redirect('staff', ['msg' => 'created']);
            } catch (PDOException $e) {
                $errors[] = '同じ名前の担当者が既に存在します。';
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)post('id', 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM staff WHERE id = :id');
            $stmt->execute([':id' => $id]);
            redirect('staff', ['msg' => 'deleted']);
        }
    }
}

$stmt = $pdo->query(
    'SELECT s.*,
            (SELECT COUNT(*) FROM purchases p WHERE p.staff_id = s.id) AS purchase_count,
            (SELECT COUNT(*) FROM push_subscriptions ps WHERE ps.staff_id = s.id) AS subscription_count
     FROM staff s
     ORDER BY s.name ASC'
);
$staff = $stmt->fetchAll();

$pageTitle = '担当者';
require __DIR__ . '/_header.php';
?>

<section class="page-section">
    <h2 class="section-title">担当者マスタ</h2>
    <p class="muted">注文を記録する人を登録します。プッシュ通知の宛先と「誰が記録したか」の追跡に使います。</p>

    <?php if ($msg === 'created'): ?>
        <div class="alert alert-success">担当者を追加しました。</div>
    <?php elseif ($msg === 'deleted'): ?>
        <div class="alert alert-success">担当者を削除しました。</div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= h(url('', ['p' => 'staff'])) ?>" class="form">
        <input type="hidden" name="action" value="create">
        <div class="form-row form-row--inline">
            <input type="text" name="name" placeholder="新しい担当者の名前" class="form-input" required>
            <button type="submit" class="btn btn-primary">追加</button>
        </div>
    </form>

    <?php if (empty($staff)): ?>
        <p class="muted">まだ担当者が登録されていません。最初の担当者を追加してください。</p>
    <?php else: ?>
        <ul class="staff-list">
            <?php foreach ($staff as $s): ?>
                <li class="staff-item">
                    <div class="staff-item__name"><?= h($s['name']) ?></div>
                    <div class="staff-item__meta">
                        記録: <?= (int)$s['purchase_count'] ?>件 / 通知端末: <?= (int)$s['subscription_count'] ?>台
                    </div>
                    <form method="post" action="<?= h(url('', ['p' => 'staff'])) ?>" class="staff-item__delete"
                          onsubmit="return confirm('「<?= h($s['name']) ?>」を削除しますか？\n（過去の記録の担当者欄は空白になります）');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= h((string)$s['id']) ?>">
                        <button type="submit" class="btn-icon" aria-label="削除">×</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <div class="page-actions" style="margin-top: 16px;">
        <a class="btn btn-secondary btn-block" href="<?= h(url('', ['p' => 'settings'])) ?>">この端末の通知設定へ</a>
    </div>
</section>

<?php require __DIR__ . '/_footer.php'; ?>
