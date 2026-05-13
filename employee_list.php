<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';
include 'includes/app_helpers.php';

$username = $_SESSION['username'];

$permissions_sql = "SELECT group_name_1, group_name_2, group_name_3 FROM user_permissions WHERE username='$username'";
$permissions_result = $conn->query($permissions_sql);
$user_groups = [];
if ($permissions_result->num_rows > 0) {
    $user_permissions = $permissions_result->fetch_assoc();
    if ($user_permissions['group_name_1']) $user_groups[] = $user_permissions['group_name_1'];
    if ($user_permissions['group_name_2']) $user_groups[] = $user_permissions['group_name_2'];
    if ($user_permissions['group_name_3']) $user_groups[] = $user_permissions['group_name_3'];
}

$allowed_departments = [];
$departments_array = [];
$departments_list = "''";
if (!empty($user_groups)) {
    $groups_in = "'" . implode("','", $user_groups) . "'";
    $departments_sql = "SELECT DISTINCT department FROM department_groups WHERE group_name IN ($groups_in) OR group_name1 IN ($groups_in) OR group_name2 IN ($groups_in)";
    $departments_result = $conn->query($departments_sql);
    if ($departments_result->num_rows > 0) {
        while ($row = $departments_result->fetch_assoc()) {
            $allowed_departments[] = $row['department'];
            $departments_array[] = $row['department'];
        }
    }
    if (!empty($departments_array)) {
        $departments_list = "'" . implode("','", $departments_array) . "'";
    }
}

function getEmployeesByDepartment($conn, $department, $departments_list) {
    $sql = ($department === 'all')
        ? "SELECT employee_code, first_name, gender, department, job FROM employees WHERE department IN ($departments_list)"
        : "SELECT employee_code, first_name, gender, department, job FROM employees WHERE department = ?";
    $stmt = $conn->prepare($sql);
    if ($department !== 'all') {
        $stmt->bind_param("s", $department);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$selected_date = date('Y-m-d');
$week_start = date('Y-m-d', strtotime('last Saturday', strtotime($selected_date)));
if (date('w', strtotime($selected_date)) == 6) {
    $week_start = date('Y-m-d', strtotime($selected_date));
}
$week_end = date('Y-m-d', strtotime('next Friday', strtotime($selected_date)));
if (date('w', strtotime($selected_date)) == 5) {
    $week_end = date('Y-m-d', strtotime($selected_date));
}

function getOvertimeData($conn, $week_start, $week_end) {
    $sql = "SELECT employee_code, SUM(CASE
                WHEN DAYOFWEEK(overtime_date) IN (6, 7) AND overtime_date NOT IN (SELECT days FROM calendar WHERE type = 'saturday') THEN 8
                WHEN overtime_date IN (SELECT days FROM calendar WHERE type = 'holiday') THEN 0
                ELSE 2
            END) AS total_hours
            FROM overtime
            WHERE types = 'normal'
            AND overtime_date BETWEEN ? AND ?
            GROUP BY employee_code";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $week_start, $week_end);
    $stmt->execute();
    $result = $stmt->get_result();
    $overtime_data = [];
    while ($row = $result->fetch_assoc()) {
        $overtime_data[$row['employee_code']] = $row['total_hours'];
    }
    return $overtime_data;
}

function renderEmployeeTable($employees, $overtime_data, $selected_department) {
    echo "<div id='printArea' class='stack-gap'>";
    echo "<div class='summary-card'><strong>Selected Department</strong><p>" . htmlspecialchars($selected_department) . "</p></div>";
    echo "<div class='table-scroll'><table>";
    echo "<thead><tr><th>Code</th><th>Name</th><th>Department</th><th>Job</th><th>Remaining H</th><th>Transportation</th></tr></thead>";
    echo "<tbody>";

    foreach ($employees as $row) {
        $employee_code = $row['employee_code'];
        $employee_name = $row['first_name'];
        $department = $row['department'];
        $job = $row['job'];
        $total_hours = $overtime_data[$employee_code] ?? 0;
        $remaining_hours = max(-20, 12 - $total_hours);
        $row_class = ($total_hours >= 12) ? 'exceeded' : '';

        echo "<tr class='$row_class'>";
        echo "<td>" . htmlspecialchars($employee_code) . "</td>";
        echo "<td>" . htmlspecialchars($employee_name) . "</td>";
        echo "<td>" . htmlspecialchars($department) . "</td>";
        echo "<td>" . htmlspecialchars($job) . "</td>";
        echo "<td>" . $remaining_hours . "</td>";
        echo "<td></td>";
        echo "</tr>";
    }

    echo "</tbody></table></div></div>";
}

$selected_department = $_GET['department'] ?? '';
$employees = [];
$overtime_data = [];
$show_table = false;
if ($selected_department !== '' && (in_array($selected_department, $allowed_departments, true) || $selected_department === 'all')) {
    $employees = getEmployeesByDepartment($conn, $selected_department, $departments_list);
    $overtime_data = getOvertimeData($conn, $week_start, $week_end);
    $show_table = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Employee List</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="icon" type="image/png" href="images/logo.png">
    <script>
        function showEmployees() {
            var department = document.getElementById('department').value;
            window.location.href = 'employee_list.php?department=' + encodeURIComponent(department);
        }

        function printTable() {
            var printContents = document.getElementById('printArea').innerHTML;
            var printWindow = window.open('', '_blank');
            printWindow.document.open();
            printWindow.document.write('<html><head><title>Print</title><style>body{font-size:9pt;font-family:Arial,sans-serif;}table{width:100%;border-collapse:collapse;}th,td{padding:5px;font-size:8pt;border:1px solid black;white-space:nowrap;}th{background:#8f1a1e;color:#fff;}</style></head><body>');
            printWindow.document.write(printContents);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
            printWindow.close();
        }
    </script>
</head>
<body>
<?php
app_render_page_header('EL', 'Employee List', 'Department views with weekly overtime balance.', [
    ['label' => 'Home', 'href' => 'welcome.php'],
    ['label' => 'Overtime', 'href' => 'overtime_home.php'],
    ['label' => 'Logout', 'href' => 'logout.php'],
]);
app_render_page_hero('Second-level page', 'Employee lookup and overtime balance are now easier to scan.', 'This page keeps the existing data logic but uses the new inner shell so filters, tables, and print actions feel consistent with the redesigned dashboard.', [
    ['title' => 'Week Range', 'text' => $week_start . ' to ' . $week_end],
    ['title' => 'Departments', 'text' => (string) count($allowed_departments) . ' accessible groups'],
]);
app_open_content_panel('Filter Employees', 'Choose one department or view all departments you can access.');
?>
<form id="employeeForm" method="GET" action="employee_list.php">
    <div class="form-row">
        <label for="department">Department</label>
        <select id="department" name="department" onchange="showEmployees()">
            <option value="">Select a department</option>
            <option value="all" <?php echo $selected_department === 'all' ? 'selected' : ''; ?>>All Departments</option>
            <?php foreach ($allowed_departments as $department): ?>
                <option value="<?php echo htmlspecialchars($department); ?>" <?php echo $selected_department === $department ? 'selected' : ''; ?>><?php echo htmlspecialchars($department); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>
<?php if ($show_table): ?>
    <div class="panel-actions">
        <button type="button" onclick="printTable()">Print</button>
    </div>
    <?php renderEmployeeTable($employees, $overtime_data, $selected_department); ?>
<?php else: ?>
    <div class="message-card">Please select a department from the list above.</div>
<?php endif; ?>
<?php
app_close_content_panel();
app_render_page_end();
?>
<script src="assets/js/app.js"></script>
</body>
</html>


