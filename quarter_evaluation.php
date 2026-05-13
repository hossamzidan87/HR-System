<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';
include 'includes/app_helpers.php';

function quarter_evaluation_render_state_page(string $title, string $message, array $cards = []): void
{
    app_render_state_page('EV', 'Quarter Evaluation', 'Performance review workspace', 'Evaluation status', $title, $message, [
        ['label' => 'Home', 'href' => 'welcome.php'],
        ['label' => 'Evaluation', 'href' => 'evaluation.php'],
        ['label' => 'Logout', 'href' => 'logout.php'],
    ], array_merge([
        ['title' => 'Workspace', 'text' => 'Quarterly review'],
        ['title' => 'Next Step', 'text' => 'Return when the review window is open'],
    ], $cards), [
        ['label' => 'Back To Evaluation Home', 'href' => 'evaluation.php'],
        ['label' => 'Dashboard', 'href' => 'welcome.php'],
    ], 'Evaluation Status', 'This quarterly evaluation workspace is unavailable right now, but related navigation remains available.');
}

function quarter_evaluation_fetch_previous(mysqli $conn, string $employee_code, string $quarter, int $year): array
{
    $evaluation_sql = 'SELECT * FROM evaluations WHERE employee_code = ? AND quarter = ? AND year = ? ORDER BY evaluation_date DESC LIMIT 1';
    $stmt = $conn->prepare($evaluation_sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('ssi', $employee_code, $quarter, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    $record = $result->num_rows > 0 ? ($result->fetch_assoc() ?: []) : [];
    $stmt->close();

    return $record;
}

function quarter_evaluation_fetch_employee(mysqli $conn, string $employee_code): ?array
{
    $employee_sql = 'SELECT employee_code, first_name, department, job, employment_date FROM eva_list WHERE employee_code = ? LIMIT 1';
    $stmt = $conn->prepare($employee_sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $employee_code);
    $stmt->execute();
    $result = $stmt->get_result();
    $record = $result->num_rows > 0 ? ($result->fetch_assoc() ?: null) : null;
    $stmt->close();

    return $record;
}

$username = $_SESSION['username'] ?? '';
if ($username === '') {
    quarter_evaluation_render_state_page('Unauthorized', 'Please sign in again to continue.', [
        ['title' => 'Session', 'text' => 'Authentication required'],
    ]);
}

$user_groups = [];
$stmt = $conn->prepare('SELECT group_name_1, group_name_2, group_name_3 FROM user_permissions WHERE username = ?');
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

$rule_sql = "SELECT quarter, year, eva_start, eva_end, defined_rule FROM rules WHERE defined_rule = quarter AND name = 'eva_q' LIMIT 1";
$rule_result = $conn->query($rule_sql);
if (!$rule_result || $rule_result->num_rows === 0) {
    quarter_evaluation_render_state_page('Configuration Missing', 'The current quarterly evaluation rule could not be loaded.', [
        ['title' => 'Rule Name', 'text' => 'eva_q'],
    ]);
}
$rule = $rule_result->fetch_assoc();
$quarter = (string) $rule['quarter'];
$year = (int) $rule['year'];
$eva_start = (string) $rule['eva_start'];
$eva_end = (string) $rule['eva_end'];
$defined_rule = (string) $rule['defined_rule'];

$current_datetime = new DateTime();
$start_datetime = new DateTime($eva_start);
$end_datetime = new DateTime($eva_end);
if ($defined_rule !== $quarter) {
    quarter_evaluation_render_state_page('Rule Mismatch', 'The configured quarterly rule does not match the active quarter.', [
        ['title' => 'Active Quarter', 'text' => $quarter],
        ['title' => 'Configured Rule', 'text' => $defined_rule],
    ]);
}
if ($current_datetime < $start_datetime) {
    $interval = $current_datetime->diff($start_datetime);
    quarter_evaluation_render_state_page('Evaluation Not Started', 'Quarterly evaluation opens in ' . $interval->format('%a day(s) and %h hour(s)') . '.', [
        ['title' => 'Quarter', 'text' => $quarter . ' / ' . $year],
        ['title' => 'Starts', 'text' => $eva_start],
    ]);
}
if ($current_datetime > $end_datetime) {
    quarter_evaluation_render_state_page('Evaluation Closed', 'The quarterly evaluation window ended on ' . $end_datetime->format('Y-m-d H:i') . '.', [
        ['title' => 'Quarter', 'text' => $quarter . ' / ' . $year],
        ['title' => 'Ended', 'text' => $end_datetime->format('Y-m-d H:i')],
    ]);
}

$criteria = [
    'attendance' => 'Attendance',
    'productivity' => 'Productivity',
    'work_quality' => 'Work quality',
    'communication_skills' => 'Communication skills',
    'job_knowledge' => 'Job knowledge',
    'cooperation' => 'Cooperation',
    'technical_skills' => 'Technical skills',
    'commitment_to_safety' => 'Commitment to safety',
    'attitude' => 'Attitude',
    'creativity' => 'Creativity',
];

$flash_messages = [];
$selected_department = trim((string) ($_POST['department'] ?? ''));
$selected_employee = trim((string) ($_POST['employee'] ?? ''));
$previous_evaluation = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['select_department'])) {
        $selected_employee = '';
    } elseif (isset($_POST['select_employee'])) {
        if ($selected_employee !== '') {
            $previous_evaluation = quarter_evaluation_fetch_previous($conn, $selected_employee, $quarter, $year);
        }
    } elseif (isset($_POST['clear_evaluation'])) {
        if ($selected_employee !== '') {
            $delete_sql = 'DELETE FROM evaluations WHERE employee_code = ? AND quarter = ? AND year = ?';
            $stmt = $conn->prepare($delete_sql);
            if ($stmt) {
                $stmt->bind_param('ssi', $selected_employee, $quarter, $year);
                $stmt->execute();
                $stmt->close();
                $flash_messages[] = 'Evaluation cleared successfully.';
            }
        }
    } elseif (isset($_POST['save_evaluation'])) {
        $ratings = [];
        foreach ($criteria as $field => $label) {
            $value = filter_input(INPUT_POST, $field, FILTER_VALIDATE_INT);
            if ($value === false || $value < 1 || $value > 10) {
                quarter_evaluation_render_state_page('Invalid Rating', 'Each evaluation element must be rated from 1 to 10.');
            }
            $ratings[$field] = $value;
        }

        $employee_info_sql = 'SELECT job, employment_date, first_name FROM eva_list WHERE employee_code = ?';
        $stmt = $conn->prepare($employee_info_sql);
        if (!$stmt) {
            quarter_evaluation_render_state_page('Database Error', 'Employee information could not be loaded.');
        }
        $stmt->bind_param('s', $selected_employee);
        $stmt->execute();
        $employee_info_result = $stmt->get_result();
        if ($employee_info_result->num_rows === 0) {
            $stmt->close();
            quarter_evaluation_render_state_page('Employee Missing', 'The selected employee could not be found in the evaluation list.');
        }
        $employee_info = $employee_info_result->fetch_assoc();
        $stmt->close();

        $exp = trim((string) ($_POST['exp'] ?? ''));
        if (!in_array($exp, ['A', 'B', 'C'], true)) {
            quarter_evaluation_render_state_page('Invalid Experience Level', 'Choose a valid experience level before saving.');
        }

        $evaluation_check_sql = 'SELECT employee_code FROM evaluations WHERE employee_code = ? AND quarter = ? AND year = ?';
        $stmt = $conn->prepare($evaluation_check_sql);
        if (!$stmt) {
            quarter_evaluation_render_state_page('Database Error', 'The existing evaluation could not be checked.');
        }
        $stmt->bind_param('ssi', $selected_employee, $quarter, $year);
        $stmt->execute();
        $evaluation_check_result = $stmt->get_result();
        $exists = $evaluation_check_result->num_rows > 0;
        $stmt->close();

        if ($exists) {
            $sql = 'UPDATE evaluations SET attendance = ?, productivity = ?, work_quality = ?, communication_skills = ?, job_knowledge = ?, cooperation = ?, technical_skills = ?, commitment_to_safety = ?, attitude = ?, creativity = ?, exp = ?, evaluated_by = ?, evaluation_date = NOW() WHERE employee_code = ? AND quarter = ? AND year = ?';
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('iiiiiiiiiissssi', $ratings['attendance'], $ratings['productivity'], $ratings['work_quality'], $ratings['communication_skills'], $ratings['job_knowledge'], $ratings['cooperation'], $ratings['technical_skills'], $ratings['commitment_to_safety'], $ratings['attitude'], $ratings['creativity'], $exp, $username, $selected_employee, $quarter, $year);
                $stmt->execute();
                $stmt->close();
                $flash_messages[] = 'Evaluation updated successfully.';
            }
        } else {
            $sql = 'INSERT INTO evaluations (employee_code, employee_name, department, job, employment_date, attendance, productivity, work_quality, communication_skills, job_knowledge, cooperation, technical_skills, commitment_to_safety, attitude, creativity, exp, evaluated_by, quarter, year, evaluation_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())';
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('sssssiiiiiiiiiisssi', $selected_employee, $employee_info['first_name'], $selected_department, $employee_info['job'], $employee_info['employment_date'], $ratings['attendance'], $ratings['productivity'], $ratings['work_quality'], $ratings['communication_skills'], $ratings['job_knowledge'], $ratings['cooperation'], $ratings['technical_skills'], $ratings['commitment_to_safety'], $ratings['attitude'], $ratings['creativity'], $exp, $username, $quarter, $year);
                $stmt->execute();
                $stmt->close();
                $flash_messages[] = 'Evaluation submitted successfully.';
            }
        }

        $previous_evaluation = quarter_evaluation_fetch_previous($conn, $selected_employee, $quarter, $year);
    }
}

if ($previous_evaluation === [] && $selected_employee !== '') {
    $previous_evaluation = quarter_evaluation_fetch_previous($conn, $selected_employee, $quarter, $year);
}

$employees = [];
if ($selected_department !== '') {
    $employees_sql = 'SELECT employee_code, first_name, department, job, employment_date FROM eva_list WHERE department = ? ORDER BY employee_code';
    $stmt = $conn->prepare($employees_sql);
    if ($stmt) {
        $stmt->bind_param('s', $selected_department);
        $stmt->execute();
        $employees_result = $stmt->get_result();
        while ($row = $employees_result->fetch_assoc()) {
            $employees[] = $row;
        }
        $stmt->close();
    }
}

$permitted_employee_count = 0;
if (!empty($allowed_departments)) {
    $placeholders = implode(',', array_fill(0, count($allowed_departments), '?'));
    $types = str_repeat('s', count($allowed_departments));
    $permitted_sql = "SELECT COUNT(*) AS total FROM eva_list WHERE department IN ($placeholders)";
    $stmt = $conn->prepare($permitted_sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$allowed_departments);
        $stmt->execute();
        $permitted_result = $stmt->get_result();
        if ($permitted_result && $permitted_result->num_rows > 0) {
            $permitted_row = $permitted_result->fetch_assoc();
            $permitted_employee_count = (int) ($permitted_row['total'] ?? 0);
        }
        $stmt->close();
    }
}

$evaluated_employees = [];
$evaluated_sql = '';
if (!empty($allowed_departments)) {
    $placeholders = implode(',', array_fill(0, count($allowed_departments), '?'));
    $evaluated_sql = "SELECT DISTINCT employee_code FROM evaluations WHERE quarter = ? AND year = ? AND department IN ($placeholders)";
    $stmt = $conn->prepare($evaluated_sql);
    if ($stmt) {
        $types = 'si' . str_repeat('s', count($allowed_departments));
        $stmt->bind_param($types, $quarter, $year, ...$allowed_departments);
        $stmt->execute();
        $evaluated_result = $stmt->get_result();
        while ($row = $evaluated_result->fetch_assoc()) {
            $evaluated_employees[] = $row['employee_code'];
        }
        $stmt->close();
    }
}

$evaluated_employee_lookup = [];
foreach ($evaluated_employees as $evaluated_employee_code) {
    $normalized_code = strtolower(trim((string) $evaluated_employee_code));
    if ($normalized_code !== '') {
        $evaluated_employee_lookup[$normalized_code] = true;
    }
}

$selected_employee_record = null;
foreach ($employees as $employee) {
    if ($employee['employee_code'] === $selected_employee) {
        $selected_employee_record = $employee;
        break;
    }
}

if ($selected_employee_record === null && $selected_employee !== '') {
    $selected_employee_record = quarter_evaluation_fetch_employee($conn, $selected_employee);
    if ($selected_employee_record !== null && $selected_department === '') {
        $selected_department = (string) $selected_employee_record['department'];
    }
}

$total_score = 0;
foreach (array_keys($criteria) as $field) {
    $total_score += (int) ($previous_evaluation[$field] ?? 0);
}
$average_score = count($criteria) > 0 ? round($total_score / count($criteria), 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quarter Evaluation</title>
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="icon" type="image/png" href="images/logo.png">
</head>
<body>
<?php
app_render_page_header('EV', 'Quarter Evaluation', 'Review and submit quarterly employee performance in one streamlined workspace.', [
    ['label' => 'Home', 'href' => 'welcome.php'],
    ['label' => 'Evaluation', 'href' => 'evaluation.php'],
    ['label' => 'Logout', 'href' => 'logout.php'],
]);
app_render_page_hero('Quarterly review', 'A source-level rewrite with cleaner flow, live scoring, and maintainable criteria rendering.', 'The old repeated evaluation markup has been replaced with a single structured template that keeps the same database behavior while making reviews much easier to manage.', [
    ['title' => 'Quarter', 'text' => $quarter],
    ['title' => 'Year', 'text' => (string) $year],
    ['title' => 'Window Ends', 'text' => $eva_end],
]);
app_open_content_panel('Choose Department And Employee', 'Select a department first, then pick the employee you want to evaluate for the active quarter.');
?>
<?php foreach ($flash_messages as $message): ?>
    <div class="message-card"><?php echo app_escape($message); ?></div>
<?php endforeach; ?>

<div class="summary-grid">
    <div class="summary-card"><strong>Accessible Departments</strong><p><?php echo count($allowed_departments); ?></p></div>
    <div class="summary-card"><strong>Evaluated Employees</strong><p><?php echo count($evaluated_employees) . '/' . $permitted_employee_count; ?></p></div>
    <div class="summary-card"><strong>Evaluation Start</strong><p><?php echo app_escape($eva_start); ?></p></div>
    <div class="summary-card"><strong>Evaluation End</strong><p><?php echo app_escape($eva_end); ?></p></div>
</div>

<form id="quarterDepartmentForm" method="POST" action="quarter_evaluation.php">
    <input type="hidden" name="select_department" value="1">
    <div class="form-row">
        <label for="department">Department</label>
        <select id="department" name="department" required onchange="submitQuarterDepartmentForm()">
            <option value="">Select a department</option>
            <?php foreach ($allowed_departments as $department): ?>
                <option value="<?php echo app_escape($department); ?>" <?php echo $selected_department === $department ? 'selected' : ''; ?>><?php echo app_escape($department); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<?php if ($selected_department !== ''): ?>
    <form id="quarterEmployeeForm" method="POST" action="quarter_evaluation.php">
        <input type="hidden" name="select_employee" value="1">
        <input type="hidden" name="department" value="<?php echo app_escape($selected_department); ?>">
        <div class="form-row">
            <label for="employee">Employee</label>
            <select id="employee" name="employee" required onchange="submitQuarterEmployeeForm()">
                <option value="">Select an employee</option>
                <?php foreach ($employees as $employee): ?>
                    <?php $employee_code = trim((string) ($employee['employee_code'] ?? '')); ?>
                    <option value="<?php echo app_escape($employee_code); ?>" <?php echo $selected_employee === $employee_code ? 'selected' : ''; ?>>
                        <?php echo app_escape($employee_code . ' - ' . $employee['first_name']) . (isset($evaluated_employee_lookup[strtolower($employee_code)]) ? ' &#10004;' : ''); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
<?php endif; ?>

<?php if ($selected_employee_record !== null): ?>
    <div class="stack-gap">
        <section class="summary-card">
            <strong>Employee Overview</strong>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Job</th>
                            <th>Employment Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><img src="images/employees/<?php echo app_escape($selected_employee_record['employee_code']); ?>.png" alt="Employee Photo" class="employee-photo"></td>
                            <td><?php echo app_escape($selected_employee_record['employee_code']); ?></td>
                            <td><?php echo app_escape($selected_employee_record['first_name']); ?></td>
                            <td><?php echo app_escape($selected_employee_record['department']); ?></td>
                            <td><?php echo app_escape($selected_employee_record['job']); ?></td>
                            <td><?php echo app_escape($selected_employee_record['employment_date']); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <form method="POST" action="quarter_evaluation.php" id="quarterEvaluationForm">
            <input type="hidden" name="department" value="<?php echo app_escape($selected_department); ?>">
            <input type="hidden" name="employee" value="<?php echo app_escape($selected_employee); ?>">

            <div class="summary-grid">
                <div class="summary-card"><strong>Total Score</strong><p id="quarterTotalScore"><?php echo $total_score; ?> / 100</p></div>
                <div class="summary-card"><strong>Average</strong><p id="quarterAverageScore"><?php echo app_escape((string) $average_score); ?> / 10</p></div>
                <div class="summary-card"><strong>Status</strong><p><?php echo $previous_evaluation === [] ? 'Not evaluated yet' : 'Saved for this quarter'; ?></p></div>
            </div>

            <section class="summary-card">
                <strong>Experience Level</strong>
                <p>Choose the experience level before saving the quarterly review.</p>
                <div class="form-row">
                    <label><input type="radio" name="exp" value="A" <?php echo (($previous_evaluation['exp'] ?? '') === 'A') ? 'checked' : ''; ?> required> A</label>
                    <label><input type="radio" name="exp" value="B" <?php echo (($previous_evaluation['exp'] ?? '') === 'B') ? 'checked' : ''; ?>> B</label>
                    <label><input type="radio" name="exp" value="C" <?php echo (($previous_evaluation['exp'] ?? '') === 'C') ? 'checked' : ''; ?>> C</label>
                </div>
            </section>

            <section class="summary-card">
                <strong>Evaluation Elements</strong>
                <p>Rate each performance element from 1 to 10. The total and average update as you score.</p>
                <div class="table-scroll">
                    <table id="quarterEvaluationTable">
                        <thead>
                            <tr>
                                <th>Element</th>
                                <?php for ($score = 1; $score <= 10; $score++): ?>
                                    <th><?php echo $score; ?></th>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($criteria as $field => $label): ?>
                                <tr>
                                    <td><?php echo app_escape($label); ?></td>
                                    <?php for ($score = 1; $score <= 10; $score++): ?>
                                        <td>
                                            <input type="radio" name="<?php echo app_escape($field); ?>" value="<?php echo $score; ?>" <?php echo ((int) ($previous_evaluation[$field] ?? 0) === $score) ? 'checked' : ''; ?> required onchange="updateQuarterScore()">
                                        </td>
                                    <?php endfor; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="panel-actions">
                    <button type="submit" name="save_evaluation" value="1">Save Evaluation</button>
                    <button type="submit" name="clear_evaluation" value="1">Clear Evaluation</button>
                </div>
            </section>
        </form>
    </div>
<?php elseif ($selected_department !== ''): ?>
    <div class="message-card">Choose an employee from the selected department to load the quarterly evaluation form.</div>
<?php else: ?>
    <div class="message-card">Choose a department to start the quarterly evaluation workflow.</div>
<?php endif; ?>
<?php
app_close_content_panel();
app_render_page_end();
?>
<script>
function submitQuarterDepartmentForm() {
    document.getElementById('quarterDepartmentForm').submit();
}

function submitQuarterEmployeeForm() {
    document.getElementById('quarterEmployeeForm').submit();
}

function updateQuarterScore() {
    const totalNode = document.getElementById('quarterTotalScore');
    const averageNode = document.getElementById('quarterAverageScore');
    if (!totalNode || !averageNode) {
        return;
    }
    let total = 0;
    let count = 0;
    document.querySelectorAll('#quarterEvaluationTable tbody tr').forEach((row) => {
        const checked = row.querySelector('input[type="radio"]:checked');
        if (checked) {
            total += parseInt(checked.value, 10);
            count += 1;
        }
    });
    totalNode.textContent = total + ' / 100';
    averageNode.textContent = (count ? (total / count).toFixed(1) : '0.0') + ' / 10';
}

updateQuarterScore();
</script>
</body>
</html>
