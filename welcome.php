<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';
include 'includes/app_helpers.php';

$username = $_SESSION['username'];
$accessMap = app_get_access_map($conn, $username);
$modules = app_get_dashboard_modules($accessMap, $username);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Home</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="icon" type="image/png" href="images/logo.png">
</head>
<body>
    <main class="app-shell" data-dashboard data-dashboard-endpoint="api/dashboard_summary.php">
        <header class="topbar">
            <div class="brand-block">
                <div class="brand-mark"><img src="images/logo.png" alt="Logo"></div>
                <div class="brand-copy">
                    <h1>Operations Hub</h1>
                    <p>Welcome back, <?php echo app_escape($username); ?>.</p>
                </div>
            </div>

            <div class="toolbar">
                <div class="clock-chip" data-live-clock>Loading time...</div>
                <div class="status-chip" data-sadv-status>Checking salary advance status...</div>
                <a class="ghost-chip" href="setting.php">Settings</a>
                <a class="ghost-chip" href="logout.php">Logout</a>
            </div>
        </header>

        <section class="hero-panel">
            <div class="hero-copy">
                <span class="hero-kicker">Dynamic dashboard</span>
                <h2>Run the full HR workflow from one responsive home screen.</h2>
                <p>This new landing page turns the old image menu into a searchable, live workspace with shared styling.</p>
                <div class="hero-actions">
                    <a class="btn-primary" href="<?php echo app_escape($modules[0]['href'] ?? 'welcome.php'); ?>">Open Main Module</a>
                    <a class="btn-secondary" href="../meeting/">Meeting Room Booking</a>
                </div>
            </div>

            <aside class="hero-aside">
                <div class="info-stack">
                    <article class="info-card">
                        <strong data-quarter-label>Current evaluation cycle: loading...</strong>
                    </article>
                    <article class="info-card">
                        <strong><?php echo count($modules); ?> module<?php echo count($modules) === 1 ? '' : 's'; ?></strong>
                        <p>Your visible modules are filtered automatically from existing page permissions.</p>
                    </article>
                </div>
            </aside>
        </section>

        <section class="content-panel">
            <div class="section-head">
                <div class="section-title">
                    <h2>Live Snapshot</h2>
                    <p>Real-time counts for core HR activity.</p>
                </div>
            </div>
            <div class="stats-grid" data-summary-cards>
                <article class="stat-card"><strong>...</strong><p>Loading</p></article>
                <article class="stat-card"><strong>...</strong><p>Loading</p></article>
                <article class="stat-card"><strong>...</strong><p>Loading</p></article>
                <article class="stat-card"><strong>...</strong><p>Loading</p></article>
            </div>
        </section>

        <section class="content-panel">
            <div class="section-head">
                <div class="section-title">
                    <h2>Modules</h2>
                    <p>Jump directly into the tools you can access.</p>
                </div>
                <input class="page-filter" type="search" placeholder="Filter modules or shortcuts" data-module-filter>
            </div>

            <div class="module-grid" data-module-cards>
                <?php foreach ($modules as $module): ?>
                    <article class="module-card" data-module-card data-accent="<?php echo app_escape($module['accent']); ?>" data-title="<?php echo app_escape($module['title']); ?>" data-tags="<?php echo app_escape(implode(' ', array_column($module['links'], 'label'))); ?>">
                        <div class="module-head">
                            <div>
                                <h3><?php echo app_escape($module['title']); ?></h3>
                                <p><?php echo app_escape($module['description']); ?></p>
                            </div>
                            <div class="module-icon"><?php echo app_escape($module['icon']); ?></div>
                        </div>
                        <div class="module-links">
                            <a class="btn-primary" href="<?php echo app_escape($module['href']); ?>">Open</a>
                            <?php foreach ($module['links'] as $link): ?>
                                <a class="pill-link" href="<?php echo app_escape($link['href']); ?>"><?php echo app_escape($link['label']); ?></a>
                            <?php endforeach; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="empty-state is-hidden" data-module-empty>No matching modules found.</div>
        </section>
    </main>

    <script src="assets/js/app.js"></script>
</body>
</html>
