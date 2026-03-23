<?php

date_default_timezone_set('Asia/Tashkent');
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config.php';

$driver   = requireDriver($conn);
$order_id = (int) ($_GET['id'] ?? 0);

if (!$order_id) {
    http_response_code(400);
    die(json_encode(['error' => 'id required']));
}

$stmt = $conn->prepare(
    "SELECT
        o.id,
        o.order_number,
        o.status,
        o.delivery_date,
        o.delivery_time,
        o.total_amount,
        o.comment,
        o.driver_comment,
        o.delivery_fee_paid,
        o.delivery_price,
        o.is_rejected,
        c.owner_name AS client_name,
        s.name    AS shop_name,
        s.address AS shop_address,
        d.name_uz AS district_name
     FROM orders o
     INNER JOIN clients   c ON c.id = o.client_id
     INNER JOIN shops      s  ON s.id  = o.shop_id
     LEFT  JOIN warehouses w  ON w.id  = s.warehouse_id
     LEFT  JOIN districts  d  ON d.id  = w.district_id
     WHERE o.id = ?
       AND o.employee_id = ?
       AND o.status IN (2, 3, 4)
       AND o.deleted_at IS NULL
     LIMIT 1"
);
$stmt->execute([$order_id, $driver['employees_id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    http_response_code(404);
    die(json_encode(['error' => 'Order not found']));
}

// Mahsulotlar
$stmt2 = $conn->prepare(
    "SELECT
        oi.id          AS item_id,
        oi.product_id,
        oi.product_type,
        oi.quantity,
        oi.price,
        oi.total,
        p.name_uz      AS name,
        p.image,
        p.product_weight
     FROM order_items oi
     INNER JOIN products p ON p.id = oi.product_id
     WHERE oi.order_id = ? AND oi.deleted_at IS NULL
     ORDER BY oi.id"
);
$stmt2->execute([$order_id]);
$items = $stmt2->fetchAll(PDO::FETCH_ASSOC);

foreach ($items as &$item) {
    $item['quantity']       = (float) $item['quantity'];
    $item['price']          = (float) $item['price'];
    $item['total']          = (float) $item['total'];
    $item['product_type']   = (int)   $item['product_type'];
    $item['product_weight'] = (float) ($item['product_weight'] ?? 0);
    $item['image_url']      = $item['image']
        ? APP_URL . '/' . $item['image']
        : null;
}
unset($item);

$order['status']           = (int)   $order['status'];
$order['delivery_fee_paid'] = (int)   $order['delivery_fee_paid'];
$order['delivery_price']    = $order['delivery_price'] !== null ? (float) $order['delivery_price'] : null;
$order['is_rejected']       = (int)   $order['is_rejected'];
$order['items']             = $items;

// Yopilgan buyurtma uchun o'zgargan mahsulotlar tarixi
$change_logs = [];
if ($order['status'] === 4) {
    $stmt3 = $conn->prepare(
        "SELECT product_name, qty_original, qty_final
         FROM order_item_change_logs
         WHERE order_id = ?
         ORDER BY id"
    );
    $stmt3->execute([$order_id]);
    $change_logs = $stmt3->fetchAll(PDO::FETCH_ASSOC);
    foreach ($change_logs as &$cl) {
        $cl['qty_original'] = (float) $cl['qty_original'];
        $cl['qty_final']    = (float) $cl['qty_final'];
    }
    unset($cl);
}
$order['change_logs'] = $change_logs;

echo json_encode(['order' => $order]);
