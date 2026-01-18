<?php
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

$sql = "SELECT 
          p.*,
          COALESCE(SUM(CASE WHEN part.status IN ('pending','approved') THEN part.amount ELSE 0 END), 0) AS invested,
          COUNT(CASE WHEN part.status IN ('pending','approved') THEN part.id ELSE NULL END) AS participants
        FROM properties p
        LEFT JOIN participations part ON part.property_id = p.id
        GROUP BY p.id
        ORDER BY p.id ASC";
$res = mysqli_query($conn, $sql);

$props = [];
while ($res && ($row = mysqli_fetch_assoc($res))) {
  $id = (int)$row['id'];

  // медиа
  $media = [];
  $resM = mysqli_query($conn, "SELECT id, file_path, caption, sort_order 
                               FROM property_media WHERE property_id=$id
                               ORDER BY sort_order ASC, id ASC");
  while ($resM && ($m = mysqli_fetch_assoc($resM))) {
    $media[] = [
      'id' => (int)$m['id'],
      'file_path' => $m['file_path'],
      'caption' => $m['caption'],
      'sort_order' => (int)$m['sort_order'],
    ];
  }

  $props[] = [
    'id' => $id,
    'name' => $row['name'],
    'location' => $row['location'],
    'region' => $row['region'],
    'type' => $row['type'],
    'status' => $row['status'] ?? 'funding',
    'price' => (float)$row['price'],
    'min_ticket' => (float)$row['min_ticket'],
    'max_partners' => (int)$row['max_partners'],
    'rent_per_year' => (float)$row['rent_per_year'],
    'yield_percent' => (float)$row['yield_percent'],
    'payback_years' => (float)$row['payback_years'],
    'risk' => $row['risk'],
    'description' => $row['description'],
    'invested' => (float)$row['invested'],
    'participants' => (int)$row['participants'],
    'media' => $media
  ];
}

echo json_encode(['properties' => $props], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
