// -------- Utils ----------
function formatCurrency(value) {
  return "€" + Number(value).toLocaleString("ru-RU");
}
function formatPercent(value) {
  return Number(value).toFixed(1).replace(".", ",") + "%";
}

// -------- State ----------
const state = {
  properties: [],
  selectedId: null,
  filter: "all",
  user: null
};

const elements = {};

// -------- API ----------
async function apiGetMe() {
  const r = await fetch("../api/me.php", { method: "GET" });
  const j = await r.json();
  state.user = j.user || null;
  return state.user;
}

async function loadProperties() {
  const r = await fetch("../api/properties.php", { method: "GET" });
  const j = await r.json();
  state.properties = Array.isArray(j.properties) ? j.properties : [];
}

// -------- DOM init ----------
document.addEventListener("DOMContentLoaded", async () => {
  cacheElements();
  initFilters();
  initToast();
  initModals();
  initParticipationForm();

  await apiGetMe();
  await loadProperties();

  renderProperties();
  if (state.properties.length > 0) {
    selectProperty(state.properties[0].id);
  }

  // если не залогинен — блокируем участие
  toggleParticipationAvailability();
});

// -------- Cache ----------
function cacheElements() {
  elements.propertiesList = document.getElementById("propertiesList");
  elements.filterPills = document.querySelectorAll(".filter-pill");

  elements.detailsName = document.getElementById("detailsName");
  elements.detailsLocation = document.getElementById("detailsLocation");
  elements.detailsTags = document.getElementById("detailsTags");
  elements.detailsPrice = document.getElementById("detailsPrice");

  elements.galleryTitle = document.getElementById("galleryTitle");
  elements.galleryMeta = document.getElementById("galleryMeta");
  elements.galleryType = document.getElementById("galleryType");
  elements.galleryStatus = document.getElementById("galleryStatus");

  elements.detailsDescription = document.getElementById("detailsDescription");

  elements.metricRent = document.getElementById("metricRent");
  elements.metricYield = document.getElementById("metricYield");
  elements.metricPayback = document.getElementById("metricPayback");
  elements.metricRisk = document.getElementById("metricRisk");
  elements.economicsNote = document.getElementById("economicsNote");
  elements.minTicketLabel = document.getElementById("minTicketLabel");

  elements.progressInner = document.getElementById("progressInner");
  elements.progressCollected = document.getElementById("progressCollected");
  elements.progressRemaining = document.getElementById("progressRemaining");
  elements.participantsCount = document.getElementById("participantsCount");
  elements.slotsRemaining = document.getElementById("slotsRemaining");
  elements.shareInfo = document.getElementById("shareInfo");

  elements.investmentAmount = document.getElementById("investmentAmount");
  elements.errorAmount = document.getElementById("errorAmount");
  elements.participationForm = document.getElementById("participationForm");
  elements.participateBtn = document.getElementById("participateBtn");

  elements.loginBtn = document.getElementById("loginBtn");
  elements.registerBtn = document.getElementById("registerBtn");
  elements.loginModal = document.getElementById("loginModal");
  elements.registerModal = document.getElementById("registerModal");

  // gallery
  elements.detailsMainImage = document.getElementById("detailsMainImage");
  elements.detailsThumbs = document.getElementById("detailsThumbs");
}

// -------- Filters ----------
function initFilters() {
  elements.filterPills.forEach(pill => {
    pill.addEventListener("click", () => {
      elements.filterPills.forEach(p => p.classList.remove("active"));
      pill.classList.add("active");
      state.filter = pill.dataset.filter || "all";
      renderProperties();
    });
  });
}

// -------- Render list ----------
function renderProperties() {
  if (!elements.propertiesList) return;
  elements.propertiesList.innerHTML = "";

  state.properties.forEach(p => {
    if (state.filter === "residential" && p.type !== "residential") return;
    if (state.filter === "commercial" && p.type !== "commercial") return;
    if (state.filter === "europe" && p.region !== "europe") return;
    if (state.filter === "middleeast" && p.region !== "middleeast") return;

    const remaining = Math.max(p.price - (p.invested || 0), 0);

    const card = document.createElement("div");
    card.className = "property-card";
    card.dataset.id = p.id;

    // красивый thumb v2 (без вылезания текста)
    card.innerHTML = `
      <div class="property-thumb property-thumb--v2">
        <div class="thumb-top">
          <span class="thumb-pill">${p.type === "residential" ? "Жилая" : "Коммерческая"}</span>
          <span class="thumb-pill thumb-pill--muted">${p.region === "europe" ? "Европа" : "Ближний Восток"}</span>
        </div>

        <div class="thumb-stats">
          <div class="thumb-stat">
            <div class="thumb-stat-label">Доходность</div>
            <div class="thumb-stat-value">${formatPercent(p.yield_percent)}</div>
          </div>
          <div class="thumb-stat">
            <div class="thumb-stat-label">Окупаемость</div>
            <div class="thumb-stat-value">${String(p.payback_years).replace(".", ",")} лет</div>
          </div>
        </div>
      </div>

      <div>
        <div class="property-card-title">${p.name}</div>
        <div class="property-card-location">${p.location}</div>
        <div class="property-card-meta">
          <div class="property-card-price">${formatCurrency(p.price)}</div>
          <div class="property-card-yield">Доходность ~ ${formatPercent(p.yield_percent)}</div>
          <div class="property-card-remaining">Осталось собрать: <strong>${formatCurrency(remaining)}</strong></div>
        </div>
      </div>
    `;

    card.addEventListener("click", () => selectProperty(p.id));

    if (state.selectedId === p.id) {
      card.style.outline = "1px solid rgba(79, 70, 229, 0.9)";
    }

    elements.propertiesList.appendChild(card);
  });
}

// -------- Select ----------
function selectProperty(id) {
  const property = state.properties.find(p => Number(p.id) === Number(id));
  if (!property) return;
  state.selectedId = property.id;

  document.querySelectorAll(".property-card").forEach(card => {
    card.style.outline =
      Number(card.dataset.id) === Number(id)
        ? "1px solid rgba(79, 70, 229, 0.9)"
        : "none";
  });

  elements.detailsName.textContent = property.name;
  elements.detailsLocation.textContent = property.location;
  elements.detailsPrice.textContent = formatCurrency(property.price);

  // tags
  elements.detailsTags.innerHTML = "";
  const tags = [];
  tags.push(property.type === "residential" ? "Жилая недвижимость" : "Коммерческий объект");
  tags.push(property.region === "europe" ? "Европа" : "Ближний Восток");
  if (property.risk) tags.push(property.risk);

  tags.forEach(t => {
    const el = document.createElement("span");
    el.className = "details-tag";
    el.textContent = t;
    elements.detailsTags.appendChild(el);
  });

  // status
  const remaining = Math.max(property.price - (property.invested || 0), 0);
  elements.galleryStatus.textContent = remaining <= 0 ? "Полностью профинансирован" : "Идёт сбор долей";

  elements.galleryTitle.textContent = property.name;
  elements.galleryMeta.textContent = "Фотографии загружаются администратором через админ-панель.";
  elements.galleryType.textContent = property.type === "residential" ? "Жилая недвижимость" : "Коммерческий объект";

  // description
  elements.detailsDescription.textContent = property.description || "";

  // economics
  elements.metricRent.textContent = formatCurrency(property.rent_per_year) + " / год";
  elements.metricYield.textContent = "Ожидаемая доходность ~ " + formatPercent(property.yield_percent);
  elements.metricPayback.textContent = String(property.payback_years).replace(".", ",") + " лет";
  elements.metricRisk.textContent = property.risk || "—";
  elements.economicsNote.textContent = "Расчёты ориентировочные и не являются инвестиционной рекомендацией.";

  elements.minTicketLabel.textContent = "Мин. взнос: " + formatCurrency(property.min_ticket);

  // progress
  const collected = property.invested || 0;
  const percent = property.price > 0 ? Math.min((collected / property.price) * 100, 100) : 0;
  elements.progressInner.style.width = percent + "%";
  elements.progressCollected.textContent = "Собрано: " + formatCurrency(collected) + " (" + percent.toFixed(0) + "%)";
  elements.progressRemaining.textContent = "Осталось: " + formatCurrency(remaining);

  const used = property.participants || 0;
  const total = property.max_partners || 0;
  elements.participantsCount.textContent = used + " из " + total;
  elements.slotsRemaining.textContent = Math.max(total - used, 0);

  elements.shareInfo.textContent = "Укажите сумму участия, чтобы увидеть вашу долю";
  if (elements.investmentAmount) elements.investmentAmount.value = "";
  if (elements.errorAmount) elements.errorAmount.textContent = "";

  renderMedia(property);
  updateShareInfo();
  toggleParticipationAvailability();
}

// -------- Media gallery ----------
function renderMedia(property) {
  if (!elements.detailsMainImage || !elements.detailsThumbs) return;

  const media = Array.isArray(property.media) ? property.media : [];
  const valid = media.filter(m => m && m.url);

  if (valid.length === 0) {
    elements.detailsMainImage.removeAttribute('src');
    elements.detailsMainImage.alt = '';
    elements.detailsThumbs.innerHTML = '';
    return;
  }

  const setMain = (m) => {
    elements.detailsMainImage.src = m.url;
    elements.detailsMainImage.alt = m.caption || property.name || '';
  };

  setMain(valid[0]);

  elements.detailsThumbs.innerHTML = '';
  valid.slice(0, 10).forEach((m) => {
    const d = document.createElement('div');
    d.className = 'details-thumb';
    d.innerHTML = `<img src="${m.url}" alt="">`;
    d.addEventListener('click', () => setMain(m));
    elements.detailsThumbs.appendChild(d);
  });
}


// -------- Toast ----------
let toastTimeout = null;
function initToast() {
  elements.successToast = document.getElementById("successToast");
  elements.toastCloseBtn = document.getElementById("toastCloseBtn");
  if (elements.toastCloseBtn) {
    elements.toastCloseBtn.addEventListener("click", hideToast);
  }
}

function showToast() {
  if (!elements.successToast) return;
  elements.successToast.classList.add("visible");
  if (toastTimeout) clearTimeout(toastTimeout);
  toastTimeout = setTimeout(hideToast, 4500);
}

function hideToast() {
  if (!elements.successToast) return;
  elements.successToast.classList.remove("visible");
}

// -------- Modals ----------
function initModals() {
  const closeButtons = document.querySelectorAll("[data-modal-close]");

  if (elements.loginBtn && elements.loginModal) {
    elements.loginBtn.addEventListener("click", () => openModal(elements.loginModal));
  }
  if (elements.registerBtn && elements.registerModal) {
    elements.registerBtn.addEventListener("click", () => openModal(elements.registerModal));
  }

  closeButtons.forEach(btn => {
    btn.addEventListener("click", () => {
      const backdrop = btn.closest(".modal-backdrop");
      if (backdrop) closeModal(backdrop);
    });
  });

  [elements.loginModal, elements.registerModal].forEach(backdrop => {
    if (!backdrop) return;
    backdrop.addEventListener("click", (e) => {
      if (e.target === backdrop) closeModal(backdrop);
    });
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      [elements.loginModal, elements.registerModal].forEach(b => b && closeModal(b));
    }
  });
}

function openModal(backdrop) {
  backdrop.classList.add("open");
}
function closeModal(backdrop) {
  backdrop.classList.remove("open");
}

// -------- Participation ----------
function initParticipationForm() {
  if (!elements.participationForm || !elements.investmentAmount) return;

  elements.investmentAmount.addEventListener("input", updateShareInfo);

  elements.participationForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    await submitParticipation();
  });
}

function toggleParticipationAvailability() {
  const logged = !!state.user;
  if (elements.investmentAmount) elements.investmentAmount.disabled = !logged;
  if (elements.participateBtn) elements.participateBtn.disabled = !logged;
}

function updateShareInfo() {
  const property = state.properties.find(p => Number(p.id) === Number(state.selectedId));
  if (!property) {
    if (elements.shareInfo) elements.shareInfo.textContent = "Выберите объект";
    return;
  }

  const val = parseFloat(elements.investmentAmount?.value || "");
  if (!val || val <= 0) {
    if (elements.shareInfo) elements.shareInfo.textContent = "Укажите сумму участия";
    return;
  }

  const percent = property.price > 0 ? (val / property.price * 100) : 0;
  if (elements.shareInfo) elements.shareInfo.textContent = "≈ " + percent.toFixed(2).replace(".", ",") + "% от объекта";
}

async function submitParticipation() {
  if (!state.user) {
    if (elements.loginModal) openModal(elements.loginModal);
    return;
  }

  const property = state.properties.find(p => Number(p.id) === Number(state.selectedId));
  if (!property) return;

  elements.errorAmount.textContent = "";
  const amount = parseFloat(elements.investmentAmount.value);

  if (!amount || amount <= 0) {
    elements.errorAmount.textContent = "Введите сумму участия.";
    return;
  }
  if (amount < property.min_ticket) {
    elements.errorAmount.textContent = "Минимальная сумма: " + formatCurrency(property.min_ticket);
    return;
  }

  const remaining = Math.max(property.price - (property.invested || 0), 0);
  if (amount > remaining) {
    elements.errorAmount.textContent = "Сумма больше остатка для сбора.";
    return;
  }

  elements.participateBtn.disabled = true;

  try {
    const response = await fetch("../api/participation.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": window.CSRF_TOKEN
      },
      body: JSON.stringify({ property_id: property.id, amount })
    });

    const data = await response.json();

    if (!data.success) {
      elements.errorAmount.textContent = data.message || "Ошибка сохранения участия";
      return;
    }

    // обновим объект в state
    const updated = data.property;
    const idx = state.properties.findIndex(x => Number(x.id) === Number(updated.id));
    if (idx !== -1) state.properties[idx] = updated;

    renderProperties();
    selectProperty(updated.id);

    elements.investmentAmount.value = "";
    updateShareInfo();
    showToast();
  } catch (err) {
    console.error(err);
    elements.errorAmount.textContent = "Ошибка сети. Попробуйте ещё раз.";
  } finally {
    elements.participateBtn.disabled = false;
  }
}
