<?php

date_default_timezone_set('Asia/Tashkent');
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

$driver = requireDriver($conn);

// status=2 (yangi), 3 (yo'lda), 4 (yopilgan)
$stmt = $conn->prepare(
    "SELECT
        o.id,
        o.order_number,
        o.status,
        o.delivery_date,
        o.delivery_time,
        o.total_amount,
        o.delivery_fee_paid,
        o.is_rejected,
        s.name    AS shop_name,
        s.address AS shop_address,
        d.name_uz AS district_name
     FROM orders o
     INNER JOIN shops      s  ON s.id  = o.shop_id
     LEFT  JOIN warehouses w  ON w.id  = s.warehouse_id
     LEFT  JOIN districts  d  ON d.id  = w.district_id
     WHERE o.employee_id = ?
       AND o.status IN (2, 3, 4)
       AND o.deleted_at IS NULL
     ORDER BY
       FIELD(o.status, 2, 3, 4),
       o.delivery_date ASC,
       o.delivery_time ASC"
);
$stmt->execute([$driver['employees_id']]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$orders = ['new' => [], 'road' => [], 'closed' => []];

foreach ($rows as $o) {
    $o['status'] = (int) $o['status'];
    switch ($o['status']) {
        case 2: $orders['new'][]    = $o; break;
        case 3: $orders['road'][]   = $o; break;
        case 4: $orders['closed'][] = $o; break;
    }
}

echo json_encode([
    'driver' => $driver,
    'orders' => $orders,
]);
