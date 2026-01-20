<?php
require_once 'db.php';
require_once 'csrf.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
  echo json_encode(['success'=>false,'message'=>'Unauthorized'], JSON_UNESCAPED_UNICODE);
  exit;
}

$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!csrf_validate($csrfHeader)) {
  echo json_encode(['success'=>false,'message'=>'CSRF invalid'], JSON_UNESCAPED_UNICODE);
  exit;
}

$userId = (int)$_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true) ?: [];

$participationId = isset($data['participation_id']) ? (int)$data['participation_id'] : 0;
$propertyId = isset($data['property_id']) ? (int)$data['property_id'] : 0;
$text = trim($data['message'] ?? '');

if ($participationId <= 0 || $text === '' || mb_strlen($text) > 2000) {
  echo json_encode(['success'=>false,'message'=>'Bad input'], JSON_UNESCAPED_UNICODE);
  exit;
}

// проверяем, что заявка принадлежит пользователю
$chk = mysqli_query($conn, "SELECT property_id FROM participations WHERE id=$participationId AND user_id=$userId LIMIT 1");
if (!$chk || mysqli_num_rows($chk) === 0) {
  echo json_encode(['success'=>false,'message'=>'Not your participation'], JSON_UNESCAPED_UNICODE);
  exit;
}
$row = mysqli_fetch_assoc($chk);
if ($propertyId <= 0) $propertyId = (int)$row['property_id'];

// находим админа
$adminId = 0;
$resA = mysqli_query($conn, "SELECT id FROM users WHERE is_admin=1 ORDER BY id ASC LIMIT 1");
if ($resA && mysqli_num_rows($resA) > 0) $adminId = (int)mysqli_fetch_assoc($resA)['id'];

if ($adminId <= 0) {
  echo json_encode(['success'=>false,'message'=>'Admin not found (is_admin=1)'], JSON_UNESCAPED_UNICODE);
  exit;
}

$textEsc = mysqli_real_escape_string($conn, $text);
$sql = "INSERT INTO messages (user_id, admin_id, participation_id, property_id, sender_role, message_text)
        VALUES ($userId, $adminId, $participationId, $propertyId, 'user', '$textEsc')";

if (!mysqli_query($conn, $sql)) {
  echo json_encode(['success'=>false,'message'=>mysqli_error($conn)], JSON_UNESCAPED_UNICODE);
  exit;
}

echo json_encode(['success'=>true,'id'=>mysqli_insert_id($conn)], JSON_UNESCAPED_UNICODE);
