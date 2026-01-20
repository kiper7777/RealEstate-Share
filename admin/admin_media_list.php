<?php
require_once __DIR__ . '/../project/db.php';
require_once __DIR__ . '/../project/csrf.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
  echo json_encode(['success'=>false,'message'=>'Forbidden'], JSON_UNESCAPED_UNICODE);
  exit;
}

$propertyId = isset($_GET['property_id']) ? (int)$_GET['property_id'] : 0;
if ($propertyId <= 0) {
  echo json_encode(['success'=>true,'media'=>[]], JSON_UNESCAPED_UNICODE);
  exit;
}

// вычисляем base (если проект в подпапке)
$script = $_SERVER['SCRIPT_NAME'] ?? '/admin/admin_media_list.php';
$base = preg_replace('~/admin/.*$~', '', $script);
$base = rtrim($base, '/');

function media_url(string $base, string $stored): string {
  $filename = basename(str_replace('\\','/',$stored));
  return ($base === '' ? '' : $base) . '/uploads/' . $filename;
}

$media = [];
$res = mysqli_query($conn, "SELECT id, file_path, caption, sort_order, created_at
                            FROM property_media
                            WHERE property_id=$propertyId
                            ORDER BY sort_order ASC, id DESC");
while ($res && ($m = mysqli_fetch_assoc($res))) {
  $media[] = [
    'id' => (int)$m['id'],
    'url' => media_url($base, $m['file_path'] ?? ''),
    'file_name' => basename($m['file_path'] ?? ''),
    'caption' => $m['caption'],
    'sort_order' => (int)$m['sort_order'],
    'created_at' => $m['created_at'],
  ];
}

echo json_encode(['success'=>true,'media'=>$media], JSON_UNESCAPED_UNICODE);
