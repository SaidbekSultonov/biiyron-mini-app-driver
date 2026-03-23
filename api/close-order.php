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
    "SELECT id, client_id, total_amount, delivery_price FROM orders
     WHERE id = ? AND employee_id = ? AND status = 3 AND deleted_at IS NULL LIMIT 1"
);
$stmt->execute([$id, $driver['employees_id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) {
    http_response_code(404); die(json_encode(['error' => 'Order not found or wrong status']));
}

$conn->beginTransaction();
try {
    // Miqdori o'zgargan mahsulotlarni topish va tarix jadvaliga yozish
    // (faqat qty_original != quantity bo'lgan itemlar)
    $changed = $conn->prepare(
        "SELECT oi.id, oi.product_id, oi.qty_original, oi.quantity AS qty_final,
                p.name_uz AS product_name
         FROM order_items oi
         INNER JOIN products p ON p.id = oi.product_id
         WHERE oi.order_id      = ?
           AND oi.deleted_at   IS NULL
           AND oi.qty_original IS NOT NULL
           AND oi.quantity     != oi.qty_original"
    );
    $changed->execute([$id]);
    $changedItems = $changed->fetchAll(PDO::FETCH_ASSOC);

    foreach ($changedItems as $item) {
        $conn->prepare(
            "INSERT INTO order_item_change_logs
             (order_id, order_item_id, product_id, product_name, qty_original, qty_final, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())"
        )->execute([
            $id,
            $item['id'],
            $item['product_id'],
            $item['product_name'],
            $item['qty_original'],
            $item['qty_final'],
        ]);
    }

    // Buyurtmani yopish
    $conn->prepare("UPDATE orders SET status = 4, delivered_at = NOW(), updated_at = NOW() WHERE id = ?")
         ->execute([$id]);

    // Mijozdan yechilishi kerak bo'lgan yakuniy summa: mahsulotlar + yetkazib berish
    $finalAmount = (float) $order['total_amount'] + (float) ($order['delivery_price'] ?? 0);

    // Client transaction yozish (qarz — type=2)
    $conn->prepare(
        "INSERT INTO client_transactions (client_id, order_id, amount, type, comment, created_at, updated_at)
         VALUES (?, ?, ?, 2, ?, NOW(), NOW())"
    )->execute([
        $order['client_id'],
        $id,
        $finalAmount,
        "Buyurtma #{$id} yetkazildi",
    ]);

    // Mijoz balansini kamaytirish
    $conn->prepare(
        "UPDATE client_balance SET balance = balance - ?, updated_at = NOW() WHERE client_id = ?"
    )->execute([$finalAmount, $order['client_id']]);

    $conn->commit();
} catch (Exception $e) {
    $conn->rollBack();
    http_response_code(500);
    die(json_encode(['error' => 'Transaction failed: ' . $e->getMessage()]));
}

echo json_encode(['success' => true]);
