<?php
/**
 * CSV インポート画面
 *
 * 3 ステップ:
 *   1. upload   ファイル選択
 *   2. preview  パース結果のプレビュー（隠しフィールドに行データを保持）
 *   3. execute  確定して DB へ書き込み
 *
 * @var PDO $pdo
 */

$step    = 'upload';
$rows    = [];
$errors  = [];
$stats   = null;
$genericError = '';

if (is_post()) {
    $action = (string)post('action', '');

    if ($action === 'parse' && !empty($_FILES['csv']['tmp_name']) && is_uploaded_file($_FILES['csv']['tmp_name'])) {
        if (!empty($_FILES['csv']['error'])) {
            $genericError = 'ファイルのアップロードに失敗しました（コード: ' . (int)$_FILES['csv']['error'] . '）';
        } else {
            $result = parse_import_csv($_FILES['csv']['tmp_name']);
            $rows   = $result['rows'];
            $errors = $result['errors'];
            $step   = 'preview';
        }
    } elseif ($action === 'execute') {
        $rowsJson = (string)post('rows_json', '[]');
        $decoded  = json_decode($rowsJson, true);
        if (!is_array($decoded) || empty($decoded)) {
            $genericError = '取り込み対象の行がありません。';
        } else {
            try {
                $stats = execute_import($pdo, $decoded);
                $step  = 'result';
            } catch (Throwable $e) {
                $genericError = '取り込み処理でエラーが発生しました: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'CSV 取り込み';
require __DIR__ . '/_header.php';
?>

<section class="page-section">
    <h2 class="section-title">CSV 取り込み</h2>

    <?php if ($genericError !== ''): ?>
        <div class="alert alert-error"><?= h($genericError) ?></div>
    <?php endif; ?>

    <?php if ($step === 'upload'): ?>
        <p class="muted">
            Excel などで管理している購入履歴を CSV にして取り込みます。<br>
            列の順番は <strong>顧客名 / カテゴリー / 購入日時 / 数量(kg) / メモ</strong> でお願いします。
        </p>

        <div class="format-guide">
            <div class="format-guide__title">CSV のサンプル</div>
            <pre class="format-guide__pre">顧客名,カテゴリー,購入日時,数量(kg),メモ
山田商店,業務用,2026/01/15 10:00,30,
田中太郎,常連,2026/02/03,5,おまけサービス
佐藤花子,自由米,2026/03/10,10,</pre>
            <ul class="format-guide__list">
                <li>カテゴリーは <code>業務用</code> / <code>常連</code> / <code>自由米</code> のいずれか</li>
                <li>購入日時は <code>2026/01/15</code> や <code>2026/01/15 10:00</code> など</li>
                <li>同名の顧客が無い場合は自動で新規作成します</li>
                <li>同じ顧客・同じ日時・同じ数量の行は二重登録を避けてスキップします</li>
                <li>文字コードは UTF-8 / Shift_JIS どちらでも OK</li>
            </ul>
        </div>

        <form method="post" action="<?= h(url('', ['p' => 'import'])) ?>" enctype="multipart/form-data" class="form">
            <input type="hidden" name="action" value="parse">
            <div class="form-row">
                <label class="form-label" for="csv">CSV ファイル <span class="required">*</span></label>
                <input type="file" id="csv" name="csv" accept=".csv,text/csv" required class="form-input">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-block">アップロードして内容を確認</button>
                <a class="btn btn-link" href="<?= h(url('', ['p' => 'export'])) ?>">キャンセル</a>
            </div>
        </form>

    <?php elseif ($step === 'preview'): ?>
        <?php
            $existingNames = [];
            foreach ($pdo->query('SELECT name FROM customers') as $c) {
                $existingNames[$c['name']] = true;
            }
            $newCustomerNames = [];
            foreach ($rows as $r) {
                if (!isset($existingNames[$r['name']]) && !isset($newCustomerNames[$r['name']])) {
                    $newCustomerNames[$r['name']] = true;
                }
            }
            $previewRows = array_slice($rows, 0, 50);
            $hasMore     = count($rows) > 50;
        ?>

        <div class="summary-grid summary-grid--3">
            <div class="summary-card">
                <div class="summary-card__num"><?= count($rows) ?></div>
                <div class="summary-card__label">取り込む行</div>
            </div>
            <div class="summary-card">
                <div class="summary-card__num"><?= count($newCustomerNames) ?></div>
                <div class="summary-card__label">新規作成される顧客</div>
            </div>
            <div class="summary-card">
                <div class="summary-card__num"><?= count($errors) ?></div>
                <div class="summary-card__label">エラー行（除外）</div>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <strong>以下の行はエラーのため取り込み対象から除外されます:</strong>
                <ul class="error-list">
                    <?php foreach (array_slice($errors, 0, 20) as $e): ?>
                        <li><?= h((string)$e['line']) ?>行目: <?= h($e['message']) ?></li>
                    <?php endforeach; ?>
                    <?php if (count($errors) > 20): ?>
                        <li>他 <?= count($errors) - 20 ?> 件…</li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (empty($rows)): ?>
            <div class="alert alert-info">取り込み可能な行がありませんでした。</div>
            <a class="btn btn-secondary" href="<?= h(url('', ['p' => 'import'])) ?>">最初からやり直す</a>
        <?php else: ?>
            <h3 class="section-title">プレビュー（先頭 <?= count($previewRows) ?> 件<?= $hasMore ? ' / 全 ' . count($rows) . ' 件' : '' ?>）</h3>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>顧客名</th>
                            <th>カテゴリー</th>
                            <th>購入日時</th>
                            <th>数量</th>
                            <th>メモ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($previewRows as $r): ?>
                            <tr>
                                <td>
                                    <?= h($r['name']) ?>
                                    <?php if (!isset($existingNames[$r['name']])): ?>
                                        <span class="badge badge-new">新規</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= h($r['category_jp']) ?></td>
                                <td><?= h(format_datetime($r['purchased_at'])) ?></td>
                                <td><?= h(format_kg((float)$r['quantity_kg'])) ?></td>
                                <td><?= h($r['note']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <form method="post" action="<?= h(url('', ['p' => 'import'])) ?>" class="form"
                  onsubmit="return confirm('<?= count($rows) ?>件の購入履歴を取り込みます。よろしいですか？');">
                <input type="hidden" name="action" value="execute">
                <input type="hidden" name="rows_json" value="<?= h(json_encode(array_values($rows), JSON_UNESCAPED_UNICODE)) ?>">
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-block">この内容で取り込む</button>
                    <a class="btn btn-link" href="<?= h(url('', ['p' => 'import'])) ?>">やり直す</a>
                </div>
            </form>
        <?php endif; ?>

    <?php elseif ($step === 'result' && $stats !== null): ?>
        <div class="alert alert-success">
            <strong>取り込みが完了しました。</strong>
        </div>
        <div class="summary-grid summary-grid--3">
            <div class="summary-card">
                <div class="summary-card__num"><?= (int)$stats['purchases_added'] ?></div>
                <div class="summary-card__label">追加された購入履歴</div>
            </div>
            <div class="summary-card">
                <div class="summary-card__num"><?= (int)$stats['customers_created'] ?></div>
                <div class="summary-card__label">新規作成された顧客</div>
            </div>
            <div class="summary-card">
                <div class="summary-card__num"><?= (int)$stats['duplicates_skipped'] ?></div>
                <div class="summary-card__label">重複でスキップ</div>
            </div>
        </div>
        <div class="form-actions">
            <a class="btn btn-primary" href="<?= h(url('', ['p' => 'dashboard'])) ?>">ダッシュボードへ</a>
            <a class="btn btn-secondary" href="<?= h(url('', ['p' => 'customers'])) ?>">顧客一覧へ</a>
            <a class="btn btn-link" href="<?= h(url('', ['p' => 'import'])) ?>">続けて別の CSV を取り込む</a>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/_footer.php'; ?>
