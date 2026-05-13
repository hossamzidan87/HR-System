<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';
include 'includes/app_helpers.php';

$username = $_SESSION['username'];
$accessMap = app_get_access_map($conn, $username);
$modules = app_get_dashboard_modules($accessMap, $username);
$currentModule = app_get_module_by_id($modules, 'overtime');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Overtime Request - Home</title>
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
                    <h1>Overtime Workspace</h1>
                    <p>Manage overtime requests and reporting for <?php echo app_escape($username); ?>.</p>
                </div>
            </div>
            <div class="toolbar-actions">
                <a class="ghost-chip" href="welcome.php">Home</a>
                <a class="ghost-chip" href="logout.php">Logout</a>
            </div>
        </header>

        <section class="hero-panel">
            <div class="hero-copy">
                <span class="hero-kicker">Operations module</span>
                <h2>Submit requests, review teams, and export overtime insights from one place.</h2>
                <p>The module home is now structured as a task dashboard so supervisors can jump straight into the next action.</p>
                <div class="hero-actions">
                    <a class="btn-primary" href="overtime.php">Create Request</a>
                    <a class="btn-secondary" href="overtime_report.php">Open Report</a>
                </div>
            </div>
            <aside class="hero-aside">
                <article class="info-card">
                    <strong><?php echo app_escape($currentModule['title'] ?? 'Overtime'); ?></strong>
                    <p><?php echo app_escape($currentModule['description'] ?? ''); ?></p>
                </article>
                <article class="info-card">
                    <strong>Quick flow</strong>
                    <p>Request entry, employee lookup, reporting, and admin settings are grouped below.</p>
                </article>
            </aside>
        </section>

        <section class="content-panel">
            <div class="section-head">
                <div class="section-title">
                    <h2>Quick Actions</h2>
                    <p>The most common overtime tasks are one click away.</p>
                </div>
            </div>
            <div class="quick-grid">
                <a class="quick-card" href="overtime.php"><strong>Overtime Request</strong><p>Create or edit daily overtime entries.</p></a>
                <a class="quick-card" href="employee_list.php"><strong>Employee List</strong><p>Browse employees and monthly overtime totals.</p></a>
                <a class="quick-card" href="overtime_report.php"><strong>Overtime Report</strong><p>Open filtered reports for processed records.</p></a>
                <?php if (app_is_admin($username) && app_has_page_access($accessMap, 'overtime_cpanel.php')): ?>
                    <a class="quick-card" href="overtime_cpanel.php"><strong>Control Panel</strong><p>Update rules, bus lines, calendars, and imported employee data.</p></a>
                <?php endif; ?>
            </div>
        </section>
    </main>
<script src="assets/js/app.js"></script>
</body>
</html>

