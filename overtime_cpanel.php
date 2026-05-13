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

// Fetch end_time from roles table
$role_name = 'close_time';
$end_time_sql = "SELECT end_time, onoff FROM rules WHERE name = '$role_name'";
$end_time_result = $conn->query($end_time_sql);

if ($end_time_result->num_rows > 0) {
    $end_time_row = $end_time_result->fetch_assoc();
    $end_time = $end_time_row['end_time'];
    $onoff = $end_time_row['onoff'];
}

// Handle form submission to update the close time
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rule_name']) && $_POST['rule_name'] === 'close_time') {
    $close_time = $_POST['close_time'];
    $onoff = $_POST['onoff'];
    $colose20 = date('Y-m-d H:i:s', strtotime($close_time));
    $colose20 = date('Y-m-d H:i:s', strtotime($colose20 . ' -20 minutes'));
    $update_sql = "UPDATE rules SET 20mins = '$colose20', end_time = '$close_time', onoff = '$onoff' WHERE name = 'close_time'";
    if ($conn->query($update_sql) === TRUE) {
        echo "Close time updated successfully!";
    } else {
        echo "Error: " . $conn->error;
    }
}

// Handle form submission to update the 20mins column
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_20mins'])) {
    $current_time = date('Y-m-d H:i:s');
    $update_sql = "UPDATE rules SET 20mins = '$current_time' WHERE name = 'close_time'";
    if ($conn->query($update_sql) === TRUE) {
        echo "20mins column updated successfully!";
    } else {
        echo "Error: " . $conn->error;
    }
}

// Handle form submission to delete employees
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rule_name']) && $_POST['rule_name'] === 'delete_employees') {
    $selected_date = isset($_POST['selected_date']) ? $conn->real_escape_string($_POST['selected_date']) : '';
    $employees_to_delete = isset($_POST['employees_to_delete']) ? array_filter($_POST['employees_to_delete'], 'strlen') : [];

    if (!empty($employees_to_delete) && $selected_date !== '') {
        // sanitize and quote values for a single DELETE query
        $escaped = array_map(function($code) use ($conn) {
            return "'" . $conn->real_escape_string(trim($code)) . "'";
        }, $employees_to_delete);
        $in_clause = implode(',', $escaped);

        // run one DELETE instead of many individual queries
        $conn->begin_transaction();
        $delete_sql = "DELETE FROM overtime
                       WHERE employee_code IN ($in_clause)
                         AND overtime_date = '$selected_date'
                         AND types = 'normal'";
        if ($conn->query($delete_sql) === TRUE) {
            $deleted_rows = $conn->affected_rows;
            $conn->commit();
            echo "Deleted $deleted_rows employee(s) successfully!";
        } else {
            $conn->rollback();
            echo "Error deleting employees: " . $conn->error;
        }
    } else {
        echo "No employees selected for deletion or invalid date.";
    }
}

// Handle form submission to submit employees into overtime (individual)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rule_name']) && $_POST['rule_name'] === 'submit_employees') {
    $selected_date = $_POST['selected_date'];
    $employee_codes = isset($_POST['employee_codes']) ? $_POST['employee_codes'] : [];

    if (!empty($employee_codes)) {
        foreach ($employee_codes as $employee_code) {
            // Check if the employee is already submitted for the selected date
            $check_sql = "SELECT * FROM overtime WHERE employee_code = '$employee_code' AND overtime_date = '$selected_date' AND types = 'normal'";
            $check_result = $conn->query($check_sql);

            if ($check_result->num_rows == 0) {
                $employee_sql = "SELECT employee_code, first_name, department, job FROM employees WHERE employee_code = '$employee_code'";
                $employee_result = $conn->query($employee_sql);
                if ($employee_result->num_rows > 0) {
                    $employee = $employee_result->fetch_assoc();
                    $insert_sql = "INSERT INTO overtime (employee_code, employee_name, department, job, bus_line_name, processed_by, processed_at, overtime_date, types)
                                   VALUES ('{$employee['employee_code']}', '{$employee['first_name']}', '{$employee['department']}', '{$employee['job']}', '', 'Admin', NOW(), '$selected_date', 'normal')";
                    if ($conn->query($insert_sql) !== TRUE) {
                        echo "Error adding employee code $employee_code: " . $conn->error;
                    }
                } else {
                    echo "Employee code $employee_code not found.<br>";
                }
            } else {
                echo "Employee code $employee_code is already submitted for the selected date.<br>";
            }
        }
        echo "Selected employees added successfully!<br>";
    } else {
        echo "No employee codes entered.";
    }
}

// Handle form submission to submit employees via Excel
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rule_name']) && $_POST['rule_name'] === 'submit_employees_excel') {
    if (isset($_FILES['overtime_file']) && $_FILES['overtime_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['overtime_file']['tmp_name'];
        $selected_date = $_POST['selected_date_excel'];

        try {
            $inputFileType = \PhpOffice\PhpSpreadsheet\IOFactory::identify($file_tmp);
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
            $spreadsheet = $reader->load($file_tmp);
            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray();

            $added_count = 0;
            $duplicate_count = 0;
            $not_found_count = 0;

            foreach ($data as $row) {
                if (!empty($row[0])) {
                    $employee_code = trim($row[0]);

                    // Check if already submitted
                    $check_sql = "SELECT * FROM overtime WHERE employee_code = ? AND overtime_date = ? AND types = 'normal'";
                    $stmt = $conn->prepare($check_sql);
                    $stmt->bind_param("ss", $employee_code, $selected_date);
                    $stmt->execute();
                    $check_result = $stmt->get_result();

                    if ($check_result->num_rows == 0) {
                        // Fetch employee details
                        $employee_sql = "SELECT employee_code, first_name, department, job FROM employees WHERE employee_code = ?";
                        $emp_stmt = $conn->prepare($employee_sql);
                        $emp_stmt->bind_param("s", $employee_code);
                        $emp_stmt->execute();
                        $employee_result = $emp_stmt->get_result();

                        if ($employee_result->num_rows > 0) {
                            $employee = $employee_result->fetch_assoc();
                            $insert_sql = "INSERT INTO overtime (employee_code, employee_name, department, job, bus_line_name, processed_by, processed_at, overtime_date, types)
                                           VALUES (?, ?, ?, ?, '', 'Admin', NOW(), ?, 'normal')";
                            $insert_stmt = $conn->prepare($insert_sql);
                            $insert_stmt->bind_param("sssss", $employee['employee_code'], $employee['first_name'], $employee['department'], $employee['job'], $selected_date);
                            if ($insert_stmt->execute()) {
                                $added_count++;
                            }
                            $insert_stmt->close();
                        } else {
                            $not_found_count++;
                        }
                        $emp_stmt->close();
                    } else {
                        $duplicate_count++;
                    }
                    $stmt->close();
                }
            }

            echo "Excel upload completed!<br>";
            echo "Added: $added_count | Duplicates: $duplicate_count | Not Found: $not_found_count<br>";
        } catch (Exception $e) {
            echo "Error processing file: " . $e->getMessage();
        }
    } else {
        echo "Error uploading file. Please try again.";
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
        $delete_sql = "TRUNCATE TABLE employees";
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

                $insert_sql = "INSERT INTO employees (employee_code, first_name, department, job, employment_date, gender)
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

// Handle form submission to update bus lines
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rule_name']) && $_POST['rule_name'] === 'update_bus_lines') {
    if (isset($_POST['new_bus_line']) && !empty($_POST['new_bus_line'])) {
        $new_bus_line = $_POST['new_bus_line'];
        $insert_sql = "INSERT INTO bus_lines (bus_line_name) VALUES ('$new_bus_line')";
        if ($conn->query($insert_sql) === TRUE) {
            echo "Bus line added successfully!";
        } else {
            echo "Error: " . $conn->error;
        }
    }

    if (isset($_POST['bus_lines_to_delete']) && !empty($_POST['bus_lines_to_delete'])) {
        $bus_lines_to_delete = $_POST['bus_lines_to_delete'];
        foreach ($bus_lines_to_delete as $bus_line) {
            $delete_sql = "DELETE FROM bus_lines WHERE bus_line_name = '$bus_line'";
            if ($conn->query($delete_sql) !== TRUE) {
                echo "Error deleting bus line $bus_line: " . $conn->error;
            }
        }
    }
}

// Handle form submission to add Saturdays or holidays
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rule_name']) && ($_POST['rule_name'] === 'add_saturday' || $_POST['rule_name'] === 'add_holiday')) {
    $selected_date = $_POST['selected_date'] ?? $_POST['holiday_date'];
    $type = ($_POST['rule_name'] === 'add_saturday') ? 'saturday' : 'holiday';

    $check_sql = "SELECT * FROM calendar WHERE days = '$selected_date' AND type = '$type'";
    $check_result = $conn->query($check_sql);

    if ($check_result->num_rows > 0) {
        echo "Error: The selected date has already been added as a $type.";
    } else {
        $insert_sql = "INSERT INTO calendar (days, type) VALUES ('$selected_date', '$type')";
        if ($conn->query($insert_sql) === TRUE) {
            ucfirst($type) . " date added successfully!";
        } else {
            echo "Error: " . $conn->error;
        }
    }
}

// Handle form submission to delete Saturdays or holidays
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rule_name']) && ($_POST['rule_name'] === 'delete_saturday' || $_POST['rule_name'] === 'delete_holiday')) {
    $selected_date = $_POST['selected_date'] ?? $_POST['holiday_date'];
    $type = ($_POST['rule_name'] === 'delete_saturday') ? 'saturday' : 'holiday';

    $delete_sql = "DELETE FROM calendar WHERE days = '$selected_date' AND type = '$type'";
    if ($conn->query($delete_sql) === TRUE) {
        ucfirst($type) . " date deleted successfully!";
    } else {
        echo "Error: " . $conn->error;
    }
}

$active_rule = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_20mins'])) {
        $active_rule = 'close_time';
    } elseif (isset($_POST['rule_name'])) {
        $rule_name = $_POST['rule_name'];
        if ($rule_name === 'delete_saturday') {
            $active_rule = 'add_saturday';
        } elseif ($rule_name === 'delete_holiday') {
            $active_rule = 'add_holiday';
        } else {
            $active_rule = $rule_name;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Overtime Control Panel</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        .form-group {
            margin: 10px 0;
        }
    </style>
    <script>
        function showRuleForm(ruleName) {
            var forms = document.getElementsByClassName("rule-form");
            var tabs = document.getElementsByClassName("panel-tab");
            for (var i = 0; i < forms.length; i++) {
                forms[i].style.display = "none";
            }
            for (var j = 0; j < tabs.length; j++) {
                tabs[j].classList.remove("active");
            }
            if (ruleName !== "") {
                document.getElementById(ruleName + "Form").style.display = "block";
                var tabButton = document.querySelector('.panel-tab[data-target="' + ruleName + '"]');
                if (tabButton) {
                    tabButton.classList.add('active');
                }
            }
        }

        function fetchEmployees() {
            var selectedDate = document.getElementById("delete_date").value;
            if (selectedDate) {
                var xhr = new XMLHttpRequest();
                xhr.onreadystatechange = function() {
                    if (xhr.readyState == 4 && xhr.status == 200) {
                        document.getElementById("employeeList").innerHTML = xhr.responseText;
                    }
                };
                xhr.open("GET", "fetch_employees.php?date=" + selectedDate, true);
                xhr.send();
            } else {
                document.getElementById("employeeList").innerHTML = "";
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            var initialRule = document.body.getAttribute('data-active-rule') || '';
            if (initialRule) {
                showRuleForm(initialRule);
            }
        });
    </script>
    <style>
        .rule-form {
            margin: 16px 0;
        }
        .rule-form label {
            font-size: 16px;
            margin-right: 10px;
        }
        .rule-form select, .form-group input {
            padding: 10px;
            font-size: 14px;
        }
        .rule-form button {
            padding: 10px 20px;
            font-size: 14px;
            background-color: #007BFF;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .rule-form button:hover {
            background-color: #0056b3;
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
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="icon" type="image/png" href="images/logo.png">
</head>
<body data-active-rule="<?php echo htmlspecialchars($active_rule); ?>">
<?php
app_render_page_header('OC', 'Overtime Cpanel', 'Manage overtime rules and submitted employees.', [
    ['label' => 'Home', 'href' => 'welcome.php'],
    ['label' => 'Cpanel', 'href' => 'cpanel.php'],
    ['label' => 'Logout', 'href' => 'logout.php'],
]);
app_render_page_hero('Admin area', 'Manage overtime settings and employee lists.', 'Use the select menu to choose an option to modify.', [
    ['title' => 'Close Time', 'text' => $end_time ? date('H:i', strtotime($end_time)) : 'Not set'],
    ['title' => 'Weekend', 'text' => $onoff === 'on' ? 'On' : 'Off'],
]);
app_open_content_panel('Overtime Control Panel', 'Select an option from the dropdown below.');
?>
    <div class="panel-tabbar">
        <button type="button" class="panel-tab" data-target="close_time" onclick="showRuleForm('close_time')">Change Close Time</button>
        <button type="button" class="panel-tab" data-target="delete_employees" onclick="showRuleForm('delete_employees')">Delete Submitted Employees</button>
        <button type="button" class="panel-tab" data-target="submit_employees" onclick="showRuleForm('submit_employees')">Submit Employees (Individual)</button>
        <button type="button" class="panel-tab" data-target="submit_employees_excel" onclick="showRuleForm('submit_employees_excel')">Submit Employees (Excel)</button>
        <button type="button" class="panel-tab" data-target="update_employees" onclick="showRuleForm('update_employees')">Update Employees</button>
        <button type="button" class="panel-tab" data-target="update_bus_lines" onclick="showRuleForm('update_bus_lines')">Update Bus Lines</button>
        <button type="button" class="panel-tab" data-target="add_saturday" onclick="showRuleForm('add_saturday')">Add Excepted Saturdays</button>
        <button type="button" class="panel-tab" data-target="add_holiday" onclick="showRuleForm('add_holiday')">Manage Holidays</button>
    </div>

<div id="close_timeForm" class="rule-form" style="display:none;">
    <h3>Change Close Time</h3>
    <form method="POST" action="overtime_cpanel.php">
        <input type="hidden" name="update_20mins" value="1">
        <button type="submit">20 Minutes Exception</button>
        </form>
    <form method="POST" action="overtime_cpanel.php">
        <input type="hidden" name="rule_name" value="close_time">
        <div class="form-group">
            <label for="close_time">Close Time (HH:MM): <?php echo date('H:i', strtotime($end_time)); ?> </label>
            <input type="time" id="close_time" name="close_time" required>
        </div>
        <div class="form-group">
            <label for="onoff">Weekend On/Off:</label>
            <input type="radio" id="on" name="onoff" value="on" <?php echo ($onoff === 'on') ? 'checked' : ''; ?> required>
            <label for="on">On</label>
            <input type="radio" id="off" name="onoff" value="off" <?php echo ($onoff === 'off') ? 'checked' : ''; ?> required>
            <label for="off">Off</label>
        </div>
        <button type="submit">Update</button>
    </form>
</div>


    <div id="delete_employeesForm" class="rule-form" style="display:none;">
        <h3>Delete Submitted Employees</h3>
        <form method="POST" action="overtime_cpanel.php">
            <input type="hidden" name="rule_name" value="delete_employees">
            <div class="form-group">
                <label for="delete_date">Select Date:</label>
                <input type="date" id="delete_date" name="selected_date" required onchange="fetchEmployees()">
            </div>
            <div class="form-group">
                <label for="employees_to_delete">Select Employees:</label>
                <div id="employeeList"></div> <!-- Employees will be loaded here -->
            </div>
            <button type="submit">Delete</button>
        </form>
    </div>
    <script>
function toggle(source) {
    var checkboxes = document.querySelectorAll('input[type="checkbox"]');
    for (var i = 0; i < checkboxes.length; i++) {
        if (checkboxes[i] != source)
            checkboxes[i].checked = source.checked;
    }
}
    </script>

    <div id="submit_employeesForm" class="rule-form" style="display:none;">
        <h3>Submit Employees into Overtime</h3>
        <form method="POST" action="overtime_cpanel.php">
            <input type="hidden" name="rule_name" value="submit_employees">
            <div class="form-group">
                <label for="submit_date">Select Date:</label>
                <input type="date" id="submit_date" name="selected_date" required>
            </div>
            <div class="form-group">
                <label for="employee_codes">Enter Employee Codes:</label>
                <div id="employee_codes_group">
                    <?php for ($i = 0; $i < 10; $i++): ?>
                        <input type="text" name="employee_codes[]" placeholder="Employee Code"><br>
                    <?php endfor; ?>
                </div>
            </div>
            <button type="submit">Submit</button>
        </form>
    </div>

    <div id="submit_employees_excelForm" class="rule-form" style="display:none;">
        <h3>Submit Employees into Overtime (Excel)</h3>
        <form method="POST" action="overtime_cpanel.php" enctype="multipart/form-data">
            <input type="hidden" name="rule_name" value="submit_employees_excel">
            <div class="form-group">
                <label for="selected_date_excel">Select Date:</label>
                <input type="date" id="selected_date_excel" name="selected_date_excel" required>
            </div>
            <div class="form-group">
                <label for="overtime_file">Upload Excel File (Employee Codes in Column A):</label>
                <input type="file" id="overtime_file" name="overtime_file" accept=".xlsx,.xls" required>
            </div>
            <button type="submit">Submit</button>
        </form>
    </div>

    <div id="update_employeesForm" class="rule-form" style="display:none;">
        <h3>Update Employees Table</h3>
        <h4>Code, Name, Department, Job, Starting Date and Gender</h4>
        <form method="POST" action="overtime_cpanel.php" enctype="multipart/form-data">
            <input type="hidden" name="rule_name" value="update_employees">
            <div class="form-group">
                <label for="employee_file">Upload Excel File:</label>
                <input type="file" id="employee_file" name="employee_file" accept=".xlsx,.xls" required>
            </div>
            <button type="submit">Update</button>
        </form>
    </div>

    <div id="update_bus_linesForm" class="rule-form" style="display:none;">
        <h3>Update Bus Lines</h3>
        <form method="POST" action="overtime_cpanel.php">
            <input type="hidden" name="rule_name" value="update_bus_lines">
            <div class="form-group">
                <label for="current_bus_lines">Current Bus Lines:</label>
                <div id="bus_lines_list" style="display: flex; justify-content: center;">
                    <table>
                        <tr>
                            <th>Select</th>
                            <th>Bus Line Name</th>
                        </tr>
                        <?php
                        $bus_lines_sql = "SELECT bus_line_name FROM bus_lines";
                        $bus_lines_result = $conn->query($bus_lines_sql);
                        if ($bus_lines_result->num_rows > 0) {
                            while ($row = $bus_lines_result->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td><input type='checkbox' name='bus_lines_to_delete[]' value='" . $row['bus_line_name'] . "'></td>";
                                echo "<td>" . $row['bus_line_name'] . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='2'>No bus lines found.</td></tr>";
                        }
                        ?>
                    </table>
                </div>
            </div>
            <div class="form-group">
                <label for="new_bus_line">New Bus Line:</label>
                <input type="text" id="new_bus_line" name="new_bus_line">
            </div>
            <button type="submit">Update</button>
            <button type="submit">Delete</button>
        </form>
    </div>

    <div id="add_saturdayForm" class="rule-form" style="display:none;">
        <h3>Add Saturdays</h3>
        <form method="POST" action="overtime_cpanel.php">
            <input type="hidden" name="rule_name" value="add_saturday">
            <div class="form-group">
                <label for="selected_date">Select Date:</label>
                <input type="date" id="selected_date" name="selected_date" required>
            </div>
            <button type="submit">Add</button>
        </form>

        <h3>Delete Saturdays</h3>
        <form method="POST" action="overtime_cpanel.php">
            <input type="hidden" name="rule_name" value="delete_saturday">
            <div class="form-group">
                <label for="selected_date">Select Date:</label>
                <select id="selected_date" name="selected_date" required>
                    <option value="">Select a Saturday</option>
                    <?php
                    $saturdays_sql = "SELECT days FROM calendar WHERE type = 'saturday'";
                    $saturdays_result = $conn->query($saturdays_sql);
                    if ($saturdays_result->num_rows > 0) {
                        while ($row = $saturdays_result->fetch_assoc()) {
                            echo "<option value='" . $row['days'] . "'>" . $row['days'] . "</option>";
                        }
                    } else {
                        echo "<option value=''>No Saturdays found</option>";
                    }
                    ?>
                </select>
            </div>
            <button type="submit">Delete</button>
        </form>
    </div>

    <div id="add_holidayForm" class="rule-form" style="display:none;">
        <h3>Add Holidays</h3>
        <form method="POST" action="overtime_cpanel.php">
            <input type="hidden" name="rule_name" value="add_holiday">
            <div class="form-group">
                <label for="holiday_date">Select Date:</label>
                <input type="date" id="holiday_date" name="holiday_date" required>
            </div>
            <button type="submit">Add</button>
        </form>

        <h3>Delete Holidays</h3>
        <form method="POST" action="overtime_cpanel.php">
            <input type="hidden" name="rule_name" value="delete_holiday">
            <div class="form-group">
                <label for="holiday_date">Select Date:</label>
                <select id="holiday_date" name="holiday_date" required>
                    <option value="">Select a Holiday</option>
                    <?php
                    $holidays_sql = "SELECT days FROM calendar WHERE type = 'holiday'";
                    $holidays_result = $conn->query($holidays_sql);
                    if ($holidays_result->num_rows > 0) {
                        while ($row = $holidays_result->fetch_assoc()) {
                            echo "<option value='" . $row['days'] . "'>" . $row['days'] . "</option>";
                        }
                    } else {
                        echo "<option value=''>No Holidays found</option>";
                    }
                    ?>
                </select>
            </div>
            <button type="submit">Delete</button>
        </form>
    </div>
<?php
app_close_content_panel();
app_render_page_end();
?>
<script src="assets/js/app.js"></script>
</body>
</html>
