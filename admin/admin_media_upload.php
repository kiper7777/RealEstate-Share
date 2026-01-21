<?php
require_once __DIR__ . '/../project/db.php';
require_once __DIR__ . '/../project/csrf.php';

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
$sortOrder  = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
$caption    = trim($_POST['caption'] ?? '');

if ($propertyId <= 0) {
  echo json_encode(['success'=>false,'message'=>'Bad property_id'], JSON_UNESCAPED_UNICODE);
  exit;
}

if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
  echo json_encode(['success'=>false,'message'=>'Upload error'], JSON_UNESCAPED_UNICODE);
  exit;
}

$allowed = [
  'image/jpeg' => 'jpg',
  'image/png'  => 'png',
  'image/webp' => 'webp',
];

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $_FILES['image']['tmp_name']);
finfo_close($finfo);

if (!isset($allowed[$mime])) {
  echo json_encode(['success'=>false,'message'=>'Формат не поддерживается. Только JPG/PNG/WEBP'], JSON_UNESCAPED_UNICODE);
  exit;
}

$ext = $allowed[$mime];
$uploadsDir = realpath(__DIR__ . '/../uploads');
if (!$uploadsDir) {
  // если папки нет — создадим
  $uploadsDir = __DIR__ . '/../uploads';
  @mkdir($uploadsDir, 0775, true);
}
$uploadsDir = realpath($uploadsDir) ?: (__DIR__ . '/../uploads');

$filename = 'prop_' . $propertyId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$target = rtrim($uploadsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

if (!move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
  echo json_encode(['success'=>false,'message'=>'Не удалось сохранить файл'], JSON_UNESCAPED_UNICODE);
  exit;
}

$filePath = 'uploads/' . $filename; // относительный путь
$filePathEsc = mysqli_real_escape_string($conn, $filePath);
$captionEsc = mysqli_real_escape_string($conn, $caption);

$sql = "INSERT INTO property_media (property_id, file_path, caption, sort_order)
        VALUES ($propertyId, '$filePathEsc', '$captionEsc', $sortOrder)";
if (!mysqli_query($conn, $sql)) {
  echo json_encode(['success'=>false,'message'=>mysqli_error($conn)], JSON_UNESCAPED_UNICODE);
  exit;
}

echo json_encode(['success'=>true,'media_id'=>mysqli_insert_id($conn)], JSON_UNESCAPED_UNICODE);
