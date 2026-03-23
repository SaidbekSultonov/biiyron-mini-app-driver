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

$to_item_id   = (int) ($body['to_item_id']   ?? 0); // joriy buyurtma item
$from_item_id = (int) ($body['from_item_id'] ?? 0); // manba buyurtma item

if (!$to_item_id || !$from_item_id) {
    http_response_code(400); die(json_encode(['error' => 'to_item_id and from_item_id required']));
}

// Manba itemni tekshirish
$stmt = $conn->prepare(
    "SELECT oi.id, oi.quantity, oi.product_type, oi.product_id, o.id AS order_id, o.is_rejected
     FROM order_items oi
     INNER JOIN orders o ON o.id = oi.order_id
     WHERE oi.id = ? AND o.employee_id = ? AND o.status = 3 AND oi.deleted_at IS NULL LIMIT 1"
);
$stmt->execute([$from_item_id, $driver['employees_id']]);
$from_item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$from_item) {
    http_response_code(404); die(json_encode(['error' => 'Source item not found']));
}
if ((int) $from_item['is_rejected']) {
    http_response_code(400); die(json_encode(['error' => 'Source order is rejected']));
}
if ((float) $from_item['quantity'] <= 0) {
    http_response_code(400); die(json_encode(['error' => 'Source item quantity is 0']));
}

// Maqsad itemni tekshirish
$stmt2 = $conn->prepare(
    "SELECT oi.id, oi.quantity, oi.product_type, oi.product_id, o.id AS order_id, o.is_rejected
     FROM order_items oi
     INNER JOIN orders o ON o.id = oi.order_id
     WHERE oi.id = ? AND o.employee_id = ? AND o.status = 3 AND oi.deleted_at IS NULL LIMIT 1"
);
$stmt2->execute([$to_item_id, $driver['employees_id']]);
$to_item = $stmt2->fetch(PDO::FETCH_ASSOC);

if (!$to_item) {
    http_response_code(404); die(json_encode(['error' => 'Target item not found']));
}
if ((int) $to_item['is_rejected']) {
    http_response_code(400); die(json_encode(['error' => 'Target order is rejected']));
}
if ($from_item['product_id'] !== $to_item['product_id']) {
    http_response_code(400); die(json_encode(['error' => 'Product mismatch']));
}

$delta            = 1; // har doim 1 birlik
$product_type     = (int)   $from_item['product_type'];
$to_qty_before    = (float) $to_item['quantity'];
$from_qty_before  = (float) $from_item['quantity'];
$new_to           = $to_qty_before   + $delta;
$new_from         = $from_qty_before - $delta;

$conn->beginTransaction();
try {
    // Manba itemdan -1 (aniq qiymat bilan)
    $conn->prepare("UPDATE order_items SET quantity = ?, updated_at = NOW() WHERE id = ?")
         ->execute([$new_from, $from_item_id]);

    // Maqsad itemga +1 (aniq qiymat bilan)
    $conn->prepare("UPDATE order_items SET quantity = ?, updated_at = NOW() WHERE id = ?")
         ->execute([$new_to, $to_item_id]);

    // Log: qty_before va source_qty_before saqlanadi — revert uchun delta emas aynan shu qiymatlar ishlatiladi
    $conn->prepare(
        "INSERT INTO driver_item_logs
         (order_id, order_item_id, change_type, product_type, delta,
          qty_before, source_qty_before,
          source_order_id, source_order_item_id, employees_id, created_at, updated_at)
         VALUES (?, ?, 1, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
    )->execute([
        $to_item['order_id'],
        $to_item_id,
        $product_type,
        $delta,
        $to_qty_before,
        $from_qty_before,
        $from_item['order_id'],
        $from_item_id,
        $driver['employees_id'],
    ]);

    $conn->commit();
} catch (Exception $e) {
    $conn->rollBack();
    http_response_code(500);
    die(json_encode(['error' => 'Transaction failed']));
}

echo json_encode([
    'success'       => true,
    'new_to_qty'    => $new_to,
    'new_from_qty'  => $new_from,
    'from_order_id' => $from_item['order_id'],
]);
