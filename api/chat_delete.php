<?php
require_once __DIR__ . '/../project/db.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
  echo json_encode(['success'=>false,'message'=>'Not authenticated'], JSON_UNESCAPED_UNICODE);
  exit;
}
$userId = (int)$_SESSION['user_id'];

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$id = (int)($data['id'] ?? 0);
if ($id<=0) {
  echo json_encode(['success'=>false,'message'=>'Bad id'], JSON_UNESCAPED_UNICODE);
  exit;
}

mysqli_query($conn, "UPDATE messages SET is_deleted=1 WHERE id=$id AND user_id=$userId AND sender='partner'");
echo json_encode(['success'=>true], JSON_UNESCAPED_UNICODE);
