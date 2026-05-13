<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';
include 'includes/app_helpers.php';

$username = $_SESSION['username'];
$user_groups = [];
$permissions_sql = "SELECT group_name_1, group_name_2, group_name_3 FROM user_permissions WHERE username='$username'";
$permissions_result = $conn->query($permissions_sql);
if ($permissions_result->num_rows > 0) {
    $user_permissions = $permissions_result->fetch_assoc();
    foreach (['group_name_1', 'group_name_2', 'group_name_3'] as $group) {
        if (!empty($user_permissions[$group])) {
            $user_groups[] = $user_permissions[$group];
        }
    }
}

$allowed_departments = [];
if (!empty($user_groups)) {
    $groups_in = "'" . implode("','", $user_groups) . "'";
    $departments_sql = "SELECT DISTINCT department FROM department_groups WHERE group_name IN ($groups_in) OR group_name1 IN ($groups_in) OR group_name2 IN ($groups_in)";
    $departments_result = $conn->query($departments_sql);
    while ($row = $departments_result->fetch_assoc()) {
        $allowed_departments[] = $row['department'];
    }
}

$today_timestamp = strtotime('today');
$current_week_start = strtotime('last Saturday', $today_timestamp);
if (date('w', $today_timestamp) == 6) {
    $current_week_start = $today_timestamp;
}

$week_options = [];
for ($offset = -5; $offset <= 5; $offset++) {
    $week_start = strtotime(($offset >= 0 ? '+' : '') . $offset . ' week', $current_week_start);
    $week_end = strtotime('+6 days', $week_start);
    $week_options[] = [
        'label' => date('Y-m-d', $week_start) . ' to ' . date('Y-m-d', $week_end),
        'start_date' => date('Y-m-d', $week_start),
        'end_date' => date('Y-m-d', $week_end),
    ];
}

$selected_week = date('Y-m-d', $current_week_start);
$selected_department = '';
$department_table = [];
$bus_line_table = [];
$submitted_employees_result = null;
$start_of_week = '';
$end_of_week = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_week = $_POST['week'] ?? '';
    $selected_department = $_POST['department'] ?? '';

    $start_of_week = date('Y-m-d', strtotime('last Saturday', strtotime($selected_week)));
    if (date('w', strtotime($selected_week)) == 6) {
        $start_of_week = date('Y-m-d', strtotime($selected_week));
    }
    $end_of_week = date('Y-m-d', strtotime('+6 days', strtotime($start_of_week)));

    $submitted_employees_sql = ($selected_department === 'all')
        ? "SELECT employee_code, employee_name, bus_line_name, department, job, start_date, end_date FROM night_shift WHERE department IN ('" . implode("','", $allowed_departments) . "') AND start_date = '$start_of_week' AND end_date = '$end_of_week' order by department asc"
        : "SELECT employee_code, employee_name, bus_line_name, department, job, start_date, end_date FROM night_shift WHERE department = '$selected_department' AND start_date = '$start_of_week' AND end_date = '$end_of_week'";
    $submitted_employees_result = $conn->query($submitted_employees_sql);

    while ($row = $submitted_employees_result->fetch_assoc()) {
        if (!isset($department_table[$row['department']])) {
            $department_table[$row['department']] = 0;
        }
        $department_table[$row['department']]++;

        if (!isset($bus_line_table[$row['bus_line_name']])) {
            $bus_line_table[$row['bus_line_name']] = 0;
        }
        $bus_line_table[$row['bus_line_name']]++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Night Shift Weekly Report</title>
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="icon" type="image/png" href="images/logo.png">
    <style>
        .report-dual-grid {
            display: flex !important;
            gap: 1.5rem;
            align-items: flex-start;
            flex-wrap: nowrap;
        }

        .report-dual-grid > .report-block {
            flex: 1 1 0;
            min-width: 0;
            width: calc(50% - 0.75rem);
        }

        .report-block h3 {
            margin-bottom: 0.75rem;
        }

        @media (max-width: 900px) {
            .report-dual-grid {
                flex-direction: column;
            }

            .report-dual-grid > .report-block {
                width: 100%;
            }
        }
    </style>
    <script>
        function printArea(areaId) {
            var printContents = document.getElementById(areaId).outerHTML;
            var baseHref = window.location.href;
            var printWindow = window.open('', '_blank');
            printWindow.document.open();
            printWindow.document.write('<html><head><title>Print</title><base href="' + baseHref + '"><link rel="stylesheet" href="assets/css/app.css"><style>@page{size:auto;margin:12mm;}body{font-family:Arial,sans-serif;background:#fff;padding:16px;color:#111;}.summary-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:16px;}.summary-card,.table-scroll{background:#fff;border:1px solid #d7dee7;border-radius:12px;padding:12px;box-shadow:none;overflow:visible;}.summary-card strong{display:block;margin-bottom:4px;}.summary-card p{margin:0;}.report-dual-grid{display:flex!important;gap:12px;align-items:flex-start;flex-wrap:nowrap;}.report-dual-grid>.report-block{flex:1 1 0;min-width:0;width:calc(50% - 6px);}.report-block h3{margin:0 0 8px;}.panel-actions{display:none!important;}table{width:100%;border-collapse:collapse;margin:0;}th,td{padding:6px;text-align:left;border:1px solid #d7dee7;font-size:11px;}th{background:#8f1a1e!important;color:#fff!important;}@media print{body{padding:0;}.report-dual-grid,.summary-grid{break-inside:avoid;}}</style></head><body>');
            printWindow.document.write(printContents);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.focus();
            setTimeout(function () {
                printWindow.print();
                printWindow.close();
            }, 500);
        }
    </script>
</head>
<body>
<?php
app_render_page_header('NR', 'Night Shift Report', 'Weekly shift summaries and submitted employee lists.', [
    ['label' => 'Home', 'href' => 'welcome.php'],
    ['label' => 'Night Shift', 'href' => 'shifts_home.php'],
    ['label' => 'Logout', 'href' => 'logout.php'],
]);
app_render_page_hero('Second-level page', 'The weekly night shift report now uses the shared app shell.', 'You can still generate the same department, bus line, and employee reports, but the filters and output sit inside a cleaner responsive layout.', [
    ['title' => 'Week Options', 'text' => (string) count($week_options) . ' generated periods'],
    ['title' => 'Departments', 'text' => (string) count($allowed_departments) . ' available selections'],
]);
app_open_content_panel('Generate Weekly Report', 'Choose a department and week, then print either the summary tables or the employee list.');
?>
<form method="POST" action="night_report.php">
    <div class="form-row">
        <label for="department">Department</label>
        <select id="department" name="department" required>
            <option value="all" <?php echo ($selected_department === 'all') ? 'selected' : ''; ?>>All Departments</option>
            <?php foreach ($allowed_departments as $department): ?>
                <option value="<?php echo htmlspecialchars($department); ?>" <?php echo ($selected_department === $department) ? 'selected' : ''; ?>><?php echo htmlspecialchars($department); ?></option>
            <?php endforeach; ?>
        </select>
        <label for="week">Week</label>
        <select id="week" name="week" required>
            <option value="">Select a week range</option>
            <?php foreach ($week_options as $option): ?>
                <option value="<?php echo $option['start_date']; ?>" <?php echo ($selected_week === $option['start_date']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($option['label']); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="button">Generate Report</button>
    </div>
</form>

<?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && $submitted_employees_result): ?>
    <div class="summary-grid" id="printAreaTables">
        <div class="summary-card"><strong>Total Submitted Employees</strong><p><?php echo array_sum($department_table); ?></p></div>
        <div class="summary-card"><strong>Week Range</strong><p><?php echo htmlspecialchars($start_of_week . ' to ' . $end_of_week); ?></p></div>
    </div>

    <div class="report-dual-grid" id="printAreaSummary">
        <div class="table-scroll report-block">
            <h3>Department Information</h3>
            <table>
                <thead><tr><th>Department</th><th>Number of Employees</th></tr></thead>
                <tbody>
                    <?php foreach ($department_table as $department => $count): ?>
                        <tr><td><?php echo htmlspecialchars($department); ?></td><td><?php echo htmlspecialchars($count); ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="table-scroll report-block">
            <h3>Bus Line Information</h3>
            <table>
                <thead><tr><th>Bus Line</th><th>Number of Employees</th></tr></thead>
                <tbody>
                    <?php foreach ($bus_line_table as $bus_line => $count): ?>
                        <tr><td><?php echo htmlspecialchars($bus_line); ?></td><td><?php echo htmlspecialchars($count); ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel-actions">
        <button class="button" type="button" onclick="printArea('printAreaSummary')">Print Department and Bus Line Information</button>
    </div>

    <div id="printAreaSubmittedList" class="table-scroll">
        <h3>Submitted Employee List</h3>
        <table>
            <thead>
                <tr><th>Employee Code</th><th>Employee Name</th><th>Department</th><th>Job</th><th>Bus Line</th><th>Start Date</th><th>End Date</th></tr>
            </thead>
            <tbody>
                <?php $submitted_employees_result->data_seek(0); while ($row = $submitted_employees_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['employee_code']); ?></td>
                        <td><?php echo htmlspecialchars($row['employee_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['department']); ?></td>
                        <td><?php echo htmlspecialchars($row['job']); ?></td>
                        <td><?php echo htmlspecialchars($row['bus_line_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['start_date']); ?></td>
                        <td><?php echo htmlspecialchars($row['end_date']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="panel-actions">
        <button class="button" type="button" onclick="printArea('printAreaSubmittedList')">Print Submitted Employee List</button>
    </div>
<?php endif; ?>
<?php
app_close_content_panel();
app_render_page_end();
?>
<script src="assets/js/app.js"></script>
</body>
</html>
