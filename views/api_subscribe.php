<?php
/**
 * API: プッシュ通知の購読を保存。
 *
 * @var PDO $pdo
 */

header('Content-Type: application/json; charset=utf-8');

if (!is_post()) {
    http_response_code(405);
    echo json_encode(['error' => 'method not allowed']);
    return;
}

$rawBody = file_get_contents('php://input');
$data = json_decode((string)$rawBody, true);
$sub  = $data['subscription'] ?? null;
$staffId = isset($data['staff_id']) ? (int)$data['staff_id'] : null;
$renew   = !empty($data['renew']);

if (!is_array($sub)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid payload']);
    return;
}

$endpoint = (string)($sub['endpoint'] ?? '');
$keys     = $sub['keys'] ?? [];
$p256dh   = (string)($keys['p256dh'] ?? '');
$auth     = (string)($keys['auth'] ?? '');

if ($endpoint === '' || $p256dh === '' || $auth === '') {
    http_response_code(400);
    echo json_encode(['error' => 'invalid subscription']);
    return;
}

$endpointHash = hash('sha256', $endpoint);
$ua = mb_strimwidth((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

if ($renew) {
    // pushsubscriptionchange からの更新
    $stmt = $pdo->prepare(
        'UPDATE push_subscriptions
         SET endpoint = :ep, endpoint_hash = :h, p256dh = :pk, auth = :a, last_used_at = NOW()
         WHERE endpoint_hash = :h'
    );
    $stmt->execute([':ep' => $endpoint, ':h' => $endpointHash, ':pk' => $p256dh, ':a' => $auth]);
    if ($stmt->rowCount() === 0) {
        // 新規としてフォールスルー
        $stmt = $pdo->prepare(
            'INSERT INTO push_subscriptions (staff_id, endpoint, endpoint_hash, p256dh, auth, user_agent)
             VALUES (:sid, :ep, :h, :pk, :a, :ua)'
        );
        $stmt->execute([
            ':sid' => $staffId, ':ep' => $endpoint, ':h' => $endpointHash,
            ':pk' => $p256dh, ':a' => $auth, ':ua' => $ua
        ]);
    }
} else {
    $stmt = $pdo->prepare(
        'INSERT INTO push_subscriptions (staff_id, endpoint, endpoint_hash, p256dh, auth, user_agent)
         VALUES (:sid, :ep, :h, :pk, :a, :ua)
         ON DUPLICATE KEY UPDATE
            staff_id = VALUES(staff_id),
            endpoint = VALUES(endpoint),
            p256dh   = VALUES(p256dh),
            auth     = VALUES(auth),
            user_agent = VALUES(user_agent),
            last_used_at = NOW()'
    );
    $stmt->execute([
        ':sid' => $staffId, ':ep' => $endpoint, ':h' => $endpointHash,
        ':pk' => $p256dh, ':a' => $auth, ':ua' => $ua
    ]);
}

echo json_encode(['ok' => true]);
