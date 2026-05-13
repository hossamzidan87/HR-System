<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';
include 'includes/app_helpers.php';

if (!isset($_SESSION['username']) || $_SESSION['username'] !== 'admin') {
    echo "Access Denied. You do not have permission to view this page.";
    exit;
}

$sadv_start = '';
$sadv_end = '';
$stmt = $conn->prepare("SELECT sadv_start, sadv_end FROM rules WHERE name = 'close_time' LIMIT 1");
if (!$stmt) {
    die("Database error: " . $conn->error);
}
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $sadv_start = $row['sadv_start'];
    $sadv_end = $row['sadv_end'];
}
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['sadv_start']) && isset($_POST['sadv_end'])) {
        $sadv_start = filter_input(INPUT_POST, 'sadv_start', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $sadv_end = filter_input(INPUT_POST, 'sadv_end', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        $stmt = $conn->prepare("UPDATE rules SET sadv_start = ?, sadv_end = ? WHERE name = 'close_time'");
        if (!$stmt) {
            die("Database error: " . $conn->error);
        }
        $stmt->bind_param("ss", $sadv_start, $sadv_end);
        if ($stmt->execute()) {
            echo "Opening and Closing times updated successfully!";
        } else {
            echo "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Adv Cpanel</title>
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="icon" type="image/png" href="images/logo.png">
</head>
<body>

<?php
app_render_page_header('SA', 'Salary Advance Cpanel', 'Configure the monthly opening and closing window for salary advance requests.', [
    ['label' => 'Home', 'href' => 'welcome.php'],
    ['label' => 'Cpanel', 'href' => 'cpanel.php'],
    ['label' => 'Logout', 'href' => 'logout.php'],
]);
app_render_page_hero('Admin area', 'Salary advance timing is now managed from the same modern admin shell.', 'Update the date range below to control when the salary advance list can be used.', [
    ['title' => 'Opening', 'text' => $sadv_start ?: 'Not set'],
    ['title' => 'Closing', 'text' => $sadv_end ?: 'Not set'],
]);
app_open_content_panel('Salary Advance Window', 'Save the opening and closing date-time values for the salary advance module.');
?>
<form method="POST" action="sadv_cpanel.php">
    <div class="form-row">
        <label for="sadv_start">Opening Time</label>
        <input type="datetime-local" id="sadv_start" name="sadv_start" value="<?php echo htmlspecialchars($sadv_start); ?>" required>
        <label for="sadv_end">Closing Time</label>
        <input type="datetime-local" id="sadv_end" name="sadv_end" value="<?php echo htmlspecialchars($sadv_end); ?>" required>
        <button type="submit">Update Times</button>
    </div>
</form>
<?php
app_close_content_panel();
app_render_page_end();
?>
<script src="assets/js/app.js"></script>
</body>
</html>
