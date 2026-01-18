<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../csrf.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
  echo json_encode(['success'=>false,'message'=>'Forbidden'], JSON_UNESCAPED_UNICODE);
  exit;
}

if (!csrf_validate($_POST['csrf_token'] ?? null)) {
  echo json_encode(['success'=>false,'message'=>'CSRF invalid'], JSON_UNESCAPED_UNICODE);
  exit;
}

$propertyId = isset($_POST['property_id']) ? (int)$_POST['property_id'] : 0;
$caption = trim($_POST['caption'] ?? '');
$sort = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;

if ($propertyId <= 0 || empty($_FILES['image'])) {
  echo json_encode(['success'=>false,'message'=>'Bad input'], JSON_UNESCAPED_UNICODE);
  exit;
}

$allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
$tmp = $_FILES['image']['tmp_name'];
$mime = mime_content_type($tmp);

if (!isset($allowed[$mime])) {
  echo json_encode(['success'=>false,'message'=>'Unsupported format'], JSON_UNESCAPED_UNICODE);
  exit;
}

if ($_FILES['image']['size'] > 6 * 1024 * 1024) {
  echo json_encode(['success'=>false,'message'=>'File too large'], JSON_UNESCAPED_UNICODE);
  exit;
}

$ext = $allowed[$mime];
$dir = __DIR__ . '/../uploads';
if (!is_dir($dir)) mkdir($dir, 0755, true);

$filename = 'p'.$propertyId.'_'.time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
$destPath = $dir . '/' . $filename;

if (!move_uploaded_file($tmp, $destPath)) {
  echo json_encode(['success'=>false,'message'=>'Upload failed'], JSON_UNESCAPED_UNICODE);
  exit;
}

$relPath = 'uploads/' . $filename;
$relEsc = mysqli_real_escape_string($conn, $relPath);
$capEsc = mysqli_real_escape_string($conn, $caption);

$sql = "INSERT INTO property_media (property_id, file_path, caption, sort_order)
        VALUES ($propertyId, '$relEsc', '$capEsc', $sort)";
if (!mysqli_query($conn, $sql)) {
  echo js
::contentReference[oaicite:0]{index=0}
on_encode(['success'=>false,'message'=>mysqli_error($conn)], JSON_UNESCAPED_UNICODE);
  exit;
}

echo json_encode(['success'=>true,'media_id'=>mysqli_insert_id($conn),'file_path'=>$relPath], JSON_UNESCAPED_UNICODE);
