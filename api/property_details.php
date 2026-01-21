<?php
require_once __DIR__ . '/../project/db.php';

header('Content-Type: application/json; charset=utf-8');

$propertyId = isset($_GET['property_id']) ? (int)$_GET['property_id'] : 0;
if ($propertyId <= 0) {
  echo json_encode(['success'=>false,'message'=>'Bad property_id'], JSON_UNESCAPED_UNICODE);
  exit;
}

$propRes = mysqli_query($conn, "SELECT id, name, location, type, region, status,
  price, min_ticket, max_partners, rent_per_year, yield_percent, payback_years, risk, description
  FROM properties WHERE id=$propertyId LIMIT 1");
if (!$propRes || mysqli_num_rows($propRes) === 0) {
  echo json_encode(['success'=>false,'message'=>'Not found'], JSON_UNESCAPED_UNICODE);
  exit;
}
$p = mysqli_fetch_assoc($propRes);

$price = (float)$p['price'];
$rent = (float)$p['rent_per_year'];
$yield = (float)$p['yield_percent'];

// ожидаемый доход €/год: если rent_per_year задан — берём его, иначе считаем по yield
$expectedIncome = $rent > 0 ? $rent : ($price > 0 ? $price * $yield / 100.0 : 0);

$script = $_SERVER['SCRIPT_NAME'] ?? '/api/property_details.php';
$base = preg_replace('~/api/.*$~', '', $script);
$base = rtrim($base, '/');

$media = [];
$resM = mysqli_query($conn, "SELECT id, file_path, caption, sort_order
                            FROM property_media
                            WHERE property_id=$propertyId
                            ORDER BY sort_order ASC, id DESC");
while ($resM && ($m = mysqli_fetch_assoc($resM))) {
  $file = basename(str_replace('\\','/',$m['file_path'] ?? ''));
  $url = $file ? (($base === '' ? '' : $base) . '/uploads/' . $file) : '';
  $media[] = [
    'id' => (int)$m['id'],
    'url' => $url,
    'caption' => $m['caption'] ?? '',
    'sort_order' => (int)$m['sort_order'],
  ];
}

echo json_encode([
  'success' => true,
  'property' => [
    'id' => (int)$p['id'],
    'name' => $p['name'],
    'location' => $p['location'],
    'type' => $p['type'],
    'region' => $p['region'],
    'status' => $p['status'],
    'price' => (float)$p['price'],
    'min_ticket' => (float)$p['min_ticket'],
    'max_partners' => (int)$p['max_partners'],
    'rent_per_year' => (float)$p['rent_per_year'],
    'yield_percent' => (float)$p['yield_percent'],
    'payback_years' => (float)$p['payback_years'],
    'risk' => $p['risk'],
    'description' => $p['description'],
    'expected_income_year' => $expectedIncome,
  ],
  'media' => $media
], JSON_UNESCAPED_UNICODE);
