<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';
include 'includes/app_helpers.php';

function overtime_render_state_page(string $title, string $message, array $cards = []): void
{
    app_render_state_page('OT', 'Overtime Management', 'Daily planning workspace', 'Overtime status', $title, $message, [
        ['label' => 'Home', 'href' => 'welcome.php'],
        ['label' => 'Overtime', 'href' => 'overtime_home.php'],
        ['label' => 'Logout', 'href' => 'logout.php'],
    ], array_merge([
        ['title' => 'Workspace', 'text' => 'Daily overtime planning'],
        ['title' => 'Availability', 'text' => 'Check the active submission window'],
    ], $cards), [
        ['label' => 'Back To Overtime Home', 'href' => 'overtime_home.php'],
        ['label' => 'Dashboard', 'href' => 'welcome.php'],
    ], 'Workspace Status', 'This overtime workspace is currently unavailable, but the rest of the module remains accessible.');
}

$role_name = 'close_time';
$end_time_sql = "SELECT end_time, onoff, 20mins FROM rules WHERE name = '$role_name'";
$end_time_result = $conn->query($end_time_sql);

if ($end_time_result->num_rows > 0) {
    $end_time_row = $end_time_result->fetch_assoc();
    $end_time = $end_time_row['end_time'];
    $onoff_status = $end_time_row['onoff'];
    $exception_20mins = $end_time_row['20mins'];

    $current_time = date('H:i:s');
    $current_date_time = date('Y-m-d H:i:s');
    $current_day = date('w');

    $new_end_time = date('H:i:s', strtotime($end_time . ' +0 hours'));
    $exception_time = date('Y-m-d H:i:s', strtotime($exception_20mins . ' +20 minutes'));

    if (($current_day == 5 || $current_day == 6) && $onoff_status === 'off') {
        overtime_render_state_page('Submission Closed', 'Overtime submissions are closed on Fridays and Saturdays.', [
            ['title' => 'Weekend Rule', 'text' => 'Friday and Saturday submissions are disabled'],
            ['title' => 'Next Access', 'text' => 'Reopen on the next working day'],
        ]);
    }

    if ($current_time > $end_time && ($current_date_time > $exception_time || $exception_20mins > $current_date_time)) {
        overtime_render_state_page('Submission Closed For Today', 'You cannot submit or edit overtime records after ' . $new_end_time . '.', [
            ['title' => 'Cutoff Time', 'text' => $new_end_time],
            ['title' => 'Exception Window', 'text' => $exception_20mins],
        ]);
    }
} else {
    overtime_render_state_page('Configuration Missing', 'No matching close-time rule was found for overtime submission.', [
        ['title' => 'Rule Name', 'text' => $role_name],
        ['title' => 'Action', 'text' => 'Review overtime settings in the control panel'],
    ]);
}

$username = $_SESSION['username'];
$today = date('Y-m-d');
$selected_date = $_POST['date'] ?? $_POST['selected_date'] ?? $today;
$selected_department = $_POST['department'] ?? $_POST['selected_department'] ?? '';
$employee_search = trim($_POST['employee_search'] ?? '');

$user_groups = [];
$permissions_sql = "SELECT group_name_1, group_name_2, group_name_3 FROM user_permissions WHERE username='$username'";
$permissions_result = $conn->query($permissions_sql);
if ($permissions_result->num_rows > 0) {
    $user_permissions = $permissions_result->fetch_assoc();
    foreach (['group_name_1', 'group_name_2', 'group_name_3'] as $group_key) {
        if (!empty($user_permissions[$group_key])) {
            $user_groups[] = $user_permissions[$group_key];
        }
    }
}

$allowed_departments = [];
if (!empty($user_groups)) {
    $groups_in = "'" . implode("','", $user_groups) . "'";
    $departments_sql = "SELECT DISTINCT department FROM department_groups WHERE group_name IN ($groups_in) OR group_name1 IN ($groups_in) OR group_name2 IN ($groups_in) ORDER BY department";
    $departments_result = $conn->query($departments_sql);
    while ($row = $departments_result->fetch_assoc()) {
        $allowed_departments[] = $row['department'];
    }
}

$date_options = [];
for ($offset = 0; $offset <= 6; $offset++) {
    $value = date('Y-m-d', strtotime("+$offset day", strtotime($today)));
    $date_options[$value] = date('l, Y-m-d', strtotime($value));
}

$bus_lines = [];
$bus_line_result = $conn->query("SELECT bus_line_name FROM bus_lines ORDER BY bus_line_name");
while ($row = $bus_line_result->fetch_assoc()) {
    $bus_lines[] = $row['bus_line_name'];
}

$available_employees = [];
$submitted_employees = [];
$summary = [
    'eligible' => 0,
    'submitted' => 0,
];

$is_department_selected = $selected_department !== '' && in_array($selected_department, $allowed_departments, true);
$has_employee_search = $employee_search !== '';

$week_start = date('Y-m-d', strtotime('last Saturday', strtotime($selected_date)));
if (date('w', strtotime($selected_date)) == 6) {
    $week_start = $selected_date;
}
$week_end = date('Y-m-d', strtotime('next Friday', strtotime($selected_date)));
if (date('w', strtotime($selected_date)) == 5) {
    $week_end = $selected_date;
}

if ($is_department_selected || $has_employee_search) {
    $available_departments_in = "'" . implode("','", $allowed_departments) . "'";

    if ($is_department_selected) {
        $available_sql = "SELECT e.employee_code, e.first_name, e.department, e.job,
            SUM(CASE
                WHEN o.overtime_date IS NOT NULL AND DAYOFWEEK(o.overtime_date) IN (6, 7) AND o.overtime_date NOT IN (SELECT days FROM calendar WHERE type = 'saturday') THEN 8
                WHEN o.overtime_date IS NOT NULL AND o.overtime_date IN (SELECT days FROM calendar WHERE type = 'holiday') THEN 0
                WHEN o.overtime_date IS NOT NULL THEN 2
                ELSE 0
            END) AS total_hours
            FROM employees e
            LEFT JOIN overtime o ON e.employee_code = o.employee_code AND o.overtime_date BETWEEN ? AND ?
            WHERE e.department = ?
              AND e.department IN ($available_departments_in)
              AND e.employee_code NOT IN (SELECT employee_code FROM overtime WHERE overtime_date = ?)
              AND (
                  ? = ''
                  OR e.employee_code LIKE CONCAT('%', ?, '%')
                  OR e.first_name LIKE CONCAT('%', ?, '%')
              )
            GROUP BY e.employee_code, e.first_name, e.department, e.job
            ORDER BY e.first_name";
        $stmt = $conn->prepare($available_sql);
        $stmt->bind_param('sssssss', $week_start, $week_end, $selected_department, $selected_date, $employee_search, $employee_search, $employee_search);
    } else {
        $available_sql = "SELECT e.employee_code, e.first_name, e.department, e.job,
            SUM(CASE
                WHEN o.overtime_date IS NOT NULL AND DAYOFWEEK(o.overtime_date) IN (6, 7) AND o.overtime_date NOT IN (SELECT days FROM calendar WHERE type = 'saturday') THEN 8
                WHEN o.overtime_date IS NOT NULL AND o.overtime_date IN (SELECT days FROM calendar WHERE type = 'holiday') THEN 0
                WHEN o.overtime_date IS NOT NULL THEN 2
                ELSE 0
            END) AS total_hours
            FROM employees e
            LEFT JOIN overtime o ON e.employee_code = o.employee_code AND o.overtime_date BETWEEN ? AND ?
            WHERE e.department IN ($available_departments_in)
              AND e.employee_code NOT IN (SELECT employee_code FROM overtime WHERE overtime_date = ?)
              AND (
                  e.employee_code LIKE CONCAT('%', ?, '%')
                  OR e.first_name LIKE CONCAT('%', ?, '%')
              )
            GROUP BY e.employee_code, e.first_name, e.department, e.job
            ORDER BY e.first_name";
        $stmt = $conn->prepare($available_sql);
        $stmt->bind_param('sssss', $week_start, $week_end, $selected_date, $employee_search, $employee_search);
    }

    $stmt->execute();
    $available_result = $stmt->get_result();
    while ($row = $available_result->fetch_assoc()) {
        $row['remaining_hours'] = 12 - (float) $row['total_hours'];
        $available_employees[] = $row;
    }
    $stmt->close();

    $summary['eligible'] = count($available_employees);
}

if (!empty($allowed_departments)) {
    $submitted_departments_in = "'" . implode("','", $allowed_departments) . "'";

    $submitted_sql = "SELECT t.employee_code, t.employee_name, t.bus_line_name, t.department, t.job, t.types,
        w.total_hours_week
        FROM (
            SELECT employee_code, employee_name, bus_line_name, department, job, types
            FROM overtime
            WHERE overtime_date = ? AND department IN ($submitted_departments_in)
        ) t
        LEFT JOIN (
            SELECT employee_code,
                SUM(CASE
                    WHEN DAYOFWEEK(overtime_date) IN (6, 7) AND overtime_date NOT IN (SELECT days FROM calendar WHERE type = 'saturday') THEN 8
                    WHEN overtime_date IN (SELECT days FROM calendar WHERE type = 'holiday') THEN 0
                    ELSE 2
                END) AS total_hours_week
            FROM overtime
            WHERE overtime_date BETWEEN ? AND ? AND types = 'normal'
            GROUP BY employee_code
        ) w ON t.employee_code = w.employee_code
        ORDER BY t.employee_name";
    $stmt = $conn->prepare($submitted_sql);
    $stmt->bind_param('sss', $selected_date, $week_start, $week_end);
    $stmt->execute();
    $submitted_result = $stmt->get_result();
    while ($row = $submitted_result->fetch_assoc()) {
        $row['remaining_hours'] = max(-20, 12 - (float) ($row['total_hours_week'] ?? 0));
        $submitted_employees[] = $row;
    }
    $stmt->close();
}

$summary['submitted'] = count($submitted_employees);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Overtime Management</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="icon" type="image/png" href="images/logo.png">
</head>
<body>
<?php
app_render_page_header('OT', 'Overtime Management', 'Create daily overtime entries from a cleaner operational workspace.', [
    ['label' => 'Home', 'href' => 'welcome.php'],
    ['label' => 'Overtime', 'href' => 'overtime_home.php'],
    ['label' => 'Logout', 'href' => 'logout.php'],
]);
app_render_page_hero('Operations workflow', 'Daily overtime planning, submission, and cleanup now live in one structured workspace.', 'The backend submission flow is unchanged, but the page has been rewritten into clear sections with better filtering, summaries, and selection controls.', [
    ['title' => 'Close Time', 'text' => $new_end_time],
    ['title' => 'Departments', 'text' => (string) count($allowed_departments) . ' accessible'],
]);
app_open_content_panel('Select Overtime Date And Department', 'Choose a date and department, or search by employee code/name, to load eligible employees and today\'s submitted overtime.');
?>
<form method="POST" action="overtime.php" id="dateDepartmentForm">
    <div class="form-row">
        <label for="date">Date</label>
        <select id="date" name="date" onchange="this.form.submit()">
            <?php foreach ($date_options as $value => $label): ?>
                <option value="<?php echo app_escape($value); ?>" <?php echo $selected_date === $value ? 'selected' : ''; ?>><?php echo app_escape($label); ?></option>
            <?php endforeach; ?>
        </select>
        <label for="department">Department</label>
        <select id="department" name="department" onchange="this.form.submit()">
            <option value="">Select a department</option>
            <?php foreach ($allowed_departments as $department): ?>
                <option value="<?php echo app_escape($department); ?>" <?php echo $selected_department === $department ? 'selected' : ''; ?>><?php echo app_escape($department); ?></option>
            <?php endforeach; ?>
        </select>
        <label for="employee_search">Employee Search</label>
        <input
            type="text"
            id="employee_search"
            name="employee_search"
            value="<?php echo app_escape($employee_search); ?>"
            placeholder="Code or name"
        >
        <button type="submit">Search</button>
    </div>
</form>

<?php if ($is_department_selected): ?>
    <div class="summary-grid">
        <div class="summary-card"><strong>Date</strong><p><?php echo app_escape($selected_date); ?></p></div>
        <div class="summary-card"><strong>Department</strong><p><?php echo app_escape($selected_department); ?></p></div>
        <div class="summary-card"><strong>Eligible Employees</strong><p><?php echo $summary['eligible']; ?></p></div>
        <div class="summary-card"><strong>Submitted (Permitted Departments)</strong><p><?php echo $summary['submitted']; ?></p></div>
    </div>
<?php endif; ?>

<div class="stack-gap">
    <?php if ($is_department_selected || $has_employee_search): ?>
        <div class="summary-card">
            <strong>Available Employees</strong>
            <p>Select employees, assign bus lines, then submit overtime for the chosen date. Use Employee Search to filter by code or name.</p>
            <?php if (!empty($available_employees)): ?>
                <form method="POST" action="process_overtime.php">
                    <input type="hidden" name="selected_date" value="<?php echo app_escape($selected_date); ?>">
                    <input type="hidden" name="selected_department" value="<?php echo app_escape($selected_department); ?>">
                    <div class="panel-actions">
                        <button type="button" class="button" onclick="toggleAllCheckboxes('available-employee', this)">Select All</button>
                        <button type="submit">Submit Selected Employees</button>
                    </div>
                    <div class="table-scroll">
                        <table class="sortable-table">
                            <thead>
                                <tr>
                                    <th data-sort="text">Code</th>
                                    <th data-sort="text">Name</th>
                                    <th data-sort="text">Bus Line</th>
                                    <th data-sort="text">Department</th>
                                    <th data-sort="text">Job</th>
                                    <th data-sort="number">Remaining H</th>
                                    <th>Select</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($available_employees as $employee): ?>
                                    <tr class="<?php echo ($employee['remaining_hours'] <= 0) ? 'exceeded' : ''; ?>">
                                        <td><?php echo app_escape($employee['employee_code']); ?></td>
                                        <td><?php echo app_escape($employee['first_name']); ?></td>
                                        <td>
                                            <select name="bus_line_name[<?php echo app_escape($employee['employee_code']); ?>]">
                                                <option value="">Select bus line</option>
                                                <?php foreach ($bus_lines as $bus_line): ?>
                                                    <option value="<?php echo app_escape($bus_line); ?>"><?php echo app_escape($bus_line); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td><?php echo app_escape($employee['department']); ?></td>
                                        <td><?php echo app_escape($employee['job']); ?></td>
                                        <td><?php echo app_escape((string) $employee['remaining_hours']); ?></td>
                                        <td><input class="available-employee" type="checkbox" name="select_employee[]" value="<?php echo app_escape($employee['employee_code']); ?>"></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            <?php else: ?>
                <div class="message-card">No eligible employees found for your permitted departments, selected date, and search criteria.</div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="message-card">Select a department or search by employee code or name to begin managing overtime.</div>
    <?php endif; ?>

    <div class="summary-card">
        <strong>Submitted Overtime</strong>
        <p>Review or delete normal overtime records already submitted for the selected date across your permitted departments.</p>
        <?php if (!empty($allowed_departments) && !empty($submitted_employees)): ?>
            <div class="table-scroll">
                <table class="sortable-table">
                    <thead>
                        <tr>
                            <th data-sort="text">Code</th>
                            <th data-sort="text">Name</th>
                            <th data-sort="text">Bus Line</th>
                            <th data-sort="text">Department</th>
                            <th data-sort="text">Job</th>
                            <th data-sort="text">Shift</th>
                            <th data-sort="number">Remaining H</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submitted_employees as $employee): ?>
                            <tr class="<?php echo ($employee['remaining_hours'] <= 0) ? 'exceeded' : ''; ?>">
                                <td><?php echo app_escape($employee['employee_code']); ?></td>
                                <td><?php echo app_escape($employee['employee_name']); ?></td>
                                <td><?php echo app_escape($employee['bus_line_name']); ?></td>
                                <td><?php echo app_escape($employee['department']); ?></td>
                                <td><?php echo app_escape($employee['job']); ?></td>
                                <td><?php echo app_escape($employee['types']); ?></td>
                                <td><?php echo app_escape((string) $employee['remaining_hours']); ?></td>
                                <td>
                                    <form method="POST" action="delete_overtime.php">
                                        <input type="hidden" name="employee_code" value="<?php echo app_escape($employee['employee_code']); ?>">
                                        <input type="hidden" name="overtime_date" value="<?php echo app_escape($selected_date); ?>">
                                        <input type="hidden" name="selected_department" value="<?php echo app_escape($selected_department); ?>">
                                        <button type="submit">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif (empty($allowed_departments)): ?>
            <div class="message-card">You do not currently have any permitted departments assigned.</div>
        <?php else: ?>
            <div class="message-card">No normal overtime has been submitted for your permitted departments on the selected date yet.</div>
        <?php endif; ?>
    </div>
</div>
<?php
app_close_content_panel();
app_render_page_end();
?>
<script>
function getSortableCellValue(row, columnIndex) {
    const cell = row.cells[columnIndex];
    if (!cell) {
        return '';
    }

    const select = cell.querySelector('select');
    if (select) {
        return (select.options[select.selectedIndex] || {}).text || '';
    }

    return cell.textContent.trim();
}

function sortTableByColumn(table, columnIndex, sortType, header) {
    const tbody = table.tBodies[0];
    if (!tbody) {
        return;
    }

    const rows = Array.from(tbody.rows);
    const previousColumn = table.dataset.sortColumn;
    const previousDirection = table.dataset.sortDirection || 'asc';
    const nextDirection = previousColumn === String(columnIndex) && previousDirection === 'asc' ? 'desc' : 'asc';

    rows.sort((rowA, rowB) => {
        const valueA = getSortableCellValue(rowA, columnIndex);
        const valueB = getSortableCellValue(rowB, columnIndex);

        if (sortType === 'number') {
            const numberA = parseFloat(valueA.replace(/[^0-9.-]/g, ''));
            const numberB = parseFloat(valueB.replace(/[^0-9.-]/g, ''));
            const safeA = Number.isNaN(numberA) ? 0 : numberA;
            const safeB = Number.isNaN(numberB) ? 0 : numberB;
            return nextDirection === 'asc' ? safeA - safeB : safeB - safeA;
        }

        return nextDirection === 'asc'
            ? valueA.localeCompare(valueB, undefined, { numeric: true, sensitivity: 'base' })
            : valueB.localeCompare(valueA, undefined, { numeric: true, sensitivity: 'base' });
    });

    rows.forEach((row) => tbody.appendChild(row));

    table.dataset.sortColumn = String(columnIndex);
    table.dataset.sortDirection = nextDirection;

    table.querySelectorAll('thead th[data-sort]').forEach((th) => {
        th.removeAttribute('aria-sort');
    });
    header.setAttribute('aria-sort', nextDirection === 'asc' ? 'ascending' : 'descending');
}

function initSortableTables() {
    document.querySelectorAll('table.sortable-table').forEach((table) => {
        table.querySelectorAll('thead th[data-sort]').forEach((header, columnIndex) => {
            header.style.cursor = 'pointer';
            header.setAttribute('title', 'Click to sort');
            header.setAttribute('tabindex', '0');

            header.addEventListener('click', () => {
                sortTableByColumn(table, columnIndex, header.dataset.sort, header);
            });

            header.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    sortTableByColumn(table, columnIndex, header.dataset.sort, header);
                }
            });
        });
    });
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

initSortableTables();
</script>
</body>
</html>
