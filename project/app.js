// project/app.js
(() => {
  const state = {
    properties: [],
    filtered: [],
    selectedId: null,
    expandedId: null,
  };

  // --- helpers
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  const eur = (n) => {
    const v = Number(n || 0);
    return '€' + v.toLocaleString('ru-RU', { maximumFractionDigits: 0 });
  };

  const esc = (s) =>
    String(s ?? "").replace(/[&<>"']/g, (c) => ({
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#39;",
    }[c]));

  // твой проект: index.php в /project/, api в /api/
  const apiUrl = () => {
    const p = window.location.pathname;
    return p.includes("/project/") ? "../api/properties.php" : "api/properties.php";
  };

  // --- inject minimal css for arrow + expanded block (не ломает твой styles.css)
  function injectCss() {
    if ($("#reShareExpandCss")) return;
    const style = document.createElement("style");
    style.id = "reShareExpandCss";
    style.textContent = `
      .prop-card { position:relative; }
      .prop-expand { display:none; padding: 10px 14px 14px; border-top:1px solid rgba(55,65,81,.7); }
      .prop-card.expanded .prop-expand { display:block; }

      .prop-arrow {
        position:absolute; right:12px; top:12px;
        width:34px; height:34px; border-radius:14px;
        border:1px solid rgba(55,65,81,.9);
        background:rgba(2,6,23,.25);
        display:flex; align-items:center; justify-content:center;
        color: rgba(226,232,240,.95);
        cursor:pointer;
      }
      .prop-arrow svg{ width:18px; height:18px; animation: reBounce 1.2s ease-in-out infinite; }
      .prop-card.expanded .prop-arrow svg{ transform: rotate(180deg); animation:none; }
      @keyframes reBounce { 0%,100% { transform: translateY(0);} 50%{ transform: translateY(5px);} }

      .prop-expand-grid{ display:grid; grid-template-columns: 1fr; gap: 12px; }
      .prop-expand-kv{ display:grid; grid-template-columns: 1fr 1fr; gap: 8px; }
      @media (max-width: 760px){ .prop-expand-kv{ grid-template-columns:1fr; } }

      .prop-box{
        border-radius:14px;
        border:1px solid rgba(55,65,81,.9);
        background:rgba(2,6,23,.20);
        padding:10px;
      }
      .prop-k{ font-size:11px; color: var(--text-muted); text-transform:uppercase; letter-spacing:.08em; }
      .prop-v{ margin-top:6px; font-size:13px; color: var(--text-main); font-weight:600; }

      .prop-gallery{ display:grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap:10px; }
      @media (max-width: 980px){ .prop-gallery{ grid-template-columns: repeat(2,minmax(0,1fr)); } }
      @media (max-width: 520px){ .prop-gallery{ grid-template-columns: 1fr; } }
      .prop-gallery img{
        width:100%; height:140px; object-fit:cover;
        border-radius:14px; border:1px solid rgba(255,255,255,.1);
        background:rgba(2,6,23,.25);
      }
      .prop-expand-desc{ color: rgba(226,232,240,.9); font-size:12px; line-height:1.55; }
      .prop-expand-note{ margin-top:8px; color: var(--text-muted); font-size:12px; line-height:1.45; }
    `;
    document.head.appendChild(style);
  }

  // --- load properties
  async function loadProperties() {
    const r = await fetch(apiUrl(), { method: "GET" });
    const j = await r.json();
    state.properties = Array.isArray(j.properties) ? j.properties : [];
    state.filtered = state.properties.slice();
  }

  // --- filters (твои pill'ы)
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

  // --- render left list (внутрь #propertiesList)
  function renderList() {
    const root = $("#propertiesList");
    if (!root) return;

    root.innerHTML = state.filtered.map((p) => {
      const cover = p.cover_url || (p.media && p.media[0] ? p.media[0].url : "");
      const invested = Number(p.invested || 0);
      const price = Number(p.price || 0);
      const remaining = Math.max(price - invested, 0);
      const prog = price > 0 ? Math.min(100, Math.round((invested / price) * 100)) : 0;

      // блок слева "синяя плашка" у тебя раньше был некрасивый — делаю компактно и без вылезания текста
      const typeLabel = p.type === "commercial" ? "Коммерческая" : "Жилая";
      const yieldLabel = Number(p.yield_percent || 0).toFixed(2) + "%";
      const paybackLabel = Number(p.payback_years || 0).toFixed(1) + " лет";

      return `
        <div class="property-card prop-card ${state.selectedId === p.id ? "active" : ""} ${state.expandedId === p.id ? "expanded" : ""}"
             data-id="${p.id}">
          <div class="property-left-badge" style="
            display:flex; flex-direction:column; gap:6px;
            padding:10px 10px; border-radius:14px;
            border:1px solid rgba(255,255,255,0.12);
            background: linear-gradient(180deg, rgba(37,99,235,0.20), rgba(2,6,23,0.12));
            min-width: 120px; max-width: 140px;
          ">
            <div style="font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:rgba(226,232,240,0.85);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
              ${esc(typeLabel)}
            </div>
            <div style="font-size:18px;font-weight:700;color:#e0e7ff;line-height:1;white-space:nowrap;">
              ${esc(yieldLabel)}
            </div>
            <div style="font-size:11px;color:rgba(226,232,240,0.82);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
              окупаемость ${esc(paybackLabel)}
            </div>
          </div>

          <div class="property-main" style="display:grid;grid-template-columns:92px 1fr;gap:10px;align-items:center;">
            ${cover ? `<img src="${esc(cover)}" alt="" style="width:92px;height:66px;object-fit:cover;border-radius:12px;border:1px solid rgba(255,255,255,0.1);background:rgba(2,6,23,0.25);">`
                    : `<div style="width:92px;height:66px;border-radius:12px;border:1px solid rgba(255,255,255,0.1);background:rgba(2,6,23,0.25);"></div>`}

            <div>
              <div class="property-name" style="font-weight:600;color:var(--text-main);font-size:13px;">
                ${esc(p.name)}
              </div>
              <div class="property-location" style="color:var(--text-muted);font-size:12px;margin-top:4px;">
                ${esc(p.location || "")}
              </div>
              <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:8px;color:var(--text-muted);font-size:12px;">
                <span><strong style="color:var(--text-main);">${eur(p.price)}</strong> цена</span>
                <span><strong style="color:var(--text-main);">${eur(remaining)}</strong> осталось</span>
                <span>${prog}% собрано</span>
              </div>
            </div>
          </div>

          <div class="prop-arrow" title="Раскрыть / свернуть">
            <svg viewBox="0 0 24 24" fill="none">
              <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>

          <div class="prop-expand">
            <div class="prop-expand-grid">
              <div class="prop-expand-kv">
                <div class="prop-box">
                  <div class="prop-k">Стоимость</div>
                  <div class="prop-v">${eur(p.price)}</div>
                </div>
                <div class="prop-box">
                  <div class="prop-k">Инвестировано</div>
                  <div class="prop-v">${eur(invested)}</div>
                </div>
                <div class="prop-box">
                  <div class="prop-k">Осталось</div>
                  <div class="prop-v">${eur(remaining)}</div>
                </div>
                <div class="prop-box">
                  <div class="prop-k">Аренда / год</div>
                  <div class="prop-v">${eur(p.rent_per_year)}</div>
                </div>
                <div class="prop-box">
                  <div class="prop-k">Ожидаемый доход / год</div>
                  <div class="prop-v">${eur(p.expected_income_year)}</div>
                </div>
                <div class="prop-box">
                  <div class="prop-k">Окупаемость</div>
                  <div class="prop-v">${Number(p.payback_years || 0).toFixed(1)} лет</div>
                </div>
              </div>

              <div class="prop-box">
                <div class="prop-k">Описание</div>
                <div class="prop-expand-desc">${esc(p.description || "").replace(/\n/g, "<br>")}</div>

                <div class="prop-expand-note">
                  <strong>Доход по вашей заявке:</strong>
                  <span class="js-income-by-amount" data-id="${p.id}">введите сумму справа → будет расчёт</span>
                </div>
              </div>

              <div>
                <div class="prop-k" style="margin-bottom:8px;">Фотографии (крупнее)</div>
                <div class="prop-gallery">
                  ${(p.media || []).length
                    ? (p.media || []).map((m) => `<img src="${esc(m.url)}" alt="">`).join("")
                    : `<div style="color:var(--text-muted);font-size:12px;">Фото не найдены</div>`}
                </div>
              </div>
            </div>
          </div>
        </div>
      `;
    }).join("");

    // bind clicks
    $$(".prop-card", root).forEach((card) => {
      const id = Number(card.dataset.id);

      // кликом по карточке — выбрать объект (правая панель)
      card.addEventListener("click", (e) => {
        // если кликнули по стрелке — не надо менять выбор дважды
        if (e.target.closest(".prop-arrow")) return;
        selectProperty(id);
      });

      // стрелка: раскрыть/свернуть
      const arrow = $(".prop-arrow", card);
      arrow.addEventListener("click", (e) => {
        e.stopPropagation();
        toggleExpand(id);
      });
    });
  }

  function toggleExpand(id) {
    state.expandedId = (state.expandedId === id) ? null : id;
    renderList();          // перерисуем только левый список
    updateIncomeByAmount(); // обновим “доход по вашей сумме”
  }

  // --- select property updates right panel
  function selectProperty(id) {
    const p = state.properties.find((x) => Number(x.id) === Number(id));
    if (!p) return;
    state.selectedId = p.id;

    // выделение карточки + не ломаем expanded
    renderList();

    // RIGHT panel fields
    $("#detailsName").textContent = p.name || "";
    $("#detailsLocation").textContent = p.location || "";
    $("#detailsPrice").textContent = eur(p.price);

    // tags
    const tags = $("#detailsTags");
    if (tags) {
      const typeLabel = p.type === "commercial" ? "Коммерческая" : "Жилая";
      tags.innerHTML = `
        <span class="details-tag">${esc(typeLabel)}</span>
        <span class="details-tag">${esc(p.region)}</span>
        <span class="details-tag">${esc(p.status || "funding")}</span>
      `;
    }

    // gallery side
    $("#galleryType").textContent = p.type === "commercial" ? "Коммерческая" : "Жилая";
    $("#galleryStatus").textContent = p.status || "funding";

    // description
    $("#detailsDescription").innerHTML = esc(p.description || "").replace(/\n/g, "<br>");

    // metrics
    $("#metricRent").textContent = eur(p.rent_per_year);
    // "Годовой доход" — у тебя в UI именно так подписано
    $("#metricYield").textContent = eur(p.expected_income_year) + " / год";
    $("#metricPayback").textContent = Number(p.payback_years || 0).toFixed(1) + " лет";
    $("#metricRisk").textContent = p.risk || "–";

    // min ticket label
    const minTicketLabel = $("#minTicketLabel");
    if (minTicketLabel) minTicketLabel.textContent = "Мин. взнос: " + eur(p.min_ticket);

    // progress
    const invested = Number(p.invested || 0);
    const price = Number(p.price || 0);
    const remaining = Math.max(price - invested, 0);
    const prog = price > 0 ? Math.min(100, Math.round((invested / price) * 100)) : 0;

    $("#progressCollected").textContent = "Собрано: " + eur(invested);
    $("#progressRemaining").textContent = "Осталось: " + eur(remaining);
    $("#progressInner").style.width = prog + "%";

    // participants
    $("#participantsCount").textContent = String(p.participants || 0);
    const slotsLeft = Math.max(Number(p.max_partners || 0) - Number(p.participants || 0), 0);
    $("#slotsRemaining").textContent = String(slotsLeft);

    // gallery main + thumbs
    const media = Array.isArray(p.media) ? p.media : [];
    const mainImg = $("#detailsMainImage");
    if (mainImg) {
      mainImg.src = media[0] ? media[0].url : "";
      mainImg.alt = p.name || "";
    }

    const thumbs = $("#detailsThumbs");
    if (thumbs) {
      thumbs.innerHTML = media.map((m, idx) => `
        <button type="button" class="thumb ${idx === 0 ? "active" : ""}" data-url="${esc(m.url)}" title="${esc(m.caption || "")}">
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

    // gallery text
    $("#galleryTitle").textContent = "Фотографии объекта";
    $("#galleryMeta").textContent = media.length
      ? `Всего фото: ${media.length}. Вы можете переключать миниатюры.`
      : "Фото пока не загружены.";

    // set selected property to form
    const form = $("#participationForm");
    if (form) form.dataset.propertyId = String(p.id);

    // share info recalculation based on current amount
    updateShareInfo(p);
    updateIncomeByAmount(); // сразу обновим расчёт в раскрытых карточках
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

    const share = (amount / price) * 100;
    shareEl.textContent = share.toFixed(2) + "%";
  }

  // --- “доход с учётом суммы заявки”
  // логика: (expected_income_year) * (amount/price)
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
        el.textContent = "введите сумму справа → будет расчёт";
        return;
      }

      const v = calcIncomeByAmount(p, amount);
      el.textContent = `${eur(v)} / год (примерно)`;
    });
  }

  // --- participation submit (если у тебя уже есть API — подставь URL; здесь аккуратно не ломаем)
  function initParticipationForm() {
    const form = $("#participationForm");
    if (!form) return;

    form.addEventListener("submit", async (e) => {
      e.preventDefault();

      const propId = Number(form.dataset.propertyId || 0);
      const amount = Number($("#investmentAmount")?.value || 0);
      const err = $("#errorAmount");

      const p = state.properties.find((x) => Number(x.id) === propId);
      if (!p) return;

      if (amount <= 0) {
        if (err) err.textContent = "Укажите сумму участия.";
        return;
      }
      if (amount < Number(p.min_ticket || 0)) {
        if (err) err.textContent = `Минимальный взнос для этого объекта: ${eur(p.min_ticket)}.`;
        return;
      }
      if (err) err.textContent = "";

      // Если у тебя endpoint участия уже существует — оставь его.
      // Я не меняю твою серверную часть здесь, только добавляю совместимый вызов.
      try {
        const r = await fetch("api/participate.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-CSRF-Token": window.CSRF_TOKEN || ""
          },
          body: JSON.stringify({
            property_id: propId,
            amount: amount
          })
        });

        const j = await r.json();
        if (!j.success) {
          if (err) err.textContent = j.message || "Ошибка отправки заявки.";
          return;
        }

        // Обновим цифры на клиенте (optimistic UI)
        p.invested = Number(p.invested || 0) + amount;
        p.participants = Number(p.participants || 0) + 1;

        // показать toast (у тебя есть)
        const toast = $("#successToast");
        if (toast) toast.classList.add("show");
        const close = $("#toastCloseBtn");
        if (close) close.onclick = () => toast.classList.remove("show");
        setTimeout(() => toast && toast.classList.remove("show"), 4500);

        // перерисуем и обновим правую панель
        selectProperty(p.id);

      } catch (e2) {
        if (err) err.textContent = "Сеть/сервер недоступны. Попробуйте ещё раз.";
      }
    });

    // on input update %
    const amountEl = $("#investmentAmount");
    if (amountEl) {
      amountEl.addEventListener("input", () => {
        const p = state.properties.find((x) => Number(x.id) === Number(state.selectedId));
        if (p) updateShareInfo(p);
        updateIncomeByAmount();
      });
    }
  }

  // --- modals (у тебя уже есть в styles)
  function initModals() {
    const loginBtn = $("#loginBtn");
    const registerBtn = $("#registerBtn");
    const loginModal = $("#loginModal");
    const registerModal = $("#registerModal");

    const open = (m) => m && m.classList.add("open");
    const close = (m) => m && m.classList.remove("open");

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

  // --- init
  document.addEventListener("DOMContentLoaded", async () => {
    injectCss();
    initFilters();
    initModals();
    initParticipationForm();

    await loadProperties();
    renderList();

    if (state.filtered.length) {
      selectProperty(state.filtered[0].id);
    }
  });
})();
