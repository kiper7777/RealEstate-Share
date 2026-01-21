<?php
require_once __DIR__ . '/../project/db.php';
require_once __DIR__ . '/../project/csrf.php';
require_once __DIR__ . '/../project/config_mail.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
  echo json_encode(['success'=>false,'message'=>'Forbidden'], JSON_UNESCAPED_UNICODE);
  exit;
}

$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!csrf_validate($csrfHeader)) {
  echo json_encode(['success'=>false,'message'=>'CSRF invalid'], JSON_UNESCAPED_UNICODE);
  exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$userId = isset($data['user_id']) ? (int)$data['user_id'] : 0;
$participationId = isset($data['participation_id']) ? (int)$data['participation_id'] : 0;
$propertyId = isset($data['property_id']) ? (int)$data['property_id'] : 0;
$text = trim($data['message'] ?? '');

if ($userId <= 0 || $text === '' || mb_strlen($text) > 2000) {
  echo json_encode(['success'=>false,'message'=>'Bad input'], JSON_UNESCAPED_UNICODE);
  exit;
}

$adminId = (int)$_SESSION['user_id'];

$textEsc = mysqli_real_escape_string($conn, $text);
$pidVal = $participationId > 0 ? $participationId : 'NULL';
$propVal = $propertyId > 0 ? $propertyId : 'NULL';

// is_read=0 — у получателя (пользователя)
$sql = "INSERT INTO messages (user_id, admin_id, participation_id, property_id, sender_role, message_text, is_read)
        VALUES ($userId, $adminId, $pidVal, $propVal, 'admin', '$textEsc', 0)";

if (!mysqli_query($conn, $sql)) {
  echo json_encode(['success'=>false,'message'=>mysqli_error($conn)], JSON_UNESCAPED_UNICODE);
  exit;
}

$msgId = mysqli_insert_id($conn);

// Email пользователю
$resU = mysqli_query($conn, "SELECT email, name FROM users WHERE id=$userId LIMIT 1");
if ($resU && mysqli_num_rows($resU) > 0) {
  $u = mysqli_fetch_assoc($resU);
  $to = $u['email'] ?? '';
  $uname = $u['name'] ?? '';
  if ($to) {
    $subject = "Новое сообщение по заявке #$participationId";
    $body = "Здравствуйте, $uname!\n\nУ вас новое сообщение от администратора по заявке #$participationId.\n\nТекст:\n$text\n\nВойдите в личный кабинет, чтобы ответить.";
    send_email_notification($to, $subject, $body);
  }
}

echo json_encode(['success'=>true,'id'=>$msgId], JSON_UNESCAPED_UNICODE);
