<?php
require_once __DIR__ . '/../project/db.php';
require_once __DIR__ . '/../project/csrf.php';

if (empty($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
  header('Location: ../project/index.php');
  exit;
}

$csrf = csrf_get_token();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function eur($n){ return '€' . number_format((float)$n, 0, ',', ' '); }

$script = $_SERVER['SCRIPT_NAME'] ?? '/admin/index.php';
$base = preg_replace('~/admin/.*$~', '', $script);
$base = rtrim($base, '/');

function upload_url_from_path(string $filePath, string $base): string {
  $file = basename(str_replace('\\','/',$filePath));
  if (!$file) return '';
  return ($base === '' ? '' : $base) . '/uploads/' . $file;
}

function media_urls_for_property(mysqli $conn, int $propertyId, string $base, int $limit=4): array {
  $arr = [];
  $res = mysqli_query($conn, "SELECT file_path FROM property_media WHERE property_id=$propertyId ORDER BY sort_order ASC, id DESC LIMIT $limit");
  while ($res && ($m = mysqli_fetch_assoc($res))) {
    $u = upload_url_from_path($m['file_path'] ?? '', $base);
    if ($u) $arr[] = $u;
  }
  return $arr;
}

// --- Objects list with cover photo
$props = [];
$sqlProps = "SELECT p.*,
  (SELECT pm.file_path FROM property_media pm WHERE pm.property_id=p.id ORDER BY pm.sort_order ASC, pm.id DESC LIMIT 1) AS cover_file
  FROM properties p
  ORDER BY p.id DESC";
$res = mysqli_query($conn, $sqlProps);
while ($res && ($r = mysqli_fetch_assoc($res))) {
  $r['cover_url'] = !empty($r['cover_file']) ? upload_url_from_path($r['cover_file'], $base) : '';
  $props[] = $r;
}

// --- Pending with unread counts
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
          COUNT(CASE WHEN part2.status IN ('pending','approved') THEN part2.id ELSE NULL END) AS participants,
          COALESCE((
            SELECT COUNT(*) 
            FROM messages m
            WHERE m.user_id = part.user_id 
              AND m.participation_id = part.id
              AND m.sender_role='user'
              AND m.is_read=0
          ),0) AS unread_for_admin
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
  $r['unread_for_admin'] = (int)$r['unread_for_admin'];
  $pending[] = $r;
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

    .admin-grid{display:grid;grid-template-columns:1.1fr 0.9fr;gap:12px;}
    @media (max-width:1000px){.admin-grid{grid-template-columns:1fr;}}

    .admin-box{border-radius:16px;background:rgba(15,23,42,.95);border:1px solid rgba(55,65,81,.9);padding:12px;}
    .admin-title{font-size:16px;font-weight:600;margin:0 0 10px;}
    .admin-small{color:var(--text-muted);font-size:12px;margin-bottom:10px;line-height:1.45;}

    .toast{margin-top:10px;font-size:12px;padding:8px 10px;border-radius:12px;display:none;}
    .toast.show{display:block;}
    .toast.ok{color:#bbf7d0;border:1px solid rgba(34,197,94,.7);background:rgba(22,163,74,.14);}
    .toast.err{color:#fecaca;border:1px solid rgba(239,68,68,.7);background:rgba(239,68,68,.12);}

    /* Objects */
    .list{display:flex;flex-direction:column;gap:8px;}
    .item{display:grid;grid-template-columns:92px 1fr;gap:10px;border:1px solid rgba(55,65,81,.9);border-radius:14px;padding:10px;background:rgba(15,23,42,.95);transition:transform 160ms ease-out, border-color 160ms ease-out, box-shadow 160ms ease-out;}
    .item:hover{transform:translateY(-1px);border-color:rgba(148,163,184,.7);}
    .item.selected{border-color:rgba(79,70,229,0.9);box-shadow:0 18px 40px rgba(15,23,42,0.9);
      background: radial-gradient(circle at top left, rgba(79,70,229,0.22), transparent 60%), rgba(15,23,42,0.98);}
    .cover{width:92px;height:66px;border-radius:12px;border:1px solid rgba(255,255,255,0.1);object-fit:cover;background:rgba(2,6,23,0.25);}
    .item-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;}

    .row{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
    .row3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;}
    @media (max-width:720px){.row,.row3{grid-template-columns:1fr;}}
    textarea{min-height:90px;resize:vertical;}

    /* Media */
    .media-top{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:10px;}
    .media-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;}
    @media (max-width:900px){.media-grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
    @media (max-width:520px){.media-grid{grid-template-columns:1fr;}}
    .media-card{border-radius:14px;border:1px solid rgba(55,65,81,.9);background:rgba(15,23,42,.95);padding:10px;}
    .media-card img{width:100%;height:120px;object-fit:cover;border-radius:12px;border:1px solid rgba(255,255,255,0.1);}
    .media-meta{margin-top:8px;color:var(--text-muted);font-size:12px;line-height:1.35;}
    .media-meta strong{color:var(--text-main);}

    /* Pending */
    .pending-grid{display:grid;grid-template-columns:1fr;gap:12px;}
    .pending-card{border-radius:16px;border:1px solid rgba(55,65,81,.9);background:rgba(15,23,42,.95);padding:12px;}
    .pending-head{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;}
    .pending-title{font-size:14px;font-weight:600;margin:0;}
    .chips{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px;}
    .chip{display:inline-flex;gap:6px;align-items:center;padding:4px 8px;border-radius:999px;border:1px solid rgba(55,65,81,.9);background:rgba(15,23,42,.95);color:var(--text-muted);font-size:11px;}
    .chip strong{color:var(--text-main);}

    .btn-mini{padding:6px 10px;border-radius:10px;font-size:12px;line-height:1;}
    .btn-mini + .btn-mini{margin-left:6px;}

    /* Badge: чуть выше угла */
    .btn-badge{position:relative;overflow:visible;}
    .badge{
      position:absolute;
      top:-7px;
      right:-7px;
      min-width:18px;
      height:18px;
      padding:0 6px;
      border-radius:999px;
      font-size:11px;
      display:flex;
      align-items:center;
      justify-content:center;
      background:rgba(239,68,68,0.95);
      color:#fff;
      border:1px solid rgba(255,255,255,0.28);
      pointer-events:none;
      box-shadow:0 10px 24px rgba(0,0,0,.35);
    }

    .pending-body{display:grid;grid-template-columns:1fr 0.9fr;gap:12px;margin-top:10px;}
    @media (max-width:980px){.pending-body{grid-template-columns:1fr;}}
    .kv{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
    @media (max-width:620px){.kv{grid-template-columns:1fr;}}
    .kv .box{border-radius:14px;border:1px solid rgba(55,65,81,.9);background:rgba(2,6,23,0.20);padding:10px;}
    .kv .k{font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.08em;}
    .kv .v{margin-top:6px;font-size:13px;color:var(--text-main);font-weight:600;}
    .photos{display:flex;gap:8px;flex-wrap:wrap;}
    .photos img{width:94px;height:64px;object-fit:cover;border-radius:12px;border:1px solid rgba(255,255,255,0.1);}

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
      <button class="admin-tab active" data-tab="objects">Объекты</button>
      <button class="admin-tab" data-tab="media">Медиа</button>
      <button class="admin-tab" data-tab="pending">Pending</button>
    </div>

    <div id="toast" class="toast"></div>

    <!-- OBJECTS -->
    <section class="admin-panel active" id="tab-objects">
      <div class="admin-grid">
        <div class="admin-box">
          <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center;">
            <div>
              <div class="admin-title">Список объектов</div>
              <div class="admin-small">Нажми «Редактировать» — форма справа заполнится исходными данными.</div>
            </div>
            <button class="btn btn-primary btn-sm" type="button" id="newPropertyBtn">+ Новый объект</button>
          </div>

          <div class="list" id="propsList">
            <?php foreach ($props as $p): ?>
              <div class="item js-prop-item"
                   data-prop='<?= h(json_encode($p, JSON_UNESCAPED_UNICODE)) ?>'>
                <?php if (!empty($p['cover_url'])): ?>
                  <img class="cover" src="<?= h($p['cover_url']) ?>" alt="">
                <?php else: ?>
                  <div class="cover"></div>
                <?php endif; ?>

                <div>
                  <strong>#<?= (int)$p['id'] ?> · <?= h($p['name']) ?></strong>
                  <div style="color:var(--text-muted);font-size:12px;">
                    <?= h($p['location']) ?> · <?= eur($p['price']) ?>
                  </div>
                  <div class="chips">
                    <span class="chip">Тип: <strong><?= h($p['type']) ?></strong></span>
                    <span class="chip">Регион: <strong><?= h($p['region']) ?></strong></span>
                    <span class="chip">Статус: <strong><?= h($p['status']) ?></strong></span>
                  </div>

                  <div class="item-actions">
                    <button class="btn btn-outline btn-sm js-edit" type="button">Редактировать</button>
                    <button class="btn btn-outline btn-sm js-delete" type="button">Удалить</button>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="admin-box">
          <div class="admin-title" id="formTitle">Редактирование объекта</div>
          <div class="admin-small">Сохранение идёт через <code>admin_property_save.php</code> (insert/update).</div>

          <form id="propForm">
            <input type="hidden" id="prop_id" value="">

            <div class="form-row"><label>Название</label><input type="text" id="prop_name" required></div>
            <div class="form-row"><label>Локация</label><input type="text" id="prop_location" required></div>

            <div class="row3">
              <div class="form-row">
                <label>Тип</label>
                <select id="prop_type">
                  <option value="residential">residential</option>
                  <option value="commercial">commercial</option>
                </select>
              </div>
              <div class="form-row">
                <label>Регион</label>
                <select id="prop_region">
                  <option value="europe">europe</option>
                  <option value="middleeast">middleeast</option>
                </select>
              </div>
              <div class="form-row">
                <label>Статус</label>
                <select id="prop_status">
                  <option value="funding">funding</option>
                  <option value="acquired">acquired</option>
                  <option value="managed">managed</option>
                  <option value="closed">closed</option>
                </select>
              </div>
            </div>

            <div class="row">
              <div class="form-row"><label>Цена (€)</label><input type="number" id="prop_price" step="100" required></div>
              <div class="form-row"><label>Мин. взнос (€)</label><input type="number" id="prop_min_ticket" step="100" required></div>
            </div>

            <div class="row">
              <div class="form-row"><label>Макс. партнёров</label><input type="number" id="prop_max_partners" step="1" required></div>
              <div class="form-row"><label>Аренда/год (€)</label><input type="number" id="prop_rent_per_year" step="100" required></div>
            </div>

            <div class="row">
              <div class="form-row"><label>Доходность (%)</label><input type="number" id="prop_yield" step="0.1" required></div>
              <div class="form-row"><label>Окупаемость (лет)</label><input type="number" id="prop_payback" step="0.1" required></div>
            </div>

            <div class="form-row"><label>Риски</label><input type="text" id="prop_risk" required></div>
            <div class="form-row"><label>Описание</label><textarea id="prop_description" required></textarea></div>

            <div class="row">
              <button class="btn btn-primary btn-sm" type="submit">Сохранить</button>
              <button class="btn btn-outline btn-sm" type="button" id="propReset">Очистить</button>
            </div>

            <div style="margin-top:10px;color:var(--text-muted);font-size:12px;" id="propMsg"></div>
          </form>
        </div>
      </div>
    </section>

    <!-- MEDIA -->
    <section class="admin-panel" id="tab-media">
      <div class="admin-box">
        <div class="admin-title">Медиа по объекту</div>
        <div class="admin-small">Выберите объект — покажутся только его фото. Чекбоксы → массовое удаление.</div>

        <div class="media-top">
          <div class="form-row" style="min-width:320px;">
            <label>Объект</label>
            <select id="mediaPropertySelect">
              <option value="0">— выберите объект —</option>
              <?php foreach ($props as $p): ?>
                <option value="<?= (int)$p['id'] ?>">#<?= (int)$p['id'] ?> · <?= h($p['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <button class="btn btn-outline btn-sm" id="mediaReload" type="button">Обновить</button>
          <button class="btn btn-outline btn-sm" id="mediaDeleteSelected" type="button">Удалить выбранные</button>
          <div style="color:var(--text-muted);font-size:12px;" id="mediaMsg"></div>
        </div>

        <div id="mediaGrid" class="media-grid"></div>

        <hr style="border:none;border-top:1px solid rgba(55,65,81,.7);margin:14px 0;">

        <div class="admin-title" style="font-size:14px;">Загрузить фото</div>
        <form id="mediaForm" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

          <div class="row">
            <div class="form-row">
              <label>property_id</label>
              <input type="number" name="property_id" id="mediaUploadPropertyId" required>
            </div>
            <div class="form-row">
              <label>sort_order</label>
              <input type="number" name="sort_order" value="0">
            </div>
          </div>

          <div class="form-row"><label>caption</label><input type="text" name="caption"></div>
          <div class="form-row"><label>Файл</label><input type="file" name="image" accept="image/jpeg,image/png,image/webp" required></div>
          <button class="btn btn-primary btn-sm" type="submit">Загрузить</button>
        </form>
      </div>
    </section>

    <!-- PENDING -->
    <section class="admin-panel" id="tab-pending">
      <div class="admin-box">
        <div class="admin-title">Pending заявки</div>
        <div class="admin-small">Ничего не меняем по логике. Добавили: бейдж новых сообщений + mark-read.</div>

        <?php if (empty($pending)): ?>
          <div class="admin-small">Нет pending заявок.</div>
        <?php else: ?>
          <div class="pending-grid" id="pendingList">
            <?php foreach ($pending as $x): ?>
              <?php
                $remaining = max($x['price'] - $x['invested'], 0);
                $slotsLeft = max(((int)$x['max_partners']) - (int)$x['participants'], 0);
                $photos = media_urls_for_property($conn, (int)$x['property_id'], $base, 4);
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

                    <button class="btn btn-outline btn-mini btn-badge js-open-chat" type="button">
                      Чат
                      <?php if ((int)$x['unread_for_admin'] > 0): ?>
                        <span class="badge"><?= (int)$x['unread_for_admin'] ?></span>
                      <?php endif; ?>
                    </button>
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

  document.querySelectorAll('.admin-tab').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.admin-tab').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.admin-panel').forEach(p => p.classList.remove('active'));
      btn.classList.add('active');
      document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
    });
  });

  // ---- Objects
  function clearSelected(){ document.querySelectorAll('.js-prop-item').forEach(i => i.classList.remove('selected')); }

  const prop_id = document.getElementById('prop_id');
  const prop_name = document.getElementById('prop_name');
  const prop_location = document.getElementById('prop_location');
  const prop_type = document.getElementById('prop_type');
  const prop_region = document.getElementById('prop_region');
  const prop_status = document.getElementById('prop_status');
  const prop_price = document.getElementById('prop_price');
  const prop_min_ticket = document.getElementById('prop_min_ticket');
  const prop_max_partners = document.getElementById('prop_max_partners');
  const prop_rent_per_year = document.getElementById('prop_rent_per_year');
  const prop_yield = document.getElementById('prop_yield');
  const prop_payback = document.getElementById('prop_payback');
  const prop_risk = document.getElementById('prop_risk');
  const prop_description = document.getElementById('prop_description');
  const propMsg = document.getElementById('propMsg');
  const formTitle = document.getElementById('formTitle');

  function fillForm(d){
    prop_id.value = d.id || '';
    prop_name.value = d.name || '';
    prop_location.value = d.location || '';
    prop_type.value = d.type || 'residential';
    prop_region.value = d.region || 'europe';
    prop_status.value = d.status || 'funding';
    prop_price.value = d.price || '';
    prop_min_ticket.value = d.min_ticket || '';
    prop_max_partners.value = d.max_partners || '';
    prop_rent_per_year.value = d.rent_per_year || '';
    prop_yield.value = d.yield_percent || '';
    prop_payback.value = d.payback_years || '';
    prop_risk.value = d.risk || '';
    prop_description.value = d.description || '';
    propMsg.textContent = d.id ? ('Редактирование объекта #' + d.id) : 'Создание нового объекта';
    formTitle.textContent = d.id ? 'Редактирование объекта' : 'Создание нового объекта';
  }

  function newProperty(){
    clearSelected();
    document.getElementById('propForm').reset();
    fillForm({});
    toast('Новый объект: заполните форму и нажмите Сохранить');
  }

  document.getElementById('newPropertyBtn').addEventListener('click', newProperty);

  document.querySelectorAll('.js-prop-item').forEach(item => {
    const data = JSON.parse(item.dataset.prop || "{}");
    item.querySelector('.js-edit').addEventListener('click', () => {
      clearSelected(); item.classList.add('selected'); fillForm(data);
      toast('Вы редактируете объект #' + data.id);
    });
    item.querySelector('.js-delete').addEventListener('click', async () => {
      if (!confirm('Удалить объект #' + data.id + '?')) return;
      const r = await fetch('admin_property_delete.php', {
        method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},
        body: JSON.stringify({ id: data.id })
      });
      const j = await r.json();
      if (j.success){ item.remove(); toast('Объект удалён'); newProperty(); }
      else toast(j.message || 'Ошибка удаления', false);
    });
  });

  document.getElementById('propReset').addEventListener('click', () => newProperty());

  document.getElementById('propForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const payload = {
      id: prop_id.value ? Number(prop_id.value) : null,
      name: prop_name.value.trim(),
      location: prop_location.value.trim(),
      type: prop_type.value,
      region: prop_region.value,
      status: prop_status.value,
      price: Number(prop_price.value),
      min_ticket: Number(prop_min_ticket.value),
      max_partners: Number(prop_max_partners.value),
      rent_per_year: Number(prop_rent_per_year.value),
      yield_percent: Number(prop_yield.value),
      payback_years: Number(prop_payback.value),
      risk: prop_risk.value.trim(),
      description: prop_description.value.trim(),
    };
    const r = await fetch('admin_property_save.php', {
      method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},
      body: JSON.stringify(payload)
    });
    const j = await r.json();
    if (j.success){
      toast(payload.id ? 'Сохранено' : ('Создан объект #' + j.id));
      propMsg.textContent = 'OK';
      if (!payload.id && j.id) {
        // после создания — перезайди в админку, чтобы увидеть новый объект в списке (самый простой и надёжный вариант)
        toast('Объект создан. Обнови страницу (F5), чтобы увидеть его в списке.');
        prop_id.value = j.id;
      }
    } else {
      toast(j.message || 'Ошибка сохранения', false);
      propMsg.textContent = j.message || 'Ошибка';
    }
  });

  // ---- Media
  const mediaSelect = document.getElementById('mediaPropertySelect');
  const mediaGrid = document.getElementById('mediaGrid');
  const mediaMsg = document.getElementById('mediaMsg');
  const mediaUploadPropertyId = document.getElementById('mediaUploadPropertyId');

  function escapeHtml(s){ return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

  async function loadMedia(){
    const pid = Number(mediaSelect.value);
    mediaGrid.innerHTML = '';
    mediaMsg.textContent = '';
    if (!pid) return;
    mediaUploadPropertyId.value = pid;

    const r = await fetch('admin_media_list.php?property_id=' + pid);
    const j = await r.json();
    if (!j.success){ mediaMsg.textContent = j.message || 'Ошибка'; return; }
    const list = Array.isArray(j.media) ? j.media : [];
    if (!list.length){ mediaGrid.innerHTML = '<div class="admin-small">Нет фото для этого объекта.</div>'; return; }

    mediaGrid.innerHTML = list.map(m => `
      <div class="media-card">
        <label class="chip" style="cursor:pointer;">
          <input class="js-media-check" type="checkbox" value="${m.id}">
          <span>Выбрать</span>
        </label>
        <img src="${m.url}" alt="">
        <div class="media-meta">
          ID: <strong>${m.id}</strong><br>
          Файл: <strong>${escapeHtml(m.file_name)}</strong><br>
          ${m.caption ? ('Caption: ' + escapeHtml(m.caption) + '<br>') : ''}
          sort_order: <strong>${m.sort_order}</strong>
        </div>
      </div>
    `).join('');
  }

  mediaSelect.addEventListener('change', loadMedia);
  document.getElementById('mediaReload').addEventListener('click', loadMedia);

  document.getElementById('mediaDeleteSelected').addEventListener('click', async () => {
    const checks = Array.from(document.querySelectorAll('.js-media-check:checked'));
    if (!checks.length) return toast('Ничего не выбрано', false);
    if (!confirm('Удалить выбранные фото ('+checks.length+')?')) return;

    const ids = checks.map(c => Number(c.value));
    const r = await fetch('admin_media_delete_bulk.php', {
      method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},
      body: JSON.stringify({ ids })
    });
    const j = await r.json();
    if (j.success){ toast('Удалено: ' + (j.deleted || ids.length)); loadMedia(); }
    else toast(j.message || 'Ошибка удаления', false);
  });

  document.getElementById('mediaForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    mediaMsg.textContent = 'Загрузка...';
    const fd = new FormData(e.target);
    const r = await fetch('admin_media_upload.php', { method:'POST', body: fd });
    const j = await r.json();
    if (j.success){ toast('Фото загружено (id=' + j.media_id + ')'); mediaMsg.textContent='OK'; loadMedia(); }
    else { toast(j.message || 'Ошибка загрузки', false); mediaMsg.textContent = j.message || 'Ошибка'; }
  });

  // ---- Pending status + chat drawer
  async function setStatus(partId, status){
    const r = await fetch('admin_participation_set_status.php', {
      method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},
      body: JSON.stringify({ id: partId, status })
    });
    const j = await r.json();
    if (j.success){
      const el = document.querySelector('.js-pending-item[data-id="'+partId+'"]');
      if (el) el.remove();
      toast(status === 'approved' ? 'Заявка подтверждена #' + partId : 'Заявка отклонена #' + partId);
    } else toast(j.message || 'Ошибка', false);
  }

  const overlay = document.getElementById('overlay');
  const drawer = document.getElementById('drawer');
  const chatList = document.getElementById('chatList');
  const chatTitle = document.getElementById('chatTitle');
  const chatSub = document.getElementById('chatSub');
  const chatInput = document.getElementById('chatInput');

  let chatCtx = { userId:0, participationId:0, propertyId:0, openerBtn:null };

  function openDrawer(){ overlay.classList.add('open'); drawer.classList.add('open'); }
  function closeDrawer(){ overlay.classList.remove('open'); drawer.classList.remove('open'); chatList.innerHTML=''; chatInput.value=''; chatCtx={ userId:0, participationId:0, propertyId:0, openerBtn:null }; }
  overlay.addEventListener('click', closeDrawer);
  document.getElementById('chatClose').addEventListener('click', closeDrawer);

  async function markRead(){
    await fetch('admin_chat_mark_read.php', {
      method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},
      body: JSON.stringify({ user_id: chatCtx.userId, participation_id: chatCtx.participationId })
    }).catch(()=>{});
    if (chatCtx.openerBtn){
      const b = chatCtx.openerBtn.querySelector('.badge');
      if (b) b.remove();
    }
  }

  async function loadChat(){
    chatList.innerHTML = '<div class="admin-small">Загрузка...</div>';
    const url = 'admin_chat_fetch.php?user_id=' + chatCtx.userId + '&participation_id=' + chatCtx.participationId;
    const r = await fetch(url);
    const j = await r.json();
    if (!j.success){ chatList.innerHTML = '<div class="admin-small">'+escapeHtml(j.message || 'Ошибка')+'</div>'; return; }
    const msgs = Array.isArray(j.messages) ? j.messages : [];
    if (!msgs.length){ chatList.innerHTML = '<div class="admin-small">Сообщений нет.</div>'; await markRead(); return; }

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
    await markRead();
  }

  async function sendChat(){
    const text = chatInput.value.trim();
    if (!text) return;

    const r = await fetch('admin_chat_send.php', {
      method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},
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
      toast('Отправлено');
    } else toast(j.message || 'Ошибка отправки', false);
  }

  document.getElementById('chatSend').addEventListener('click', sendChat);

  document.querySelectorAll('.js-pending-item').forEach(card => {
    const partId = Number(card.dataset.id);
    card.querySelector('.js-approve').addEventListener('click', () => setStatus(partId,'approved'));
    card.querySelector('.js-reject').addEventListener('click', () => setStatus(partId,'rejected'));
    card.querySelector('.js-open-chat').addEventListener('click', async (e) => {
      chatCtx = {
        userId: Number(card.dataset.userId),
        participationId: Number(card.dataset.id),
        propertyId: Number(card.dataset.propertyId),
        openerBtn: e.currentTarget
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
