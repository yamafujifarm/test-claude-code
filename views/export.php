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

$pageTitle = 'データ管理';
require __DIR__ . '/_header.php';
?>

<section class="page-section">
    <h2 class="section-title">データ管理</h2>
    <p class="muted">CSV の取り込み（既存履歴のインポート）と書き出し（バックアップ・AI 分析用）ができます。</p>

    <h3 class="section-subtitle">取り込み</h3>
    <div class="export-list">
        <a class="export-card export-card--accent" href="<?= h(url('', ['p' => 'import'])) ?>">
            <div class="export-card__title">CSV から購入履歴を取り込む</div>
            <div class="export-card__desc">
                Excel 等で管理している過去の購入履歴を取り込みます。<br>
                顧客名・カテゴリー・購入日時・数量(kg)・メモ の 5 列。
            </div>
        </a>
    </div>

    <h3 class="section-subtitle">書き出し</h3>
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
