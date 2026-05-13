<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';
include 'includes/app_helpers.php';
require 'vendor/autoload.php'; // Ensure PHPExcel is installed via Composer

// Check if the logged-in user is 'admin'
if (!isset($_SESSION['username']) || $_SESSION['username'] !== 'admin') {
    echo "Access Denied. You do not have permission to view this page.";
    exit;
}

// Initialize variables
$selected_rule = '';
$selected_quarter = '';
$selected_year = (int) date('Y');
$eva_start = '';
$eva_end = '';
$show_not_evaluated = false;

// Fetch the current quarter from the "rules" table
$stmt = $conn->prepare("SELECT quarter, year FROM rules WHERE name = 'eva_q' LIMIT 1");
if (!$stmt) {
    die("Database error: " . $conn->error);
}
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $selected_quarter = $row['quarter'];
    $selected_year = (int) $row['year'];
}
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['quarter']) && isset($_POST['year'])) {
        $selected_rule = 'quarter';
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
        $selected_rule = 'defined_rule';
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
    // handle annual start/end update where rules.name = 'eva_y'
    elseif (isset($_POST['rule_name']) && $_POST['rule_name'] === 'annual_time' && isset($_POST['eva_start']) && isset($_POST['eva_end'])) {
        $selected_rule = 'annual';
        $eva_start = filter_input(INPUT_POST, 'eva_start', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $eva_end   = filter_input(INPUT_POST, 'eva_end', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        $stmt = $conn->prepare("UPDATE rules SET eva_start = ?, eva_end = ? WHERE name = 'eva_y'");
        if (!$stmt) { die("Database error: " . $conn->error); }
        $stmt->bind_param("ss", $eva_start, $eva_end);
        if ($stmt->execute()) {
            echo "Annual evaluation start and end updated successfully!";
        } else {
            echo "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle form submission to update employees table
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rule_name']) && $_POST['rule_name'] === 'update_employees') {
    $selected_rule = 'update_employees';
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
    $selected_rule = 'not_evaluated';
    $show_not_evaluated = true;
    $stmt = $conn->prepare("SELECT department, COUNT(*) as total_employees FROM eva_list WHERE employee_code NOT IN (SELECT employee_code FROM evaluations WHERE quarter = ? AND year = ?) GROUP BY department");
    if (!$stmt) {
        die("Database error: " . $conn->error);
    }
    $stmt->bind_param("si", $selected_quarter, $selected_year);
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
            display: none !important;
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
            cursor: pointer;
        }
        .panel-tabbar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 10px 0 18px;
        }
        .panel-tab {
            border: 1px solid #cfd7df;
            background: #f3f6fa;
            color: #1f2d3d;
            border-radius: 8px;
            padding: 10px 14px;
            font-weight: 600;
            cursor: pointer;
        }
        .panel-tab.active {
            background: #0a4d8c;
            color: #fff;
            border-color: #0a4d8c;
        }
    </style>
    <script>
        function showRuleForm(selectedRuleValue, shouldSubmit) {
            var selectedRule = selectedRuleValue || '';
            var forms = document.getElementsByClassName('rule-form');
            var tabs = document.getElementsByClassName('panel-tab');
            for (var i = 0; i < forms.length; i++) {
                forms[i].classList.add('is-hidden');
            }
            for (var j = 0; j < tabs.length; j++) {
                tabs[j].classList.remove('active');
            }
            var notEvaluatedResults = document.getElementById('not_evaluatedResults');
            if (notEvaluatedResults) {
                notEvaluatedResults.classList.add('is-hidden');
            }
            if (selectedRule) {
                var selectedForm = document.getElementById(selectedRule + 'Form');
                if (selectedForm) {
                    selectedForm.classList.remove('is-hidden');
                }
                if (selectedRule === 'not_evaluated' && notEvaluatedResults) {
                    notEvaluatedResults.classList.remove('is-hidden');
                }
                var activeTab = document.querySelector('.panel-tab[data-target="' + selectedRule + '"]');
                if (activeTab) {
                    activeTab.classList.add('active');
                }
                if (selectedRule === 'not_evaluated' && shouldSubmit) {
                    document.getElementById('not_evaluatedForm').submit();
                }
            }
        }

        function sortTable(tableId, columnIndex) {
            const table = document.getElementById(tableId);
            const rows = Array.from(table.rows).slice(1); // Exclude header row
            const isAscending = table.getAttribute('data-sort-order') === 'asc';
            const direction = isAscending ? 1 : -1;

            rows.sort((a, b) => {
                const cellA = a.cells[columnIndex].innerText.trim();
                const cellB = b.cells[columnIndex].innerText.trim();
                return cellA.localeCompare(cellB, undefined, { numeric: true }) * direction;
            });

            rows.forEach(row => table.tBodies[0].appendChild(row));
            table.setAttribute('data-sort-order', isAscending ? 'desc' : 'asc');
        }

        document.addEventListener('DOMContentLoaded', function () {
            var initialRule = document.body.getAttribute('data-active-rule') || '';
            if (initialRule) {
                showRuleForm(initialRule, false);
            }
        });
    </script>
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="icon" type="image/png" href="images/logo.png">
</head>
<body data-active-rule="<?php echo htmlspecialchars($selected_rule); ?>">
<?php
app_render_page_header('EC', 'Evaluation Cpanel', 'Manage evaluation periods and rules.', [
    ['label' => 'Home', 'href' => 'welcome.php'],
    ['label' => 'Cpanel', 'href' => 'cpanel.php'],
    ['label' => 'Logout', 'href' => 'logout.php'],
]);
app_render_page_hero('Admin area', 'Manage quarterly and annual evaluations.', 'Select an action from the control tabs below.', [
    ['title' => 'Current Quarter', 'text' => $selected_quarter ?: 'Not set'],
    ['title' => 'Current Year', 'text' => (string) $selected_year],
]);
app_open_content_panel('Evaluation Control Panel', 'Use the tabs below to configure each evaluation rule set.');
?>
        <div class="panel-tabbar">
            <button type="button" class="panel-tab" data-target="quarter" onclick="showRuleForm('quarter', false)">Change Quarter And Year</button>
            <button type="button" class="panel-tab" data-target="defined_rule" onclick="showRuleForm('defined_rule', false)">Set Start and End Time</button>
            <button type="button" class="panel-tab" data-target="annual" onclick="showRuleForm('annual', false)">Annual Start And End</button>
            <button type="button" class="panel-tab" data-target="update_employees" onclick="showRuleForm('update_employees', false)">Update Employees List</button>
            <button type="button" class="panel-tab" data-target="not_evaluated" onclick="showRuleForm('not_evaluated', true)">Show Not Evaluated Employees</button>
        </div>
        <form id="quarterForm" class="rule-form is-hidden" method="POST" action="evaluation_cpanel.php">
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
        <form id="defined_ruleForm" class="rule-form is-hidden" method="POST" action="evaluation_cpanel.php">
            <input type="hidden" name="defined_rule" value="<?php echo $selected_quarter; ?>">
            <div style="font-size: 24px; font-weight: bold;">Quarter: <?php echo $selected_quarter; ?></div>
            <div class="form-group">
                <label for="eva_start">Start Time:</label>
                <input type="datetime-local" id="eva_start" name="eva_start" value="<?php echo getRuleDateTimeValue($selected_quarter, 'eva_start'); ?>" required>
            </div>
            <div class="form-group">
                <label for="eva_end">End Time:</label>
                <input type="datetime-local" id="eva_end" name="eva_end" value="<?php echo getRuleDateTimeValue($selected_quarter, 'eva_end'); ?>" required>
            </div>
            <div class="form-group">
                <button type="submit">Update Rule</button>
            </div>
        </form>
        <div id="update_employeesForm" class="rule-form is-hidden">
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
        <form id="not_evaluatedForm" class="rule-form is-hidden" method="POST" action="evaluation_cpanel.php">
            <input type="hidden" name="rule_name" value="not_evaluated">
        </form>
        <!-- Annual Start/End form -->
        <form id="annualForm" class="rule-form is-hidden" method="POST" action="evaluation_cpanel.php">
            <input type="hidden" name="rule_name" value="annual_time">
            <div style="font-size: 24px; font-weight: bold;">Annual Evaluation Date & Time</div>
            <div class="form-group">
                <label for="annual_eva_start">Start Time:</label>
                <input type="datetime-local" id="annual_eva_start" name="eva_start" value="<?php $v = getRuleValueByName('eva_y','eva_start'); echo $v ? htmlspecialchars(date('Y-m-d\TH:i', strtotime($v))) : ''; ?>" required>
            </div>
            <div class="form-group">
                <label for="annual_eva_end">End Time:</label>
                <input type="datetime-local" id="annual_eva_end" name="eva_end" value="<?php $v = getRuleValueByName('eva_y','eva_end'); echo $v ? htmlspecialchars(date('Y-m-d\TH:i', strtotime($v))) : ''; ?>" required>
            </div>
            <div class="form-group">
                <button type="submit">Update Annual Start/End</button>
            </div>
        </form>
        <?php if ($show_not_evaluated): ?>
            <?php if (!empty($not_evaluated)): ?>
            <div id="not_evaluatedResults" class="rule-form <?php echo $show_not_evaluated ? '' : 'is-hidden'; ?>">
                <h3>Employees Not Evaluated</h3>
                <table id="notEvaluatedTable" data-sort-order="asc">
                    <tr>
                        <th onclick="sortTable('notEvaluatedTable', 0)">Department</th>
                        <th onclick="sortTable('notEvaluatedTable', 1)">Number of Employees</th>
                        <th onclick="sortTable('notEvaluatedTable', 2)">Number of Employees Not Evaluated</th>
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
                        <!-- New: Detailed list of not evaluated employees per department -->
        <?php foreach ($not_evaluated as $row): ?>
            <h4>Not Evaluated Employees in <?php echo htmlspecialchars($row['department']); ?></h4>
            <table>
                <tr>
                    <th>Employee Code</th>
                    <th>Name</th>
                    <th>Job</th>
                    <th>Employment Date</th>
                    <th>Gender</th>
                </tr>
                <?php
                $stmt = $conn->prepare(
                    "SELECT employee_code, first_name, job, employment_date, gender 
                     FROM eva_list 
                     WHERE department = ? 
                       AND employee_code NOT IN (
                           SELECT employee_code FROM evaluations WHERE quarter = ? AND year = ?
                       )"
                );
                $stmt->bind_param("ssi", $row['department'], $selected_quarter, $selected_year);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($emp = $result->fetch_assoc()):
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($emp['employee_code']); ?></td>
                    <td><?php echo htmlspecialchars($emp['first_name']); ?></td>
                    <td><?php echo htmlspecialchars($emp['job']); ?></td>
                    <td><?php echo htmlspecialchars($emp['employment_date']); ?></td>
                    <td><?php echo htmlspecialchars($emp['gender']); ?></td>
                </tr>
                <?php endwhile; $stmt->close(); ?>
            </table>
        <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p>No data available.</p>
            <?php endif; ?>
        <?php endif; ?>
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

    function getRuleDateTimeValue($quarter, $field) {
        $value = getRuleValue($quarter, $field);
        if ($value === '') {
            return '';
        }

        $timestamp = strtotime(htmlspecialchars_decode($value, ENT_QUOTES));
        return $timestamp ? htmlspecialchars(date('Y-m-d\TH:i', $timestamp)) : '';
    }

    // helper to get rule field by rules.name
    function getRuleValueByName($name, $field) {
        global $conn;
        $stmt = $conn->prepare("SELECT $field FROM rules WHERE name = ? LIMIT 1");
        if (!$stmt) { return ''; }
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $result = $stmt->get_result();
        $value = '';
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $value = $row[$field];
        }
        $stmt->close();
        return $value;
    }
?>
<?php
app_close_content_panel();
app_render_page_end();
?>
<script src="assets/js/app.js"></script>
</body>
</html>
