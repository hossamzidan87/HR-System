<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';



// Initialize $selected_quarter, $selected_year, $selected_department, $selected_employee, and $evaluations
$selected_quarter = '';
$selected_year = '';
$selected_department = '';
$selected_employee = '';
$evaluations = [];

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
$selected_quarter = isset($_POST['quarter']) ? $_POST['quarter'] : null;
$selected_year = isset($_POST['year']) ? $_POST['year'] : null;
$selected_department = isset($_POST['department']) ? $_POST['department'] : null;
$selected_employee = isset($_POST['employee']) ? $_POST['employee'] : null;

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Quarter Report</title>
    <style>
        .container { width: 100%; margin: 0 auto; text-align: center; }
        .form-group { margin: 10px; }
        table { width: 100%; border-collapse: collapse; margin: 0 auto; }
        th, td { padding: 2px; text-align: center; border-bottom: 1px solid #ddd; white-space: nowrap; font-size: 12px; }
        th { background-color: #4CAF50; color: white; white-space: wrap; font-size: 13px; }
        tr:hover { background-color: #f5f5f5; }
        .image-container { display: flex; justify-content: flex-end; align-items: center; }
        .image-link { border: 1px solid #ddd; border-radius: 4px; padding: 5px; width: 25px; margin: 0 5px; display: inline-block; }
        .image-link:hover { box-shadow: 0 0 2px 1px rgba(0, 140, 186, 0.5); }
        .image-link img { width: 100%; height: auto; display: block; }
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
    </script>
</head>
<body>
    <div class="image-container">
        <div class="image-link">
            <a href="welcome.php"><img src="/images/icons/home.png" alt="home"></a>
        </div>
        <div class="image-link">
            <a href="evaluation.php"><img src="/images/icons/evaluation.png" alt="Evaluation"></a>
        </div>
        <div class="image-link">
            <a href="logout.php"><img src="/images/icons/logout.png" alt="logout"></a>
        </div>
    </div>

    <div class="container">
        <h1>Quarter Evaluation Reports</h1>
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

        <div id="departmentTable" style="display: <?php echo $selected_employee ? 'none' : 'block'; ?>;">
            <h2>Departments Report</h2>
            <table border="1">
                <tr>
                    <th>Employee Code</th>
                    <th>Employee Name</th>
                    <th>Department</th>
                    <th>Job</th>
                    <th>Exp</th>
                    <th>Attendance</th>
                    <th>Productivity</th>
                    <th>Work Quality</th>
                    <th>Communication Skills</th>
                    <th>Job Knowledge</th>
                    <th>Cooperation</th>
                    <th>Technical Skills</th>
                    <th>Commitment to Safety</th>
                    <th>Attitude</th>
                    <th>Creativity</th>
                    <th>Total</th>
                </tr>
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
        </div>
    </div>
</body>
</html>