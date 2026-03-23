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

if (!$id) {
    http_response_code(400); die(json_encode(['error' => 'order_id required']));
}

$stmt = $conn->prepare(
    "SELECT id FROM orders
     WHERE id = ? AND employee_id = ? AND status = 2 AND deleted_at IS NULL LIMIT 1"
);
$stmt->execute([$id, $driver['employees_id']]);

if (!$stmt->fetch()) {
    http_response_code(404); die(json_encode(['error' => 'Order not found or wrong status']));
}

$conn->beginTransaction();
try {
    // Status 3 ga o'tkazish
    $conn->prepare("UPDATE orders SET status = 3, updated_at = NOW() WHERE id = ?")
         ->execute([$id]);

    // Har bir order_item ning hozirgi quantity ni qty_original sifatida saqlash
    // (keyinchalik o'zgarish bo'lsa yopish vaqtida taqqoslash uchun)
    $conn->prepare(
        "UPDATE order_items SET qty_original = quantity WHERE order_id = ? AND deleted_at IS NULL"
    )->execute([$id]);

    $conn->commit();
} catch (Exception $e) {
    $conn->rollBack();
    http_response_code(500);
    die(json_encode(['error' => 'Transaction failed']));
}

echo json_encode(['success' => true]);
