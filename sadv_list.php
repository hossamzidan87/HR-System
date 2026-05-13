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

$rule_sql = "SELECT sadv_start, sadv_end FROM rules WHERE name = 'close_time'";
$rule_result = $conn->query($rule_sql);
$rule = $rule_result->fetch_assoc();
$sadv_start = $rule['sadv_start'];
$sadv_end = $rule['sadv_end'];
$current_time = date('Y-m-d H:i:s');

if ($current_time < $sadv_start || $current_time > $sadv_end) {
    app_render_state_page(
        'SA',
        'Salary Advance List',
        'Department-based salary advance sheets with a cleaner print workflow.',
        'Salary Advance Window',
        'Salary Advance Page Closed',
        'This page is currently closed and usually opens on the 17th of every month.',
        [
            ['label' => 'Home', 'href' => 'welcome.php'],
            ['label' => 'Salary Advance', 'href' => 'sadv_list.php'],
            ['label' => 'Logout', 'href' => 'logout.php'],
        ],
        [
            ['title' => 'Window Start', 'text' => $sadv_start],
            ['title' => 'Window End', 'text' => $sadv_end],
        ],
        [
            ['label' => 'Back To Dashboard', 'href' => 'welcome.php'],
            ['label' => 'Refresh Page', 'href' => 'sadv_list.php'],
        ],
        'Workspace Status',
        'The salary advance workspace is currently unavailable, but the rest of the module remains accessible.'
    );
}

function getEmployeesByDepartment($conn, $department, $departments_list) {
    $sql = ($department === 'all')
        ? "SELECT employee_code, first_name, department, job FROM employees WHERE department IN ($departments_list) ORDER BY department, employee_code"
        : "SELECT employee_code, first_name, department, job FROM employees WHERE department = ? ORDER BY employee_code";
    $stmt = $conn->prepare($sql);
    if ($department !== 'all') {
        $stmt->bind_param("s", $department);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function renderEmployeeTable($employees, $selected_department) {
    echo "<div id='printArea' class='stack-gap'>";
    echo "<div class='summary-card'><strong>Department</strong><p>" . htmlspecialchars($selected_department) . " - Month: " . date('F') . "</p></div>";
    echo "<div class='table-scroll'><table border='1'>";
    echo "<thead><tr><th>Code</th><th>Name</th><th>Department</th><th>Job Description</th><th>Salary ADV</th><th>Employee Signature</th></tr></thead><tbody>";

    foreach ($employees as $row) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['employee_code']) . "</td>";
        echo "<td>" . htmlspecialchars($row['first_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['department']) . "</td>";
        echo "<td>" . htmlspecialchars($row['job']) . "</td>";
        echo "<td></td><td></td>";
        echo "</tr>";
    }

    echo "</tbody></table></div>";
    echo "<div class='summary-card'><strong>Manager Approval</strong><p>....................................................</p></div>";
    echo "</div>";
}

$selected_department = $_GET['department'] ?? '';
$employees = [];
$show_table = false;
if ($selected_department !== '' && (in_array($selected_department, $allowed_departments, true) || $selected_department === 'all')) {
    $employees = getEmployeesByDepartment($conn, $selected_department, $departments_list);
    $show_table = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Salary Advance List</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="icon" type="image/png" href="images/logo.png">
    <script>
        function showEmployees() {
            var department = document.getElementById('department').value;
            window.location.href = 'sadv_list.php?department=' + encodeURIComponent(department);
        }

        function printTable() {
            var printContents = document.getElementById('printArea').innerHTML;
            var printWindow = window.open('', '_blank');
            printWindow.document.open();
            printWindow.document.write('<html><head><title>Print</title><style>body{font-size:9pt;font-family:Arial,sans-serif;}table{width:100%;border-collapse:collapse;}th,td{padding-top:5px;padding-bottom:5px;font-size:8pt;border:1px solid black;white-space:nowrap;}th{background:#8f1a1e;color:#fff;}</style></head><body>');
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
app_render_page_header('SA', 'Salary Advance List', 'Department-based salary advance sheets with a cleaner print workflow.', [
    ['label' => 'Home', 'href' => 'welcome.php'],
    ['label' => 'Salary Advance', 'href' => 'sadv_list.php'],
    ['label' => 'Logout', 'href' => 'logout.php'],
]);
app_render_page_hero('Second-level page', 'The salary advance list now uses the same inner shell as the rest of the revamp.', 'Filter by department, view the printable sheet, and print directly from a cleaner responsive page layout.', [
    ['title' => 'Window Start', 'text' => $sadv_start],
    ['title' => 'Window End', 'text' => $sadv_end],
]);
app_open_content_panel('Generate Salary Advance Sheet', 'Choose one department or all accessible departments to prepare the printable list.');
?>
<form id="employeeForm" method="GET" action="sadv_list.php">
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
    <div class="panel-actions"><button type="button" onclick="printTable()">Print</button></div>
    <?php renderEmployeeTable($employees, $selected_department); ?>
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


