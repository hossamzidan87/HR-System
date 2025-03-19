<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';


// Fetch end_time and 20mins exception from rules table
$role_name = 'close_time';
$end_time_sql = "SELECT end_time, onoff, 20mins FROM rules WHERE name = '$role_name'";
$end_time_result = $conn->query($end_time_sql);

if ($end_time_result->num_rows > 0) {
    $end_time_row = $end_time_result->fetch_assoc();
    $end_time = $end_time_row['end_time'];
    $onoff_status = $end_time_row['onoff']; // Retrieve the onoff status
    $exception_20mins = $end_time_row['20mins']; // Retrieve the 20mins exception time

    $current_time = date('H:i:s');
    $current_day = date('w'); // 0 (Sunday) to 6 (Saturday)

    $new_end_time = date('H:i:s', strtotime($end_time . ' +0 hours'));
    $exception_time = date('H:i:s', strtotime($exception_20mins . ' +20 minutes'));

    // Check if today is Friday (5) or Saturday (6) and onoff is "off"
    if (($current_day == 5 || $current_day == 6) && $onoff_status === 'off') {
        echo "<h3>Overtime submission is closed on Fridays and Saturdays.</h3>";
        echo "<p>Overtime submissions are not allowed on these days.</p>";
        echo "<a href='overtime_home.php'><img width='50' height='50' src='/images/icons/home.png' alt='home'></a>";
        exit(); // Stop further execution
    }

    // Check if current time is past end_time or within the 20mins exception
    if ($current_time > $end_time && $current_time > $exception_time) {
        echo "<h3>Overtime submission is closed for today.</h3>";
        echo "<p>You cannot submit or edit overtime records after $new_end_time.</p>";
        echo "<a href='overtime_home.php'><img width='50' height='50' src='/images/icons/home.png' alt='home'></a>";
        exit(); // Stop further execution
    }
} else {
    // Handle case when no matching rule is found
    echo "<h3>Error: No matching rule found for $role_name.</h3>";
    exit();
}

 $today = date('Y-m-d'); 
 $selected_date = $_POST['date'] ?? $today; 
 $selected_department = $_POST['department'] ?? ''; 

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $selected_date = $_POST['selected_date'] ?? $today;
    $selected_department = $_POST['selected_department'] ?? '';


}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Overtime Management</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Add your CSS and other includes here -->
    <style>
        .exceeded { background-color: #ffcccc; }

        html, body {
        height: 100%;
        }



        table {
            width: 80%;
            margin: 0 auto;
            border-collapse: collapse;
        }

        th {
            background-color: #4CAF50;
            color: white;
            text-align: left;
            padding: 8px;
            border-bottom: 2px solid #000000;
        }

        td {
            padding: 1px;
            text-align: left;
            border-bottom: 2px solid #ddd;
        }

        tr:hover {background-color: #f5f5f5;}

        select, button {
            padding: 8px;
            margin: 5px;
        }
        checkbox

        .header {
            background-color: #f2f2f2;
            text-align: center;
            padding: 10px;
            font-size: 20px;
        }

        .content {
            padding: 20px;
        }

        .footer {
            background-color: #f2f2f2;
            text-align: center;
            padding: 10px;
        }

        input[type="checkbox"] {
  width: 40px;
  height: 40px;
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

    </style>


</head>
<body>
<div class="image-container">
    <div class="image-link">
        <a href="welcome.php"><img src="/images/icons/home.png" alt="home"></a>
    </div>
    <div class="image-link">
        <a href="overtime_home.php"><img src="/images/icons/otre.png" alt="Overtime"></a>
    </div>
    <div class="image-link">
        <a href="logout.php"><img src="/images/icons/logout.png" alt="logout"></a>
    </div>
</div>
    <h2>Overtime Management</h2>
 <form method="POST" action="overtime.php" id="dateDepartmentForm"> 
    <label for="date">Choose Date:</label> 
    <select id="date" name="date" onchange="submitForm()">
        <?php
        $today = date('Y-m-d');
        $next_day = date('Y-m-d', strtotime('+1 day'));
        $day_after_next = date('Y-m-d', strtotime('+2 days'));

        $date_options = [
            $today => date('l, Y-m-d', strtotime($today)),
            $next_day => date('l, Y-m-d', strtotime($next_day)),
            $day_after_next => date('l, Y-m-d', strtotime($day_after_next)),
        ];

        foreach ($date_options as $date_value => $date_label) {
            $selected = isset($_POST['date']) && $_POST['date'] == $date_value ? "selected" : "";
            echo "<option value='$date_value' $selected>$date_label</option>";
        }
        ?>
    </select>
    <label for="department">Choose Department:</label>
    <select id="department" name="department" onchange="submitForm()">
        <option value="">Select a department</option>
        <?php
        include 'db_connection.php';
        session_start();

        // Get the logged-in username
        $username = $_SESSION['username'];

        // Fetch user permissions
        $permissions_sql = "SELECT group_name_1, group_name_2, group_name_3 FROM user_permissions WHERE username='$username'";
        $permissions_result = $conn->query($permissions_sql);
        $user_groups = [];
        if ($permissions_result->num_rows > 0) {
            $user_permissions = $permissions_result->fetch_assoc();
            if ($user_permissions['group_name_1']) $user_groups[] = $user_permissions['group_name_1'];
            if ($user_permissions['group_name_2']) $user_groups[] = $user_permissions['group_name_2'];
            if ($user_permissions['group_name_3']) $user_groups[] = $user_permissions['group_name_3'];
        }

        // Fetch departments based on user groups
        $allowed_departments = [];
        if (!empty($user_groups)) {
            $groups_in = "'" . implode("','", $user_groups) . "'";
             $departments_sql = "SELECT DISTINCT department FROM department_groups WHERE group_name IN ($groups_in) OR group_name1 IN ($groups_in) OR group_name2 IN ($groups_in)";
            $departments_result = $conn->query($departments_sql);
            if ($departments_result->num_rows > 0) {
                while ($row = $departments_result->fetch_assoc()) {
                    $allowed_departments[] = $row['department'];
                    $selected = isset($_POST['department']) && $_POST['department'] == $row['department'] ? "selected" : "";
                    echo "<option value='" . $row['department'] . "' $selected>" . $row['department'] . "</option>";
                }
            }
        }
        ?>
    </select>
</form>
<script> 
    function submitForm() {
     document.getElementById('dateDepartmentForm').submit(); 
 } 
</script>

    <?php
    $selected_date = $_POST['date'] ?? $today; 
    $selected_department = $_POST['department'] ?? ''; 
    if (isset($_POST['department']) && in_array($_POST['department'], $allowed_departments)) {
        $selected_department = $_POST['department'];
        echo '<h3>Selected Department: ' . htmlspecialchars($selected_department) . '</h3>';
    ?>
        <form method="POST" action="process_overtime.php">
            <input type="hidden" name="selected_date" value="<?php echo htmlspecialchars($selected_date); ?>">
            <input type="hidden" name="selected_department" value="<?php echo htmlspecialchars($selected_department); ?>">
            <table border="1">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Bus Line</th>
                        <th>Department</th>
                        <th>Job</th>
                        <th>Remaining H</th>
                        <th>Select</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $today = date('Y-m-d');
                    // Fetch employees based on allowed departments and who haven't been submitted today
                    $employees_sql = "SELECT * FROM employees WHERE department = '$selected_department' AND employee_code NOT IN (SELECT employee_code FROM overtime WHERE overtime_date = '$selected_date')";
                    $employees_result = $conn->query($employees_sql);
                    if ($employees_result->num_rows > 0) {
                        while ($row = $employees_result->fetch_assoc()) {
                            $employee_code = $row['employee_code'];
                            $employee_name = $row['first_name'];
                            $department = $row['department'];
                            $job = $row['job'];

                            // Calculate total overtime hours for the current week
                            // Calculate the start of the week (last Saturday or the same Saturday if today is Saturday)
                            $week_start = date('Y-m-d', strtotime('last Saturday', strtotime($selected_date)));
                            if (date('w', strtotime($selected_date)) == 6) { // If selected date is Saturday
                             $week_start = date('Y-m-d', strtotime($selected_date));
                            }

                            // Calculate the end of the week (next Friday)
                            $week_end = date('Y-m-d', strtotime('next Friday', strtotime($selected_date)));
                            if (date('w', strtotime($selected_date)) == 5) { // If selected date is Friday
                            $week_end = date('Y-m-d', strtotime($selected_date));
                            }

                            $overtime_sql = "SELECT SUM(CASE 
                                                            WHEN DAYOFWEEK(overtime_date) IN (6, 7) AND overtime_date NOT IN (SELECT saturday FROM calendar) THEN 8  
                                                            ELSE 2 
                                                          END) AS total_hours 
                                             FROM overtime 
                                             WHERE employee_code = '$employee_code' 
                                               AND overtime_date BETWEEN '$week_start' AND '$week_end'";
                            $overtime_result = $conn->query($overtime_sql);
                            $total_hours = 0;
                            if ($overtime_result->num_rows > 0) {
                                $overtime_row = $overtime_result->fetch_assoc();
                                $total_hours = $overtime_row['total_hours'];
                            }

                            $remaining_hours = max(-20, 12 - $total_hours);
                            $row_class = ($total_hours >= 12) ? 'exceeded' : '';

                            echo "<tr class='$row_class'>";
                            echo "<td>" . $employee_code . "</td>";
                            echo "<td>" . $employee_name . "</td>";
                            echo "<td>";
                             echo "<select name='bus_line_name[$employee_code]'>"; // Use employee_code as the key
                            $bus_line_sql = "SELECT * FROM bus_lines";
                            $bus_line_result = $conn->query($bus_line_sql);
                            if ($bus_line_result->num_rows > 0) {
                                echo "<option value=''></option>";
                                while ($bus_line_row = $bus_line_result->fetch_assoc()) {
                                    
                                    echo "<option value='" . $bus_line_row['bus_line_name'] . "'>" . $bus_line_row['bus_line_name'] . "</option>";
                                }
                            }
                            echo "</select>";
                            echo "</td>";
                            echo "<td>" . $department . "</td>";
                            echo "<td>" . $job . "</td>";
                            echo "<td>" . $remaining_hours . "</td>";
                            echo "<td><input type='checkbox' name='select_employee[]' value='" . $employee_code . "'></td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='8'>No employees found for this department.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
            <br>
            <button type="submit">Submit</button>
        </form>

        <h3>Submitted Employees for Date <?php echo $selected_date?> </h3>
        <table border="1">
            <thead>
            <tr>
                <th>Code</th>
                <th>Name</th>
                <th>Bus Line</th>
                <th>Department</th>
                <th>Job</th>
                <th>Remaining H</th>
                <th>Action</th> <!-- Add this column header for the delete button -->
            </tr>
        </thead>
        <tbody>
            <?php
            include 'db_connection.php';

                            // Calculate the start of the week (last Saturday or the same Saturday if today is Saturday)
                            $week_start = date('Y-m-d', strtotime('last Saturday', strtotime($selected_date)));
                            if (date('w', strtotime($selected_date)) == 6) { // If selected date is Saturday
                             $week_start = date('Y-m-d', strtotime($selected_date));
                            }

                            // Calculate the end of the week (next Friday)
                            $week_end = date('Y-m-d', strtotime('next Friday', strtotime($selected_date)));
                            if (date('w', strtotime($selected_date)) == 5) { // If selected date is Friday
                            $week_end = date('Y-m-d', strtotime($selected_date));
                            }


$departments_list = '';
if (!empty($user_groups)) {
    $groups_in = "'" . implode("','", $user_groups) . "'";
    $departments_sql = "SELECT DISTINCT department FROM department_groups WHERE group_name IN ($groups_in) OR group_name1 IN ($groups_in) OR group_name2 IN ($groups_in)";
    $departments_result = $conn->query($departments_sql);
    
    $departments_array = [];
    while ($row = $departments_result->fetch_assoc()) {
        $departments_array[] = $row['department'];
    }
    $departments_list = "'" . implode("','", $departments_array) . "'";
}

$submitted_employees_sql = "SELECT t.employee_code, t.employee_name, t.bus_line_name, t.department, t.job,
                                   t.total_hours_today, w.total_hours_week
                            FROM (
                                SELECT employee_code, employee_name, bus_line_name, department, job, 
                                       SUM(CASE 
                                           WHEN DAYOFWEEK(overtime_date) IN (6, 7) AND overtime_date NOT IN (SELECT saturday FROM calendar) THEN 8  
                                           ELSE 2 
                                       END) AS total_hours_today
                                FROM overtime
                                WHERE overtime_date = '$selected_date'
                                AND department IN ($departments_list)
                                GROUP BY employee_code, employee_name, bus_line_name, department, job
                            ) t
                            LEFT JOIN (
                                SELECT employee_code, 
                                       SUM(CASE 
                                           WHEN DAYOFWEEK(overtime_date) IN (6, 7) AND overtime_date NOT IN (SELECT saturday FROM calendar) THEN 8  
                                           ELSE 2 
                                       END) AS total_hours_week
                                FROM overtime
                                WHERE overtime_date BETWEEN '$week_start' AND '$week_end'
                                GROUP BY employee_code
                            ) w ON t.employee_code = w.employee_code";

$submitted_employees_result = $conn->query($submitted_employees_sql);

            if ($submitted_employees_result->num_rows > 0) {
                while ($row = $submitted_employees_result->fetch_assoc()) {
                    $total_hours = $row['total_hours_today'];
                    $remaining_hours = max(-20, 12 - $row['total_hours_week']);
                    $row_class = ($row['total_hours_week'] >= 12) ? 'exceeded' : '';

                    echo "<tr class='$row_class'>";
                    echo "<td>" . $row['employee_code'] . "</td>";
                    echo "<td>" . $row['employee_name'] . "</td>";
                    echo "<td>" . $row['bus_line_name'] . "</td>";
                    echo "<td>" . $row['department'] . "</td>";
                    echo "<td>" . $row['job'] . "</td>";
                    echo "<td>" . $remaining_hours . "</td>";
                    echo "<td><form method='POST' action='delete_overtime.php'> 
                        <input type='hidden' name='employee_code' value='" . $row['employee_code'] . "'> 
                        <input type='hidden' name='overtime_date' value='" . $selected_date . "'>
                        <input type='hidden' name='selected_department' value='" . $selected_department . "'>
                        <button type='submit'>Delete</button>
                        </form></td>"; // Add delete button with form 
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='8'>No employees submitted today.</td></tr>";
            }
            ?>
        </tbody>
    </table>
    <?php
    } else {
        echo "<h3>Please select a department from the list above.</h3>";
    }
    ?>

</body>
</html>