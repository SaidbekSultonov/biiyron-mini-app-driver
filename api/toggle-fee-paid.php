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
$id     = (int) ($body['order_id'] ?? 0);
$value  = (int) ($body['value']    ?? 0); // 0 yoki 1

if (!$id) {
    http_response_code(400); die(json_encode(['error' => 'order_id required']));
}

$stmt = $conn->prepare(
    "SELECT id FROM orders
     WHERE id = ? AND employee_id = ? AND status = 3 AND deleted_at IS NULL LIMIT 1"
);
$stmt->execute([$id, $driver['employees_id']]);
if (!$stmt->fetch()) {
    http_response_code(404); die(json_encode(['error' => 'Order not found']));
}

$conn->prepare("UPDATE orders SET delivery_fee_paid = ?, updated_at = NOW() WHERE id = ?")
     ->execute([$value ? 1 : 0, $id]);

echo json_encode(['success' => true]);
