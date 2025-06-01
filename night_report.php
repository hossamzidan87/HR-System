<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';

$username = $_SESSION['username'];

// Fetch user permissions
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

// Fetch allowed departments
$allowed_departments = [];
if (!empty($user_groups)) {
    $groups_in = "'" . implode("','", $user_groups) . "'";
    $departments_sql = "SELECT DISTINCT department FROM department_groups WHERE group_name IN ($groups_in) OR group_name1 IN ($groups_in) OR group_name2 IN ($groups_in)";
    $departments_result = $conn->query($departments_sql);
    while ($row = $departments_result->fetch_assoc()) {
        $allowed_departments[] = $row['department'];
    }
}

// Generate week options starting from 10/05/2025
$start_date = strtotime('2025-05-10'); // Start from the given date
$week_options = [];
for ($i = 1; $i <= 4; $i++) {
    $end_date = strtotime("+6 days", $start_date);
    $week_options[] = [
        'label' => "Week $i: " . date('Y-m-d', $start_date) . " to " . date('Y-m-d', $end_date),
        'start_date' => date('Y-m-d', $start_date),
        'end_date' => date('Y-m-d', $end_date),
    ];
    $start_date = strtotime('+1 week', $start_date);
}

// Handle form submission
$selected_week = '';
$selected_department = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_week = $_POST['week'] ?? '';
    $selected_department = $_POST['department'] ?? '';

    // Calculate start and end dates of the selected week
    $start_of_week = date('Y-m-d', strtotime('last Saturday', strtotime($selected_week)));
    if (date('w', strtotime($selected_week)) == 6) {
        $start_of_week = date('Y-m-d', strtotime($selected_week));
    }
    $end_of_week = date('Y-m-d', strtotime('+6 days', strtotime($start_of_week)));

    // Fetch submitted employees
    $submitted_employees_sql = ($selected_department === 'all')
        ? "SELECT employee_code, employee_name, bus_line_name, department, job, start_date, end_date 
           FROM night_shift 
           WHERE department IN ('" . implode("','", $allowed_departments) . "') 
           AND start_date = '$start_of_week' AND end_date = '$end_of_week' order by department asc"
        : "SELECT employee_code, employee_name, bus_line_name, department, job, start_date, end_date 
           FROM night_shift 
           WHERE department = '$selected_department' 
           AND start_date = '$start_of_week' AND end_date = '$end_of_week'";
    $submitted_employees_result = $conn->query($submitted_employees_sql);

    // Process data for tables
    $department_table = [];
    $bus_line_table = [];
    while ($row = $submitted_employees_result->fetch_assoc()) {
        // Department Table
        if (!isset($department_table[$row['department']])) {
            $department_table[$row['department']] = 0;
        }
        $department_table[$row['department']]++;

        // Bus Line Table
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
    <style>
        .container { width: 80%; margin: 0 auto; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #4CAF50; color: white; }
        tr:hover { background-color: #f5f5f5; }
        .form-group select, .form-group button {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
        }
        .button { padding: 10px 20px; background-color: #007BFF; color: white; border: none; border-radius: 5px; cursor: pointer; }
        .button:hover { background-color: #0056b3; }
        .image-container { display: flex; justify-content: flex-end; align-items: center; }
        .image-link { border: 1px solid #ddd; border-radius: 4px; padding: 5px; width: 25px; margin: 0 5px; }
        .image-link img { width: 100%; height: auto; display: block; }
    </style>
</head>
<body>
<div class="image-container">
    <div class="image-link">
        <a href="welcome.php"><img src="/images/icons/home.png" alt="home"></a>
    </div>
    <div class="image-link">
        <a href="shifts_home.php"><img src="/images/icons/night.png" alt="home"></a>
    </div>
    <div class="image-link">
        <a href="logout.php"><img src="/images/icons/logout.png" alt="logout"></a>
    </div>
</div>
    <div class="container">
        <h1>Night Shift Weekly Report</h1>
        <form method="POST" action="night_report.php">
            <div class="form-group">
                <label for="department">Choose Department:</label>
                <select id="department" name="department" required>
                    <option value="all" <?php echo ($selected_department === 'all') ? 'selected' : ''; ?>>All Departments</option>
                    <?php foreach ($allowed_departments as $department): ?>
                        <option value="<?php echo htmlspecialchars($department); ?>" <?php echo ($selected_department === $department) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($department); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
                    </br>
            <div class="form-group">
                <label for="week">Choose Week:</label>
                <select id="week" name="week" required>
                    <option value="">Select a week range</option>
                    <?php foreach ($week_options as $option): ?>
                        <option value="<?php echo $option['start_date']; ?>" <?php echo ($selected_week === $option['start_date']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($option['label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
                    </br>
            <button type="submit" class="button">Generate Report</button>
        </form>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <div id="printAreaTables" style="display: flex; flex-direction: column; gap: 20px; align-items: center;">
                <div style="margin-top: 20px; text-align: center;">
                    <h2>Total Submitted Employees: <?php echo array_sum($department_table); ?> | Week Date: <?php echo htmlspecialchars($start_of_week); ?> and <?php echo htmlspecialchars($end_of_week); ?></h2>
                </div>
                <div style="display: flex; justify-content: space-between; gap: 20px; width: 100%;">
                    <div style="flex: 1;">
                        <h2>Department Information</h2>
                        <table>
                            <thead>
                                <tr>
                                    <th>Department</th>
                                    <th>Number of Employees</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($department_table as $department => $count): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($department); ?></td>
                                        <td><?php echo htmlspecialchars($count); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div style="flex: 1;">
                        <h2>Bus Line Information</h2>
                        <table>
                            <thead>
                                <tr>
                                    <th>Bus Line</th>
                                    <th>Number of Employees</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bus_line_table as $bus_line => $count): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($bus_line); ?></td>
                                        <td><?php echo htmlspecialchars($count); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <button class="button" onclick="printArea('printAreaTables')">Print Department and Bus Line Information</button>

            <div id="printAreaSubmittedList">
                <h2>Submitted Employee List</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Employee Code</th>
                            <th>Employee Name</th>
                            <th>Department</th>
                            <th>Job</th>
                            <th>Bus Line</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $submitted_employees_result->data_seek(0); // Reset result pointer
                        while ($row = $submitted_employees_result->fetch_assoc()): ?>
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
            <button class="button" onclick="printArea('printAreaSubmittedList')">Print Submitted Employee List</button>
        <?php endif; ?>
    </div>

    <script>
        function printArea(areaId) {
            var printContents = document.getElementById(areaId).innerHTML;
            var printWindow = window.open('', '_blank');
            printWindow.document.open();
            printWindow.document.write('<html><head><title>Print</title><style>');
            printWindow.document.write('body { font-family: Arial, sans-serif; font-size: 8px; }');
            printWindow.document.write('table { width: 100%; border-collapse: collapse; margin: 4px 0; }');
            printWindow.document.write('th, td { padding: 4px; text-align: left; border: 1px solid #ddd; }');
            printWindow.document.write('th { background-color: #4CAF50; color: white; }');
            printWindow.document.write('</style></head><body>');
            printWindow.document.write(printContents);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
            printWindow.close();
        }
    </script>
</body>
</html>
