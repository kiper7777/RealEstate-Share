<?php
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

// Проверяем авторизацию
if (empty($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'error_code' => 'not_authenticated',
        'message' => 'Необходимо войти как партнёр.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Читаем JSON
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$propertyId = isset($data['property_id']) ? (int)$data['property_id'] : 0;
$amount     = isset($data['amount']) ? (float)$data['amount'] : 0;

if ($propertyId <= 0 || $amount <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Некорректные данные участия.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Получаем информацию об объекте
$sql = "SELECT 
            p.*,
            COALESCE(SUM(part.amount), 0) AS invested,
            COUNT(part.id) AS participants
        FROM properties p
        LEFT JOIN participations part ON part.property_id = p.id
        WHERE p.id = $propertyId
        GROUP BY p.id
        LIMIT 1";
$res = mysqli_query($conn, $sql);

if (!$res || mysqli_num_rows($res) === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Объект не найден.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$property = mysqli_fetch_assoc($res);
$property['price']         = (float)$property['price'];
$property['min_ticket']    = (float)$property['min_ticket'];
$property['max_partners']  = (int)$property['max_partners'];
$property['rent_per_year'] = (float)$property['rent_per_year'];
$property['yield_percent'] = (float)$property['yield_percent'];
$property['payback_years'] = (float)$property['payback_years'];
$property['invested']      = (float)$property['invested'];
$property['participants']  = (int)$property['participants'];

$remaining = max($property['price'] - $property['invested'], 0);

// Валидация
if ($amount < $property['min_ticket']) {
    echo json_encode([
        'success' => false,
        'message' => 'Минимальная сумма участия: €' . number_format($property['min_ticket'], 0, ',', ' ')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($amount > $remaining) {
    echo json_encode([
        'success' => false,
        'message' => 'Сумма больше оставшейся к сбору для объекта.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Сохраняем участие
$userId = (int)$_SESSION['user_id'];
$amountEsc = mysqli_real_escape_string($conn, $amount);
$sqlInsert = "INSERT INTO participations (user_id, property_id, amount)
              VALUES ($userId, $propertyId, $amountEsc)";
if (!mysqli_query($conn, $sqlInsert)) {
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка сохранения участия: ' . mysqli_error($conn)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Пересчитываем агрегированные данные по объекту
$sql2 = "SELECT 
            p.*,
            COALESCE(SUM(part.amount), 0) AS invested,
            COUNT(part.id) AS participants
        FROM properties p
        LEFT JOIN participations part ON part.property_id = p.id
        WHERE p.id = $propertyId
        GROUP BY p.id
        LIMIT 1";
$res2 = mysqli_query($conn, $sql2);

if (!$res2 || mysqli_num_rows($res2) === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка пересчёта данных объекта.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$updated = mysqli_fetch_assoc($res2);
$updated['price']         = (float)$updated['price'];
$updated['min_ticket']    = (float)$updated['min_ticket'];
$updated['max_partners']  = (int)$updated['max_partners'];
$updated['rent_per_year'] = (float)$updated['rent_per_year'];
$updated['yield_percent'] = (float)$updated['yield_percent'];
$updated['payback_years'] = (float)$updated['payback_years'];
$updated['invested']      = (float)$updated['invested'];
$updated['participants']  = (int)$updated['participants'];

echo json_encode([
    'success' => true,
    'property' => $updated
], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
exit;
