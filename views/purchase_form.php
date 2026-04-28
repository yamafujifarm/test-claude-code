<?php
/** @var PDO $pdo */

$customerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$errors = [];

$customers = $pdo->query('SELECT id, name, category FROM customers ORDER BY name ASC')->fetchAll();
$staff     = $pdo->query('SELECT id, name FROM staff ORDER BY name ASC')->fetchAll();

$form = [
    'customer_id'    => $customerId,
    'staff_id'       => 0,
    'purchased_date' => date('Y-m-d'),
    'purchased_time' => date('H:i'),
    'quantity_kg'    => '',
    'note'           => '',
];

if (is_post()) {
    $form['customer_id']    = (int)post('customer_id', 0);
    $form['staff_id']       = (int)post('staff_id', 0);
    $form['purchased_date'] = (string)post('purchased_date', '');
    $form['purchased_time'] = (string)post('purchased_time', '00:00');
    $form['quantity_kg']    = (string)post('quantity_kg', '');
    $form['note']           = (string)post('note', '');

    if ($form['customer_id'] <= 0) {
        $errors[] = '顧客を選択してください。';
    }
    if (!empty($staff) && $form['staff_id'] <= 0) {
        $errors[] = '担当者を選択してください。';
    }
    if ($form['purchased_date'] === '' || !strtotime($form['purchased_date'])) {
        $errors[] = '購入日を入力してください。';
    }
    if ($form['quantity_kg'] === '' || !is_numeric($form['quantity_kg']) || (float)$form['quantity_kg'] <= 0) {
        $errors[] = '数量(kg)は0より大きい数値を入力してください。';
    }

    if (empty($errors)) {
        $datetime = $form['purchased_date'] . ' ' . ($form['purchased_time'] ?: '00:00') . ':00';
        $stmt = $pdo->prepare(
            'INSERT INTO purchases (customer_id, staff_id, purchased_at, quantity_kg, note)
             VALUES (:cid, :sid, :pa, :qty, :note)'
        );
        $stmt->execute([
            ':cid'  => $form['customer_id'],
            ':sid'  => $form['staff_id'] > 0 ? $form['staff_id'] : null,
            ':pa'   => $datetime,
            ':qty'  => (float)$form['quantity_kg'],
            ':note' => $form['note'] !== '' ? $form['note'] : null,
        ]);
        $newId = (int)$pdo->lastInsertId();

        // プッシュ通知を発火（自分以外の端末へ）。失敗は記録の成功を妨げない。
        try {
            notify_purchase_recorded($pdo, $newId, $form['staff_id'] > 0 ? $form['staff_id'] : null);
        } catch (Throwable $e) {
            // ignore
        }

        redirect('customer_detail', ['id' => $form['customer_id'], 'msg' => 'purchase_added']);
    }
}

$pageTitle = '購入を記録';
require __DIR__ . '/_header.php';
?>

<section class="page-section">
    <h2 class="section-title">購入を記録</h2>

    <?php if (empty($customers)): ?>
        <div class="alert alert-info">
            まず顧客を登録してください。
            <a href="<?= h(url('', ['p' => 'customer_new'])) ?>">新規顧客登録へ</a>
        </div>
    <?php else: ?>
        <?php if ($errors): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $msg): ?>
                    <div><?= h($msg) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= h(url('', ['p' => 'purchase_new'])) ?>" class="form" id="purchase-form">
            <div class="form-row">
                <label class="form-label" for="customer_id">顧客 <span class="required">*</span></label>
                <select id="customer_id" name="customer_id" required class="form-input">
                    <option value="">-- 選択してください --</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= h((string)$c['id']) ?>"
                            <?= (int)$form['customer_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                            [<?= h(category_label($c['category'])) ?>] <?= h($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if (!empty($staff)): ?>
                <div class="form-row">
                    <label class="form-label" for="staff_id">担当者 <span class="required">*</span></label>
                    <select id="staff_id" name="staff_id" required class="form-input">
                        <option value="">-- 選択してください --</option>
                        <?php foreach ($staff as $s): ?>
                            <option value="<?= h((string)$s['id']) ?>"
                                <?= (int)$form['staff_id'] === (int)$s['id'] ? 'selected' : '' ?>>
                                <?= h($s['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    担当者が登録されていないため、担当者欄は省略されます。
                    通知機能を使う場合は <a href="<?= h(url('', ['p' => 'staff'])) ?>">担当者管理</a> から登録してください。
                </div>
            <?php endif; ?>

            <div class="form-row form-row--split">
                <div class="form-row__col">
                    <label class="form-label" for="purchased_date">購入日 <span class="required">*</span></label>
                    <input type="date" id="purchased_date" name="purchased_date"
                           value="<?= h($form['purchased_date']) ?>" required class="form-input">
                </div>
                <div class="form-row__col">
                    <label class="form-label" for="purchased_time">時刻</label>
                    <input type="time" id="purchased_time" name="purchased_time"
                           value="<?= h($form['purchased_time']) ?>" class="form-input">
                </div>
            </div>

            <div class="form-row">
                <label class="form-label" for="quantity_kg">数量 (kg) <span class="required">*</span></label>
                <input type="number" id="quantity_kg" name="quantity_kg"
                       value="<?= h((string)$form['quantity_kg']) ?>"
                       step="0.1" min="0.1" inputmode="decimal" required class="form-input">
            </div>

            <div class="form-row">
                <label class="form-label" for="note">メモ</label>
                <textarea id="note" name="note" rows="3" class="form-input"><?= h($form['note']) ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-block">記録する</button>
                <a class="btn btn-link"
                   href="<?= h($form['customer_id'] > 0 ? url('', ['p' => 'customer_detail', 'id' => $form['customer_id']]) : url('', ['p' => 'dashboard'])) ?>">キャンセル</a>
            </div>
        </form>
    <?php endif; ?>
</section>

<script>
// 担当者選択を localStorage に保存し、次回デフォルトに反映
(function() {
    const sel = document.getElementById('staff_id');
    if (!sel) return;
    const stored = localStorage.getItem('rice-app-last-staff-id');
    if (stored && !sel.value) {
        const opt = sel.querySelector('option[value="' + stored + '"]');
        if (opt) sel.value = stored;
    }
    sel.addEventListener('change', () => {
        if (sel.value) localStorage.setItem('rice-app-last-staff-id', sel.value);
    });
})();
</script>

<?php require __DIR__ . '/_footer.php'; ?>
