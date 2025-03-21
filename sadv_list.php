<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';

// Fetch user permissions
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

// Fetch departments based on user groups
$allowed_departments = [];
$departments_array = [];
$departments_sql = "";

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
    $departments_list = "'" . implode("','", $departments_array) . "'";
}

// Fetch opening and closing times
$rule_sql = "SELECT sadv_start, sadv_end FROM rules WHERE name = 'close_time'";
$rule_result = $conn->query($rule_sql);
$rule = $rule_result->fetch_assoc();
$sadv_start = $rule['sadv_start'];
$sadv_end = $rule['sadv_end'];
$current_time = date('Y-m-d H:i:s');

if ($current_time < $sadv_start || $current_time > $sadv_end) {
    echo "<h3>This page is currently closed and usually opens on the 17th of every month.</h3>";
    echo "<a href='welcome.php'><img width='50' height='50' src='/images/icons/home.png' alt='home'></a>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Salary Advance List</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        .exceeded { background-color: #ffcccc; }
        table { width: 80%; margin: 0 auto; border-collapse: collapse; }
        th { background-color: #4CAF50; color: white; text-align: left; padding: 5px; }
        td { padding: 5px; text-align: left; border-bottom: 1px solid #ddd; }
        tr:hover { background-color: #f5f5f5; }
        .header, .footer { background-color: #f2f2f2; text-align: center; padding: 10px; }
        button { background-color: #04AA6D; border: none; color: white; padding: 15px 32px; text-align: center; text-decoration: none; display: inline-block; font-size: 16px; margin: 4px 2px; cursor: pointer; transition-duration: 0.4s; }
        button:hover { box-shadow: 0 12px 16px 0 rgba(0,0,0,0.24),0 17px 50px 0 rgba(0,0,0,0.19); }
        select { background-color: #04AA6D; height: 30px; border-color: #fff; color: white; }
        .image-container { display: flex; justify-content: flex-end; align-items: center; }
        .image-link { border: 1px solid #ddd; border-radius: 4px; padding: 5px; width: 25px; margin: 0 5px; }
        .image-link:hover { box-shadow: 0 0 2px 1px rgba(0, 140, 186, 0.5); }
        .image-link img { width: 100%; height: auto; display: block; }
        @media print { button, .header, .footer { display: none; } }
    </style>
    <script>
        function showEmployees() {
            var department = document.getElementById("department").value;
            var form = document.getElementById("employeeForm");
            form.action = "sadv_list.php?department=" + department;
            form.submit();
        }

        function printTable() {
            var printContents = document.getElementById('printArea');
            var printWindow = window.open('', '_blank');
            printWindow.document.open();
            printWindow.document.write('<html><head><title>Print</title><style>');
            printWindow.document.write('body { font-size: 9pt; }');
            printWindow.document.write('table { width: 100%; border-collapse: collapse; }');
            printWindow.document.write('th, td { padding-top: 5px; padding-bottom: 5px; font-size: 8pt; border: 1px solid black; white-space: nowrap; }');
            printWindow.document.write('</style></head><body>');
            printWindow.document.write(printContents.innerHTML);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
            printWindow.close();
        }
    </script>
</head>
<body>
    <div class="image-container">
        <div class="image-link">
            <a href="welcome.php"><img src="/images/icons/home.png" alt="home"></a>
        </div>
        <div class="image-link">
            <a href="logout.php"><img src="/images/icons/logout.png" alt="logout"></a>
        </div>
    </div>
    <h2>Salary Advance List</h2>
    <form id="employeeForm" method="POST" action="sadv_list.php">
        <label for="department">Choose Department:</label>
        <select id="department" name="department" onchange="showEmployees()">
            <option value="">Select a department</option>
            <option value="all">All Departments</option>
            <?php
            foreach ($allowed_departments as $department) {
                echo "<option value='" . htmlspecialchars($department) . "'>" . htmlspecialchars($department) . "</option>";
            }
            ?>
        </select>
    </form>

<?php
// Fetch employees based on selected department
function getEmployeesByDepartment($conn, $department, $departments_list) {
    $sql = ($department === 'all') ? 
        "SELECT employee_code, first_name, department, job FROM employees WHERE department IN ($departments_list)" :
        "SELECT employee_code, first_name, department, job FROM employees WHERE department = ?";
    $stmt = $conn->prepare($sql);
    if ($department !== 'all') $stmt->bind_param("s", $department);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Display employee table
function displayEmployeeTable($employees, $selected_department) {
    echo "<div id='printArea'>";
    echo "<h3 id='departmentHeadline' align='center'>Selected Department: " . htmlspecialchars($selected_department) . " - Month : " . date('F') . "</h3>";
    echo "<table border='1'>";
    echo "<thead><tr>
            <th>Code</th>
            <th>Name</th>
            <th>Department</th>
            <th>Job Description</th>
            <th>Salary ADV</th>
            <th>Employee Signature</th>
          </tr></thead>";
    echo "<tbody>";

    foreach ($employees as $row) {
        $employee_code = $row['employee_code'];
        $employee_name = $row['first_name'];
        $department = $row['department'];
        $job = $row['job'];

        echo "<tr>";
        echo "<td>" . htmlspecialchars($employee_code) . "</td>";
        echo "<td>" . htmlspecialchars($employee_name) . "</td>";
        echo "<td>" . htmlspecialchars($department) . "</td>";
        echo "<td>" . htmlspecialchars($job) . "</td>";
        echo "<td></td>";  // Sadv empty
        echo "<td></td>";  // Signature empty
        echo "</tr>";
    }

    echo "</tbody></table>";
    echo "<h3>Manager Approval</h3>";
    echo "<p>....................................................</p>";
    echo "</div>";
    echo "<button onclick='printTable()'>Print</button>";
}

// Main logic
if (isset($_GET['department']) && (in_array($_GET['department'], $allowed_departments) || $_GET['department'] === 'all')) {
    $selected_department = $_GET['department'];
    $employees = getEmployeesByDepartment($conn, $selected_department, $departments_list);
    displayEmployeeTable($employees, $selected_department);
} else {
    echo "<h3>Please select a department from the list above.</h3>";
}
?>
    </body>
</html>
