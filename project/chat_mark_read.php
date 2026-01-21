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

if ($participationId <= 0) {
  echo json_encode(['success'=>false,'message'=>'Bad participation_id'], JSON_UNESCAPED_UNICODE);
  exit;
}

$sql = "UPDATE messages
        SET is_read=1, read_at=NOW()
        WHERE user_id=$userId AND participation_id=$participationId
          AND sender_role='admin' AND is_read=0";
mysqli_query($conn, $sql);

echo json_encode(['success'=>true], JSON_UNESCAPED_UNICODE);
