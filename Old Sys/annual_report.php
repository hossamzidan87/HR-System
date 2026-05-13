<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';

if (!isset($_SESSION['username'])) {
    die("Unauthorized access. Please log in.");
}
$username = $_SESSION['username'];

$message = ''; // initialize message to avoid undefined variable warnings

// Fetch user groups and allowed departments (reuse pattern)
$user_groups = [];
$stmt = $conn->prepare("SELECT group_name_1, group_name_2, group_name_3 FROM user_permissions WHERE username = ?");
if ($stmt) {
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $perm = $stmt->get_result();
    if ($perm && $perm->num_rows > 0) {
        $row = $perm->fetch_assoc();
        foreach (['group_name_1','group_name_2','group_name_3'] as $g) {
            if (!empty($row[$g])) $user_groups[] = $row[$g];
        }
    }
    $stmt->close();
} else {
    die("Database error: " . $conn->error);
}

$allowed_departments = [];
if (!empty($user_groups)) {
    // Safely escape group names. If $conn is unavailable, fallback to addslashes.
    if (isset($conn) && $conn instanceof mysqli) {
        $escaped_groups = array_map(function($g) use ($conn){ return $conn->real_escape_string($g); }, $user_groups);
    } else {
        $escaped_groups = array_map('addslashes', $user_groups);
    }
    $groups_in = implode("','", $escaped_groups);
    $sql = "SELECT DISTINCT department FROM department_groups WHERE group_name IN ('$groups_in') OR group_name1 IN ('$groups_in') OR group_name2 IN ('$groups_in')";
    $res = isset($conn) ? $conn->query($sql) : false;
    if ($res) {
        while ($r = $res->fetch_assoc()) $allowed_departments[] = $r['department'];
    } else {
        die("Database error: " . (isset($conn) ? $conn->error : 'No DB connection'));
    }
}

// Available years: from annual_evaluation and fallback to rules.eva_y
$available_years = [];
$res = $conn->query("SELECT DISTINCT year FROM annual_evaluation ORDER BY year DESC");
if ($res) {
    while ($r = $res->fetch_assoc()) $available_years[] = (int)$r['year'];
}
$stmt = $conn->prepare("SELECT year FROM rules WHERE name = 'eva_y' LIMIT 1");
$eva_y = null;
if ($stmt) {
    $stmt->execute();
    $r = $stmt->get_result();
    if ($r && $r->num_rows > 0) $eva_y = (int)$r->fetch_assoc()['year'];
    $stmt->close();
}
if ($eva_y && !in_array($eva_y, $available_years)) $available_years[] = $eva_y;
sort($available_years);
$available_years = array_reverse($available_years);

// Handle form submission / selection
$selected_year = isset($_POST['year']) ? (int)$_POST['year'] : ($eva_y ?: (count($available_years)?$available_years[0]:null));
$selected_department = isset($_POST['department']) ? trim($_POST['department']) : '';

// Validate department against allowed list; if empty => include all allowed
if ($selected_department !== '' && !in_array($selected_department, $allowed_departments)) {
    $selected_department = ''; // ignore unauthorized selection
}

// Fetch data if year is selected
$evaluations = [];
if ($selected_year) {
    if ($selected_department === '') {
        // fetch for all allowed departments
        if (empty($allowed_departments)) {
            $message = "You do not have permission to view any departments.";
        } else {
            // prepare placeholders
            $placeholders = implode(',', array_fill(0, count($allowed_departments), '?'));
            $types = str_repeat('s', count($allowed_departments)) . 'i';
            // build query with parameterized IN (we'll bind dynamically)
            // Order by department first so rows are grouped by department by default
            $sql = "SELECT * FROM annual_evaluation WHERE department IN ($placeholders) AND year = ? ORDER BY department, employee_name";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                // bind dynamic parameters
                $bind_names = [];
                $bind_values = [];
                // prepare references
                foreach ($allowed_departments as $idx => $dept) { $bind_values[] = $dept; }
                $bind_values[] = $selected_year;
                $refs = [];
                foreach ($bind_values as $k => $v) $refs[$k] = &$bind_values[$k];
                array_unshift($refs, $types);
                call_user_func_array(array($stmt, 'bind_param'), $refs);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($r = $res->fetch_assoc()) $evaluations[] = $r;
                $stmt->close();
            } else {
                $message = "Database error: " . $conn->error;
            }
        }
    } else {
        $stmt = $conn->prepare("SELECT * FROM annual_evaluation WHERE year = ? AND department = ? ORDER BY employee_name");
        if ($stmt) {
            $stmt->bind_param("is", $selected_year, $selected_department);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) $evaluations[] = $r;
            $stmt->close();
        } else {
            $message = "Database error: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Annual Evaluation Report</title>
    <style>
        .container { width: 90%; margin: 0 auto; text-align: center; padding: 20px; }
        .form-group { margin: 12px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { padding: 8px; border: 1px solid #ddd; text-align: left; font-size: 13px; }
        th { background: #f2f2f2; }
        .image-container { display: flex; justify-content: flex-end; align-items: center; }
        .image-link { border: 1px solid #ddd; border-radius: 4px; padding: 5px; width: 25px; margin: 0 5px; display: inline-block; }
        .image-link:hover { box-shadow: 0 0 2px 1px rgba(0, 140, 186, 0.5); }
        .image-link img { width: 100%; height: auto; display: block; }
    </style>
</head>
<body>
<div class="image-container">
    <div class="image-link"><a href="welcome.php"><img src="/images/icons/home.png" alt="home"></a></div>
    <div class="image-link"><a href="evaluation.php"><img src="/images/icons/evaluation.png" alt="evaluation"></a></div>
    <div class="image-link"><a href="logout.php"><img src="/images/icons/logout.png" alt="logout"></a></div>
</div>

<div class="container">
    <h1>Annual Evaluation Report</h1>

    <form method="POST" action="annual_report.php">
        <div class="form-group">
            <label for="year">Select Year:</label>
            <select name="year" id="year" onchange="this.form.submit()" required>
                <option value="">--Select Year--</option>
                <?php foreach ($available_years as $y): ?>
                    <option value="<?php echo $y; ?>" <?php echo ($y == $selected_year) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="department">Select Department:</label>
            <select name="department" id="department" onchange="this.form.submit()">
                <option value="">--All Allowed Departments--</option>
                <?php foreach ($allowed_departments as $dept): ?>
                    <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo ($dept === $selected_department) ? 'selected' : ''; ?>><?php echo htmlspecialchars($dept); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <?php if (!empty($message)): ?>
        <p><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <?php if ($selected_year && empty($evaluations)): ?>
        <p>No evaluations found for year <?php echo htmlspecialchars($selected_year); ?><?php echo $selected_department ? " in department " . htmlspecialchars($selected_department) : ""; ?>.</p>
    <?php elseif (!empty($evaluations)): ?>
        <div class="summary">
            Total evaluated employees: <?php echo count($evaluations); ?>
        </div>
        <table id="annualReportTable" data-sort-order="asc">
             <thead>
                 <tr>
                     <th onclick="sortTable('annualReportTable', 0)" style="cursor:pointer;">Photo ▾</th>
                     <th onclick="sortTable('annualReportTable', 1)" style="cursor:pointer;">Employee Code ▾</th>
                     <th onclick="sortTable('annualReportTable', 2)" style="cursor:pointer;">Employee Name ▾</th>
                     <th onclick="sortTable('annualReportTable', 3)" style="cursor:pointer;">Department ▾</th>
                     <th onclick="sortTable('annualReportTable', 4)" style="cursor:pointer;">Job ▾</th>
                     <th onclick="sortTable('annualReportTable', 5)" style="cursor:pointer;">Employment Date ▾</th>
                     <th onclick="sortTable('annualReportTable', 6)" style="cursor:pointer;">Experience ▾</th>
                     <th onclick="sortTable('annualReportTable', 7)" style="cursor:pointer;">Total Score ▾</th>
                     <th onclick="sortTable('annualReportTable', 8)" style="cursor:pointer;">Evaluated By ▾</th>
                     <th onclick="sortTable('annualReportTable', 9)" style="cursor:pointer;">Evaluation Date ▾</th>
                 </tr>
             </thead>
             <tbody>
                 <?php foreach ($evaluations as $ev): 
                     // sum ela1..ela20 safely
                     $total = 0;
                     for ($i = 1; $i <= 20; $i++) {
                         $k = 'ela'.$i;
                         $total += isset($ev[$k]) ? (int)$ev[$k] : 0;
                     }
                 ?>
                 <tr>
                    <td><img src="images/employees/<?php echo htmlspecialchars($ev['employee_code']); ?>.png" alt="photo" style="width:60px;height:auto;"></td>
                    <td><?php echo htmlspecialchars($ev['employee_code']); ?></td>
                    <td><?php echo htmlspecialchars($ev['employee_name']); ?></td>
                    <td><?php echo htmlspecialchars($ev['department']); ?></td>
                    <td><?php echo htmlspecialchars($ev['job']); ?></td>
                    <td><?php echo htmlspecialchars($ev['employment_date']); ?></td>
                    <td><?php echo htmlspecialchars($ev['exp']); ?></td>
                    <td><?php echo $total; ?> / 100</td>
                    <td><?php echo htmlspecialchars($ev['evaluated_by']); ?></td>
                    <td><?php echo htmlspecialchars($ev['evaluation_date']); ?></td>
                 </tr>
                 <?php endforeach; ?>
             </tbody>
         </table>
         <script>
            // Simple client-side table sorter (numeric-aware)
            function sortTable(tableId, columnIndex) {
                const table = document.getElementById(tableId);
                const tbody = table.tBodies[0];
                const rows = Array.from(tbody.rows);
                const isAsc = table.getAttribute('data-sort-order') === 'asc';
                rows.sort((a, b) => {
                    const A = a.cells[columnIndex].innerText.trim();
                    const B = b.cells[columnIndex].innerText.trim();
                    const aNum = parseFloat(A.replace(/[^0-9.-]+/g, ''));
                    const bNum = parseFloat(B.replace(/[^0-9.-]+/g, ''));
                    if (!isNaN(aNum) && !isNaN(bNum)) return (aNum - bNum) * (isAsc ? 1 : -1);
                    return A.localeCompare(B) * (isAsc ? 1 : -1);
                });
                rows.forEach(r => tbody.appendChild(r));
                table.setAttribute('data-sort-order', isAsc ? 'desc' : 'asc');
            }
        </script>
    <?php endif; ?>
 </div>
</body>
</html>
