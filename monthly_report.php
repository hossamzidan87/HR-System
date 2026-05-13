<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';
include 'includes/app_helpers.php';

if (!isset($_SESSION['username'])) {
    die('Unauthorized access. Please log in.');
}

$username = $_SESSION['username'];

$user_groups = [];
$stmt = $conn->prepare('SELECT group_name_1, group_name_2, group_name_3 FROM user_permissions WHERE username = ?');
if (!$stmt) {
    die('Database error: ' . $conn->error);
}
$stmt->bind_param('s', $username);
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

$allowed_departments = [];
if (!empty($user_groups)) {
    $groups_in = implode("','", $user_groups);
    $departments_sql = "SELECT DISTINCT department FROM department_groups WHERE group_name IN ('$groups_in') OR group_name1 IN ('$groups_in') OR group_name2 IN ('$groups_in') ORDER BY department";
    $departments_result = $conn->query($departments_sql);
    if (!$departments_result) {
        die('Database error: ' . $conn->error);
    }
    while ($row = $departments_result->fetch_assoc()) {
        $allowed_departments[] = $row['department'];
    }
}

$saturdays = [];
$holidays = [];
$calendar_sql = "SELECT days, type FROM calendar";
$calendar_result = $conn->query($calendar_sql);
if (!$calendar_result) {
    die('Database error: ' . $conn->error);
}
while ($row = $calendar_result->fetch_assoc()) {
    if ($row['type'] === 'saturday') {
        $saturdays[] = $row['days'];
    } elseif ($row['type'] === 'holiday') {
        $holidays[] = $row['days'];
    }
}

$selected_year = (int) date('Y');
$selected_month_number = (int) date('m');
$selected_month = date('Y-m');
$selected_department = 'all';
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');

$employee_monthly_rows = [];
$department_summary = [];
$exceeded_list = [];
$daily_submission_count_selected = [];

$chart_labels = [];
$chart_no_overtime = [];
$chart_0_40 = [];
$chart_40_48 = [];
$chart_more_48 = [];
$chart_ot_workers = [];
$chart_total_employees = [];
$chart_day_labels = [];
$chart_day_submissions = [];
$chart_day_remaining = [];

$month_names = [
    1 => 'January',
    2 => 'February',
    3 => 'March',
    4 => 'April',
    5 => 'May',
    6 => 'June',
    7 => 'July',
    8 => 'August',
    9 => 'September',
    10 => 'October',
    11 => 'November',
    12 => 'December',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_year = filter_input(INPUT_POST, 'year', FILTER_VALIDATE_INT) ?: (int) date('Y');
    $selected_month_number = filter_input(INPUT_POST, 'month_number', FILTER_VALIDATE_INT) ?: (int) date('m');
    $selected_department = filter_input(INPUT_POST, 'department', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'all';

    if ($selected_month_number < 1 || $selected_month_number > 12) {
        $selected_month_number = (int) date('m');
    }

    $selected_month = sprintf('%04d-%02d', $selected_year, $selected_month_number);

    if ($selected_department !== 'all' && !in_array($selected_department, $allowed_departments, true)) {
        die('Invalid department selected.');
    }

    $month_start = date('Y-m-01', strtotime($selected_month . '-01'));
    $month_end = date('Y-m-t', strtotime($selected_month . '-01'));

    if (!empty($allowed_departments)) {
        $department_employee_count = [];
        $total_employees_sql = "SELECT department, COUNT(DISTINCT employee_code) AS total_employees FROM employees GROUP BY department";
        $total_employees_result = $conn->query($total_employees_sql);
        if (!$total_employees_result) {
            die('Database error: ' . $conn->error);
        }
        while ($row = $total_employees_result->fetch_assoc()) {
            $department_employee_count[$row['department']] = (int) $row['total_employees'];
        }

        if ($selected_department === 'all') {
            $escaped_departments = array_map(static function ($department) use ($conn) {
                return "'" . $conn->real_escape_string($department) . "'";
            }, $allowed_departments);
            $departments_in = implode(',', $escaped_departments);

            $monthly_sql = "SELECT o.employee_code, o.employee_name, o.department, o.job,
                SUM(CASE
                    WHEN o.overtime_date IN (SELECT days FROM calendar WHERE type = 'holiday') THEN 0
                    WHEN DAYOFWEEK(o.overtime_date) = 6 THEN 0
                    WHEN DAYOFWEEK(o.overtime_date) = 7 AND o.overtime_date NOT IN (SELECT days FROM calendar WHERE type = 'saturday') THEN 8
                    ELSE 2
                END) AS total_hours
                FROM overtime o
                WHERE o.types = 'normal'
                  AND o.department IN ($departments_in)
                  AND o.overtime_date BETWEEN ? AND ?
                GROUP BY o.employee_code, o.employee_name, o.department, o.job
                ORDER BY o.department, total_hours DESC, o.employee_name";
            $stmt = $conn->prepare($monthly_sql);
            if (!$stmt) {
                die('Database error: ' . $conn->error);
            }
            $stmt->bind_param('ss', $month_start, $month_end);
        } else {
            $monthly_sql = "SELECT o.employee_code, o.employee_name, o.department, o.job,
                SUM(CASE
                    WHEN o.overtime_date IN (SELECT days FROM calendar WHERE type = 'holiday') THEN 0
                    WHEN DAYOFWEEK(o.overtime_date) = 6 THEN 0
                    WHEN DAYOFWEEK(o.overtime_date) = 7 AND o.overtime_date NOT IN (SELECT days FROM calendar WHERE type = 'saturday') THEN 8
                    ELSE 2
                END) AS total_hours
                FROM overtime o
                WHERE o.types = 'normal'
                  AND o.department = ?
                  AND o.overtime_date BETWEEN ? AND ?
                GROUP BY o.employee_code, o.employee_name, o.department, o.job
                ORDER BY o.department, total_hours DESC, o.employee_name";
            $stmt = $conn->prepare($monthly_sql);
            if (!$stmt) {
                die('Database error: ' . $conn->error);
            }
            $stmt->bind_param('sss', $selected_department, $month_start, $month_end);
        }

        $stmt->execute();
        $monthly_result = $stmt->get_result();
        $employee_overtime = [];
        while ($row = $monthly_result->fetch_assoc()) {
            $employee_code = $row['employee_code'];
            $employee_overtime[$employee_code] = [
                'employee_code' => $row['employee_code'],
                'employee_name' => $row['employee_name'],
                'department' => $row['department'],
                'job' => $row['job'],
                'total_hours' => (float) $row['total_hours'],
            ];
        }
        $stmt->close();

        foreach ($employee_overtime as $employee) {
            $employee_monthly_rows[] = $employee;
            if ($employee['total_hours'] > 48) {
                $exceeded_list[] = $employee;
            }
        }

        usort($exceeded_list, static function ($a, $b) {
            return $b['total_hours'] <=> $a['total_hours'];
        });

        foreach ($allowed_departments as $department) {
            if ($selected_department === 'all' || $department === $selected_department) {
                $dept_overtime = array_filter($employee_monthly_rows, static function ($overtime) use ($department) {
                    return $overtime['department'] === $department;
                });

                $total_employees = $department_employee_count[$department] ?? 0;
                $employees_with_overtime = count($dept_overtime);
                $no_overtime = max(0, $total_employees - $employees_with_overtime);
                $less_than_40 = count(array_unique(array_column(array_filter($dept_overtime, static function ($overtime) {
                    return $overtime['total_hours'] > 0 && $overtime['total_hours'] <= 40;
                }), 'employee_code')));
                $between_40_and_48 = count(array_unique(array_column(array_filter($dept_overtime, static function ($overtime) {
                    return $overtime['total_hours'] > 40 && $overtime['total_hours'] <= 48;
                }), 'employee_code')));
                $more_than_48 = count(array_unique(array_column(array_filter($dept_overtime, static function ($overtime) {
                    return $overtime['total_hours'] > 48;
                }), 'employee_code')));

                $department_summary[] = [
                    'department' => $department,
                    'total_employees' => $total_employees,
                    'no_overtime' => $no_overtime,
                    'less_than_40' => $less_than_40,
                    'between_40_and_48' => $between_40_and_48,
                    'more_than_48' => $more_than_48,
                ];
            }
        }

        $daily_submission_sql = "SELECT department, overtime_date, COUNT(DISTINCT employee_code) AS submission_count
            FROM overtime
            WHERE types = 'normal'
              AND (department = ? OR ? = 'all')
              AND overtime_date BETWEEN ? AND ?
            GROUP BY department, overtime_date";
        $stmt = $conn->prepare($daily_submission_sql);
        if (!$stmt) {
            die('Database error: ' . $conn->error);
        }
        $stmt->bind_param('ssss', $selected_department, $selected_department, $month_start, $month_end);
        $stmt->execute();
        $daily_submission_result = $stmt->get_result();
        while ($row = $daily_submission_result->fetch_assoc()) {
            $daily_submission_count_selected[$row['department']][$row['overtime_date']] = (int) $row['submission_count'];
        }
        $stmt->close();

        foreach ($department_summary as $summary) {
            $chart_labels[] = $summary['department'];
            $chart_no_overtime[] = (int) $summary['no_overtime'];
            $chart_0_40[] = (int) $summary['less_than_40'];
            $chart_40_48[] = (int) $summary['between_40_and_48'];
            $chart_more_48[] = (int) $summary['more_than_48'];
            $chart_ot_workers[] = (int) ($summary['less_than_40'] + $summary['between_40_and_48'] + $summary['more_than_48']);
            $chart_total_employees[] = (int) $summary['total_employees'];
        }

        $chart_total_factory = array_sum($chart_total_employees);
        $days_in_month = (int) date('t', strtotime($month_start));
        for ($day = 1; $day <= $days_in_month; $day++) {
            $date = date('Y-m-d', strtotime($selected_month . '-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT)));
            $chart_day_labels[] = date('d', strtotime($date));

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
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Report</title>
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="icon" type="image/png" href="images/logo.png">
    <style>
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
        .table-scroll {
            overflow-x: auto;
            margin-bottom: 18px;
        }
        @media (max-width: 980px) {
            .chart-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<?php
app_render_page_header('MR', 'Monthly Overtime Report', 'Monthly overtime ranges and exceeded employees in one report.', [
    ['label' => 'Home', 'href' => 'welcome.php'],
    ['label' => 'Overtime Report', 'href' => 'overtime_report.php'],
    ['label' => 'Logout', 'href' => 'logout.php'],
]);
app_render_page_hero('Report', 'Generate monthly overtime report ranges and exceeded list.', 'Use month and department filters to review 0-40, 40-48, and more than 48 hour categories, including employees who exceeded 48 monthly hours.', [
    ['title' => 'Month', 'text' => ($month_names[$selected_month_number] ?? date('F')) . ' ' . $selected_year],
    ['title' => 'Departments', 'text' => (string) count($allowed_departments) . ' available'],
]);
app_open_content_panel('Monthly Overtime', 'Choose a month and department to load the monthly range summary and exceeded list.');
?>
<form method="POST" action="monthly_report.php">
    <div class="form-row">
        <label for="year">Year</label>
        <select id="year" name="year" required>
            <?php for ($year = 2025; $year <= (int) date('Y') + 1; $year++): ?>
                <option value="<?php echo htmlspecialchars((string) $year); ?>" <?php echo $selected_year === $year ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) $year); ?></option>
            <?php endfor; ?>
        </select>

        <label for="month_number">Month</label>
        <select id="month_number" name="month_number" required>
            <?php foreach ($month_names as $month_value => $month_label): ?>
                <option value="<?php echo htmlspecialchars((string) $month_value); ?>" <?php echo $selected_month_number === $month_value ? 'selected' : ''; ?>><?php echo htmlspecialchars($month_label); ?></option>
            <?php endforeach; ?>
        </select>

        <label for="department">Department</label>
        <select id="department" name="department" required>
            <option value="all" <?php echo $selected_department === 'all' ? 'selected' : ''; ?>>All Departments</option>
            <?php foreach ($allowed_departments as $department): ?>
                <option value="<?php echo htmlspecialchars($department); ?>" <?php echo $selected_department === $department ? 'selected' : ''; ?>><?php echo htmlspecialchars($department); ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="button">Generate Report</button>
    </div>
</form>

<?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
    <?php if (!empty($employee_monthly_rows)): ?>
        <div class="summary-grid">
            <div class="summary-card"><strong>Month Range</strong><p><?php echo htmlspecialchars($month_start . ' to ' . $month_end); ?></p></div>
            <div class="summary-card"><strong>Total Employees With Overtime</strong><p><?php echo htmlspecialchars((string) count($employee_monthly_rows)); ?></p></div>
            <div class="summary-card"><strong>Exceeded 48 Hours</strong><p><?php echo htmlspecialchars((string) count($exceeded_list)); ?></p></div>
        </div>

        <h2>Exceeded List</h2>
        <?php if (!empty($exceeded_list)): ?>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Employee Code</th>
                            <th>Employee Name</th>
                            <th>Department</th>
                            <th>Job</th>
                            <th>Total Monthly Hours</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($exceeded_list as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['employee_code']); ?></td>
                                <td><?php echo htmlspecialchars($row['employee_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['department']); ?></td>
                                <td><?php echo htmlspecialchars($row['job']); ?></td>
                                <td><?php echo htmlspecialchars((string) $row['total_hours']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="message-card">No employees exceeded 48 overtime hours for this month.</div>
        <?php endif; ?>

        <div class="chart-board">
            <div class="chart-row split-60-40">
                <div class="chart-card wide">
                    <h3>Overtime Analysis by Department</h3>
                    <canvas id="monthlyOvertimeStackedChart"></canvas>
                </div>
                <div class="chart-card">
                    <h3>Total Distribution</h3>
                    <canvas id="monthlyOvertimePieChart"></canvas>
                </div>
            </div>

            <div class="chart-row">
                <div class="chart-card wide full-width">
                    <h3>Workers Overtime vs Total Workers</h3>
                    <canvas id="monthlyWorkersComparisonChart"></canvas>
                </div>
            </div>

            <div class="chart-row">
                <div class="chart-card wide full-width">
                    <h3>Daily Submission vs Remaining Factory</h3>
                    <canvas id="monthlyDayChart"></canvas>
                </div>
            </div>
        </div>

        <h2>Department Summary</h2>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>Total Employees</th>
                        <th>No Overtime</th>
                        <th>0-40 Hours</th>
                        <th>40-48 Hours</th>
                        <th>More than 48 Hours</th>
                        <th>Total Employees OT</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $total_employees_sum = 0;
                    $no_overtime_sum = 0;
                    $less_than_40_sum = 0;
                    $between_40_and_48_sum = 0;
                    $more_than_48_sum = 0;
                    $total_employees_ot_sum = 0;
                    ?>
                    <?php foreach ($department_summary as $summary): ?>
                        <?php
                        $total_employees_sum += $summary['total_employees'];
                        $no_overtime_sum += $summary['no_overtime'];
                        $less_than_40_sum += $summary['less_than_40'];
                        $between_40_and_48_sum += $summary['between_40_and_48'];
                        $more_than_48_sum += $summary['more_than_48'];
                        $total_employees_ot = $summary['less_than_40'] + $summary['between_40_and_48'] + $summary['more_than_48'];
                        $total_employees_ot_sum += $total_employees_ot;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($summary['department']); ?></td>
                            <td><?php echo htmlspecialchars((string) $summary['total_employees']); ?></td>
                            <td><?php echo htmlspecialchars((string) $summary['no_overtime']); ?></td>
                            <td><?php echo htmlspecialchars((string) $summary['less_than_40']); ?></td>
                            <td><?php echo htmlspecialchars((string) $summary['between_40_and_48']); ?></td>
                            <td><?php echo htmlspecialchars((string) $summary['more_than_48']); ?></td>
                            <td><?php echo htmlspecialchars((string) $total_employees_ot); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th>Total</th>
                        <th><?php echo htmlspecialchars((string) $total_employees_sum); ?></th>
                        <th><?php echo htmlspecialchars((string) $no_overtime_sum); ?></th>
                        <th><?php echo htmlspecialchars((string) $less_than_40_sum); ?></th>
                        <th><?php echo htmlspecialchars((string) $between_40_and_48_sum); ?></th>
                        <th><?php echo htmlspecialchars((string) $more_than_48_sum); ?></th>
                        <th><?php echo htmlspecialchars((string) $total_employees_ot_sum); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <h2>Daily Total Submission Overtime Hours for Selected Month</h2>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <?php foreach ($chart_day_labels as $dayLabel): ?>
                            <th><?php echo htmlspecialchars($dayLabel); ?></th>
                        <?php endforeach; ?>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <?php
                        $monthly_total_hours = 0;
                        foreach ($chart_day_labels as $index => $dayLabel) {
                            $date = date('Y-m-', strtotime($month_start)) . $dayLabel;
                            $total_hours = 0;
                            foreach ($daily_submission_count_selected as $department => $dates) {
                                if (in_array($department, $allowed_departments, true) && isset($dates[$date])) {
                                    $day_of_week = date('w', strtotime($date));
                                    $hours_per_employee = ($day_of_week == 5 || ($day_of_week == 6 && !in_array($date, $saturdays, true))) ? 8 : 2;
                                    $total_hours += $dates[$date] * $hours_per_employee;
                                }
                            }
                            $monthly_total_hours += $total_hours;
                            echo '<td>' . htmlspecialchars((string) $total_hours) . '</td>';
                        }
                        ?>
                        <td><?php echo htmlspecialchars((string) $monthly_total_hours); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <h2>Daily Submission Count for Selected Month and Department</h2>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Department</th>
                        <?php foreach ($chart_day_labels as $dayLabel): ?>
                            <th><?php echo htmlspecialchars($dayLabel); ?></th>
                        <?php endforeach; ?>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daily_submission_count_selected as $department => $dates): ?>
                        <?php if (in_array($department, $allowed_departments, true)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($department); ?></td>
                                <?php
                                $total_count = 0;
                                foreach ($chart_day_labels as $dayLabel) {
                                    $date = date('Y-m-', strtotime($month_start)) . $dayLabel;
                                    $count = $dates[$date] ?? 0;
                                    $total_count += $count;
                                    echo '<td>' . htmlspecialchars((string) $count) . '</td>';
                                }
                                ?>
                                <td><?php echo htmlspecialchars((string) $total_count); ?></td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th>Total</th>
                        <?php
                        $grand_total = 0;
                        foreach ($chart_day_labels as $dayLabel) {
                            $date = date('Y-m-', strtotime($month_start)) . $dayLabel;
                            $day_total = 0;
                            foreach ($daily_submission_count_selected as $department => $dates) {
                                if (in_array($department, $allowed_departments, true) && isset($dates[$date])) {
                                    $day_total += $dates[$date];
                                }
                            }
                            $grand_total += $day_total;
                            echo '<th>' . htmlspecialchars((string) $day_total) . '</th>';
                        }
                        ?>
                        <th><?php echo htmlspecialchars((string) $grand_total); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php else: ?>
        <div class="message-card">No overtime records found for the selected month and department.</div>
    <?php endif; ?>
<?php endif; ?>
<?php
app_close_content_panel();
app_render_page_end();
?>
<?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($employee_monthly_rows)): ?>
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
        const hours0to40 = <?php echo json_encode($chart_0_40); ?>;
        const hours40to48 = <?php echo json_encode($chart_40_48); ?>;
        const moreThan48 = <?php echo json_encode($chart_more_48); ?>;
        const otWorkers = <?php echo json_encode($chart_ot_workers); ?>;
        const totalWorkers = <?php echo json_encode($chart_total_employees); ?>;
        const dayLabels = <?php echo json_encode($chart_day_labels); ?>;
        const daySubmitted = <?php echo json_encode($chart_day_submissions); ?>;
        const dayRemaining = <?php echo json_encode($chart_day_remaining); ?>;

        const totalNoOt = noOt.reduce((sum, value) => sum + value, 0);
        const total0to40 = hours0to40.reduce((sum, value) => sum + value, 0);
        const total40to48 = hours40to48.reduce((sum, value) => sum + value, 0);
        const totalMore48 = moreThan48.reduce((sum, value) => sum + value, 0);

        const stackedCtx = document.getElementById('monthlyOvertimeStackedChart');
        if (stackedCtx) {
            new Chart(stackedCtx, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [
                        { label: 'Non OT', data: noOt, backgroundColor: '#1f77b4' },
                        { label: '0-40 Hr', data: hours0to40, backgroundColor: '#b7d69a' },
                        { label: '40-48 Hr', data: hours40to48, backgroundColor: '#8bc53f' },
                        { label: 'More Than 48 Hr', data: moreThan48, backgroundColor: '#d9534f' },
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

        const pieCtx = document.getElementById('monthlyOvertimePieChart');
        if (pieCtx) {
            new Chart(pieCtx, {
                type: 'pie',
                data: {
                    labels: ['Non OT', '0-40 Hr', '40-48 Hr', 'More Than 48 Hr'],
                    datasets: [{
                        data: [totalNoOt, total0to40, total40to48, totalMore48],
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

        const compareCtx = document.getElementById('monthlyWorkersComparisonChart');
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

        const dayCtx = document.getElementById('monthlyDayChart');
        if (dayCtx) {
            new Chart(dayCtx, {
                type: 'bar',
                data: {
                    labels: dayLabels,
                    datasets: [
                        { label: 'Submitted', data: daySubmitted, backgroundColor: '#88c26a' },
                        { label: 'Remaining Factory', data: dayRemaining, backgroundColor: '#2f7fd0' },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } },
                    scales: {
                        x: { ticks: { maxRotation: 0, minRotation: 0 } },
                        y: { beginAtZero: true },
                    },
                },
            });
        }
    })();
</script>
<?php endif; ?>
<script src="assets/js/app.js"></script>
</body>
</html>

