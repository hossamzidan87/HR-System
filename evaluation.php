<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';
include 'includes/app_helpers.php';

$username = $_SESSION['username'];
$accessMap = app_get_access_map($conn, $username);
$modules = app_get_dashboard_modules($accessMap, $username);
$currentModule = app_get_module_by_id($modules, 'evaluation');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Evaluation Home</title>
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
                    <h1>Evaluation Workspace</h1>
                    <p>Performance review tools in a focused dashboard layout.</p>
                </div>
            </div>
            <div class="toolbar-actions">
                <a class="ghost-chip" href="welcome.php">Home</a>
                <a class="ghost-chip" href="logout.php">Logout</a>
            </div>
        </header>

        <section class="hero-panel">
            <div class="hero-copy">
                <span class="hero-kicker">Performance module</span>
                <h2>Move between quarterly reviews, annual reviews, and reports without the old icon grid.</h2>
                <p>The updated interface makes the evaluation workflow feel like one coherent workspace instead of several disconnected pages.</p>
                <div class="hero-actions">
                    <a class="btn-primary" href="quarter_evaluation.php">Quarter Evaluation</a>
                    <a class="btn-secondary" href="quarter_report.php">Quarter Report</a>
                </div>
            </div>
            <aside class="hero-aside">
                <article class="info-card">
                    <strong><?php echo app_escape($currentModule['title'] ?? 'Evaluation'); ?></strong>
                    <p><?php echo app_escape($currentModule['description'] ?? ''); ?></p>
                </article>
                <article class="info-card">
                    <strong>Review cadence</strong>
                    <p>Use the quarter and annual flows below while keeping your current backend logic unchanged.</p>
                </article>
            </aside>
        </section>

        <section class="content-panel">
            <div class="section-head">
                <div class="section-title">
                    <h2>Quick Actions</h2>
                    <p>Open the next evaluation task directly.</p>
                </div>
            </div>
            <div class="quick-grid">
                <a class="quick-card" href="quarter_evaluation.php"><strong>Quarter Evaluation</strong><p>Create or update quarterly employee scores.</p></a>
                <a class="quick-card" href="quarter_report.php"><strong>Quarter Report</strong><p>Review filtered evaluation reports.</p></a>
                <a class="quick-card" href="annual_evaluation.php"><strong>Annual Evaluation</strong><p>Work through yearly review records.</p></a>
                <?php if (app_is_admin($username) && app_has_page_access($accessMap, 'evaluation_cpanel.php')): ?>
                    <a class="quick-card" href="evaluation_cpanel.php"><strong>Control Panel</strong><p>Manage evaluation periods, employee sync, and rules.</p></a>
                <?php endif; ?>
            </div>
        </section>
    </main>
<script src="assets/js/app.js"></script>
</body>
</html>

