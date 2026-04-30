<?php
/** @var PDO $pdo */

$customerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$errors = [];

$customers = $pdo->query(
    'SELECT id, name, category, primary_staff_id
     FROM customers ORDER BY name ASC'
)->fetchAll();
$staff = $pdo->query('SELECT id, name FROM staff ORDER BY id ASC')->fetchAll();

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
    if ($form['quantity_kg'] === '' || !is_numeric($form['quantity_kg']) || (float)$form['quantity_kg'] == 0.0) {
        $errors[] = '数量(kg) は 0 以外の数値を入力してください（在庫から差し引く場合はマイナス）。';
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

// ---- 顧客を担当者 × カテゴリーでグルーピング ----------
$categoryOrder = ['business', 'regular', 'retail'];
$staffNames    = [0 => '担当未設定'];
foreach ($staff as $s) {
    $staffNames[(int)$s['id']] = $s['name'];
}

$grouped = []; // [staffId][category] => [customer, ...]
foreach ($customers as $c) {
    $sid = (int)($c['primary_staff_id'] ?? 0);
    $cat = $c['category'];
    $grouped[$sid][$cat][] = $c;
}
// 担当者順: 登録順（id 昇順）+ 末尾に未設定
$staffIdsInOrder = array_map(fn($s) => (int)$s['id'], $staff);
$staffIdsInOrder[] = 0;

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
                    <p class="muted" style="font-size:12px;">あなたの担当の顧客が顧客リストの上位に並びます。</p>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    担当者が登録されていないため、担当者欄は省略されます。
                    通知機能を使う場合は <a href="<?= h(url('', ['p' => 'staff'])) ?>">担当者管理</a> から登録してください。
                </div>
            <?php endif; ?>

            <div class="form-row">
                <label class="form-label" for="customer_id">顧客 <span class="required">*</span></label>
                <select id="customer_id" name="customer_id" required class="form-input">
                    <option value="">-- 選択してください --</option>
                    <?php foreach ($staffIdsInOrder as $sid):
                        $catGroups = $grouped[$sid] ?? [];
                        if (empty($catGroups)) continue;
                        foreach ($categoryOrder as $catIdx => $cat):
                            if (empty($catGroups[$cat])) continue;
                            $sname = $staffNames[$sid] ?? '担当未設定';
                            $catLabel = category_label($cat);
                    ?>
                        <optgroup label="<?= h($sname . ' / ' . $catLabel) ?>"
                                  data-staff="<?= h((string)$sid) ?>"
                                  data-cat-order="<?= $catIdx + 1 ?>">
                            <?php foreach ($catGroups[$cat] as $c): ?>
                                <option value="<?= h((string)$c['id']) ?>"
                                    <?= (int)$form['customer_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                                    <?= h($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php
                        endforeach;
                    endforeach; ?>
                </select>
            </div>

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
                       step="0.1" inputmode="decimal" required class="form-input">
                <p class="muted" style="font-size:12px;">マイナスの値も入力可（例: 自由米の在庫を業務用に回したときは <code>-30</code>）。</p>
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
(function() {
    const customerSel = document.getElementById('customer_id');
    const staffSel    = document.getElementById('staff_id');
    if (!customerSel) return;

    function reorderGroups(staffId) {
        const groups = Array.from(customerSel.querySelectorAll('optgroup'));
        groups.sort((a, b) => {
            const aSid = parseInt(a.dataset.staff || '0', 10);
            const bSid = parseInt(b.dataset.staff || '0', 10);
            // 自分の担当 (0) → 他の担当 (1) → 担当未設定 (2)
            const rank = (sid) => sid === staffId ? 0 : (sid === 0 ? 2 : 1);
            const r = rank(aSid) - rank(bSid);
            if (r !== 0) return r;
            const aOrd = parseInt(a.dataset.catOrder || '99', 10);
            const bOrd = parseInt(b.dataset.catOrder || '99', 10);
            return aOrd - bOrd;
        });
        groups.forEach(g => customerSel.appendChild(g));
    }

    if (staffSel) {
        // localStorage から前回の担当を復元
        const stored = localStorage.getItem('rice-app-last-staff-id');
        if (stored && !staffSel.value) {
            const opt = staffSel.querySelector('option[value="' + stored + '"]');
            if (opt) staffSel.value = stored;
        }
        // 初期並び替え
        reorderGroups(parseInt(staffSel.value || '0', 10));
        // 担当者を変えるたびに並び替え
        staffSel.addEventListener('change', () => {
            reorderGroups(parseInt(staffSel.value || '0', 10));
            if (staffSel.value) localStorage.setItem('rice-app-last-staff-id', staffSel.value);
        });
    }
})();
</script>

<?php require __DIR__ . '/_footer.php'; ?>
