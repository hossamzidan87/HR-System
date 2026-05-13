<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';
include 'includes/app_helpers.php';



// Initialize $selected_quarter, $selected_year, $selected_department, $selected_employee, and $evaluations
$selected_quarter = '';
$selected_year = '';
$selected_department = '';
$selected_employee = '';
$evaluations = [];
$chart_labels = [];
$chart_evaluated = [];
$chart_pending = [];
$chart_department_totals = [];
$chart_total_employees = 0;
$chart_total_evaluated = 0;

// Fetch allowed departments based on user groups
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

// Fetch evaluations based on selected quarter, year, department, or employee
$selected_quarter = isset($_POST['quarter']) ? trim((string) $_POST['quarter']) : null;
$selected_year = isset($_POST['year']) ? (int) $_POST['year'] : null;
$selected_department = isset($_POST['department']) ? trim((string) $_POST['department']) : null;
$selected_employee = isset($_POST['employee']) ? trim((string) $_POST['employee']) : null;

if ($selected_department && !in_array($selected_department, $allowed_departments, true)) {
    $selected_department = '';
    $selected_employee = '';
}

if ($selected_employee) {
    $stmt = $conn->prepare("SELECT * FROM evaluations WHERE employee_code = ? AND quarter = ? AND year = ? AND department = ?");
    $stmt->bind_param("ssis", $selected_employee, $selected_quarter, $selected_year, $selected_department);
    $stmt->execute();
    $result = $stmt->get_result();
    $evaluations = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else if ($selected_department) {
    $stmt = $conn->prepare("SELECT * FROM evaluations WHERE department = ? AND quarter = ? AND year = ?");
    $stmt->bind_param("ssi", $selected_department, $selected_quarter, $selected_year);
    $stmt->execute();
    $result = $stmt->get_result();
    $evaluations = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else if ($selected_quarter && $selected_year) {
    $departments_in = implode("','", $allowed_departments);
    $stmt = $conn->prepare("SELECT * FROM evaluations WHERE department IN ('$departments_in') AND quarter = ? AND year = ?");
    $stmt->bind_param("si", $selected_quarter, $selected_year);
    $stmt->execute();
    $result = $stmt->get_result();
    $evaluations = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

if ($selected_quarter && $selected_year && !empty($allowed_departments)) {
    $department_totals = array_fill_keys($allowed_departments, 0);
    $department_evaluated = array_fill_keys($allowed_departments, 0);

    $placeholders = implode(',', array_fill(0, count($allowed_departments), '?'));
    $types = str_repeat('s', count($allowed_departments));

    $totals_sql = "SELECT department, COUNT(DISTINCT employee_code) AS total FROM eva_list WHERE department IN ($placeholders) GROUP BY department";
    $stmt = $conn->prepare($totals_sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$allowed_departments);
        $stmt->execute();
        $totals_result = $stmt->get_result();
        while ($row = $totals_result->fetch_assoc()) {
            $department = (string) ($row['department'] ?? '');
            if (array_key_exists($department, $department_totals)) {
                $department_totals[$department] = (int) ($row['total'] ?? 0);
            }
        }
        $stmt->close();
    }

    $evaluated_types = 'si' . $types;
    $evaluated_sql = "SELECT department, COUNT(DISTINCT employee_code) AS evaluated FROM evaluations WHERE quarter = ? AND year = ? AND department IN ($placeholders) GROUP BY department";
    $stmt = $conn->prepare($evaluated_sql);
    if ($stmt) {
        $stmt->bind_param($evaluated_types, $selected_quarter, $selected_year, ...$allowed_departments);
        $stmt->execute();
        $evaluated_result = $stmt->get_result();
        while ($row = $evaluated_result->fetch_assoc()) {
            $department = (string) ($row['department'] ?? '');
            if (array_key_exists($department, $department_evaluated)) {
                $department_evaluated[$department] = (int) ($row['evaluated'] ?? 0);
            }
        }
        $stmt->close();
    }

    foreach ($allowed_departments as $department) {
        $total = (int) ($department_totals[$department] ?? 0);
        $evaluated = (int) ($department_evaluated[$department] ?? 0);
        $pending = max(0, $total - $evaluated);

        $chart_labels[] = $department;
        $chart_department_totals[] = $total;
        $chart_evaluated[] = $evaluated;
        $chart_pending[] = $pending;
    }

    $chart_total_employees = array_sum($chart_department_totals);
    $chart_total_evaluated = array_sum($chart_evaluated);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Quarter Report</title>
    <style>
        .form-group { margin: 10px; }
        table { width: 100%; border-collapse: collapse; margin: 0 auto; }
        th, td { padding: 2px; text-align: center; border-bottom: 1px solid #ddd; white-space: nowrap; font-size: 12px; }
        th { background-color: #4CAF50; color: white; white-space: wrap; font-size: 13px; cursor: pointer; }
        tr:hover { background-color: #f5f5f5; }
        .form-group label {
            font-size: 18px;
            font-weight: bold;
            margin-right: 8px;
        }
        .form-group select, .form-group input {
            padding: 8px;
            border: 1px solid #ccc;
            font-weight: bold;
            border-radius: 4px;
            font-size: 12px;
        }
        .employee-info-table, .evaluation-report-table {
            width: 40%;
            margin: 20px auto;
            border-collapse: collapse;
        }
        .employee-info-table th, .evaluation-report-table th, .employee-info-table td, .evaluation-report-table td {
            width: 50%;
            background-color: #4CAF50;
            color: white;
            font-weight: bold;
            font-size: 18px;
            padding: 10px;
            border: 1px solid #ddd;
        }
        .employee-info-table td, .evaluation-report-table td {
            background-color: white;
            color: black;
            font-weight: bold;
            font-size: 14px;
            padding: 15px;
            border: 1px solid #ddd;
        }
        .employee-photo { width: 100px; height: 130px; }
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
        @media (max-width: 980px) {
            .chart-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <script>
        function showEmployeeReport() {
            document.getElementById('departmentTable').style.display = 'none';
            document.getElementById('employeeTable').style.display = 'block';
        }

        function showDepartmentReport() {
            document.getElementById('departmentTable').style.display = 'block';
            document.getElementById('employeeTable').style.display = 'none';
        }

        function sortTable(tableId, columnIndex) {
            const table = document.getElementById(tableId);
            const tbody = table.querySelector("tbody");
            const rows = Array.from(tbody.rows); // Get all rows from tbody
            const isAscending = table.getAttribute('data-sort-order') === 'asc';
            const multiplier = isAscending ? 1 : -1;

            rows.sort((a, b) => {
                const aText = a.cells[columnIndex].innerText.trim();
                const bText = b.cells[columnIndex].innerText.trim();
                const aValue = isNaN(aText) ? aText.toLowerCase() : parseFloat(aText);
                const bValue = isNaN(bText) ? bText.toLowerCase() : parseFloat(bText);

                return aValue > bValue ? multiplier : aValue < bValue ? -multiplier : 0;
            });

            rows.forEach(row => tbody.appendChild(row)); // Re-append rows in sorted order
            table.setAttribute('data-sort-order', isAscending ? 'desc' : 'asc');
        }
    </script>
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="icon" type="image/png" href="images/logo.png">
</head>
<body>
<?php
app_render_page_header('QR', 'Quarter Report', 'Review and analyze quarterly evaluation data.', [
    ['label' => 'Home', 'href' => 'welcome.php'],
    ['label' => 'Evaluation', 'href' => 'evaluation.php'],
    ['label' => 'Logout', 'href' => 'logout.php'],
]);
app_render_page_hero('Report', 'View evaluations by department or employee.', 'Select a year and quarter to begin filtering results.', []);
app_open_content_panel('Evaluation Reports', 'Configure report options below.');
?>
        <form method="post">
        <div class="form-group">
                <label for="year">Select Year:</label>
                <select name="year" id="year" onchange="this.form.submit();">
                    <option value="">--Select Year--</option>
                    <?php
                    $current_year = date("Y");
                    for ($year = 2025; $year <= $current_year; $year++) {
                        echo "<option value=\"$year\" " . (($selected_year == $year) ? 'selected' : '') . ">$year</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label for="quarter">Select Quarter:</label>
                <select name="quarter" id="quarter" onchange="this.form.submit();">
                    <option value="">--Select Quarter--</option>
                    <option value="March" <?php if ($selected_quarter == 'March') echo 'selected'; ?>>March</option>
                    <option value="June" <?php if ($selected_quarter == 'June') echo 'selected'; ?>>June</option>
                    <option value="September" <?php if ($selected_quarter == 'September') echo 'selected'; ?>>September</option>
                </select>
            </div>
            <?php if ($selected_quarter && $selected_year): ?>
                <div class="form-group">
                    <label for="department">Select Department:</label>
                    <select name="department" id="department" onchange="this.form.submit(); showDepartmentReport();">
                        <option value="">--Select Department--</option>
                        <?php foreach ($allowed_departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>" <?php if ($dept == $selected_department) echo 'selected'; ?>><?php echo htmlspecialchars($dept); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($selected_department): ?>
                    <div class="form-group">
                        <label for="employee">Select Employee:</label>
                        <select name="employee" id="employee" onchange="this.form.submit(); showEmployeeReport();">
                            <option value="">--Select Employee--</option>
                            <?php
                            $query = "SELECT DISTINCT employee_code, employee_name FROM evaluations WHERE department = ? AND quarter = ? AND year = ?";
                            $stmt = $conn->prepare($query);
                            $stmt->bind_param("ssi", $selected_department, $selected_quarter, $selected_year);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            while ($row = $result->fetch_assoc()) {
                                $selected = ($row['employee_code'] == $selected_employee) ? 'selected' : '';
                                echo "<option value='{$row['employee_code']}' $selected>{$row['employee_name']}</option>";
                            }
                            $stmt->close();
                            ?>
                        </select>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </form>

        <?php if ($selected_quarter && $selected_year && !empty($allowed_departments)): ?>
            <div class="chart-board">
                <div class="chart-row split-60-40">
                    <div class="chart-card wide">
                        <h3>Evaluation Completion by Department</h3>
                        <canvas id="quarterDepartmentCompletionChart"></canvas>
                    </div>
                    <div class="chart-card">
                        <h3>Total Evaluation Completion</h3>
                        <canvas id="quarterCompletionPieChart"></canvas>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div id="departmentTable" style="display: <?php echo $selected_employee ? 'none' : 'block'; ?>;">
            <h2>Departments Report</h2>
            <h2>Count of evaluated Employees <?php echo count($evaluations); ?></h2>
            <table id="departmentTable" data-sort-order="asc">
                <thead>
                    <tr>
                        <th onclick="sortTable('departmentTable', 0)">Employee Code</th>
                        <th onclick="sortTable('departmentTable', 1)">Employee Name</th>
                        <th onclick="sortTable('departmentTable', 2)">Department</th>
                        <th onclick="sortTable('departmentTable', 3)">Job</th>
                        <th onclick="sortTable('departmentTable', 4)">Exp</th>
                        <th onclick="sortTable('departmentTable', 5)">Attendance</th>
                        <th onclick="sortTable('departmentTable', 6)">Productivity</th>
                        <th onclick="sortTable('departmentTable', 7)">Work Quality</th>
                        <th onclick="sortTable('departmentTable', 8)">Communication Skills</th>
                        <th onclick="sortTable('departmentTable', 9)">Job Knowledge</th>
                        <th onclick="sortTable('departmentTable', 10)">Cooperation</th>
                        <th onclick="sortTable('departmentTable', 11)">Technical Skills</th>
                        <th onclick="sortTable('departmentTable', 12)">Commitment to Safety</th>
                        <th onclick="sortTable('departmentTable', 13)">Attitude</th>
                        <th onclick="sortTable('departmentTable', 14)">Creativity</th>
                        <th onclick="sortTable('departmentTable', 15)">Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($evaluations as $evaluation): ?>
                    <?php
                    $total = $evaluation['attendance'] + $evaluation['productivity'] + $evaluation['work_quality'] + $evaluation['communication_skills'] + $evaluation['job_knowledge'] + $evaluation['cooperation'] + $evaluation['technical_skills'] + $evaluation['commitment_to_safety'] + $evaluation['attitude'] + $evaluation['creativity'];
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($evaluation['employee_code']); ?></td>
                        <td><?php echo htmlspecialchars($evaluation['employee_name']); ?></td>
                        <td><?php echo htmlspecialchars($evaluation['department']); ?></td>
                        <td><?php echo htmlspecialchars($evaluation['job']); ?></td>
                        <td><?php echo htmlspecialchars($evaluation['exp']); ?></td>
                        <td><?php echo htmlspecialchars($evaluation['attendance']); ?></td>
                        <td><?php echo htmlspecialchars($evaluation['productivity']); ?></td>
                        <td><?php echo htmlspecialchars($evaluation['work_quality']); ?></td>
                        <td><?php echo htmlspecialchars($evaluation['communication_skills']); ?></td>
                        <td><?php echo htmlspecialchars($evaluation['job_knowledge']); ?></td>
                        <td><?php echo htmlspecialchars($evaluation['cooperation']); ?></td>
                        <td><?php echo htmlspecialchars($evaluation['technical_skills']); ?></td>
                        <td><?php echo htmlspecialchars($evaluation['commitment_to_safety']); ?></td>
                        <td><?php echo htmlspecialchars($evaluation['attitude']); ?></td>
                        <td><?php echo htmlspecialchars($evaluation['creativity']); ?></td>
                        <td><?php echo htmlspecialchars($total); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div id="employeeTable" style="display: <?php echo $selected_employee ? 'block' : 'none'; ?>;">
            <h2>Employee Report</h2>
            <?php if ($selected_employee): ?>
            <?php foreach ($evaluations as $evaluation): ?>
                <?php
                $total = $evaluation['attendance'] + $evaluation['productivity'] + $evaluation['work_quality'] + $evaluation['communication_skills'] + $evaluation['job_knowledge'] + $evaluation['cooperation'] + $evaluation['technical_skills'] + $evaluation['commitment_to_safety'] + $evaluation['attitude'] + $evaluation['creativity'];
                ?>
                <h3>Employee Information</h3>
                <table border="1" class="employee-info-table">
                <tr>
                    <th>Photo</th>
                    <th>Employee Code</th>
                    <th>Employee Name</th>
                    <th>Department</th>
                    <th>Job</th>
                    <th>Experience level</th>
                </tr>
                <tr>
                    <td><img src="images/employees/<?php echo htmlspecialchars($evaluation['employee_code']); ?>.png" alt="Employee Photo" class="employee-photo"></td>
                    <td><?php echo htmlspecialchars($evaluation['employee_code']); ?></td>
                    <td><?php echo htmlspecialchars($evaluation['employee_name']); ?></td>
                    <td><?php echo htmlspecialchars($evaluation['department']); ?></td>
                    <td><?php echo htmlspecialchars($evaluation['job']); ?></td>
                    <td><?php echo htmlspecialchars($evaluation['exp']); ?></td>
                </tr>
                </table>

                <h3>Evaluation Report</h3>
                <table border="1" class="evaluation-report-table">
                <tr>
                    <th>Attendance</th>
                    <td><?php echo htmlspecialchars($evaluation['attendance']); ?></td>
                </tr>
                <tr>
                    <th>Productivity</th>
                    <td><?php echo htmlspecialchars($evaluation['productivity']); ?></td>
                </tr>
                <tr>
                    <th>Work Quality</th>
                    <td><?php echo htmlspecialchars($evaluation['work_quality']); ?></td>
                </tr>
                <tr>
                    <th>Communication Skills</th>
                    <td><?php echo htmlspecialchars($evaluation['communication_skills']); ?></td>
                </tr>
                <tr>
                    <th>Job Knowledge</th>
                    <td><?php echo htmlspecialchars($evaluation['job_knowledge']); ?></td>
                </tr>
                <tr>
                    <th>Cooperation</th>
                    <td><?php echo htmlspecialchars($evaluation['cooperation']); ?></td>
                </tr>
                <tr>
                    <th>Technical Skills</th>
                    <td><?php echo htmlspecialchars($evaluation['technical_skills']); ?></td>
                </tr>
                <tr>
                    <th>Commitment to Safety</th>
                    <td><?php echo htmlspecialchars($evaluation['commitment_to_safety']); ?></td>
                </tr>
                <tr>
                    <th>Attitude</th>
                    <td><?php echo htmlspecialchars($evaluation['attitude']); ?></td>
                </tr>
                <tr>
                    <th>Creativity</th>
                    <td><?php echo htmlspecialchars($evaluation['creativity']); ?></td>
                </tr>
                <tr>
                    <th>Total</th>
                    <td><?php echo htmlspecialchars($total); ?></td>
                </tr>
                </table>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
<?php
app_close_content_panel();
app_render_page_end();
?>
<?php if ($selected_quarter && $selected_year && !empty($allowed_departments)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
<script>
    (function () {
        if (typeof Chart === 'undefined') {
            console.error('Chart.js failed to load. Check internet/CDN access.');
            return;
        }

        const labels = <?php echo json_encode($chart_labels); ?>;
        const evaluated = <?php echo json_encode($chart_evaluated); ?>;
        const pending = <?php echo json_encode($chart_pending); ?>;
        const totalEmployees = <?php echo json_encode((int) $chart_total_employees); ?>;
        const totalEvaluated = <?php echo json_encode((int) $chart_total_evaluated); ?>;
        const totalPending = Math.max(0, totalEmployees - totalEvaluated);

        const departmentCtx = document.getElementById('quarterDepartmentCompletionChart');
        if (departmentCtx) {
            new Chart(departmentCtx, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [
                        { label: 'Evaluated', data: evaluated, backgroundColor: '#88c26a' },
                        { label: 'Pending', data: pending, backgroundColor: '#2f7fd0' },
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

        const pieCtx = document.getElementById('quarterCompletionPieChart');
        if (pieCtx) {
            new Chart(pieCtx, {
                type: 'pie',
                data: {
                    labels: ['Evaluated', 'Pending'],
                    datasets: [{
                        data: [totalEvaluated, totalPending],
                        backgroundColor: ['#88c26a', '#2f7fd0'],
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
    })();
</script>
<?php endif; ?>
<script src="assets/js/app.js"></script>
</body>
</html>
