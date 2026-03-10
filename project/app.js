// project/app.js
(() => {
  console.log("RealEstate Share app.js loaded");

  const state = {
    properties: [],
    filtered: [],
    selectedId: null,
    expandedId: null,
  };

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

  const esc = (value) =>
    String(value ?? "").replace(/[&<>"']/g, (char) => ({
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#39;",
    }[char]));

  const eur = (value) => {
    const number = Number(value || 0);
    return "€" + number.toLocaleString("ru-RU", { maximumFractionDigits: 0 });
  };

  function getApiPath(fileName) {
    const path = window.location.pathname || "";
    return path.includes("/project/") ? `../api/${fileName}` : `api/${fileName}`;
  }

  function injectCss() {
    if ($("#realestateShareInjectedCss")) return;

    const style = document.createElement("style");
    style.id = "realestateShareInjectedCss";
    style.textContent = `
      /* ===== LEFT COLUMN CARDS ===== */
      #propertiesList{
        display:grid;
        gap:16px;
      }

      .prop-card{
        position:relative;
        border:1px solid rgba(255,255,255,0.08);
        border-radius:24px;
        background:rgba(10,18,36,0.92);
        box-shadow:0 20px 50px rgba(0,0,0,0.18);
        overflow:hidden;
        transition:border-color .2s ease, transform .2s ease, box-shadow .2s ease;
      }

      .prop-card:hover{
        transform:translateY(-2px);
        border-color:rgba(255,255,255,0.14);
      }

      .prop-card.active{
        border-color:rgba(89,124,255,0.45);
        box-shadow:0 24px 60px rgba(32,55,130,0.26);
      }

      .prop-card-head{
        position:relative;
        display:grid;
        grid-template-columns:140px 1fr;
        gap:14px;
        padding:16px 16px 74px 16px; /* запас снизу под плавающую кнопку */
        cursor:pointer;
      }

      .prop-side-badge{
        display:flex;
        flex-direction:column;
        justify-content:flex-start;
        gap:8px;
        padding:12px;
        border-radius:18px;
        border:1px solid rgba(255,255,255,0.10);
        background:linear-gradient(180deg, rgba(52,114,255,0.28), rgba(13,23,46,0.30));
        min-width:0;
        overflow:hidden;
      }

      .prop-side-badge-type{
        font-size:11px;
        letter-spacing:.08em;
        text-transform:uppercase;
        color:rgba(226,232,240,0.86);
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
      }

      .prop-side-badge-yield{
        font-size:22px;
        line-height:1;
        font-weight:700;
        color:#eff4ff;
        white-space:nowrap;
      }

      .prop-side-badge-payback{
        font-size:11px;
        color:rgba(226,232,240,0.78);
        line-height:1.35;
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
      }

      .prop-main{
        min-width:0;
        display:grid;
        grid-template-columns:96px 1fr;
        gap:12px;
        align-items:center;
      }

      .prop-cover-wrap{
        width:96px;
        height:74px;
        border-radius:16px;
        overflow:hidden;
        border:1px solid rgba(255,255,255,0.10);
        background:rgba(7,12,24,0.65);
        flex-shrink:0;
      }

      .prop-cover-wrap img{
        width:100%;
        height:100%;
        object-fit:cover;
        display:block;
      }

      .prop-main-info{
        min-width:0;
      }

      .prop-name{
        font-size:15px;
        font-weight:600;
        color:#f8fafc;
        line-height:1.35;
        margin:0;
      }

      .prop-location{
        margin-top:6px;
        font-size:12px;
        color:rgba(203,213,225,0.76);
        line-height:1.4;
      }

      .prop-meta-row{
        display:flex;
        flex-wrap:wrap;
        gap:10px;
        margin-top:10px;
        font-size:12px;
        color:rgba(203,213,225,0.72);
      }

      .prop-meta-row strong{
        color:#fff;
        font-weight:600;
      }

      .prop-fab{
        position:absolute;
        right:16px;
        bottom:16px;
        width:46px;
        height:46px;
        border:none;
        border-radius:999px;
        background:linear-gradient(180deg, #5378ff, #3455d1);
        color:#fff;
        display:flex;
        align-items:center;
        justify-content:center;
        cursor:pointer;
        box-shadow:0 16px 30px rgba(49,84,211,0.35);
        z-index:2;
      }

      .prop-fab svg{
        width:18px;
        height:18px;
        animation:propArrowFloat 1.3s ease-in-out infinite;
        transition:transform .25s ease;
      }

      .prop-card.expanded .prop-fab svg{
        animation:none;
        transform:rotate(180deg);
      }

      @keyframes propArrowFloat{
        0%,100%{ transform:translateY(0); }
        50%{ transform:translateY(5px); }
      }

      .prop-expand{
        display:none;
        padding:0 16px 16px 16px;
        border-top:1px solid rgba(255,255,255,0.08);
        background:rgba(7,12,24,0.18);
      }

      .prop-card.expanded .prop-expand{
        display:block;
      }

      .prop-expand-inner{
        display:grid;
        gap:14px;
        padding-top:14px;
      }

      .prop-carousel{
        position:relative;
        width:100%;
        overflow:hidden;
        border-radius:18px;
        border:1px solid rgba(255,255,255,0.08);
        background:rgba(2,6,23,0.30);
      }

      .prop-track{
        display:flex;
        gap:12px;
        width:max-content;
        padding:12px;
        animation:propCarouselScroll 24s linear infinite;
      }

      .prop-carousel:hover .prop-track{
        animation-play-state:paused;
      }

      .prop-carousel-item{
        width:240px;
        height:150px;
        border-radius:14px;
        overflow:hidden;
        flex-shrink:0;
        background:rgba(15,23,42,0.8);
        border:1px solid rgba(255,255,255,0.08);
      }

      .prop-carousel-item img{
        width:100%;
        height:100%;
        object-fit:cover;
        display:block;
      }

      @keyframes propCarouselScroll{
        0%{ transform:translateX(0); }
        100%{ transform:translateX(-50%); }
      }

      .prop-grid{
        display:grid;
        grid-template-columns:repeat(2, minmax(0,1fr));
        gap:10px;
      }

      .prop-box{
        min-width:0;
        border-radius:16px;
        padding:12px;
        border:1px solid rgba(255,255,255,0.08);
        background:rgba(15,23,42,0.72);
      }

      .prop-box-k{
        font-size:11px;
        letter-spacing:.08em;
        text-transform:uppercase;
        color:rgba(203,213,225,0.64);
      }

      .prop-box-v{
        margin-top:7px;
        font-size:14px;
        font-weight:600;
        color:#f8fafc;
        line-height:1.45;
        word-break:break-word;
      }

      .prop-description-box{
        border-radius:16px;
        padding:12px;
        border:1px solid rgba(255,255,255,0.08);
        background:rgba(15,23,42,0.72);
      }

      .prop-description-text{
        margin-top:8px;
        font-size:13px;
        line-height:1.65;
        color:rgba(226,232,240,0.88);
        word-break:break-word;
      }

      .prop-income-note{
        margin-top:10px;
        font-size:12px;
        line-height:1.5;
        color:rgba(191,219,254,0.92);
      }

      /* ===== RIGHT DETAILS PANEL IMAGES ===== */
      .details-gallery{
        overflow:hidden;
      }

      .gallery-main{
        min-width:0;
      }

      .details-photo{
        width:100%;
        max-width:100%;
        overflow:hidden;
        border-radius:20px;
      }

      .details-photo img,
      #detailsMainImage{
        display:block;
        width:100%;
        max-width:100%;
        // height:360px;
        object-fit:cover;
        border-radius:20px;
      }

      .details-thumbs{
        display:flex;
        gap:10px;
        margin-top:12px;
        overflow-x:auto;
        overflow-y:hidden;
        padding-bottom:4px;
        scrollbar-width:thin;
      }

      .details-thumbs::-webkit-scrollbar{
        height:8px;
      }

      .details-thumbs::-webkit-scrollbar-thumb{
        background:rgba(148,163,184,0.45);
        border-radius:999px;
      }

      .details-thumbs .thumb{
        flex:0 0 auto;
        width:86px;
        height:64px;
        padding:0;
        border:none;
        background:none;
        border-radius:14px;
        overflow:hidden;
        cursor:pointer;
        opacity:0.75;
      }

      .details-thumbs .thumb.active{
        opacity:1;
        outline:2px solid rgba(83,120,255,0.8);
      }

      .details-thumbs .thumb img{
        width:100%;
        height:100%;
        object-fit:cover;
        display:block;
      }

      @media (max-width: 980px){
        .prop-grid{
          grid-template-columns:1fr;
        }

        .prop-carousel-item{
          width:210px;
          height:132px;
        }

        .details-photo img,
        #detailsMainImage{
          height:300px;
        }
      }

      @media (max-width: 720px){
        .prop-card-head{
          grid-template-columns:1fr;
          gap:12px;
        }

        .prop-side-badge{
          width:100%;
        }

        .prop-main{
          grid-template-columns:84px 1fr;
        }

        .prop-cover-wrap{
          width:84px;
          height:66px;
        }

        .prop-carousel-item{
          width:190px;
          height:120px;
        }

        .details-photo img,
        #detailsMainImage{
          height:240px;
        }
      }
    `;
    document.head.appendChild(style);
  }

  async function loadProperties() {
    const response = await fetch(getApiPath("properties.php"), {
      method: "GET",
      cache: "no-store",
    });
    const json = await response.json();
    state.properties = Array.isArray(json.properties) ? json.properties : [];
    state.filtered = state.properties.slice();
  }

  function getTypeLabel(type) {
    return type === "commercial" ? "Коммерческая" : "Жилая";
  }

  function buildCarousel(media) {
    if (!Array.isArray(media) || media.length === 0) {
      return `
        <div class="prop-carousel">
          <div class="prop-track" style="animation:none;">
            <div class="prop-carousel-item" style="width:100%;max-width:none;">
              <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:rgba(203,213,225,0.78);font-size:13px;">
                Фото пока не загружены
              </div>
            </div>
          </div>
        </div>
      `;
    }

    const items = media.map((m) => `
      <div class="prop-carousel-item">
        <img src="${esc(m.url)}" alt="">
      </div>
    `).join("");

    const duplicate = media.length > 1 ? items : items;

    return `
      <div class="prop-carousel">
        <div class="prop-track" style="${media.length <= 1 ? "animation:none;" : ""}">
          ${items}
          ${duplicate}
        </div>
      </div>
    `;
  }

  function renderList() {
    const root = $("#propertiesList");
    if (!root) return;

    root.innerHTML = state.filtered.map((property) => {
      const media = Array.isArray(property.media) ? property.media : [];
      const cover = property.cover_url || (media[0] ? media[0].url : "");
      const price = Number(property.price || 0);
      const invested = Number(property.invested || 0);
      const remaining = Math.max(price - invested, 0);
      const progress = price > 0 ? Math.min(100, Math.round((invested / price) * 100)) : 0;
      const yieldPercent = Number(property.yield_percent || 0).toFixed(2);
      const paybackYears = Number(property.payback_years || 0).toFixed(1);
      const expectedIncomeYear = Number(property.expected_income_year || 0);

      return `
        <article class="prop-card ${state.selectedId === property.id ? "active" : ""} ${state.expandedId === property.id ? "expanded" : ""}" data-id="${property.id}">
          <div class="prop-card-head js-card-select">
            <div class="prop-side-badge">
              <div class="prop-side-badge-type">${esc(getTypeLabel(property.type))}</div>
              <div class="prop-side-badge-yield">${yieldPercent}%</div>
              <div class="prop-side-badge-payback">окупаемость ${paybackYears} лет</div>
            </div>

            <div class="prop-main">
              <div class="prop-cover-wrap">
                ${cover ? `<img src="${esc(cover)}" alt="">` : ""}
              </div>

              <div class="prop-main-info">
                <div class="prop-name">${esc(property.name)}</div>
                <div class="prop-location">${esc(property.location || "")}</div>

                <div class="prop-meta-row">
                  <span><strong>${eur(price)}</strong> цена</span>
                  <span><strong>${eur(remaining)}</strong> осталось</span>
                  <span>${progress}% собрано</span>
                </div>
              </div>
            </div>

            <button class="prop-fab js-card-toggle" type="button" aria-label="Открыть детали">
              <svg viewBox="0 0 24 24" fill="none">
                <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
              </svg>
            </button>
          </div>

          <div class="prop-expand">
            <div class="prop-expand-inner">
              ${buildCarousel(media)}

              <div class="prop-grid">
                <div class="prop-box">
                  <div class="prop-box-k">Годовая арендная ставка</div>
                  <div class="prop-box-v">${eur(property.rent_per_year)}</div>
                </div>

                <div class="prop-box">
                  <div class="prop-box-k">Ожидаемый доход / год</div>
                  <div class="prop-box-v">${eur(expectedIncomeYear)} / год</div>
                </div>

                <div class="prop-box">
                  <div class="prop-box-k">Окупаемость</div>
                  <div class="prop-box-v">${paybackYears} лет</div>
                </div>

                <div class="prop-box">
                  <div class="prop-box-k">Риск-профиль</div>
                  <div class="prop-box-v">${esc(property.risk || "—")}</div>
                </div>
              </div>

              <div class="prop-description-box">
                <div class="prop-box-k">Описание объекта</div>
                <div class="prop-description-text">${esc(property.description || "").replace(/\n/g, "<br>")}</div>
                <div class="prop-income-note js-income-by-amount" data-id="${property.id}">
                  Введите сумму участия справа, чтобы увидеть ожидаемый доход по вашей заявке.
                </div>
              </div>
            </div>
          </div>
        </article>
      `;
    }).join("");

    $$(".prop-card", root).forEach((card) => {
      const id = Number(card.dataset.id);
      const selectArea = $(".js-card-select", card);
      const toggleButton = $(".js-card-toggle", card);

      if (selectArea) {
        selectArea.addEventListener("click", (event) => {
          if (event.target.closest(".js-card-toggle")) return;
          selectProperty(id);
        });
      }

      if (toggleButton) {
        toggleButton.addEventListener("click", (event) => {
          event.stopPropagation();
          state.expandedId = state.expandedId === id ? null : id;
          renderList();
          updateIncomeByAmount();
        });
      }
    });
  }

  function selectProperty(id) {
    const property = state.properties.find((item) => Number(item.id) === Number(id));
    if (!property) return;

    state.selectedId = property.id;
    renderList();

    const media = Array.isArray(property.media) ? property.media : [];
    const price = Number(property.price || 0);
    const invested = Number(property.invested || 0);
    const remaining = Math.max(price - invested, 0);
    const progress = price > 0 ? Math.min(100, Math.round((invested / price) * 100)) : 0;
    const slotsLeft = Math.max(Number(property.max_partners || 0) - Number(property.participants || 0), 0);

    if ($("#detailsName")) $("#detailsName").textContent = property.name || "";
    if ($("#detailsLocation")) $("#detailsLocation").textContent = property.location || "";
    if ($("#detailsPrice")) $("#detailsPrice").textContent = eur(price);

    if ($("#detailsTags")) {
      $("#detailsTags").innerHTML = `
        <span class="details-tag">${esc(getTypeLabel(property.type))}</span>
        <span class="details-tag">${esc(property.region || "")}</span>
        <span class="details-tag">${esc(property.status || "funding")}</span>
      `;
    }

    if ($("#galleryType")) $("#galleryType").textContent = getTypeLabel(property.type);
    if ($("#galleryStatus")) $("#galleryStatus").textContent = property.status || "funding";

    if ($("#detailsDescription")) {
      $("#detailsDescription").innerHTML = esc(property.description || "").replace(/\n/g, "<br>");
    }

    if ($("#metricRent")) $("#metricRent").textContent = eur(property.rent_per_year);
    if ($("#metricYield")) $("#metricYield").textContent = eur(property.expected_income_year) + " / год";
    if ($("#metricPayback")) $("#metricPayback").textContent = Number(property.payback_years || 0).toFixed(1) + " лет";
    if ($("#metricRisk")) $("#metricRisk").textContent = property.risk || "–";

    if ($("#minTicketLabel")) $("#minTicketLabel").textContent = "Мин. взнос: " + eur(property.min_ticket);

    if ($("#progressCollected")) $("#progressCollected").textContent = "Собрано: " + eur(invested);
    if ($("#progressRemaining")) $("#progressRemaining").textContent = "Осталось: " + eur(remaining);
    if ($("#progressInner")) $("#progressInner").style.width = progress + "%";

    if ($("#participantsCount")) $("#participantsCount").textContent = String(property.participants || 0);
    if ($("#slotsRemaining")) $("#slotsRemaining").textContent = String(slotsLeft);

    const mainImage = $("#detailsMainImage");
    if (mainImage) {
      mainImage.src = media[0] ? media[0].url : "";
      mainImage.alt = property.name || "";
    }

    const thumbs = $("#detailsThumbs");
    if (thumbs) {
      thumbs.innerHTML = media.map((item, index) => `
        <button type="button" class="thumb ${index === 0 ? "active" : ""}" data-url="${esc(item.url)}" title="${esc(item.caption || "")}">
          <img src="${esc(item.url)}" alt="">
        </button>
      `).join("");

      $$(".thumb", thumbs).forEach((button) => {
        button.addEventListener("click", () => {
          $$(".thumb", thumbs).forEach((thumb) => thumb.classList.remove("active"));
          button.classList.add("active");
          if (mainImage) mainImage.src = button.dataset.url || "";
        });
      });
    }

    if ($("#galleryTitle")) $("#galleryTitle").textContent = "Фотографии объекта";
    if ($("#galleryMeta")) {
      $("#galleryMeta").textContent = media.length
        ? `Всего фото: ${media.length}. Вы можете переключать миниатюры ниже.`
        : "Фото пока не загружены.";
    }

    const form = $("#participationForm");
    if (form) form.dataset.propertyId = String(property.id);

    updateShareInfo(property);
    updateIncomeByAmount();
  }

  function updateShareInfo(property) {
    const amountInput = $("#investmentAmount");
    const shareInfo = $("#shareInfo");
    if (!amountInput || !shareInfo || !property) return;

    const amount = Number(amountInput.value || 0);
    const price = Number(property.price || 0);

    if (amount <= 0 || price <= 0) {
      shareInfo.textContent = "–";
      return;
    }

    shareInfo.textContent = ((amount / price) * 100).toFixed(2) + "%";
  }

  function calcIncomeByAmount(property, amount) {
    const price = Number(property.price || 0);
    const expectedIncome = Number(property.expected_income_year || 0);

    if (amount <= 0 || price <= 0 || expectedIncome <= 0) return 0;
    return expectedIncome * (amount / price);
  }

  function updateIncomeByAmount() {
    const amount = Number($("#investmentAmount")?.value || 0);

    $$(".js-income-by-amount").forEach((node) => {
      const id = Number(node.dataset.id);
      const property = state.properties.find((item) => Number(item.id) === id);
      if (!property) return;

      if (amount <= 0) {
        node.textContent = "Введите сумму участия справа, чтобы увидеть ожидаемый доход по вашей заявке.";
        return;
      }

      const annualIncome = calcIncomeByAmount(property, amount);
      node.textContent = `Ожидаемый доход по вашей сумме заявки: ${eur(annualIncome)} / год (примерно).`;
    });
  }

  function initFilters() {
    const pills = $$(".filter-pill");
    if (!pills.length) return;

    pills.forEach((pill) => {
      pill.addEventListener("click", () => {
        pills.forEach((item) => item.classList.remove("active"));
        pill.classList.add("active");

        const filter = pill.dataset.filter || "all";

        state.filtered = state.properties.filter((property) => {
          if (filter === "all") return true;
          if (filter === "residential" || filter === "commercial") return property.type === filter;
          if (filter === "europe" || filter === "middleeast") return property.region === filter;
          return true;
        });

        renderList();
        if (state.filtered.length) {
          selectProperty(state.filtered[0].id);
        }
      });
    });
  }

  function initParticipationForm() {
    const form = $("#participationForm");
    if (!form) return;

    const amountInput = $("#investmentAmount");
    if (amountInput) {
      amountInput.addEventListener("input", () => {
        const property = state.properties.find((item) => Number(item.id) === Number(state.selectedId));
        if (property) updateShareInfo(property);
        updateIncomeByAmount();
      });
    }

    form.addEventListener("submit", async (event) => {
      event.preventDefault();

      const propertyId = Number(form.dataset.propertyId || 0);
      const amount = Number(amountInput?.value || 0);
      const errorBox = $("#errorAmount");
      const property = state.properties.find((item) => Number(item.id) === propertyId);

      if (!property) return;

      if (amount <= 0) {
        if (errorBox) errorBox.textContent = "Укажите сумму участия.";
        return;
      }

      if (amount < Number(property.min_ticket || 0)) {
        if (errorBox) errorBox.textContent = `Минимальный взнос: ${eur(property.min_ticket)}.`;
        return;
      }

      if (errorBox) errorBox.textContent = "";

      try {
        const response = await fetch(getApiPath("participation.php"), {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-CSRF-Token": window.CSRF_TOKEN || "",
          },
          body: JSON.stringify({
            property_id: propertyId,
            amount: amount,
          }),
        });

        const json = await response.json();

        if (!json.success) {
          if (errorBox) errorBox.textContent = json.message || "Ошибка отправки заявки.";
          return;
        }

        property.invested = Number(property.invested || 0) + amount;
        property.participants = Number(property.participants || 0) + 1;

        selectProperty(property.id);

        const toast = $("#successToast");
        if (toast) toast.classList.add("show");

        const closeButton = $("#toastCloseBtn");
        if (closeButton) {
          closeButton.onclick = () => toast && toast.classList.remove("show");
        }

        setTimeout(() => {
          if (toast) toast.classList.remove("show");
        }, 4500);

      } catch (error) {
        if (errorBox) errorBox.textContent = "Сервер или сеть недоступны.";
      }
    });
  }

  function initModals() {
    const loginButton = $("#loginBtn");
    const registerButton = $("#registerBtn");
    const loginModal = $("#loginModal");
    const registerModal = $("#registerModal");

    const open = (modal) => modal && modal.classList.add("open");
    const close = (modal) => modal && modal.classList.remove("open");

    if (loginButton && loginModal) {
      loginButton.addEventListener("click", () => open(loginModal));
    }

    if (registerButton && registerModal) {
      registerButton.addEventListener("click", () => open(registerModal));
    }

    $$("[data-modal-close]").forEach((button) => {
      button.addEventListener("click", () => {
        close(loginModal);
        close(registerModal);
      });
    });

    [loginModal, registerModal].forEach((modal) => {
      if (!modal) return;
      modal.addEventListener("click", (event) => {
        if (event.target === modal) close(modal);
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

    if (state.filtered.length) {
      selectProperty(state.filtered[0].id);
    }
  });
})();