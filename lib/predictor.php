<?php
declare(strict_types=1);

/**
 * 予測ロジック
 *
 * 購入履歴の配列（purchased_at 昇順）からカテゴリーごとに次回購入日を予測する。
 *
 * 戻り値:
 *  [
 *    'next_date'        => 'Y-m-d' | null,
 *    'confidence'       => 'high'|'medium'|'low'|'none',
 *    'avg_interval'     => float|null,   // 採用した予測間隔（日）
 *    'recent_intervals' => float[],      // 直近3回の購入間隔（日）
 *    'purchase_count'   => int,
 *    'last_purchase'    => 'Y-m-d H:i:s' | null,
 *  ]
 */
function predict_next_order(string $category, array $purchases): array
{
    // マイナス（在庫からの引き当てなど）は「注文」ではないので予測の対象から除外
    $positive = array_values(array_filter(
        $purchases,
        fn($p) => (float)($p['quantity_kg'] ?? 0) > 0
    ));

    $count = count($positive);
    $last  = $count > 0 ? $positive[$count - 1]['purchased_at'] : null;

    if ($count < 2) {
        return [
            'next_date'        => null,
            'confidence'       => 'none',
            'avg_interval'     => null,
            'recent_intervals' => [],
            'purchase_count'   => $count,
            'last_purchase'    => $last,
        ];
    }

    $intervals = calculate_intervals($positive);
    $intervalDays = pick_interval($category, $intervals);

    $lastTs   = strtotime($last);
    $nextTs   = $lastTs + (int)round($intervalDays * 86400);
    $nextDate = date('Y-m-d', $nextTs);

    $confidence = decide_confidence($category, $count);

    $recent = array_slice($intervals, -3);
    $recent = array_map(fn($d) => round($d, 1), $recent);

    return [
        'next_date'        => $nextDate,
        'confidence'       => $confidence,
        'avg_interval'     => round($intervalDays, 1),
        'recent_intervals' => $recent,
        'purchase_count'   => $count,
        'last_purchase'    => $last,
    ];
}

/**
 * 隣接する購入日の間隔（日数）を返す。
 */
function calculate_intervals(array $purchases): array
{
    $intervals = [];
    $n = count($purchases);
    for ($i = 1; $i < $n; $i++) {
        $prev = strtotime($purchases[$i - 1]['purchased_at']);
        $curr = strtotime($purchases[$i]['purchased_at']);
        $days = ($curr - $prev) / 86400;
        if ($days > 0) {
            $intervals[] = $days;
        }
    }
    return $intervals;
}

/**
 * カテゴリーに応じて予測間隔（日）を決定する。
 *  - 業務用: 中央値と直近3回の平均をブレンド
 *  - 常連 / 自由米: 全期間の平均
 */
function pick_interval(string $category, array $intervals): float
{
    if (empty($intervals)) return 0.0;

    if ($category === 'business') {
        $median = median_value($intervals);
        $recent = array_slice($intervals, -3);
        $recentAvg = array_sum($recent) / count($recent);
        return ($median + $recentAvg) / 2;
    }
    return array_sum($intervals) / count($intervals);
}

function median_value(array $values): float
{
    $copy = $values;
    sort($copy);
    $n = count($copy);
    if ($n === 0) return 0.0;
    $mid = intdiv($n, 2);
    if ($n % 2 === 1) {
        return (float)$copy[$mid];
    }
    return ($copy[$mid - 1] + $copy[$mid]) / 2;
}

/**
 * 確信度をデータ件数とカテゴリーから決定する。
 *  - 業務用: 5件以上=高 / 3件以上=中 / それ未満=低
 *  - 常連:   5件以上=高 / 3件以上=中 / それ未満=低
 *  - 自由米: 5件以上=中 / それ未満=低（個別予測の精度が出にくいため）
 */
function decide_confidence(string $category, int $count): string
{
    if ($count < 2) return 'none';

    if ($category === 'business' || $category === 'regular') {
        if ($count >= 5) return 'high';
        if ($count >= 3) return 'medium';
        return 'low';
    }
    // retail
    if ($count >= 5) return 'medium';
    return 'low';
}

/**
 * 顧客とその予測情報をまとめて取得する。
 *
 * @return array  [
 *   ['customer' => [...], 'prediction' => [...], 'days_until' => int|null], ...
 * ]
 */
function fetch_customers_with_prediction(PDO $pdo, ?string $categoryFilter = null): array
{
    $sql = 'SELECT * FROM customers';
    $params = [];
    if ($categoryFilter !== null && $categoryFilter !== '') {
        $sql .= ' WHERE category = :category';
        $params[':category'] = $categoryFilter;
    }
    $sql .= ' ORDER BY name ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $customers = $stmt->fetchAll();

    $purchaseStmt = $pdo->prepare(
        'SELECT * FROM purchases WHERE customer_id = :id ORDER BY purchased_at ASC'
    );

    $result = [];
    foreach ($customers as $c) {
        $purchaseStmt->execute([':id' => $c['id']]);
        $purchases = $purchaseStmt->fetchAll();
        $prediction = predict_next_order($c['category'], $purchases);
        $result[] = [
            'customer'   => $c,
            'prediction' => $prediction,
            'days_until' => days_until($prediction['next_date']),
        ];
    }
    return $result;
}
