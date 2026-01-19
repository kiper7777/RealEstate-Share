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

if ($id <= 0) {
  echo json_encode(['success'=>false,'message'=>'Bad id'], JSON_UNESCAPED_UNICODE);
  exit;
}

$res = mysqli_query($conn, "SELECT file_path FROM property_media WHERE id=$id LIMIT 1");
if (!$res || mysqli_num_rows($res) === 0) {
  echo json_encode(['success'=>false,'message'=>'Not found'], JSON_UNESCAPED_UNICODE);
  exit;
}

$row = mysqli_fetch_assoc($res);
$filePath = $row['file_path']; // ожидаем /uploads/xxx.jpg

// Переводим публичный путь в путь на диске
$relative = ltrim($filePath, '/'); // uploads/xxx.jpg
$diskPath = __DIR__ . '/../' . $relative;

mysqli_query($conn, "DELETE FROM property_media WHERE id=$id");

if (is_file($diskPath)) {
  @unlink($diskPath);
}

echo json_encode(['success'=>true], JSON_UNESCAPED_UNICODE);
