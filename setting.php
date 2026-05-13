<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'includes/app_helpers.php';

$username = $_SESSION['username'];
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['old_password']) && isset($_POST['new_password']) && isset($_POST['confirm_password'])) {
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (strlen($new_password) < 8) {
        $message = "Error: New password must be at least 8 characters long.";
        $message_type = 'error';
    } elseif ($new_password !== $confirm_password) {
        $message = "Error: New passwords do not match.";
        $message_type = 'error';
    } else {
        $result = $conn->query("SELECT password FROM user_management WHERE username = '$username'");
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if (password_verify($old_password, $row['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_sql = "UPDATE user_management SET password = '$hashed_password' WHERE username = '$username'";
                if ($conn->query($update_sql) === TRUE) {
                    $message = "Password updated successfully!";
                    $message_type = 'success';
                } else {
                    $message = "Error: " . $conn->error;
                    $message_type = 'error';
                }
            } else {
                $message = "Error: Incorrect old password.";
                $message_type = 'error';
            }
        } else {
            $message = "Error: User not found.";
            $message_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings</title>
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="icon" type="image/png" href="images/logo.png">
</head>
<body>
<?php
app_render_page_header('ST', 'Settings', 'Manage your account settings.', [
    ['label' => 'Home', 'href' => 'welcome.php'],
    ['label' => 'Logout', 'href' => 'logout.php'],
]);
app_render_page_hero('Account', 'Update your personal preferences and security settings.', '', [
    ['title' => 'Password', 'text' => 'Change your password to keep your account secure.']
]);
app_open_content_panel('Security', 'Use the form below to change your password.');
?>
<div class="stack-gap">
    <?php if ($message): ?>
        <div class="summary-card" style="background-color: <?php echo $message_type === 'success' ? '#e6f4ea' : '#fce8e6'; ?>; border-color: <?php echo $message_type === 'success' ? '#34a853' : '#ea4335'; ?>;">
            <strong style="color: <?php echo $message_type === 'success' ? '#137333' : '#c5221f'; ?>;"><?php echo htmlspecialchars($message); ?></strong>
        </div>
    <?php endif; ?>
    <div class="summary-card">
        <strong>Change Password</strong>
        <form method="POST" action="setting.php">
            <div class="form-row">
                <label for="old_password">Old Password</label>
                <input type="password" id="old_password" name="old_password" required>
            </div>
            <div class="form-row" style="margin-top: 15px;">
                <label for="new_password">New Password (Min 8 chars)</label>
                <input type="password" id="new_password" name="new_password" minlength="8" required>
            </div>
            <div class="form-row" style="margin-top: 15px;">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>
            </div>
            <div class="form-row" style="margin-top: 15px;">
                <button type="submit" class="btn-primary">Change Password</button>
            </div>
        </form>
    </div>
</div>
<?php
app_close_content_panel();
app_render_page_end();
?>
<script src="assets/js/app.js"></script>
</body>
</html>
