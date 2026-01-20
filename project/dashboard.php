<?php
require_once 'db.php';
require_once 'csrf.php';

if (empty($_SESSION['user_id'])) {
  header('Location: index.php');
  exit;
}

$userId = (int)$_SESSION['user_id'];
$csrf = csrf_get_token();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function eur($n){ return '€' . number_format((float)$n, 0, ',', ' '); }

$script = $_SERVER['SCRIPT_NAME'] ?? '/project/dashboard.php';
$base = preg_replace('~/project/.*$~', '', $script);
$base = rtrim($base, '/');

// Мои участия + 1 фото объекта
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
          (SELECT pm.file_path
             FROM property_media pm
            WHERE pm.property_id = p.id
            ORDER BY pm.sort_order ASC, pm.id DESC
            LIMIT 1) AS cover_file
        FROM participations part
        JOIN properties p ON p.id = part.property_id
        WHERE part.user_id = $userId
        ORDER BY part.created_at DESC";
$res = mysqli_query($conn, $sql);

$rows = [];
$total = 0.0;
while ($res && ($r = mysqli_fetch_assoc($res))) {
  $r['amount'] = (float)$r['amount'];
  $r['price'] = (float)$r['price'];
  $r['share_percent'] = $r['share_percent'] !== null ? (float)$r['share_percent'] : null;

  $cover = $r['cover_file'] ?? '';
  $filename = $cover ? basename(str_replace('\\','/',$cover)) : '';
  $r['cover_url'] = $filename ? (($base === '' ? '' : $base) . '/uploads/' . $filename) : '';

  $total += $r['amount'];
  $rows[] = $r;
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
    .dash-wrap{max-width:1120px;margin:24px auto;padding:0 20px 60px;}
    .dash-header{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;margin-bottom:14px;}
    .dash-title{font-size:20px;font-weight:600;margin:0;}
    .dash-sub{color:var(--text-muted);font-size:13px;margin-top:6px;max-width:760px;line-height:1.45;}
    .dash-card{border-radius:16px;background:rgba(15,23,42,0.95);border:1px solid rgba(55,65,81,0.9);padding:12px;margin:14px 0;}
    .dash-kpi-label{font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.08em;}
    .dash-kpi-value{font-size:18px;font-weight:600;margin-top:6px;}

    .table{width:100%;border-collapse:separate;border-spacing:0 10px;}
    .tr{background:rgba(15,23,42,0.95);border:1px solid rgba(55,65,81,0.9);}
    .table td{padding:10px 10px;font-size:12px;color:var(--text-muted);vertical-align:top;}
    .table td strong{color:var(--text-main);}

    .pill{display:inline-flex;align-items:center;gap:6px;padding:4px 8px;border-radius:999px;font-size:11px;border:1px solid rgba(55,65,81,0.9);background:rgba(15,23,42,0.95);}
    .pill.pending{border-color:rgba(245,158,11,0.8);background:rgba(245,158,11,0.12);color:#fde68a;}
    .pill.approved{border-color:rgba(34,197,94,0.7);background:rgba(22,163,74,0.16);color:#bbf7d0;}
    .pill.rejected{border-color:rgba(239,68,68,0.7);background:rgba(239,68,68,0.14);color:#fecaca;}

    .thumb{width:86px;height:56px;object-fit:cover;border-radius:12px;border:1px solid rgba(255,255,255,0.1);display:block;}
    .thumb.empty{background:rgba(2,6,23,0.25);}

    /* Drawer chat */
    .overlay{position:fixed;inset:0;display:none;background:rgba(0,0,0,.55);z-index:9999;}
    .overlay.open{display:block;}
    .drawer{position:fixed;top:14px;right:-520px;bottom:14px;width:min(520px,94vw);
      border-radius:18px;background:rgba(15,23,42,.98);border:1px solid rgba(55,65,81,.9);
      padding:12px;z-index:10000;transition:right 220ms ease;}
    .drawer.open{right:14px;}
    .drawer-head{display:flex;justify-content:space-between;gap:10px;align-items:flex-start;}
    .drawer-title{font-size:14px;font-weight:600;margin:0;}
    .close-x{width:34px;height:34px;border-radius:12px;border:1px solid rgba(55,65,81,.9);
      background:rgba(2,6,23,0.25);color:rgba(226,232,240,.95);cursor:pointer;font-size:18px;line-height:1;}
    .chat-list{display:flex;flex-direction:column;gap:10px;max-height:calc(100vh - 210px);overflow:auto;margin-top:10px;padding-right:6px;}
    .msg{max-width:86%;border-radius:14px;padding:10px 10px;font-size:12px;line-height:1.45;border:1px solid rgba(55,65,81,.9);}
    .msg.user{margin-left:auto;background:rgba(79,70,229,0.16);border-color:rgba(79,70,229,0.45);color:#e0e7ff;}
    .msg.admin{margin-right:auto;background:rgba(2,6,23,0.30);color:rgba(226,232,240,0.9);}
    .msg-meta{font-size:10px;color:rgba(148,163,184,0.85);margin-top:6px;display:flex;gap:10px;align-items:center;}
    .chip-mini{display:inline-flex;gap:6px;align-items:center;padding:4px 8px;border-radius:999px;border:1px solid rgba(55,65,81,.9);
      background:rgba(15,23,42,.95);color:var(--text-muted);font-size:11px;}
    .chat-form{display:flex;gap:8px;margin-top:12px;align-items:flex-end;flex-wrap:wrap;}
    .chat-form textarea{flex:1;min-height:74px;border-radius:14px;border:1px solid rgba(55,65,81,.9);
      background:rgba(2,6,23,0.25);color:var(--text-main);padding:10px 10px;outline:none;}
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
        <?php if (!empty($_SESSION['is_admin'])): ?>
          <a href="../admin/index.php" class="btn btn-outline btn-sm">Админ-панель</a>
        <?php endif; ?>
        <a href="logout.php" class="btn btn-outline btn-sm">Выйти</a>
      </div>
    </div>
  </header>

  <main class="dash-wrap">
    <div class="dash-header">
      <div>
        <h1 class="dash-title">Ваш кабинет</h1>
        <div class="dash-sub">Заявки на участие + чат с администратором по каждой заявке.</div>
      </div>
    </div>

    <div class="dash-card">
      <div class="dash-kpi-label">Сумма заявок (всего)</div>
      <div class="dash-kpi-value"><?= eur($total) ?></div>
    </div>

    <h2 style="font-size:16px;margin:18px 0 10px;">Мои участия</h2>

    <?php if (empty($rows)): ?>
      <div class="details-shell">
        <div class="details-description">Пока нет заявок. Перейдите в «Объекты» и выберите объект.</div>
      </div>
    <?php else: ?>
      <table class="table">
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr class="tr">
            <td style="width:100px;">
              <?php if ($r['cover_url']): ?>
                <img class="thumb" src="<?= h($r['cover_url']) ?>" alt="">
              <?php else: ?>
                <div class="thumb empty"></div>
              <?php endif; ?>
            </td>
            <td>
              <strong><?= h($r['name']) ?></strong><br>
              <?= h($r['location']) ?><br>
              <span style="font-size:11px;">Тип: <strong><?= h($r['property_type']) ?></strong> · Статус: <strong><?= h($r['property_status']) ?></strong></span>
            </td>
            <td>
              Сумма: <strong><?= eur($r['amount']) ?></strong><br>
              Доля: <strong><?= $r['share_percent'] !== null ? number_format($r['share_percent'], 2, ',', ' ') . '%' : '—' ?></strong><br>
              Заявка: <strong>#<?= (int)$r['participation_id'] ?></strong>
            </td>
            <td>
              <span class="pill <?= h($r['status']) ?>">
                <?= $r['status']==='pending'?'На модерации':($r['status']==='approved'?'Подтверждено':'Отклонено') ?>
              </span><br>
              Дата: <strong><?= h(date('Y-m-d', strtotime($r['created_at']))) ?></strong>
            </td>
            <td>
              <button class="btn btn-outline btn-sm js-open-chat"
                type="button"
                data-participation-id="<?= (int)$r['participation_id'] ?>"
                data-property-id="<?= (int)$r['property_id'] ?>"
                data-title="<?= h('Чат по заявке #' . (int)$r['participation_id']) ?>">
                Чат с админом
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </main>

  <footer>© <span>RealEstate Share</span>. Личный кабинет партнёра.</footer>
</div>

<!-- Drawer Chat -->
<div class="overlay" id="overlay"></div>
<div class="drawer" id="drawer">
  <div class="drawer-head">
    <div>
      <div class="drawer-title" id="chatTitle">Чат</div>
      <div class="dash-sub" id="chatSub" style="margin-top:6px;"></div>
    </div>
    <button class="close-x" id="chatClose" type="button">×</button>
  </div>

  <div class="chat-list" id="chatList"></div>

  <div class="chat-form">
    <textarea id="chatInput" placeholder="Написать сообщение администратору..."></textarea>
    <button class="btn btn-primary btn-sm" id="chatSend" type="button">Отправить</button>
  </div>
</div>

<script>
  const CSRF = <?= json_encode($csrf, JSON_UNESCAPED_UNICODE) ?>;

  const overlay = document.getElementById('overlay');
  const drawer = document.getElementById('drawer');
  const chatList = document.getElementById('chatList');
  const chatTitle = document.getElementById('chatTitle');
  const chatSub = document.getElementById('chatSub');
  const chatInput = document.getElementById('chatInput');

  let ctx = { participationId:0, propertyId:0 };

  function openDrawer(){
    overlay.classList.add('open');
    drawer.classList.add('open');
  }
  function closeDrawer(){
    overlay.classList.remove('open');
    drawer.classList.remove('open');
    chatList.innerHTML = '';
    chatInput.value = '';
    ctx = { participationId:0, propertyId:0 };
  }
  overlay.addEventListener('click', closeDrawer);
  document.getElementById('chatClose').addEventListener('click', closeDrawer);

  function escapeHtml(s){
    return String(s).replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  async function loadChat(){
    chatList.innerHTML = '<div style="color:rgba(148,163,184,0.9);font-size:12px;">Загрузка...</div>';
    const r = await fetch('chat_fetch.php?participation_id=' + ctx.participationId);
    const j = await r.json();
    if (!j.success){
      chatList.innerHTML = '<div style="color:rgba(148,163,184,0.9);font-size:12px;">'+escapeHtml(j.message || 'Ошибка')+'</div>';
      return;
    }
    const msgs = Array.isArray(j.messages) ? j.messages : [];
    if (msgs.length === 0){
      chatList.innerHTML = '<div style="color:rgba(148,163,184,0.9);font-size:12px;">Сообщений нет. Напишите первое.</div>';
      return;
    }
    chatList.innerHTML = msgs.map(m => `
      <div class="msg ${m.sender_role === 'user' ? 'user' : 'admin'}">
        ${escapeHtml(m.message_text).replace(/\\n/g,'<br>')}
        <div class="msg-meta">
          <span class="chip-mini">${escapeHtml(m.created_at)}</span>
          <span class="chip-mini">ID: ${m.id}</span>
        </div>
      </div>
    `).join('');
    chatList.scrollTop = chatList.scrollHeight;
  }

  async function sendChat(){
    const text = chatInput.value.trim();
    if (!text) return;

    const r = await fetch('chat_send.php', {
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},
      body: JSON.stringify({
        participation_id: ctx.participationId,
        property_id: ctx.propertyId,
        message: text
      })
    });
    const j = await r.json();
    if (j.success){
      chatInput.value = '';
      await loadChat();
    } else {
      alert(j.message || 'Ошибка отправки');
    }
  }

  document.getElementById('chatSend').addEventListener('click', sendChat);

  document.querySelectorAll('.js-open-chat').forEach(btn => {
    btn.addEventListener('click', async () => {
      ctx.participationId = Number(btn.dataset.participationId);
      ctx.propertyId = Number(btn.dataset.propertyId);
      chatTitle.textContent = btn.dataset.title || 'Чат';
      chatSub.textContent = 'Заявка #' + ctx.participationId;
      openDrawer();
      await loadChat();
    });
  });
</script>
</body>
</html>
