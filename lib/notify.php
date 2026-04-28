<?php
declare(strict_types=1);

/**
 * 購入記録時のプッシュ通知配信ヘルパ。
 *
 * 失敗してもアプリ全体を止めないよう、例外を握りつぶしてログ的に保持する。
 */

/**
 * 指定の購入レコードに紐づく通知を、購読中の全端末（記録者本人を除く）へ送る。
 *
 * @return array{sent:int, skipped:int, failed:int}
 */
function notify_purchase_recorded(PDO $pdo, int $purchaseId, ?int $excludeStaffId = null): array
{
    if (!defined('VAPID_PUBLIC_KEY') || VAPID_PUBLIC_KEY === '') {
        return ['sent' => 0, 'skipped' => 0, 'failed' => 0];
    }

    $stmt = $pdo->prepare(
        'SELECT p.id, p.purchased_at, p.quantity_kg, p.note,
                c.name AS customer_name, c.category,
                s.name AS staff_name
         FROM purchases p
         INNER JOIN customers c ON c.id = p.customer_id
         LEFT JOIN staff s ON s.id = p.staff_id
         WHERE p.id = :id'
    );
    $stmt->execute([':id' => $purchaseId]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['sent' => 0, 'skipped' => 0, 'failed' => 0];
    }

    $title = '🌾 注文記録: ' . $row['customer_name'];
    $bodyParts = [];
    if (!empty($row['staff_name'])) {
        $bodyParts[] = $row['staff_name'] . 'さんが記録';
    }
    $bodyParts[] = category_label($row['category']);
    $bodyParts[] = format_kg((float)$row['quantity_kg']);
    $bodyParts[] = format_datetime($row['purchased_at']);
    $body = implode(' / ', $bodyParts);

    $payload = json_encode([
        'title' => $title,
        'body'  => $body,
        'url'   => './index.php?p=customer_detail&id=' . (int)$row['id'], // dummy — overridden below
        'tag'   => 'purchase-' . $purchaseId,
    ], JSON_UNESCAPED_UNICODE);

    // 顧客詳細へ飛ばすので URL を作り直す
    $custStmt = $pdo->prepare('SELECT customer_id FROM purchases WHERE id = :id');
    $custStmt->execute([':id' => $purchaseId]);
    $cid = (int)$custStmt->fetchColumn();
    $payload = json_encode([
        'title' => $title,
        'body'  => $body,
        'url'   => './index.php?p=customer_detail&id=' . $cid,
        'tag'   => 'purchase-' . $purchaseId,
    ], JSON_UNESCAPED_UNICODE);

    // 配信対象の購読を取得（自分の端末は除外）
    $sql = 'SELECT id, staff_id, endpoint, p256dh, auth FROM push_subscriptions';
    $params = [];
    if ($excludeStaffId !== null) {
        $sql .= ' WHERE (staff_id IS NULL OR staff_id <> :sid)';
        $params[':sid'] = $excludeStaffId;
    }
    $subStmt = $pdo->prepare($sql);
    $subStmt->execute($params);
    $subs = $subStmt->fetchAll();

    if (empty($subs)) {
        return ['sent' => 0, 'skipped' => 0, 'failed' => 0];
    }

    $push = new WebPush(VAPID_SUBJECT, VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY);
    $deleteStmt = $pdo->prepare('DELETE FROM push_subscriptions WHERE id = :id');
    $touchStmt  = $pdo->prepare('UPDATE push_subscriptions SET last_used_at = NOW() WHERE id = :id');

    $sent = 0; $failed = 0; $skipped = 0;
    foreach ($subs as $s) {
        try {
            $res = $push->send([
                'endpoint' => $s['endpoint'],
                'p256dh'   => $s['p256dh'],
                'auth'     => $s['auth'],
            ], (string)$payload);
            $status = $res['status'];
            if ($status >= 200 && $status < 300) {
                $sent++;
                $touchStmt->execute([':id' => $s['id']]);
            } elseif ($status === 404 || $status === 410) {
                // 期限切れ／無効化された購読は削除
                $deleteStmt->execute([':id' => $s['id']]);
                $skipped++;
            } else {
                $failed++;
            }
        } catch (Throwable $e) {
            $failed++;
        }
    }

    return ['sent' => $sent, 'skipped' => $skipped, 'failed' => $failed];
}
