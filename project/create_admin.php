<?php
require_once 'db.php';

$name = 'Admin';
$email = '7boyss@ukr.net';
$password = 'Admin123!';
$hash = password_hash($password, PASSWORD_DEFAULT);

// Проверяем, есть ли уже пользователь
$emailEsc = mysqli_real_escape_string($conn, $email);
$res = mysqli_query($conn, "SELECT id FROM users WHERE email='$emailEsc' LIMIT 1");

if ($res && mysqli_num_rows($res) > 0) {
    $sql = "UPDATE users
            SET name='" . mysqli_real_escape_string($conn, $name) . "',
                password_hash='" . mysqli_real_escape_string($conn, $hash) . "',
                is_admin=1
            WHERE email='$emailEsc'
            LIMIT 1";

    if (mysqli_query($conn, $sql)) {
        echo "Админ обновлён успешно.<br>";
        echo "Email: $email<br>";
        echo "Пароль: $password<br>";
    } else {
        echo "Ошибка обновления админа: " . mysqli_error($conn);
    }
} else {
    $sql = "INSERT INTO users (name, email, password_hash, is_admin)
            VALUES (
                '" . mysqli_real_escape_string($conn, $name) . "',
                '$emailEsc',
                '" . mysqli_real_escape_string($conn, $hash) . "',
                1
            )";

    if (mysqli_query($conn, $sql)) {
        echo "Админ создан успешно.<br>";
        echo "Email: $email<br>";
        echo "Пароль: $password<br>";
    } else {
        echo "Ошибка создания админа: " . mysqli_error($conn);
    }
}
?>