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

$fields = ['name','location','type','region','status','price','min_ticket','max_partners','rent_per_year','yield_percent','payback_years','risk','description'];
foreach ($fields as $f) {
  if (!isset($data[$f]) || $data[$f]==='') {
    echo json_encode(['success'=>false,'message'=>"Missing field: $f"], JSON_UNESCAPED_UNICODE);
    exit;
  }
}

$id = isset($data['id']) && $data['id'] ? (int)$data['id'] : 0;

$name = mysqli_real_escape_string($conn, $data['name']);
$location = mysqli_real_escape_string($conn, $data['location']);
$type = mysqli_real_escape_string($conn, $data['type']);
$region = mysqli_real_escape_string($conn, $data['region']);
$status = mysqli_real_escape_string($conn, $data['status']);
$risk = mysqli_real_escape_string($conn, $data['risk']);
$desc = mysqli_real_escape_string($conn, $data['description']);

$price = (float)$data['price'];
$min_ticket = (float)$data['min_ticket'];
$max_partners = (int)$data['max_partners'];
$rent_per_year = (float)$data['rent_per_year'];
$yield = (float)$data['yield_percent'];
$payback = (float)$data['payback_years'];

if ($id > 0) {
  $sql = "UPDATE properties SET
            name='$name',
            location='$location',
            type='$type',
            region='$region',
            status='$status',
            price=$price,
            min_ticket=$min_ticket,
            max_partners=$max_partners,
            rent_per_year=$rent_per_year,
            yield_percent=$yield,
            payback_years=$payback,
            risk='$risk',
            description='$desc'
          WHERE id=$id";
} else {
  $sql = "INSERT INTO properties
          (name, location, region, type, status, price, min_ticket, max_partners, rent_per_year, yield_percent, payback_years, risk, description)
          VALUES
          ('$name','$location','$region','$type','$status',$price,$min_ticket,$max_partners,$rent_per_year,$yield,$payback,'$risk','$desc')";
}

if (!mysqli_query($conn, $sql)) {
  echo json_encode(['success'=>false,'message'=>mysqli_error($conn)], JSON_UNESCAPED_UNICODE);
  exit;
}

echo json_encode(['success'=>true,'id'=> $id>0 ? $id : mysqli_insert_id($conn)], JSON_UNESCAPED_UNICODE);
