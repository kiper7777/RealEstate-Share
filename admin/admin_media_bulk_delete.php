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
if (!is_array($ids) || count($ids)===0) {
  echo json_encode(['success'=>false,'message'=>'No ids'], JSON_UNESCAPED_UNICODE);
  exit;
}

$ids = array_values(array_filter(array_map('intval', $ids), fn($x)=>$x>0));
if (count($ids)===0) {
  echo json_encode(['success'=>false,'message'=>'Bad ids'], JSON_UNESCAPED_UNICODE);
  exit;
}

$idList = implode(',', $ids);

$res = mysqli_query($conn, "SELECT id, file_path FROM property_media WHERE id IN ($idList)");
$rows = [];
while ($res && ($r = mysqli_fetch_assoc($res))) $rows[] = $r;

mysqli_query($conn, "DELETE FROM property_media WHERE id IN ($idList)");

$deletedFiles = 0;
foreach ($rows as $r) {
  $filename = basename(str_replace('\\','/',$r['file_path']));
  $path = __DIR__ . '/../uploads/' . $filename;
  if (is_file($path)) { @unlink($path); $deletedFiles++; }
}

echo json_encode(['success'=>true,'deleted'=>count($rows),'files_deleted'=>$deletedFiles], JSON_UNESCAPED_UNICODE);
