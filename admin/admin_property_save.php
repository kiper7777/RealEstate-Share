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

$id = isset($data['id']) && $data['id'] !== null ? (int)$data['id'] : null;

$name = trim($data['name'] ?? '');
$location = trim($data['location'] ?? '');
$type = trim($data['type'] ?? 'residential');
$region = trim($data['region'] ?? 'europe');
$status = trim($data['status'] ?? 'funding');

$price = (float)($data['price'] ?? 0);
$min_ticket = (float)($data['min_ticket'] ?? 0);
$max_partners = (int)($data['max_partners'] ?? 0);
$rent_per_year = (float)($data['rent_per_year'] ?? 0);
$yield_percent = (float)($data['yield_percent'] ?? 0);
$payback_years = (float)($data['payback_years'] ?? 0);
$risk = trim($data['risk'] ?? '');
$description = trim($data['description'] ?? '');

if (mb_strlen($name) < 3 || mb_strlen($location) < 3) {
  echo json_encode(['success'=>false,'message'=>'Заполните название и локацию'], JSON_UNESCAPED_UNICODE);
  exit;
}

$nameEsc = mysqli_real_escape_string($conn, $name);
$locEsc = mysqli_real_escape_string($conn, $location);
$typeEsc = mysqli_real_escape_string($conn, $type);
$regionEsc = mysqli_real_escape_string($conn, $region);
$statusEsc = mysqli_real_escape_string($conn, $status);
$riskEsc = mysqli_real_escape_string($conn, $risk);
$descEsc = mysqli_real_escape_string($conn, $description);

if ($id) {
  $sql = "UPDATE properties SET
    name='$nameEsc',
    location='$locEsc',
    type='$typeEsc',
    region='$regionEsc',
    status='$statusEsc',
    price=$price,
    min_ticket=$min_ticket,
    max_partners=$max_partners,
    rent_per_year=$rent_per_year,
    yield_percent=$yield_percent,
    payback_years=$payback_years,
    risk='$riskEsc',
    description='$descEsc'
    WHERE id=$id LIMIT 1";
  if (!mysqli_query($conn, $sql)) {
    echo json_encode(['success'=>false,'message'=>mysqli_error($conn)], JSON_UNESCAPED_UNICODE);
    exit;
  }
  echo json_encode(['success'=>true,'id'=>$id], JSON_UNESCAPED_UNICODE);
  exit;
}

// INSERT
$sql = "INSERT INTO properties
  (name, location, type, region, status, price, min_ticket, max_partners, rent_per_year, yield_percent, payback_years, risk, description)
  VALUES
  ('$nameEsc','$locEsc','$typeEsc','$regionEsc','$statusEsc',$price,$min_ticket,$max_partners,$rent_per_year,$yield_percent,$payback_years,'$riskEsc','$descEsc')";
if (!mysqli_query($conn, $sql)) {
  echo json_encode(['success'=>false,'message'=>mysqli_error($conn)], JSON_UNESCAPED_UNICODE);
  exit;
}

echo json_encode(['success'=>true,'id'=>mysqli_insert_id($conn)], JSON_UNESCAPED_UNICODE);
