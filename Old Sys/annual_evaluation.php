<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';

if (!isset($_SESSION['username'])) {
    die("Unauthorized access. Please log in.");
}

$username = $_SESSION['username'];

// Initialize variables
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
$year = '';
$eva_start = '';
$eva_end = '';
$quarter_sql = "SELECT year, eva_start, eva_end FROM rules WHERE name = 'eva_y' LIMIT 1";
$quarter_result = $conn->query($quarter_sql);
if ($quarter_result && $quarter_result->num_rows > 0) {
    $quarter_row = $quarter_result->fetch_assoc();
    $year = $quarter_row['year'];
    $eva_start = $quarter_row['eva_start'];
    $eva_end = $quarter_row['eva_end'];
} else {
    die("Failed to fetch the current quarter and evaluation period.");
}

// --- START: block page based on eva_start / eva_end ---
try {
    $tz = new DateTimeZone('Africa/Cairo');
    $now = new DateTime('now', $tz);
    $start = new DateTime($eva_start, $tz);
    $end = new DateTime($eva_end, $tz);
} catch (Exception $e) {
    die("Invalid evaluation period configuration.");
}

if ($now < $start) {
    // Not started yet
    $msg = "Evaluation not started. Starts on " . htmlspecialchars($start->format('Y-m-d H:i:s'));
    die("<div style='text-align:center;font-weight:bold;font-size:20px;color:#333;padding:40px;'>$msg.<a href='welcome.php'><img src='/images/icons/home.png' alt='home' style='width:40px;height:40px;'></a><title>Evaluation Unavailable</title></div>");
}

if ($now > $end) {
    // Already finished
    $msg = "Evaluation period has ended on " . htmlspecialchars($end->format('Y-m-d H:i:s'));
    die("<div style='text-align:center;font-weight:bold;font-size:20px;color:red;padding:40px;'>$msg.<a href='welcome.php'><img src='/images/icons/home.png' alt='home' style='width:40px;height:40px;'></a><title>Evaluation Unavailable</title></div>");
}
// --- END: block page based on eva_start / eva_end ---


// Fetch current year
$quarter_sql = "SELECT year FROM rules WHERE name = 'eva_q' LIMIT 1";
$quarter_result = $conn->query($quarter_sql);
if ($quarter_result && $quarter_result->num_rows > 0) {
    $quarter_row = $quarter_result->fetch_assoc();
    $current_year = $quarter_row['year'];
} else {
    die("Database error: " . $conn->error);
}


// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['select_department'])) {
        $selected_department = filter_input(INPUT_POST, 'department', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    } elseif (isset($_POST['select_employee'])) {
        $selected_department = filter_input(INPUT_POST, 'department', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $selected_employee = filter_input(INPUT_POST, 'employee', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        // Fetch previous evaluation
        $evaluation_sql = "SELECT * FROM annual_evaluation WHERE employee_code = ? AND year = ? ORDER BY evaluation_date DESC LIMIT 1";
        $stmt = $conn->prepare($evaluation_sql);
        if (!$stmt) {
            die("Database error: " . $conn->error);
        }
        $stmt->bind_param("si", $selected_employee, $year);
        $stmt->execute();
        $evaluation_result = $stmt->get_result();
        if ($evaluation_result->num_rows > 0) {
            $previous_evaluation = $evaluation_result->fetch_assoc();
        }
        $stmt->close();
    } elseif (isset($_POST['clear_evaluation'])) {
        $selected_department = filter_input(INPUT_POST, 'department', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $selected_employee = filter_input(INPUT_POST, 'employee', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        // Delete evaluation
        $delete_sql = "DELETE FROM annual_evaluation WHERE employee_code = ? AND year = ?";
        $stmt = $conn->prepare($delete_sql);
        if (!$stmt) {
            die("Database error: " . $conn->error);
        }
        $stmt->bind_param("si", $selected_employee, $year);
        if ($stmt->execute()) {
            echo "Evaluation cleared successfully!";
        } else {
            echo "Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $selected_department = filter_input(INPUT_POST, 'department', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $selected_employee = filter_input(INPUT_POST, 'employee', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        
        // Collect evaluation elements (ela1 to ela20)
        $evaluation_elements = [];
        for ($i = 1; $i <= 20; $i++) {
            $evaluation_elements['ela' . $i] = filter_input(INPUT_POST, 'ela' . $i, FILTER_VALIDATE_INT);
            if ($evaluation_elements['ela' . $i] === false || $evaluation_elements['ela' . $i] < 1 || $evaluation_elements['ela' . $i] > 5) {
                die("Invalid rating for element " . $i);
            }
        }

        // Fetch employee info
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

        // Check if evaluation exists
        $evaluation_check_sql = "SELECT * FROM annual_evaluation WHERE employee_code = ? AND year = ?";
        $stmt = $conn->prepare($evaluation_check_sql);
        if (!$stmt) {
            die("Database error: " . $conn->error);
        }
        $stmt->bind_param("si", $selected_employee, $year);
        $stmt->execute();
        $evaluation_check_result = $stmt->get_result();
        
        if ($evaluation_check_result->num_rows > 0) {
            // Update existing
            $update_sql = "UPDATE annual_evaluation SET ela1=?, ela2=?, ela3=?, ela4=?, ela5=?, ela6=?, ela7=?, ela8=?, ela9=?, ela10=?, ela11=?, ela12=?, ela13=?, ela14=?, ela15=?, ela16=?, ela17=?, ela18=?, ela19=?, ela20=?, exp=?, evaluated_by=?, evaluation_date=NOW() WHERE employee_code=? AND year=?";
            $stmt = $conn->prepare($update_sql);
            if (!$stmt) {
                die("Database error: " . $conn->error);
            }
            $stmt->bind_param("iiiiiiiiiiiiiiiiiiiisssi", $evaluation_elements['ela1'], $evaluation_elements['ela2'], $evaluation_elements['ela3'], $evaluation_elements['ela4'], $evaluation_elements['ela5'], $evaluation_elements['ela6'], $evaluation_elements['ela7'], $evaluation_elements['ela8'], $evaluation_elements['ela9'], $evaluation_elements['ela10'], $evaluation_elements['ela11'], $evaluation_elements['ela12'], $evaluation_elements['ela13'], $evaluation_elements['ela14'], $evaluation_elements['ela15'], $evaluation_elements['ela16'], $evaluation_elements['ela17'], $evaluation_elements['ela18'], $evaluation_elements['ela19'], $evaluation_elements['ela20'], $exp, $username, $selected_employee, $year);
        } else {
            // Insert new
            $insert_sql = "INSERT INTO annual_evaluation (employee_code, employee_name, department, job, employment_date, ela1, ela2, ela3, ela4, ela5, ela6, ela7, ela8, ela9, ela10, ela11, ela12, ela13, ela14, ela15, ela16, ela17, ela18, ela19, ela20, exp, evaluated_by, year, evaluation_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($insert_sql);
            if (!$stmt) {
                die("Database error: " . $conn->error);
            }
            $stmt->bind_param("sssssiiiiiiiiiiiiiiiiiiiissi", $selected_employee, $employee_name, $selected_department, $job, $employment_date, $evaluation_elements['ela1'], $evaluation_elements['ela2'], $evaluation_elements['ela3'], $evaluation_elements['ela4'], $evaluation_elements['ela5'], $evaluation_elements['ela6'], $evaluation_elements['ela7'], $evaluation_elements['ela8'], $evaluation_elements['ela9'], $evaluation_elements['ela10'], $evaluation_elements['ela11'], $evaluation_elements['ela12'], $evaluation_elements['ela13'], $evaluation_elements['ela14'], $evaluation_elements['ela15'], $evaluation_elements['ela16'], $evaluation_elements['ela17'], $evaluation_elements['ela18'], $evaluation_elements['ela19'], $evaluation_elements['ela20'], $exp, $username, $year);
        }

        if ($stmt->execute()) {
            echo "Evaluation submitted successfully!";
        } else {
            echo "Error: " . $stmt->error;
        }
        $stmt->close();

        // Fetch updated evaluation
        $evaluation_sql = "SELECT * FROM annual_evaluation WHERE employee_code = ? AND year = ? ORDER BY evaluation_date DESC LIMIT 1";
        $stmt = $conn->prepare($evaluation_sql);
        if (!$stmt) {
            die("Database error: " . $conn->error);
        }
        $stmt->bind_param("si", $selected_employee, $year);
        $stmt->execute();
        $evaluation_result = $stmt->get_result();
        if ($evaluation_result->num_rows > 0) {
            $previous_evaluation = $evaluation_result->fetch_assoc();
        }
        $stmt->close();
    }
}

// Fetch employees
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
$evaluated_sql = "SELECT DISTINCT employee_code FROM annual_evaluation WHERE year = ?";
$stmt = $conn->prepare($evaluated_sql);
if (!$stmt) {
    die("Database error: " . $conn->error);
}
$stmt->bind_param("i", $year);
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
    <title>Employee Annual Evaluation</title>
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
        .form-group label { font-size: 18px; font-weight: bold; margin-right: 8px; }
        .form-group select, .form-group input { padding: 8px; border: 1px solid #ccc; font-weight: bold; border-radius: 4px; font-size: 12px; }
        .employee-photo { width: 100px; height: 130px; }
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
        <h1>Annual Employee Evaluation</h1>
        <h2>Year: <span style="color: red;"><?php echo htmlspecialchars($year); ?></span></h2>
        
        <form id="departmentForm" method="POST" action="annual_evaluation.php">
            <input type="hidden" name="select_department" value="1">
            <div class="form-group">
                <label for="department">Select Department:</label>
                <select id="department" name="department" onchange="submitDepartmentForm()">
                    <option value="">--Select Department--</option>
                    <?php foreach ($allowed_departments as $dept): ?>
                        <option value="<?php echo htmlspecialchars($dept); ?>" <?php if ($dept == $selected_department) echo 'selected'; ?>><?php echo htmlspecialchars($dept); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <form id="employeeForm" method="POST" action="annual_evaluation.php">
            <input type="hidden" name="select_employee" value="1">
            <input type="hidden" name="department" value="<?php echo htmlspecialchars($selected_department); ?>">
            <div class="form-group">
                <label for="employee">Select Employee:</label>
                <select id="employee" name="employee" onchange="submitEmployeeForm()">
                    <option value="">--Select Employee--</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?php echo htmlspecialchars($emp['employee_code']); ?>" <?php if ($emp['employee_code'] == $selected_employee) echo 'selected'; ?>><?php echo htmlspecialchars($emp['first_name']); ?>
                                                <?php if (in_array($emp['employee_code'], $evaluated_employees)): ?>
                                &#10004; <!-- Check mark sign -->
                            <?php endif; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <?php if (!empty($selected_employee)): ?>
            <form method="POST" action="annual_evaluation.php">
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
                                <div class="evaluation-table">
                    <h3>Experience Level / مستوى الخبره</h3>
                    <div class="form-group">
                        <label>Select Experience Level:</label>
                        <label><input type="radio" name="exp" value="A" <?php echo (!empty($previous_evaluation['exp']) && $previous_evaluation['exp'] == 'A') ? 'checked' : ''; ?> required> A </label>
                        <label><input type="radio" name="exp" value="B" <?php echo (!empty($previous_evaluation['exp']) && $previous_evaluation['exp'] == 'B') ? 'checked' : ''; ?>> B </label>
                        <label><input type="radio" name="exp" value="C" <?php echo (!empty($previous_evaluation['exp']) && $previous_evaluation['exp'] == 'C') ? 'checked' : ''; ?>> C </label>
                        <label><input type="radio" name="exp" value="D" <?php echo (!empty($previous_evaluation['exp']) && $previous_evaluation['exp'] == 'D') ? 'checked' : ''; ?>> D ( Out of expectation )</label>
                    </div>
                </div>
                    <h3>Evaluation Elements (Rate from 1 to 5)</h3>
                    <table>
                        <tr>
                            <th style="text-align: center;">Element</th>
                            <th colspan="5" style="text-align: center;">Rating</th>
                        </tr>
                        <tr>
                            <th>Information about the work environment / معلومات عن بيئة العمل</th>
                            <th>1</th>
                            <th>2</th>
                            <th>3</th>
                            <th>4</th>
                            <th>5</th>
                        </tr>
                        <tr>
                            <td>1. Exhibits the required level of knowledge and skills to perform the job.</br>
يُظهر مستوى المعرفة والمهارات المطلوبة لتنفيذ العمل.</td>
                            <td><input type="radio" name="ela1" value="1" <?php echo (!empty($previous_evaluation['ela1']) && $previous_evaluation['ela1'] == 1) ? 'checked' : ''; ?> required></td>
                            <td><input type="radio" name="ela1" value="2" <?php echo (!empty($previous_evaluation['ela1']) && $previous_evaluation['ela1'] == 2) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela1" value="3" <?php echo (!empty($previous_evaluation['ela1']) && $previous_evaluation['ela1'] == 3) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela1" value="4" <?php echo (!empty($previous_evaluation['ela1']) && $previous_evaluation['ela1'] == 4) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela1" value="5" <?php echo (!empty($previous_evaluation['ela1']) && $previous_evaluation['ela1'] == 5) ? 'checked' : ''; ?>></td>
                        </tr>
                        <tr>
                            <td>2. Use of established techniques.</br>
يستخدم الأساليب المتّبعة في أداء الوظيفة.</td>
                            <td><input type="radio" name="ela2" value="1" <?php echo (!empty($previous_evaluation['ela2']) && $previous_evaluation['ela2'] == 1) ? 'checked' : ''; ?> required></td>
                            <td><input type="radio" name="ela2" value="2" <?php echo (!empty($previous_evaluation['ela2']) && $previous_evaluation['ela2'] == 2) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela2" value="3" <?php echo (!empty($previous_evaluation['ela2']) && $previous_evaluation['ela2'] == 3) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela2" value="4" <?php echo (!empty($previous_evaluation['ela2']) && $previous_evaluation['ela2'] == 4) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela2" value="5" <?php echo (!empty($previous_evaluation['ela2']) && $previous_evaluation['ela2'] == 5) ? 'checked' : ''; ?>></td>
                        </tr>
                        <tr>
                            <td>3. Uses materials and equipment properly.</br>
يستخدم المواد والمعدات بشكل صحيح.</td>
                            <td><input type="radio" name="ela3" value="1" <?php echo (!empty($previous_evaluation['ela3']) && $previous_evaluation['ela3'] == 1) ? 'checked' : ''; ?> required></td>
                            <td><input type="radio" name="ela3" value="2" <?php echo (!empty($previous_evaluation['ela3']) && $previous_evaluation['ela3'] == 2) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela3" value="3" <?php echo (!empty($previous_evaluation['ela3']) && $previous_evaluation['ela3'] == 3) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela3" value="4" <?php echo (!empty($previous_evaluation['ela3']) && $previous_evaluation['ela3'] == 4) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela3" value="5" <?php echo (!empty($previous_evaluation['ela3']) && $previous_evaluation['ela3'] == 5) ? 'checked' : ''; ?>></td>
                        </tr>
                        <tr>
                            <th>Quality of work / جودة العمل</th>
                            <th>1</th>
                            <th>2</th>
                            <th>3</th>
                            <th>4</th>
                            <th>5</th>
                        </tr>
                        <tr>
                            <td>4. Completes assigned tasks without errors.</br>
يُنجز المهام المطلوبة دون أخطاء.</td>
                            <td><input type="radio" name="ela4" value="1" <?php echo (!empty($previous_evaluation['ela4']) && $previous_evaluation['ela4'] == 1) ? 'checked' : ''; ?> required></td>
                            <td><input type="radio" name="ela4" value="2" <?php echo (!empty($previous_evaluation['ela4']) && $previous_evaluation['ela4'] == 2) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela4" value="3" <?php echo (!empty($previous_evaluation['ela4']) && $previous_evaluation['ela4'] == 3) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela4" value="4" <?php echo (!empty($previous_evaluation['ela4']) && $previous_evaluation['ela4'] == 4) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela4" value="5" <?php echo (!empty($previous_evaluation['ela4']) && $previous_evaluation['ela4'] == 5) ? 'checked' : ''; ?>></td>
                        </tr>
                        <tr>
                            <td>5. Improves the quality of work.</br>
يسعى إلى رفع جودة العمل.</td>
                            <td><input type="radio" name="ela5" value="1" <?php echo (!empty($previous_evaluation['ela5']) && $previous_evaluation['ela5'] == 1) ? 'checked' : ''; ?> required></td>
                            <td><input type="radio" name="ela5" value="2" <?php echo (!empty($previous_evaluation['ela5']) && $previous_evaluation['ela5'] == 2) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela5" value="3" <?php echo (!empty($previous_evaluation['ela5']) && $previous_evaluation['ela5'] == 3) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela5" value="4" <?php echo (!empty($previous_evaluation['ela5']) && $previous_evaluation['ela5'] == 4) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela5" value="5" <?php echo (!empty($previous_evaluation['ela5']) && $previous_evaluation['ela5'] == 5) ? 'checked' : ''; ?>></td>
                        </tr>
                        <tr>
                            <td>6. Meets job requirements.</br>
يلبّي متطلبات الوظيفة.</td>
                            <td><input type="radio" name="ela6" value="1" <?php echo (!empty($previous_evaluation['ela6']) && $previous_evaluation['ela6'] == 1) ? 'checked' : ''; ?> required></td>
                            <td><input type="radio" name="ela6" value="2" <?php echo (!empty($previous_evaluation['ela6']) && $previous_evaluation['ela6'] == 2) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela6" value="3" <?php echo (!empty($previous_evaluation['ela6']) && $previous_evaluation['ela6'] == 3) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela6" value="4" <?php echo (!empty($previous_evaluation['ela6']) && $previous_evaluation['ela6'] == 4) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela6" value="5" <?php echo (!empty($previous_evaluation['ela6']) && $previous_evaluation['ela6'] == 5) ? 'checked' : ''; ?>></td>
                        </tr>
                        <tr>
                            <td>7. Analyzes problems and determines appropriate solutions.</br>
يحلل المشكلات ويحدد الحلول المناسبة.</td>
                            <td><input type="radio" name="ela7" value="1" <?php echo (!empty($previous_evaluation['ela7']) && $previous_evaluation['ela7'] == 1) ? 'checked' : ''; ?> required></td>
                            <td><input type="radio" name="ela7" value="2" <?php echo (!empty($previous_evaluation['ela7']) && $previous_evaluation['ela7'] == 2) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela7" value="3" <?php echo (!empty($previous_evaluation['ela7']) && $previous_evaluation['ela7'] == 3) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela7" value="4" <?php echo (!empty($previous_evaluation['ela7']) && $previous_evaluation['ela7'] == 4) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela7" value="5" <?php echo (!empty($previous_evaluation['ela7']) && $previous_evaluation['ela7'] == 5) ? 'checked' : ''; ?>></td>
                        </tr>
                        <tr>
                            <td>8. Accepts changes in duties and work procedures.</br>
يتقبّل التغييرات في المهام وإجراءات العمل.</td>
                            <td><input type="radio" name="ela8" value="1" <?php echo (!empty($previous_evaluation['ela8']) && $previous_evaluation['ela8'] == 1) ? 'checked' : ''; ?> required></td>
                            <td><input type="radio" name="ela8" value="2" <?php echo (!empty($previous_evaluation['ela8']) && $previous_evaluation['ela8'] == 2) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela8" value="3" <?php echo (!empty($previous_evaluation['ela8']) && $previous_evaluation['ela8'] == 3) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela8" value="4" <?php echo (!empty($previous_evaluation['ela8']) && $previous_evaluation['ela8'] == 4) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela8" value="5" <?php echo (!empty($previous_evaluation['ela8']) && $previous_evaluation['ela8'] == 5) ? 'checked' : ''; ?>></td>
                        </tr>
                        <tr>
                            <td>9. Develops and applies new techniques or approaches.</br>
يبتكر ويطبّق أساليب أو طرق عمل جديدة.</td>
                            <td><input type="radio" name="ela9" value="1" <?php echo (!empty($previous_evaluation['ela9']) && $previous_evaluation['ela9'] == 1) ? 'checked' : ''; ?> required></td>
                            <td><input type="radio" name="ela9" value="2" <?php echo (!empty($previous_evaluation['ela9']) && $previous_evaluation['ela9'] == 2) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela9" value="3" <?php echo (!empty($previous_evaluation['ela9']) && $previous_evaluation['ela9'] == 3) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela9" value="4" <?php echo (!empty($previous_evaluation['ela9']) && $previous_evaluation['ela9'] == 4) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela9" value="5" <?php echo (!empty($previous_evaluation['ela9']) && $previous_evaluation['ela9'] == 5) ? 'checked' : ''; ?>></td>
                        </tr>
                        <tr>
                            <td>10. Complies with work rules and organizational policies.</br>
يلتزم بنظام العمل وسياسات المؤسسة.</td>
                            <td><input type="radio" name="ela10" value="1" <?php echo (!empty($previous_evaluation['ela10']) && $previous_evaluation['ela10'] == 1) ? 'checked' : ''; ?> required></td>
                            <td><input type="radio" name="ela10" value="2" <?php echo (!empty($previous_evaluation['ela10']) && $previous_evaluation['ela10'] == 2) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela10" value="3" <?php echo (!empty($previous_evaluation['ela10']) && $previous_evaluation['ela10'] == 3) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela10" value="4" <?php echo (!empty($previous_evaluation['ela10']) && $previous_evaluation['ela10'] == 4) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela10" value="5" <?php echo (!empty($previous_evaluation['ela10']) && $previous_evaluation['ela10'] == 5) ? 'checked' : ''; ?>></td>
                        </tr>
                        <tr>
                            <td>11. Prioritizes tasks according to the companys best interest.</br>
يحدّد الأولويات بما يحقق مصلحة الشركة.</td>
                            <td><input type="radio" name="ela11" value="1" <?php echo (!empty($previous_evaluation['ela11']) && $previous_evaluation['ela11'] == 1) ? 'checked' : ''; ?> required></td>
                            <td><input type="radio" name="ela11" value="2" <?php echo (!empty($previous_evaluation['ela11']) && $previous_evaluation['ela11'] == 2) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela11" value="3" <?php echo (!empty($previous_evaluation['ela11']) && $previous_evaluation['ela11'] == 3) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela11" value="4" <?php echo (!empty($previous_evaluation['ela11']) && $previous_evaluation['ela11'] == 4) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela11" value="5" <?php echo (!empty($previous_evaluation['ela11']) && $previous_evaluation['ela11'] == 5) ? 'checked' : ''; ?>></td>
                        </tr>
                        <tr>
                            <td>12. Shows enthusiasm for work.</br>
يظهر الحماس في أداء العمل.</td>
                            <td><input type="radio" name="ela12" value="1" <?php echo (!empty($previous_evaluation['ela12']) && $previous_evaluation['ela12'] == 1) ? 'checked' : ''; ?> required></td>
                            <td><input type="radio" name="ela12" value="2" <?php echo (!empty($previous_evaluation['ela12']) && $previous_evaluation['ela12'] == 2) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela12" value="3" <?php echo (!empty($previous_evaluation['ela12']) && $previous_evaluation['ela12'] == 3) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela12" value="4" <?php echo (!empty($previous_evaluation['ela12']) && $previous_evaluation['ela12'] == 4) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela12" value="5" <?php echo (!empty($previous_evaluation['ela12']) && $previous_evaluation['ela12'] == 5) ? 'checked' : ''; ?>></td>
                        </tr>
                        <tr>
                            <th>Punctuality at work / الالتزام أثناء العمل</th>
                            <th>1</th>
                            <th>2</th>
                            <th>3</th>
                            <th>4</th>
                            <th>5</th>
                        </tr>
                        <tr>
                            <td>13. Considers work arrival, departure times, and breaks.</br>
يلتزم بمواعيد الحضور والانصراف وفترات الاستراحة.</td>
                            <td><input type="radio" name="ela13" value="1" <?php echo (!empty($previous_evaluation['ela13']) && $previous_evaluation['ela13'] == 1) ? 'checked' : ''; ?> required></td>
                            <td><input type="radio" name="ela13" value="2" <?php echo (!empty($previous_evaluation['ela13']) && $previous_evaluation['ela13'] == 2) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela13" value="3" <?php echo (!empty($previous_evaluation['ela13']) && $previous_evaluation['ela13'] == 3) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela13" value="4" <?php echo (!empty($previous_evaluation['ela13']) && $previous_evaluation['ela13'] == 4) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela13" value="5" <?php echo (!empty($previous_evaluation['ela13']) && $previous_evaluation['ela13'] == 5) ? 'checked' : ''; ?>></td>
                        </tr>
                        <tr>
                            <td>14. Absences and permissions.</br>
يلتزم بالإجراءات الخاصة بالغياب والاستئذان.</td>
                            <td><input type="radio" name="ela14" value="1" <?php echo (!empty($previous_evaluation['ela14']) && $previous_evaluation['ela14'] == 1) ? 'checked' : ''; ?> required></td>
                            <td><input type="radio" name="ela14" value="2" <?php echo (!empty($previous_evaluation['ela14']) && $previous_evaluation['ela14'] == 2) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela14" value="3" <?php echo (!empty($previous_evaluation['ela14']) && $previous_evaluation['ela14'] == 3) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela14" value="4" <?php echo (!empty($previous_evaluation['ela14']) && $previous_evaluation['ela14'] == 4) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela14" value="5" <?php echo (!empty($previous_evaluation['ela14']) && $previous_evaluation['ela14'] == 5) ? 'checked' : ''; ?>></td>
                        </tr>
                        <tr>
                            <td>15. Meets work needs and works extra hours when required.</br>
يراعي احتياجات العمل ويعمل ساعات إضافية عند الحاجة.</td>
                            <td><input type="radio" name="ela15" value="1" <?php echo (!empty($previous_evaluation['ela15']) && $previous_evaluation['ela15'] == 1) ? 'checked' : ''; ?> required></td>
                            <td><input type="radio" name="ela15" value="2" <?php echo (!empty($previous_evaluation['ela15']) && $previous_evaluation['ela15'] == 2) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela15" value="3" <?php echo (!empty($previous_evaluation['ela15']) && $previous_evaluation['ela15'] == 3) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela15" value="4" <?php echo (!empty($previous_evaluation['ela15']) && $previous_evaluation['ela15'] == 4) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela15" value="5" <?php echo (!empty($previous_evaluation['ela15']) && $previous_evaluation['ela15'] == 5) ? 'checked' : ''; ?>></td>
                        </tr>
                        <tr>
                            <td>16. Planning and organization.</br>
يمتاز بالتخطيط والتنظيم في العمل.</td>
                            <td><input type="radio" name="ela16" value="1" <?php echo (!empty($previous_evaluation['ela16']) && $previous_evaluation['ela16'] == 1) ? 'checked' : ''; ?> required></td>
                            <td><input type="radio" name="ela16" value="2" <?php echo (!empty($previous_evaluation['ela16']) && $previous_evaluation['ela16'] == 2) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela16" value="3" <?php echo (!empty($previous_evaluation['ela16']) && $previous_evaluation['ela16'] == 3) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela16" value="4" <?php echo (!empty($previous_evaluation['ela16']) && $previous_evaluation['ela16'] == 4) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela16" value="5" <?php echo (!empty($previous_evaluation['ela16']) && $previous_evaluation['ela16'] == 5) ? 'checked' : ''; ?>></td>
                        </tr>
                        <tr>
                            <th>Behaviors at Workplace / السلوك داخل بيئة العمل</th>
                            <th>1</th>
                            <th>2</th>
                            <th>3</th>
                            <th>4</th>
                            <th>5</th>
                        </tr>
                        <tr>
                            <td>17. Assists co-workers in accomplishing their tasks.</br>
يتعاون مع الزملاء في إنجاز مهامهم عند الحاجة.</td>
                            <td><input type="radio" name="ela17" value="1" <?php echo (!empty($previous_evaluation['ela17']) && $previous_evaluation['ela17'] == 1) ? 'checked' : ''; ?> required></td>
                            <td><input type="radio" name="ela17" value="2" <?php echo (!empty($previous_evaluation['ela17']) && $previous_evaluation['ela17'] == 2) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela17" value="3" <?php echo (!empty($previous_evaluation['ela17']) && $previous_evaluation['ela17'] == 3) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela17" value="4" <?php echo (!empty($previous_evaluation['ela17']) && $previous_evaluation['ela17'] == 4) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela17" value="5" <?php echo (!empty($previous_evaluation['ela17']) && $previous_evaluation['ela17'] == 5) ? 'checked' : ''; ?>></td>
                        </tr>
                        <tr>
                            <td>18. Ethical behavior in the work environment.</br>
يلتزم بالأخلاقيات داخل بيئة العمل.</td>
                            <td><input type="radio" name="ela18" value="1" <?php echo (!empty($previous_evaluation['ela18']) && $previous_evaluation['ela18'] == 1) ? 'checked' : ''; ?> required></td>
                            <td><input type="radio" name="ela18" value="2" <?php echo (!empty($previous_evaluation['ela18']) && $previous_evaluation['ela18'] == 2) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela18" value="3" <?php echo (!empty($previous_evaluation['ela18']) && $previous_evaluation['ela18'] == 3) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela18" value="4" <?php echo (!empty($previous_evaluation['ela18']) && $previous_evaluation['ela18'] == 4) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela18" value="5" <?php echo (!empty($previous_evaluation['ela18']) && $previous_evaluation['ela18'] == 5) ? 'checked' : ''; ?>></td>
                        </tr>
                        <tr>
                            <td>19. Improves behavior and serves as a role model.</br>
يحسن سلوكه ويكون قدوة لغيره.</td>
                            <td><input type="radio" name="ela19" value="1" <?php echo (!empty($previous_evaluation['ela19']) && $previous_evaluation['ela19'] == 1) ? 'checked' : ''; ?> required></td>
                            <td><input type="radio" name="ela19" value="2" <?php echo (!empty($previous_evaluation['ela19']) && $previous_evaluation['ela19'] == 2) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela19" value="3" <?php echo (!empty($previous_evaluation['ela19']) && $previous_evaluation['ela19'] == 3) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela19" value="4" <?php echo (!empty($previous_evaluation['ela19']) && $previous_evaluation['ela19'] == 4) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela19" value="5" <?php echo (!empty($previous_evaluation['ela19']) && $previous_evaluation['ela19'] == 5) ? 'checked' : ''; ?>></td>
                        </tr>
                        <tr>
                            <td>20. Shows frankness and seriousness when dealing with supervisors and co-workers.</br>
يتعامل بصراحة وجدية مع الرؤساء والزملاء.</td>
                            <td><input type="radio" name="ela20" value="1" <?php echo (!empty($previous_evaluation['ela20']) && $previous_evaluation['ela20'] == 1) ? 'checked' : ''; ?> required></td>
                            <td><input type="radio" name="ela20" value="2" <?php echo (!empty($previous_evaluation['ela20']) && $previous_evaluation['ela20'] == 2) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela20" value="3" <?php echo (!empty($previous_evaluation['ela20']) && $previous_evaluation['ela20'] == 3) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela20" value="4" <?php echo (!empty($previous_evaluation['ela20']) && $previous_evaluation['ela20'] == 4) ? 'checked' : ''; ?>></td>
                            <td><input type="radio" name="ela20" value="5" <?php echo (!empty($previous_evaluation['ela20']) && $previous_evaluation['ela20'] == 5) ? 'checked' : ''; ?>></td>
                        </tr>
                        <tr style="background-color: #f0f0f0; font-weight: bold;">
                            <td>Total Score</td>
                            <td colspan="5" style="text-align: center;">
                                <span id="totalScore">
                                    <?php 
                                        if (!empty($previous_evaluation)) {
                                            $total = 0;
                                            for ($i = 1; $i <= 20; $i++) {
                                                $total += (!empty($previous_evaluation['ela' . $i])) ? (int)$previous_evaluation['ela' . $i] : 0;
                                            }
                                            echo $total . ' / 100';
                                        }
                                    ?>
                                </span>
                            </td>
                        </tr>
                    </table>
                    <script>
                        function updateTotal() {
                            let total = 0;
                            for (let i = 1; i <= 20; i++) {
                                let checked = document.querySelector('input[name="ela' + i + '"]:checked');
                                if (checked) {
                                    total += parseInt(checked.value);
                                }
                            }
                            document.getElementById('totalScore').textContent = total + ' / 100';
                        }
                        
                        for (let i = 1; i <= 20; i++) {
                            let radios = document.querySelectorAll('input[name="ela' + i + '"]');
                            radios.forEach(radio => {
                                radio.addEventListener('change', updateTotal);
                            });
                        }
                    </script>
                </div>



                <div class="form-group">
                    <button type="submit" style="font-size: 16px; background-color: green; color: white; padding: 10px 20px; border: none; border-radius: 5px;" name="submit_evaluation">Submit Evaluation</button>
                    <button type="submit" style="font-size: 16px; background-color: green; color: white; padding: 10px 20px; border: none; border-radius: 5px;" name="clear_evaluation">Clear Evaluation</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>