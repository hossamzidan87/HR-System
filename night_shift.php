<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';
include 'includes/app_helpers.php';

function night_shift_render_state_page(string $title, string $message, array $cards = []): void
{
    app_render_state_page('NS', 'Night Shift Management', 'Weekly planning workspace', 'Night shift status', $title, $message, [
        ['label' => 'Home', 'href' => 'welcome.php'],
        ['label' => 'Night Shift', 'href' => 'shifts_home.php'],
        ['label' => 'Logout', 'href' => 'logout.php'],
    ], array_merge([
        ['title' => 'Workspace', 'text' => 'Night shift planning'],
        ['title' => 'Action', 'text' => 'Check the planning window below'],
    ], $cards), [
        ['label' => 'Back To Shifts Home', 'href' => 'shifts_home.php'],
        ['label' => 'Dashboard', 'href' => 'welcome.php'],
    ], 'Planner Status', 'This night-shift workspace is temporarily unavailable, but you can still navigate to related sections.');
}

$current_time = date('Y-m-d H:i:s');
$stmt = $conn->prepare("SELECT shift_start, shift_end FROM rules WHERE name = 'close_time' LIMIT 1");
if (!$stmt) {
    night_shift_render_state_page('Configuration Error', 'The close-time rule could not be loaded.', [
        ['title' => 'Rule Name', 'text' => 'close_time'],
        ['title' => 'Action', 'text' => 'Review shift settings in the control panel'],
    ]);
}
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $stmt->close();
    night_shift_render_state_page('Configuration Missing', 'Close-time settings were not found in the rules table.', [
        ['title' => 'Rule Name', 'text' => 'close_time'],
        ['title' => 'Action', 'text' => 'Add or repair the planning window rule'],
    ]);
}
$window = $result->fetch_assoc();
$stmt->close();
$shift_start = $window['shift_start'];
$shift_end = $window['shift_end'];

if ($current_time < $shift_start || $current_time > $shift_end) {
    night_shift_render_state_page('Planner Closed', 'Night shift planning is available only between ' . $shift_start . ' and ' . $shift_end . '.', [
        ['title' => 'Planning Window', 'text' => $shift_start . ' to ' . $shift_end],
        ['title' => 'Module', 'text' => 'Weekly night shift planner'],
    ]);
}

$username = $_SESSION['username'];
$flash_messages = [];

$user_groups = [];
$permissions_sql = "SELECT group_name_1, group_name_2, group_name_3 FROM user_permissions WHERE username = ?";
$stmt = $conn->prepare($permissions_sql);
if ($stmt) {
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $permissions_result = $stmt->get_result();
    if ($permissions_result->num_rows > 0) {
        $user_permissions = $permissions_result->fetch_assoc();
        foreach (['group_name_1', 'group_name_2', 'group_name_3'] as $group_key) {
            if (!empty($user_permissions[$group_key])) {
                $user_groups[] = $user_permissions[$group_key];
            }
        }
    }
    $stmt->close();
}

$allowed_departments = [];
if (!empty($user_groups)) {
    $escaped_groups = array_map([$conn, 'real_escape_string'], $user_groups);
    $groups_in = "'" . implode("','", $escaped_groups) . "'";
    $departments_sql = "SELECT DISTINCT department FROM department_groups WHERE group_name IN ($groups_in) OR group_name1 IN ($groups_in) OR group_name2 IN ($groups_in) ORDER BY department";
    $departments_result = $conn->query($departments_sql);
    if ($departments_result) {
        while ($row = $departments_result->fetch_assoc()) {
            $allowed_departments[] = $row['department'];
        }
    }
}

$today = date('Y-m-d');
$start_date_seed = strtotime('next Saturday', strtotime($today));
$week_options = [];
for ($i = 1; $i <= 4; $i++) {
    $week_start = date('Y-m-d', $start_date_seed);
    $week_end = date('Y-m-d', strtotime('+6 days', $start_date_seed));
    $week_options[] = [
        'label' => 'Week ' . $i . ': ' . $week_start . ' to ' . $week_end,
        'start_date' => $week_start,
        'end_date' => $week_end,
    ];
    $start_date_seed = strtotime('+1 week', $start_date_seed);
}

$copy_seed = strtotime('last Saturday', strtotime($today));
$copy_from_week_options = [];
for ($i = 0; $i < 4; $i++) {
    $week_start = date('Y-m-d', $copy_seed);
    $week_end = date('Y-m-d', strtotime('+6 days', $copy_seed));
    $copy_from_week_options[] = [
        'label' => 'Week ' . $i . ': ' . $week_start . ' to ' . $week_end,
        'start_date' => $week_start,
        'end_date' => $week_end,
    ];
    $copy_seed = strtotime('+1 week', $copy_seed);
}

$bus_lines = [];
$bus_result = $conn->query("SELECT bus_line_name FROM bus_lines ORDER BY bus_line_name");
if ($bus_result) {
    while ($row = $bus_result->fetch_assoc()) {
        $bus_lines[] = $row['bus_line_name'];
    }
}

$selected_department = trim((string) ($_POST['selected_department'] ?? ''));
$selected_week_range = trim((string) ($_POST['week_range'] ?? ''));
$copy_from_date = trim((string) ($_POST['copy_from_date'] ?? ''));
$start_date = '';
$end_date = '';
if ($selected_week_range !== '' && strpos($selected_week_range, '|') !== false) {
    [$start_date, $end_date] = explode('|', $selected_week_range, 2);
}
$department_is_valid = $selected_department !== '' && ($selected_department === 'all' || in_array($selected_department, $allowed_departments, true));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_employee'])) {
    $selected_employees = $_POST['select_employee'];
    $bus_line_names = $_POST['bus_line_name'] ?? [];

    if (!$department_is_valid || $start_date === '' || $end_date === '') {
        $flash_messages[] = 'Choose a valid department and week before submitting employees.';
    } elseif (!is_array($selected_employees) || empty($selected_employees)) {
        $flash_messages[] = 'Select at least one employee to submit a night shift.';
    } else {
        $submitted_count = 0;
        $skipped_count = 0;

        foreach ($selected_employees as $employee_code) {
            $employee_code = trim((string) $employee_code);
            if ($employee_code === '') {
                continue;
            }

            $employee_name = trim((string) ($_POST['employee_name_' . $employee_code] ?? ''));
            $department = trim((string) ($_POST['department_' . $employee_code] ?? ''));
            $job = trim((string) ($_POST['job_' . $employee_code] ?? ''));
            $bus_line_name = trim((string) ($bus_line_names[$employee_code] ?? ''));

            $check_sql = 'SELECT COUNT(*) AS count FROM night_shift WHERE employee_code = ? AND start_date = ? AND end_date = ?';
            $stmt = $conn->prepare($check_sql);
            if (!$stmt) {
                continue;
            }
            $stmt->bind_param('sss', $employee_code, $start_date, $end_date);
            $stmt->execute();
            $check_result = $stmt->get_result();
            $row = $check_result->fetch_assoc();
            $stmt->close();

            if ((int) $row['count'] > 0) {
                $skipped_count++;
                continue;
            }

            $insert_sql = 'INSERT INTO night_shift (employee_code, employee_name, bus_line_name, department, job, processed_by, processed_at, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)';
            $stmt = $conn->prepare($insert_sql);
            if ($stmt) {
                $stmt->bind_param('ssssssss', $employee_code, $employee_name, $bus_line_name, $department, $job, $username, $start_date, $end_date);
                $stmt->execute();
                $stmt->close();
            }

            $current_date = strtotime('Sunday', strtotime($start_date));
            for ($i = 0; $i < 5; $i++) {
                $overtime_date = date('Y-m-d', $current_date);
                $overtime_check_sql = "SELECT COUNT(*) AS count FROM overtime WHERE employee_code = ? AND overtime_date = ? AND types = 'night'";
                $stmt = $conn->prepare($overtime_check_sql);
                if ($stmt) {
                    $stmt->bind_param('ss', $employee_code, $overtime_date);
                    $stmt->execute();
                    $overtime_check_result = $stmt->get_result();
                    $overtime_row = $overtime_check_result->fetch_assoc();
                    $stmt->close();

                    if ((int) $overtime_row['count'] === 0) {
                        $overtime_sql = "INSERT INTO overtime (employee_code, employee_name, department, job, bus_line_name, processed_by, processed_at, overtime_date, types) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, 'night')";
                        $stmt = $conn->prepare($overtime_sql);
                        if ($stmt) {
                            $stmt->bind_param('sssssss', $employee_code, $employee_name, $department, $job, $bus_line_name, $username, $overtime_date);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }
                }
                $current_date = strtotime('+1 day', $current_date);
            }

            $submitted_count++;
        }

        if ($submitted_count > 0) {
            $flash_messages[] = $submitted_count . ' employee(s) added to the selected night-shift week.';
        }
        if ($skipped_count > 0) {
            $flash_messages[] = $skipped_count . ' employee(s) were skipped because they were already submitted.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_employee'])) {
    $employee_code = trim((string) ($_POST['employee_code'] ?? ''));
    $delete_start = trim((string) ($_POST['start_date'] ?? ''));
    $delete_end = trim((string) ($_POST['end_date'] ?? ''));

    if ($employee_code !== '' && $delete_start !== '' && $delete_end !== '') {
        $delete_sql = 'DELETE FROM night_shift WHERE employee_code = ? AND start_date = ? AND end_date = ?';
        $stmt = $conn->prepare($delete_sql);
        if ($stmt) {
            $stmt->bind_param('sss', $employee_code, $delete_start, $delete_end);
            $stmt->execute();
            $stmt->close();
        }

        $delete_overtime_sql = "DELETE FROM overtime WHERE employee_code = ? AND overtime_date BETWEEN ? AND ? AND types = 'night'";
        $stmt = $conn->prepare($delete_overtime_sql);
        if ($stmt) {
            $stmt->bind_param('sss', $employee_code, $delete_start, $delete_end);
            $stmt->execute();
            $stmt->close();
        }

        $flash_messages[] = 'Night-shift assignment deleted for employee ' . $employee_code . '.';
    } else {
        $flash_messages[] = 'The selected employee could not be deleted because the request was incomplete.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['copy_from'])) {
    if (!$department_is_valid || $start_date === '' || $end_date === '' || $copy_from_date === '') {
        $flash_messages[] = 'Choose a valid department, target week, and source week before copying assignments.';
    } else {
        $source_assignments = [];

        if ($selected_department === 'all') {
            if (!empty($allowed_departments)) {
                $departments_in = "'" . implode("','", array_map([$conn, 'real_escape_string'], $allowed_departments)) . "'";
                $source_sql = "SELECT employee_code, employee_name, bus_line_name, department, job FROM night_shift WHERE start_date = ? AND department IN ($departments_in) ORDER BY department, employee_name";
                $stmt = $conn->prepare($source_sql);
                if ($stmt) {
                    $stmt->bind_param('s', $copy_from_date);
                    $stmt->execute();
                    $source_result = $stmt->get_result();
                    while ($row = $source_result->fetch_assoc()) {
                        $source_assignments[] = $row;
                    }
                    $stmt->close();
                }
            }
        } else {
            $source_sql = 'SELECT employee_code, employee_name, bus_line_name, department, job FROM night_shift WHERE start_date = ? AND department = ? ORDER BY employee_name';
            $stmt = $conn->prepare($source_sql);
            if ($stmt) {
                $stmt->bind_param('ss', $copy_from_date, $selected_department);
                $stmt->execute();
                $source_result = $stmt->get_result();
                while ($row = $source_result->fetch_assoc()) {
                    $source_assignments[] = $row;
                }
                $stmt->close();
            }
        }

        $copied_count = 0;
        foreach ($source_assignments as $assignment) {
            $employee_code = $assignment['employee_code'];
            $employee_name = $assignment['employee_name'];
            $bus_line_name = $assignment['bus_line_name'];
            $department = $assignment['department'];
            $job = $assignment['job'];

            $check_sql = 'SELECT COUNT(*) AS count FROM night_shift WHERE employee_code = ? AND start_date = ? AND end_date = ?';
            $stmt = $conn->prepare($check_sql);
            if (!$stmt) {
                continue;
            }
            $stmt->bind_param('sss', $employee_code, $start_date, $end_date);
            $stmt->execute();
            $check_result = $stmt->get_result();
            $row = $check_result->fetch_assoc();
            $stmt->close();

            if ((int) $row['count'] > 0) {
                continue;
            }

            $insert_sql = 'INSERT INTO night_shift (employee_code, employee_name, bus_line_name, department, job, processed_by, processed_at, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)';
            $stmt = $conn->prepare($insert_sql);
            if ($stmt) {
                $stmt->bind_param('ssssssss', $employee_code, $employee_name, $bus_line_name, $department, $job, $username, $start_date, $end_date);
                $stmt->execute();
                $stmt->close();
            }

            $current_date = strtotime('Sunday', strtotime($start_date));
            for ($i = 0; $i < 5; $i++) {
                $overtime_date = date('Y-m-d', $current_date);
                $overtime_check_sql = "SELECT COUNT(*) AS count FROM overtime WHERE employee_code = ? AND overtime_date = ? AND types = 'night'";
                $stmt = $conn->prepare($overtime_check_sql);
                if ($stmt) {
                    $stmt->bind_param('ss', $employee_code, $overtime_date);
                    $stmt->execute();
                    $overtime_check_result = $stmt->get_result();
                    $overtime_row = $overtime_check_result->fetch_assoc();
                    $stmt->close();

                    if ((int) $overtime_row['count'] === 0) {
                        $overtime_sql = "INSERT INTO overtime (employee_code, employee_name, department, job, bus_line_name, processed_by, processed_at, overtime_date, types) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, 'night')";
                        $stmt = $conn->prepare($overtime_sql);
                        if ($stmt) {
                            $stmt->bind_param('sssssss', $employee_code, $employee_name, $department, $job, $bus_line_name, $username, $overtime_date);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }
                }
                $current_date = strtotime('+1 day', $current_date);
            }

            $copied_count++;
        }

        if ($copied_count > 0) {
            $flash_messages[] = $copied_count . ' employee(s) copied into the selected night-shift week.';
        } else {
            $flash_messages[] = 'No assignments were copied. The source week may be empty or already fully copied.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_all'])) {
    $delete_start = trim((string) ($_POST['start_date'] ?? ''));
    $delete_end = trim((string) ($_POST['end_date'] ?? ''));

    if ($delete_start !== '' && $delete_end !== '' && $department_is_valid) {
        if ($selected_department === 'all') {
            if (!empty($allowed_departments)) {
                $departments_in = "'" . implode("','", array_map([$conn, 'real_escape_string'], $allowed_departments)) . "'";
                $delete_shift_sql = "DELETE FROM night_shift WHERE start_date = ? AND end_date = ? AND department IN ($departments_in)";
                $stmt = $conn->prepare($delete_shift_sql);
                if ($stmt) {
                    $stmt->bind_param('ss', $delete_start, $delete_end);
                    $stmt->execute();
                    $stmt->close();
                }

                $delete_overtime_sql = "DELETE FROM overtime WHERE overtime_date BETWEEN ? AND ? AND types = 'night' AND department IN ($departments_in)";
                $stmt = $conn->prepare($delete_overtime_sql);
                if ($stmt) {
                    $stmt->bind_param('ss', $delete_start, $delete_end);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        } else {
            $delete_shift_sql = 'DELETE FROM night_shift WHERE start_date = ? AND end_date = ? AND department = ?';
            $stmt = $conn->prepare($delete_shift_sql);
            if ($stmt) {
                $stmt->bind_param('sss', $delete_start, $delete_end, $selected_department);
                $stmt->execute();
                $stmt->close();
            }

            $delete_overtime_sql = "DELETE FROM overtime WHERE overtime_date BETWEEN ? AND ? AND department = ? AND types = 'night'";
            $stmt = $conn->prepare($delete_overtime_sql);
            if ($stmt) {
                $stmt->bind_param('sss', $delete_start, $delete_end, $selected_department);
                $stmt->execute();
                $stmt->close();
            }
        }

        $flash_messages[] = 'All visible night-shift assignments were cleared for the selected week.';
    } else {
        $flash_messages[] = 'Delete all could not run because the selected week or department was invalid.';
    }
}

$available_employees = [];
$submitted_employees = [];
$selected_week_label = '';
foreach ($week_options as $option) {
    if ($selected_week_range === $option['start_date'] . '|' . $option['end_date']) {
        $selected_week_label = $option['label'];
        break;
    }
}

if ($department_is_valid && $start_date !== '' && $end_date !== '') {
    if ($selected_department === 'all') {
        if (!empty($allowed_departments)) {
            $departments_in = "'" . implode("','", array_map([$conn, 'real_escape_string'], $allowed_departments)) . "'";

            $employees_sql = "SELECT employee_code, first_name, department, job FROM employees WHERE department IN ($departments_in) AND employee_code NOT IN (SELECT employee_code FROM night_shift WHERE start_date = ? AND end_date = ?) ORDER BY department, first_name";
            $stmt = $conn->prepare($employees_sql);
            if ($stmt) {
                $stmt->bind_param('ss', $start_date, $end_date);
                $stmt->execute();
                $employees_result = $stmt->get_result();
                while ($row = $employees_result->fetch_assoc()) {
                    $available_employees[] = $row;
                }
                $stmt->close();
            }

            $submitted_sql = "SELECT employee_code, employee_name, bus_line_name, department, job, start_date, end_date FROM night_shift WHERE department IN ($departments_in) AND start_date = ? AND end_date = ? ORDER BY department, employee_name";
            $stmt = $conn->prepare($submitted_sql);
            if ($stmt) {
                $stmt->bind_param('ss', $start_date, $end_date);
                $stmt->execute();
                $submitted_result = $stmt->get_result();
                while ($row = $submitted_result->fetch_assoc()) {
                    $submitted_employees[] = $row;
                }
                $stmt->close();
            }
        }
    } else {
        $employees_sql = 'SELECT employee_code, first_name, department, job FROM employees WHERE department = ? AND employee_code NOT IN (SELECT employee_code FROM night_shift WHERE start_date = ? AND end_date = ?) ORDER BY employee_code';
        $stmt = $conn->prepare($employees_sql);
        if ($stmt) {
            $stmt->bind_param('sss', $selected_department, $start_date, $end_date);
            $stmt->execute();
            $employees_result = $stmt->get_result();
            while ($row = $employees_result->fetch_assoc()) {
                $available_employees[] = $row;
            }
            $stmt->close();
        }

        $submitted_sql = 'SELECT employee_code, employee_name, bus_line_name, department, job, start_date, end_date FROM night_shift WHERE department = ? AND start_date = ? AND end_date = ? ORDER BY employee_name';
        $stmt = $conn->prepare($submitted_sql);
        if ($stmt) {
            $stmt->bind_param('sss', $selected_department, $start_date, $end_date);
            $stmt->execute();
            $submitted_result = $stmt->get_result();
            while ($row = $submitted_result->fetch_assoc()) {
                $submitted_employees[] = $row;
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Night Shift Management</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="icon" type="image/png" href="images/logo.png">
</head>
<body>
<?php
app_render_page_header('NS', 'Night Shift Management', 'Plan, copy, review, and clean weekly night-shift assignments.', [
    ['label' => 'Home', 'href' => 'welcome.php'],
    ['label' => 'Shifts', 'href' => 'shifts_home.php'],
    ['label' => 'Logout', 'href' => 'logout.php'],
]);
app_render_page_hero('Shift planning', 'A rebuilt weekly planner with bulk actions, safer cleanup, and clearer visibility.', 'The source template has been rewritten so weekly filters, copy actions, and submitted assignments now live in one modern workspace.', [
    ['title' => 'Planning Window', 'text' => $shift_start . ' to ' . $shift_end],
    ['title' => 'Departments', 'text' => (string) count($allowed_departments) . ' accessible'],
    ['title' => 'Weeks', 'text' => (string) count($week_options) . ' upcoming options'],
]);
app_open_content_panel('Select Department And Week', 'Choose the department scope and week range to load available and submitted night-shift assignments.');
?>
<form method="POST" action="night_shift.php" id="nightShiftFilterForm">
    <div class="form-row">
        <label for="selected_department">Department</label>
        <select id="selected_department" name="selected_department" onchange="submitNightShiftFilters()" required>
            <option value="">Select a department</option>
            <option value="all" <?php echo $selected_department === 'all' ? 'selected' : ''; ?>>All allowed departments</option>
            <?php foreach ($allowed_departments as $department): ?>
                <option value="<?php echo app_escape($department); ?>" <?php echo $selected_department === $department ? 'selected' : ''; ?>><?php echo app_escape($department); ?></option>
            <?php endforeach; ?>
        </select>
        <label for="week_range">Week Range</label>
        <select id="week_range" name="week_range" onchange="submitNightShiftFilters()" required>
            <option value="">Select a week range</option>
            <?php foreach ($week_options as $option): ?>
                <?php $option_value = $option['start_date'] . '|' . $option['end_date']; ?>
                <option value="<?php echo app_escape($option_value); ?>" <?php echo $selected_week_range === $option_value ? 'selected' : ''; ?>><?php echo app_escape($option['label']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-row">
        <label for="copy_from_date">Copy From Week</label>
        <select id="copy_from_date" name="copy_from_date">
            <option value="">Select a source week</option>
            <?php foreach ($copy_from_week_options as $option): ?>
                <option value="<?php echo app_escape($option['start_date']); ?>" <?php echo $copy_from_date === $option['start_date'] ? 'selected' : ''; ?>><?php echo app_escape($option['label']); ?></option>
            <?php endforeach; ?>
        </select>
        <div class="panel-actions">
            <button type="submit" name="copy_from" value="1">Copy Assignments</button>
        </div>
    </div>
</form>

<?php foreach ($flash_messages as $message): ?>
    <div class="message-card"><?php echo app_escape($message); ?></div>
<?php endforeach; ?>

<?php if ($department_is_valid && $start_date !== '' && $end_date !== ''): ?>
    <div class="summary-grid">
        <div class="summary-card"><strong>Department Scope</strong><p><?php echo app_escape($selected_department === 'all' ? 'All allowed departments' : $selected_department); ?></p></div>
        <div class="summary-card"><strong>Selected Week</strong><p><?php echo app_escape($selected_week_label !== '' ? $selected_week_label : ($start_date . ' to ' . $end_date)); ?></p></div>
        <div class="summary-card"><strong>Available Employees</strong><p><?php echo count($available_employees); ?></p></div>
        <div class="summary-card"><strong>Submitted Employees</strong><p><?php echo count($submitted_employees); ?></p></div>
    </div>

    <div class="stack-gap">
        <section class="summary-card">
            <strong>Available Employees</strong>
            <p>Assign bus lines, filter the list, and submit the selected employees into the chosen night-shift week.</p>
            <div class="panel-actions">
                <input type="search" class="table-filter-input night-shift-search" placeholder="Filter available employees" oninput="filterPlannerRows('availableNightShiftTable', this.value)">
            </div>
            <?php if (!empty($available_employees)): ?>
                <form method="POST" action="night_shift.php">
                    <input type="hidden" name="selected_department" value="<?php echo app_escape($selected_department); ?>">
                    <input type="hidden" name="week_range" value="<?php echo app_escape($selected_week_range); ?>">
                    <div class="panel-actions">
                        <button type="button" onclick="toggleAllCheckboxes('night-shift-checkbox', this)">Select All</button>
                        <button type="submit">Submit Night Shift</button>
                    </div>
                    <div class="table-scroll">
                        <table id="availableNightShiftTable">
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
                                <?php foreach ($available_employees as $employee): ?>
                                    <tr>
                                        <td><?php echo app_escape($employee['employee_code']); ?></td>
                                        <td>
                                            <?php echo app_escape($employee['first_name']); ?>
                                            <input type="hidden" name="employee_name_<?php echo app_escape($employee['employee_code']); ?>" value="<?php echo app_escape($employee['first_name']); ?>">
                                        </td>
                                        <td>
                                            <?php echo app_escape($employee['department']); ?>
                                            <input type="hidden" name="department_<?php echo app_escape($employee['employee_code']); ?>" value="<?php echo app_escape($employee['department']); ?>">
                                        </td>
                                        <td>
                                            <?php echo app_escape($employee['job']); ?>
                                            <input type="hidden" name="job_<?php echo app_escape($employee['employee_code']); ?>" value="<?php echo app_escape($employee['job']); ?>">
                                        </td>
                                        <td>
                                            <select name="bus_line_name[<?php echo app_escape($employee['employee_code']); ?>]">
                                                <option value="">Select bus line</option>
                                                <?php foreach ($bus_lines as $bus_line): ?>
                                                    <option value="<?php echo app_escape($bus_line); ?>"><?php echo app_escape($bus_line); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td><input class="night-shift-checkbox" type="checkbox" name="select_employee[]" value="<?php echo app_escape($employee['employee_code']); ?>"></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            <?php else: ?>
                <div class="message-card">No more employees are available for this week and department scope.</div>
            <?php endif; ?>
        </section>

        <section class="summary-card">
            <strong>Submitted Employees</strong>
            <p>Review assignments already planned for the selected week and remove them safely when needed.</p>
            <div class="panel-actions">
                <input type="search" class="table-filter-input night-shift-search" placeholder="Filter submitted employees" oninput="filterPlannerRows('submittedNightShiftTable', this.value)">
                <form method="POST" action="night_shift.php" onsubmit="return confirm('Delete all visible night-shift assignments for this week?');">
                    <input type="hidden" name="start_date" value="<?php echo app_escape($start_date); ?>">
                    <input type="hidden" name="end_date" value="<?php echo app_escape($end_date); ?>">
                    <input type="hidden" name="selected_department" value="<?php echo app_escape($selected_department); ?>">
                    <input type="hidden" name="week_range" value="<?php echo app_escape($selected_week_range); ?>">
                    <input type="hidden" name="delete_all" value="1">
                    <button type="submit">Delete All</button>
                </form>
            </div>
            <?php if (!empty($submitted_employees)): ?>
                <div class="table-scroll">
                    <table id="submittedNightShiftTable">
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
                            <?php foreach ($submitted_employees as $employee): ?>
                                <tr>
                                    <td><?php echo app_escape($employee['employee_code']); ?></td>
                                    <td><?php echo app_escape($employee['employee_name']); ?></td>
                                    <td><?php echo app_escape($employee['bus_line_name']); ?></td>
                                    <td><?php echo app_escape($employee['department']); ?></td>
                                    <td><?php echo app_escape($employee['job']); ?></td>
                                    <td><?php echo app_escape($employee['start_date']); ?></td>
                                    <td><?php echo app_escape($employee['end_date']); ?></td>
                                    <td>
                                        <form method="POST" action="night_shift.php" onsubmit="return confirm('Delete this night-shift assignment?');">
                                            <input type="hidden" name="employee_code" value="<?php echo app_escape($employee['employee_code']); ?>">
                                            <input type="hidden" name="start_date" value="<?php echo app_escape($employee['start_date']); ?>">
                                            <input type="hidden" name="end_date" value="<?php echo app_escape($employee['end_date']); ?>">
                                            <input type="hidden" name="selected_department" value="<?php echo app_escape($selected_department); ?>">
                                            <input type="hidden" name="week_range" value="<?php echo app_escape($selected_week_range); ?>">
                                            <input type="hidden" name="delete_employee" value="1">
                                            <button type="submit">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="message-card">No night-shift assignments have been submitted for this selection yet.</div>
            <?php endif; ?>
        </section>
    </div>
<?php else: ?>
    <div class="message-card">Choose a department scope and week range to begin planning night-shift assignments.</div>
<?php endif; ?>
<?php
app_close_content_panel();
app_render_page_end();
?>
<style>
.legacy-panel .panel-actions .night-shift-search {
    width: 240px !important;
    height: 46px !important;
    min-height: 46px !important;
    padding: 0 18px !important;
    border-radius: 16px !important;
    border: 1px solid rgba(159, 29, 34, 0.26) !important;
    background: #ffffff !important;
    box-shadow: 0 16px 34px rgba(159, 29, 34, 0.12) !important;
    color: #8f1a1e !important;
    font: inherit !important;
    font-weight: 700 !important;
    line-height: 46px !important;
    appearance: none;
    -webkit-appearance: none;
}

.legacy-panel .panel-actions .night-shift-search::placeholder {
    color: #8f1a1e;
    opacity: 0.72;
}

@media (max-width: 640px) {
    .legacy-panel .panel-actions .night-shift-search {
        width: 100% !important;
    }
}
</style>
<script>
function submitNightShiftFilters() {
    document.getElementById('nightShiftFilterForm').submit();
}

function toggleAllCheckboxes(className, trigger) {
    const boxes = document.querySelectorAll('.' + className);
    const shouldCheck = Array.from(boxes).some((box) => !box.checked);
    boxes.forEach((box) => {
        box.checked = shouldCheck;
    });
    if (trigger) {
        trigger.textContent = shouldCheck ? 'Clear Selection' : 'Select All';
    }
}

function filterPlannerRows(tableId, value) {
    const query = value.trim().toLowerCase();
    const rows = document.querySelectorAll('#' + tableId + ' tbody tr');
    rows.forEach((row) => {
        row.style.display = row.textContent.toLowerCase().includes(query) ? '' : 'none';
    });
}
</script>
</body>
</html>
