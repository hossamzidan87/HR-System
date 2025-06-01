<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';

// Check if the current time is within the allowed close time range
$current_time = date('Y-m-d H:i:s');
$stmt = $conn->prepare("SELECT shift_start, shift_end FROM rules WHERE name = 'close_time' LIMIT 1");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $shift_start = $row['shift_start'];
        $shift_end = $row['shift_end'];

        if ($current_time < $shift_start || $current_time > $shift_end) {
            echo "<p>This page can only be accessed between $shift_start and $shift_end.</p>";
            exit;
        }
    } else {
        echo "<p>Error: Close time settings not found. Please configure them in the Shifts Cpanel.</p>";
        exit;
    }
    $stmt->close();
} else {
    echo "<p>Error preparing statement: " . $conn->error . "</p>";
    exit;
}

// Fetch allowed departments based on user permissions
$username = $_SESSION['username'];
$user_groups = [];
$permissions_sql = "SELECT group_name_1, group_name_2, group_name_3 FROM user_permissions WHERE username='$username'";
$permissions_result = $conn->query($permissions_sql);
if ($permissions_result->num_rows > 0) {
    $user_permissions = $permissions_result->fetch_assoc();
    foreach (['group_name_1', 'group_name_2', 'group_name_3'] as $group) {
        if (!empty($user_permissions[$group])) {
            $user_groups[] = $user_permissions[$group];
        }
    }
}

$allowed_departments = [];
if (!empty($user_groups)) {
    $groups_in = "'" . implode("','", $user_groups) . "'";
    $departments_sql = "SELECT DISTINCT department FROM department_groups WHERE group_name IN ($groups_in) OR group_name1 IN ($groups_in) OR group_name2 IN ($groups_in)";
    $departments_result = $conn->query($departments_sql);
    while ($row = $departments_result->fetch_assoc()) {
        $allowed_departments[] = $row['department'];
    }
}

// Generate week options
$today = date('Y-m-d');
$start_date = strtotime('next Saturday', strtotime($today)); // Start from the upcoming Saturday
$week_options = [];
for ($i = 1; $i <= 4; $i++) {
    $end_date = strtotime("+6 days", $start_date);
    $week_options[] = [
        'label' => "Week $i: " . date('Y-m-d', $start_date) . " to " . date('Y-m-d', $end_date),
        'start_date' => date('Y-m-d', $start_date),
        'end_date' => date('Y-m-d', $end_date),
    ];
    $start_date = strtotime('+1 week', $start_date);
}

// Generate week options for the "Copy From Date" dropdown
$copy_from_start_date = strtotime('last Saturday', strtotime($today)); // Start from the current week
$copy_from_week_options = [];
for ($i = 0; $i < 4; $i++) { // Generate current week and next 3 weeks
    $copy_from_end_date = strtotime("+6 days", $copy_from_start_date);
    $copy_from_week_options[] = [
        'label' => "Week " . ($i + 0) . ": " . date('Y-m-d', $copy_from_start_date) . " to " . date('Y-m-d', $copy_from_end_date),
        'start_date' => date('Y-m-d', $copy_from_start_date),
        'end_date' => date('Y-m-d', $copy_from_end_date),
    ];
    $copy_from_start_date = strtotime('+1 week', $copy_from_start_date);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_department = $_POST['selected_department'] ?? '';
    $selected_week_range = $_POST['week_range'] ?? '';

    // Ensure week_range is set and extract start_date and end_date
    if (!empty($selected_week_range)) {
        list($start_date, $end_date) = explode('|', $selected_week_range);
    } else {
        $start_date = '';
        $end_date = '';
    }

    if (isset($_POST['select_employee'])) {
        $selected_employees = $_POST['select_employee'];
        $bus_lines = $_POST['bus_line_name'];

        foreach ($selected_employees as $employee_code) {
            $employee_name = $_POST["employee_name_$employee_code"];
            $department = $_POST["department_$employee_code"];
            $job = $_POST["job_$employee_code"];
            $bus_line_name = $bus_lines[$employee_code] ?? '';

            // Check if the employee is already submitted for the selected week
            $check_sql = "SELECT COUNT(*) AS count FROM night_shift 
                          WHERE employee_code = ? AND start_date = ? AND end_date = ?";
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param("sss", $employee_code, $start_date, $end_date);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            if ($row['count'] == 0) {
                // Insert the employee into the night_shift table
                $insert_sql = "INSERT INTO night_shift (employee_code, employee_name, bus_line_name, department, job, processed_by, processed_at, start_date, end_date)
                               VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)";
                $stmt = $conn->prepare($insert_sql);
                $stmt->bind_param("ssssssss", $employee_code, $employee_name, $bus_line_name, $department, $job, $username, $start_date, $end_date);
                $stmt->execute();
                $stmt->close();

                // Insert the employee into the overtime table for 5 days (Sunday to Thursday)
                $current_date = strtotime('Sunday', strtotime($start_date));
                for ($i = 0; $i < 5; $i++) {
                    $overtime_date = date('Y-m-d', $current_date);
                    $overtime_sql = "INSERT INTO overtime (employee_code, employee_name, department, job, bus_line_name, processed_by, processed_at, overtime_date, types)
                                     VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, 'night')";
                    $stmt = $conn->prepare($overtime_sql);
                    $stmt->bind_param("sssssss", $employee_code, $employee_name, $department, $job, $bus_line_name, $username, $overtime_date);
                    $stmt->execute();
                    $stmt->close();

                    $current_date = strtotime('+1 day', $current_date);
                }
            } else {
                echo "<p>Employee $employee_name (Code: $employee_code) is already submitted for the selected week.</p>";
            }
        }
        
    }
}

// Handle deletion of employees
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_employee'])) {
    $employee_code = $_POST['employee_code'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';

    if (!empty($employee_code) && !empty($start_date) && !empty($end_date)) {
        // Delete from night_shift table
        $delete_sql = "DELETE FROM night_shift WHERE employee_code = ? AND start_date = ? AND end_date = ?";
        $stmt = $conn->prepare($delete_sql);
        if ($stmt) {
            $stmt->bind_param("sss", $employee_code, $start_date, $end_date);
            $stmt->execute();
            $stmt->close();
        }

        // Delete 5 days of overtime entries
        $current_date = strtotime('Sunday', strtotime($start_date));
        for ($i = 0; $i < 5; $i++) {
            $overtime_date = date('Y-m-d', $current_date);
            $delete_overtime_sql = "DELETE FROM overtime WHERE employee_code = ? AND overtime_date = ?";
            $stmt = $conn->prepare($delete_overtime_sql);
            if ($stmt) {
                $stmt->bind_param("ss", $employee_code, $overtime_date);
                $stmt->execute();
                $stmt->close();
            }
            $current_date = strtotime('+1 day', $current_date);
        }
    } else {
        echo "<p>Invalid input. Please try again.</p>";
    }
}

// Handle copying employees from another date
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['copy_from'])) {
    $copy_from_start_date = $_POST['copy_from_date'] ?? '';
    $copy_to_week_range = $_POST['week_range'] ?? '';
    $selected_department = $_POST['selected_department'] ?? '';

    if (!empty($copy_from_start_date) && !empty($copy_to_week_range)) {
        list($copy_to_start_date, $copy_to_end_date) = explode('|', $copy_to_week_range);

        if ($selected_department === 'all') {
            // Copy employees for all allowed departments
            $copy_sql = "INSERT INTO night_shift (employee_code, employee_name, bus_line_name, department, job, processed_by, processed_at, start_date, end_date)
                         SELECT employee_code, employee_name, bus_line_name, department, job, ?, NOW(), ?, ?
                         FROM night_shift
                         WHERE start_date = ?
                         AND department IN ('" . implode("','", $allowed_departments) . "')
                         AND employee_code NOT IN (
                             SELECT employee_code 
                             FROM night_shift 
                             WHERE start_date = ? AND end_date = ? AND department IN ('" . implode("','", $allowed_departments) . "')
                         )";
            $stmt = $conn->prepare($copy_sql);
            if ($stmt) {
                $stmt->bind_param("ssssss", $username, $copy_to_start_date, $copy_to_end_date, $copy_from_start_date, $copy_to_start_date, $copy_to_end_date);
                $stmt->execute();
                $stmt->close();

                // Insert into overtime table for 5 days
                $current_date = strtotime('Sunday', strtotime($copy_to_start_date));
                for ($i = 0; $i < 5; $i++) {
                    $overtime_date = date('Y-m-d', $current_date);
                    $overtime_sql = "INSERT INTO overtime (employee_code, employee_name, department, job, bus_line_name, processed_by, processed_at, overtime_date, types)
                                     SELECT employee_code, employee_name, department, job, bus_line_name, ?, NOW(), ?, 'night'
                                     FROM night_shift
                                     WHERE start_date = ? AND department IN ('" . implode("','", $allowed_departments) . "')
                                     AND employee_code NOT IN (
                                         SELECT employee_code 
                                         FROM overtime 
                                         WHERE overtime_date = ? AND types = 'night'
                                     )";
                    $stmt = $conn->prepare($overtime_sql);
                    if ($stmt) {
                        $stmt->bind_param("ssss", $username, $overtime_date, $copy_to_start_date, $overtime_date);
                        $stmt->execute();
                        $stmt->close();
                    }
                    $current_date = strtotime('+1 day', $current_date);
                }
            }
        } else {
            // Copy employees for the selected department
            $copy_sql = "INSERT INTO night_shift (employee_code, employee_name, bus_line_name, department, job, processed_by, processed_at, start_date, end_date)
                         SELECT employee_code, employee_name, bus_line_name, department, job, ?, NOW(), ?, ?
                         FROM night_shift
                         WHERE start_date = ? AND department = ?
                         AND employee_code NOT IN (
                             SELECT employee_code 
                             FROM night_shift 
                             WHERE start_date = ? AND end_date = ? AND department = ?
                         )";
            $stmt = $conn->prepare($copy_sql);
            if ($stmt) {
                $stmt->bind_param("ssssssss", $username, $copy_to_start_date, $copy_to_end_date, $copy_from_start_date, $selected_department, $copy_to_start_date, $copy_to_end_date,$selected_department);
                $stmt->execute();
                $stmt->close();

                // Insert into overtime table for 5 days
                $current_date = strtotime('Sunday', strtotime($copy_to_start_date));
                for ($i = 0; $i < 5; $i++) {
                    $overtime_date = date('Y-m-d', $current_date);
                    $overtime_sql = "INSERT INTO overtime (employee_code, employee_name, department, job, bus_line_name, processed_by, processed_at, overtime_date, types)
                                     SELECT employee_code, employee_name, department, job, bus_line_name, ?, NOW(), ?, 'night'
                                     FROM night_shift
                                     WHERE start_date = ? AND department = ?
                                     AND employee_code NOT IN (
                                         SELECT employee_code 
                                         FROM overtime 
                                         WHERE overtime_date = ? AND types = 'night'
                                     )";
                    $stmt = $conn->prepare($overtime_sql);
                    if ($stmt) {
                        $stmt->bind_param("sssss", $username, $overtime_date, $copy_to_start_date, $selected_department, $overtime_date);
                        $stmt->execute();
                        $stmt->close();
                    }
                    $current_date = strtotime('+1 day', $current_date);
                }
            }
        }
    } else {
        echo "<p>Invalid input. Please try again.</p>";
    }
}

// Handle deletion of all employees
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_all'])) {
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';

    if (!empty($start_date) && !empty($end_date)) {
        // Delete from night_shift table
        $delete_all_sql = "DELETE FROM night_shift WHERE start_date = ? AND end_date = ?";
        $stmt = $conn->prepare($delete_all_sql);
        if ($stmt) {
            $stmt->bind_param("ss", $start_date, $end_date);
            $stmt->execute();
            $stmt->close();
        }

        // Delete from overtime table for the specified week (Sunday to Thursday)
        $current_date = strtotime('Sunday', strtotime($start_date));
        for ($i = 0; $i < 5; $i++) {
            $overtime_date = date('Y-m-d', $current_date);
            $delete_overtime_sql = "DELETE FROM overtime WHERE overtime_date = ?";
            $stmt = $conn->prepare($delete_overtime_sql);
            if ($stmt) {
                $stmt->bind_param("s", $overtime_date);
                $stmt->execute();
                $stmt->close();
            }
            $current_date = strtotime('+1 day', $current_date);
        }

        echo "<p>All employees and related overtime entries successfully deleted for the selected week.</p>";
    } else {
        echo "<p>Invalid input. Please try again.</p>";
    }
}

// Retain selected values
$selected_department = $_POST['selected_department'] ?? '';
$selected_week_range = $_POST['week_range'] ?? '';
if (!empty($selected_week_range)) {
    list($start_date, $end_date) = explode('|', $selected_week_range);
} else {
    $start_date = '';
    $end_date = '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Night Shift Management</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        table { width: 80%; margin: 0 auto; border-collapse: collapse; }
        th, td { padding: 2px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #4CAF50; color: white; }
        tr:hover { background-color: #f5f5f5; }
        .form-group { margin: 10px 0; }
        .button { padding: 10px 20px; background-color: #007BFF; color: white; border: none; border-radius: 5px; cursor: pointer; }
        .button:hover { background-color: #0056b3; }
        .image-container { display: flex; justify-content: flex-end; align-items: center; }
        .image-link { border: 1px solid #ddd; border-radius: 4px; padding: 5px; width: 25px; margin: 0 5px; }
        .image-link img { width: 100%; height: auto; display: block; }
        select, button {
            padding: 5px;
            margin: 5px;
        }
        input[type="checkbox"] {
  width: 30px;
  height: 30px;
  accent-color: green;
}

        input[type="checkbox"] {
  appearance: none;
  -webkit-appearance: none;
  display: flex;
  align-content: center;
  justify-content: center;
  font-size: 2rem;
  padding: 0.1rem;
  border: 0.25rem solid green;
  border-radius: 0.5rem;
}
input[type="checkbox"]::before {
  content: "";
  width: 1.4rem;
  height: 1.4rem;
  clip-path: polygon(20% 0%, 0% 20%, 30% 50%, 0% 80%, 20% 100%, 50% 70%, 80% 100%, 100% 80%, 70% 50%, 100% 20%, 80% 0%, 50% 30%);
  transform: scale(0);
  background-color: green;
}
input[type="checkbox"]:checked::before {
  transform: scale(1);
}
input[type="checkbox"]:hover {
  color: black;
}
    </style>
    <script>
        function autoLoadEmployees() {
            document.getElementById('filterForm').submit();
        }
    </script>
</head>
<body>
<div class="image-container">
    <div class="image-link">
        <a href="welcome.php"><img src="/images/icons/home.png" alt="home"></a>
    </div>
    <div class="image-link">
        <a href="shifts_home.php"><img src="/images/icons/night.png" alt="home"></a>
    </div>
    <div class="image-link">
        <a href="logout.php"><img src="/images/icons/logout.png" alt="logout"></a>
    </div>
</div>

<h2>Night Shift Management</h2>
<form method="POST" action="night_shift.php" id="filterForm">
    <div class="form-group">
        <label for="department">Choose Department:</label>
        <select id="department" name="selected_department" onchange="autoLoadEmployees()" required>
            <option value="">Select a department</option>
            <option value="all" <?php echo ($selected_department === 'all') ? 'selected' : ''; ?>>All Departments</option>
            <?php foreach ($allowed_departments as $department): ?>
                <option value="<?php echo htmlspecialchars($department); ?>" <?php echo ($selected_department === $department) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($department); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="week_range">Choose Week Range:</label>
        <select id="week_range" name="week_range" onchange="autoLoadEmployees()" required>
            <option value="">Select a week range</option>
            <?php foreach ($week_options as $option): ?>
                <option value="<?php echo $option['start_date'] . '|' . $option['end_date']; ?>" <?php echo ($selected_week_range === $option['start_date'] . '|' . $option['end_date']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($option['label']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="copy_from_date">Copy From Date:</label>
        <select id="copy_from_date" name="copy_from_date" required>
            <option value="">Select a date to copy from</option>
            <?php foreach ($copy_from_week_options as $option): ?>
                <option value="<?php echo $option['start_date']; ?>">
                    <?php echo htmlspecialchars($option['label']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="button" name="copy_from">Copy From</button>
    </div>
</form>

<?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && $start_date && $end_date): ?>
    <?php
    // Fetch employees eligible for submission
    $employees_sql = ($selected_department === 'all') 
        ? "SELECT employee_code, first_name, department, job FROM employees WHERE department IN ('" . implode("','", $allowed_departments) . "') 
           AND employee_code NOT IN (SELECT employee_code FROM night_shift WHERE start_date = '$start_date' AND end_date = '$end_date')"
        : "SELECT employee_code, first_name, department, job FROM employees WHERE department = '$selected_department' 
           AND employee_code NOT IN (SELECT employee_code FROM night_shift WHERE start_date = '$start_date' AND end_date = '$end_date')";
    $employees_result = $conn->query($employees_sql);
    ?>
    <form method="POST" action="night_shift.php">
        <input type="hidden" name="selected_department" value="<?php echo htmlspecialchars($selected_department); ?>">
        <input type="hidden" name="week_range" value="<?php echo htmlspecialchars($selected_week_range); ?>">
        <table border="1">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Job</th>
                    <th>Bus Line</th>
                    <th>Select</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $employees_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['employee_code']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($row['first_name']); ?>
                            <input type="hidden" name="employee_name_<?php echo $row['employee_code']; ?>" value="<?php echo htmlspecialchars($row['first_name']); ?>">
                        </td>
                        <td>
                            <?php echo htmlspecialchars($row['department']); ?>
                            <input type="hidden" name="department_<?php echo $row['employee_code']; ?>" value="<?php echo htmlspecialchars($row['department']); ?>">
                        </td>
                        <td>
                            <?php echo htmlspecialchars($row['job']); ?>
                            <input type="hidden" name="job_<?php echo $row['employee_code']; ?>" value="<?php echo htmlspecialchars($row['job']); ?>">
                        </td>
                        <td>
                            <select name="bus_line_name[<?php echo $row['employee_code']; ?>]">
                                <option value="">Select Bus Line</option>
                                <?php
                                $bus_lines_sql = "SELECT bus_line_name FROM bus_lines";
                                $bus_lines_result = $conn->query($bus_lines_sql);
                                while ($bus_line = $bus_lines_result->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($bus_line['bus_line_name']) . "'>" . htmlspecialchars($bus_line['bus_line_name']) . "</option>";
                                }
                                ?>
                            </select>
                        </td>
                        <td><input type="checkbox" name="select_employee[]" value="<?php echo $row['employee_code']; ?>"></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <button type="submit" class="button">Submit Night Shift</button>
    </form>

    <h3>Submitted Employees for Selected Week</h3>
    <?php
    // Fetch all submitted employees for the selected date range and permitted departments
    $submitted_sql = ($selected_department === 'all') 
        ? "SELECT employee_code, employee_name, bus_line_name, department, job, start_date, end_date 
           FROM night_shift 
           WHERE department IN ('" . implode("','", $allowed_departments) . "') 
           AND start_date = '$start_date' AND end_date = '$end_date'"
        : "SELECT employee_code, employee_name, bus_line_name, department, job, start_date, end_date 
           FROM night_shift 
           WHERE department = '$selected_department' 
           AND start_date = '$start_date' AND end_date = '$end_date'";
    $submitted_result = $conn->query($submitted_sql);
    ?>
    <h3>Total Submitted Employees: <?php echo $submitted_result->num_rows; ?></h3>
    <table border="1">
        <thead>
            <tr>
                <th>Code</th>
                <th>Name</th>
                <th>Bus Line</th>
                <th>Department</th>
                <th>Job</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $submitted_result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['employee_code']); ?></td>
                    <td><?php echo htmlspecialchars($row['employee_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['bus_line_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['department']); ?></td>
                    <td><?php echo htmlspecialchars($row['job']); ?></td>
                    <td><?php echo htmlspecialchars($row['start_date']); ?></td>
                    <td><?php echo htmlspecialchars($row['end_date']); ?></td>
                    <td>
                        <form method="POST" action="night_shift.php">
                            <input type="hidden" name="employee_code" value="<?php echo htmlspecialchars($row['employee_code']); ?>">
                            <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($row['start_date']); ?>">
                            <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($row['end_date']); ?>">
                            <input type="hidden" name="selected_department" value="<?php echo htmlspecialchars($selected_department); ?>">
                            <input type="hidden" name="week_range" value="<?php echo htmlspecialchars($selected_week_range); ?>">
                            <input type="hidden" name="delete_employee" value="1">
                            <button type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <form method="POST" action="night_shift.php">
        <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
        <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
        <input type="hidden" name="delete_all" value="1">
        <input type="hidden" name="selected_department" value="<?php echo htmlspecialchars($selected_department); ?>">
        <input type="hidden" name="week_range" value="<?php echo htmlspecialchars($selected_week_range); ?>">
        <button type="submit" class="button" style="margin-top: 10px;">Delete All</button>
    </form>
<?php endif; ?>
</body>
</html>
