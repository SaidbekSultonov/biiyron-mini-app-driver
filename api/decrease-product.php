<?php

date_default_timezone_set('Asia/Tashkent');
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); die(json_encode(['error' => 'POST only']));
}

$driver = requireDriver($conn);
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$item_id = (int) ($body['item_id'] ?? 0);

if (!$item_id) {
    http_response_code(400); die(json_encode(['error' => 'item_id required']));
}

$stmt = $conn->prepare(
    "SELECT oi.id, oi.quantity, oi.product_type, o.id AS order_id, o.is_rejected
     FROM order_items oi
     INNER JOIN orders o ON o.id = oi.order_id
     WHERE oi.id = ? AND o.employee_id = ? AND o.status = 3 AND oi.deleted_at IS NULL LIMIT 1"
);
$stmt->execute([$item_id, $driver['employees_id']]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    http_response_code(404); die(json_encode(['error' => 'Item not found']));
}
if ((int) $item['is_rejected']) {
    http_response_code(400); die(json_encode(['error' => 'Order is rejected']));
}
if ((float) $item['quantity'] <= 0) {
    http_response_code(400); die(json_encode(['error' => 'Already 0']));
}

$qty_before   = (float) $item['quantity'];
$delta        = 1;
$product_type = (int) $item['product_type'];
$new_qty      = $qty_before - $delta;

$conn->beginTransaction();
try {
    $conn->prepare("UPDATE order_items SET quantity = ?, updated_at = NOW() WHERE id = ?")
         ->execute([$new_qty, $item_id]);

    // Log yozish: qty_before saqlanadi — revert delta emas, aynan shu qiymatga qaytaradi
    $conn->prepare(
        "INSERT INTO driver_item_logs
         (order_id, order_item_id, change_type, product_type, delta, qty_before,
          employees_id, created_at, updated_at)
         VALUES (?, ?, 2, ?, ?, ?, ?, NOW(), NOW())"
    )->execute([
        $item['order_id'],
        $item_id,
        $product_type,
        $delta,
        $qty_before,
        $driver['employees_id'],
    ]);

    // Buyurtma total_amount ni qayta hisoblash
    $conn->prepare(
        "UPDATE orders
         SET total_amount = (
             SELECT SUM(oi2.quantity * oi2.price)
             FROM order_items oi2
             WHERE oi2.order_id = ? AND oi2.deleted_at IS NULL
         ), updated_at = NOW()
         WHERE id = ?"
    )->execute([$item['order_id'], $item['order_id']]);

    $conn->commit();
} catch (Exception $e) {
    $conn->rollBack();
    http_response_code(500);
    die(json_encode(['error' => 'Transaction failed']));
}

echo json_encode([
    'success'   => true,
    'new_qty'   => $new_qty,
    'need_comment' => $new_qty <= 0,
]);
