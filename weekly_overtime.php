<?php
include 'check_cookies.php';
include 'db_connection.php';

if (!isset($_SESSION['username'])) {
    die("Unauthorized access. Please log in.");
}

$username = $_SESSION['username'];

// Fetch user permissions
$user_groups = [];
$stmt = $conn->prepare("SELECT group_name_1, group_name_2, group_name_3 FROM user_permissions WHERE username = ?");
if (!$stmt) {
    die("Database error: " . $conn->error);
}
$stmt->bind_param("s", $username);
$stmt->execute();
$permissions_result = $stmt->get_result();

if ($permissions_result->num_rows > 0) {
    $user_permissions = $permissions_result->fetch_assoc();
    foreach (['group_name_1', 'group_name_2', 'group_name_3'] as $group) {
        if (!empty($user_permissions[$group])) {
            $user_groups[] = $user_permissions[$group];
        }
    }
}
$stmt->close();

// Fetch allowed departments based on user groups
$allowed_departments = [];
if (!empty($user_groups)) {
    $groups_in = implode("','", $user_groups);
    $departments_sql = "SELECT DISTINCT department FROM department_groups WHERE group_name IN ('$groups_in') OR group_name1 IN ('$groups_in') OR group_name2 IN ('$groups_in')";
    $departments_result = $conn->query($departments_sql);
    if (!$departments_result) {
        die("Database error: " . $conn->error);
    }
    while ($row = $departments_result->fetch_assoc()) {
        $allowed_departments[] = $row['department'];
    }
}

// Fetch the list of Saturdays from the calendar table
$saturdays = [];
$saturday_sql = "SELECT saturday FROM calendar";
$saturday_result = $conn->query($saturday_sql);
if (!$saturday_result) {
    die("Database error: " . $conn->error);
}
while ($row = $saturday_result->fetch_assoc()) {
    $saturdays[] = $row['saturday'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate and sanitize inputs
    $selected_department = filter_input(INPUT_POST, 'department', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $selected_week = filter_input(INPUT_POST, 'week', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    if (!in_array($selected_department, $allowed_departments) && $selected_department !== 'all') {
        die("Invalid department selected.");
    }

    // Calculate the start and end of the selected week
    $start_of_week = date('Y-m-d', strtotime('this Saturday', strtotime($selected_week)));
    $end_of_week = date('Y-m-d', strtotime('next Friday', strtotime($selected_week)));
    if (date('w', strtotime($selected_week)) == 5) {
        $end_of_week = date('Y-m-d', strtotime($selected_week));
    }

    // Fetch the total number of employees from the employees table
    $department_employee_count = [];
    $total_employees_sql = "SELECT department, COUNT(DISTINCT employee_code) as total_employees FROM employees GROUP BY department";
    $total_employees_result = $conn->query($total_employees_sql);
    if (!$total_employees_result) {
        die("Database error: " . $conn->error);
    }
    while ($row = $total_employees_result->fetch_assoc()) {
        $department_employee_count[$row['department']] = $row['total_employees'];
    }

    // Fetch overtime data based on selected department and week
    $overtime_data = [];
    $exceeded_list = [];
    $department_summary = [];
    $daily_submission_count = [];

    if ($selected_department === 'all') {
        $overtime_sql = "SELECT employee_code, employee_name, department, job, overtime_date 
                         FROM overtime 
                         WHERE department IN ('" . implode("','", $allowed_departments) . "') AND overtime_date BETWEEN ? AND ?";
        $stmt = $conn->prepare($overtime_sql);
        $stmt->bind_param("ss", $start_of_week, $end_of_week);
    } else {
        $overtime_sql = "SELECT employee_code, employee_name, department, job, overtime_date 
                         FROM overtime 
                         WHERE department = ? AND overtime_date BETWEEN ? AND ?";
        $stmt = $conn->prepare($overtime_sql);
        $stmt->bind_param("sss", $selected_department, $start_of_week, $end_of_week);
    }
    $stmt->execute();
    $overtime_result = $stmt->get_result();

    // Accumulate overtime hours for each employee and count daily submissions
    $employee_overtime = [];
    if ($overtime_result->num_rows > 0) {
        while ($row = $overtime_result->fetch_assoc()) {
            $employee_code = $row['employee_code'];
            $date = date('Y-m-d', strtotime($row['overtime_date']));
            $day_of_week = date('w', strtotime($date));
            $hours = ($day_of_week == 5 || ($day_of_week == 6 && !in_array($date, $saturdays))) ? 8 : 2; // Friday (5) or Saturday (6) = 8 hours, other days = 2 hours

            if (!isset($employee_overtime[$employee_code])) {
                $employee_overtime[$employee_code] = [
                    'employee_code' => $row['employee_code'],
                    'employee_name' => $row['employee_name'],
                    'department' => $row['department'],
                    'job' => $row['job'],
                    'total_hours' => 0,
                ];
            }
            $employee_overtime[$employee_code]['total_hours'] += $hours;

            // Count daily submissions
            if (!isset($daily_submission_count[$date])) {
                $daily_submission_count[$date] = 0;
            }
            $daily_submission_count[$date]++;
        }
    }
    $stmt->close();

    // Process overtime data
    foreach ($employee_overtime as $employee) {
        $total_hours = $employee['total_hours'];
        $employee['remaining_hours'] = 12 - $total_hours;
        $overtime_data[] = $employee;

        // Add to exceeded list if total_hours > 12
        if ($total_hours > 12) {
            $exceeded_list[] = $employee;
        }
    }

    // Filter exceeded list based on permitted departments when "All Departments" is selected
    if ($selected_department === 'all') {
        $exceeded_list = array_filter($exceeded_list, function ($employee) use ($allowed_departments) {
            return in_array($employee['department'], $allowed_departments);
        });
    }

    // Sort exceeded list by total_hours in descending order
    usort($exceeded_list, function ($a, $b) {
        return $b['total_hours'] - $a['total_hours'];
    });

    // Calculate department summary
    foreach ($allowed_departments as $department) {
        if ($selected_department === 'all' || $department === $selected_department) {
            $dept_overtime = array_filter($overtime_data, function ($overtime) use ($department) {
                return $overtime['department'] === $department;
            });

            $total_employees = $department_employee_count[$department] ?? 0;
            $employees_with_overtime = count($dept_overtime);
            $no_overtime = $total_employees - $employees_with_overtime;
            if ($no_overtime < 0) {
                $no_overtime = 0;
            }
            $less_than_10 = count(array_unique(array_column(array_filter($dept_overtime, function ($overtime) {
                return $overtime['total_hours'] > 0 && $overtime['total_hours'] <= 10;
            }), 'employee_code')));
            $between_10_and_12 = count(array_unique(array_column(array_filter($dept_overtime, function ($overtime) {
                return $overtime['total_hours'] > 10 && $overtime['total_hours'] <= 12;
            }), 'employee_code')));
            $more_than_12 = count(array_unique(array_column(array_filter($dept_overtime, function ($overtime) {
                return $overtime['total_hours'] > 12;
            }), 'employee_code')));

            $department_summary[] = [
                'department' => $department,
                'total_employees' => $total_employees,
                'no_overtime' => $no_overtime,
                'less_than_10' => $less_than_10,
                'between_10_and_12' => $between_10_and_12,
                'more_than_12' => $more_than_12,
            ];
        }
    }

    // Calculate daily submission count for the selected week and department
    $daily_submission_count_selected = [];
    $daily_submission_sql = "SELECT department, overtime_date, COUNT(DISTINCT employee_code) as submission_count 
                             FROM overtime 
                             WHERE (department = ? OR ? = 'all') AND overtime_date BETWEEN ? AND ? 
                             GROUP BY department, overtime_date";
    $stmt = $conn->prepare($daily_submission_sql);
    $stmt->bind_param("ssss", $selected_department, $selected_department, $start_of_week, $end_of_week);
    $stmt->execute();
    $daily_submission_result = $stmt->get_result();
    if ($daily_submission_result->num_rows > 0) {
        while ($row = $daily_submission_result->fetch_assoc()) {
            $daily_submission_count_selected[$row['department']][$row['overtime_date']] = $row['submission_count'];
        }
    }
    $stmt->close();
}

// Define the start date (Saturday, 7th December 2024)
$start_date = strtotime('2024-12-07');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weekly Overtime Report</title>
    <style>
        .container { width: 80%; margin: 0 auto; text-align: center; }
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
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #4CAF50; color: white; }
        tr:hover { background-color: #f5f5f5; }

        .image-container { /* New container */
    display: flex;
    justify-content: flex-end; /* Align items to the right */
    align-items: center; /* Vertically center items */
}

.image-link {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 5px;
    width: 25px; /* Adjust as needed */
    margin: 0 5px; /* Space between images */
    display: inline-block; /* to prevent collapsing margins */
}

.image-link:hover {
    box-shadow: 0 0 2px 1px rgba(0, 140, 186, 0.5);
}

.image-link img {
    width: 100%; /* Make image fill container */
    height: auto; /* Maintain aspect ratio */
    display: block; /* Prevents small gap below image */
}
    </style>
</head>
<body>
        <div class="image-container">
    <div class="image-link">
        <a href="welcome.php"><img src="/images/icons/home.png" alt="home"></a>
    </div>
        <div class="image-link">
        <a href="overtime_report.php"><img src="/images/icons/report.png" alt="home"></a>
    </div>
    <div class="image-link">
        <a href="logout.php"><img src="/images/icons/logout.png" alt="logout"></a>
    </div>
    </div>

    <div class="container">
        <h1>Weekly Overtime Report</h1>
        <form method="POST" action="weekly_overtime.php">

            <div class="form-group">
                <label for="department">Choose Department:</label>
                <select id="department" name="department" required>
                    <option value="all">All Departments</option>
                    <?php foreach ($allowed_departments as $department): ?>
                        <option value="<?php echo htmlspecialchars($department); ?>"><?php echo htmlspecialchars($department); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="week">Choose Week:</label>
                <select id="week" name="week" required>
<?php
// Generate dropdown options starting from Saturday, 7th December 2024
$start_date = strtotime('2024-12-07');
$current_date = strtotime('now');

// Calculate the number of weeks between the start date and the current date
$weeks_count = ceil(($current_date - $start_date) / (7 * 24 * 60 * 60));

for ($i = 0; $i < $weeks_count; $i++) {
    $week_start = strtotime("+$i week", $start_date);
    $week_end = strtotime("+6 days", $week_start);
    $week_label = date('Y-m-d', $week_start) . " to " . date('Y-m-d', $week_end);
    echo "<option value='" . date('Y-m-d', $week_start) . "' selected>$week_label</option>";
}
?>

                </select>
            </div>
            <button type="submit" class="button">Submit</button>
        </form>

        <?php if ($_SERVER['REQUEST_METHOD'] == 'POST'): ?>
            <h2>Exceeded List</h2>
            <table border="1">
                <thead>
                    <tr>
                        <th>Employee Code</th>
                        <th>Employee Name</th>
                        <th>Department</th>
                        <th>Job</th>
                        <th>Total Overtime Hours</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($exceeded_list as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['employee_code']); ?></td>
                            <td><?php echo htmlspecialchars($row['employee_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['department']); ?></td>
                            <td><?php echo htmlspecialchars($row['job']); ?></td>
                            <td><?php echo htmlspecialchars($row['total_hours']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h2>Department Summary</h2>
            <table border="1">
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>Total Employees</th>
                        <th>No Overtime</th>
                        <th>0-10 Hours</th>
                        <th>10-12 Hours</th>
                        <th>More than 12 Hours</th>
                        <th>Total Employees OT</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_employees_sum = 0;
                    $no_overtime_sum = 0;
                    $less_than_10_sum = 0;
                    $between_10_and_12_sum = 0;
                    $more_than_12_sum = 0;
                    $total_employees_ot_sum = 0;

                    foreach ($department_summary as $summary): 
                        $total_employees_sum += $summary['total_employees'];
                        $no_overtime_sum += $summary['no_overtime'];
                        $less_than_10_sum += $summary['less_than_10'];
                        $between_10_and_12_sum += $summary['between_10_and_12'];
                        $more_than_12_sum += $summary['more_than_12'];
                        $total_employees_ot = $summary['less_than_10'] + $summary['between_10_and_12'] + $summary['more_than_12'];
                        $total_employees_ot_sum += $total_employees_ot;
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($summary['department']); ?></td>
                            <td><?php echo htmlspecialchars($summary['total_employees']); ?></td>
                            <td><?php echo htmlspecialchars($summary['no_overtime']); ?></td>
                            <td><?php echo htmlspecialchars($summary['less_than_10']); ?></td>
                            <td><?php echo htmlspecialchars($summary['between_10_and_12']); ?></td>
                            <td><?php echo htmlspecialchars($summary['more_than_12']); ?></td>
                            <td><?php echo htmlspecialchars($total_employees_ot); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th>Total</th>
                        <th><?php echo htmlspecialchars($total_employees_sum); ?></th>
                        <th><?php echo htmlspecialchars($no_overtime_sum); ?></th>
                        <th><?php echo htmlspecialchars($less_than_10_sum); ?></th>
                        <th><?php echo htmlspecialchars($between_10_and_12_sum); ?></th>
                        <th><?php echo htmlspecialchars($more_than_12_sum); ?></th>
                        <th><?php echo htmlspecialchars($total_employees_ot_sum); ?></th>
                    </tr>
                </tfoot>
            </table>
            <h2>Daily Total Submission Overtime hours for Selected Week</h2>
            <table border="1">
                <thead>
                    <tr>
                        <?php
                        // Print days of the week as table headers
                        $days_of_week = ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
                        foreach ($days_of_week as $day) {
                            echo "<th>$day</th>";
                        }
                        ?>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <?php
                        // Calculate total submission counts for each day of the week for permitted departments
                        $weekly_total_hours = 0;
                        foreach ($days_of_week as $day) {
                            $date = date('Y-m-d', strtotime("$day", strtotime($start_of_week)));
                            $total_hours = 0;
                            foreach ($daily_submission_count_selected as $department => $dates) {
                                if (in_array($department, $allowed_departments) && isset($dates[$date])) {
                                    $day_of_week = date('w', strtotime($date));
                                    $hours_per_employee = ($day_of_week == 5 || ($day_of_week == 6 && !in_array($date, $saturdays))) ? 8 : 2; // Friday (5) or Saturday (6) = 8 hours, other days = 2 hours
                                    $total_hours += $dates[$date] * $hours_per_employee;
                                }
                            }
                            $weekly_total_hours += $total_hours;
                            echo "<td>" . htmlspecialchars($total_hours) . "</td>";
                        }
                        ?>
                        <td><?php echo htmlspecialchars($weekly_total_hours); ?></td>
                    </tr>
                </tbody>
            </table>

            <h2>Daily Submission Count for Selected Week and Department</h2>
            <table border="1">
                <thead>
                    <tr>
                        <th>Department</th>
                        <?php
                        // Print days of the week as table headers
                        $days_of_week = ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
                        foreach ($days_of_week as $day) {
                            echo "<th>$day</th>";
                        }
                        ?>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daily_submission_count_selected as $department => $dates): ?>
                        <?php if (in_array($department, $allowed_departments)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($department); ?></td>
                                <?php
                                $total_count = 0;
                                // Print submission counts for each day of the week
                                foreach ($days_of_week as $day) {
                                    $date = date('Y-m-d', strtotime("$day", strtotime($start_of_week)));
                                    $count = isset($dates[$date]) ? $dates[$date] : 0;
                                    $total_count += $count;
                                    echo "<td>" . htmlspecialchars($count) . "</td>";
                                }
                                ?>
                                <td><?php echo htmlspecialchars($total_count); ?></td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th>Total</th>
                        <?php
                        // Calculate total counts for each day across all permitted departments
                        $grand_total = 0;
                        foreach ($days_of_week as $day) {
                            $date = date('Y-m-d', strtotime("$day", strtotime($start_of_week)));
                            $day_total = 0;
                            foreach ($daily_submission_count_selected as $department => $dates) {
                                if (in_array($department, $allowed_departments) && isset($dates[$date])) {
                                    $day_total += $dates[$date];
                                }
                            }
                            $grand_total += $day_total;
                            echo "<th>" . htmlspecialchars($day_total) . "</th>";
                        }
                        ?>
                        <th><?php echo htmlspecialchars($grand_total); ?></th>
                    </tr>
                </tfoot>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>