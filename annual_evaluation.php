<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';
include 'includes/app_helpers.php';

function annual_evaluation_render_state_page(string $title, string $message, array $cards = []): void
{
    app_render_state_page('EV', 'Annual Evaluation', 'Yearly performance review workspace', 'Evaluation status', $title, $message, [
        ['label' => 'Home', 'href' => 'welcome.php'],
        ['label' => 'Evaluation', 'href' => 'evaluation.php'],
        ['label' => 'Logout', 'href' => 'logout.php'],
    ], array_merge([
        ['title' => 'Workspace', 'text' => 'Annual review'],
        ['title' => 'Next Step', 'text' => 'Return when the yearly window is available'],
    ], $cards), [
        ['label' => 'Back To Evaluation Home', 'href' => 'evaluation.php'],
        ['label' => 'Dashboard', 'href' => 'welcome.php'],
    ], 'Evaluation Status', 'This annual evaluation workspace is unavailable right now, but you can still navigate to the rest of the app.');
}

function annual_evaluation_fetch_previous(mysqli $conn, string $employee_code, int $year): array
{
    $evaluation_sql = 'SELECT * FROM annual_evaluation WHERE employee_code = ? AND year = ? ORDER BY evaluation_date DESC LIMIT 1';
    $stmt = $conn->prepare($evaluation_sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('si', $employee_code, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    $record = $result->num_rows > 0 ? ($result->fetch_assoc() ?: []) : [];
    $stmt->close();

    return $record;
}

function annual_evaluation_fetch_employee(mysqli $conn, string $employee_code): ?array
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
    annual_evaluation_render_state_page('Unauthorized', 'Please sign in again to continue.', [
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

$rule_sql = "SELECT year, eva_start, eva_end FROM rules WHERE name = 'eva_y' LIMIT 1";
$rule_result = $conn->query($rule_sql);
if (!$rule_result || $rule_result->num_rows === 0) {
    annual_evaluation_render_state_page('Configuration Missing', 'The annual evaluation rule could not be loaded.', [
        ['title' => 'Rule Name', 'text' => 'eva_y'],
    ]);
}
$rule = $rule_result->fetch_assoc();
$year = (int) $rule['year'];
$eva_start = (string) $rule['eva_start'];
$eva_end = (string) $rule['eva_end'];

try {
    $tz = new DateTimeZone('Africa/Cairo');
    $now = new DateTime('now', $tz);
    $start = new DateTime($eva_start, $tz);
    $end = new DateTime($eva_end, $tz);
} catch (Exception $exception) {
    annual_evaluation_render_state_page('Configuration Error', 'The annual evaluation period is not configured correctly.', [
        ['title' => 'Rule Name', 'text' => 'eva_y'],
    ]);
}

if ($now < $start) {
    annual_evaluation_render_state_page('Evaluation Not Started', 'Annual evaluation starts on ' . $start->format('Y-m-d H:i:s') . '.', [
        ['title' => 'Year', 'text' => (string) $year],
        ['title' => 'Starts', 'text' => $start->format('Y-m-d H:i:s')],
    ]);
}
if ($now > $end) {
    annual_evaluation_render_state_page('Evaluation Closed', 'The annual evaluation period ended on ' . $end->format('Y-m-d H:i:s') . '.', [
        ['title' => 'Year', 'text' => (string) $year],
        ['title' => 'Ended', 'text' => $end->format('Y-m-d H:i:s')],
    ]);
}

$criteria = [
    'ela1' => ['group' => 'Work Environment', 'label' => 'Exhibits the required level of knowledge and skills to perform the job.'],
    'ela2' => ['group' => 'Work Environment', 'label' => 'Uses established techniques correctly.'],
    'ela3' => ['group' => 'Work Environment', 'label' => 'Uses materials and equipment properly.'],
    'ela4' => ['group' => 'Quality Of Work', 'label' => 'Completes assigned tasks without errors.'],
    'ela5' => ['group' => 'Quality Of Work', 'label' => 'Improves the quality of work.'],
    'ela6' => ['group' => 'Quality Of Work', 'label' => 'Meets job requirements.'],
    'ela7' => ['group' => 'Quality Of Work', 'label' => 'Analyzes problems and determines appropriate solutions.'],
    'ela8' => ['group' => 'Quality Of Work', 'label' => 'Accepts changes in duties and work procedures.'],
    'ela9' => ['group' => 'Quality Of Work', 'label' => 'Develops and applies new techniques or approaches.'],
    'ela10' => ['group' => 'Quality Of Work', 'label' => 'Complies with work rules and organizational policies.'],
    'ela11' => ['group' => 'Quality Of Work', 'label' => 'Prioritizes tasks according to the company best interest.'],
    'ela12' => ['group' => 'Quality Of Work', 'label' => 'Shows enthusiasm for work.'],
    'ela13' => ['group' => 'Punctuality And Planning', 'label' => 'Follows arrival, departure, and break times.'],
    'ela14' => ['group' => 'Punctuality And Planning', 'label' => 'Handles absences and permissions correctly.'],
    'ela15' => ['group' => 'Punctuality And Planning', 'label' => 'Works extra hours when work needs require it.'],
    'ela16' => ['group' => 'Punctuality And Planning', 'label' => 'Shows good planning and organization.'],
    'ela17' => ['group' => 'Workplace Behavior', 'label' => 'Assists co-workers in accomplishing their tasks.'],
    'ela18' => ['group' => 'Workplace Behavior', 'label' => 'Shows ethical behavior in the work environment.'],
    'ela19' => ['group' => 'Workplace Behavior', 'label' => 'Improves behavior and acts as a role model.'],
    'ela20' => ['group' => 'Workplace Behavior', 'label' => 'Shows frankness and seriousness with supervisors and co-workers.'],
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
            $previous_evaluation = annual_evaluation_fetch_previous($conn, $selected_employee, $year);
        }
    } elseif (isset($_POST['clear_evaluation'])) {
        if ($selected_employee !== '') {
            $delete_sql = 'DELETE FROM annual_evaluation WHERE employee_code = ? AND year = ?';
            $stmt = $conn->prepare($delete_sql);
            if ($stmt) {
                $stmt->bind_param('si', $selected_employee, $year);
                $stmt->execute();
                $stmt->close();
                $flash_messages[] = 'Annual evaluation cleared successfully.';
            }
        }
    } elseif (isset($_POST['save_evaluation'])) {
        $ratings = [];
        foreach (array_keys($criteria) as $field) {
            $value = filter_input(INPUT_POST, $field, FILTER_VALIDATE_INT);
            if ($value === false || $value < 1 || $value > 5) {
                annual_evaluation_render_state_page('Invalid Rating', 'Each annual evaluation element must be rated from 1 to 5.');
            }
            $ratings[$field] = $value;
        }

        $employee_info_sql = 'SELECT job, employment_date, first_name FROM eva_list WHERE employee_code = ?';
        $stmt = $conn->prepare($employee_info_sql);
        if (!$stmt) {
            annual_evaluation_render_state_page('Database Error', 'Employee information could not be loaded.');
        }
        $stmt->bind_param('s', $selected_employee);
        $stmt->execute();
        $employee_info_result = $stmt->get_result();
        if ($employee_info_result->num_rows === 0) {
            $stmt->close();
            annual_evaluation_render_state_page('Employee Missing', 'The selected employee could not be found in the evaluation list.');
        }
        $employee_info = $employee_info_result->fetch_assoc();
        $stmt->close();

        $exp = trim((string) ($_POST['exp'] ?? ''));
        if (!in_array($exp, ['A', 'B', 'C', 'D'], true)) {
            annual_evaluation_render_state_page('Invalid Experience Level', 'Choose a valid experience level before saving.');
        }

        $evaluation_check_sql = 'SELECT employee_code FROM annual_evaluation WHERE employee_code = ? AND year = ?';
        $stmt = $conn->prepare($evaluation_check_sql);
        if (!$stmt) {
            annual_evaluation_render_state_page('Database Error', 'The existing annual evaluation could not be checked.');
        }
        $stmt->bind_param('si', $selected_employee, $year);
        $stmt->execute();
        $evaluation_check_result = $stmt->get_result();
        $exists = $evaluation_check_result->num_rows > 0;
        $stmt->close();

        if ($exists) {
            $sql = 'UPDATE annual_evaluation SET ela1=?, ela2=?, ela3=?, ela4=?, ela5=?, ela6=?, ela7=?, ela8=?, ela9=?, ela10=?, ela11=?, ela12=?, ela13=?, ela14=?, ela15=?, ela16=?, ela17=?, ela18=?, ela19=?, ela20=?, exp=?, evaluated_by=?, evaluation_date=NOW() WHERE employee_code=? AND year=?';
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('iiiiiiiiiiiiiiiiiiiisssi', $ratings['ela1'], $ratings['ela2'], $ratings['ela3'], $ratings['ela4'], $ratings['ela5'], $ratings['ela6'], $ratings['ela7'], $ratings['ela8'], $ratings['ela9'], $ratings['ela10'], $ratings['ela11'], $ratings['ela12'], $ratings['ela13'], $ratings['ela14'], $ratings['ela15'], $ratings['ela16'], $ratings['ela17'], $ratings['ela18'], $ratings['ela19'], $ratings['ela20'], $exp, $username, $selected_employee, $year);
                $stmt->execute();
                $stmt->close();
                $flash_messages[] = 'Annual evaluation updated successfully.';
            }
        } else {
            $sql = 'INSERT INTO annual_evaluation (employee_code, employee_name, department, job, employment_date, ela1, ela2, ela3, ela4, ela5, ela6, ela7, ela8, ela9, ela10, ela11, ela12, ela13, ela14, ela15, ela16, ela17, ela18, ela19, ela20, exp, evaluated_by, year, evaluation_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())';
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('sssssiiiiiiiiiiiiiiiiiiiissi', $selected_employee, $employee_info['first_name'], $selected_department, $employee_info['job'], $employee_info['employment_date'], $ratings['ela1'], $ratings['ela2'], $ratings['ela3'], $ratings['ela4'], $ratings['ela5'], $ratings['ela6'], $ratings['ela7'], $ratings['ela8'], $ratings['ela9'], $ratings['ela10'], $ratings['ela11'], $ratings['ela12'], $ratings['ela13'], $ratings['ela14'], $ratings['ela15'], $ratings['ela16'], $ratings['ela17'], $ratings['ela18'], $ratings['ela19'], $ratings['ela20'], $exp, $username, $year);
                $stmt->execute();
                $stmt->close();
                $flash_messages[] = 'Annual evaluation submitted successfully.';
            }
        }

        $previous_evaluation = annual_evaluation_fetch_previous($conn, $selected_employee, $year);
    }
}

if ($previous_evaluation === [] && $selected_employee !== '') {
    $previous_evaluation = annual_evaluation_fetch_previous($conn, $selected_employee, $year);
}

$employees = [];
if ($selected_department !== '') {
    $employees_sql = 'SELECT employee_code, first_name, department, job, employment_date FROM eva_list WHERE department = ? ORDER BY first_name';
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

$evaluated_employees = [];
$evaluated_sql = 'SELECT DISTINCT employee_code FROM annual_evaluation WHERE year = ?';
$stmt = $conn->prepare($evaluated_sql);
if ($stmt) {
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $evaluated_result = $stmt->get_result();
    while ($row = $evaluated_result->fetch_assoc()) {
        $evaluated_employees[] = $row['employee_code'];
    }
    $stmt->close();
}

$selected_employee_record = null;
foreach ($employees as $employee) {
    if ($employee['employee_code'] === $selected_employee) {
        $selected_employee_record = $employee;
        break;
    }
}

if ($selected_employee_record === null && $selected_employee !== '') {
    $selected_employee_record = annual_evaluation_fetch_employee($conn, $selected_employee);
    if ($selected_employee_record !== null && $selected_department === '') {
        $selected_department = (string) $selected_employee_record['department'];
    }
}

$total_score = 0;
foreach (array_keys($criteria) as $field) {
    $total_score += (int) ($previous_evaluation[$field] ?? 0);
}
$percentage_score = round(($total_score / 100) * 100, 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Annual Evaluation</title>
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="icon" type="image/png" href="images/logo.png">
</head>
<body>
<?php
app_render_page_header('EV', 'Annual Evaluation', 'Run the yearly performance review from a cleaner, structured workflow.', [
    ['label' => 'Home', 'href' => 'welcome.php'],
    ['label' => 'Evaluation', 'href' => 'evaluation.php'],
    ['label' => 'Logout', 'href' => 'logout.php'],
]);
app_render_page_hero('Annual review', 'A source-level rewrite with grouped criteria, live total scoring, and aligned validation.', 'The annual review page now renders from structured criteria definitions instead of hundreds of repeated legacy lines, while keeping the same database tables and submission flow.', [
    ['title' => 'Year', 'text' => (string) $year],
    ['title' => 'Start', 'text' => $eva_start],
    ['title' => 'End', 'text' => $eva_end],
]);
app_open_content_panel('Choose Department And Employee', 'Select a department and employee to open the annual review form for the active year.');
?>
<?php foreach ($flash_messages as $message): ?>
    <div class="message-card"><?php echo app_escape($message); ?></div>
<?php endforeach; ?>

<div class="summary-grid">
    <div class="summary-card"><strong>Accessible Departments</strong><p><?php echo count($allowed_departments); ?></p></div>
    <div class="summary-card"><strong>Evaluated Employees</strong><p><?php echo count($evaluated_employees); ?></p></div>
    <div class="summary-card"><strong>Window Start</strong><p><?php echo app_escape($eva_start); ?></p></div>
    <div class="summary-card"><strong>Window End</strong><p><?php echo app_escape($eva_end); ?></p></div>
</div>

<form id="annualDepartmentForm" method="POST" action="annual_evaluation.php">
    <input type="hidden" name="select_department" value="1">
    <div class="form-row">
        <label for="department">Department</label>
        <select id="department" name="department" required onchange="submitAnnualDepartmentForm()">
            <option value="">Select a department</option>
            <?php foreach ($allowed_departments as $department): ?>
                <option value="<?php echo app_escape($department); ?>" <?php echo $selected_department === $department ? 'selected' : ''; ?>><?php echo app_escape($department); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<?php if ($selected_department !== ''): ?>
    <form id="annualEmployeeForm" method="POST" action="annual_evaluation.php">
        <input type="hidden" name="select_employee" value="1">
        <input type="hidden" name="department" value="<?php echo app_escape($selected_department); ?>">
        <div class="form-row">
            <label for="employee">Employee</label>
            <select id="employee" name="employee" required onchange="submitAnnualEmployeeForm()">
                <option value="">Select an employee</option>
                <?php foreach ($employees as $employee): ?>
                    <option value="<?php echo app_escape($employee['employee_code']); ?>" <?php echo $selected_employee === $employee['employee_code'] ? 'selected' : ''; ?>>
                        <?php echo app_escape($employee['employee_code'] . ' - ' . $employee['first_name'] . (in_array($employee['employee_code'], $evaluated_employees, true) ? ' [Done]' : '')); ?>
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
                            <th>Code</th>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Job</th>
                            <th>Employment Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
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

        <form method="POST" action="annual_evaluation.php" id="annualEvaluationForm">
            <input type="hidden" name="department" value="<?php echo app_escape($selected_department); ?>">
            <input type="hidden" name="employee" value="<?php echo app_escape($selected_employee); ?>">

            <div class="summary-grid">
                <div class="summary-card"><strong>Total Score</strong><p id="annualTotalScore"><?php echo $total_score; ?> / 100</p></div>
                <div class="summary-card"><strong>Completion</strong><p id="annualPercentScore"><?php echo app_escape((string) $percentage_score); ?>%</p></div>
                <div class="summary-card"><strong>Status</strong><p><?php echo $previous_evaluation === [] ? 'Not evaluated yet' : 'Saved for this year'; ?></p></div>
            </div>

            <section class="summary-card">
                <strong>Experience Level</strong>
                <p>Annual reviews support four experience levels, including the out-of-expectation option.</p>
                <div class="form-row">
                    <label><input type="radio" name="exp" value="A" <?php echo (($previous_evaluation['exp'] ?? '') === 'A') ? 'checked' : ''; ?> required> A</label>
                    <label><input type="radio" name="exp" value="B" <?php echo (($previous_evaluation['exp'] ?? '') === 'B') ? 'checked' : ''; ?>> B</label>
                    <label><input type="radio" name="exp" value="C" <?php echo (($previous_evaluation['exp'] ?? '') === 'C') ? 'checked' : ''; ?>> C</label>
                    <label><input type="radio" name="exp" value="D" <?php echo (($previous_evaluation['exp'] ?? '') === 'D') ? 'checked' : ''; ?>> D</label>
                </div>
            </section>

            <section class="summary-card">
                <strong>Evaluation Elements</strong>
                <p>Rate each annual performance element from 1 to 5. Group headings keep the longer form easier to navigate.</p>
                <div class="table-scroll">
                    <table id="annualEvaluationTable">
                        <thead>
                            <tr>
                                <th>Element</th>
                                <?php for ($score = 1; $score <= 5; $score++): ?>
                                    <th><?php echo $score; ?></th>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $current_group = ''; ?>
                            <?php foreach ($criteria as $field => $config): ?>
                                <?php if ($current_group !== $config['group']): ?>
                                    <?php $current_group = $config['group']; ?>
                                    <tr>
                                        <td colspan="6"><strong><?php echo app_escape($current_group); ?></strong></td>
                                    </tr>
                                <?php endif; ?>
                                <tr>
                                    <td><?php echo app_escape($config['label']); ?></td>
                                    <?php for ($score = 1; $score <= 5; $score++): ?>
                                        <td>
                                            <input type="radio" name="<?php echo app_escape($field); ?>" value="<?php echo $score; ?>" <?php echo ((int) ($previous_evaluation[$field] ?? 0) === $score) ? 'checked' : ''; ?> required onchange="updateAnnualScore()">
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
    <div class="message-card">Choose an employee from the selected department to load the annual review form.</div>
<?php else: ?>
    <div class="message-card">Choose a department to begin the annual evaluation workflow.</div>
<?php endif; ?>
<?php
app_close_content_panel();
app_render_page_end();
?>
<script>
function submitAnnualDepartmentForm() {
    document.getElementById('annualDepartmentForm').submit();
}

function submitAnnualEmployeeForm() {
    document.getElementById('annualEmployeeForm').submit();
}

function updateAnnualScore() {
    const totalNode = document.getElementById('annualTotalScore');
    const percentNode = document.getElementById('annualPercentScore');
    if (!totalNode || !percentNode) {
        return;
    }
    let total = 0;
    document.querySelectorAll('#annualEvaluationTable tbody tr').forEach((row) => {
        const checked = row.querySelector('input[type="radio"]:checked');
        if (checked) {
            total += parseInt(checked.value, 10);
        }
    });
    totalNode.textContent = total + ' / 100';
    percentNode.textContent = total.toFixed(1) + '%';
}

updateAnnualScore();
</script>
</body>
</html>
