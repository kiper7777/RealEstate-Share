<?php
require_once 'db.php';
require_once 'csrf.php';

$csrfToken = csrf_get_token();

// Текущий пользователь
$currentUser = null;
if (!empty($_SESSION['user_id'])) {
    $currentUser = [
        'id'    => $_SESSION['user_id'],
        'name'  => $_SESSION['user_name'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
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
    <title>RealEstate Share – Equity participation in real estate</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
</head>

<body>
    <div class="app-shell">
        <header>
            <div class="nav">
                <div class="logo">
                    <div class="logo-mark">R</div>
                    <div class="logo-text">
                        <div class="logo-title">RealEstate Share</div>
                        <div class="logo-subtitle">Commercial and residential real estate</div>
                    </div>
                </div>

                <div class="nav-actions">
                    <a href="#platform" class="nav-link">Platform</a>
                    <a href="#properties" class="nav-link">Objects</a>
                    <a href="#partners" class="nav-link">To partners</a>

                    <?php if ($currentUser): ?>
                        <span class="nav-user">
                            Hello,
                            <?= htmlspecialchars($currentUser['name']) ?>
                        </span>

                        <a href="dashboard.php" class="btn btn-primary btn-sm">Cabinet</a>
                        <a href="logout.php" class="btn btn-outline btn-sm">Log out</a>
                    <?php else: ?>
                        <button class="btn btn-outline" id="loginBtn" type="button">Partner Login</button>
                        <button class="btn btn-primary" id="registerBtn" type="button">Become a partner</button>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <main>
            <!-- HERO -->
            <section class="hero" id="platform">
                <div class="hero-copy">
                    <div class="hero-badge">
                        <span class="hero-badge-dot"></span>
                        <span>Property management + online equity participation</span>
                    </div>

                    <h1 class="hero-title">
                        Invest in <span>residential and commercial properties</span> all over the world
                    </h1>

                    <p class="hero-subtitle">
                        A platform for partners who want to participate in the purchase of real estate with a transparent economic model: you can see the price, rent, return on investment, and financing status of each property.
                    </p>

                    <div class="hero-cta-row">
                        <a href="#properties" class="btn btn-primary">View available properties</a>
                        <a href="#partners" class="btn btn-outline">How does this work</a>
                    </div>

                    <div class="hero-stats">
                        <div class="hero-stat">
                            <div class="hero-stat-label">Managed assets</div>
                            <div class="hero-stat-value">48+ million €</div>
                        </div>

                        <div class="hero-stat">
                            <div class="hero-stat-label">Cities</div>
                            <div class="hero-stat-value">12 countries</div>
                        </div>

                        <div class="hero-stat">
                            <div class="hero-stat-label">Partners</div>
                            <div class="hero-stat-value">320+ investors</div>
                        </div>
                    </div>
                </div>

                <div class="hero-panel">
                    <div class="hero-panel-header">
                        <div class="hero-panel-title">Example of a deal</div>
                        <div class="hero-panel-tag">Live deal · Spain</div>
                    </div>

                    <div class="hero-panel-main">
                        <div class="hero-panel-property">Frontline apartments, Costa Blanca</div>
                        <div class="hero-panel-location">Spain · Mediterranean Sea</div>
                        <div class="hero-panel-price">€200,000 · from €5,000 per share</div>

                        <div class="hero-panel-meta">
                            <span class="hero-chip">Annual rental income ~ 7.8%</span>
                            <span class="hero-chip">Payback period is expected to be ~9 years.</span>
                        </div>

                        <div class="hero-progress-wrap">
                            <div class="hero-progress-bar">
                                <div class="hero-progress-inner" style="width:60%;"></div>
                            </div>
                            <div class="hero-progress-labels">
                                <span>Raised: €120,000</span>
                                <span>Remaining: €80,000</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- CONTENT -->
            <section class="content" id="properties">
                <!-- LEFT COLUMN -->
                <div>
                    <div class="section-header">
                        <div>
                            <div class="section-title">Objects for shared participation</div>
                            <div class="section-subtitle">
                                Select an item to see details and conditions
                            </div>
                        </div>

                        <div class="pill-online">
                            <span class="pill-dot"></span>
                            <span>Online selection in real time</span>
                        </div>
                    </div>

                    <div class="filters">
                        <div class="filter-pill active" data-filter="all">All</div>
                        <div class="filter-pill" data-filter="residential">Residential</div>
                        <div class="filter-pill" data-filter="commercial">Commercial</div>
                        <div class="filter-pill" data-filter="europe">Europe</div>
                        <div class="filter-pill" data-filter="middleeast">Middle East</div>
                    </div>

                    <div id="propertiesList" class="properties-list"></div>
                </div>

                <!-- RIGHT COLUMN -->
                <div class="details-shell" id="detailsShell">
                    <div class="details-header">
                        <div>
                            <div class="details-name" id="detailsName">Select an object from the list</div>
                            <div class="details-location" id="detailsLocation"></div>
                            <div class="details-tags" id="detailsTags"></div>
                        </div>

                        <div class="details-price-block">
                            <div class="details-price-main" id="detailsPrice"></div>
                            <div class="details-price-label">Total cost of the property</div>
                        </div>
                    </div>

                    <div class="details-gallery">
                        <div class="gallery-main">
                            <div class="details-photo">
                                <img id="detailsMainImage" alt="">
                            </div>

                            <div class="details-thumbs" id="detailsThumbs"></div>

                            <div>
                                <div class="gallery-main-label">Overview of the property</div>
                                <div class="gallery-main-title" id="galleryTitle">Photos and layout</div>
                                <div class="gallery-main-meta" id="galleryMeta">
                                    There will be a media gallery here: photos, plans, additional materials.
                                </div>
                            </div>

                            <div class="gallery-main-meta-extra">
                               In the production version, you can connect media storage, a video tour, and property documents.
                            </div>
                        </div>

                        <div class="gallery-side">
                            <div class="gallery-tile">
                                <div class="gallery-tile-label">Object type</div>
                                <div class="gallery-tile-value" id="galleryType">–</div>
                            </div>

                            <div class="gallery-tile">
                                <div class="gallery-tile-label">Status</div>
                                <div class="gallery-tile-value" id="galleryStatus">Select an object</div>
                            </div>
                        </div>
                    </div>

                    <div class="details-description" id="detailsDescription">
                        To view detailed information about a property, including its economic attractiveness,
select it from the list on the left.
                    </div>

                    <div class="details-grid">
                        <!-- ECONOMICS -->
                        <div class="details-economics">
                            <div class="details-economics-title">Economic attractiveness</div>

                            <div class="metrics">
                                <div class="metric">
                                    <div class="metric-label">Annual rental rate</div>
                                    <div class="metric-value" id="metricRent">–</div>
                                </div>

                                <div class="metric">
                                    <div class="metric-label">Annual income</div>
                                    <div class="metric-value" id="metricYield">–</div>
                                </div>

                                <div class="metric">
                                    <div class="metric-label">Payback</div>
                                    <div class="metric-value" id="metricPayback">–</div>
                                </div>

                                <div class="metric">
                                    <div class="metric-label">Risk profile</div>
                                    <div class="metric-value" id="metricRisk">–</div>
                                </div>
                            </div>

                            <div class="economics-note" id="economicsNote">
                                All calculations are indicative only and do not constitute investment advice.
                            </div>
                        </div>

                        <!-- PARTICIPATION -->
                        <div class="details-participation">
                            <div class="details-participation-header">
                                <div class="details-participation-title">
                                   Participate in a shared acquisition
                                </div>

                                <div class="details-participation-status">
                                    <span id="minTicketLabel">Min. contribution: –</span><br>

                                    <?php if ($currentUser): ?>
                                        <small>
                                            You are participating as:
                                            <?= htmlspecialchars($currentUser['name']) ?>
                                            (<?= htmlspecialchars($currentUser['email']) ?>)
                                        </small>
                                    <?php else: ?>
                                        <small>To participate, please log in or register.</small>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="progress-wrap">
                                <div class="progress-bar">
                                    <div class="progress-inner" id="progressInner"></div>
                                </div>

                                <div class="progress-labels">
                                    <span id="progressCollected">Collected: –</span>
                                    <span id="progressRemaining">Left: –</span>
                                </div>
                            </div>

                            <div class="participants-summary">
                                <span>Current partners: <strong id="participantsCount">–</strong></span>
                                <span>Available slots: <strong id="slotsRemaining">–</strong></span>
                            </div>

                            <form id="participationForm">
                                <div class="form-row">
                                    <label for="investmentAmount">Participation amount</label>

                                    <div class="input-inline">
                                        <div class="input-prefix">€</div>
                                        <input
                                            type="number"
                                            id="investmentAmount"
                                            min="0"
                                            step="500"
                                            placeholder="Please indicate the amount">
                                        <div class="input-suffix" id="shareInfo">–</div>
                                    </div>

                                    <div class="error-text" id="errorAmount"></div>
                                </div>

                                <div class="form-footer">
                                    <div class="form-note">
                                        Once confirmed by the manager, the property and your share will appear in your personal account.
                                    </div>

                                    <button type="submit" class="btn btn-primary btn-sm" id="participateBtn">
                                        Participate
                                    </button>
                                </div>
                            </form>

                            <?php if (!$currentUser): ?>
                                <script>
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

            <!-- PARTNERS -->
            <section id="partners" class="section-partners">
                <div class="section-inner">
                    <h2>Affiliate model</h2>
                    <p>
                        We work with private investors and corporate partners who want to diversify their portfolios with real estate in different countries.
                    </p>

                    <div class="partners-grid">
                        <div class="partner-card">
                            <h3>1. Registration</h3>
                            <p>
                                Fill out a short form, confirm your email, and gain access to your partner account.
                            </p>
                        </div>

                        <div class="partner-card">
                            <h3>2. Selecting an object</h3>
                            <p>
                                Compare properties by profitability, risk, and geography.
Each property undergoes a multi-level review.
                            </p>
                        </div>

                        <div class="partner-card">
                            <h3>3. Shared participation</h3>
                            <p>
                                Specify your participation amount. The platform will automatically calculate your share in the property and the projected income.
                            </p>
                        </div>

                        <div class="partner-card">
                            <h3>4. Management and reporting</h3>
                            <p>
                                We handle property management, leasing, and reporting.
You receive payments and analytics online.
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer>
            © <span>RealEstate Share</span>. Commercial and residential real estate management, equity participation of partners.
        </footer>

        <!-- TOAST -->
        <div class="success-toast" id="successToast">
            <div class="success-toast-icon">✔</div>
            <div class="success-toast-text">
                <div class="success-toast-title">Thank you for participating!</div>
                <div class="success-toast-body">
                    The application for shared participation in the selected project has been saved.
The remaining amount and number of partners have been updated.
                </div>
            </div>
            <div class="success-toast-close" id="toastCloseBtn">✕</div>
        </div>

        <!-- LOGIN MODAL -->
        <div class="modal-backdrop" id="loginModal">
            <div class="modal">
                <div class="modal-header">
                    <h3>Partner Login</h3>
                    <button class="modal-close" data-modal-close type="button">&times;</button>
                </div>

                <form method="post" action="auth.php">
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                    <div class="modal-body">
                        <div class="form-row">
                            <label for="loginEmail">E-mail</label>
                            <input
                                type="email"
                                id="loginEmail"
                                name="email"
                                required
                                placeholder="you@example.com">
                        </div>

                        <div class="form-row">
                            <label for="loginPassword">Password</label>
                            <input
                                type="password"
                                id="loginPassword"
                                name="password"
                                required
                                placeholder="Your password">
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Login</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- REGISTER MODAL -->
        <div class="modal-backdrop" id="registerModal">
            <div class="modal">
                <div class="modal-header">
                    <h3>Partner registration</h3>
                    <button class="modal-close" data-modal-close type="button">&times;</button>
                </div>

                <form method="post" action="auth.php">
                    <input type="hidden" name="action" value="register">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                    <div class="modal-body">
                        <div class="form-row">
                            <label for="regName">First and last name</label>
                            <input
                                type="text"
                                id="regName"
                                name="name"
                                required
                                placeholder="John Smith">
                        </div>

                        <div class="form-row">
                            <label for="regEmail">E-mail</label>
                            <input
                                type="email"
                                id="regEmail"
                                name="email"
                                required
                                placeholder="you@example.com">
                        </div>

                        <div class="form-row">
                            <label for="regPassword">Password</label>
                            <input
                                type="password"
                                id="regPassword"
                                name="password"
                                required
                                placeholder="Minimum 6 characters">
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Register</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        window.APP_USER = <?= $currentUser ? json_encode($currentUser, JSON_UNESCAPED_UNICODE) : 'null' ?>;
        window.CSRF_TOKEN = <?= json_encode($csrfToken, JSON_UNESCAPED_UNICODE) ?>;
    </script>

    <script src="app.js"></script>
</body>

</html>