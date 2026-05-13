<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';
include 'includes/app_helpers.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control Panel</title>
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="icon" type="image/png" href="images/logo.png">
</head>
<body>
<?php
app_render_page_header('CP', 'Control Panel', 'Administrative tools for permissions, rules, evaluations, shifts, and salary advance settings.', [
    ['label' => 'Home', 'href' => 'welcome.php'],
    ['label' => 'Logout', 'href' => 'logout.php'],
]);
app_render_page_hero('Admin area', 'All control modules now launch from the same app shell.', 'The cpanel home has been rebuilt as a clean action board so you can move into each admin area with the same responsive interface used across the rest of the revamp.', [
    ['title' => 'Admin Tools', 'text' => 'General, overtime, shifts, evaluation, and salary advance controls.'],
    ['title' => 'Shared UI', 'text' => 'This page now matches the redesigned dashboard and second-level screens.'],
]);
app_open_content_panel('Administrative Modules', 'Choose the control area you want to manage.');
?>
<div class="quick-grid">
    <a class="quick-card" href="general_cpanel.php"><strong>General Cpanel</strong><p>Manage department groups, user permissions, and page access.</p></a>
    <a class="quick-card" href="overtime_cpanel.php"><strong>Overtime Cpanel</strong><p>Maintain overtime rules, bus lines, calendars, and employee imports.</p></a>
    <a class="quick-card" href="shifts_cpanel.php"><strong>Shifts Cpanel</strong><p>Adjust the night shift access window.</p></a>
    <a class="quick-card" href="evaluation_cpanel.php"><strong>Evaluation Cpanel</strong><p>Manage evaluation periods, quarter rules, and evaluation imports.</p></a>
    <a class="quick-card" href="sadv_cpanel.php"><strong>Salary Advance Cpanel</strong><p>Set the salary advance opening and closing dates.</p></a>
</div>
<?php
app_close_content_panel();
app_render_page_end();
?>
<script src="assets/js/app.js"></script>
</body>
</html>

