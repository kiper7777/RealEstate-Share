<?php
require_once __DIR__ . '/../project/db.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
  echo json_encode(['success'=>false,'message'=>'Not authenticated'], JSON_UNESCAPED_UNICODE);
  exit;
}
$userId = (int)$_SESSION['user_id'];

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$propertyId = (int)($data['property_id'] ?? 0);
$message = trim((string)($data['message'] ?? ''));
if ($propertyId<=0 || $message==='') {
  echo json_encode(['success'=>false,'message'=>'Bad input'], JSON_UNESCAPED_UNICODE);
  exit;
}

$msgEsc = mysqli_real_escape_string($conn, $message);
mysqli_query($conn, "INSERT INTO messages (property_id, user_id, sender, message) VALUES ($propertyId, $userId, 'partner', '$msgEsc')");
echo json_encode(['success'=>true], JSON_UNESCAPED_UNICODE);
