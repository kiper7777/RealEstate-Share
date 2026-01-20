<?php
require_once __DIR__ . '/../project/db.php';
require_once __DIR__ . '/../project/csrf.php';

header('Content-Type: application/json; charset=utf-8');

function upload_error_message(int $code): string {
  $map = [
    UPLOAD_ERR_OK => 'OK',
    UPLOAD_ERR_INI_SIZE => 'Файл превышает upload_max_filesize в php.ini',
    UPLOAD_ERR_FORM_SIZE => 'Файл превышает MAX_FILE_SIZE в форме',
    UPLOAD_ERR_PARTIAL => 'Файл загружен частично',
    UPLOAD_ERR_NO_FILE => 'Файл не был загружен',
    UPLOAD_ERR_NO_TMP_DIR => 'Нет временной директории на сервере',
    UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл на диск',
    UPLOAD_ERR_EXTENSION => 'Загрузка остановлена расширением PHP',
  ];
  return $map[$code] ?? 'Неизвестная ошибка загрузки';
}

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

if ($propertyId <= 0) {
  echo json_encode(['success'=>false,'message'=>'Bad property_id'], JSON_UNESCAPED_UNICODE);
  exit;
}

if (empty($_FILES['image'])) {
  echo json_encode(['success'=>false,'message'=>'Файл не выбран (image)'], JSON_UNESCAPED_UNICODE);
  exit;
}

$f = $_FILES['image'];
if (!isset($f['error']) || $f['error'] !== UPLOAD_ERR_OK) {
  echo json_encode([
    'success'=>false,
    'message'=>upload_error_message((int)($f['error'] ?? -1)),
    'debug'=>[
      'upload_max_filesize'=>ini_get('upload_max_filesize'),
      'post_max_size'=>ini_get('post_max_size')
    ]
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

$tmp = $f['tmp_name'];
$info = @getimagesize($tmp);
if ($info === false || empty($info['mime'])) {
  echo json_encode(['success'=>false,'message'=>'Файл не распознан как изображение'], JSON_UNESCAPED_UNICODE);
  exit;
}

$mime = $info['mime'];
$extMap = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
if (!isset($extMap[$mime])) {
  echo json_encode(['success'=>false,'message'=>"Неподдерживаемый формат: $mime"], JSON_UNESCAPED_UNICODE);
  exit;
}

$uploadsDir = __DIR__ . '/../uploads';
if (!is_dir($uploadsDir) && !mkdir($uploadsDir, 0755, true)) {
  echo json_encode(['success'=>false,'message'=>'Не удалось создать uploads'], JSON_UNESCAPED_UNICODE);
  exit;
}
if (!is_writable($uploadsDir)) {
  echo json_encode(['success'=>false,'message'=>'uploads не доступна для записи (права)'], JSON_UNESCAPED_UNICODE);
  exit;
}

$ext = $extMap[$mime];
$filename = 'p'.$propertyId.'_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$ext;
$dest = $uploadsDir . '/' . $filename;

if (!move_uploaded_file($tmp, $dest)) {
  echo json_encode(['success'=>false,'message'=>'move_uploaded_file() failed'], JSON_UNESCAPED_UNICODE);
  exit;
}

$filenameEsc = mysqli_real_escape_string($conn, $filename);
$capEsc = mysqli_real_escape_string($conn, $caption);

$sql = "INSERT INTO property_media (property_id, file_path, caption, sort_order)
        VALUES ($propertyId, '$filenameEsc', '$capEsc', $sort)";
if (!mysqli_query($conn, $sql)) {
  echo json_encode(['success'=>false,'message'=>mysqli_error($conn)], JSON_UNESCAPED_UNICODE);
  exit;
}

echo json_encode(['success'=>true,'media_id'=>mysqli_insert_id($conn)], JSON_UNESCAPED_UNICODE);
