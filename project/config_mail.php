<?php
// project/config_mail.php

// ВКЛ/ВЫКЛ уведомлений
define('MAIL_NOTIFICATIONS_ENABLED', true);

// SMTP настройки (заполни своими)
define('SMTP_HOST', 'smtp.yourmail.com');
define('SMTP_PORT', 587);              // 587 (TLS) или 465 (SSL)
define('SMTP_SECURE', 'tls');          // 'tls' или 'ssl'
define('SMTP_USER', 'no-reply@yourdomain.com');
define('SMTP_PASS', 'YOUR_PASSWORD');

// From
define('MAIL_FROM', 'no-reply@yourdomain.com');
define('MAIL_FROM_NAME', 'RealEstate Share');

// Админ-уведомления (если пусто — берём email админа из users(is_admin=1))
define('ADMIN_NOTIFY_EMAIL', '');

// Лог (по желанию)
define('MAIL_LOG_FILE', __DIR__ . '/logs/mail.log');

function mail_log(string $s): void {
  if (!MAIL_LOG_FILE) return;
  @file_put_contents(MAIL_LOG_FILE, '['.date('Y-m-d H:i:s').'] '.$s.PHP_EOL, FILE_APPEND);
}

/**
 * Надёжная отправка через PHPMailer SMTP.
 * Автоподключение:
 *  - если composer в корне: ../vendor/autoload.php
 *  - если composer в project: ./vendor/autoload.php
 *  - если ручная установка: ./lib/PHPMailer/src/*.php
 */
function send_email_notification(string $to, string $subject, string $body): bool {
  if (!MAIL_NOTIFICATIONS_ENABLED) return true;
  if (!$to) return false;

  try {
    // Autoload
    $autoload1 = __DIR__ . '/../vendor/autoload.php';
    $autoload2 = __DIR__ . '/vendor/autoload.php';

    if (file_exists($autoload1)) {
      require_once $autoload1;
    } elseif (file_exists($autoload2)) {
      require_once $autoload2;
    } else {
      $base = __DIR__ . '/lib/PHPMailer/src/';
      require_once $base . 'Exception.php';
      require_once $base . 'PHPMailer.php';
      require_once $base . 'SMTP.php';
    }

    // Namespaces
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->Port       = SMTP_PORT;
    $mail->SMTPSecure = SMTP_SECURE;

    // Sender
    $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    $mail->addAddress($to);

    $mail->Subject = $subject;
    $mail->Body    = $body;
    $mail->isHTML(false);

    $mail->send();
    return true;
  } catch (\Throwable $e) {
    mail_log("Mail error to=$to subject=$subject :: " . $e->getMessage());
    return false;
  }
}
