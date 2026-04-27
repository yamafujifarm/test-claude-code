<?php
/** @var PDO $pdo */

if (!is_post()) {
    redirect('dashboard');
}

$id         = (int)post('id', 0);
$customerId = (int)post('customer_id', 0);

if ($id > 0) {
    $stmt = $pdo->prepare('DELETE FROM purchases WHERE id = :id');
    $stmt->execute([':id' => $id]);
}

if ($customerId > 0) {
    redirect('customer_detail', ['id' => $customerId, 'msg' => 'purchase_deleted']);
}
redirect('dashboard');
