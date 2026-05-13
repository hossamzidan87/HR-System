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
    <title>Overtime Report</title>
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="icon" type="image/png" href="images/logo.png">
</head>
<body>
<?php
app_render_page_header('OR', 'Overtime Reports', 'Jump into the right overtime report without the old icon strip.', [
    ['label' => 'Home', 'href' => 'welcome.php'],
    ['label' => 'Overtime', 'href' => 'overtime_home.php'],
    ['label' => 'Logout', 'href' => 'logout.php'],
]);
app_render_page_hero('Second-level page', 'Choose the report type you need from a cleaner report launcher.', 'Daily, daily night, and weekly overtime reports are grouped as action cards so the reporting workflow matches the redesigned application shell.', [
    ['title' => 'Daily', 'text' => 'Department and employee-level daily overtime summaries.'],
    ['title' => 'Weekly', 'text' => 'Weekly trends, exceeded employees, and totals.'],
    ['title' => 'Monthly', 'text' => 'Monthly ranges with exceeded-48-hours list.'],
]);
app_open_content_panel('Report Shortcuts', 'Select the report view you want to open.');
?>
<div class="quick-grid">
    <form method="POST" action="daily_report.php" class="inline-form"><button class="quick-card" type="submit" name="report_type" value="daily"><strong>Daily Report</strong><p>Open the standard daily overtime report.</p></button></form>
    <form method="POST" action="daily_night_report.php" class="inline-form"><button class="quick-card" type="submit" name="report_type" value="daily"><strong>Daily Night</strong><p>Open the daily night overtime report.</p></button></form>
    <form method="GET" action="weekly_overtime.php" class="inline-form"><button class="quick-card" type="submit" name="report_type" value="weekly"><strong>Weekly Report</strong><p>Open the weekly overtime dashboard and print views.</p></button></form>
    <form method="GET" action="monthly_report.php" class="inline-form"><button class="quick-card" type="submit" name="report_type" value="monthly"><strong>Monthly Report</strong><p>Open monthly overtime reports dashboard.</p></button></form>
</div>
<?php
app_close_content_panel();
app_render_page_end();
?>
<script src="assets/js/app.js"></script>
</body>
</html>

