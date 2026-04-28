<?php
/**
 * API: テスト通知を送信（全購読端末あて）。
 *
 * @var PDO $pdo
 */

header('Content-Type: application/json; charset=utf-8');

if (!is_post()) {
    http_response_code(405);
    echo json_encode(['error' => 'method not allowed']);
    return;
}

if (!defined('VAPID_PUBLIC_KEY') || VAPID_PUBLIC_KEY === '') {
    http_response_code(500);
    echo json_encode(['error' => 'VAPID 鍵が未設定です。']);
    return;
}

$subs = $pdo->query('SELECT id, endpoint, p256dh, auth FROM push_subscriptions')->fetchAll();
if (empty($subs)) {
    echo json_encode(['ok' => true, 'sent' => 0, 'message' => '購読中の端末がありません。']);
    return;
}

$payload = json_encode([
    'title' => '🌾 やまふじ農園（テスト）',
    'body'  => 'プッシュ通知のテストです。届いていれば成功です。',
    'url'   => './index.php?p=settings',
    'tag'   => 'test-push',
], JSON_UNESCAPED_UNICODE);

$push = new WebPush(VAPID_SUBJECT, VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY);
$deleteStmt = $pdo->prepare('DELETE FROM push_subscriptions WHERE id = :id');

$sent = 0; $failed = 0; $cleaned = 0; $errors = [];
foreach ($subs as $s) {
    try {
        $res = $push->send([
            'endpoint' => $s['endpoint'],
            'p256dh'   => $s['p256dh'],
            'auth'     => $s['auth'],
        ], (string)$payload);
        if ($res['status'] >= 200 && $res['status'] < 300) {
            $sent++;
        } elseif ($res['status'] === 404 || $res['status'] === 410) {
            $deleteStmt->execute([':id' => $s['id']]);
            $cleaned++;
        } else {
            $failed++;
            $errors[] = 'status ' . $res['status'];
        }
    } catch (Throwable $e) {
        $failed++;
        $errors[] = $e->getMessage();
    }
}

echo json_encode([
    'ok' => $sent > 0,
    'sent' => $sent,
    'failed' => $failed,
    'cleaned' => $cleaned,
    'errors' => array_slice($errors, 0, 5),
], JSON_UNESCAPED_UNICODE);
