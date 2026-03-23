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
$value  = (int) ($body['value']    ?? 0);

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

$conn->beginTransaction();
try {
    if ($value) {
        // Rad qilindi — barcha o'zgarishlarni qty_before qiymatiga qaytarish
        $logs = $conn->prepare(
            "SELECT * FROM driver_item_logs
             WHERE order_id = ? AND reverted = 0
             ORDER BY id ASC"
        );
        $logs->execute([$id]);
        $logRows = $logs->fetchAll(PDO::FETCH_ASSOC);

        foreach ($logRows as $log) {
            if ((int) $log['change_type'] === 1) {
                // transfer_in edi:
                // — maqsad itemni o'zgarishdan oldingi qiymatga qaytarish
                $conn->prepare(
                    "UPDATE order_items SET quantity = ?, updated_at = NOW() WHERE id = ?"
                )->execute([$log['qty_before'], $log['order_item_id']]);

                // — manba itemni ham oldingi qiymatiga qaytarish
                if ($log['source_order_item_id'] && $log['source_qty_before'] !== null) {
                    $conn->prepare(
                        "UPDATE order_items SET quantity = ?, updated_at = NOW() WHERE id = ?"
                    )->execute([$log['source_qty_before'], $log['source_order_item_id']]);
                }
            } else {
                // direct_decrease edi:
                // — itemni o'zgarishdan oldingi qiymatga qaytarish
                $conn->prepare(
                    "UPDATE order_items SET quantity = ?, updated_at = NOW() WHERE id = ?"
                )->execute([$log['qty_before'], $log['order_item_id']]);
            }

            $conn->prepare(
                "UPDATE driver_item_logs SET reverted = 1, updated_at = NOW() WHERE id = ?"
            )->execute([$log['id']]);
        }
    }

    $conn->prepare("UPDATE orders SET is_rejected = ?, updated_at = NOW() WHERE id = ?")
         ->execute([$value ? 1 : 0, $id]);

    $conn->commit();
} catch (Exception $e) {
    $conn->rollBack();
    http_response_code(500);
    die(json_encode(['error' => 'Transaction failed: ' . $e->getMessage()]));
}

echo json_encode(['success' => true]);
