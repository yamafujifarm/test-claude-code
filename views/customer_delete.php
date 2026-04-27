<?php
/** @var PDO $pdo */

if (!is_post()) {
    redirect('customers');
}

$id = (int)post('id', 0);
if ($id > 0) {
    $stmt = $pdo->prepare('DELETE FROM customers WHERE id = :id');
    $stmt->execute([':id' => $id]);
}

redirect('customers');
