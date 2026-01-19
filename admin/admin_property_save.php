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

$need = ['name','location','type','region','status','price','min_ticket','max_partners','rent_per_year','yield_percent','payback_years','risk','description'];
foreach ($need as $k) {
    if (!isset($data[$k]) || $data[$k] === '') {
        echo json_encode(['success'=>false,'message'=>"Missing: $k"], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$id = !empty($data['id']) ? (int)$data['id'] : 0;

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
              payback
