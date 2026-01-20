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
$ids = $data['ids'] ?? [];
if (!is_array($ids) || empty($ids)) {
  echo json_encode(['success'=>false,'message'=>'No ids'], JSON_UNESCAPED_UNICODE);
  exit;
}

$idsInt = [];
foreach ($ids as $id) {
  $id = (int)$id;
  if ($id > 0) $idsInt[] = $id;
}
if (empty($idsInt)) {
  echo json_encode(['success'=>false,'message'=>'Bad ids'], JSON_UNESCAPED_UNICODE);
  exit;
}

$idList = implode(',', $idsInt);

// Сначала забираем пути для удаления файлов
$res = mysqli_query($conn, "SELECT id, file_path FROM property_media WHERE id IN ($idList)");
$files = [];
while ($res && ($r = mysqli_fetch_assoc($res))) {
  $files[] = $r['file_path'];
}

// удаляем записи
mysqli_query($conn, "DELETE FROM property_media WHERE id IN ($idList)");

// удаляем файлы
$deletedFiles = 0;
foreach ($files as $p) {
  $filename = basename(str_replace('\\','/',$p));
  $diskPath = __DIR__ . '/../uploads/' . $filename;
  if (is_file($diskPath)) {
    @unlink($diskPath);
    $deletedFiles++;
  }
}

echo json_encode(['success'=>true,'deleted_ids'=>count($idsInt),'deleted_files'=>$deletedFiles], JSON_UNESCAPED_UNICODE);
