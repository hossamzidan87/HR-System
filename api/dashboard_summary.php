<?php
include __DIR__ . '/../check_cookies.php';
include __DIR__ . '/../db_connection.php';
include __DIR__ . '/../includes/app_helpers.php';

header('Content-Type: application/json; charset=UTF-8');

$username = $_SESSION['username'];
$accessMap = app_get_access_map($conn, $username);
$modules = app_get_dashboard_modules($accessMap, $username);

$allowedDepartments = app_get_allowed_departments($conn, $username);
$departmentsIn = '';
if (!app_is_admin($username) && !empty($allowedDepartments)) {
    $escapedDepartments = array_map([$conn, 'real_escape_string'], $allowedDepartments);
    $departmentsIn = "'" . implode("','", $escapedDepartments) . "'";
}

function app_scalar_query(mysqli $conn, string $sql, string $types = '', array $params = [])
{
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return null;
    }

    if ($types !== '' && $params) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $value = null;

    if ($result) {
        $row = $result->fetch_row();
        $value = $row[0] ?? null;
    }

    $stmt->close();

    return $value;
}

$today = date('Y-m-d');
$now = date('Y-m-d H:i:s');
$currentQuarter = null;
$currentYear = (int) date('Y');

$quarterStmt = $conn->prepare("SELECT quarter, year FROM rules WHERE name = 'eva_q' LIMIT 1");
if ($quarterStmt) {
    $quarterStmt->execute();
    $quarterResult = $quarterStmt->get_result();
    if ($quarterResult) {
        $quarterRow = $quarterResult->fetch_assoc();
        $currentQuarter = $quarterRow['quarter'] ?? null;
        $currentYear = isset($quarterRow['year']) ? (int) $quarterRow['year'] : $currentYear;
    }
    $quarterStmt->close();
}

$employeesWhere = '';
$overtimeWhere = "WHERE overtime_date = ? AND types = 'normal'";
$nightShiftWhere = "WHERE ? BETWEEN start_date AND end_date";
$evaluationsWhere = "";
$paramsForOvertime = [$today];
$paramsForNightShift = [$today];

$applyDepartmentFilter = !app_is_admin($username);
if ($applyDepartmentFilter) {
    if ($departmentsIn !== '') {
        $employeesWhere = "WHERE department IN ($departmentsIn)";
        $overtimeWhere .= " AND department IN ($departmentsIn)";
        $nightShiftWhere .= " AND department IN ($departmentsIn)";
        $evaluationsWhere = " AND department IN ($departmentsIn)";
    } else {
        // User has no allowed departments; return empty counts
        $employeesWhere = 'WHERE 1=0';
        $overtimeWhere = 'WHERE 1=0';
        $nightShiftWhere = 'WHERE 1=0';
        $evaluationsWhere = ' AND 1=0';
    }
}

$stats = [
    'employees' => (int) (app_scalar_query($conn, "SELECT COUNT(*) FROM employees $employeesWhere") ?? 0),
    'todayOvertime' => (int) (app_scalar_query($conn, "SELECT COUNT(*) FROM overtime $overtimeWhere", 's', $paramsForOvertime) ?? 0),
    'activeNightShift' => (int) (app_scalar_query($conn, "SELECT COUNT(*) FROM night_shift $nightShiftWhere", 's', $paramsForNightShift) ?? 0),
    'monthlySalaryAdvanceWindow' => (bool) (app_scalar_query($conn, "SELECT COUNT(*) FROM rules WHERE name = 'close_time' AND ? BETWEEN sadv_start AND sadv_end", 's', [$now]) ?? 0),
];

if ($currentQuarter) {
    $stats['quarterEvaluations'] = (int) (app_scalar_query(
        $conn,
        "SELECT COUNT(DISTINCT employee_code) FROM evaluations WHERE quarter = ? AND year = ?" . $evaluationsWhere,
        'si',
        [$currentQuarter, $currentYear]
    ) ?? 0);
} else {
    $stats['quarterEvaluations'] = 0;
}

$summaryCards = [
    ['label' => 'Employees', 'value' => $stats['employees']],
    ['label' => 'Overtime Today', 'value' => $stats['todayOvertime']],
    ['label' => 'Active Night Shift', 'value' => $stats['activeNightShift']],
    ['label' => 'Evaluations', 'value' => $stats['quarterEvaluations']],
];

echo json_encode([
    'generatedAt' => date(DATE_ATOM),
    'user' => [
        'name' => $username,
        'isAdmin' => app_is_admin($username),
    ],
    'status' => [
        'salaryAdvanceWindow' => $stats['monthlySalaryAdvanceWindow'],
        'currentQuarter' => $currentQuarter,
        'currentYear' => $currentYear,
    ],
    'summaryCards' => $summaryCards,
    'modules' => $modules,
], JSON_UNESCAPED_SLASHES);
