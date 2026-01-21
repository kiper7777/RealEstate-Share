<?php
require_once 'db.php';
require_once 'csrf.php';
require_once 'config_mail.php';

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

// Проверка, что заявка принадлежит пользователю
$chk = mysqli_query($conn, "SELECT property_id FROM participations WHERE id=$participationId AND user_id=$userId LIMIT 1");
if (!$chk || mysqli_num_rows($chk) === 0) {
  echo json_encode(['success'=>false,'message'=>'Not your participation'], JSON_UNESCAPED_UNICODE);
  exit;
}
$row = mysqli_fetch_assoc($chk);
if ($propertyId <= 0) $propertyId = (int)$row['property_id'];

// Админ
$adminId = 0; $adminEmail=''; $adminName='';
$resA = mysqli_query($conn, "SELECT id, email, name FROM users WHERE is_admin=1 ORDER BY id ASC LIMIT 1");
if ($resA && mysqli_num_rows($resA) > 0) {
  $a = mysqli_fetch_assoc($resA);
  $adminId = (int)$a['id'];
  $adminEmail = $a['email'] ?? '';
  $adminName = $a['name'] ?? '';
}
if ($adminId <= 0) {
  echo json_encode(['success'=>false,'message'=>'Admin not found (is_admin=1)'], JSON_UNESCAPED_UNICODE);
  exit;
}

// is_read=0 — у получателя (админа)
$textEsc = mysqli_real_escape_string($conn, $text);
$sql = "INSERT INTO messages (user_id, admin_id, participation_id, property_id, sender_role, message_text, is_read)
        VALUES ($userId, $adminId, $participationId, $propertyId, 'user', '$textEsc', 0)";

if (!mysqli_query($conn, $sql)) {
  echo json_encode(['success'=>false,'message'=>mysqli_error($conn)], JSON_UNESCAPED_UNICODE);
  exit;
}

$msgId = mysqli_insert_id($conn);

// Email админу
$to = ADMIN_NOTIFY_EMAIL ?: $adminEmail;
if ($to) {
  $subject = "Новое сообщение от партнёра по заявке #$participationId";
  $body = "Здравствуйте, $adminName!\n\nНовое сообщение от партнёра (user_id=$userId) по заявке #$participationId.\n\nТекст:\n$text\n\nОткройте Admin → Pending → Чат.";
  send_email_notification($to, $subject, $body);
}

echo json_encode(['success'=>true,'id'=>$msgId], JSON_UNESCAPED_UNICODE);
