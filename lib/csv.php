<?php
declare(strict_types=1);

/**
 * CSV エクスポート (UTF-8 BOM 付き)
 *
 * Excel で文字化けせずに開けるよう BOM を出力する。
 */
function output_csv(string $filename, array $header, iterable $rows): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    echo "\xEF\xBB\xBF"; // UTF-8 BOM

    $fp = fopen('php://output', 'w');
    fputcsv($fp, $header);
    foreach ($rows as $row) {
        fputcsv($fp, $row);
    }
    fclose($fp);
}

/**
 * 購入履歴 CSV（生データ）
 */
function export_purchases_csv(PDO $pdo): void
{
    $sql = 'SELECT p.id, c.id AS customer_id, c.name, c.category, p.purchased_at, p.quantity_kg, p.note
            FROM purchases p
            INNER JOIN customers c ON c.id = p.customer_id
            ORDER BY p.purchased_at ASC';
    $stmt = $pdo->query($sql);

    $header = [
        '購入ID', '顧客ID', '顧客名', 'カテゴリー',
        '購入日時', '数量(kg)', 'メモ',
    ];

    $rows = (function () use ($stmt) {
        while ($row = $stmt->fetch()) {
            yield [
                $row['id'],
                $row['customer_id'],
                $row['name'],
                category_label($row['category']),
                $row['purchased_at'],
                $row['quantity_kg'],
                $row['note'] ?? '',
            ];
        }
    })();

    output_csv('purchases_' . date('Ymd_His') . '.csv', $header, $rows);
}

/**
 * 予測サマリー CSV（AI 分析用）
 */
function export_predictions_csv(PDO $pdo): void
{
    $entries = fetch_customers_with_prediction($pdo);

    $header = [
        '顧客ID', '顧客名', 'カテゴリー', '購入回数',
        '最終購入日', '平均購入間隔(日)', '次回予測日',
        '次回予測まで(日)', '確信度',
        '直近間隔1', '直近間隔2', '直近間隔3',
        '電話番号', 'メモ',
    ];

    $rows = (function () use ($entries) {
        foreach ($entries as $e) {
            $c  = $e['customer'];
            $p  = $e['prediction'];
            $r  = $p['recent_intervals'];
            yield [
                $c['id'],
                $c['name'],
                category_label($c['category']),
                $p['purchase_count'],
                $p['last_purchase'] ? format_date($p['last_purchase']) : '',
                $p['avg_interval']  ?? '',
                $p['next_date']     ?? '',
                $e['days_until']    ?? '',
                confidence_label($p['confidence']),
                $r[0] ?? '',
                $r[1] ?? '',
                $r[2] ?? '',
                $c['phone'] ?? '',
                $c['note'] ?? '',
            ];
        }
    })();

    output_csv('predictions_' . date('Ymd_His') . '.csv', $header, $rows);
}
