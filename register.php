<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';
include 'includes/app_helpers.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $insert_sql = "INSERT INTO user_management (username, password) VALUES ('$username', '$hashed_password')";
    if ($conn->query($insert_sql) === TRUE) {
        $insert_permissions_sql = "INSERT INTO user_permissions (username) VALUES ('$username')";
        if ($conn->query($insert_permissions_sql) === TRUE) {
            $default_sql = "SELECT pages FROM page_access WHERE user_access = 'default'";
            $default_result = $conn->query($default_sql);
            if ($default_result->num_rows > 0) {
                $default_row = $default_result->fetch_assoc();
                $default_pages = $default_row['pages'];
                $insert_page_access_sql = "INSERT INTO page_access (user_access, pages) VALUES ('$username', '$default_pages')";
                if ($conn->query($insert_page_access_sql) === TRUE) {
                    echo "New user registered successfully!";
                } else {
                    echo "Error inserting into page_access: " . $conn->error;
                }
            } else {
                echo "Error: Default user pages not found.";
            }
        } else {
            echo "Error inserting into user_permissions: " . $conn->error;
        }
    } else {
        echo "Error: " . $conn->error;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['selected_user']) && isset($_POST['new_password'])) {
    $selected_user = $_POST['selected_user'];
    $new_password = $_POST['new_password'];
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    $update_sql = "UPDATE user_management SET password = '$hashed_password' WHERE username = '$selected_user'";
    if ($conn->query($update_sql) === TRUE) {
        echo "Password updated successfully for user: $selected_user";
    } else {
        echo "Error: " . $conn->error;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_user'])) {
    $delete_user = $_POST['delete_user'];
    $conn->query("DELETE FROM user_management WHERE username = '$delete_user'");
    $conn->query("DELETE FROM user_permissions WHERE username = '$delete_user'");
    $conn->query("DELETE FROM page_access WHERE user_access = '$delete_user'");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="icon" type="image/png" href="images/logo.png">
</head>
<body>
<?php
app_render_page_header('UM', 'User Management', 'Register users, change passwords, and remove access from one admin screen.', [
    ['label' => 'Home', 'href' => 'welcome.php'],
    ['label' => 'Cpanel', 'href' => 'cpanel.php'],
    ['label' => 'Logout', 'href' => 'logout.php'],
]);
app_render_page_hero('Admin area', 'User administration now matches the new application shell.', 'The old stacked forms have been reorganized into panel sections so account management is easier to scan and safer to operate.', [
    ['title' => 'Accounts', 'text' => 'Create, update, and remove system users.'],
    ['title' => 'Inherited Access', 'text' => 'New users still receive the default page access set.'],
]);
app_open_content_panel('Account Actions', 'Use the sections below to register a user, change a password, or delete an existing account.');
?>
<div class="stack-gap">
    <div class="summary-card">
        <strong>Register New User</strong>
        <form method="POST" action="register.php">
            <div class="form-row">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
                <button type="submit" class="button">Register</button>
            </div>
        </form>
    </div>
    <div class="summary-card">
        <strong>Change User Password</strong>
        <form method="POST" action="register.php">
            <div class="form-row">
                <label for="selected_user">User</label>
                <select id="selected_user" name="selected_user" required>
                    <?php $users_result = $conn->query("SELECT username FROM user_management"); while ($row = $users_result->fetch_assoc()): ?>
                        <option value="<?php echo $row['username']; ?>"><?php echo $row['username']; ?></option>
                    <?php endwhile; ?>
                </select>
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" required>
                <button type="submit" class="button">Change Password</button>
            </div>
        </form>
    </div>
    <div class="summary-card">
        <strong>Delete User</strong>
        <form method="POST" action="register.php">
            <div class="form-row">
                <label for="delete_user">User</label>
                <select id="delete_user" name="delete_user" required>
                    <?php $users_result = $conn->query("SELECT username FROM user_management"); while ($row = $users_result->fetch_assoc()): ?>
                        <option value="<?php echo $row['username']; ?>"><?php echo $row['username']; ?></option>
                    <?php endwhile; ?>
                </select>
                <button type="submit" class="button">Delete User</button>
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

