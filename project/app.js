// Утилиты форматирования
function formatCurrency(value) {
    return "€" + Number(value).toLocaleString("ru-RU");
}

function formatPercent(value) {
    return Number(value).toFixed(1).replace(".", ",") + "%";
}

// Глобальное состояние
const state = {
    properties: Array.isArray(window.APP_PROPERTIES) ? window.APP_PROPERTIES : [],
    selectedId: null,
    filter: "all"
};

const elements = {};

// Инициализация после загрузки DOM
document.addEventListener("DOMContentLoaded", () => {
    cacheElements();
    initFilters();
    renderProperties();
    if (state.properties.length > 0) {
        selectProperty(state.properties[0].id);
    }
    initToast();
    initModals();
    initParticipationForm();
});

// Кэшируем элементы
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
}

// Фильтры
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

// Отрисовка списка объектов
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


        card.addEventListener("click", () => {
            selectProperty(p.id);
        });

        if (state.selectedId === p.id) {
            card.style.outline = "1px solid rgba(79, 70, 229, 0.9)";
        }

        elements.propertiesList.appendChild(card);
    });
}

// Выбор объекта
function selectProperty(id) {
    const property = state.properties.find(p => Number(p.id) === Number(id));
    if (!property) return;
    state.selectedId = property.id;

    document.querySelectorAll(".property-card").forEach(card => {
        card.style.outline = (Number(card.dataset.id) === Number(id))
            ? "1px solid rgba(79, 70, 229, 0.9)"
            : "none";
    });

    elements.detailsName.textContent = property.name;
    elements.detailsLocation.textContent = property.location;
    elements.detailsPrice.textContent = formatCurrency(property.price);

    elements.detailsTags.innerHTML = "";
    const tags = [];
    tags.push(property.type === "residential" ? "Жилая недвижимость" : "Коммерческий объект");
    tags.push(property.region === "europe" ? "Европа" : "Ближний Восток");
    const extraTag = property.risk;
    if (extraTag) tags.push(extraTag);

    tags.forEach(tag => {
        const el = document.createElement("span");
        el.className = "details-tag";
        el.textContent = tag;
        elements.detailsTags.appendChild(el);
    });

    elements.galleryTitle.textContent = property.name;
    elements.galleryMeta.textContent = "Демо-пример. В production-версии здесь будет медиагалерея объекта.";
    elements.galleryType.textContent = property.type === "residential" ? "Жилая недвижимость" : "Коммерческий объект";

    const remaining = Math.max(property.price - (property.invested || 0), 0);
    elements.galleryStatus.textContent = remaining <= 0 ? "Полностью профинансирован" : "Идёт сбор долей";

    elements.detailsDescription.textContent = property.description || "";

    elements.metricRent.textContent = formatCurrency(property.rent_per_year) + " / год";
    elements.metricYield.textContent = "Ожидаемая доходность ~ " + formatPercent(property.yield_percent);
    elements.metricPayback.textContent = String(property.payback_years).replace(".", ",") + " лет";
    elements.metricRisk.textContent = property.risk;
    elements.economicsNote.textContent = "Расчёты основаны на консервативном сценарии аренды. Фактические значения могут отличаться и не являются гарантией доходности.";

    elements.minTicketLabel.textContent = "Мин. взнос: " + formatCurrency(property.min_ticket);

    const collected = property.invested || 0;
    const percent = property.price > 0 ? Math.min(collected / property.price * 100, 100) : 0;
    elements.progressInner.style.width = percent + "%";
    elements.progressCollected.textContent = "Собрано: " + formatCurrency(collected) + " (" + percent.toFixed(0) + "%)";
    elements.progressRemaining.textContent = "Осталось: " + formatCurrency(remaining);

    const used = property.participants || 0;
    const totalSlots = property.max_partners || 0;
    const freeSlots = Math.max(totalSlots - used, 0);
    elements.participantsCount.textContent = used + " из " + totalSlots;
    elements.slotsRemaining.textContent = freeSlots;

    elements.shareInfo.textContent = "Укажите сумму участия, чтобы увидеть вашу долю";

    if (elements.investmentAmount) {
        elements.investmentAmount.value = "";
    }
    if (elements.errorAmount) {
        elements.errorAmount.textContent = "";
    }
}

// Toast
let toastTimeout = null;
function initToast() {
    elements.successToast = document.getElementById("successToast");
    elements.toastCloseBtn = document.getElementById("toastCloseBtn");
    if (elements.toastCloseBtn) {
        elements.toastCloseBtn.addEventListener("click", () => {
            hideToast();
        });
    }
}

function showToast() {
    if (!elements.successToast) return;
    elements.successToast.classList.add("visible");
    if (toastTimeout) clearTimeout(toastTimeout);
    toastTimeout = setTimeout(() => hideToast(), 4500);
}

function hideToast() {
    if (!elements.successToast) return;
    elements.successToast.classList.remove("visible");
}

// Модальные окна входа/регистрации
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
            if (e.target === backdrop) {
                closeModal(backdrop);
            }
        });
    });

    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") {
            [elements.loginModal, elements.registerModal].forEach(backdrop => {
                if (backdrop) closeModal(backdrop);
            });
        }
    });
}

function openModal(backdrop) {
    if (!backdrop) return;
    backdrop.classList.add("open");
}

function closeModal(backdrop) {
    if (!backdrop) return;
    backdrop.classList.remove("open");
}

// Форма участия
function initParticipationForm() {
    if (!elements.participationForm || !elements.investmentAmount) return;

    elements.investmentAmount.addEventListener("input", () => {
        updateShareInfo();
    });

    elements.participationForm.addEventListener("submit", (e) => {
        e.preventDefault();
        submitParticipation();
    });
}

function updateShareInfo() {
    const property = state.properties.find(p => Number(p.id) === Number(state.selectedId));
    if (!property) {
        elements.shareInfo.textContent = "Выберите объект";
        return;
    }
    const val = parseFloat(elements.investmentAmount.value);
    if (!val || val <= 0) {
        elements.shareInfo.textContent = "Укажите сумму участия";
        return;
    }
    const percent = property.price > 0 ? (val / property.price * 100) : 0;
    elements.shareInfo.textContent = "≈ " + percent.toFixed(2).replace(".", ",") + "% от объекта";
}

async function submitParticipation() {
    if (!window.APP_USER) {
        // Если не авторизован – предлагаем войти
        if (elements.loginModal) {
            openModal(elements.loginModal);
        } else {
            alert("Для участия необходимо войти как партнёр.");
        }
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
        elements.errorAmount.textContent = "Минимальная сумма участия: " + formatCurrency(property.min_ticket) + ".";
        return;
    }
    const remaining = Math.max(property.price - (property.invested || 0), 0);
    if (amount > remaining) {
        elements.errorAmount.textContent = "Сумма больше оставшейся к сбору для объекта.";
        return;
    }

    elements.participateBtn.disabled = true;

    try {
        const response = await fetch("participation.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                property_id: property.id,
                amount: amount
            })
        });

        const data = await response.json();

        if (!data.success) {
            if (data.error_code === "not_authenticated") {
                if (elements.loginModal) {
                    openModal(elements.loginModal);
                } else {
                    alert("Для участия необходимо войти как партнёр.");
                }
            } else if (data.message) {
                elements.errorAmount.textContent = data.message;
            } else {
                elements.errorAmount.textContent = "Произошла ошибка при сохранении участия.";
            }
            return;
        }

        // Обновляем объект в state
        const updated = data.property;
        const idx = state.properties.findIndex(p => Number(p.id) === Number(updated.id));
        if (idx !== -1) {
            state.properties[idx] = updated;
        }

        renderProperties();
        selectProperty(updated.id);
        if (elements.investmentAmount) {
            elements.investmentAmount.value = "";
        }
        updateShareInfo();
        showToast();
    } catch (err) {
        console.error(err);
        elements.errorAmount.textContent = "Ошибка сети. Попробуйте ещё раз.";
    } finally {
        elements.participateBtn.disabled = false;
    }
}

/* highlighting the active tab when scrolling */
// Активная вкладка в меню при прокрутке
(function initNavActive() {
  const links = Array.from(document.querySelectorAll('.nav-link'))
    .filter(a => a.getAttribute('href') && a.getAttribute('href').startsWith('#'));

  const sections = links
    .map(a => document.querySelector(a.getAttribute('href')))
    .filter(Boolean);

  function setActive() {
    const y = window.scrollY + 120;
    let activeId = null;

    for (const sec of sections) {
      if (sec.offsetTop <= y) activeId = sec.id;
    }

    links.forEach(a => {
      const id = a.getAttribute('href').slice(1);
      a.classList.toggle('active', id === activeId);
    });
  }

  window.addEventListener('scroll', setActive, { passive: true });
  setActive();
})();


