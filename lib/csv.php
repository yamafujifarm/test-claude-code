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
 * 顧客名一覧 CSV
 *
 * インポート前の表記合わせ用に、現在登録されている顧客の名前と関連情報を出力する。
 * Excel で開いて、取り込み予定の CSV と並べて見比べる用途を想定。
 */
function export_customers_csv(PDO $pdo): void
{
    $sql = 'SELECT
                c.id,
                c.name,
                c.category,
                c.phone,
                s.name AS staff_name,
                COALESCE(stats.purchase_count, 0) AS purchase_count,
                stats.last_purchased_at,
                COALESCE(stats.total_kg, 0) AS total_kg,
                c.note
            FROM customers c
            LEFT JOIN staff s ON s.id = c.primary_staff_id
            LEFT JOIN (
                SELECT customer_id,
                       COUNT(*) AS purchase_count,
                       MAX(purchased_at) AS last_purchased_at,
                       SUM(quantity_kg) AS total_kg
                FROM purchases
                GROUP BY customer_id
            ) stats ON stats.customer_id = c.id
            ORDER BY c.name ASC';
    $stmt = $pdo->query($sql);

    $header = [
        '顧客ID', '顧客名', 'カテゴリー', '主担当者',
        '電話番号', '購入回数', '最終購入日', '累計(kg)', 'メモ',
    ];

    $rows = (function () use ($stmt) {
        while ($row = $stmt->fetch()) {
            yield [
                $row['id'],
                $row['name'],
                category_label($row['category']),
                $row['staff_name'] ?? '',
                $row['phone'] ?? '',
                (int)$row['purchase_count'],
                $row['last_purchased_at'] ? format_date($row['last_purchased_at']) : '',
                (float)$row['total_kg'],
                $row['note'] ?? '',
            ];
        }
    })();

    output_csv('customers_' . date('Ymd_His') . '.csv', $header, $rows);
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
        '購入日時', '数量(kg)', '玄米本数', 'メモ',
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
                round(genmai_count((float)$row['quantity_kg']), 2),
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

/* ==================================================================
 * CSV インポート
 * ================================================================== */

const CATEGORY_JP_TO_KEY = [
    '業務用' => 'business',
    '常連'   => 'regular',
    '自由米' => 'retail',
];

const IMPORT_HEADER = ['顧客名', 'カテゴリー', '購入日時', '数量(kg)', 'メモ'];

/**
 * アップロードされた CSV を解析してバリデート済みの行配列を返す。
 *
 * 戻り値:
 *  [
 *    'rows'   => [ ['line'=>N, 'name'=>..., 'category'=>'business'|..., 'purchased_at'=>'Y-m-d H:i:s', 'quantity_kg'=>float, 'note'=>string], ...],
 *    'errors' => [ ['line'=>N, 'message'=>'...', 'raw'=>[...]], ...],
 *  ]
 */
function parse_import_csv(string $filepath): array
{
    $rows   = [];
    $errors = [];

    $content = file_get_contents($filepath);
    if ($content === false) {
        return [
            'rows'   => [],
            'errors' => [['line' => 0, 'message' => 'ファイルを読み込めませんでした。', 'raw' => []]],
        ];
    }

    // 文字コード検出して UTF-8 へ変換（Excel 由来の Shift_JIS にも対応）
    $encoding = mb_detect_encoding($content, ['UTF-8', 'SJIS-win', 'SJIS', 'EUC-JP', 'CP932'], true);
    if ($encoding && strtoupper($encoding) !== 'UTF-8') {
        $content = mb_convert_encoding($content, 'UTF-8', $encoding);
    }
    // BOM 除去
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
        $content = substr($content, 3);
    }
    // 改行コード正規化
    $content = str_replace(["\r\n", "\r"], "\n", $content);

    $tmp = fopen('php://temp', 'r+');
    fwrite($tmp, $content);
    rewind($tmp);

    $lineNo        = 0;
    $headerSkipped = false;
    while (($cells = fgetcsv($tmp)) !== false) {
        $lineNo++;

        // 完全に空の行はスキップ
        $nonEmpty = array_filter($cells, fn($c) => trim((string)$c) !== '');
        if (count($nonEmpty) === 0) {
            continue;
        }

        // 1 行目はヘッダーとみなしてスキップ（顧客名 / カテゴリー... の判定）
        if (!$headerSkipped) {
            $headerSkipped = true;
            $first = trim((string)($cells[0] ?? ''));
            if ($first === '顧客名' || $first === 'name') {
                continue;
            }
            // ヘッダーが無い CSV だった場合に備えてフォールスルー（このまま処理する）
        }

        $name       = trim((string)($cells[0] ?? ''));
        $categoryJp = trim((string)($cells[1] ?? ''));
        $dateStr    = trim((string)($cells[2] ?? ''));
        $qtyStr     = trim((string)($cells[3] ?? ''));
        $note       = trim((string)($cells[4] ?? ''));

        $rowErrors = [];
        if ($name === '') {
            $rowErrors[] = '顧客名が空です';
        }
        if (!isset(CATEGORY_JP_TO_KEY[$categoryJp])) {
            $rowErrors[] = 'カテゴリーが不正です（業務用 / 常連 / 自由米 のいずれか）: ' . $categoryJp;
        }
        $ts = $dateStr !== '' ? strtotime($dateStr) : false;
        if ($ts === false) {
            $rowErrors[] = '購入日時が不正です: ' . $dateStr;
        }
        if ($qtyStr === '' || !is_numeric($qtyStr) || (float)$qtyStr <= 0) {
            $rowErrors[] = '数量(kg) が不正です: ' . $qtyStr;
        }

        if (!empty($rowErrors)) {
            $errors[] = [
                'line'    => $lineNo,
                'message' => implode(' / ', $rowErrors),
                'raw'     => $cells,
            ];
            continue;
        }

        $rows[] = [
            'line'         => $lineNo,
            'name'         => $name,
            'category'     => CATEGORY_JP_TO_KEY[$categoryJp],
            'category_jp'  => $categoryJp,
            'purchased_at' => date('Y-m-d H:i:s', $ts),
            'quantity_kg'  => (float)$qtyStr,
            'note'         => $note,
        ];
    }
    fclose($tmp);

    return ['rows' => $rows, 'errors' => $errors];
}

/**
 * 解析済みの行を DB に取り込む。
 * 重複（同一顧客・同一購入日時・同一数量）はスキップ。
 * 同名顧客が無ければ自動で新規作成する。
 */
function execute_import(PDO $pdo, array $rows): array
{
    $stats = [
        'purchases_added'    => 0,
        'customers_created'  => 0,
        'duplicates_skipped' => 0,
    ];

    // 既存の顧客名 → ID マップ
    $byName = [];
    foreach ($pdo->query('SELECT id, name FROM customers') as $c) {
        $byName[$c['name']] = (int)$c['id'];
    }

    $insertCustomer = $pdo->prepare(
        'INSERT INTO customers (name, category) VALUES (:name, :category)'
    );
    $checkDupe = $pdo->prepare(
        'SELECT COUNT(*) FROM purchases
         WHERE customer_id = :cid AND purchased_at = :pa AND quantity_kg = :qty'
    );
    $insertPurchase = $pdo->prepare(
        'INSERT INTO purchases (customer_id, purchased_at, quantity_kg, note)
         VALUES (:cid, :pa, :qty, :note)'
    );

    $pdo->beginTransaction();
    try {
        foreach ($rows as $row) {
            $cid = $byName[$row['name']] ?? null;
            if ($cid === null) {
                $insertCustomer->execute([
                    ':name'     => $row['name'],
                    ':category' => $row['category'],
                ]);
                $cid = (int)$pdo->lastInsertId();
                $byName[$row['name']] = $cid;
                $stats['customers_created']++;
            }

            $checkDupe->execute([
                ':cid' => $cid,
                ':pa'  => $row['purchased_at'],
                ':qty' => $row['quantity_kg'],
            ]);
            if ((int)$checkDupe->fetchColumn() > 0) {
                $stats['duplicates_skipped']++;
                continue;
            }

            $insertPurchase->execute([
                ':cid'  => $cid,
                ':pa'   => $row['purchased_at'],
                ':qty'  => $row['quantity_kg'],
                ':note' => $row['note'] !== '' ? $row['note'] : null,
            ]);
            $stats['purchases_added']++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return $stats;
}
