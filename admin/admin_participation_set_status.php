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
$id = isset($data['id']) ? (int)$data['id'] : 0;
$status = $data['status'] ?? '';

if ($id <= 0 || !in_array($status, ['approved','rejected'], true)) {
  echo json_encode(['success'=>false,'message'=>'Bad input'], JSON_UNESCAPED_UNICODE);
  exit;
}

if (!mysqli_query($conn, "UPDATE participations SET status='$status' WHERE id=$id")) {
  echo json_encode(['success'=>false,'message'=>mysqli_error($conn)], JSON_UNESCAPED_UNICODE);
  exit;
}

if (mysqli_affected_rows($conn) === 0) {
  echo json_encode(['success'=>false,'message'=>'Record not found or already updated'], JSON_UNESCAPED_UNICODE);
  exit;
}

echo json_encode(['success'=>true,'id'=>$id,'status'=>$status], JSON_UNESCAPED_UNICODE);
