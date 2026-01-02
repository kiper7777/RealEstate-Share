<?php
require_once 'db.php';

// Текущий пользователь
$currentUser = null;
if (!empty($_SESSION['user_id'])) {
    $currentUser = [
        'id'    => $_SESSION['user_id'],
        'name'  => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
    ];
}

// Получаем объекты с агрегированными данными
$properties = [];
$sql = "SELECT 
            p.*,
            COALESCE(SUM(part.amount), 0) AS invested,
            COUNT(part.id) AS participants
        FROM properties p
        LEFT JOIN participations part ON part.property_id = p.id
        GROUP BY p.id
        ORDER BY p.id ASC";
$res = mysqli_query($conn, $sql);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        // Приводим numeric
        $row['price']         = (float)$row['price'];
        $row['min_ticket']    = (float)$row['min_ticket'];
        $row['max_partners']  = (int)$row['max_partners'];
        $row['rent_per_year'] = (float)$row['rent_per_year'];
        $row['yield_percent'] = (float)$row['yield_percent'];
        $row['payback_years'] = (float)$row['payback_years'];
        $row['invested']      = (float)$row['invested'];
        $row['participants']  = (int)$row['participants'];
        $properties[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>RealEstate Share – Долевое участие в недвижимости</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
    <div class="app-shell">
        <header>
            <div class="nav">
                <div class="logo">
                    <div class="logo-mark">R</div>
                    <div class="logo-text">
                        <div class="logo-title">RealEstate Share</div>
                        <div class="logo-subtitle">Коммерческая и жилая недвижимость</div>
                    </div>
                </div>
                <div class="nav-actions">
                    <a href="#platform" class="nav-link">Платформа</a>
                    <a href="#properties" class="nav-link">Объекты</a>
                    <a href="#partners" class="nav-link">Партнёрам</a>

                    <?php if ($currentUser): ?>
                    <span class="nav-user">
                        Привет,
                        <?= htmlspecialchars($currentUser['name']) ?>
                    </span>
                    <a href="logout.php" class="btn btn-outline btn-sm">Выйти</a>
                    <?php else: ?>
                    <button class="btn btn-outline" id="loginBtn">Вход партнёра</button>
                    <button class="btn btn-primary" id="registerBtn">Стать партнёром</button>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <main>
            <!-- HERO (Платформа) -->
            <section class="hero" id="platform">
                <div class="hero-copy">
                    <div class="hero-badge">
                        <span class="hero-badge-dot"></span>
                        <span>Управление объектами + долевое участие онлайн</span>
                    </div>
                    <h1 class="hero-title">
                        Инвестируйте в <span>жильё и коммерческие объекты</span> по всему миру
                    </h1>
                    <p class="hero-subtitle">
                        Платформа для партнёров, которые хотят участвовать в покупке
                        недвижимости с прозрачной экономикой: вы видите стоимость, аренду,
                        окупаемость и статус финансирования каждого объекта.
                    </p>

                    <div class="hero-cta-row">
                        <a href="#properties" class="btn btn-primary">Посмотреть доступные объекты</a>
                        <a href="#partners" class="btn btn-outline">Как это работает</a>
                    </div>

                    <div class="hero-stats">
                        <div class="hero-stat">
                            <div class="hero-stat-label">Управляемые активы</div>
                            <div class="hero-stat-value">48+ млн €</div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-stat-label">Города</div>
                            <div class="hero-stat-value">12 стран</div>
                        </div>
                        <div class="hero-stat">
                            <div classhero-stat-label">Партнёры</div>
                            <div class="hero-stat-value">320+ инвесторов</div>
                        </div>
                    </div>
                </div>

                <div class="hero-panel">
                    <div class="hero-panel-header">
                        <div class="hero-panel-title">Пример сделки</div>
                        <div class="hero-panel-tag">Live deal · Испания</div>
                    </div>
                    <div class="hero-panel-main">
                        <div class="hero-panel-property">Апартаменты на первой линии, Коста-Бланка</div>
                        <div class="hero-panel-location">Испания · Средиземное море</div>
                        <div class="hero-panel-price">€200 000 · от €5 000 за долю</div>

                        <div class="hero-panel-meta">
                            <span class="hero-chip">Годовой доход от аренды ~ 7,8%</span>
                            <span class="hero-chip">Прогноз окупаемости ~ 9 лет</span>
                        </div>

                        <div class="hero-progress-wrap">
                            <div class="hero-progress-bar">
                                <div class="hero-progress-inner"></div>
                            </div>
                            <div class="hero-progress-labels">
                                <span>Собрано: €120 000</span>
                                <span>Осталось: €80 000</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ОСНОВНОЙ БЛОК: ОБЪЕКТЫ И ДЕТАЛИ -->
            <section class="content" id="properties">
                <!-- ЛЕВАЯ КОЛОНКА: СПИСОК -->
                <div>
                    <div class="section-header">
                        <div>
                            <div class="section-title">Объекты для долевого участия</div>
                            <div class="section-subtitle">Выберите объект, чтобы увидеть подробности и условия</div>
                        </div>
                        <div class="pill-online">
                            <span class="pill-dot"></span>
                            <span>Онлайн-подбор в реальном времени</span>
                        </div>
                    </div>

                    <div class="filters">
                        <div class="filter-pill active" data-filter="all">Все</div>
                        <div class="filter-pill" data-filter="residential">Жилая</div>
                        <div class="filter-pill" data-filter="commercial">Коммерческая</div>
                        <div class="filter-pill" data-filter="europe">Европа</div>
                        <div class="filter-pill" data-filter="middleeast">Ближний Восток</div>
                    </div>

                    <div id="propertiesList" class="properties-list"></div>
                </div>

                <!-- ПРАВАЯ КОЛОНКА: ДЕТАЛИ -->
                <div class="details-shell" id="detailsShell">
                    <div class="details-header">
                        <div>
                            <div class="details-name" id="detailsName">Выберите объект из списка</div>
                            <div class="details-location" id="detailsLocation"></div>
                            <div class="details-tags" id="detailsTags"></div>
                        </div>
                        <div class="details-price-block">
                            <div class="details-price-main" id="detailsPrice"></div>
                            <div class="details-price-label">Общая стоимость объекта</div>
                        </div>
                    </div>

                    <div class="details-gallery">
                        <div class="gallery-main">
                            <div>
                                <div class="gallery-main-label">Обзор объекта</div>
                                <div class="gallery-main-title" id="galleryTitle">Фотографии и планировка</div>
                                <div class="gallery-main-meta" id="galleryMeta">
                                    Здесь будет медиагалерея: фото, видео-тур, планы этажей.
                                </div>
                            </div>
                            <div class="gallery-main-meta-extra">
                                В демо-версии данные статичны, в production — интеграция с медиахранилищем.
                            </div>
                        </div>
                        <div class="gallery-side">
                            <div class="gallery-tile">
                                <div class="gallery-tile-label">Тип объекта</div>
                                <div class="gallery-tile-value" id="galleryType">–</div>
                            </div>
                            <div class="gallery-tile">
                                <div class="gallery-tile-label">Статус</div>
                                <div class="gallery-tile-value" id="galleryStatus">Выберите объект</div>
                            </div>
                        </div>
                    </div>

                    <div class="details-description" id="detailsDescription">
                        Для просмотра подробной информации по объекту, включая экономическую привлекательность,
                        выберите его слева из списка.
                    </div>

                    <div class="details-grid">
                        <!-- Экономика -->
                        <div class="details-economics">
                            <div class="details-economics-title">Экономическая привлекательность</div>
                            <div class="metrics">
                                <div class="metric">
                                    <div class="metric-label">Годовая арендная ставка</div>
                                    <div class="metric-value" id="metricRent">–</div>
                                </div>
                                <div class="metric">
                                    <div class="metric-label">Годовой доход</div>
                                    <div class="metric-value" id="metricYield">–</div>
                                </div>
                                <div class="metric">
                                    <div class="metric-label">Окупаемость</div>
                                    <div class="metric-value" id="metricPayback">–</div>
                                </div>
                                <div class="metric">
                                    <div class="metric-label">Риск-профиль</div>
                                    <div class="metric-value" id="metricRisk">–</div>
                                </div>
                            </div>
                            <div class="economics-note" id="economicsNote">
                                Все расчёты являются ориентировочными и не являются инвестиционной рекомендацией.
                            </div>
                        </div>

                        <!-- Форма участия -->
                        <div class="details-participation">
                            <div class="details-participation-header">
                                <div class="details-participation-title">Участвовать в долевом приобретении</div>
                                <div class="details-participation-status">
                                    <span id="minTicketLabel">Мин. взнос: –</span><br>
                                    <?php if ($currentUser): ?>
                                    <small>Вы участвуете как:
                                        <?= htmlspecialchars($currentUser['name']) ?> (
                                        <?= htmlspecialchars($currentUser['email']) ?>)
                                    </small>
                                    <?php else: ?>
                                    <small>Для участия войдите или зарегистрируйтесь.</small>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="progress-wrap">
                                <div class="progress-bar">
                                    <div class="progress-inner" id="progressInner"></div>
                                </div>
                                <div class="progress-labels">
                                    <span id="progressCollected">Собрано: –</span>
                                    <span id="progressRemaining">Осталось: –</span>
                                </div>
                            </div>

                            <div class="participants-summary">
                                <span>Текущие партнёры: <strong id="participantsCount">–</strong></span>
                                <span>Доступно слотов: <strong id="slotsRemaining">–</strong></span>
                            </div>

                            <form id="participationForm">
                                <div class="form-row">
                                    <label for="investmentAmount">Сумма участия</label>
                                    <div class="input-inline">
                                        <div class="input-prefix">€</div>
                                        <input type="number" id="investmentAmount" min="0" step="500"
                                            placeholder="Укажите сумму">
                                        <div class="input-suffix" id="shareInfo">–</div>
                                    </div>
                                    <div class="error-text" id="errorAmount"></div>
                                </div>

                                <div class="form-footer">
                                    <div class="form-note">
                                        После подтверждения менеджером объект и ваша доля появятся в личном кабинете.
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm" id="participateBtn">
                                        Участвовать
                                    </button>
                                </div>
                            </form>

                            <?php if (!$currentUser): ?>
                            <script>
                                // Простое отключение формы, если не авторизован
                                document.addEventListener('DOMContentLoaded', function () {
                                    const amount = document.getElementById('investmentAmount');
                                    const btn = document.getElementById('participateBtn');
                                    if (amount && btn) {
                                        amount.disabled = true;
                                        btn.disabled = true;
                                    }
                                });
                            </script>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>

            <!-- БЛОК ДЛЯ ПАРТНЁРОВ -->
            <section id="partners" class="section-partners">
                <div class="section-inner">
                    <h2>Партнёрская модель</h2>
                    <p>
                        Мы работаем с частными инвесторами и корпоративными партнёрами, которые хотят диверсифицировать
                        портфель за счёт недвижимости в разных странах.
                    </p>
                    <div class="partners-grid">
                        <div class="partner-card">
                            <h3>1. Регистрация</h3>
                            <p>Заполните короткую анкету, подтвердите e-mail и получите доступ в личный кабинет
                                партнёра.</p>
                        </div>
                        <div class="partner-card">
                            <h3>2. Выбор объекта</h3>
                            <p>Сравните объекты по доходности, рискам и географии. Каждый объект проходит многоуровневую
                                проверку.</p>
                        </div>
                        <div class="partner-card">
                            <h3>3. Долевое участие</h3>
                            <p>Укажите сумму участия. Платформа автоматически рассчитает вашу долю в объекте и
                                прогнозируемый доход.</p>
                        </div>
                        <div class="partner-card">
                            <h3>4. Управление и отчётность</h3>
                            <p>Мы берём на себя управление объектом, аренду и отчётность. Вы получаете выплаты и
                                аналитику онлайн.</p>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer>
            © <span>RealEstate Share</span>. Управление коммерческой и жилой недвижимостью, долевое участие партнёров.
        </footer>

        <!-- Toast -->
        <div class="success-toast" id="successToast">
            <div class="success-toast-icon">✔</div>
            <div class="success-toast-text">
                <div class="success-toast-title">Спасибо за участие!</div>
                <div class="success-toast-body">
                    Заявка на долевое участие по выбранному объекту сохранена.
                    Обновлены остаток суммы и количество партнёров.
                </div>
            </div>
            <div class="success-toast-close" id="toastCloseBtn">✕</div>
        </div>

        <!-- Модальное окно ВХОДА -->
        <div class="modal-backdrop" id="loginModal">
            <div class="modal">
                <div class="modal-header">
                    <h3>Вход партнёра</h3>
                    <button class="modal-close" data-modal-close>&times;</button>
                </div>
                <form method="post" action="auth.php">
                    <input type="hidden" name="action" value="login">
                    <div class="modal-body">
                        <div class="form-row">
                            <label for="loginEmail">E-mail</label>
                            <input type="email" id="loginEmail" name="email" required placeholder="you@example.com">
                        </div>
                        <div class="form-row">
                            <label for="loginPassword">Пароль</label>
                            <input type="password" id="loginPassword" name="password" required placeholder="Ваш пароль">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline btn-sm" data-modal-close>Отмена</button>
                        <button type="submit" class="btn btn-primary btn-sm">Войти</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Модальное окно РЕГИСТРАЦИИ -->
        <div class="modal-backdrop" id="registerModal">
            <div class="modal">
                <div class="modal-header">
                    <h3>Регистрация партнёра</h3>
                    <button class="modal-close" data-modal-close>&times;</button>
                </div>
                <form method="post" action="auth.php">
                    <input type="hidden" name="action" value="register">
                    <div class="modal-body">
                        <div class="form-row">
                            <label for="regName">Имя и фамилия</label>
                            <input type="text" id="regName" name="name" required placeholder="Иван Петров">
                        </div>
                        <div class="form-row">
                            <label for="regEmail">E-mail</label>
                            <input type="email" id="regEmail" name="email" required placeholder="you@example.com">
                        </div>
                        <div class="form-row">
                            <label for="regPassword">Пароль</label>
                            <input type="password" id="regPassword" name="password" required
                                placeholder="Минимум 6 символов">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline btn-sm" data-modal-close>Отмена</button>
                        <button type="submit" class="btn btn-primary btn-sm">Зарегистрироваться</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Перед app.js прокидываем данные PHP -> JS -->
    <script>
        window.APP_PROPERTIES = <?= json_encode($properties, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK) ?>;
        window.APP_USER = <?= $currentUser ? json_encode($currentUser, JSON_UNESCAPED_UNICODE) : 'null' ?>;
    </script>
    <script src="app.js"></script>
</body>

</html>