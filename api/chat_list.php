<?php
require_once __DIR__ . '/../project/db.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
  echo json_encode(['success'=>false,'message'=>'Not authenticated'], JSON_UNESCAPED_UNICODE);
  exit;
}
$userId = (int)$_SESSION['user_id'];

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$propertyId = (int)($data['property_id'] ?? 0);
if ($propertyId<=0) {
  echo json_encode(['success'=>false,'message'=>'Bad property_id'], JSON_UNESCAPED_UNICODE);
  exit;
}

$res = mysqli_query($conn, "SELECT id, sender, message, created_at
                            FROM messages
                            WHERE property_id=$propertyId AND user_id=$userId AND is_deleted=0
                            ORDER BY id ASC
                            LIMIT 200");
$msgs = [];
while ($res && ($m = mysqli_fetch_assoc($res))) {
  $msgs[] = [
    'id' => (int)$m['id'],
    'sender' => $m['sender'],
    'message' => $m['message'],
    'created_at' => date('Y-m-d H:i', strtotime($m['created_at']))
  ];
}

echo json_encode(['success'=>true,'messages'=>$msgs], JSON_UNESCAPED_UNICODE);
