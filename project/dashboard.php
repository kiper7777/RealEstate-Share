<?php
require_once 'db.php';

if (empty($_SESSION['user_id'])) {
  header('Location: index.php');
  exit;
}

$userId = (int)$_SESSION['user_id'];

/**
 * 1) Мои участия + данные объекта
 */
$sql = "SELECT 
          part.id AS participation_id,
          part.amount,
          part.status,
          part.share_percent,
          part.created_at,
          p.id AS property_id,
          p.name,
          p.location,
          p.price,
          p.status AS property_status,
          p.type AS property_type,
          p.region AS property_region,
          p.yield_percent,
          p.payback_years
        FROM participations part
        JOIN properties p ON p.id = part.property_id
        WHERE part.user_id = $userId
        ORDER BY part.created_at DESC";
$res = mysqli_query($conn, $sql);

$participations = [];
$totalInvested = 0.0;

while ($res && ($r = mysqli_fetch_assoc($res))) {
  $r['amount'] = (float)$r['amount'];
  $r['price']  = (float)$r['price'];
  $r['yield_percent'] = (float)$r['yield_percent'];
  $r['payback_years'] = (float)$r['payback_years'];
  $r['share_percent'] = $r['share_percent'] !== null ? (float)$r['share_percent'] : null;

  $totalInvested += $r['amount'];
  $participations[] = $r;
}

/**
 * 2) Выплаты (если таблица payouts существует)
 */
$payouts = [];
$totalPayouts = 0.0;

$hasPayouts = false;
$chk = mysqli_query($conn, "SHOW TABLES LIKE 'payouts'");
if ($chk && mysqli_num_rows($chk) > 0) {
  $hasPayouts = true;
}

if ($hasPayouts) {
  $sqlP = "SELECT 
            p.name AS property_name,
            pay.amount,
            pay.payout_date,
            pay.note
          FROM payouts pay
          JOIN properties p ON p.id = pay.property_id
          WHERE pay.user_id = $userId
          ORDER BY pay.payout_date DESC
          LIMIT 50";
  $resP = mysqli_query($conn, $sqlP);
  while ($resP && ($p = mysqli_fetch_assoc($resP))) {
    $p['amount'] = (float)$p['amount'];
    $totalPayouts += $p['amount'];
    $payouts[] = $p;
  }
}

/**
 * 3) Сообщения (чат) — если таблица messages существует
 * Пользователь может отправить сообщение администратору.
 */
$hasMessages = false;
$chk2 = mysqli_query($conn, "SHOW TABLES LIKE 'messages'");
if ($chk2 && mysqli_num_rows($chk2) > 0) {
  $hasMessages = true;
}

$chatError = '';
if ($hasMessages && ($_SERVER['REQUEST_METHOD'] === 'POST') && (($_POST['action'] ?? '') === 'send_message')) {
  $text = trim($_POST['message'] ?? '');

  if ($text === '' || mb_strlen($text) < 1) {
    $chatError = 'Сообщение пустое.';
  } elseif (mb_strlen($text) > 2000) {
    $chatError = 'Сообщение слишком длинное (макс. 2000 символов).';
  } else {
    $textEsc = mysqli_real_escape_string($conn, $text);

    // Ищем любого админа (самого первого) — можно заменить на конкретного позже
    $resA = mysqli_query($conn, "SELECT id FROM users WHERE is_admin=1 ORDER BY id ASC LIMIT 1");
    $adminId = 0;
    if ($resA && mysqli_num_rows($resA) > 0) {
      $a = mysqli_fetch_assoc($resA);
      $adminId = (int)$a['id'];
    }

    if ($adminId <= 0) {
      $chatError = 'Администратор не найден (is_admin=1).';
    } else {
      $sqlIns = "INSERT INTO messages (user_id, admin_id, sender_role, message_text)
                 VALUES ($userId, $adminId, 'user', '$textEsc')";
      if (!mysqli_query($conn, $sqlIns)) {
        $chatError = 'Ошибка отправки: ' . mysqli_error($conn);
      } else {
        // чтобы избежать повторной отправки при F5
        header('Location: dashboard.php#chat');
        exit;
      }
    }
  }
}

// Загружаем чат
$messages = [];
if ($hasMessages) {
  $sqlM = "SELECT 
            id, sender_role, message_text, created_at
          FROM messages
          WHERE user_id = $userId
          ORDER BY id ASC
          LIMIT 200";
  $resM = mysqli_query($conn, $sqlM);
  while ($resM && ($m = mysqli_fetch_assoc($resM))) {
    $messages[] = $m;
  }
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function eur($n) { return '€' . number_format((float)$n, 0, ',', ' '); }

?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Личный кабинет – RealEstate Share</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    .dash-wrap{max-width:1120px;margin:24px auto;padding:0 20px 60px;}
    .dash-header{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;margin-bottom:14px;}
    .dash-title{font-size:20px;font-weight:600;margin:0;}
    .dash-sub{color:var(--text-muted);font-size:13px;margin-top:6px;max-width:760px;line-height:1.45;}
    .dash-actions{display:flex;gap:8px;flex-wrap:wrap;}
    .dash-cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:14px 0 18px;}
    @media (max-width:900px){.dash-cards{grid-template-columns:1fr;}}
    .dash-card{border-radius:16px;background:rgba(15,23,42,0.95);border:1px solid rgba(55,65,81,0.9);padding:12px;}
    .dash-kpi-label{font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.08em;}
    .dash-kpi-value{font-size:18px;font-weight:600;margin-top:6px;}
    .section-title{font-size:16px;margin:18px 0 10px;}
    .table{width:100%;border-collapse:separate;border-spacing:0 8px;}
    .tr{background:rgba(15,23,42,0.95);border:1px solid rgba(55,65,81,0.9);}
    .table td{padding:10px 10px;font-size:12px;color:var(--text-muted);vertical-align:top;}
    .table td strong{color:var(--text-main);}
    .pill{display:inline-flex;align-items:center;gap:6px;padding:4px 8px;border-radius:999px;font-size:11px;border:1px solid rgba(55,65,81,0.9);background:rgba(15,23,42,0.95);}
    .pill.pending{border-color:rgba(245,158,11,0.8);background:rgba(245,158,11,0.12);color:#fde68a;}
    .pill.approved{border-color:rgba(34,197,94,0.7);background:rgba(22,163,74,0.16);color:#bbf7d0;}
    .pill.rejected{border-color:rgba(239,68,68,0.7);background:rgba(239,68,68,0.14);color:#fecaca;}

    /* Chat */
    .chat-shell{border-radius:16px;background:rgba(15,23,42,0.95);border:1px solid rgba(55,65,81,0.9);padding:12px;}
    .chat-list{display:flex;flex-direction:column;gap:10px;max-height:320px;overflow:auto;padding-right:4px;}
    .msg{max-width:86%;border-radius:14px;padding:10px 10px;font-size:12px;line-height:1.45;border:1px solid rgba(55,65,81,.9);}
    .msg.user{margin-left:auto;background:rgba(79,70,229,0.16);border-color:rgba(79,70,229,0.45);color:#e0e7ff;}
    .msg.admin{margin-right:auto;background:rgba(15,23,42,0.75);color:rgba(226,232,240,0.9);}
    .msg-meta{font-size:10px;color:rgba(148,163,184,0.85);margin-top:6px;}
    .chat-form{display:flex;gap:8px;margin-top:12px;align-items:flex-end;flex-wrap:wrap;}
    .chat-form textarea{flex:1;min-height:72px;border-radius:14px;border:1px solid rgba(55,65,81,.9);background:rgba(2,6,23,0.35);color:var(--text-main);padding:10px 10px;outline:none;}
    .err{color:#fecaca;border:1px solid rgba(239,68,68,.55);background:rgba(239,68,68,.12);padding:8px 10px;border-radius:12px;font-size:12px;margin:10px 0;}
    .note{color:var(--text-muted);font-size:12px;line-height:1.45;}
  </style>
</head>
<body>
<div class="app-shell">
  <header>
    <div class="nav">
      <div class="logo">
        <div class="logo-mark">R</div>
        <div class="logo-text">
          <div class="logo-title">RealEstate Share</div>
          <div class="logo-subtitle">Личный кабинет партнёра</div>
        </div>
      </div>
      <div class="nav-actions">
        <a href="index.php#properties" class="nav-link">Объекты</a>
        <a href="index.php#partners" class="nav-link">Партнёрам</a>
        <span class="nav-user">Привет, <?= h($_SESSION['user_name']) ?></span>
        <div class="dash-actions">
          <a href="index.php#properties" class="btn btn-primary btn-sm">Выбрать объект</a>
          <?php if (!empty($_SESSION['is_admin'])): ?>
            <a href="../admin/index.php" class="btn btn-outline btn-sm">Админ-панель</a>
          <?php endif; ?>
          <a href="logout.php" class="btn btn-outline btn-sm">Выйти</a>
        </div>
      </div>
    </div>
  </header>

  <main class="dash-wrap">
    <div class="dash-header">
      <div>
        <h1 class="dash-title">Ваш кабинет</h1>
        <div class="dash-sub">
          Здесь ваши заявки на участие, статусы, выплаты и сообщения от администрации.
        </div>
      </div>
    </div>

    <div class="dash-cards">
      <div class="dash-card">
        <div class="dash-kpi-label">Сумма заявок (всего)</div>
        <div class="dash-kpi-value"><?= eur($totalInvested) ?></div>
      </div>
      <div class="dash-card">
        <div class="dash-kpi-label">Выплаты (получено)</div>
        <div class="dash-kpi-value"><?= eur($totalPayouts) ?></div>
      </div>
      <div class="dash-card">
        <div class="dash-kpi-label">Заявок</div>
        <div class="dash-kpi-value"><?= count($participations) ?></div>
      </div>
    </div>

    <h2 class="section-title">Мои участия</h2>

    <?php if (empty($participations)): ?>
      <div class="details-shell">
        <div class="details-description">Пока нет заявок. Перейдите в «Объекты» и выберите объект для участия.</div>
      </div>
    <?php else: ?>
      <table class="table">
        <tbody>
        <?php foreach ($participations as $r): ?>
          <tr class="tr">
            <td>
              <strong><?= h($r['name']) ?></strong><br>
              <?= h($r['location']) ?><br>
              <span style="font-size:11px;">Тип: <strong><?= h($r['property_type']) ?></strong> · Статус: <strong><?= h($r['property_status']) ?></strong></span>
            </td>
            <td>
              Сумма: <strong><?= eur($r['amount']) ?></strong><br>
              Доля: <strong><?= $r['share_percent'] !== null ? number_format($r['share_percent'], 2, ',', ' ') . '%' : '—' ?></strong><br>
              Доходность: <strong><?= number_format($r['yield_percent'], 2, ',', ' ') ?>%</strong><br>
              Окупаемость: <strong><?= number_format($r['payback_years'], 1, ',', ' ') ?> лет</strong>
            </td>
            <td>
              Статус:
              <span class="pill <?= h($r['status']) ?>">
                <?= $r['status']==='pending'?'На модерации':($r['status']==='approved'?'Подтверждено':'Отклонено') ?>
              </span><br>
              Дата: <strong><?= h(date('Y-m-d', strtotime($r['created_at']))) ?></strong>
            </td>
            <td>
              Стоимость объекта:<br>
              <strong><?= eur($r['price']) ?></strong>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <h2 class="section-title">Выплаты</h2>
    <?php if (!$hasPayouts): ?>
      <div class="details-shell">
        <div class="details-description">Таблица выплат ещё не создана. Когда подключим выплаты, они появятся здесь.</div>
      </div>
    <?php elseif (empty($payouts)): ?>
      <div class="details-shell">
        <div class="details-description">Пока нет выплат. Когда объект начнёт генерировать доход, здесь появятся начисления.</div>
      </div>
    <?php else: ?>
      <table class="table">
        <tbody>
        <?php foreach ($payouts as $p): ?>
          <tr class="tr">
            <td><strong><?= h($p['property_name']) ?></strong></td>
            <td>Дата: <strong><?= h($p['payout_date']) ?></strong></td>
            <td>Сумма: <strong>€<?= number_format((float)$p['amount'], 2, ',', ' ') ?></strong></td>
            <td><?= h($p['note'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <h2 class="section-title" id="chat">Сообщения</h2>

    <?php if (!$hasMessages): ?>
      <div class="details-shell">
        <div class="details-description">
          Чат ещё не подключён (нет таблицы <strong>messages</strong>). Добавь SQL ниже — и он заработает.
        </div>
      </div>
    <?php else: ?>
      <div class="chat-shell">
        <div class="note">Здесь можно переписываться с администратором по вашим заявкам.</div>

        <?php if ($chatError): ?>
          <div class="err"><?= h($chatError) ?></div>
        <?php endif; ?>

        <div class="chat-list">
          <?php if (empty($messages)): ?>
            <div class="note">Сообщений пока нет. Напишите первое сообщение.</div>
          <?php else: ?>
            <?php foreach ($messages as $m): ?>
              <div class="msg <?= $m['sender_role']==='user' ? 'user' : 'admin' ?>">
                <?= nl2br(h($m['message_text'])) ?>
                <div class="msg-meta"><?= h(date('Y-m-d H:i', strtotime($m['created_at']))) ?></div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <form class="chat-form" method="post" action="dashboard.php#chat">
          <input type="hidden" name="action" value="send_message">
          <textarea name="message" placeholder="Напишите сообщение администратору..."></textarea>
          <button class="btn btn-primary btn-sm" type="submit">Отправить</button>
        </form>
      </div>
    <?php endif; ?>
  </main>

  <footer>© <span>RealEstate Share</span>. Личный кабинет партнёра.</footer>
</div>
</body>
</html>
