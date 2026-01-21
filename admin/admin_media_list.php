<?php
require_once __DIR__ . '/../project/db.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
  echo json_encode(['success'=>false,'message'=>'Forbidden'], JSON_UNESCAPED_UNICODE);
  exit;
}

$propertyId = isset($_GET['property_id']) ? (int)$_GET['property_id'] : 0;
if ($propertyId <= 0) {
  echo json_encode(['success'=>false,'message'=>'Bad property_id'], JSON_UNESCAPED_UNICODE);
  exit;
}

$script = $_SERVER['SCRIPT_NAME'] ?? '/admin/admin_media_list.php';
$base = preg_replace('~/admin/.*$~', '', $script);
$base = rtrim($base, '/');

$media = [];
$res = mysqli_query($conn, "SELECT id, property_id, file_path, caption, sort_order
                            FROM property_media
                            WHERE property_id=$propertyId
                            ORDER BY sort_order ASC, id DESC");
while ($res && ($m = mysqli_fetch_assoc($res))) {
  $file = basename(str_replace('\\','/',$m['file_path'] ?? ''));
  $url = $file ? (($base === '' ? '' : $base) . '/uploads/' . $file) : '';
  $media[] = [
    'id' => (int)$m['id'],
    'property_id' => (int)$m['property_id'],
    'file_name' => $file,
    'file_path' => $m['file_path'],
    'url' => $url,
    'caption' => $m['caption'] ?? '',
    'sort_order' => (int)$m['sort_order'],
  ];
}

echo json_encode(['success'=>true,'media'=>$media], JSON_UNESCAPED_UNICODE);
