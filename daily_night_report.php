<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';
include 'includes/app_helpers.php';

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
if (!empty($user_groups)) {
    $groups_in = "'" . implode("','", $user_groups) . "'";
    $departments_sql = "SELECT DISTINCT department FROM department_groups WHERE group_name IN ($groups_in) OR group_name1 IN ($groups_in) OR group_name2 IN ($groups_in)";
    $departments_result = $conn->query($departments_sql);

    if ($departments_result->num_rows > 0) {
        while ($row = $departments_result->fetch_assoc()) {
            $allowed_departments[] = $row['department'];
        }
    }
}

$today = date('Y-m-d');
$selected_date = $today;
$selected_department = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['date']) && isset($_POST['department'])) {
    $selected_date = $_POST['date'];
    $selected_department = $_POST['department'];
    $week_start = date('Y-m-d', strtotime('last Saturday', strtotime($selected_date)));
    if (date('w', strtotime($selected_date)) == 6) { // If selected date is Saturday
        $week_start = date('Y-m-d', strtotime($selected_date));
    }

    // If "All Departments" is selected, fetch all departments the user has access to
    if ($selected_department == 'all') {
        $departments_list = "'" . implode("','", array_unique($allowed_departments)) . "'";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Overtime Report</title>
    <style>
        .container {
            width: 80%;
            margin: 0 auto;
            text-align: center;
        }
        .form-group select, .form-group input {
            padding: 8px;
            border: 1px solid #ccc;
            font-weight: bold;
            border-radius: 4px;
            font-size: 12px;
        }
        .button {
            margin: 10px;
            padding: 10px 20px;
            font-size: 15px;
            cursor: pointer;
        }
                table {
            width: 85%;
            margin: 0 auto;
            border-collapse: collapse;
        }
                thead {
            background-color: #4CAF50;
            color: white;
            text-align: left;
            padding: 8px;
            border-bottom: 2px solid #000000;
            text-align: center;
        }
                td {
            padding: 1px;
            text-align: center;
            border-bottom: 2px solid #ddd;
        }
        td:hover {background-color: #f5f5f5;}
        .side-by-side {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            gap: 20px;
            margin: 20px 0;
        }
        .side-by-side > div {
            flex: 1;
        }
        @media print {
            button {
                display: none;
            }

            /* Hide elements you don't want to print */
            button, form {
                display: none;
            }
            .header, .footer {
                display: none; /* Hide header and footer if needed */
            }

        }

    </style>
    <script>
        function printSection(sectionId) {
            var printContents = document.getElementById(sectionId).outerHTML;
            var printWindow = window.open('', '_blank');
            printWindow.document.open();
            printWindow.document.write('<html><head><title>Print</title>');
            // Add styles for printing
            printWindow.document.write('<style>body{font-size:9pt} table{width:100%;margin:20px 0;border-collapse:collapse} thead{background-color:#4CAF50;color:white} th,td{padding:8px;text-align:center;border:1px solid #ddd} h2{text-align:center} .side-by-side{display:flex;justify-content:center;align-items:flex-start;gap:20px;} .side-by-side > div{flex:1;} </style>');
            printWindow.document.write('</head><body>');
            printWindow.document.write(printContents);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.focus();
            setTimeout(function () {
                printWindow.print();
                printWindow.close();
            }, 500);
        }

        function printTable() {
            printSection('printArea');
        }

        function printSummary() {
            printSection('printSummaryArea');
        }
    </script>
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="icon" type="image/png" href="images/logo.png">
</head>
<body>
<?php
app_render_page_header('DNR', 'Daily Night Overtime Report', 'View daily night overtime data and generate reports.', [
    ['label' => 'Home', 'href' => 'welcome.php'],
    ['label' => 'Overtime Report', 'href' => 'overtime_report.php'],
    ['label' => 'Logout', 'href' => 'logout.php'],
]);
app_render_page_hero('Report', 'Generate daily night overtime reports.', 'Select a date and department to view the report.', []);
app_open_content_panel('Daily Night Overtime', 'Configure report options below.');
?>
        <form method="POST" id="dateDepartmentForm">
        <div class="form-group">
            <label for="date">Choose Date:</label>
            <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($selected_date); ?>" required>
            <label for="department">Choose Department:</label>
            <select id="department" name="department" required>
                <option value="">Select a department</option>
                <option value="all" <?php echo ($selected_department == 'all') ? 'selected' : ''; ?>>All Departments</option>
                <?php
                foreach (array_unique($allowed_departments) as $department) {
                    $selected = ($selected_department == $department) ? "selected" : "";
                    echo "<option value='$department' $selected>$department</option>";
                }
                ?>
            </select>
            </div>
            <button type="submit" class="button">Generate Report</button>
        </form>
        <div id="printArea">
            <?php
            if ($_SERVER['REQUEST_METHOD'] == 'POST' && $selected_date && $selected_department) {
                $where_clause = ($selected_department == 'all') ? "ot.department IN ($departments_list)" : "ot.department = '$selected_department'";

                // Combined query for exceeded list, department table, and bus line table
                $combined_sql = "
                    SELECT 
                        ot.employee_code AS Code, 
                        ot.employee_name AS Name, 
                        ot.department AS Department, 
                        ot.job AS Job, 
                        ot.bus_line_name AS Bus, 
                        (12 - IFNULL(aggregated_overtime.total_hours, 0)) AS 'Remaining H',
                        COUNT(DISTINCT ot.employee_code) AS 'Number of Employees'
                    FROM overtime ot
                    INNER JOIN (
                        SELECT 
                            employee_code,
                            SUM(CASE 
                                WHEN DAYOFWEEK(overtime_date) IN (6, 7) AND overtime_date NOT IN (SELECT days FROM calendar WHERE type = 'saturday') THEN 8 
                                WHEN overtime_date IN (SELECT days FROM calendar WHERE type = 'holiday') THEN 0
                                ELSE 1.5 
                            END) AS total_hours
                        FROM overtime
                        WHERE overtime_date BETWEEN '$week_start' AND '$selected_date'
                        AND types = 'night'
                        GROUP BY employee_code
                    ) AS aggregated_overtime ON ot.employee_code = aggregated_overtime.employee_code
                    WHERE $where_clause
                    AND ot.types = 'night'
                    AND ot.overtime_date = '$selected_date'
                    GROUP BY ot.department,ot.employee_code, ot.bus_line_name";

                $combined_result = $conn->query($combined_sql);
                if ($combined_result->num_rows > 0) {
                    $exceeded_list = [];
                    $department_table = [];
                    $bus_line_table = [];
                    $all_employees = [];
                    $total_employees = 0;

                    while ($row = $combined_result->fetch_assoc()) {
                        // Exceeded List
                        if ($row['Remaining H'] < 0) {
                            $exceeded_list[] = $row;
                        }

                        // Department Table
                        if (!isset($department_table[$row['Department']])) {
                            $department_table[$row['Department']] = 0;
                        }
                        $department_table[$row['Department']]++;

                        // Bus Line Table
                        if (!isset($bus_line_table[$row['Bus']])) {
                            $bus_line_table[$row['Bus']] = 0;
                        }
                        $bus_line_table[$row['Bus']]++;

                        // All Employees
                        $all_employees[] = $row;

                        // Total Employees
                        $total_employees++;
                    }

                    // Display Exceeded List
                    if (!empty($exceeded_list)) {
                        echo "<h2>Exceeded List</h2>";
                        echo "<table>
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Name</th>
                                        <th>Department</th>
                                        <th>Job Description</th>
                                        <th>Remaining H</th>
                                    </tr>
                                </thead>
                                <tbody>";
                        foreach ($exceeded_list as $row) {
                            echo "<tr>
                                    <td>{$row['Code']}</td>
                                    <td>{$row['Name']}</td>
                                    <td>{$row['Department']}</td>
                                    <td>{$row['Job']}</td>
                                    <td>{$row['Remaining H']}</td>
                                  </tr>";
                        }
                        echo "</tbody></table>";
                    } else {
                        echo "<h2>Empty Exceeded List</h2>";
                    }

                    // Container for side-by-side tables
                    echo '<div class="side-by-side" id="printSummaryArea">';

                    // Display Department Table
                    if (!empty($department_table)) {
                        echo '<div>';
                        echo "<h2>Department Information</h2>";
                        echo "<table>
                                <thead>
                                    <tr>
                                        <th>Department</th>
                                        <th>Number of Employees</th>
                                    </tr>
                                </thead>
                                <tbody>";
                        foreach ($department_table as $department => $count) {
                            echo "<tr>
                                    <td>$department</td>
                                    <td>$count</td>
                                  </tr>";
                        }
                        echo "</tbody></table></div>";
                    }

                    // Display Bus Line Table
                    if (!empty($bus_line_table)) {
                        echo '<div>';
                        echo "<h2>Bus Line Information</h2>";
                        echo "<table>
                                <thead>
                                    <tr>
                                        <th>Bus Line</th>
                                        <th>Number of Employees</th>
                                    </tr>
                                </thead>
                                <tbody>";
                        foreach ($bus_line_table as $bus_line => $count) {
                            echo "<tr>
                                    <td>$bus_line</td>
                                    <td>$count</td>
                                  </tr>";
                        }
                        echo "</tbody></table></div>";
                    }
                    echo '</div>';

                    // Display Total Recorded Employees
                    echo "<h2>Total Recorded Employees: $total_employees | Date: $selected_date</h2>";
                    // Display All Employees
                    if (!empty($all_employees)) {
                        echo "<h2>All Recorded Employees</h2>";
                        echo "<table>
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Name</th>
                                        <th>Department</th>
                                        <th>Job</th>
                                        <th>Bus Line</th>
                                        <th>Remaining H</th>
                                    </tr>
                                </thead>
                                <tbody>";
                        foreach ($all_employees as $row) {
                            echo "<tr>
                                    <td>{$row['Code']}</td>
                                    <td>{$row['Name']}</td>
                                    <td>{$row['Department']}</td>
                                    <td>{$row['Job']}</td>
                                    <td>{$row['Bus']}</td>
                                    <td>{$row['Remaining H']}</td>
                                  </tr>";
                        }
                        echo "</tbody></table>";
                    }
                } else {
                    echo "<h2>No records found for the selected date and department.</h2>";
                }
            } ?>
        </div>
        <?php
            // Show print button only if there is a report generated
            if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($combined_result) && $combined_result->num_rows > 0) {
                echo "<button class='button' onclick='printSummary()'>Print Summary</button>\n";
                echo "<button class='button' onclick='printTable()'>Print Full Report</button>";
            }
        ?>

<?php
app_close_content_panel();
app_render_page_end();
?>
<script src="assets/js/app.js"></script>
</body>
</html>
