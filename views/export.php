<?php
/** @var PDO $pdo */

$type = (string)($_GET['type'] ?? '');

if ($type === 'purchases') {
    export_purchases_csv($pdo);
    return;
}
if ($type === 'predictions') {
    export_predictions_csv($pdo);
    return;
}

$pageTitle = 'データ書き出し';
require __DIR__ . '/_header.php';
?>

<section class="page-section">
    <h2 class="section-title">データ書き出し (CSV)</h2>
    <p class="muted">ダウンロードしたファイルは AI に分析させたり、Excel で確認したりできます。</p>

    <div class="export-list">
        <a class="export-card" href="<?= h(url('', ['p' => 'export', 'type' => 'purchases'])) ?>">
            <div class="export-card__title">購入履歴 CSV (生データ)</div>
            <div class="export-card__desc">
                顧客ごとの購入日時・数量を全件出力します。<br>
                列: 購入ID / 顧客ID / 顧客名 / カテゴリー / 購入日時 / 数量(kg) / メモ
            </div>
        </a>

        <a class="export-card" href="<?= h(url('', ['p' => 'export', 'type' => 'predictions'])) ?>">
            <div class="export-card__title">予測サマリー CSV (AI 分析用)</div>
            <div class="export-card__desc">
                顧客ごとの平均購入間隔・次回予測日・確信度などを出力します。<br>
                AI に「次に注文がきそうな顧客は？」と尋ねる際に貼り付けてください。
            </div>
        </a>
    </div>
</section>

<?php require __DIR__ . '/_footer.php'; ?>
