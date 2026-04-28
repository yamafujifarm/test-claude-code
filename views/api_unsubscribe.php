<?php
/**
 * API: プッシュ通知の購読解除。
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
$endpoint = (string)($data['endpoint'] ?? '');

if ($endpoint === '') {
    http_response_code(400);
    echo json_encode(['error' => 'endpoint required']);
    return;
}

$stmt = $pdo->prepare('DELETE FROM push_subscriptions WHERE endpoint_hash = :h');
$stmt->execute([':h' => hash('sha256', $endpoint)]);

echo json_encode(['ok' => true, 'deleted' => $stmt->rowCount()]);
