<?php
require_once __DIR__ . '/../project/db.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * Если проект лежит в подпапке (например /realestate/),
 * то API находится /realestate/api/properties.php
 * Нам нужно получить базовый префикс "/realestate" для ссылок на /uploads.
 */
$script = $_SERVER['SCRIPT_NAME'] ?? '/api/properties.php'; // например: /realestate/api/properties.php
$base = preg_replace('~/api/.*$~', '', $script);            // станет: /realestate
$base = rtrim($base, '/');                                  // /realestate или ''

function media_url(string $base, string $stored): string {
  $stored = trim($stored);
  if ($stored === '') return '';
  $filename = basename(str_replace('\\','/',$stored));
  return ($base === '' ? '' : $base) . '/uploads/' . $filename;
}

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

  $media = [];
  $resM = mysqli_query($conn, "SELECT id, file_path, caption, sort_order
                               FROM property_media
                               WHERE property_id=$id
                               ORDER BY sort_order ASC, id ASC");
  while ($resM && ($m = mysqli_fetch_assoc($resM))) {
    $media[] = [
      'id' => (int)$m['id'],
      'url' => media_url($base, $m['file_path'] ?? ''),
      'caption' => $m['caption'] ?? '',
      'sort_order' => (int)$m['sort_order'],
    ];
  }

  // Главное фото для карточек
  $cover_url = '';
  if (!empty($media)) {
    $cover_url = $media[0]['url'] ?? '';
  }

  $price = (float)$row['price'];
  $rentPerYear = (float)$row['rent_per_year'];
  $yieldPercent = (float)$row['yield_percent'];

  // ожидаемый доход €/год: если rent_per_year задан — берём его, иначе считаем по yield%
  $expectedIncomeYear = $rentPerYear > 0 ? $rentPerYear : ($price > 0 ? ($price * $yieldPercent / 100.0) : 0);

  $props[] = [
    'id' => $id,
    'name' => $row['name'],
    'location' => $row['location'],
    'region' => $row['region'],
    'type' => $row['type'],
    'status' => $row['status'] ?? 'funding',
    'price' => $price,
    'min_ticket' => (float)$row['min_ticket'],
    'max_partners' => (int)$row['max_partners'],
    'rent_per_year' => $rentPerYear,
    'yield_percent' => $yieldPercent,
    'payback_years' => (float)$row['payback_years'],
    'risk' => $row['risk'],
    'description' => $row['description'],
    'invested' => (float)$row['invested'],
    'participants' => (int)$row['participants'],
    'expected_income_year' => (float)$expectedIncomeYear,
    'cover_url' => $cover_url,
    'media' => $media
  ];
}

echo json_encode(['properties' => $props], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
