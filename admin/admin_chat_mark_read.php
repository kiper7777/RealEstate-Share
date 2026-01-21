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
$userId = isset($data['user_id']) ? (int)$data['user_id'] : 0;
$participationId = isset($data['participation_id']) ? (int)$data['participation_id'] : 0;

if ($userId <= 0 || $participationId <= 0) {
  echo json_encode(['success'=>false,'message'=>'Bad input'], JSON_UNESCAPED_UNICODE);
  exit;
}

$sql = "UPDATE messages
        SET is_read=1, read_at=NOW()
        WHERE user_id=$userId AND participation_id=$participationId
          AND sender_role='user' AND is_read=0";

mysqli_query($conn, $sql);

echo json_encode(['success'=>true], JSON_UNESCAPED_UNICODE);
