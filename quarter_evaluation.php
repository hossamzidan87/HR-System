<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';

if (!isset($_SESSION['username'])) {
    die("Unauthorized access. Please log in.");
}

$username = $_SESSION['username'];

// Initialize $selected_department and $selected_employee
$selected_department = '';
$selected_employee = '';
$previous_evaluation = [];

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

// Fetch the current quarter and evaluation period from the rules table
$quarter = '';
$year = '';
$eva_start = '';
$eva_end = '';
$defined_rule = '';
$quarter_sql = "SELECT quarter, year, eva_start, eva_end, defined_rule FROM rules WHERE defined_rule = quarter AND name = 'eva_q' LIMIT 1";
$quarter_result = $conn->query($quarter_sql);
if ($quarter_result && $quarter_result->num_rows > 0) {
    $quarter_row = $quarter_result->fetch_assoc();
    $quarter = $quarter_row['quarter'];
    $year = $quarter_row['year'];
    $eva_start = $quarter_row['eva_start'];
    $eva_end = $quarter_row['eva_end'];
    $defined_rule = $quarter_row['defined_rule'];
} else {
    die("Failed to fetch the current quarter and evaluation period.");
}

// Check if the current date and time are within the evaluation period and if the defined_rule matches the current quarter
$current_datetime = new DateTime();
$start_datetime = new DateTime($eva_start);
$end_datetime = new DateTime($eva_end);

if ($defined_rule !== $quarter) {
    die("<div style='text-align: center; font-weight: bold; font-size: 22px; color: red; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);'>The defined rule does not match the current quarter.</div>");
} elseif ($current_datetime < $start_datetime) {
    $interval = $current_datetime->diff($start_datetime);
    die("<div style='text-align: center; font-weight: bold; font-size: 22px; color: red; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);'>Evaluation period has not started yet. It will be available in " . $interval->format('%d days and %h hours') . ".<a href='welcome.php'><img src='/images/icons/home.png' alt='home' style='width:40px;height:40px;'></a><title>Evaluation Unavailable</title></div>");
} elseif ($current_datetime > $end_datetime) {
    die("<div style='text-align: center; font-weight: bold; font-size: 22px; color: red; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);'>Evaluation period has ended. Time out. ". $end_datetime->format('D d.m.Y H:i') . ".<a href='welcome.php'><img src='/images/icons/home.png' alt='home' style='width:40px;height:40px;'></a><title>Evaluation Unavailable</title></div>");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['select_department'])) {
        $selected_department = filter_input(INPUT_POST, 'department', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    } elseif (isset($_POST['select_employee'])) {
        $selected_department = filter_input(INPUT_POST, 'department', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $selected_employee = filter_input(INPUT_POST, 'employee', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        // Fetch previous evaluation for the selected employee, quarter, and year
        $evaluation_sql = "SELECT * FROM evaluations WHERE employee_code = ? AND quarter = ? AND year = ? ORDER BY evaluation_date DESC LIMIT 1";
        $stmt = $conn->prepare($evaluation_sql);
        if (!$stmt) {
            die("Database error: " . $conn->error);
        }
        $stmt->bind_param("ssi", $selected_employee, $quarter, $year);
        $stmt->execute();
        $evaluation_result = $stmt->get_result();
        if ($evaluation_result->num_rows > 0) {
            $previous_evaluation = $evaluation_result->fetch_assoc();
        }
        $stmt->close();
    } elseif (isset($_POST['clear_evaluation'])) {
        $selected_department = filter_input(INPUT_POST, 'department', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $selected_employee = filter_input(INPUT_POST, 'employee', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        // Delete the evaluation for the selected employee, quarter, and year
        $delete_sql = "DELETE FROM evaluations WHERE employee_code = ? AND quarter = ? AND year = ?";
        $stmt = $conn->prepare($delete_sql);
        if (!$stmt) {
            die("Database error: " . $conn->error);
        }
        $stmt->bind_param("ssi", $selected_employee, $quarter, $year);
        if ($stmt->execute()) {
            echo "Evaluation cleared successfully!";
        } else {
            echo "Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $selected_department = filter_input(INPUT_POST, 'department', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $selected_employee = filter_input(INPUT_POST, 'employee', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $evaluation_elements = [
            'attendance' => filter_input(INPUT_POST, 'attendance', FILTER_VALIDATE_INT),
            'productivity' => filter_input(INPUT_POST, 'productivity', FILTER_VALIDATE_INT),
            'work_quality' => filter_input(INPUT_POST, 'work_quality', FILTER_VALIDATE_INT),
            'communication_skills' => filter_input(INPUT_POST, 'communication_skills', FILTER_VALIDATE_INT),
            'job_knowledge' => filter_input(INPUT_POST, 'job_knowledge', FILTER_VALIDATE_INT),
            'cooperation' => filter_input(INPUT_POST, 'cooperation', FILTER_VALIDATE_INT),
            'technical_skills' => filter_input(INPUT_POST, 'technical_skills', FILTER_VALIDATE_INT),
            'commitment_to_safety' => filter_input(INPUT_POST, 'commitment_to_safety', FILTER_VALIDATE_INT),
            'attitude' => filter_input(INPUT_POST, 'attitude', FILTER_VALIDATE_INT),
            'creativity' => filter_input(INPUT_POST, 'creativity', FILTER_VALIDATE_INT),
        ];

        // Ensure all elements are rated
        foreach ($evaluation_elements as $element => $rating) {
            if ($rating === false || $rating < 1 || $rating > 10) {
                die("All evaluation elements must be rated between 1 and 10.");
            }
        }

        // Fetch job and employment_date for the selected employee
        $employee_info_sql = "SELECT job, employment_date, first_name FROM eva_list WHERE employee_code = ?";
        $stmt = $conn->prepare($employee_info_sql);
        if (!$stmt) {
            die("Database error: " . $conn->error);
        }
        $stmt->bind_param("s", $selected_employee);
        $stmt->execute();
        $employee_info_result = $stmt->get_result();
        if ($employee_info_result->num_rows > 0) {
            $employee_info = $employee_info_result->fetch_assoc();
            $job = $employee_info['job'];
            $employment_date = $employee_info['employment_date'];
            $employee_name = $employee_info['first_name'];
        } else {
            die("Employee not found.");
        }
        $stmt->close();

        // Fetch experience level
        $exp = filter_input(INPUT_POST, 'exp', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (!in_array($exp, ['A', 'B', 'C'])) {
            die("Invalid experience level.");
        }

        // Check if an evaluation already exists for the selected employee, quarter, and year
        $evaluation_check_sql = "SELECT * FROM evaluations WHERE employee_code = ? AND quarter = ? AND year = ?";
        $stmt = $conn->prepare($evaluation_check_sql);
        if (!$stmt) {
            die("Database error: " . $conn->error);
        }
        $stmt->bind_param("ssi", $selected_employee, $quarter, $year);
        $stmt->execute();
        $evaluation_check_result = $stmt->get_result();
        if ($evaluation_check_result->num_rows > 0) {
            // Update existing evaluation
            $stmt = $conn->prepare("UPDATE evaluations SET attendance = ?, productivity = ?, work_quality = ?, communication_skills = ?, job_knowledge = ?, cooperation = ?, technical_skills = ?, commitment_to_safety = ?, attitude = ?, creativity = ?, exp = ?, evaluated_by = ?, evaluation_date = NOW() WHERE employee_code = ? AND quarter = ? AND year = ?");
            if (!$stmt) {
                die("Database error: " . $conn->error);
            }
            $stmt->bind_param("iiiiiiiiiissssi", $evaluation_elements['attendance'], $evaluation_elements['productivity'], $evaluation_elements['work_quality'], $evaluation_elements['communication_skills'], $evaluation_elements['job_knowledge'], $evaluation_elements['cooperation'], $evaluation_elements['technical_skills'], $evaluation_elements['commitment_to_safety'], $evaluation_elements['attitude'], $evaluation_elements['creativity'], $exp, $username, $selected_employee, $quarter, $year);
        } else {
            // Insert new evaluation
            $stmt = $conn->prepare("INSERT INTO evaluations (employee_code, employee_name, department, job, employment_date, attendance, productivity, work_quality, communication_skills, job_knowledge, cooperation, technical_skills, commitment_to_safety, attitude, creativity, exp, evaluated_by, quarter, year, evaluation_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            if (!$stmt) {
                die("Database error: " . $conn->error);
            }
            $stmt->bind_param("sssssiiiiiiiiiisssi", $selected_employee, $employee_name, $selected_department, $job, $employment_date, $evaluation_elements['attendance'], $evaluation_elements['productivity'], $evaluation_elements['work_quality'], $evaluation_elements['communication_skills'], $evaluation_elements['job_knowledge'], $evaluation_elements['cooperation'], $evaluation_elements['technical_skills'], $evaluation_elements['commitment_to_safety'], $evaluation_elements['attitude'], $evaluation_elements['creativity'], $exp, $username, $quarter, $year);
        }

        if ($stmt->execute()) {
            echo "Evaluation submitted successfully!";
        } else {
            echo "Error: " . $stmt->error;
        }
        $stmt->close();

        // Fetch previous evaluation for the selected employee again to update the form with the latest values
        $evaluation_sql = "SELECT * FROM evaluations WHERE employee_code = ? AND quarter = ? AND year = ? ORDER BY evaluation_date DESC LIMIT 1";
        $stmt = $conn->prepare($evaluation_sql);
        if (!$stmt) {
            die("Database error: " . $conn->error);
        }
        $stmt->bind_param("ssi", $selected_employee, $quarter, $year);
        $stmt->execute();
        $evaluation_result = $stmt->get_result();
        if ($evaluation_result->num_rows > 0) {
            $previous_evaluation = $evaluation_result->fetch_assoc();
        }
        $stmt->close();
    }
}

// Fetch employees based on selected department
$employees = [];
if (isset($selected_department) && $selected_department !== '') {
    $employees_sql = "SELECT employee_code, first_name, department, job, employment_date FROM eva_list WHERE department = ?";
    $stmt = $conn->prepare($employees_sql);
    if (!$stmt) {
        die("Database error: " . $conn->error);
    }
    $stmt->bind_param("s", $selected_department);
    $stmt->execute();
    $employees_result = $stmt->get_result();
    while ($row = $employees_result->fetch_assoc()) {
        $employees[] = $row;
    }
    $stmt->close();
}

// Check if employees have been evaluated in the selected quarter and year
$evaluated_employees = [];
$evaluated_sql = "SELECT DISTINCT employee_code FROM evaluations WHERE quarter = ? AND year = ?";
$stmt = $conn->prepare($evaluated_sql);
if (!$stmt) {
    die("Database error: " . $conn->error);
}
$stmt->bind_param("si", $quarter, $year);
$stmt->execute();
$evaluated_result = $stmt->get_result();
if ($evaluated_result->num_rows > 0) {
    while ($row = $evaluated_result->fetch_assoc()) {
        $evaluated_employees[] = $row['employee_code'];
    }
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Quarter Evaluation</title>
    <style>
        .container { width: 80%; margin: 0 auto; text-align: center; }
        .form-group { margin: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #4CAF50; color: white; }
        tr:hover { background-color: #f5f5f5; }
        .image-container { display: flex; justify-content: flex-end; align-items: center; }
        .image-link { border: 1px solid #ddd; border-radius: 4px; padding: 5px; width: 25px; margin: 0 5px; display: inline-block; }
        .image-link:hover { box-shadow: 0 0 2px 1px rgba(0, 140, 186, 0.5); }
        .image-link img { width: 100%; height: auto; display: block; }
        .radio-group { display: flex; justify-content: center; align-items: center; }
        .radio-group label { margin: 0 5px; }
        .evaluation-table { border: 1px solid #ddd; border-radius: 4px; padding: 10px; margin-top: 20px; }
        .evaluation-table th, .evaluation-table td { border: 1px solid #ddd; padding: 10px; }
        .employee-photo { width: 100px; height: 130px; }
        .form-group label {
            font-size: 18px;
            font-weight: bold;
            margin-right: 8px;
            margin-right: 8px;
        }
        .form-group select, .form-group input {
            padding: 8px;
            border: 1px solid #ccc;
            font-weight: bold;
            border-radius: 4px;
            font-size: 12px;
        }
    </style>
    <script>
        function submitDepartmentForm() {
            document.getElementById('departmentForm').submit();
        }

        function submitEmployeeForm() {
            document.getElementById('employeeForm').submit();
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
        <h1>Employee Evaluation</h1>
        <h2>Quarter: <span style="color: red;"><?php echo htmlspecialchars($quarter); ?></span> | Year: <span style="color: red;"><?php echo htmlspecialchars($year); ?></span> | End Date: <span style="color: red;"><?php echo htmlspecialchars($eva_end); ?></span></h2>
        <form id="departmentForm" method="POST" action="quarter_evaluation.php">
            <input type="hidden" name="select_department" value="1">
            <div class="form-group">
                <label for="department">Choose Department:</label>
                <select id="department" name="department" required onchange="submitDepartmentForm()">
                    <option value="">Select a department</option>
                    <?php foreach ($allowed_departments as $department): ?>
                        <option value="<?php echo htmlspecialchars($department); ?>" <?php echo (isset($selected_department) && $selected_department == $department) ? 'selected' : ''; ?>><?php echo htmlspecialchars($department); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
        <form id="employeeForm" method="POST" action="quarter_evaluation.php">
            <input type="hidden" name="select_employee" value="1">
            <input type="hidden" name="department" value="<?php echo htmlspecialchars($selected_department); ?>">
            <div class="form-group">
                <label for="employee">Choose Employee:</label>
                <select id="employee" name="employee" required onchange="submitEmployeeForm()">
                    <option value="">Select an employee</option>
                    <?php foreach ($employees as $employee): ?>
                        <option value="<?php echo htmlspecialchars($employee['employee_code']); ?>" <?php echo (isset($selected_employee) && $selected_employee == $employee['employee_code']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($employee['employee_code'] . ' - ' . $employee['first_name']); ?>
                            <?php if (in_array($employee['employee_code'], $evaluated_employees)): ?>
                                &#10004; <!-- Check mark sign -->
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
        <?php if (!empty($selected_employee)): ?>
            <form method="POST" action="quarter_evaluation.php">
            <input type="hidden" name="department" value="<?php echo htmlspecialchars($selected_department); ?>">
            <input type="hidden" name="employee" value="<?php echo htmlspecialchars($selected_employee); ?>">
            <div class="evaluation-table">
                <h3>Employee Information</h3>
                <table>
                <tr>
                    <th>Photo</th>
                    <th>Code</th>
                    <th>Employee Name</th>
                    <th>Department</th>
                    <th>Job</th>
                    <th>Employment Date</th>
                </tr>
                <?php foreach ($employees as $employee): ?>
                    <?php if ($employee['employee_code'] == $selected_employee): ?>
                    <tr>
                        <td><img src="images/employees/<?php echo htmlspecialchars($employee['employee_code']); ?>.png" alt="Employee Photo" class="employee-photo"></td>
                        <td><?php echo htmlspecialchars($employee['employee_code']); ?></td>
                        <td><?php echo htmlspecialchars($employee['first_name']); ?></td>
                        <td><?php echo htmlspecialchars($employee['department']); ?></td>
                        <td><?php echo htmlspecialchars($employee['job']); ?></td>
                        <td><?php echo htmlspecialchars($employee['employment_date']); ?></td>
                    </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                </table>
            </div>
            <div class="evaluation-table">
                <h3>Experience level مستوى الخبره</h3>
                <div class="radio-group">
                    <label>
                        <input type="radio" name="exp" value="A" <?php echo (isset($previous_evaluation['exp']) && $previous_evaluation['exp'] == 'A') ? 'checked' : ''; ?> required> A
                    </label>
                    <label>
                        <input type="radio" name="exp" value="B" <?php echo (isset($previous_evaluation['exp']) && $previous_evaluation['exp'] == 'B') ? 'checked' : ''; ?> required> B
                    </label>
                    <label>
                        <input type="radio" name="exp" value="C" <?php echo (isset($previous_evaluation['exp']) && $previous_evaluation['exp'] == 'C') ? 'checked' : ''; ?> required> C
                    </label>
                </div>
            </div>
            <div class="evaluation-table">
                <h3>Evaluation Elements</h3>
                <table>
                <?php
                $elements = [
                    'attendance' => 'Attendance الحضور',
                    'productivity' => 'Productivity الإنتاجيه',
                    'work_quality' => 'Work Quality جودة العمل',
                    'communication_skills' => 'Communication Skills مهارات الإتصال',
                    'job_knowledge' => 'Job Knowledge معرفته بالوظيفه',
                    'cooperation' => 'Cooperation التعاون',
                    'technical_skills' => 'Technical Skills مهارات التقنية',
                    'commitment_to_safety' => 'Commitment To Safety الألتزام بالسلامه',
                    'attitude' => 'Attitude السلوك',
                    'creativity' => 'Creativity الإبداع'
                ];
                $total_evaluation = 0;
                $is_evaluated = !empty($previous_evaluation);
                foreach ($elements as $element => $label): ?>
                    <tr>
                    <td><?php echo $label; ?>:</td>
                    <td>
                        <div class="radio-group">
                        <?php for ($i = 1; $i <= 10; $i++): ?>
                            <label>
                            <input type="radio" id="<?php echo $element . $i; ?>" name="<?php echo $element; ?>" value="<?php echo $i; ?>" <?php echo (isset($previous_evaluation[$element]) && $previous_evaluation[$element] == $i) ? 'checked' : ''; ?> required>
                            <?php echo $i; ?>
                            </label>
                        <?php endfor; ?>
                        </div>
                    </td>
                    </tr>
                    <?php if ($is_evaluated) $total_evaluation += $previous_evaluation[$element]; ?>
                <?php endforeach; ?>
                <tr style="background-color:rgb(57, 139, 60); color: white;">
                    <td>Total Evaluation:</td>
                    <td><?php echo $is_evaluated ? $total_evaluation : 'Not Evaluated Yet'; ?></td>
                </tr>
                </table>
            </div>
            <button type="submit" style="font-size: 16px; background-color: green; color: white; padding: 10px 20px; border: none; border-radius: 5px;">Submit Evaluation</button>
            <button type="submit" name="clear_evaluation" value="1" style="font-size: 16px; background-color: green; color: white; padding: 10px 20px; border: none; border-radius: 5px;">Clear Evaluation</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>