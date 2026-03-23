<?php

date_default_timezone_set('Asia/Tashkent');
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

$driver     = requireDriver($conn);
$product_id = (int) ($_GET['product_id'] ?? 0);
$exclude_id = (int) ($_GET['exclude_order_id'] ?? 0); // joriy buyurtma

if (!$product_id || !$exclude_id) {
    http_response_code(400);
    die(json_encode(['error' => 'product_id and exclude_order_id required']));
}

// Shu haydovchining status=3 buyurtmalaridan shu product bor va qty > 0 bo'lganlarini topamiz
$stmt = $conn->prepare(
    "SELECT
        oi.id       AS item_id,
        oi.quantity,
        oi.product_type,
        o.id        AS order_id,
        o.order_number,
        s.name      AS shop_name
     FROM order_items oi
     INNER JOIN orders o ON o.id = oi.order_id
     INNER JOIN shops  s ON s.id = o.shop_id
     WHERE oi.product_id  = ?
       AND o.employee_id  = ?
       AND o.status       = 3
       AND o.id          != ?
       AND o.is_rejected  = 0
       AND oi.quantity    > 0
       AND oi.deleted_at IS NULL
       AND o.deleted_at  IS NULL
     ORDER BY o.delivery_date ASC"
);
$stmt->execute([$product_id, $driver['employees_id'], $exclude_id]);
$sources = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($sources as &$s) {
    $s['quantity']     = (float) $s['quantity'];
    $s['product_type'] = (int)   $s['product_type'];
}
unset($s);

echo json_encode(['sources' => $sources]);
