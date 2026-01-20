<?php
require_once __DIR__ . '/../project/db.php';
require_once __DIR__ . '/../project/csrf.php';

if (empty($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
  header('Location: ../project/index.php');
  exit;
}

$csrf = csrf_get_token();

/**
 * BASE (если проект в подпапке типа /realestate_share)
 * /realestate_share/admin/index.php -> base = /realestate_share
 */
$script = $_SERVER['SCRIPT_NAME'] ?? '/admin/index.php';
$base = preg_replace('~/admin/.*$~', '', $script);
$base = rtrim($base, '/'); // '' или '/realestate_share'
$uploadsBaseUrl = ($base === '' ? '' : $base) . '/uploads/';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/** Полные данные объектов + агрегаты */
$props = [];
$sqlProps = "SELECT 
    p.*,
    COALESCE(SUM(CASE WHEN part.status='approved' THEN part.amount ELSE 0 END),0) AS invested_approved,
    COALESCE(SUM(CASE WHEN part.status='pending' THEN part.amount ELSE 0 END),0) AS invested_pending,
    COALESCE(COUNT(CASE WHEN part.status='approved' THEN 1 END),0) AS partners_approved,
    COALESCE(COUNT(CASE WHEN part.status='pending' THEN 1 END),0) AS partners_pending
  FROM properties p
  LEFT JOIN participations part ON part.property_id=p.id
  GROUP BY p.id
  ORDER BY p.id DESC";
$res = mysqli_query($conn, $sqlProps);
while ($res && ($r = mysqli_fetch_assoc($res))) {
  $props[] = $r;
}

/** Медиа список (для вкладки Медиа) */
$mediaAll = [];
$sqlMediaAll = "SELECT m.id, m.property_id, m.file_path, m.caption, m.sort_order, m.created_at, p.name AS property_name
                FROM property_media m
                JOIN properties p ON p.id=m.property_id
                ORDER BY m.property_id DESC, m.sort_order ASC, m.id DESC";
$resMAll = mysqli_query($conn, $sqlMediaAll);
while ($resMAll && ($m = mysqli_fetch_assoc($resMAll))) {
  // В file_path у тебя хранится ИМЯ ФАЙЛА (например p1_...jpg). Если вдруг там путь - нормализуем.
  $filename = basename(str_replace('\\','/',$m['file_path']));
  $m['url'] = $uploadsBaseUrl . $filename;
  $mediaAll[] = $m;
}

/** Pending участия сгруппированные по объекту */
$pendingByProperty = []; // property_id => ['property' => ..., 'items' => [...], 'media'=>[...]]
$sqlPend = "SELECT part.id AS participation_id, part.amount, part.created_at,
                   u.id AS user_id, u.name AS user_name, u.email,
                   p.*
            FROM participations part
            JOIN users u ON u.id=part.user_id
            JOIN properties p ON p.id=part.property_id
            WHERE part.status='pending'
            ORDER BY part.created_at DESC";
$resP = mysqli_query($conn, $sqlPend);

while ($resP && ($row = mysqli_fetch_assoc($resP))) {
  $pid = (int)$row['id'];

  if (!isset($pendingByProperty[$pid])) {
    // агрегаты по объекту
    $agg = ['invested_approved'=>0,'invested_pending'=>0,'partners_approved'=>0,'partners_pending'=>0];
    $resAgg = mysqli_query($conn, "SELECT
        COALESCE(SUM(CASE WHEN status='approved' THEN amount ELSE 0 END),0) AS invested_approved,
        COALESCE(SUM(CASE WHEN status='pending' THEN amount ELSE 0 END),0) AS invested_pending,
        COALESCE(COUNT(CASE WHEN status='approved' THEN 1 END),0) AS partners_approved,
        COALESCE(COUNT(CASE WHEN status='pending' THEN 1 END),0) AS partners_pending
      FROM participations WHERE property_id=$pid");
    if ($resAgg) $agg = mysqli_fetch_assoc($resAgg) ?: $agg;

    // медиа по объекту (до 6)
    $media = [];
    $resPM = mysqli_query($conn, "SELECT id, file_path, caption, sort_order
                                  FROM property_media
                                  WHERE property_id=$pid
                                  ORDER BY sort_order ASC, id ASC
                                  LIMIT 6");
    while ($resPM && ($m = mysqli_fetch_assoc($resPM))) {
      $filename = basename(str_replace('\\','/',$m['file_path']));
      $media[] = [
        'id' => (int)$m['id'],
        'url' => $uploadsBaseUrl . $filename,
        'caption' => $m['caption']
      ];
    }

    $pendingByProperty[$pid] = [
      'property' => $row,
      'agg' => $agg,
      'media' => $media,
      'items' => []
    ];
  }

  $pendingByProperty[$pid]['items'][] = [
    'participation_id' => (int)$row['participation_id'],
    'amount' => (float)$row['amount'],
    'created_at' => $row['created_at'],
    'user_id' => (int)$row['user_id'],
    'user_name' => $row['user_name'],
    'email' => $row['email'],
  ];
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
    .admin-wrap{max-width:1200px;margin:24px auto;padding:0 20px 40px;}
    .admin-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:12px 0;}
    .admin-tab{padding:7px 12px;border-radius:999px;border:1px solid rgba(55,65,81,.9);background:rgba(15,23,42,.95);color:var(--text-muted);cursor:pointer;font-size:12px;}
    .admin-tab.active{border-color:var(--accent);color:var(--accent);background:var(--accent-soft);}
    .admin-panel{display:none;}
    .admin-panel.active{display:block;}
    .admin-grid{display:grid;grid-template-columns:1.1fr 0.9fr;gap:12px;}
    @media (max-width:980px){.admin-grid{grid-template-columns:1fr;}}

    .admin-box{border-radius:16px;background:rgba(15,23,42,.95);border:1px solid rgba(55,65,81,.9);padding:12px;}
    .admin-title{font-size:16px;font-weight:600;margin:0 0 10px;}
    .admin-small{color:var(--text-muted);font-size:12px;margin-bottom:10px;}
    .list{display:flex;flex-direction:column;gap:8px;}
    .item{border:1px solid rgba(55,65,81,.9);border-radius:14px;padding:10px;background:rgba(15,23,42,.95);transition:transform 160ms ease-out, border-color 160ms ease-out, box-shadow 160ms ease-out;}
    .item:hover{transform:translateY(-1px);border-color:rgba(148,163,184,.7);}
    .item.selected{border-color: rgba(79,70,229,0.9);box-shadow: 0 18px 40px rgba(15,23,42,0.9);
      background: radial-gradient(circle at top left, rgba(79,70,229,0.22), transparent 60%), rgba(15,23,42,0.98);
    }
    .badge-editing{display:none;margin-top:6px;font-size:11px;padding:4px 8px;border-radius:999px;background: rgba(79,70,229,0.14);border: 1px solid rgba(79,70,229,0.6);color: #c7d2fe;width: fit-content;}
    .item.selected .badge-editing{display:inline-flex;}
    .item-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;}

    .row{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
    .row3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;}
    @media (max-width:720px){.row,.row3{grid-template-columns:1fr;}}

    .inline{display:flex;gap:8px;align-items:center;flex-wrap:wrap;}
    select{background:rgba(15,23,42,.95);border-radius:10px;border:1px solid rgba(55,65,81,.9);padding:7px 9px;color:var(--text-main);font-size:12px;outline:none;}
    textarea{min-height:100px;resize:vertical;}

    .admin-toast{
      margin:10px 0;
      font-size:12px;
      color:#bbf7d0;
      border:1px solid rgba(34,197,94,.7);
      background:rgba(22,163,74,.14);
      padding:8px 10px;border-radius:12px;display:none;
    }
    .admin-toast.err{color:#fecaca;border-color:rgba(239,68,68,.7);background:rgba(239,68,68,.12);}
    .admin-toast.show{display:block;}

    /* Media grid */
    .media-grid{display:grid;grid-template-columns:repeat(4, minmax(0,1fr));gap:10px;}
    @media (max-width:1100px){.media-grid{grid-template-columns:repeat(3,minmax(0,1fr));}}
    @media (max-width:820px){.media-grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
    @media (max-width:520px){.media-grid{grid-template-columns:1fr;}}
    .media-card{border:1px solid rgba(55,65,81,.9);border-radius:16px;background:rgba(15,23,42,.95);overflow:hidden;}
    .media-img{height:140px;background:rgba(2,6,23,.5);display:flex;align-items:center;justify-content:center;}
    .media-img img{width:100%;height:100%;object-fit:cover;display:block;}
    .media-meta{padding:10px;font-size:12px;color:var(--text-muted);}
    .media-meta strong{color:var(--text-main);}
    .media-check{display:flex;align-items:center;gap:8px;margin-top:8px;}

    /* Pending property summary */
    .prop-summary{display:grid;grid-template-columns:1.25fr 0.75fr;gap:10px;}
    @media (max-width:980px){.prop-summary{grid-template-columns:1fr;}}
    .summary-kpis{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;}
    @media (max-width:780px){.summary-kpis{grid-template-columns:1fr;}}
    .kpi{border:1px solid rgba(55,65,81,.9);background:rgba(15,23,42,.95);border-radius:14px;padding:10px;}
    .kpi .l{font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.08em;}
    .kpi .v{margin-top:6px;font-size:14px;color:var(--text-main);font-weight:600;}
    .thumbs{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px;}
    .thumb{width:52px;height:52px;border-radius:14px;overflow:hidden;border:1px solid rgba(255,255,255,0.12);}
    .thumb img{width:100%;height:100%;object-fit:cover;display:block;}

    /* Chat UI */
    .chat{margin-top:10px;border:1px solid rgba(55,65,81,.9);border-radius:14px;overflow:hidden;background:rgba(15,23,42,.95);}
    .chat-head{display:flex;justify-content:space-between;align-items:center;padding:8px 10px;border-bottom:1px solid rgba(55,65,81,.7);color:var(--text-muted);font-size:12px;}
    .chat-body{max-height:220px;overflow:auto;padding:10px;display:flex;flex-direction:column;gap:8px;}
    .msg{border:1px solid rgba(55,65,81,.7);border-radius:14px;padding:8px 10px;font-size:12px;background:rgba(2,6,23,.35);color:var(--text-muted);}
    .msg strong{color:var(--text-main);}
    .msg-row{display:flex;gap:8px;align-items:center;justify-content:space-between;}
    .msg-del{cursor:pointer;font-size:11px;color:#fecaca;border:1px solid rgba(239,68,68,.5);padding:3px 8px;border-radius:999px;background:rgba(239,68,68,.08);}
    .chat-foot{display:flex;gap:8px;padding:10px;border-top:1px solid rgba(55,65,81,.7);}
    .chat-foot input{flex:1;}
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
      <button class="admin-tab active" data-tab="properties">Объекты</button>
      <button class="admin-tab" data-tab="media">Медиа</button>
      <button class="admin-tab" data-tab="participations">Участия (pending)</button>
    </div>

    <div id="toast" class="admin-toast"></div>

    <!-- TAB: PROPERTIES -->
    <section class="admin-panel active" id="tab-properties">
      <div class="admin-grid">
        <div class="admin-box">
          <div class="admin-title">Список объектов</div>
          <div class="admin-small">Нажми «Редактировать» — карточка выделится и справа появятся исходные данные. После сохранения карточка обновится без перезагрузки.</div>

          <div class="list" id="propsList">
            <?php foreach ($props as $p): ?>
              <div class="item js-prop-item" id="propCard<?= (int)$p['id'] ?>"
                   data-prop='<?= h(json_encode($p, JSON_UNESCAPED_UNICODE)) ?>'>
                <strong>#<?= (int)$p['id'] ?> · <?= h($p['name']) ?></strong>
                <div style="color:var(--text-muted);font-size:12px;">
                  <?= h($p['location']) ?> · €<?= number_format((float)$p['price'], 0, ',', ' ') ?>
                </div>
                <div style="margin-top:6px;color:var(--text-muted);font-size:12px;">
                  Тип: <?= h($p['type']) ?> · Регион: <?= h($p['region']) ?> · Статус: <?= h($p['status'] ?? 'funding') ?>
                </div>

                <div class="badge-editing">Сейчас редактируется</div>

                <div class="item-actions">
                  <button class="btn btn-outline btn-sm js-edit" type="button">Редактировать</button>
                  <button class="btn btn-outline btn-sm js-delete" type="button">Удалить</button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="admin-box">
          <div class="admin-title">Создать/обновить объект</div>
          <div class="admin-small">Сохранение реально пишет в БД. Поля справа всегда заполняются исходными данными выбранного объекта.</div>

          <form id="propForm">
            <input type="hidden" id="prop_id" value="">

            <div class="form-row">
              <label>Название</label>
              <input type="text" id="prop_name" required>
            </div>
            <div class="form-row">
              <label>Локация</label>
              <input type="text" id="prop_location" required>
            </div>

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
              <div class="form-row">
                <label>Цена (€)</label>
                <input type="number" id="prop_price" step="100" required>
              </div>
              <div class="form-row">
                <label>Мин. взнос (€)</label>
                <input type="number" id="prop_min_ticket" step="100" required>
              </div>
            </div>

            <div class="row">
              <div class="form-row">
                <label>Макс. партнёров</label>
                <input type="number" id="prop_max_partners" step="1" required>
              </div>
              <div class="form-row">
                <label>Аренда/год (€)</label>
                <input type="number" id="prop_rent_per_year" step="100" required>
              </div>
            </div>

            <div class="row">
              <div class="form-row">
                <label>Доходность (%)</label>
                <input type="number" id="prop_yield" step="0.1" required>
              </div>
              <div class="form-row">
                <label>Окупаемость (лет)</label>
                <input type="number" id="prop_payback" step="0.1" required>
              </div>
            </div>

            <div class="form-row">
              <label>Риски</label>
              <input type="text" id="prop_risk" required>
            </div>

            <div class="form-row">
              <label>Описание</label>
              <textarea id="prop_description" required></textarea>
            </div>

            <div class="inline">
              <button class="btn btn-primary btn-sm" type="submit">Сохранить</button>
              <button class="btn btn-outline btn-sm" type="button" id="propReset">Очистить</button>
              <span id="propMsg" style="color:var(--text-muted);font-size:12px;"></span>
            </div>
          </form>
        </div>
      </div>
    </section>

    <!-- TAB: MEDIA -->
    <section class="admin-panel" id="tab-media">
      <div class="admin-box">
        <div class="admin-title">Медиа: просмотр + удаление по галочкам</div>
        <div class="admin-small">Загрузи фото — оно появится ниже. Можно отметить несколько и удалить одним нажатием.</div>

        <form id="mediaUploadForm" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
          <div class="row">
            <div class="form-row">
              <label>Объект</label>
              <select name="property_id" required>
                <?php foreach ($props as $p): ?>
                  <option value="<?= (int)$p['id'] ?>">#<?= (int)$p['id'] ?> · <?= h($p['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-row">
              <label>sort_order</label>
              <input type="number" name="sort_order" value="0">
            </div>
          </div>

          <div class="form-row">
            <label>Подпись</label>
            <input type="text" name="caption" placeholder="Например: Вид с балкона">
          </div>

          <div class="form-row">
            <label>Файл (JPG/PNG/WebP)</label>
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required>
          </div>

          <div class="inline">
            <button class="btn btn-primary btn-sm" type="submit">Загрузить</button>
            <span id="mediaUploadMsg" style="color:var(--text-muted);font-size:12px;"></span>
          </div>
        </form>

        <hr style="border:none;border-top:1px solid rgba(55,65,81,.7);margin:14px 0;">

        <form id="mediaBulkForm">
          <div class="inline" style="justify-content:space-between;">
            <div class="admin-title" style="font-size:14px;margin:0;">Все фотографии</div>
            <div class="inline">
              <button class="btn btn-outline btn-sm" type="button" id="mediaSelectAll">Выбрать всё</button>
              <button class="btn btn-outline btn-sm" type="button" id="mediaClearAll">Снять всё</button>
              <button class="btn btn-outline btn-sm" type="button" id="mediaDeleteSelected">Удалить выбранные</button>
            </div>
          </div>

          <div style="margin-top:10px;" class="media-grid" id="mediaGrid">
            <?php foreach ($mediaAll as $m): ?>
              <div class="media-card" data-media-id="<?= (int)$m['id'] ?>">
                <div class="media-img">
                  <img src="<?= h($m['url']) ?>" alt="">
                </div>
                <div class="media-meta">
                  <div><strong>ID:</strong> <?= (int)$m['id'] ?></div>
                  <div><strong>Объект:</strong> #<?= (int)$m['property_id'] ?> · <?= h($m['property_name']) ?></div>
                  <div><strong>caption:</strong> <?= h($m['caption'] ?? '') ?></div>
                  <div><strong>sort:</strong> <?= (int)$m['sort_order'] ?></div>
                  <div class="media-check">
                    <input type="checkbox" class="mediaCheck" value="<?= (int)$m['id'] ?>">
                    <span>Отметить для удаления</span>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="inline" style="margin-top:10px;">
            <span id="mediaBulkMsg" style="color:var(--text-muted);font-size:12px;"></span>
          </div>
        </form>
      </div>
    </section>

    <!-- TAB: PENDING -->
    <section class="admin-panel" id="tab-participations">
      <div class="admin-box">
        <div class="admin-title">Pending: картина по объекту + заявки + чат</div>
        <div class="admin-small">Здесь показана полная финансовая ситуация по объекту и медиа, чтобы проще принимать решения.</div>

        <?php if (empty($pendingByProperty)): ?>
          <div class="details-description">Нет заявок со статусом pending.</div>
        <?php else: ?>
          <div class="list" id="pendingRoot">
            <?php foreach ($pendingByProperty as $pid => $block): 
              $p = $block['property'];
              $agg = $block['agg'];
              $price = (float)$p['price'];
              $invA = (float)$agg['invested_approved'];
              $invP = (float)$agg['invested_pending'];
              $remainingA = max($price - $invA, 0);
              $remainingAP = max($price - ($invA + $invP), 0);
              $slotsLeft = max(((int)$p['max_partners']) - ((int)$agg['partners_approved'] + (int)$agg['partners_pending']), 0);
            ?>
              <div class="item" data-property-id="<?= (int)$pid ?>"
                   data-price="<?= h($price) ?>"
                   data-inv-approved="<?= h($invA) ?>"
                   data-inv-pending="<?= h($invP) ?>"
                   data-part-approved="<?= h((int)$agg['partners_approved']) ?>"
                   data-part-pending="<?= h((int)$agg['partners_pending']) ?>"
                   data-max-partners="<?= h((int)$p['max_partners']) ?>">

                <strong>#<?= (int)$pid ?> · <?= h($p['name']) ?></strong>
                <div style="color:var(--text-muted);font-size:12px;">
                  <?= h($p['location']) ?> · Тип: <?= h($p['type']) ?> · Статус: <?= h($p['status'] ?? 'funding') ?> · Регион: <?= h($p['region']) ?>
                </div>

                <?php if (!empty($block['media'])): ?>
                  <div class="thumbs">
                    <?php foreach ($block['media'] as $m): ?>
                      <div class="thumb" title="<?= h($m['caption'] ?? '') ?>">
                        <img src="<?= h($m['url']) ?>" alt="">
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>

                <div class="prop-summary" style="margin-top:10px;">
                  <div class="summary-kpis">
                    <div class="kpi">
                      <div class="l">Цена</div>
                      <div class="v">€<?= number_format($price, 0, ',', ' ') ?></div>
                    </div>
                    <div class="kpi">
                      <div class="l">Инвестировано (approved)</div>
                      <div class="v" data-kpi="invA">€<?= number_format($invA, 0, ',', ' ') ?></div>
                    </div>
                    <div class="kpi">
                      <div class="l">Инвестировано (pending)</div>
                      <div class="v" data-kpi="invP">€<?= number_format($invP, 0, ',', ' ') ?></div>
                    </div>
                    <div class="kpi">
                      <div class="l">Осталось (до acquired, только approved)</div>
                      <div class="v" data-kpi="remA">€<?= number_format($remainingA, 0, ',', ' ') ?></div>
                    </div>
                    <div class="kpi">
                      <div class="l">Осталось (с учётом pending)</div>
                      <div class="v" data-kpi="remAP">€<?= number_format($remainingAP, 0, ',', ' ') ?></div>
                    </div>
                    <div class="kpi">
                      <div class="l">Партнёры / слоты</div>
                      <div class="v" data-kpi="slots"><?= (int)$agg['partners_approved'] + (int)$agg['partners_pending'] ?> / <?= (int)$p['max_partners'] ?> (свободно: <?= $slotsLeft ?>)</div>
                    </div>
                    <div class="kpi">
                      <div class="l">Аренда/год</div>
                      <div class="v">€<?= number_format((float)$p['rent_per_year'], 0, ',', ' ') ?></div>
                    </div>
                    <div class="kpi">
                      <div class="l">Доходность</div>
                      <div class="v"><?= number_format((float)$p['yield_percent'], 2, ',', ' ') ?>%</div>
                    </div>
                    <div class="kpi">
                      <div class="l">Окупаемость</div>
                      <div class="v"><?= number_format((float)$p['payback_years'], 2, ',', ' ') ?> лет</div>
                    </div>
                    <div class="kpi" style="grid-column:1/-1;">
                      <div class="l">Риски</div>
                      <div class="v" style="font-weight:500;color:var(--text-muted);"><?= h($p['risk']) ?></div>
                    </div>
                  </div>

                  <div>
                    <div class="admin-small" style="margin:0 0 8px;">Pending заявки:</div>
                    <div class="list" id="pendingList<?= (int)$pid ?>">
                      <?php foreach ($block['items'] as $it): ?>
                        <div class="item js-pending-item"
                             data-participation-id="<?= (int)$it['participation_id'] ?>"
                             data-user-id="<?= (int)$it['user_id'] ?>"
                             data-property-id="<?= (int)$pid ?>"
                             data-amount="<?= h($it['amount']) ?>"
                             style="padding:10px;">

                          <strong>#<?= (int)$it['participation_id'] ?> · <?= h($it['user_name']) ?> (<?= h($it['email']) ?>)</strong>
                          <div style="color:var(--text-muted);font-size:12px;margin-top:6px;">
                            Сумма: <span style="color:var(--text-main);font-weight:600;">€<?= number_format((float)$it['amount'],0,',',' ') ?></span>
                            · Дата: <?= h(date('Y-m-d', strtotime($it['created_at']))) ?>
                          </div>

                          <div class="item-actions">
                            <button class="btn btn-primary btn-sm js-set-status"
                                    type="button"
                                    data-id="<?= (int)$it['participation_id'] ?>"
                                    data-status="approved">Подтвердить</button>

                            <button class="btn btn-outline btn-sm js-set-status"
                                    type="button"
                                    data-id="<?= (int)$it['participation_id'] ?>"
                                    data-status="rejected">Отклонить</button>

                            <button class="btn btn-outline btn-sm js-chat-toggle" type="button">Чат</button>
                          </div>

                          <div class="chat" style="display:none;">
                            <div class="chat-head">
                              <span>Чат по объекту #<?= (int)$pid ?> · пользователь #<?= (int)$it['user_id'] ?></span>
                              <span style="opacity:.8;">(можно удалять сообщения)</span>
                            </div>
                            <div class="chat-body js-chat-body">Загрузка...</div>
                            <div class="chat-foot">
                              <input class="js-chat-input" type="text" placeholder="Сообщение заявителю...">
                              <button class="btn btn-primary btn-sm js-chat-send" type="button">Отправить</button>
                            </div>
                          </div>

                        </div>
                      <?php endforeach; ?>
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

<script>
  const CSRF = <?= json_encode($csrf, JSON_UNESCAPED_UNICODE) ?>;

  function toast(text, isError=false){
    const el = document.getElementById('toast');
    el.classList.remove('err');
    if (isError) el.classList.add('err');
    el.textContent = text;
    el.classList.add('show');
    setTimeout(() => el.classList.remove('show'), 2800);
  }

  // Tabs
  document.querySelectorAll('.admin-tab').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.admin-tab').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.admin-panel').forEach(p => p.classList.remove('active'));
      btn.classList.add('active');
      document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
    });
  });

  // -----------------------------
  // PROPERTIES: edit + save works
  // -----------------------------
  function clearSelected(){
    document.querySelectorAll('.js-prop-item').forEach(i => i.classList.remove('selected'));
  }

  function fillForm(d){
    document.getElementById('prop_id').value = d.id || '';
    document.getElementById('prop_name').value = d.name || '';
    document.getElementById('prop_location').value = d.location || '';
    document.getElementById('prop_type').value = d.type || 'residential';
    document.getElementById('prop_region').value = d.region || 'europe';
    document.getElementById('prop_status').value = d.status || 'funding';

    document.getElementById('prop_price').value = d.price ?? '';
    document.getElementById('prop_min_ticket').value = d.min_ticket ?? '';
    document.getElementById('prop_max_partners').value = d.max_partners ?? '';
    document.getElementById('prop_rent_per_year').value = d.rent_per_year ?? '';
    document.getElementById('prop_yield').value = d.yield_percent ?? '';
    document.getElementById('prop_payback').value = d.payback_years ?? '';
    document.getElementById('prop_risk').value = d.risk ?? '';
    document.getElementById('prop_description').value = d.description ?? '';

    document.getElementById('propMsg').textContent = 'Редактирование объекта #' + (d.id || '');
  }

  document.querySelectorAll('#propsList .js-prop-item').forEach(item => {
    const data = JSON.parse(item.dataset.prop || "{}");

    item.querySelector('.js-edit').addEventListener('click', () => {
      clearSelected();
      item.classList.add('selected');
      fillForm(data);
      toast('Открыт объект #' + data.id + ' для редактирования');
    });

    item.querySelector('.js-delete').addEventListener('click', async () => {
      if (!confirm('Удалить объект #' + data.id + '?')) return;

      const r = await fetch('admin_property_delete.php', {
        method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},
        body: JSON.stringify({ id: data.id })
      });
      const j = await r.json();
      if (j.success){
        item.remove();
        toast('Объект удалён');
      } else toast(j.message || 'Ошибка удаления', true);
    });
  });

  document.getElementById('propReset').addEventListener('click', () => {
    document.getElementById('propForm').reset();
    document.getElementById('prop_id').value = '';
    document.getElementById('propMsg').textContent = '';
    clearSelected();
    toast('Форма очищена');
  });

  document.getElementById('propForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const payload = {
      id: document.getElementById('prop_id').value || null,
      name: document.getElementById('prop_name').value.trim(),
      location: document.getElementById('prop_location').value.trim(),
      type: document.getElementById('prop_type').value,
      region: document.getElementById('prop_region').value,
      status: document.getElementById('prop_status').value,
      price: Number(document.getElementById('prop_price').value),
      min_ticket: Number(document.getElementById('prop_min_ticket').value),
      max_partners: Number(document.getElementById('prop_max_partners').value),
      rent_per_year: Number(document.getElementById('prop_rent_per_year').value),
      yield_percent: Number(document.getElementById('prop_yield').value),
      payback_years: Number(document.getElementById('prop_payback').value),
      risk: document.getElementById('prop_risk').value.trim(),
      description: document.getElementById('prop_description').value.trim(),
    };

    // базовая проверка (чтобы не было "ничего не происходит")
    if (!payload.name || !payload.location || !payload.price) {
      toast('Заполни обязательные поля: название, локация, цена', true);
      return;
    }

    const r = await fetch('admin_property_save.php', {
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},
      body: JSON.stringify(payload)
    });

    const j = await r.json();
    if (!j.success){
      toast(j.message || 'Ошибка сохранения', true);
      document.getElementById('propMsg').textContent = j.message || 'Ошибка';
      return;
    }

    toast('Сохранено (объект #' + j.property.id + ')');
    document.getElementById('propMsg').textContent = 'OK';

    // Обновим карточку слева без перезагрузки
    const card = document.getElementById('propCard' + j.property.id);
    if (card) {
      card.dataset.prop = JSON.stringify(j.property);
      card.querySelector('strong').textContent = '#' + j.property.id + ' · ' + j.property.name;
      const lines = card.querySelectorAll('div');
      if (lines[0]) lines[0].textContent = j.property.location + ' · €' + Number(j.property.price).toLocaleString('ru-RU');
      if (lines[1]) lines[1].textContent = 'Тип: ' + j.property.type + ' · Регион: ' + j.property.region + ' · Статус: ' + j.property.status;
    }
  });

  // -----------------------------
  // MEDIA: upload + bulk delete
  // -----------------------------
  document.getElementById('mediaUploadForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const msg = document.getElementById('mediaUploadMsg');
    msg.textContent = 'Загрузка...';

    const fd = new FormData(e.target);
    const r = await fetch('admin_media_upload.php', { method:'POST', body: fd });
    const j = await r.json();

    if (j.success) {
      msg.textContent = 'OK (media_id=' + j.media_id + ') — обнови вкладку для списка';
      toast('Фото загружено (media_id=' + j.media_id + ')');
    } else {
      msg.textContent = j.message || 'Ошибка';
      toast(j.message || 'Ошибка загрузки', true);
    }
  });

  document.getElementById('mediaSelectAll')?.addEventListener('click', () => {
    document.querySelectorAll('.mediaCheck').forEach(ch => ch.checked = true);
  });
  document.getElementById('mediaClearAll')?.addEventListener('click', () => {
    document.querySelectorAll('.mediaCheck').forEach(ch => ch.checked = false);
  });

  document.getElementById('mediaDeleteSelected')?.addEventListener('click', async () => {
    const ids = Array.from(document.querySelectorAll('.mediaCheck:checked')).map(ch => Number(ch.value)).filter(Boolean);
    if (ids.length === 0) { toast('Ничего не выбрано', true); return; }
    if (!confirm('Удалить выбранные фото: ' + ids.join(', ') + ' ?')) return;

    const r = await fetch('admin_media_bulk_delete.php', {
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},
      body: JSON.stringify({ ids })
    });
    const j = await r.json();
    if (j.success) {
      ids.forEach(id => {
        const card = document.querySelector('[data-media-id="'+id+'"]');
        if (card) card.remove();
      });
      toast('Удалено: ' + j.deleted);
      document.getElementById('mediaBulkMsg').textContent = 'Удалено: ' + j.deleted;
    } else toast(j.message || 'Ошибка удаления', true);
  });

  // -----------------------------
  // PENDING: approve/reject + update summary + chat
  // -----------------------------
  function fmtEUR(n){
    return '€' + Number(n||0).toLocaleString('ru-RU');
  }

  async function setParticipationStatus(id, status){
    const r = await fetch('admin_participation_set_status.php', {
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},
      body: JSON.stringify({ id, status })
    });
    return await r.json();
  }

  function updatePropertyNumbers(propertyCardEl, amount, newStatus){
    const price = Number(propertyCardEl.dataset.price || 0);
    let invA = Number(propertyCardEl.dataset.invApproved || 0);
    let invP = Number(propertyCardEl.dataset.invPending || 0);
    let partA = Number(propertyCardEl.dataset.partApproved || 0);
    let partP = Number(propertyCardEl.dataset.partPending || 0);

    // pending -> approved/rejected
    invP = Math.max(invP - amount, 0);
    partP = Math.max(partP - 1, 0);

    if (newStatus === 'approved') {
      invA += amount;
      partA += 1;
    }

    propertyCardEl.dataset.invApproved = String(invA);
    propertyCardEl.dataset.invPending  = String(invP);
    propertyCardEl.dataset.partApproved = String(partA);
    propertyCardEl.dataset.partPending  = String(partP);

    const remA  = Math.max(price - invA, 0);
    const remAP = Math.max(price - (invA + invP), 0);
    const maxP  = Number(propertyCardEl.dataset.maxPartners || 0);
    const used  = partA + partP;
    const free  = Math.max(maxP - used, 0);

    propertyCardEl.querySelector('[data-kpi="invA"]').textContent = fmtEUR(invA);
    propertyCardEl.querySelector('[data-kpi="invP"]').textContent = fmtEUR(invP);
    propertyCardEl.querySelector('[data-kpi="remA"]').textContent = fmtEUR(remA);
    propertyCardEl.querySelector('[data-kpi="remAP"]').textContent = fmtEUR(remAP);
    propertyCardEl.querySelector('[data-kpi="slots"]').textContent = used + ' / ' + maxP + ' (свободно: ' + free + ')';
  }

  document.querySelectorAll('.js-set-status').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id = Number(btn.dataset.id);
      const status = btn.dataset.status;
      const item = btn.closest('.js-pending-item');
      const propertyId = Number(item.dataset.propertyId);
      const amount = Number(item.dataset.amount || 0);

      const res = await setParticipationStatus(id, status);
      if (!res.success) { toast(res.message || 'Ошибка', true); return; }

      // убрать карточку pending
      item.remove();

      // обновить цифры по объекту
      const propertyCard = document.querySelector('.item[data-property-id="'+propertyId+'"]');
      if (propertyCard) updatePropertyNumbers(propertyCard, amount, status);

      toast(status === 'approved' ? ('Заявка #' + id + ' подтверждена') : ('Заявка #' + id + ' отклонена'));
    });
  });

  // CHAT: list/send/delete
  async function chatList(propertyId, userId){
    const r = await fetch('admin_chat_list.php', {
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},
      body: JSON.stringify({ property_id: propertyId, user_id: userId })
    });
    return await r.json();
  }

  async function chatSend(propertyId, userId, message){
    const r = await fetch('admin_chat_send.php', {
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},
      body: JSON.stringify({ property_id: propertyId, user_id: userId, message })
    });
    return await r.json();
  }

  async function chatDelete(messageId){
    const r = await fetch('admin_chat_delete.php', {
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},
      body: JSON.stringify({ id: messageId })
    });
    return await r.json();
  }

  function renderChatBody(bodyEl, messages){
    bodyEl.innerHTML = '';
    if (!messages || messages.length === 0){
      bodyEl.textContent = 'Нет сообщений';
      return;
    }
    messages.forEach(m => {
      const div = document.createElement('div');
      div.className = 'msg';
      div.innerHTML = `
        <div class="msg-row">
          <div>
            <strong>${m.sender === 'admin' ? 'Admin' : 'Partner'}</strong>
            <span style="opacity:.8;"> · ${m.created_at}</span>
          </div>
          <button class="msg-del" type="button" data-mid="${m.id}">Удалить</button>
        </div>
        <div style="margin-top:6px;white-space:pre-wrap;">${escapeHtml(m.message)}</div>
      `;
      bodyEl.appendChild(div);
    });

    bodyEl.querySelectorAll('.msg-del').forEach(b => {
      b.addEventListener('click', async () => {
        const mid = Number(b.dataset.mid);
        const res = await chatDelete(mid);
        if (!res.success){ toast(res.message || 'Ошибка удаления', true); return; }
        // обновим список после удаления
        const wrap = bodyEl.closest('.js-pending-item');
        const pid = Number(wrap.dataset.propertyId);
        const uid = Number(wrap.dataset.userId);
        const data = await chatList(pid, uid);
        if (data.success) renderChatBody(bodyEl, data.messages);
      });
    });
  }

  function escapeHtml(s){
    return String(s)
      .replaceAll('&','&amp;')
      .replaceAll('<','&lt;')
      .replaceAll('>','&gt;')
      .replaceAll('"','&quot;')
      .replaceAll("'","&#039;");
  }

  document.querySelectorAll('.js-chat-toggle').forEach(btn => {
    btn.addEventListener('click', async () => {
      const item = btn.closest('.js-pending-item');
      const box = item.querySelector('.chat');
      const body = item.querySelector('.js-chat-body');

      const pid = Number(item.dataset.propertyId);
      const uid = Number(item.dataset.userId);

      box.style.display = (box.style.display === 'none' || !box.style.display) ? 'block' : 'none';
      if (box.style.display === 'block') {
        body.textContent = 'Загрузка...';
        const data = await chatList(pid, uid);
        if (!data.success){ body.textContent = data.message || 'Ошибка'; return; }
        renderChatBody(body, data.messages);
      }
    });
  });

  document.querySelectorAll('.js-chat-send').forEach(btn => {
    btn.addEventListener('click', async () => {
      const item = btn.closest('.js-pending-item');
      const input = item.querySelector('.js-chat-input');
      const body = item.querySelector('.js-chat-body');

      const pid = Number(item.dataset.propertyId);
      const uid = Number(item.dataset.userId);
      const text = (input.value || '').trim();
      if (!text){ toast('Сообщение пустое', true); return; }

      const res = await chatSend(pid, uid, text);
      if (!res.success){ toast(res.message || 'Ошибка отправки', true); return; }

      input.value = '';
      const data = await chatList(pid, uid);
      if (data.success) renderChatBody(body, data.messages);
      toast('Сообщение отправлено');
    });
  });
</script>
</body>
</html>
