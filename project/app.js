// project/app.js (v3)
(() => {
  console.log("app.js v3 loaded");
  document.documentElement.dataset.appjs = "v3";

  const state = {
    properties: [],
    filtered: [],
    selectedId: null,
    expandedId: null,
  };

  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

  const esc = (s) =>
    String(s ?? "").replace(/[&<>"']/g, (c) => ({
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#39;",
    }[c]));

  const eur = (n) => {
    const v = Number(n || 0);
    return "€" + v.toLocaleString("ru-RU", { maximumFractionDigits: 0 });
  };

  // api/properties.php относительно /project/index.php
  function apiUrl() {
    // если открываешь /project/index.php -> ../api/properties.php
    return "../api/properties.php";
  }

  function injectCss() {
    if ($("#reShareExpandCss")) return;
    const st = document.createElement("style");
    st.id = "reShareExpandCss";
    st.textContent = `
      .prop-card{ position:relative; }
      .prop-arrow{
        position:absolute; right:12px; top:12px;
        width:34px; height:34px; border-radius:14px;
        border:1px solid rgba(255,255,255,.14);
        background:rgba(2,6,23,.22);
        display:flex; align-items:center; justify-content:center;
        cursor:pointer;
      }
      .prop-arrow svg{ width:18px; height:18px; animation: reBounce 1.2s ease-in-out infinite; }
      .prop-card.expanded .prop-arrow svg{ transform: rotate(180deg); animation:none; }
      @keyframes reBounce { 0%,100%{ transform:translateY(0);} 50%{ transform:translateY(5px);} }

      .prop-expand{ display:none; padding:12px 14px 14px; border-top:1px solid rgba(255,255,255,.10); margin-top:10px; }
      .prop-card.expanded .prop-expand{ display:block; }

      .prop-box{
        border-radius:14px;
        border:1px solid rgba(255,255,255,.10);
        background:rgba(2,6,23,.18);
        padding:10px;
      }
      .prop-k{ font-size:11px; opacity:.75; letter-spacing:.08em; text-transform:uppercase; }
      .prop-v{ margin-top:6px; font-size:13px; font-weight:650; }

      .prop-expand-kv{ display:grid; grid-template-columns: 1fr 1fr; gap:10px; }
      @media(max-width:760px){ .prop-expand-kv{ grid-template-columns:1fr; } }

      .prop-gallery{ display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; }
      @media(max-width:980px){ .prop-gallery{ grid-template-columns:repeat(2,minmax(0,1fr)); } }
      @media(max-width:520px){ .prop-gallery{ grid-template-columns:1fr; } }
      .prop-gallery img{
        width:100%; height:150px; object-fit:cover;
        border-radius:14px; border:1px solid rgba(255,255,255,.10);
        background:rgba(2,6,23,.25);
      }

      /* модалки: поддержим и .open и .active */
      .modal-backdrop.open, .modal-backdrop.active{ display:flex !important; }
    `;
    document.head.appendChild(st);
  }

  async function loadProperties() {
    const r = await fetch(apiUrl(), { method: "GET", cache: "no-store" });
    const j = await r.json();
    state.properties = Array.isArray(j.properties) ? j.properties : [];
    state.filtered = state.properties.slice();
  }

  function initFilters() {
    const pills = $$(".filter-pill");
    pills.forEach((p) => {
      p.addEventListener("click", () => {
        pills.forEach((x) => x.classList.remove("active"));
        p.classList.add("active");

        const f = p.dataset.filter || "all";
        state.filtered = state.properties.filter((pr) => {
          if (f === "all") return true;
          if (f === "residential" || f === "commercial") return pr.type === f;
          if (f === "europe" || f === "middleeast") return pr.region === f;
          return true;
        });

        renderList();
        if (state.filtered.length) selectProperty(state.filtered[0].id);
      });
    });
  }

  function renderList() {
    const root = $("#propertiesList");
    if (!root) return;

    root.innerHTML = state.filtered.map((p) => {
      const media = Array.isArray(p.media) ? p.media : [];
      const cover = p.cover_url || (media[0] ? media[0].url : "");
      const price = Number(p.price || 0);
      const invested = Number(p.invested || 0);
      const remaining = Math.max(price - invested, 0);
      const prog = price > 0 ? Math.min(100, Math.round((invested / price) * 100)) : 0;

      const typeLabel = p.type === "commercial" ? "Коммерческая" : "Жилая";
      const y = Number(p.yield_percent || 0).toFixed(2) + "%";
      const pay = Number(p.payback_years || 0).toFixed(1) + " лет";

      return `
        <div class="property-card prop-card ${state.selectedId === p.id ? "active" : ""} ${state.expandedId === p.id ? "expanded" : ""}"
             data-id="${p.id}"
             style="display:grid;gap:10px;">
          
          <!-- верх карточки -->
          <div style="display:flex;gap:12px;align-items:center;">
            <!-- улучшенная синяя плашка (не вылазит текст) -->
            <div style="
              flex:0 0 140px;
              padding:10px; border-radius:14px;
              border:1px solid rgba(255,255,255,.12);
              background:linear-gradient(180deg, rgba(37,99,235,.22), rgba(2,6,23,.12));
              overflow:hidden;
            ">
              <div style="font-size:11px;letter-spacing:.08em;text-transform:uppercase;opacity:.9;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                ${esc(typeLabel)}
              </div>
              <div style="margin-top:6px;font-size:18px;font-weight:750;line-height:1.05;white-space:nowrap;">
                ${esc(y)}
              </div>
              <div style="margin-top:6px;font-size:11px;opacity:.85;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                окупаемость ${esc(pay)}
              </div>
            </div>

            <!-- превью + инфо -->
            <div style="display:grid;grid-template-columns:92px 1fr;gap:10px;align-items:center;width:100%;">
              ${
                cover
                  ? `<img src="${esc(cover)}" alt="" style="width:92px;height:66px;object-fit:cover;border-radius:12px;border:1px solid rgba(255,255,255,.10);background:rgba(2,6,23,.25);">`
                  : `<div style="width:92px;height:66px;border-radius:12px;border:1px solid rgba(255,255,255,.10);background:rgba(2,6,23,.25);"></div>`
              }
              <div>
                <div class="property-name" style="font-weight:650;font-size:13px;">
                  ${esc(p.name)}
                </div>
                <div class="property-location" style="margin-top:4px;opacity:.75;font-size:12px;">
                  ${esc(p.location || "")}
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:8px;opacity:.78;font-size:12px;">
                  <span><strong style="opacity:1;">${eur(price)}</strong> цена</span>
                  <span><strong style="opacity:1;">${eur(remaining)}</strong> осталось</span>
                  <span>${prog}% собрано</span>
                </div>
              </div>
            </div>
          </div>

          <!-- стрелка -->
          <div class="prop-arrow" title="Раскрыть / свернуть">
            <svg viewBox="0 0 24 24" fill="none">
              <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>

          <!-- раскрытие -->
          <div class="prop-expand">
            <div class="prop-expand-kv">
              <div class="prop-box">
                <div class="prop-k">Аренда / год</div>
                <div class="prop-v">${eur(p.rent_per_year)}</div>
              </div>
              <div class="prop-box">
                <div class="prop-k">Ожидаемый доход / год</div>
                <div class="prop-v">${eur(p.expected_income_year)} / год</div>
              </div>
              <div class="prop-box">
                <div class="prop-k">Окупаемость</div>
                <div class="prop-v">${Number(p.payback_years || 0).toFixed(1)} лет</div>
              </div>
              <div class="prop-box">
                <div class="prop-k">Риски</div>
                <div class="prop-v">${esc(p.risk || "—")}</div>
              </div>
            </div>

            <div class="prop-box" style="margin-top:10px;">
              <div class="prop-k">Доход по вашей сумме заявки</div>
              <div class="prop-v js-income-by-amount" data-id="${p.id}">Введите сумму участия справа → будет расчёт</div>
              <div style="margin-top:8px;opacity:.8;font-size:12px;line-height:1.5;">
                ${esc(p.description || "").slice(0, 260)}${(p.description && p.description.length > 260) ? "…" : ""}
              </div>
            </div>

            <div style="margin-top:12px;">
              <div class="prop-k" style="margin-bottom:8px;">Фотографии (крупно)</div>
              <div class="prop-gallery">
                ${
                  media.length
                    ? media.map((m) => `<img src="${esc(m.url)}" alt="">`).join("")
                    : `<div style="opacity:.75;font-size:12px;">Фото не найдены</div>`
                }
              </div>
            </div>
          </div>
        </div>
      `;
    }).join("");

    // бинды
    $$(".prop-card", root).forEach((card) => {
      const id = Number(card.dataset.id);

      // выбор объекта (правая панель)
      card.addEventListener("click", (e) => {
        if (e.target.closest(".prop-arrow")) return;
        selectProperty(id);
      });

      // раскрытие
      const arrow = $(".prop-arrow", card);
      arrow.addEventListener("click", (e) => {
        e.stopPropagation();
        state.expandedId = (state.expandedId === id) ? null : id;
        renderList();
        updateIncomeByAmount();
      });
    });
  }

  function calcIncomeByAmount(p, amount) {
    const price = Number(p.price || 0);
    const incomeYear = Number(p.expected_income_year || 0);
    if (price <= 0 || incomeYear <= 0 || amount <= 0) return 0;
    return incomeYear * (amount / price);
  }

  function updateIncomeByAmount() {
    const amountEl = $("#investmentAmount");
    const amount = Number(amountEl?.value || 0);

    $$(".js-income-by-amount").forEach((el) => {
      const id = Number(el.dataset.id);
      const p = state.properties.find((x) => Number(x.id) === id);
      if (!p) return;

      if (amount <= 0) {
        el.textContent = "Введите сумму участия справа → будет расчёт";
        return;
      }

      const v = calcIncomeByAmount(p, amount);
      el.textContent = `${eur(v)} / год (примерно)`;
    });
  }

  function selectProperty(id) {
    const p = state.properties.find((x) => Number(x.id) === Number(id));
    if (!p) return;

    state.selectedId = p.id;
    renderList();

    // правая панель (твои id)
    $("#detailsName").textContent = p.name || "";
    $("#detailsLocation").textContent = p.location || "";
    $("#detailsPrice").textContent = eur(p.price);

    const tags = $("#detailsTags");
    if (tags) {
      const typeLabel = p.type === "commercial" ? "Коммерческая" : "Жилая";
      tags.innerHTML = `
        <span class="details-tag">${esc(typeLabel)}</span>
        <span class="details-tag">${esc(p.region || "")}</span>
        <span class="details-tag">${esc(p.status || "funding")}</span>
      `;
    }

    $("#galleryType").textContent = p.type === "commercial" ? "Коммерческая" : "Жилая";
    $("#galleryStatus").textContent = p.status || "funding";
    $("#detailsDescription").innerHTML = esc(p.description || "").replace(/\n/g, "<br>");

    $("#metricRent").textContent = eur(p.rent_per_year);
    $("#metricYield").textContent = eur(p.expected_income_year) + " / год";
    $("#metricPayback").textContent = Number(p.payback_years || 0).toFixed(1) + " лет";
    $("#metricRisk").textContent = p.risk || "–";

    const minTicketLabel = $("#minTicketLabel");
    if (minTicketLabel) minTicketLabel.textContent = "Мин. взнос: " + eur(p.min_ticket);

    const invested = Number(p.invested || 0);
    const price = Number(p.price || 0);
    const remaining = Math.max(price - invested, 0);
    const prog = price > 0 ? Math.min(100, Math.round((invested / price) * 100)) : 0;

    $("#progressCollected").textContent = "Собрано: " + eur(invested);
    $("#progressRemaining").textContent = "Осталось: " + eur(remaining);
    $("#progressInner").style.width = prog + "%";

    $("#participantsCount").textContent = String(p.participants || 0);
    const slotsLeft = Math.max(Number(p.max_partners || 0) - Number(p.participants || 0), 0);
    $("#slotsRemaining").textContent = String(slotsLeft);

    const media = Array.isArray(p.media) ? p.media : [];
    const mainImg = $("#detailsMainImage");
    if (mainImg) {
      mainImg.src = media[0] ? media[0].url : "";
      mainImg.alt = p.name || "";
    }

    const thumbs = $("#detailsThumbs");
    if (thumbs) {
      thumbs.innerHTML = media.map((m, idx) => `
        <button type="button" class="thumb ${idx === 0 ? "active" : ""}" data-url="${esc(m.url)}">
          <img src="${esc(m.url)}" alt="">
        </button>
      `).join("");

      $$(".thumb", thumbs).forEach((btn) => {
        btn.addEventListener("click", () => {
          $$(".thumb", thumbs).forEach((x) => x.classList.remove("active"));
          btn.classList.add("active");
          if (mainImg) mainImg.src = btn.dataset.url;
        });
      });
    }

    // запоминаем выбранный объект в форме
    const form = $("#participationForm");
    if (form) form.dataset.propertyId = String(p.id);

    updateShareInfo(p);
    updateIncomeByAmount();
  }

  function updateShareInfo(p) {
    const amountEl = $("#investmentAmount");
    const shareEl = $("#shareInfo");
    if (!amountEl || !shareEl) return;

    const amount = Number(amountEl.value || 0);
    const price = Number(p.price || 0);
    if (amount <= 0 || price <= 0) {
      shareEl.textContent = "–";
      return;
    }
    shareEl.textContent = ((amount / price) * 100).toFixed(2) + "%";
  }

  function initParticipationForm() {
    const form = $("#participationForm");
    if (!form) return;

    const amountEl = $("#investmentAmount");
    if (amountEl) {
      amountEl.addEventListener("input", () => {
        const p = state.properties.find((x) => Number(x.id) === Number(state.selectedId));
        if (p) updateShareInfo(p);
        updateIncomeByAmount();
      });
    }

    form.addEventListener("submit", async (e) => {
      e.preventDefault();

      const propId = Number(form.dataset.propertyId || 0);
      const amount = Number(amountEl?.value || 0);
      const err = $("#errorAmount");

      const p = state.properties.find((x) => Number(x.id) === propId);
      if (!p) return;

      if (amount <= 0) { if (err) err.textContent = "Укажите сумму участия."; return; }
      if (amount < Number(p.min_ticket || 0)) { if (err) err.textContent = `Минимум: ${eur(p.min_ticket)}.`; return; }
      if (err) err.textContent = "";

      // если api/participate.php есть — отлично. Если нет — просто покажет ошибку, UI не сломает.
      try {
        const r = await fetch("../api/participate.php", {
          method: "POST",
          headers: { "Content-Type": "application/json", "X-CSRF-Token": window.CSRF_TOKEN || "" },
          body: JSON.stringify({ property_id: propId, amount })
        });
        const j = await r.json();
        if (!j.success) { if (err) err.textContent = j.message || "Ошибка отправки заявки."; return; }

        // оптимистично обновим цифры
        p.invested = Number(p.invested || 0) + amount;
        p.participants = Number(p.participants || 0) + 1;
        selectProperty(p.id);

        // toast
        const toast = $("#successToast");
        if (toast) toast.classList.add("show");
        const close = $("#toastCloseBtn");
        if (close) close.onclick = () => toast.classList.remove("show");
        setTimeout(() => toast && toast.classList.remove("show"), 4500);
      } catch {
        if (err) err.textContent = "Сервер/сеть недоступны или нет participate.php.";
      }
    });
  }

  function initModals() {
    const loginBtn = $("#loginBtn");
    const registerBtn = $("#registerBtn");
    const loginModal = $("#loginModal");
    const registerModal = $("#registerModal");

    const open = (m) => m && (m.classList.add("open"), m.classList.add("active"));
    const close = (m) => m && (m.classList.remove("open"), m.classList.remove("active"));

    if (loginBtn && loginModal) loginBtn.addEventListener("click", () => open(loginModal));
    if (registerBtn && registerModal) registerBtn.addEventListener("click", () => open(registerModal));

    $$("[data-modal-close]").forEach((btn) => {
      btn.addEventListener("click", () => {
        close(loginModal);
        close(registerModal);
      });
    });

    [loginModal, registerModal].forEach((m) => {
      if (!m) return;
      m.addEventListener("click", (e) => {
        if (e.target === m) close(m);
      });
    });
  }

  document.addEventListener("DOMContentLoaded", async () => {
    injectCss();
    initFilters();
    initModals();
    initParticipationForm();

    await loadProperties();
    renderList();

    if (state.filtered.length) selectProperty(state.filtered[0].id);
  });
})();
