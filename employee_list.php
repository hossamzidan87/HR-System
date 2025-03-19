<?php
// Include necessary files
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';

// Start session and retrieve username

$username = $_SESSION['username'];

// Fetch user permissions
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
  ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Employee List</title>
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
            form.action = "employee_list.php?department=" + department;
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
        <a href="overtime_home.php"><img src="/images/icons/otre.png" alt="Overtime"></a>
        </div>
        <div class="image-link">
            <a href="logout.php"><img src="/images/icons/logout.png" alt="logout"></a>
        </div>
    </div>
    <h2>Employee List</h2>
    <form id="employeeForm" method="POST" action="employee_list.php">
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
        "SELECT employee_code, first_name, gender, department, job FROM employees WHERE department IN ($departments_list)" :
        "SELECT employee_code, first_name, gender, department, job FROM employees WHERE department = ?";
    $stmt = $conn->prepare($sql);
    if ($department !== 'all') $stmt->bind_param("s", $department);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
                            // Calculate total overtime hours for the current week
                            $selected_date = date('Y-m-d');
                            // Calculate the start of the week (last Saturday or the same Saturday if today is Saturday)
                            $week_start = date('Y-m-d', strtotime('last Saturday', strtotime($selected_date)));
                            if (date('w', strtotime($selected_date)) == 6) { // If selected date is Saturday
                             $week_start = date('Y-m-d', strtotime($selected_date));
                            }

                            // Calculate the end of the week (next Friday)
                            $week_end = date('Y-m-d', strtotime('next Friday', strtotime($selected_date)));
                            if (date('w', strtotime($selected_date)) == 5) { // If selected date is Friday
                            $week_end = date('Y-m-d', strtotime($selected_date));
                            }
// Fetch overtime data for the week
function getOvertimeData($conn, $week_start, $week_end) {
    $sql = "SELECT employee_code, SUM(CASE 
                                        WHEN DAYOFWEEK(overtime_date) IN (6, 7) AND overtime_date NOT IN (SELECT saturday FROM calendar) THEN 8  
                                        ELSE 2 
                                      END) AS total_hours 
            FROM overtime 
            WHERE overtime_date BETWEEN ? AND ? 
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
// Display employee table
function displayEmployeeTable($employees, $overtime_data, $selected_department) {
    echo "<div id='printArea'>";
    echo "<h3 id='departmentHeadline' align='center'>Selected Department: " . htmlspecialchars($selected_department) . "</h3>";
    echo "<table border='1'>";
    echo "<thead><tr>
            <th>Code</th>
            <th>Name</th>
            <th>Department</th>
            <th>Job</th>
            <th>Remaining H</th>
            <th>Transportation</th>
          </tr></thead>";
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
        echo "<td></td>";  // Bus line empty
        echo "</tr>";
    }

    echo "</tbody></table></div>";
    echo "<button onclick='printTable()'>Print</button>";
}

// Main logic
if (isset($_GET['department']) && (in_array($_GET['department'], $allowed_departments) || $_GET['department'] === 'all')) {
    $selected_department = $_GET['department'];
    $employees = getEmployeesByDepartment($conn, $selected_department, $departments_list);
    $overtime_data = getOvertimeData($conn, $week_start, $week_end);
    displayEmployeeTable($employees, $overtime_data, $selected_department);
} else {
    echo "<h3>Please select a department from the list above.</h3>";
}
?>
    </body>
</html>