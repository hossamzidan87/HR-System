<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';
require 'vendor/autoload.php'; // Ensure PHPExcel is installed via Composer

// Check if the logged-in user is 'admin'
if (!isset($_SESSION['username']) || $_SESSION['username'] !== 'admin') {
    echo "Access Denied. You do not have permission to view this page.";
    exit;
}

// Initialize variables
$selected_quarter = '';
$eva_start = '';
$eva_end = '';
$show_not_evaluated = false;

// Fetch the current quarter from the "rules" table
$stmt = $conn->prepare("SELECT quarter FROM rules WHERE name = 'eva_q' LIMIT 1");
if (!$stmt) {
    die("Database error: " . $conn->error);
}
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $selected_quarter = $row['quarter'];
}
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['quarter']) && isset($_POST['year'])) {
        $selected_quarter = filter_input(INPUT_POST, 'quarter', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $selected_year = filter_input(INPUT_POST, 'year', FILTER_SANITIZE_NUMBER_INT);

        // Update the "rules" table with the selected quarter and year
        $stmt = $conn->prepare("UPDATE rules SET quarter = ?, year = ? WHERE name = 'eva_q'");
        if (!$stmt) {
            die("Database error: " . $conn->error);
        }
        $stmt->bind_param("si", $selected_quarter, $selected_year);
        if ($stmt->execute()) {
            echo "Quarter and Year updated successfully!";
        } else {
            echo "Error: " . $stmt->error;
        }
        $stmt->close();
    } elseif (isset($_POST['eva_start']) && isset($_POST['eva_end']) && isset($_POST['defined_rule'])) {
        $eva_start = filter_input(INPUT_POST, 'eva_start', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $eva_end = filter_input(INPUT_POST, 'eva_end', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $defined_rule = filter_input(INPUT_POST, 'defined_rule', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        // Update the "rules" table with the defined rule
        $stmt = $conn->prepare("UPDATE rules SET eva_start = ?, eva_end = ? WHERE defined_rule = ?");
        if (!$stmt) {
            die("Database error: " . $conn->error);
        }
        $stmt->bind_param("sss", $eva_start, $eva_end, $defined_rule);
        if ($stmt->execute()) {
            echo "Rule updated successfully!";
        } else {
            echo "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle form submission to update employees table
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rule_name']) && $_POST['rule_name'] === 'update_employees') {
    if (isset($_FILES['employee_file']) && $_FILES['employee_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['employee_file']['tmp_name'];
        $file_name = $_FILES['employee_file']['name'];

        $inputFileType = \PhpOffice\PhpSpreadsheet\IOFactory::identify($file_tmp);
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
        $spreadsheet = $reader->load($file_tmp);
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray();

        // Check if the first row contains the expected number of columns
        if (count($data[0]) < 6) {
            echo "Error: The uploaded file does not contain the 6 expected number of columns.";
            exit;
        }

        // Erase existing data
        $delete_sql = "TRUNCATE TABLE eva_list";
        $conn->query($delete_sql);

        // Insert new data
        foreach ($data as $row) {
            if (!empty($row[0])) {
                $employee_code = $row[0];
                $first_name = $row[1];
                $department = $row[2];
                $job = $row[3];
                $employment_date = $row[4];
                $gender = $row[5];

                $insert_sql = "INSERT INTO eva_list (employee_code, first_name, department, job, employment_date, gender)
                               VALUES ('$employee_code', '$first_name', '$department', '$job', '$employment_date', '$gender')";
                if ($conn->query($insert_sql) !== TRUE) {
                    echo "Error adding employee: " . $conn->error;
                }
            }
        }
        echo "Employees table updated successfully!";
    } else {
        echo "Error uploading file. Please try again.";
    }
}

// Handle form submission to show employees not evaluated
$not_evaluated = [];
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rule_name']) && $_POST['rule_name'] === 'not_evaluated') {
    $show_not_evaluated = true;
    $stmt = $conn->prepare("SELECT department, COUNT(*) as total_employees FROM eva_list WHERE employee_code NOT IN (SELECT employee_code FROM evaluations WHERE quarter = ?) GROUP BY department");
    if (!$stmt) {
        die("Database error: " . $conn->error);
    }
    $stmt->bind_param("s", $selected_quarter);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $not_evaluated[] = $row;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluation Cpanel</title>
    <style>
        .container {
            width: 80%;
            margin: 0 auto;
            text-align: center;
            padding: 50px;
        }
        .form-group {
            margin: 20px 0;
        }
        .form-group label {
            font-size: 20px;
            margin-right: 10px;
        }
        .form-group select, .form-group input {
            padding: 10px;
            font-size: 16px;
        }
        .form-group button {
            padding: 10px 20px;
            font-size: 16px;
            background-color: #007BFF;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .form-group button:hover {
            background-color: #0056b3;
        }
        .hidden {
            display: none;
        }
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
    </style>
    <script>
        function showRuleForm() {
            var selectedRule = document.getElementById('ruleSelect').value;
            var forms = document.getElementsByClassName('rule-form');
            for (var i = 0; i < forms.length; i++) {
                forms[i].classList.add('hidden');
            }
            if (selectedRule) {
                document.getElementById(selectedRule + 'Form').classList.remove('hidden');
                if (selectedRule === 'not_evaluated') {
                    document.getElementById('not_evaluatedForm').submit();
                }
            }
        }
    </script>
</head>
<body>
<div class="image-container">
    <div class="image-link">
        <a href="welcome.php"><img src="/images/icons/home.png" alt="home"></a>
    </div>
    <div class="image-link">
        <a href="cpanel.php"><img src="/images/icons/cpanel.png" alt="Cpanel"></a>
    </div>
    <div class="image-link">
        <a href="logout.php"><img src="/images/icons/logout.png" alt="logout"></a>
    </div>
</div>
    <div class="container">
        <h1>Evaluation Cpanel</h1>
        <div class="form-group">
            <label for="ruleSelect">Select Rule:</label>
            <select id="ruleSelect" onchange="showRuleForm()">
                <option value="">Select a rule</option>
                <option value="quarter">Change Quarter And Year</option>
                <option value="defined_rule">Set Start and End Time</option>
                <option value="update_employees">Update Employees List</option>
                <option value="not_evaluated">Show Not Evaluated Employees</option>
                <!-- Add more options for other rules here -->
            </select>
        </div>
        <form id="quarterForm" class="rule-form hidden" method="POST" action="evaluation_cpanel.php">
            <div class="form-group">
                <label for="quarter">Choose Quarter:</label>
                <select id="quarter" name="quarter" required>
                    <option value="March" <?php echo ($selected_quarter == 'March') ? 'selected' : ''; ?>>March</option>
                    <option value="June" <?php echo ($selected_quarter == 'June') ? 'selected' : ''; ?>>June</option>
                    <option value="September" <?php echo ($selected_quarter == 'September') ? 'selected' : ''; ?>>September</option>
                </select>
            </div>
            <div class="form-group">
                <label for="year">Choose Year:</label>
                <select id="year" name="year" required>
                    <?php
                    $current_year = date("Y");
                    for ($year = 2025; $year <= $current_year; $year++) {
                        echo "<option value=\"$year\" " . (($selected_year == $year) ? 'selected' : '') . ">$year</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <button type="submit">Update Quarter and Year</button>
            </div>
        </form>
        <form id="defined_ruleForm" class="rule-form hidden" method="POST" action="evaluation_cpanel.php">
            <input type="hidden" name="defined_rule" value="<?php echo $selected_quarter; ?>">
            <div style="font-size: 24px; font-weight: bold;">Quarter: <?php echo $selected_quarter; ?></div>
            <div class="form-group">
                <label for="eva_start">Start Time:</label>
                <input type="datetime-local" id="eva_start" name="eva_start" value="<?php echo getRuleValue($selected_quarter, 'eva_start'); ?>" required>
            </div>
            <div class="form-group">
                <label for="eva_end">End Time:</label>
                <input type="datetime-local" id="eva_end" name="eva_end" value="<?php echo getRuleValue($selected_quarter, 'eva_end'); ?>" required>
            </div>
            <div class="form-group">
                <button type="submit">Update Rule</button>
            </div>
        </form>
        <div id="update_employeesForm" class="rule-form hidden">
            <h3>Update Employee Evaluation List</h3>
            <h4>Code, Name, Department, Job, Starting Date and Gender</h4>
            <form method="POST" action="evaluation_cpanel.php" enctype="multipart/form-data">
                <input type="hidden" name="rule_name" value="update_employees">
                <div class="form-group">
                    <label for="employee_file">Upload Excel File:</label>
                    <input type="file" id="employee_file" name="employee_file" accept=".xlsx,.xls" required>
                </div>
                <button type="submit">Update</button>
            </form>
        </div>
        <form id="not_evaluatedForm" class="rule-form hidden" method="POST" action="evaluation_cpanel.php">
            <input type="hidden" name="rule_name" value="not_evaluated">
        </form>
        <?php if ($show_not_evaluated): ?>
            <?php if (!empty($not_evaluated)): ?>
            <div id="not_evaluatedResults" class="rule-form <?php echo $show_not_evaluated ? '' : 'hidden'; ?>">
                <h3>Employees Not Evaluated</h3>
                <table>
                    <tr>
                        <th>Department</th>
                        <th>Number of Employees</th>
                        <th>Number of Employees Not Evaluated</th>
                    </tr>
                    <?php 
                    $total_not_evaluated = 0;
                    $total_employees_all = 0;
                    foreach ($not_evaluated as $row): 
                        $total_not_evaluated += $row['total_employees'];
                        // Fetch the total number of employees in each department
                        $stmt = $conn->prepare("SELECT COUNT(*) as total_employees FROM eva_list WHERE department = ?");
                        $stmt->bind_param("s", $row['department']);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $total_employees = $result->fetch_assoc()['total_employees'];
                        $total_employees_all += $total_employees;
                        $stmt->close();
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['department']); ?></td>
                        <td><?php echo htmlspecialchars($total_employees); ?></td>
                        <td><?php echo htmlspecialchars($row['total_employees']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td><strong>Total</strong></td>
                        <td><strong><?php echo htmlspecialchars($total_employees_all); ?></strong></td>
                        <td><strong><?php echo htmlspecialchars($total_not_evaluated); ?></strong></td>
                    </tr>
                </table>
            </div>
            <?php else: ?>
            <p>No data available.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
    <?php
    function getRuleValue($quarter, $field) {
        global $conn;
        $stmt = $conn->prepare("SELECT $field FROM rules WHERE defined_rule = ? LIMIT 1");
        if (!$stmt) {
            die("Database error: " . $conn->error);
        }
        $stmt->bind_param("s", $quarter);
        $stmt->execute();
        $result = $stmt->get_result();
        $value = '';
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $value = $row[$field];
        }
        $stmt->close();
        return htmlspecialchars($value);
    }
    ?>
</body>
</html>