<?php
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
  echo json_encode(['user' => null], JSON_UNESCAPED_UNICODE);
  exit;
}

echo json_encode([
  'user' => [
    'id' => (int)$_SESSION['user_id'],
    'name' => $_SESSION['user_name'],
    'email' => $_SESSION['user_email'],
    'is_admin' => !empty($_SESSION['is_admin']) ? 1 : 0
  ]
], JSON_UNESCAPED_UNICODE);
