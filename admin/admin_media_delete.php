<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../csrf.php';

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
if ($id<=0) {
  echo json_encode(['success'=>false,'message'=>'Bad id'], JSON_UNESCAPED_UNICODE);
  exit;
}

$res = mysqli_query($conn, "SELECT file_path FROM property_media WHERE id=$id LIMIT 1");
if (!$res || mysqli_num_rows($res)===0) {
  echo json_encode(['success'=>false,'message'=>'Not found'], JSON_UNESCAPED_UNICODE);
  exit;
}
$row = mysqli_fetch_assoc($res);
$file = __DIR__ . '/../' . $row['file_path'];

mysqli_query($conn, "DELETE FROM property_media WHERE id=$id");
if (is_file($file)) @unlink($file);

echo json_encode(['success'=>true], JSON_UNESCAPED_UNICODE);
