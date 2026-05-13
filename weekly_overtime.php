<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';
include 'includes/app_helpers.php';

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

// Fetch the list of Saturdays and holidays from the calendar table
$saturdays = [];
$holidays = [];
$calendar_sql = "SELECT days, type FROM calendar";
$calendar_result = $conn->query($calendar_sql);
if (!$calendar_result) {
    die("Database error: " . $conn->error);
}
while ($row = $calendar_result->fetch_assoc()) {
    if ($row['type'] === 'saturday') {
        $saturdays[] = $row['days'];
    } elseif ($row['type'] === 'holiday') {
        $holidays[] = $row['days'];
    }
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
                         WHERE types = 'normal'
                         AND department IN ('" . implode("','", $allowed_departments) . "') AND overtime_date BETWEEN ? AND ?";
        $stmt = $conn->prepare($overtime_sql);
        $stmt->bind_param("ss", $start_of_week, $end_of_week);
    } else {
        $overtime_sql = "SELECT employee_code, employee_name, department, job, overtime_date 
                         FROM overtime 
                         WHERE types = 'normal'
                         AND department = ? AND overtime_date BETWEEN ? AND ?";
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
            $hours = ($day_of_week == 5 || ($day_of_week == 6 && !in_array($date, $saturdays))) ? 8 : 2;
            if (in_array($date, $holidays)) {
                $hours = 0; // Exclude holidays
            }

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
                             WHERE types = 'normal' 
                             AND (department = ? OR ? = 'all') AND overtime_date BETWEEN ? AND ? 
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

$chart_labels = [];
$chart_no_overtime = [];
$chart_0_10 = [];
$chart_10_12 = [];
$chart_more_12 = [];
$chart_ot_workers = [];
$chart_total_employees = [];
$chart_day_labels = ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
$chart_day_submissions = [];
$chart_day_remaining = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    foreach ($department_summary as $summary) {
        $chart_labels[] = $summary['department'];
        $chart_no_overtime[] = (int) $summary['no_overtime'];
        $chart_0_10[] = (int) $summary['less_than_10'];
        $chart_10_12[] = (int) $summary['between_10_and_12'];
        $chart_more_12[] = (int) $summary['more_than_12'];
        $chart_ot_workers[] = (int) ($summary['less_than_10'] + $summary['between_10_and_12'] + $summary['more_than_12']);
        $chart_total_employees[] = (int) $summary['total_employees'];
    }

    $chart_total_factory = array_sum($chart_total_employees);

    foreach ($chart_day_labels as $day) {
        $date = date('Y-m-d', strtotime($day, strtotime($start_of_week)));
        $submitted = 0;

        foreach ($daily_submission_count_selected as $department => $dates) {
            if (in_array($department, $allowed_departments, true) && isset($dates[$date])) {
                $submitted += (int) $dates[$date];
            }
        }

        $chart_day_submissions[] = $submitted;
        $chart_day_remaining[] = max(0, $chart_total_factory - $submitted);
    }
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
        .chart-board {
            margin-top: 20px;
            padding: 16px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #fff;
        }
        .chart-row {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }
        .chart-row.split-60-40 {
            grid-template-columns: 3fr 2fr;
        }
        .chart-card {
            border: 1px solid #e3e3e3;
            border-radius: 8px;
            padding: 12px;
            background: #fafafa;
        }
        .chart-card.full-width {
            grid-column: 1 / -1;
        }
        .chart-card h3 {
            margin: 0 0 12px;
            font-size: 16px;
        }
        .chart-card canvas {
            width: 100% !important;
            height: 320px !important;
        }
        .chart-row .wide canvas {
            height: 380px !important;
        }
        .day-charts {
            display: grid;
            grid-template-columns: repeat(7, minmax(140px, 1fr));
            gap: 10px;
        }
        .day-chart-card {
            border: 1px solid #e3e3e3;
            border-radius: 8px;
            padding: 8px;
            text-align: center;
            background: #fafafa;
        }
        .day-chart-card h4 {
            margin: 0 0 8px;
            font-size: 13px;
        }
        .day-chart-card canvas {
            width: 100% !important;
            height: 180px !important;
        }
        @media (max-width: 980px) {
            .chart-row {
                grid-template-columns: 1fr;
            }
            .day-charts {
                grid-template-columns: repeat(2, minmax(140px, 1fr));
            }
        }
    </style>
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="icon" type="image/png" href="images/logo.png">
</head>
<body>
<?php
app_render_page_header('WR', 'Weekly Overtime Report', 'View weekly overtime data and generate reports.', [
    ['label' => 'Home', 'href' => 'welcome.php'],
    ['label' => 'Overtime Report', 'href' => 'overtime_report.php'],
    ['label' => 'Logout', 'href' => 'logout.php'],
]);
app_render_page_hero('Report', 'Generate weekly overtime reports.', 'Select a week and department to view the report.', []);
app_open_content_panel('Weekly Overtime', 'Configure report options below.');
?>
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
            <h2><p><strong>Selected Week:</strong> <?php echo htmlspecialchars($start_of_week . ' to ' . $end_of_week); ?></p></h2>
            <h2>Exceeded List</h2>
            <table>
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

            <div class="chart-board">
                <div class="chart-row split-60-40">
                    <div class="chart-card wide">
                        <h3>Overtime Analysis by Department</h3>
                        <canvas id="overtimeStackedChart"></canvas>
                    </div>
                    <div class="chart-card">
                        <h3>Total Distribution</h3>
                        <canvas id="overtimePieChart"></canvas>
                    </div>
                </div>

                <div class="chart-row">
                    <div class="chart-card wide full-width">
                        <h3>Workers Overtime vs Total Workers</h3>
                        <canvas id="workersComparisonChart"></canvas>
                    </div>
                </div>

                <div class="day-charts">
                    <div class="day-chart-card"><h4>Saturday</h4><canvas id="dayChart0"></canvas></div>
                    <div class="day-chart-card"><h4>Sunday</h4><canvas id="dayChart1"></canvas></div>
                    <div class="day-chart-card"><h4>Monday</h4><canvas id="dayChart2"></canvas></div>
                    <div class="day-chart-card"><h4>Tuesday</h4><canvas id="dayChart3"></canvas></div>
                    <div class="day-chart-card"><h4>Wednesday</h4><canvas id="dayChart4"></canvas></div>
                    <div class="day-chart-card"><h4>Thursday</h4><canvas id="dayChart5"></canvas></div>
                    <div class="day-chart-card"><h4>Friday</h4><canvas id="dayChart6"></canvas></div>
                </div>
            </div>


            <h2>Department Summary</h2>
            <table>
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
            <table>
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
                                    if (in_array($date, $holidays)) {
                                        $hours_per_employee = 0; // Exclude holidays
                                    }
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
            <table>
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
<?php
app_close_content_panel();
app_render_page_end();
?>
<?php if ($_SERVER['REQUEST_METHOD'] == 'POST'): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
<script>
    (function () {
        if (typeof Chart === 'undefined') {
            console.error('Chart.js failed to load. Check internet/CDN access.');
            return;
        }

        const labels = <?php echo json_encode($chart_labels); ?>;
        const noOt = <?php echo json_encode($chart_no_overtime); ?>;
        const hours0to10 = <?php echo json_encode($chart_0_10); ?>;
        const hours10to12 = <?php echo json_encode($chart_10_12); ?>;
        const moreThan12 = <?php echo json_encode($chart_more_12); ?>;
        const otWorkers = <?php echo json_encode($chart_ot_workers); ?>;
        const totalWorkers = <?php echo json_encode($chart_total_employees); ?>;
        const dayLabels = <?php echo json_encode($chart_day_labels); ?>;
        const daySubmitted = <?php echo json_encode($chart_day_submissions); ?>;
        const dayRemaining = <?php echo json_encode($chart_day_remaining); ?>;

        const totalNoOt = noOt.reduce((sum, value) => sum + value, 0);
        const total0to10 = hours0to10.reduce((sum, value) => sum + value, 0);
        const total10to12 = hours10to12.reduce((sum, value) => sum + value, 0);
        const totalMore12 = moreThan12.reduce((sum, value) => sum + value, 0);

        const stackedCtx = document.getElementById('overtimeStackedChart');
        if (stackedCtx) {
            new Chart(stackedCtx, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [
                        { label: 'Non OT', data: noOt, backgroundColor: '#1f77b4' },
                        { label: '0-10 Hr', data: hours0to10, backgroundColor: '#b7d69a' },
                        { label: '10-12 Hr', data: hours10to12, backgroundColor: '#8bc53f' },
                        { label: 'More Than 12 Hr', data: moreThan12, backgroundColor: '#d9534f' },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } },
                    scales: {
                        x: { stacked: true, ticks: { maxRotation: 70, minRotation: 45 } },
                        y: { stacked: true, beginAtZero: true },
                    },
                },
            });
        }

        const pieCtx = document.getElementById('overtimePieChart');
        if (pieCtx) {
            new Chart(pieCtx, {
                type: 'pie',
                data: {
                    labels: ['Non OT', '0-10 Hr', '10-12 Hr', 'More Than 12 Hr'],
                    datasets: [{
                        data: [totalNoOt, total0to10, total10to12, totalMore12],
                        backgroundColor: ['#1f77b4', '#b7d69a', '#8bc53f', '#d9534f'],
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' },
                        datalabels: {
                            formatter: function (value, ctx) {
                                var total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                                if (total === 0 || value === 0) return '';
                                return (value / total * 100).toFixed(1) + '%';
                            },
                            color: '#fff',
                            font: { weight: 'bold', size: 13 },
                        },
                    },
                },
                plugins: [ChartDataLabels],
            });
        }

        const compareCtx = document.getElementById('workersComparisonChart');
        if (compareCtx) {
            new Chart(compareCtx, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [
                        { label: 'Total OT Workers', data: otWorkers, backgroundColor: '#88c26a' },
                        { label: 'Total Factory', data: totalWorkers, backgroundColor: '#2f7fd0' },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } },
                    scales: {
                        x: { ticks: { maxRotation: 70, minRotation: 45 } },
                        y: { beginAtZero: true },
                    },
                },
            });
        }

        dayLabels.forEach((day, index) => {
            const dayCtx = document.getElementById(`dayChart${index}`);
            if (!dayCtx) return;
            new Chart(dayCtx, {
                type: 'pie',
                data: {
                    labels: [day + ' Submitted', 'Remaining Factory'],
                    datasets: [{
                        data: [daySubmitted[index] || 0, dayRemaining[index] || 0],
                        backgroundColor: ['#88c26a', '#2f7fd0'],
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 10 } },
                        datalabels: {
                            formatter: function (value, ctx) {
                                var total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                                if (total === 0 || value === 0) return '';
                                return (value / total * 100).toFixed(1) + '%';
                            },
                            color: '#fff',
                            font: { weight: 'bold', size: 11 },
                        },
                    },
                },
                plugins: [ChartDataLabels],
            });
        });
    })();
</script>
<?php endif; ?>
<script src="assets/js/app.js"></script>
</body>
</html>
