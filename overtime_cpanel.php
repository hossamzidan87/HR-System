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
    $selected_date = $_POST['selected_date'];
    $employees_to_delete = isset($_POST['employees_to_delete']) ? $_POST['employees_to_delete'] : [];

    if (!empty($employees_to_delete)) {
        foreach ($employees_to_delete as $employee_code) {
            $delete_sql = "DELETE FROM overtime WHERE employee_code = '$employee_code' AND overtime_date = '$selected_date'";
            if ($conn->query($delete_sql) !== TRUE) {
                echo "Error deleting employee code $employee_code: " . $conn->error;
            }
        }
        echo "Selected employees deleted successfully!";
    } else {
        echo "No employees selected for deletion.";
    }
}

// Handle form submission to submit employees into overtime
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rule_name']) && $_POST['rule_name'] === 'submit_employees') {
    $selected_date = $_POST['selected_date'];
    $employee_codes = isset($_POST['employee_codes']) ? $_POST['employee_codes'] : [];

    if (!empty($employee_codes)) {
        foreach ($employee_codes as $employee_code) {
            $employee_sql = "SELECT employee_code, first_name, department, job FROM employees WHERE employee_code = '$employee_code'";
            $employee_result = $conn->query($employee_sql);
            if ($employee_result->num_rows > 0) {
                $employee = $employee_result->fetch_assoc();
                $insert_sql = "INSERT INTO overtime (employee_code, employee_name, department, job, bus_line_name, processed_by, processed_at, overtime_date)
                               VALUES ('{$employee['employee_code']}', '{$employee['first_name']}', '{$employee['department']}', '{$employee['job']}', '', 'Admin', NOW(), '$selected_date')";
                if ($conn->query($insert_sql) !== TRUE) {
                    echo "Error adding employee code $employee_code: " . $conn->error;
                }
            } else {
                echo "Employee code $employee_code not found.";
            }
        }
        echo "Selected employees added successfully!";
    } else {
        echo "No employee codes entered.";
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

// Handle form submission to add Saturday dates to the calendar
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rule_name']) && $_POST['rule_name'] === 'add_saturday') {
    $selected_date = $_POST['selected_date'];
    $day_of_week = date('l', strtotime($selected_date));

    if ($day_of_week === 'Saturday') {
        // Check if the Saturday date already exists
        $check_sql = "SELECT * FROM calendar WHERE saturday = '$selected_date'";
        $check_result = $conn->query($check_sql);

        if ($check_result->num_rows > 0) {
            echo "Error: The selected Saturday date has already been added.";
        } else {
            $insert_sql = "INSERT INTO calendar (saturday) VALUES ('$selected_date')";
            if ($conn->query($insert_sql) === TRUE) {
                echo "Saturday date added successfully!";
            } else {
                echo "Error: " . $conn->error;
            }
        }
    } else {
        echo "Error: The selected date is not a Saturday.";
    }
}

// Handle form submission to delete Saturday dates from the calendar
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rule_name']) && $_POST['rule_name'] === 'delete_saturday') {
    $selected_date = $_POST['selected_date'];

    $delete_sql = "DELETE FROM calendar WHERE saturday = '$selected_date'";
    if ($conn->query($delete_sql) === TRUE) {
        echo "Saturday date deleted successfully!";
    } else {
        echo "Error: " . $conn->error;
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
        function showRuleForm() {
            var ruleName = document.getElementById("ruleSelect").value;
            var forms = document.getElementsByClassName("rule-form");
            for (var i = 0; i < forms.length; i++) {
                forms[i].style.display = "none";
            }
            if (ruleName === "register") {
                window.location.href = "register.php";
            } else if (ruleName !== "") {
                document.getElementById(ruleName + "Form").style.display = "block";
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
    </script>
        <style>
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
        .container {
            width: 80%;
            margin: 0 auto;
            text-align: center;
            padding: 50px;
        }
        .container select{
            width: 20%;
            margin: 0 auto;
            text-align: center;
            padding: 8px;
        }
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

    </style>
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
    <h2>Control Panel</h2>
    <label for="ruleSelect">Select Rule:</label>
    <select id="ruleSelect" onchange="showRuleForm()">
        <option value="">Select a rule</option>
        <option value="close_time">Change Close Time</option>
        <option value="delete_employees">Delete Submitted Employees</option>
        <option value="submit_employees">Submit Employees</option>
        <option value="update_employees">Update Employees</option>
        <option value="update_bus_lines">Update Bus Lines</option>
        <option value="add_saturday">Add Excepted Saturdays</option>
    </select>

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

    <div id="update_employeesForm" class="rule-form" style="display:none;">
        <h3>Update Employees Table</h3>
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
        <h3>Add of Saturdays except to the 8 hours</h3>
        <form method="POST" action="overtime_cpanel.php">
            <input type="hidden" name="rule_name" value="add_saturday">
            <div class="form-group">
                <label for="selected_date">Select Date:</label>
                <input type="date" id="selected_date" name="selected_date" required>
            </div>
            <button type="submit">Add</button>
        </form>

        <h3>Delete Excepted Saturdays</h3>
        <form method="POST" action="overtime_cpanel.php">
            <input type="hidden" name="rule_name" value="delete_saturday">
            <div class="form-group">
                <label for="selected_date">Select Date:</label>
                <select id="selected_date" name="selected_date" required>
                    <option value="">Select a Saturday</option>
                    <?php
                    $saturdays_sql = "SELECT saturday FROM calendar";
                    $saturdays_result = $conn->query($saturdays_sql);
                    if ($saturdays_result->num_rows > 0) {
                        while ($row = $saturdays_result->fetch_assoc()) {
                            echo "<option value='" . $row['saturday'] . "'>" . $row['saturday'] . "</option>";
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
</div>
</body>
</html>
