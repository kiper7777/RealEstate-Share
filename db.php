<?php
$host = 'localhost';
$user = 'root';       
$pass = '';           
$db   = 'realestate_share';

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die('Ошибка подключения к БД: ' . mysqli_connect_error());
}

mysqli_set_charset($conn, 'utf8mb4');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
