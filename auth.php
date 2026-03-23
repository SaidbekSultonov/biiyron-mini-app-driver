<?php

/**
 * Telegram initData ni tekshiradi va haydovchi ma'lumotlarini qaytaradi.
 * Muvaffaqiyatsiz bo'lsa 401 qaytaradi.
 */
function requireDriver(PDO $conn): array
{
    // JSON body ni ham tekshirish (POST so'rovlar uchun)
    $jsonBody = [];
    $raw = file_get_contents('php://input');
    if ($raw) {
        $jsonBody = json_decode($raw, true) ?? [];
    }

    $initData = $_GET['initData'] ?? $_POST['initData'] ?? $jsonBody['initData'] ?? '';

    // DEV: initData bo'lmasa test user_id bilan ishlash
    if (empty($initData)) {
        $user_id = (int) ($_GET['user_id'] ?? $_POST['user_id'] ?? $jsonBody['user_id'] ?? 0);
        if (!$user_id) {
            http_response_code(401);
            die(json_encode(['error' => 'Unauthorized']));
        }
    } else {
        $user_id = validateInitData($initData);
        if (!$user_id) {
            http_response_code(401);
            die(json_encode(['error' => 'Invalid initData']));
        }
    }

    $stmt = $conn->prepare(
        "SELECT bd.id, bd.user_id, bd.employees_id, e.full_name, e.phone_number
         FROM bot_drivers bd
         INNER JOIN employees e ON e.id = bd.employees_id
         WHERE bd.user_id = ? AND bd.deleted_at IS NULL
         LIMIT 1"
    );
    $stmt->execute([$user_id]);
    $driver = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$driver) {
        http_response_code(403);
        die(json_encode(['error' => 'Driver not found']));
    }

    return $driver;
}

function validateInitData(string $initData): int
{
    parse_str($initData, $params);

    $hash = $params['hash'] ?? '';
    unset($params['hash']);

    ksort($params);
    $dataCheckString = implode("\n", array_map(
        fn($k, $v) => "$k=$v",
        array_keys($params),
        array_values($params)
    ));

    $secretKey = hash_hmac('sha256', BOT_TOKEN, 'WebAppData', true);
    $expected  = hash_hmac('sha256', $dataCheckString, $secretKey);

    if (!hash_equals($expected, $hash)) {
        return 0;
    }

    $user = json_decode($params['user'] ?? '{}', true);
    return (int) ($user['id'] ?? 0);
}
