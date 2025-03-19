<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';



// Handle new user registration
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert the new user into the user_management table
    $insert_sql = "INSERT INTO user_management (username, password) VALUES ('$username', '$hashed_password')";
    if ($conn->query($insert_sql) === TRUE) {
        // Insert the new user into the user_permissions table
        $insert_permissions_sql = "INSERT INTO user_permissions (username) VALUES ('$username')";
        if ($conn->query($insert_permissions_sql) === TRUE) {
            // Insert the new user into the page_access table with default pages
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

// Handle password change
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

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_user'])) {
    $delete_user = $_POST['delete_user'];

    // Delete user from user_management table
    $delete_sql = "DELETE FROM user_management WHERE username = '$delete_user'";
    if ($conn->query($delete_sql) === TRUE) {
        echo "User deleted successfully from user_management: $delete_user";
    } else {
        echo "Error: " . $conn->error;
    }

    // Delete user from user_permissions table
    $delete_permissions_sql = "DELETE FROM user_permissions WHERE username = '$delete_user'";
    if ($conn->query($delete_permissions_sql) === TRUE) {
        echo " User deleted successfully from user_permissions: $delete_user";
    } else {
        echo "Error: " . $conn->error;
    }

    // Delete user from page_access table
    $delete_page_access_sql = "DELETE FROM page_access WHERE user_access = '$delete_user'";
    if ($conn->query($delete_page_access_sql) === TRUE) {
        echo " User deleted successfully from page_access: $delete_user";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register New User</title>
    <style>
        .container {
            width: 50%;
            margin: 0 auto;
            text-align: center;
        }
        .form-group {
            margin: 15px;
        }
        .button {
            padding: 5px 10px;
            font-size: 18px;
            cursor: pointer;
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
    </style>
</head>
<body>
        <div class="image-container">
                <div class="image-link">
        <a href="welcome.php"><img src="/images/icons/home.png" alt="home"></a>
    </div>
    <div class="image-link">
        <a href="cpanel.php"><img src="/images/welcome/cpanel.png" alt="home"></a>
    </div>
    <div class="image-link">
        <a href="logout.php"><img src="/images/icons/logout.png" alt="logout"></a>
    </div>
    </div>
    <div class="container">
        <h2>Register New User</h2>
        <form method="POST" action="register.php">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="button">Register</button>
        </form>

        <h2>Change User Password</h2>
        <form method="POST" action="register.php">
            <div class="form-group">
                <label for="selected_user">Select User:</label>
                <select id="selected_user" name="selected_user" required>
                    <?php
                    // Fetch users
                    $users_sql = "SELECT username FROM user_management";
                    $users_result = $conn->query($users_sql);
                    if ($users_result->num_rows > 0) {
                        while ($row = $users_result->fetch_assoc()) {
                            echo "<option value='" . $row['username'] . "'>" . $row['username'] . "</option>";
                        }
                    } else {
                        echo "<option value=''>No users found</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label for="new_password">New Password:</label>
                <input type="password" id="new_password" name="new_password" required>
            </div>
            <button type="submit" class="button">Change Password</button>
        </form>

        <h2>Delete User</h2>
        <form method="POST" action="register.php">
            <div class="form-group">
                <label for="delete_user">Select User to Delete:</label>
                <select id="delete_user" name="delete_user" required>
                    <?php
                    // Fetch users
                    $users_sql = "SELECT username FROM user_management";
                    $users_result = $conn->query($users_sql);
                    if ($users_result->num_rows > 0) {
                        while ($row = $users_result->fetch_assoc()) {
                            echo "<option value='" . $row['username'] . "'>" . $row['username'] . "</option>";
                        }
                    } else {
                        echo "<option value=''>No users found</option>";
                    }
                    ?>
                </select>
            </div>
            <button type="submit" class="button">Delete User</button>
        </form>
    </div>
</body>
</html>
