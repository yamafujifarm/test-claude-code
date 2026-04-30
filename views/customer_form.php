<?php
/** @var PDO $pdo */
/** @var string $page */

$isEdit = ($page === 'customer_edit');
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];

$customer = [
    'id'               => 0,
    'name'             => '',
    'category'         => 'business',
    'primary_staff_id' => null,
    'phone'            => '',
    'note'             => '',
];

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        http_response_code(404);
        echo '顧客が見つかりません。';
        exit;
    }
    $customer = $row;
}

$staffList = $pdo->query('SELECT id, name FROM staff ORDER BY name ASC')->fetchAll();

if (is_post()) {
    $customer['name']             = trim((string)post('name', ''));
    $customer['category']         = (string)post('category', 'business');
    $primaryStaffRaw              = post('primary_staff_id', '');
    $customer['primary_staff_id'] = ($primaryStaffRaw !== '' ? (int)$primaryStaffRaw : null);
    $customer['phone']            = trim((string)post('phone', ''));
    $customer['note']             = (string)post('note', '');

    if ($customer['name'] === '') {
        $errors[] = '顧客名を入力してください。';
    }
    if (!isset(CATEGORY_LABELS[$customer['category']])) {
        $errors[] = 'カテゴリーが不正です。';
    }

    if (empty($errors)) {
        if ($isEdit) {
            $stmt = $pdo->prepare(
                'UPDATE customers SET name = :name, category = :category,
                    primary_staff_id = :psid, phone = :phone, note = :note
                 WHERE id = :id'
            );
            $stmt->execute([
                ':name'     => $customer['name'],
                ':category' => $customer['category'],
                ':psid'     => $customer['primary_staff_id'],
                ':phone'    => $customer['phone'] ?: null,
                ':note'     => $customer['note'] ?: null,
                ':id'       => $id,
            ]);
            redirect('customer_detail', ['id' => $id, 'msg' => 'updated']);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO customers (name, category, primary_staff_id, phone, note)
                 VALUES (:name, :category, :psid, :phone, :note)'
            );
            $stmt->execute([
                ':name'     => $customer['name'],
                ':category' => $customer['category'],
                ':psid'     => $customer['primary_staff_id'],
                ':phone'    => $customer['phone'] ?: null,
                ':note'     => $customer['note'] ?: null,
            ]);
            $newId = (int)$pdo->lastInsertId();
            redirect('customer_detail', ['id' => $newId, 'msg' => 'created']);
        }
    }
}

$pageTitle = $isEdit ? '顧客編集' : '新規顧客登録';
require __DIR__ . '/_header.php';
?>

<section class="page-section">
    <h2 class="section-title"><?= h($pageTitle) ?></h2>

    <?php if ($errors): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $msg): ?>
                <div><?= h($msg) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= h(url('', ['p' => $page] + ($isEdit ? ['id' => $id] : []))) ?>" class="form">
        <div class="form-row">
            <label class="form-label" for="name">顧客名 <span class="required">*</span></label>
            <input type="text" id="name" name="name" value="<?= h($customer['name']) ?>" required class="form-input" autocomplete="off">
        </div>

        <div class="form-row">
            <label class="form-label">カテゴリー <span class="required">*</span></label>
            <div class="radio-group">
                <?php foreach (CATEGORY_LABELS as $key => $label): ?>
                    <label class="radio-item">
                        <input type="radio" name="category" value="<?= h($key) ?>"
                            <?= $customer['category'] === $key ? 'checked' : '' ?>>
                        <span><?= h($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-row">
            <label class="form-label" for="primary_staff_id">主担当者</label>
            <select id="primary_staff_id" name="primary_staff_id" class="form-input">
                <option value="">未設定（誰でも対応）</option>
                <?php foreach ($staffList as $s): ?>
                    <option value="<?= h((string)$s['id']) ?>"
                        <?= (int)($customer['primary_staff_id'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>>
                        <?= h($s['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="muted" style="font-size:12px;">記録画面でこの顧客を上位に表示する人を選びます。</p>
        </div>

        <div class="form-row">
            <label class="form-label" for="phone">電話番号</label>
            <input type="tel" id="phone" name="phone" value="<?= h($customer['phone'] ?? '') ?>" class="form-input" autocomplete="off">
        </div>

        <div class="form-row">
            <label class="form-label" for="note">メモ</label>
            <textarea id="note" name="note" rows="4" class="form-input"><?= h($customer['note'] ?? '') ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-block">保存する</button>
            <a class="btn btn-link" href="<?= h(url('', ['p' => $isEdit ? 'customer_detail' : 'customers'] + ($isEdit ? ['id' => $id] : []))) ?>">キャンセル</a>
        </div>
    </form>
</section>

<?php require __DIR__ . '/_footer.php'; ?>
