<?php
$servername = 'localhost';
$username = 'root';       
$password = '';           
$database   = 'realestate_share';

$conn = mysqli_connect($servername, $username, $password, $database);

if (!$conn) {
    die('Ошибка подключения к БД: ' . mysqli_connect_error());
}

mysqli_set_charset($conn, 'utf8mb4');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

?>
