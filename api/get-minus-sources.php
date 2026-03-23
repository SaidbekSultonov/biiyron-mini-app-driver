<?php

date_default_timezone_set('Asia/Tashkent');
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

$driver  = requireDriver($conn);
$item_id = (int) ($_GET['item_id'] ?? 0);

if (!$item_id) {
    http_response_code(400);
    die(json_encode(['error' => 'item_id required']));
}

// Shu order_item uchun qaytarib yuborish mumkin bo'lgan transfer manbalari
// (unreverted transfer_in loglar, source_order bo'yicha guruhlanadi)
$stmt = $conn->prepare(
    "SELECT
        dil.source_order_item_id,
        dil.source_order_id,
        o.order_number,
        s.name    AS shop_name,
        COUNT(*)  AS transfer_count
     FROM driver_item_logs dil
     INNER JOIN orders o ON o.id  = dil.source_order_id
     INNER JOIN shops  s ON s.id  = o.shop_id
     WHERE dil.order_item_id = ?
       AND dil.change_type   = 1
       AND dil.reverted      = 0
     GROUP BY dil.source_order_item_id, dil.source_order_id, o.order_number, s.name
     ORDER BY MIN(dil.id) ASC"
);
$stmt->execute([$item_id]);
$sources = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($sources as &$s) {
    $s['transfer_count'] = (int) $s['transfer_count'];
}
unset($s);

echo json_encode(['sources' => $sources]);
