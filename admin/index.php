<?php
require_once __DIR__ . '/../project/db.php';
require_once __DIR__ . '/../project/csrf.php';

if (empty($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
  header('Location: ../project/index.php');
  exit;
}

$csrf = csrf_get_token();

$props = [];
$res = mysqli_query($conn, "SELECT id, name, location, price, status, type, region FROM properties ORDER BY id DESC");
while ($res && ($r = mysqli_fetch_assoc($res))) $props[] = $r;

$pending = [];
$sqlP = "SELECT part.id, part.amount, part.status, part.created_at, u.name AS user_name, u.email,
                p.name AS property_name, p.id AS property_id
         FROM participations part
         JOIN users u ON u.id = part.user_id
         JOIN properties p ON p.id = part.property_id
         WHERE part.status='pending'
         ORDER BY part.created_at DESC";
$resP = mysqli_query($conn, $sqlP);
while ($resP && ($r = mysqli_fetch_assoc($resP))) $pending[] = $r;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin – RealEstate Share</title>
  <link rel="stylesheet" href="../project/styles.css">
  <style>
    .admin-wrap{max-width:1120px;margin:24px auto;padding:0 20px 40px;}
    .admin-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:12px 0;}
    .admin-tab{padding:7px 12px;border-radius:999px;border:1px solid rgba(55,65,81,.9);background:rgba(15,23,42,.95);color:var(--text-muted);cursor:pointer;font-size:12px;}
    .admin-tab.active{border-color:var(--accent);color:var(--accent);background:var(--accent-soft);}
    .admin-panel{display:none;}
    .admin-panel.active{display:block;}
    .admin-grid{display:grid;grid-template-columns:1.1fr 0.9fr;gap:12px;}
    @media (max-width:900px){.admin-grid{grid-template-columns:1fr;}}
    .admin-box{border-radius:16px;background:rgba(15,23,42,.95);border:1px solid rgba(55,65,81,.9);padding:12px;}
    .admin-title{font-size:16px;font-weight:600;margin:0 0 10px;}
    .admin-small{color:var(--text-muted);font-size:12px;margin-bottom:10px;}
    .row{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
    .row3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;}
    .list{display:flex;flex-direction:column;gap:8px;}
    .item{border:1px solid rgba(55,65,81,.9);border-radius:14px;padding:10px;background:rgba(15,23,42,.95);transition:transform 160ms ease-out, border-color 160ms ease-out, box-shadow 160ms ease-out;}
    .item:hover{transform:translateY(-1px);border-color:rgba(148,163,184,.7);}
    .item strong{display:block;margin-bottom:4px;}
    .item-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;}

    /* ВЫДЕЛЕНИЕ выбранной карточки для редактирования */
    .item.selected{
      border-color: rgba(79,70,229,0.9);
      box-shadow: 0 18px 40px rgba(15,23,42,0.9);
      background: radial-gradient(circle at top left, rgba(79,70,229,0.22), transparent 60%),
                  rgba(15,23,42,0.98);
    }
    .item.selected .badge-editing{
      display:inline-flex;
    }
    .badge-editing{
      display:none;
      margin-top:6px;
      font-size:11px;
      padding:4px 8px;
      border-radius:999px;
      background: rgba(79,70,229,0.14);
      border: 1px solid rgba(79,70,229,0.6);
      color: #c7d2fe;
      width: fit-content;
    }

    .inline{display:flex;gap:8px;align-items:center;flex-wrap:wrap;}
    select{background:rgba(15,23,42,.95);border-radius:10px;border:1px solid rgba(55,65,81,.9);padding:7px 9px;color:var(--text-main);font-size:12px;outline:none;}
    textarea{min-height:90px;resize:vertical;}

    .admin-toast{
      margin-top:10px;
      font-size:12px;
      color:#bbf7d0;
      border:1px solid rgba(34,197,94,.7);
      background:rgba(22,163,74,.14);
      padding:8px 10px;
      border-radius:12px;
      display:none;
    }
    .admin-toast.err{
      color:#fecaca;
      border-color:rgba(239,68,68,.7);
      background:rgba(239,68,68,.12);
    }
    .admin-toast.show{display:block;}
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
        <span class="nav-user">Админ: <?= htmlspecialchars($_SESSION['user_name']) ?></span>
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

    <!-- Объекты -->
    <section class="admin-panel active" id="tab-properties">
      <div class="admin-grid">
        <div class="admin-box">
          <div class="admin-title">Список объектов</div>
          <div class="admin-small">Нажми «Редактировать» — карточка выделится. Удаление удаляет объект и связанные данные.</div>

          <div class="list" id="propsList">
            <?php foreach ($props as $p): ?>
              <div class="item js-prop-item"
                   data-prop='<?= htmlspecialchars(json_encode($p, JSON_UNESCAPED_UNICODE)) ?>'>
                <strong>#<?= (int)$p['id'] ?> · <?= htmlspecialchars($p['name']) ?></strong>
                <div style="color:var(--text-muted);font-size:12px;">
                  <?= htmlspecialchars($p['location']) ?> · €<?= number_format((float)$p['price'], 0, ',', ' ') ?>
                </div>
                <div style="margin-top:6px;color:var(--text-muted);font-size:12px;">
                  Тип: <?= htmlspecialchars($p['type']) ?> · Регион: <?= htmlspecialchars($p['region']) ?> · Статус: <?= htmlspecialchars($p['status']) ?>
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
          <div class="admin-small">Сохранение через admin_property_save.php</div>

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
              <label>Риск</label>
              <input type="text" id="prop_risk" required>
            </div>

            <div class="form-row">
              <label>Описание</label>
              <textarea id="prop_description" required></textarea>
            </div>

            <div class="inline">
              <button class="btn btn-primary btn-sm" type="submit">Сохранить</button>
              <button class="btn btn-outline btn-sm" type="button" id="propReset">Очистить</button>
              <span style="color:var(--text-muted);font-size:12px;" id="propMsg"></span>
            </div>
          </form>
        </div>
      </div>
    </section>

    <!-- Медиа -->
    <section class="admin-panel" id="tab-media">
      <div class="admin-box">
        <div class="admin-title">Загрузка фото объекта</div>
        <div class="admin-small">Разрешены JPG/PNG/WebP до 6MB. Файлы сохраняются в /uploads.</div>

        <form id="mediaForm" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
          <div class="row">
            <div class="form-row">
              <label>Объект</label>
              <select name="property_id" required>
                <?php foreach ($props as $p): ?>
                  <option value="<?= (int)$p['id'] ?>">#<?= (int)$p['id'] ?> · <?= htmlspecialchars($p['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-row">
              <label>Порядок (sort_order)</label>
              <input type="number" name="sort_order" value="0">
            </div>
          </div>

          <div class="form-row">
            <label>Подпись (caption)</label>
            <input type="text" name="caption" placeholder="Например: Вид с балкона">
          </div>

          <div class="form-row">
            <label>Файл</label>
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required>
          </div>

          <div class="inline">
            <button class="btn btn-primary btn-sm" type="submit">Загрузить</button>
            <span id="mediaMsg" style="color:var(--text-muted);font-size:12px;"></span>
          </div>
        </form>

        <hr style="border:none;border-top:1px solid rgba(55,65,81,.7);margin:14px 0;">

        <div class="admin-title" style="font-size:14px;">Удаление медиа</div>
        <div class="admin-small">Введи ID медиа (property_media.id)</div>

        <div class="inline">
          <input type="number" id="mediaDeleteId" placeholder="media_id" style="max-width:160px;">
          <button class="btn btn-outline btn-sm" id="mediaDeleteBtn" type="button">Удалить</button>
          <span id="mediaDeleteMsg" style="color:var(--text-muted);font-size:12px;"></span>
        </div>
      </div>
    </section>

    <!-- Участия -->
    <section class="admin-panel" id="tab-participations">
      <div class="admin-box">
        <div class="admin-title">Заявки на участие (pending)</div>
        <div class="admin-small">После подтверждения/отклонения запись исчезает из списка (без перезагрузки).</div>

        <?php if (empty($pending)): ?>
          <div class="details-description">Нет заявок со статусом pending.</div>
        <?php else: ?>
          <div class="list" id="pendingList">
            <?php foreach ($pending as $x): ?>
              <div class="item js-pending-item" data-id="<?= (int)$x['id'] ?>">
                <strong>#<?= (int)$x['id'] ?> · <?= htmlspecialchars($x['user_name']) ?> (<?= htmlspecialchars($x['email']) ?>)</strong>
                <div style="color:var(--text-muted);font-size:12px;">
                  Объект: #<?= (int)$x['property_id'] ?> · <?= htmlspecialchars($x['property_name']) ?>
                </div>
                <div style="margin-top:6px;color:var(--text-muted);font-size:12px;">
                  Сумма: <span style="color:var(--text-main);font-weight:600;">€<?= number_format((float)$x['amount'], 0, ',', ' ') ?></span>
                  · Дата: <?= htmlspecialchars(date('Y-m-d', strtotime($x['created_at']))) ?>
                </div>

                <div class="item-actions">
                  <button class="btn btn-primary btn-sm js-set-status" type="button"
                          data-id="<?= (int)$x['id'] ?>" data-status="approved">Подтвердить</button>
                  <button class="btn btn-outline btn-sm js-set-status" type="button"
                          data-id="<?= (int)$x['id'] ?>" data-status="rejected">Отклонить</button>
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

  // Выделение выбранного объекта
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
    document.getElementById('prop_price').value = d.price || '';
    document.getElementById('propMsg').textContent = 'Заполни остальные поля и нажми "Сохранить".';
  }

  // Property actions
  document.querySelectorAll('#propsList .js-prop-item').forEach(item => {
    const data = JSON.parse(item.dataset.prop || "{}");

    item.querySelector('.js-edit').addEventListener('click', () => {
      clearSelected();
      item.classList.add('selected');
      fillForm(data);
      toast('Редактирование объекта #' + data.id);
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
      } else {
        toast(j.message || 'Ошибка удаления', true);
      }
    });
  });

  // Form save
  const f = document.getElementById('propForm');
  f.addEventListener('submit', async (e) => {
    e.preventDefault();
    document.getElementById('propMsg').textContent = 'Сохранение...';

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

    const r = await fetch('admin_property_save.php', {
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},
      body: JSON.stringify(payload)
    });
    const j = await r.json();
    if (j.success){
      toast('Сохранено. Обнови страницу, чтобы увидеть в списке новые значения.');
      document.getElementById('propMsg').textContent = 'OK';
    } else {
      toast(j.message || 'Ошибка сохранения', true);
      document.getElementById('propMsg').textContent = j.message || 'Ошибка';
    }
  });

  document.getElementById('propReset').addEventListener('click', () => {
    f.reset();
    document.getElementById('prop_id').value = '';
    document.getElementById('propMsg').textContent = '';
    clearSelected();
    toast('Форма очищена');
  });

  // Media upload
  const mediaForm = document.getElementById('mediaForm');
  mediaForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const mediaMsg = document.getElementById('mediaMsg');
    mediaMsg.textContent = 'Загрузка...';

    const fd = new FormData(mediaForm);
    const r = await fetch('admin_media_upload.php', { method:'POST', body: fd });
    const j = await r.json();

    if (j.success){
      mediaMsg.textContent = 'OK (media_id=' + j.media_id + ')';
      toast('Фото загружено (media_id=' + j.media_id + ')');
    } else {
      mediaMsg.textContent = j.message || 'Ошибка';
      toast(j.message || 'Ошибка загрузки', true);
    }
  });

  // Media delete
  document.getElementById('mediaDeleteBtn').addEventListener('click', async () => {
    const id = Number(document.getElementById('mediaDeleteId').value);
    if (!id) return;

    const r = await fetch('admin_media_delete.php', {
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},
      body: JSON.stringify({ id })
    });
    const j = await r.json();
    document.getElementById('mediaDeleteMsg').textContent = j.success ? 'Удалено' : (j.message || 'Ошибка');
    toast(j.success ? 'Медиа удалено' : (j.message || 'Ошибка удаления'), !j.success);
  });

  // Participation approve/reject + удаление строки сразу
  document.querySelectorAll('.js-set-status').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id = Number(btn.dataset.id);
      const status = btn.dataset.status;

      const r = await fetch('admin_participation_set_status.php', {
        method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},
        body: JSON.stringify({ id, status })
      });
      const j = await r.json();

      if (j.success){
        const el = document.querySelector('.js-pending-item[data-id="'+id+'"]');
        if (el) el.remove();
        toast(status === 'approved' ? 'Заявка подтверждена (#'+id+')' : 'Заявка отклонена (#'+id+')');
      } else {
        toast(j.message || 'Ошибка', true);
      }
    });
  });
</script>
</body>
</html>
