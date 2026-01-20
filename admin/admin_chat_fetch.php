<?php
require_once __DIR__ . '/../project/db.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
  echo json_encode(['success'=>false,'message'=>'Forbidden'], JSON_UNESCAPED_UNICODE);
  exit;
}

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$participationId = isset($_GET['participation_id']) ? (int)$_GET['participation_id'] : 0;

if ($userId <= 0) {
  echo json_encode(['success'=>false,'message'=>'Bad user_id'], JSON_UNESCAPED_UNICODE);
  exit;
}

$where = "user_id=$userId";
if ($participationId > 0) {
  $where .= " AND participation_id=$participationId";
}

$msgs = [];
$res = mysqli_query($conn, "SELECT id, sender_role, message_text, created_at
                            FROM messages
                            WHERE $where
                            ORDER BY id ASC
                            LIMIT 400");
while ($res && ($m = mysqli_fetch_assoc($res))) {
  $msgs[] = [
    'id' => (int)$m['id'],
    'sender_role' => $m['sender_role'],
    'message_text' => $m['message_text'],
    'created_at' => $m['created_at'],
  ];
}

echo json_encode(['success'=>true,'messages'=>$msgs], JSON_UNESCAPED_UNICODE);
