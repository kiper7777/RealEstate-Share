<?php
require_once __DIR__ . '/../project/db.php';
require_once __DIR__ . '/../project/csrf.php';

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
$propertyId = (int)($data['property_id'] ?? 0);
$userId = (int)($data['user_id'] ?? 0);
$message = trim((string)($data['message'] ?? ''));
if ($propertyId<=0 || $userId<=0 || $message==='') {
  echo json_encode(['success'=>false,'message'=>'Bad input'], JSON_UNESCAPED_UNICODE);
  exit;
}

$msgEsc = mysqli_real_escape_string($conn, $message);
mysqli_query($conn, "INSERT INTO messages (property_id, user_id, sender, message) VALUES ($propertyId, $userId, 'admin', '$msgEsc')");
echo json_encode(['success'=>true], JSON_UNESCAPED_UNICODE);
