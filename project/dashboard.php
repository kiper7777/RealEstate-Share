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

// Мои участия + cover + unread count (админ -> user)
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
          p.rent_per_year,
          p.yield_percent,
          p.payback_years,
          p.risk,
          p.description,
          p.status AS property_status,
          p.type AS property_type,
          p.region AS property_region,
          (SELECT pm.file_path
             FROM property_media pm
            WHERE pm.property_id = p.id
            ORDER BY pm.sort_order ASC, pm.id DESC
            LIMIT 1) AS cover_file,
          COALESCE((
            SELECT COUNT(*)
            FROM messages m
            WHERE m.user_id = part.user_id
              AND m.participation_id = part.id
              AND m.sender_role='admin'
              AND m.is_read=0
          ),0) AS unread_for_user
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
  $r['rent_per_year'] = (float)$r['rent_per_year'];
  $r['yield_percent'] = (float)$r['yield_percent'];
  $r['payback_years'] = (float)$r['payback_years'];
  $r['share_percent'] = $r['share_percent'] !== null ? (float)$r['share_percent'] : null;
  $r['unread_for_user'] = (int)$r['unread_for_user'];

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

    /* list */
    .cards{display:flex;flex-direction:column;gap:12px;margin-top:10px;}
    .card{
      border-radius:18px;border:1px solid rgba(55,65,81,0.9);
      background:rgba(15,23,42,0.95);
      overflow:hidden;
    }
    .card-head{
      display:grid;grid-template-columns:110px 1fr auto;
      gap:12px;padding:12px;align-items:center;cursor:pointer;
    }
    @media (max-width:820px){.card-head{grid-template-columns:110px 1fr;}.card-actions{grid-column:1/-1;}}
    .thumb{width:110px;height:74px;object-fit:cover;border-radius:14px;border:1px solid rgba(255,255,255,0.1);background:rgba(2,6,23,0.25);}
    .title{font-size:14px;font-weight:600;color:var(--text-main);}
    .meta{color:var(--text-muted);font-size:12px;line-height:1.35;margin-top:4px;}
    .pills{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;}
    .pill{display:inline-flex;align-items:center;gap:6px;padding:4px 8px;border-radius:999px;font-size:11px;border:1px solid rgba(55,65,81,0.9);background:rgba(15,23,42,0.95);color:var(--text-muted);}
    .pill strong{color:var(--text-main);}
    .status.pending{border-color:rgba(245,158,11,0.8);background:rgba(245,158,11,0.12);color:#fde68a;}
    .status.approved{border-color:rgba(34,197,94,0.7);background:rgba(22,163,74,0.16);color:#bbf7d0;}
    .status.rejected{border-color:rgba(239,68,68,0.7);background:rgba(239,68,68,0.14);color:#fecaca;}

    /* Badge above corner */
    .btn-badge{position:relative;overflow:visible;}
    .badge{
      position:absolute;top:-7px;right:-7px;
      min-width:18px;height:18px;padding:0 6px;border-radius:999px;
      font-size:11px;display:flex;align-items:center;justify-content:center;
      background:rgba(239,68,68,0.95);color:#fff;border:1px solid rgba(255,255,255,0.28);
      pointer-events:none;box-shadow:0 10px 24px rgba(0,0,0,.35);
    }

    /* Expand */
    .card-body{display:none;padding:0 12px 12px;}
    .card.expanded .card-body{display:block;}
    .divider{border:none;border-top:1px solid rgba(55,65,81,0.7);margin:10px 0;}
    .grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
    @media (max-width:860px){.grid2{grid-template-columns:1fr;}}
    .kv{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
    @media (max-width:640px){.kv{grid-template-columns:1fr;}}
    .box{border-radius:14px;border:1px solid rgba(55,65,81,.9);background:rgba(2,6,23,0.20);padding:10px;}
    .k{font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.08em;}
    .v{margin-top:6px;font-size:13px;color:var(--text-main);font-weight:600;}

    .gallery{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;}
    @media (max-width:980px){.gallery{grid-template-columns:repeat(2,minmax(0,1fr));}}
    @media (max-width:520px){.gallery{grid-template-columns:1fr;}}
    .gallery img{width:100%;height:160px;object-fit:cover;border-radius:14px;border:1px solid rgba(255,255,255,0.1);}

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
        <div class="dash-sub">Клик по карточке раскрывает подробную информацию об объекте и фото. Чат — справа.</div>
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
      <div class="cards" id="cards">
        <?php foreach ($rows as $r): ?>
          <div class="card js-card"
               data-participation-id="<?= (int)$r['participation_id'] ?>"
               data-property-id="<?= (int)$r['property_id'] ?>">
            <div class="card-head js-head">
              <?php if ($r['cover_url']): ?>
                <img class="thumb" src="<?= h($r['cover_url']) ?>" alt="">
              <?php else: ?>
                <div class="thumb"></div>
              <?php endif; ?>

              <div>
                <div class="title"><?= h($r['name']) ?></div>
                <div class="meta"><?= h($r['location']) ?></div>
                <div class="pills">
                  <span class="pill">Заявка: <strong>#<?= (int)$r['participation_id'] ?></strong></span>
                  <span class="pill">Сумма: <strong><?= eur($r['amount']) ?></strong></span>
                  <span class="pill status <?= h($r['status']) ?>">
                    <?= $r['status']==='pending'?'На модерации':($r['status']==='approved'?'Подтверждено':'Отклонено') ?>
                  </span>
                </div>
              </div>

              <div class="card-actions">
                <button class="btn btn-outline btn-sm btn-badge js-open-chat"
                  type="button"
                  data-participation-id="<?= (int)$r['participation_id'] ?>"
                  data-property-id="<?= (int)$r['property_id'] ?>"
                  data-title="<?= h('Чат по заявке #' . (int)$r['participation_id']) ?>">
                  Чат с админом
                  <?php if ((int)$r['unread_for_user'] > 0): ?>
                    <span class="badge"><?= (int)$r['unread_for_user'] ?></span>
                  <?php endif; ?>
                </button>
              </div>
            </div>

            <div class="card-body">
              <hr class="divider">
              <div class="dash-sub" style="margin:0 0 10px;">
                <strong>Подробности объекта</strong> (экономика, доходность и фото).
              </div>

              <div class="grid2">
                <div>
                  <div class="kv">
                    <div class="box"><div class="k">Стоимость</div><div class="v"><?= eur($r['price']) ?></div></div>
                    <div class="box"><div class="k">Аренда / год</div><div class="v"><?= eur($r['rent_per_year']) ?></div></div>
                    <div class="box"><div class="k">Доходность</div><div class="v"><?= number_format($r['yield_percent'], 2, ',', ' ') ?>%</div></div>
                    <div class="box"><div class="k">Окупаемость</div><div class="v"><?= number_format($r['payback_years'], 1, ',', ' ') ?> лет</div></div>
                  </div>

                  <div class="box" style="margin-top:10px;">
                    <div class="k">Риски</div>
                    <div class="v" style="font-size:12px;font-weight:500;color:rgba(226,232,240,.92);"><?= h($r['risk']) ?></div>
                  </div>

                  <div class="box" style="margin-top:10px;">
                    <div class="k">Ожидаемый доход / год</div>
                    <?php
                      $expected = $r['rent_per_year'] > 0 ? $r['rent_per_year'] : ($r['price'] * $r['yield_percent'] / 100.0);
                    ?>
                    <div class="v"><?= eur($expected) ?></div>
                    <div style="margin-top:6px;color:var(--text-muted);font-size:12px;line-height:1.35;">
                      Если <code>rent_per_year</code> задан — берём его, иначе считаем <code>price × yield%</code>.
                    </div>
                  </div>

                  <div class="box" style="margin-top:10px;">
                    <div class="k">Описание</div>
                    <div style="margin-top:8px;color:rgba(226,232,240,.9);font-size:12px;line-height:1.55;">
                      <?= nl2br(h($r['description'])) ?>
                    </div>
                  </div>
                </div>

                <div>
                  <div class="dash-sub" style="margin:0 0 10px;">Фотографии (крупнее)</div>
                  <div class="gallery js-gallery">
                    <div class="dash-sub">Загрузка фото…</div>
                  </div>
                </div>
              </div>
            </div>

          </div>
        <?php endforeach; ?>
      </div>
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

  const apiBase = (() => {
    // dashboard.php находится в /project/ => API в /api/
    return '../api';
  })();

  function escapeHtml(s){
    return String(s).replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  // Expand card + load details photos once
  const loadedMedia = new Set();
  document.querySelectorAll('.js-card').forEach(card => {
    const head = card.querySelector('.js-head');
    head.addEventListener('click', async (e) => {
      // не раскрывать, если нажали кнопку чата
      if (e.target.closest('.js-open-chat')) return;

      card.classList.toggle('expanded');
      if (card.classList.contains('expanded')) {
        const propId = Number(card.dataset.propertyId);
        if (!loadedMedia.has(propId)) {
          loadedMedia.add(propId);
          const gallery = card.querySelector('.js-gallery');
          gallery.innerHTML = '<div class="dash-sub">Загрузка фото…</div>';
          const r = await fetch(apiBase + '/property_details.php?property_id=' + propId);
          const j = await r.json();
          if (!j.success) {
            gallery.innerHTML = '<div class="dash-sub">' + escapeHtml(j.message || 'Ошибка') + '</div>';
            return;
          }
          const media = Array.isArray(j.media) ? j.media : [];
          if (!media.length) {
            gallery.innerHTML = '<div class="dash-sub">Фото не найдены.</div>';
            return;
          }
          gallery.innerHTML = media.map(m => `<img src="${m.url}" alt="">`).join('');
        }
      }
    });
  });

  // Chat drawer
  const overlay = document.getElementById('overlay');
  const drawer = document.getElementById('drawer');
  const chatList = document.getElementById('chatList');
  const chatTitle = document.getElementById('chatTitle');
  const chatSub = document.getElementById('chatSub');
  const chatInput = document.getElementById('chatInput');

  let ctx = { participationId:0, propertyId:0, openerBtn:null };

  function openDrawer(){ overlay.classList.add('open'); drawer.classList.add('open'); }
  function closeDrawer(){
    overlay.classList.remove('open');
    drawer.classList.remove('open');
    chatList.innerHTML = '';
    chatInput.value = '';
    ctx = { participationId:0, propertyId:0, openerBtn:null };
  }

  overlay.addEventListener('click', closeDrawer);
  document.getElementById('chatClose').addEventListener('click', closeDrawer);

  async function markRead(){
    await fetch('chat_mark_read.php', {
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},
      body: JSON.stringify({ participation_id: ctx.participationId })
    }).catch(()=>{});
    if (ctx.openerBtn){
      const b = ctx.openerBtn.querySelector('.badge');
      if (b) b.remove();
    }
  }

  async function loadChat(){
    chatList.innerHTML = '<div class="dash-sub">Загрузка…</div>';
    const r = await fetch('chat_fetch.php?participation_id=' + ctx.participationId);
    const j = await r.json();
    if (!j.success){
      chatList.innerHTML = '<div class="dash-sub">'+escapeHtml(j.message || 'Ошибка')+'</div>';
      return;
    }
    const msgs = Array.isArray(j.messages) ? j.messages : [];
    if (!msgs.length){
      chatList.innerHTML = '<div class="dash-sub">Сообщений нет. Напишите первое.</div>';
      await markRead();
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
    await markRead();
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
    btn.addEventListener('click', async (e) => {
      e.stopPropagation();
      ctx.participationId = Number(btn.dataset.participationId);
      ctx.propertyId = Number(btn.dataset.propertyId);
      ctx.openerBtn = btn;
      chatTitle.textContent = btn.dataset.title || 'Чат';
      chatSub.textContent = 'Заявка #' + ctx.participationId;
      openDrawer();
      await loadChat();
    });
  });
</script>
</body>
</html>
