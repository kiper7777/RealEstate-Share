<?php
require_once 'db.php';

if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Мои участия + объекты
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
            p.status AS property_status
        FROM participations part
        JOIN properties p ON p.id = part.property_id
        WHERE part.user_id = $userId
        ORDER BY part.created_at DESC";
$res = mysqli_query($conn, $sql);

$rows = [];
$totalInvested = 0.0;
while ($res && ($r = mysqli_fetch_assoc($res))) {
    $r['amount'] = (float)$r['amount'];
    $r['price']  = (float)$r['price'];
    $r['share_percent'] = $r['share_percent'] !== null ? (float)$r['share_percent'] : null;
    $totalInvested += $r['amount'];
    $rows[] = $r;
}

// Выплаты
$sqlP = "SELECT p.name, pay.amount, pay.payout_date, pay.note
         FROM payouts pay
         JOIN properties p ON p.id = pay.property_id
         WHERE pay.user_id = $userId
         ORDER BY pay.payout_date DESC
         LIMIT 20";
$resP = mysqli_query($conn, $sqlP);
$payouts = [];
$totalPayouts = 0.0;
while ($resP && ($p = mysqli_fetch_assoc($resP))) {
    $p['amount'] = (float)$p['amount'];
    $totalPayouts += $p['amount'];
    $payouts[] = $p;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Личный кабинет – RealEstate Share</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    .dash-wrap{max-width:1120px;margin:24px auto;padding:0 20px 40px;}
    .dash-header{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;margin-bottom:14px;}
    .dash-title{font-size:20px;font-weight:600;margin:0;}
    .dash-sub{color:var(--text-muted);font-size:13px;margin-top:6px;max-width:720px;}
    .dash-cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:14px 0 18px;}
    @media (max-width:900px){.dash-cards{grid-template-columns:1fr;}}
    .dash-card{border-radius:16px;background:rgba(15,23,42,0.95);border:1px solid rgba(55,65,81,0.9);padding:12px;}
    .dash-kpi-label{font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.08em;}
    .dash-kpi-value{font-size:18px;font-weight:600;margin-top:6px;}
    .table{width:100%;border-collapse:separate;border-spacing:0 8px;}
    .tr{background:rgba(15,23,42,0.95);border:1px solid rgba(55,65,81,0.9);}
    .table td{padding:10px 10px;font-size:12px;color:var(--text-muted);}
    .table td strong{color:var(--text-main);}
    .pill{display:inline-flex;align-items:center;gap:6px;padding:4px 8px;border-radius:999px;font-size:11px;border:1px solid rgba(55,65,81,0.9);background:rgba(15,23,42,0.95);}
    .pill.pending{border-color:rgba(245,158,11,0.8);background:rgba(245,158,11,0.12);color:#fde68a;}
    .pill.approved{border-color:rgba(34,197,94,0.7);background:rgba(22,163,74,0.16);color:#bbf7d0;}
    .pill.rejected{border-color:rgba(239,68,68,0.7);background:rgba(239,68,68,0.14);color:#fecaca;}
    .dash-actions{display:flex;gap:8px;flex-wrap:wrap;}
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
        <span class="nav-user">Привет, <?= htmlspecialchars($_SESSION['user_name']) ?></span>
        <a href="logout.php" class="btn btn-outline btn-sm">Выйти</a>
      </div>
    </div>
  </header>

  <main class="dash-wrap">
    <div class="dash-header">
      <div>
        <h1 class="dash-title">Ваш кабинет</h1>
        <div class="dash-sub">
          Здесь отображаются ваши заявки на участие, статусы (на модерации/подтверждено),
          а также выплаты и отчётность по объектам.
        </div>
      </div>
      <div class="dash-actions">
        <a href="index.php#properties" class="btn btn-primary btn-sm">Выбрать объект</a>
      </div>
    </div>

    <div class="dash-cards">
      <div class="dash-card">
        <div class="dash-kpi-label">Сумма заявок (всего)</div>
        <div class="dash-kpi-value">€<?= number_format($totalInvested, 0, ',', ' ') ?></div>
      </div>
      <div class="dash-card">
        <div class="dash-kpi-label">Выплаты (получено)</div>
        <div class="dash-kpi-value">€<?= number_format($totalPayouts, 0, ',', ' ') ?></div>
      </div>
      <div class="dash-card">
        <div class="dash-kpi-label">Заявок</div>
        <div class="dash-kpi-value"><?= count($rows) ?></div>
      </div>
    </div>

    <h2 style="font-size:16px;margin:0 0 10px;">Мои участия</h2>

    <?php if (empty($rows)): ?>
      <div class="details-shell">
        <div class="details-description">Пока нет заявок. Перейдите в «Объекты» и выберите объект для участия.</div>
      </div>
    <?php else: ?>
      <table class="table">
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr class="tr">
            <td>
              <strong><?= htmlspecialchars($r['name']) ?></strong><br>
              <?= htmlspecialchars($r['location']) ?>
            </td>
            <td>
              Сумма: <strong>€<?= number_format($r['amount'], 0, ',', ' ') ?></strong><br>
              Доля: <strong><?= $r['share_percent'] !== null ? number_format($r['share_percent'], 2, ',', ' ') . '%' : '—' ?></strong>
            </td>
            <td>
              Статус:
              <span class="pill <?= htmlspecialchars($r['status']) ?>">
                <?= $r['status']==='pending'?'На модерации':($r['status']==='approved'?'Подтверждено':'Отклонено') ?>
              </span><br>
              Дата: <strong><?= htmlspecialchars(date('Y-m-d', strtotime($r['created_at']))) ?></strong>
            </td>
            <td>
              Объект: <strong><?= htmlspecialchars($r['property_status']) ?></strong><br>
              Стоимость: <strong>€<?= number_format($r['price'], 0, ',', ' ') ?></strong>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <h2 style="font-size:16px;margin:18px 0 10px;">Последние выплаты</h2>
    <?php if (empty($payouts)): ?>
      <div class="details-shell">
        <div class="details-description">Пока нет выплат. Когда объект начнёт генерировать доход, здесь появятся начисления.</div>
      </div>
    <?php else: ?>
      <table class="table">
        <tbody>
        <?php foreach ($payouts as $p): ?>
          <tr class="tr">
            <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
            <td>Дата: <strong><?= htmlspecialchars($p['payout_date']) ?></strong></td>
            <td>Сумма: <strong>€<?= number_format($p['amount'], 2, ',', ' ') ?></strong></td>
            <td><?= htmlspecialchars($p['note'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </main>

  <footer>© <span>RealEstate Share</span>. Личный кабинет партнёра.</footer>
</div>
</body>
</html>
