<?php
require_once 'csrf.php';
if (!csrf_validate($_POST['csrf_token'] ?? null)) {
    die('CSRF token invalid. <a href="index.php">Вернуться</a>');
}


require_once 'db.php';

$action = $_POST['action'] ?? '';

if ($action === 'register') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (mb_strlen($name) < 3 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($password) < 6) {
        // В реальном проекте — красивое отображение ошибок
        die('Некорректные данные регистрации. <a href="index.php">Вернуться</a>');
    }

    // Проверка, что email свободен
    $emailEsc = mysqli_real_escape_string($conn, $email);
    $res = mysqli_query($conn, "SELECT id FROM users WHERE email='$emailEsc' LIMIT 1");
    if ($res && mysqli_num_rows($res) > 0) {
        die('Пользователь с таким e-mail уже зарегистрирован. <a href="index.php">Вернуться</a>');
    }

    $nameEsc = mysqli_real_escape_string($conn, $name);
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $hashEsc = mysqli_real_escape_string($conn, $hash);

    $sql = "INSERT INTO users (name, email, password_hash)
            VALUES ('$nameEsc', '$emailEsc', '$hashEsc')";
    if (!mysqli_query($conn, $sql)) {
        die('Ошибка регистрации: ' . mysqli_error($conn));
    }

    $userId = mysqli_insert_id($conn);
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_name'] = $name;
    $_SESSION['user_email'] = $email;
    $_SESSION['is_admin'] = 0;


    header('Location: index.php');
    exit;
}

if ($action === 'login') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        die('Некорректные данные авторизации. <a href="index.php">Вернуться</a>');
    }

    $emailEsc = mysqli_real_escape_string($conn, $email);
    $sql = "SELECT * FROM users WHERE email='$emailEsc' LIMIT 1";
    $res = mysqli_query($conn, $sql);
    if (!$res || mysqli_num_rows($res) === 0) {
        die('Пользователь не найден. <a href="index.php">Вернуться</a>');
    }
    $user = mysqli_fetch_assoc($res);

    if (!password_verify($password, $user['password_hash'])) {
        die('Неверный пароль. <a href="index.php">Вернуться</a>');
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['is_admin'] = !empty($user['is_admin']) ? 1 : 0;


    header('Location: index.php');
    exit;
}

header('Location: index.php');
exit;
