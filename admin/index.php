<?php
require_once __DIR__ . '/../project/db.php';
require_once __DIR__ . '/../project/csrf.php';

if (empty($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
  header('Location: ../project/index.php');
  exit;
}

$csrf = csrf_get_token();

$props = [];
$res = mysqli_query($conn, "SELECT id, name, location, price, status, type, region, min_ticket, max_partners, rent_per_year, yield_percent, payback_years, risk, description
                            FROM properties
                            ORDER BY id DESC");
while ($res && ($r = mysqli_fetch_assoc($res))) $props[] = $r;

$pending = [];
$sqlP = "SELECT 
          part.id AS participation_id,
          part.amount,
          part.status,
          part.created_at,
          u.id AS user_id,
          u.name AS user_name,
          u.email,
          p.id AS property_id,
          p.name AS property_name,
          p.location,
          p.region,
          p.type,
          p.status AS property_status,
          p.price,
          p.max_partners,
          p.rent_per_year,
          p.yield_percent,
          p.payback_years,
          p.risk,
          p.description,
          COALESCE(SUM(CASE WHEN part2.status IN ('pending','approved') THEN part2.amount ELSE 0 END),0) AS invested,
          COUNT(CASE WHEN part2.status IN ('pending','approved') THEN part2.id ELSE NULL END) AS participants
        FROM participations part
        JOIN users u ON u.id = part.user_id
        JOIN properties p ON p.id = part.property_id
        LEFT JOIN participations part2 ON part2.property_id = p.id
        WHERE part.status='pending'
        GROUP BY part.id
        ORDER BY part.created_at DESC";
$resP = mysqli_query($conn, $sqlP);
while ($resP && ($r = mysqli_fetch_assoc($resP))) {
  $r['price'] = (float)$r['price'];
  $r['rent_per_year'] = (float)$r['rent_per_year'];
  $r['yield_percent'] = (float)$r['yield_percent'];
  $r['payback_years'] = (float)$r['payback_years'];
  $r['invested'] = (float)$r['invested'];
  $r['participants'] = (int)$r['participants'];
  $pending[] = $r;
}

$script = $_SERVER['SCRIPT_NAME'] ?? '/admin/index.php';
$base = preg_replace('~/admin/.*$~', '', $script);
$base = rtrim($base, '/');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function eur($n){ return '€' . number_format((float)$n, 0, ',', ' '); }

function media_urls_for_property(mysqli $conn, int $propertyId, string $base): array {
  $arr = [];
  $res = mysqli_query($conn, "SELECT file_path FROM property_media WHERE property_id=$propertyId ORDER BY sort_order ASC, id DESC LIMIT 4");
  while ($res && ($m = mysqli_fetch_assoc($res))) {
    $filename = basename(str_replace('\\','/',$m['file_path'] ?? ''));
    if ($filename) $arr[] = ($base === '' ? '' : $base) . '/uploads/' . $filename;
  }
  return $arr;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin – RealEstate Share</title>
  <link rel="stylesheet" href="../project/styles.css">
  <style>
    .admin-wrap{max-width:1180px;margin:24px auto;padding:0 20px 50px;}
    .admin-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:12px 0;}
    .admin-tab{padding:7px 12px;border-radius:999px;border:1px solid rgba(55,65,81,.9);background:rgba(15,23,42,.95);color:var(--text-muted);cursor:pointer;font-size:12px;}
    .admin-tab.active{border-color:var(--accent);color:var(--accent);background:var(--accent-soft);}
    .admin-panel{display:none;}
    .admin-panel.active{display:block;}

    .admin-box{border-radius:16px;background:rgba(15,23,42,.95);border:1px solid rgba(55,65,81,.9);padding:12px;}
    .admin-title{font-size:16px;font-weight:600;margin:0 0 10px;}
    .admin-small{color:var(--text-muted);font-size:12px;margin-bottom:10px;line-height:1.45;}

    .pending-grid{display:grid;grid-template-columns:1fr;gap:12px;}
    .pending-card{border-radius:16px;border:1px solid rgba(55,65,81,.9);background:rgba(15,23,42,.95);padding:12px;}
    .pending-head{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;}
    .pending-title{font-size:14px;font-weight:600;margin:0;}
    .chips{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px;}
    .chip{display:inline-flex;gap:6px;align-items:center;padding:4px 8px;border-radius:999px;border:1px solid rgba(55,65,81,.9);background:rgba(15,23,42,.95);color:var(--text-muted);font-size:11px;}
    .chip strong{color:var(--text-main);}

    .btn-mini{padding:6px 10px;border-radius:10px;font-size:12px;line-height:1;}
    .btn-mini + .btn-mini{margin-left:6px;}

    .pending-body{display:grid;grid-template-columns:1fr 0.9fr;gap:12px;margin-top:10px;}
    @media (max-width:980px){.pending-body{grid-template-columns:1fr;}}
    .kv{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
    @media (max-width:620px){.kv{grid-template-columns:1fr;}}
    .kv .box{border-radius:14px;border:1px solid rgba(55,65,81,.9);background:rgba(2,6,23,0.20);padding:10px;}
    .kv .k{font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.08em;}
    .kv .v{margin-top:6px;font-size:13px;color:var(--text-main);font-weight:600;}
    .photos{display:flex;gap:8px;flex-wrap:wrap;}
    .photos img{width:94px;height:64px;object-fit:cover;border-radius:12px;border:1px solid rgba(255,255,255,0.1);}

    .toast{margin-top:10px;font-size:12px;padding:8px 10px;border-radius:12px;display:none;}
    .toast.show{display:block;}
    .toast.ok{color:#bbf7d0;border:1px solid rgba(34,197,94,.7);background:rgba(22,163,74,.14);}
    .toast.err{color:#fecaca;border:1px solid rgba(239,68,68,.7);background:rgba(239,68,68,.12);}

    /* Drawer chat справа */
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
          <div class="logo-title">Admin Panel</div>
          <div class="logo-subtitle">RealEstate Share</div>
        </div>
      </div>
      <div class="nav-actions">
        <a href="../project/index.php" class="nav-link">На сайт</a>
        <a href="../project/dashboard.php" class="nav-link">Кабинет</a>
        <span class="nav-user">Админ: <?= h($_SESSION['user_name']) ?></span>
        <a href="../project/logout.php" class="btn btn-outline btn-sm">Выйти</a>
      </div>
    </div>
  </header>

  <main class="admin-wrap">
    <div class="admin-tabs">
      <button class="admin-tab active" data-tab="pending">Pending</button>
    </div>

    <div id="toast" class="toast"></div>

    <section class="admin-panel active" id="tab-pending">
      <div class="admin-box">
        <div class="admin-title">Pending заявки</div>
        <div class="admin-small">Карточка объекта + фото + чат с заявителем по каждой заявке.</div>

        <?php if (empty($pending)): ?>
          <div class="admin-small">Нет pending заявок.</div>
        <?php else: ?>
          <div class="pending-grid" id="pendingList">
            <?php foreach ($pending as $x): ?>
              <?php
                $remaining = max($x['price'] - $x['invested'], 0);
                $slotsLeft = max(((int)$x['max_partners']) - (int)$x['participants'], 0);
                $photos = media_urls_for_property($conn, (int)$x['property_id'], $base);
              ?>
              <div class="pending-card js-pending-item"
                   data-id="<?= (int)$x['participation_id'] ?>"
                   data-user-id="<?= (int)$x['user_id'] ?>"
                   data-property-id="<?= (int)$x['property_id'] ?>"
                   data-user-name="<?= h($x['user_name']) ?>"
                   data-user-email="<?= h($x['email']) ?>"
                   data-property-name="<?= h($x['property_name']) ?>">
                <div class="pending-head">
                  <div>
                    <div class="pending-title">
                      Заявка #<?= (int)$x['participation_id'] ?> · <?= h($x['user_name']) ?> (<?= h($x['email']) ?>)
                    </div>
                    <div class="admin-small" style="margin:6px 0 0;">
                      Объект #<?= (int)$x['property_id'] ?> · <?= h($x['property_name']) ?> · <?= h($x['location']) ?>
                    </div>

                    <div class="chips">
                      <span class="chip">Тип: <strong><?= h($x['type']) ?></strong></span>
                      <span class="chip">Статус: <strong><?= h($x['property_status']) ?></strong></span>
                      <span class="chip">Регион: <strong><?= h($x['region']) ?></strong></span>
                      <span class="chip">Сумма заявки: <strong><?= eur($x['amount']) ?></strong></span>
                    </div>
                  </div>

                  <div>
                    <button class="btn btn-primary btn-mini js-approve" type="button">Подтвердить</button>
                    <button class="btn btn-outline btn-mini js-reject" type="button">Отклонить</button>
                    <button class="btn btn-outline btn-mini js-open-chat" type="button">Чат</button>
                  </div>
                </div>

                <div class="pending-body">
                  <div class="kv">
                    <div class="box"><div class="k">Стоимость</div><div class="v"><?= eur($x['price']) ?></div></div>
                    <div class="box"><div class="k">Инвестировано</div><div class="v"><?= eur($x['invested']) ?></div></div>
                    <div class="box"><div class="k">Осталось</div><div class="v"><?= eur($remaining) ?></div></div>
                    <div class="box"><div class="k">Аренда / год</div><div class="v"><?= eur($x['rent_per_year']) ?></div></div>
                    <div class="box"><div class="k">Доходность</div><div class="v"><?= number_format($x['yield_percent'], 2, ',', ' ') ?>%</div></div>
                    <div class="box"><div class="k">Окупаемость</div><div class="v"><?= number_format($x['payback_years'], 1, ',', ' ') ?> лет</div></div>
                    <div class="box"><div class="k">Партнёры</div><div class="v"><?= (int)$x['participants'] ?></div></div>
                    <div class="box"><div class="k">Слоты доступны</div><div class="v"><?= (int)$slotsLeft ?></div></div>
                    <div class="box" style="grid-column:1/-1;">
                      <div class="k">Риски</div>
                      <div class="v" style="font-size:12px;font-weight:500;color:rgba(226,232,240,.92);"><?= h($x['risk']) ?></div>
                    </div>
                  </div>

                  <div>
                    <div class="admin-small">Фото объекта</div>
                    <div class="photos">
                      <?php if (empty($photos)): ?>
                        <div class="admin-small">Нет фото</div>
                      <?php else: ?>
                        <?php foreach ($photos as $u): ?>
                          <img src="<?= h($u) ?>" alt="">
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </div>

                    <div class="admin-small" style="margin-top:10px;">Описание</div>
                    <div style="color:rgba(226,232,240,.9);font-size:12px;line-height:1.45;">
                      <?= nl2br(h($x['description'])) ?>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </main>

  <footer>© <span>RealEstate Share</span>. Admin Panel.</footer>
</div>

<!-- Drawer Chat -->
<div class="overlay" id="overlay"></div>
<div class="drawer" id="drawer">
  <div class="drawer-head">
    <div>
      <div class="drawer-title" id="chatTitle">Чат</div>
      <div class="admin-small" id="chatSub" style="margin:6px 0 0;"></div>
    </div>
    <button class="close-x" id="chatClose" type="button">×</button>
  </div>

  <div class="chat-list" id="chatList"></div>

  <div class="chat-form">
    <textarea id="chatInput" placeholder="Написать сообщение заявителю..."></textarea>
    <button class="btn btn-primary btn-sm" id="chatSend" type="button">Отправить</button>
  </div>
</div>

<script>
  const CSRF = <?= json_encode($csrf, JSON_UNESCAPED_UNICODE) ?>;

  function toast(text, ok=true){
    const el = document.getElementById('toast');
    el.className = 'toast show ' + (ok ? 'ok' : 'err');
    el.textContent = text;
    setTimeout(()=>el.classList.remove('show'), 3200);
  }

  // Tab (тут только один, но оставим на будущее)
  document.querySelectorAll('.admin-tab').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.admin-tab').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.admin-panel').forEach(p => p.classList.remove('active'));
      btn.classList.add('active');
      document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
    });
  });

  // Pending status
  async function setStatus(partId, status){
    const r = await fetch('admin_participation_set_status.php', {
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},
      body: JSON.stringify({ id: partId, status })
    });
    const j = await r.json();
    if (j.success){
      const el = document.querySelector('.js-pending-item[data-id="'+partId+'"]');
      if (el) el.remove();
      toast(status === 'approved' ? 'Заявка подтверждена #' + partId : 'Заявка отклонена #' + partId);
    } else {
      toast(j.message || 'Ошибка', false);
    }
  }

  // Drawer chat
  const overlay = document.getElementById('overlay');
  const drawer = document.getElementById('drawer');
  const chatList = document.getElementById('chatList');
  const chatTitle = document.getElementById('chatTitle');
  const chatSub = document.getElementById('chatSub');
  const chatInput = document.getElementById('chatInput');

  let chatCtx = { userId:0, participationId:0, propertyId:0 };

  function openDrawer(){
    overlay.classList.add('open');
    drawer.classList.add('open');
  }
  function closeDrawer(){
    overlay.classList.remove('open');
    drawer.classList.remove('open');
    chatList.innerHTML = '';
    chatInput.value = '';
    chatCtx = { userId:0, participationId:0, propertyId:0 };
  }

  overlay.addEventListener('click', closeDrawer);
  document.getElementById('chatClose').addEventListener('click', closeDrawer);

  async function loadChat(){
    chatList.innerHTML = '<div class="admin-small">Загрузка...</div>';
    const url = 'admin_chat_fetch.php?user_id=' + chatCtx.userId + '&participation_id=' + chatCtx.participationId;
    const r = await fetch(url);
    const j = await r.json();
    if (!j.success){
      chatList.innerHTML = '<div class="admin-small">'+escapeHtml(j.message || 'Ошибка')+'</div>';
      return;
    }
    const msgs = Array.isArray(j.messages) ? j.messages : [];
    if (msgs.length === 0){
      chatList.innerHTML = '<div class="admin-small">Сообщений нет. Напиши первое.</div>';
      return;
    }
    chatList.innerHTML = msgs.map(m => `
      <div class="msg ${m.sender_role === 'admin' ? 'admin' : 'user'}">
        ${escapeHtml(m.message_text).replace(/\\n/g,'<br>')}
        <div class="msg-meta">
          <span class="chip-mini">${escapeHtml(m.created_at)}</span>
          <span class="chip-mini">ID: ${m.id}</span>
        </div>
      </div>
    `).join('');
    chatList.scrollTop = chatList.scrollHeight;
  }

  function escapeHtml(s){
    return String(s).replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  async function sendChat(){
    const text = chatInput.value.trim();
    if (!text) return;

    const r = await fetch('admin_chat_send.php', {
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},
      body: JSON.stringify({
        user_id: chatCtx.userId,
        participation_id: chatCtx.participationId,
        property_id: chatCtx.propertyId,
        message: text
      })
    });
    const j = await r.json();
    if (j.success){
      chatInput.value = '';
      await loadChat();
      toast('Сообщение отправлено');
    } else {
      toast(j.message || 'Ошибка отправки', false);
    }
  }

  document.getElementById('chatSend').addEventListener('click', sendChat);

  document.querySelectorAll('.js-pending-item').forEach(card => {
    const partId = Number(card.dataset.id);
    card.querySelector('.js-approve').addEventListener('click', () => setStatus(partId,'approved'));
    card.querySelector('.js-reject').addEventListener('click', () => setStatus(partId,'rejected'));
    card.querySelector('.js-open-chat').addEventListener('click', async () => {
      chatCtx = {
        userId: Number(card.dataset.userId),
        participationId: Number(card.dataset.id),
        propertyId: Number(card.dataset.propertyId)
      };
      chatTitle.textContent = 'Чат по заявке #' + chatCtx.participationId;
      chatSub.textContent = card.dataset.userName + ' · ' + card.dataset.userEmail + ' · ' + card.dataset.propertyName;
      openDrawer();
      await loadChat();
    });
  });
</script>
</body>
</html>
