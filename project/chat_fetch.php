<?php
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
  echo json_encode(['success'=>false,'message'=>'Unauthorized'], JSON_UNESCAPED_UNICODE);
  exit;
}

$userId = (int)$_SESSION['user_id'];
$participationId = isset($_GET['participation_id']) ? (int)$_GET['participation_id'] : 0;

if ($participationId <= 0) {
  echo json_encode(['success'=>false,'message'=>'Bad participation_id'], JSON_UNESCAPED_UNICODE);
  exit;
}

// проверяем, что заявка принадлежит пользователю
$chk = mysqli_query($conn, "SELECT id FROM participations WHERE id=$participationId AND user_id=$userId LIMIT 1");
if (!$chk || mysqli_num_rows($chk) === 0) {
  echo json_encode(['success'=>false,'message'=>'Not your participation'], JSON_UNESCAPED_UNICODE);
  exit;
}

$msgs = [];
$res = mysqli_query($conn, "SELECT id, sender_role, message_text, created_at
                            FROM messages
                            WHERE user_id=$userId AND participation_id=$participationId
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
