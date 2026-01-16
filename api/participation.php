<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../csrf.php';

header('Content-Type: application/json; charset=utf-8');

// CSRF для AJAX (передаём в заголовке X-CSRF-Token)
$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!csrf_validate($csrfHeader)) {
    echo json_encode([
        'success' => false,
        'error_code' => 'csrf_invalid',
        'message' => 'CSRF token invalid.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'error_code' => 'not_authenticated',
        'message' => 'Необходимо войти как партнёр.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$propertyId = isset($data['property_id']) ? (int)$data['property_id'] : 0;
$amount     = isset($data['amount']) ? (float)$data['amount'] : 0;

if ($propertyId <= 0 || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Некорректные данные участия.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Берём объект + агрегаты
$sql = "SELECT 
            p.*,
            COALESCE(SUM(part.amount), 0) AS invested,
            COUNT(part.id) AS participants
        FROM properties p
        LEFT JOIN participations part ON part.property_id = p.id AND part.status IN ('pending','approved')
        WHERE p.id = $propertyId
        GROUP BY p.id
        LIMIT 1";
$res = mysqli_query($conn, $sql);

if (!$res || mysqli_num_rows($res) === 0) {
    echo json_encode(['success' => false, 'message' => 'Объект не найден.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$property = mysqli_fetch_assoc($res);
$price      = (float)$property['price'];
$minTicket  = (float)$property['min_ticket'];
$invested   = (float)$property['invested'];
$remaining  = max($price - $invested, 0);

if ($amount < $minTicket) {
    echo json_encode([
        'success' => false,
        'message' => 'Минимальная сумма участия: €' . number_format($minTicket, 0, ',', ' ')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($amount > $remaining) {
    echo json_encode(['success' => false, 'message' => 'Сумма больше оставшейся к сбору для объекта.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Считаем долю
$sharePercent = $price > 0 ? ($amount / $price * 100) : 0;
$sharePercentEsc = mysqli_real_escape_string($conn, (string)$sharePercent);
$amountEsc = mysqli_real_escape_string($conn, (string)$amount);

// Пишем участие как pending (реально в бизнесе обычно есть модерация/подписание)
$sqlInsert = "INSERT INTO participations (user_id, property_id, amount, status, share_percent)
              VALUES ($userId, $propertyId, $amountEsc, 'pending', $sharePercentEsc)";
if (!mysqli_query($conn, $sqlInsert)) {
    echo json_encode(['success' => false, 'message' => 'Ошибка сохранения: ' . mysqli_error($conn)], JSON_UNESCAPED_UNICODE);
    exit;
}

// Возвращаем обновлённые агрегаты
$sql2 = "SELECT 
            p.*,
            COALESCE(SUM(part.amount), 0) AS invested,
            COUNT(part.id) AS participants
        FROM properties p
        LEFT JOIN participations part ON part.property_id = p.id AND part.status IN ('pending','approved')
        WHERE p.id = $propertyId
        GROUP BY p.id
        LIMIT 1";
$res2 = mysqli_query($conn, $sql2);
$updated = mysqli_fetch_assoc($res2);

// Приведение чисел
$updated['id']           = (int)$updated['id'];
$updated['price']        = (float)$updated['price'];
$updated['min_ticket']   = (float)$updated['min_ticket'];
$updated['max_partners'] = (int)$updated['max_partners'];
$updated['rent_per_year']= (float)$updated['rent_per_year'];
$updated['yield_percent']= (float)$updated['yield_percent'];
$updated['payback_years']= (float)$updated['payback_years'];
$updated['invested']     = (float)$updated['invested'];
$updated['participants'] = (int)$updated['participants'];

echo json_encode(['success' => true, 'property' => $updated], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
exit;
