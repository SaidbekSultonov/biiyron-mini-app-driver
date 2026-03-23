<?php

date_default_timezone_set('Asia/Tashkent');
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); die(json_encode(['error' => 'POST only']));
}

$driver           = requireDriver($conn);
$body             = json_decode(file_get_contents('php://input'), true) ?? [];
$to_item_id       = (int) ($body['to_item_id']       ?? 0); // joriy buyurtma item (kamayadi)
$source_item_id   = (int) ($body['source_item_id']   ?? 0); // manba buyurtma item (ko'payadi)

if (!$to_item_id || !$source_item_id) {
    http_response_code(400); die(json_encode(['error' => 'to_item_id and source_item_id required']));
}

// Eng qadimgi unreverted transfer_in logni topish
$stmt = $conn->prepare(
    "SELECT id, qty_before, source_qty_before
     FROM driver_item_logs
     WHERE order_item_id      = ?
       AND source_order_item_id = ?
       AND change_type         = 1
       AND reverted            = 0
     ORDER BY id ASC
     LIMIT 1"
);
$stmt->execute([$to_item_id, $source_item_id]);
$log = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$log) {
    http_response_code(404); die(json_encode(['error' => 'No transfer log found to reverse']));
}

// Joriy qiymatlarni olish
$stmt2 = $conn->prepare(
    "SELECT oi.id, oi.quantity, o.id AS order_id, o.employee_id
     FROM order_items oi
     INNER JOIN orders o ON o.id = oi.order_id
     WHERE oi.id = ? AND o.employee_id = ? AND oi.deleted_at IS NULL LIMIT 1"
);
$stmt2->execute([$to_item_id, $driver['employees_id']]);
$to_item = $stmt2->fetch(PDO::FETCH_ASSOC);

if (!$to_item || (float) $to_item['quantity'] <= 0) {
    http_response_code(400); die(json_encode(['error' => 'Cannot reverse: to_item quantity is 0']));
}

$stmt3 = $conn->prepare(
    "SELECT oi.id, oi.quantity, o.id AS order_id
     FROM order_items oi
     INNER JOIN orders o ON o.id = oi.order_id
     WHERE oi.id = ? AND oi.deleted_at IS NULL LIMIT 1"
);
$stmt3->execute([$source_item_id]);
$src_item = $stmt3->fetch(PDO::FETCH_ASSOC);

if (!$src_item) {
    http_response_code(404); die(json_encode(['error' => 'Source item not found']));
}

$new_to_qty  = (float) $to_item['quantity']  - 1;
$new_src_qty = (float) $src_item['quantity'] + 1;

$conn->beginTransaction();
try {
    $conn->prepare("UPDATE order_items SET quantity = ?, updated_at = NOW() WHERE id = ?")
         ->execute([$new_to_qty, $to_item_id]);

    $conn->prepare("UPDATE order_items SET quantity = ?, updated_at = NOW() WHERE id = ?")
         ->execute([$new_src_qty, $source_item_id]);

    $conn->prepare("UPDATE driver_item_logs SET reverted = 1, updated_at = NOW() WHERE id = ?")
         ->execute([$log['id']]);

    // Ikkala buyurtma total_amount ni qayta hisoblash
    foreach (array_unique([$to_item['order_id'], $src_item['order_id']]) as $oid) {
        $conn->prepare(
            "UPDATE orders
             SET total_amount = (
                 SELECT SUM(oi2.quantity * oi2.price)
                 FROM order_items oi2
                 WHERE oi2.order_id = ? AND oi2.deleted_at IS NULL
             ), updated_at = NOW()
             WHERE id = ?"
        )->execute([$oid, $oid]);
    }

    $conn->commit();
} catch (Exception $e) {
    $conn->rollBack();
    http_response_code(500);
    die(json_encode(['error' => 'Transaction failed']));
}

echo json_encode([
    'success'    => true,
    'new_to_qty' => $new_to_qty,
]);
