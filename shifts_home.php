<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';
include 'includes/app_helpers.php';

$username = $_SESSION['username'];
$accessMap = app_get_access_map($conn, $username);
$modules = app_get_dashboard_modules($accessMap, $username);
$currentModule = app_get_module_by_id($modules, 'shifts');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Night Shift Request - Home</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="icon" type="image/png" href="images/logo.png">
</head>
<body>
    <main class="app-shell">
        <header class="topbar">
            <div class="brand-block">
                <div class="brand-mark"><img src="images/logo.png" alt="Logo"></div>
                <div class="brand-copy">
                    <h1>Night Shift Workspace</h1>
                    <p>Shift planning tools in a cleaner layout for <?php echo app_escape($username); ?>.</p>
                </div>
            </div>
            <div class="toolbar-actions">
                <a class="ghost-chip" href="welcome.php">Home</a>
                <a class="ghost-chip" href="logout.php">Logout</a>
            </div>
        </header>

        <section class="hero-panel">
            <div class="hero-copy">
                <span class="hero-kicker">Scheduling module</span>
                <h2>Control shift windows, assignments, and reports with fewer clicks.</h2>
                <p>The refreshed module page groups the main shift tasks into a clear action board that works better on desktop and mobile.</p>
                <div class="hero-actions">
                    <a class="btn-primary" href="night_shift.php">Manage Shift Plan</a>
                    <a class="btn-secondary" href="night_report.php">Open Shift Report</a>
                </div>
            </div>
            <aside class="hero-aside">
                <article class="info-card">
                    <strong><?php echo app_escape($currentModule['title'] ?? 'Night Shift'); ?></strong>
                    <p><?php echo app_escape($currentModule['description'] ?? ''); ?></p>
                </article>
                <article class="info-card">
                    <strong>Connected workflow</strong>
                    <p>Night shift entries continue feeding related overtime logic in your existing backend.</p>
                </article>
            </aside>
        </section>

        <section class="content-panel">
            <div class="section-head">
                <div class="section-title">
                    <h2>Quick Actions</h2>
                    <p>Open the main night shift tasks directly.</p>
                </div>
            </div>
            <div class="quick-grid">
                <a class="quick-card" href="night_shift.php"><strong>Night Shift Request</strong><p>Create, copy, and clean up shift schedules.</p></a>
                <a class="quick-card" href="night_report.php"><strong>Night Shift Report</strong><p>Review processed shift records and department output.</p></a>
                <?php if (app_is_admin($username) && app_has_page_access($accessMap, 'shifts_cpanel.php')): ?>
                    <a class="quick-card" href="shifts_cpanel.php"><strong>Control Panel</strong><p>Adjust opening and closing dates for shift entry.</p></a>
                <?php endif; ?>
            </div>
        </section>
    </main>
<script src="assets/js/app.js"></script>
</body>
</html>

