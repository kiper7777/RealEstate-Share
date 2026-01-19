<?php
require_once __DIR__ . '/../project/db.php';
require_once __DIR__ . '/../project/csrf.php';

header('Content-Type: application/json; charset=utf-8');

$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!csrf_validate($csrfHeader)) {
    echo json_encode(['success' => false, 'message' => 'CSRF invalid'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error_code' => 'not_authenticated', 'message' => 'Необходимо войти.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$propertyId = isset($data['property_id']) ? (int)$data['property_id'] : 0;
$amount     = isset($data['amount']) ? (float)$data['amount'] : 0;

if ($propertyId <= 0 || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Bad input'], JSON_UNESCAPED_UNICODE);
    exit;
}

$sql = "SELECT 
          p.*,
          COALESCE(SUM(CASE WHEN part.status IN ('pending','approved') THEN part.amount ELSE 0 END), 0) AS invested
        FROM properties p
        LEFT JOIN participations part ON part.property_id = p.id
        WHERE p.id = $propertyId
        GROUP BY p.id
        LIMIT 1";
$res = mysqli_query($conn, $sql);
if (!$res || mysqli_num_rows($res) === 0) {
    echo json_encode(['success' => false, 'message' => 'Property not found'], JSON_UNESCAPED_UNICODE);
    exit;
}
$p = mysqli_fetch_assoc($res);

$price = (float)$p['price'];
$minTicket = (float)$p['min_ticket'];
$invested = (float)$p['invested'];
$remaining = max($price - $invested, 0);

if ($amount < $minTicket) {
    echo json_encode(['success' => false, 'message' => 'Минимум: €' . number_format($minTicket, 0, ',', ' ')], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($amount > $remaining) {
    echo json_encode(['success' => false, 'message' => 'Сумма больше остатка для сбора'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$sharePercent = $price > 0 ? ($amount / $price * 100) : 0;

$amountEsc = mysqli_real_escape_string($conn, (string)$amount);
$shareEsc  = mysqli_real_escape_string($conn, (string)$sharePercent);

$ins = "INSERT INTO participations (user_id, property_id, amount, status, share_percent)
        VALUES ($userId, $propertyId, $amountEsc, 'pending', $shareEsc)";
if (!mysqli_query($conn, $ins)) {
    echo json_encode(['success' => false, 'message' => mysqli_error($conn)], JSON_UNESCAPED_UNICODE);
    exit;
}

/* Вернём обновлённый объект */
$sql2 = "SELECT 
          p.*,
          COALESCE(SUM(CASE WHEN part.status IN ('pending','approved') THEN part.amount ELSE 0 END), 0) AS invested,
          COUNT(CASE WHEN part.status IN ('pending','approved') THEN part.id ELSE NULL END) AS participants
        FROM properties p
        LEFT JOIN participations part ON part.property_id = p.id
        WHERE p.id = $propertyId
        GROUP BY p.id
        LIMIT 1";
$res2 = mysqli_query($conn, $sql2);
$u = mysqli_fetch_assoc($res2);

$prop = [
    'id' => (int)$u['id'],
    'name' => $u['name'],
    'location' => $u['location'],
    'region' => $u['region'],
    'type' => $u['type'],
    'status' => $u['status'] ?? 'funding',
    'price' => (float)$u['price'],
    'min_ticket' => (float)$u['min_ticket'],
    'max_partners' => (int)$u['max_partners'],
    'rent_per_year' => (float)$u['rent_per_year'],
    'yield_percent' => (float)$u['yield_percent'],
    'payback_years' => (float)$u['payback_years'],
    'risk' => $u['risk'],
    'description' => $u['description'],
    'invested' => (float)$u['invested'],
    'participants' => (int)$u['participants'],
    'media' => []
];

$resM = mysqli_query($conn, "SELECT id, file_path, caption, sort_order 
                             FROM property_media WHERE property_id=$propertyId
                             ORDER BY sort_order ASC, id ASC");
while ($resM && ($m = mysqli_fetch_assoc($resM))) {
    $prop['media'][] = [
        'id' => (int)$m['id'],
        'file_path' => $m['file_path'],
        'caption' => $m['caption'],
        'sort_order' => (int)$m['sort_order']
    ];
}

echo json_encode(['success' => true, 'property' => $prop], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
