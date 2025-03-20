<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';
require 'vendor/autoload.php'; // Ensure PHPExcel is installed via Compose


// Handle form submission to update department groups
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rule_name']) && $_POST['rule_name'] === 'update_department_groups') {
    // Add new department
    if (isset($_POST['new_department']) && !empty($_POST['new_department'])) {
        $new_department = $_POST['new_department'];
        $group_name = $_POST['new_group_name'];
        $group_name1 = $_POST['new_group_name1'];
        $group_name2 = $_POST['new_group_name2'];
        $insert_sql = "INSERT INTO department_groups (department, group_name, group_name1, group_name2)
                       VALUES ('$new_department', '$group_name', '$group_name1', '$group_name2')";
        if ($conn->query($insert_sql) === TRUE) {
            echo "Department added successfully!";
        } else {
            echo "Error: " . $conn->error;
        }
    }

    // Update existing departments
    if (isset($_POST['departments_to_update']) && !empty($_POST['departments_to_update'])) {
        foreach ($_POST['departments_to_update'] as $dep_id => $department) {
            $group_name = $_POST['groups_to_update'][$dep_id]['group_name'];
            $group_name1 = $_POST['groups_to_update'][$dep_id]['group_name1'];
            $group_name2 = $_POST['groups_to_update'][$dep_id]['group_name2'];

            $update_sql = "UPDATE department_groups SET 
                           group_name = '$group_name', 
                           group_name1 = '$group_name1', 
                           group_name2 = '$group_name2'
                           WHERE department = '$department'";

            if ($conn->query($update_sql) !== TRUE) {
                echo "Error updating department $department: " . $conn->error;
            }
        }
    }
}

// Handle form submission to update user permissions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rule_name']) && $_POST['rule_name'] === 'update_user_permissions') {
    // Add new user permission
    if (isset($_POST['new_username']) && !empty($_POST['new_username'])) {
        $new_username = $_POST['new_username'];
        $group_name_1 = $_POST['new_group_name_1'];
        $group_name_2 = $_POST['new_group_name_2'];
        $group_name_3 = $_POST['new_group_name_3'];
        $insert_sql = "INSERT INTO user_permissions (username, group_name_1, group_name_2, group_name_3)
                       VALUES ('$new_username', '$group_name_1', '$group_name_2', '$group_name_3')";
        if ($conn->query($insert_sql) === TRUE) {
            echo "User permissions added successfully!";
        } else {
            echo "Error: " . $conn->error;
        }
    }

    // Update existing user permissions
    if (isset($_POST['users_to_update']) && !empty($_POST['users_to_update'])) {
        foreach ($_POST['users_to_update'] as $username => $user_perms) {
            $group_name_1 = $user_perms['group_name_1'];
            $group_name_2 = $user_perms['group_name_2'];
            $group_name_3 = $user_perms['group_name_3'];

            $update_sql = "UPDATE user_permissions SET 
                           group_name_1 = '$group_name_1', 
                           group_name_2 = '$group_name_2', 
                           group_name_3 = '$group_name_3'
                           WHERE username = '$username'";
            if ($conn->query($update_sql) !== TRUE) {
                echo "Error updating user permissions for $username: " . $conn->error;
            }
        }
    }
}

// Handle form submission to update page permissions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rule_name']) && $_POST['rule_name'] === 'update_page_permissions') {
    $action = $_POST['action'];

    // Add new user page permissions
    if ($action === 'add_user' && isset($_POST['new_user_access']) && !empty($_POST['new_user_access'])) {
        $new_user_access = $_POST['new_user_access'];
        $default_sql = "SELECT pages FROM page_access WHERE user_access = 'default'";
        $default_result = $conn->query($default_sql);
        if ($default_result->num_rows > 0) {
            $default_row = $default_result->fetch_assoc();
            $default_pages = $default_row['pages'];
            $insert_sql = "INSERT INTO page_access (user_access, pages) VALUES ('$new_user_access', '$default_pages')";
            if ($conn->query($insert_sql) === TRUE) {
                echo "Page permissions added successfully!";
            } else {
                echo "Error: " . $conn->error;
            }
        } else {
            echo "Error: Default user pages not found.";
        }
    }

    // Update page access for selected page
    if ($action === 'update_page_access' && isset($_POST['user_select']) && isset($_POST['page_select']) && isset($_POST['page_access'])) {
        $user = $_POST['user_select'];
        $page = $_POST['page_select'];
        $access = $_POST['page_access'];
        $user_sql = "SELECT pages FROM page_access WHERE user_access = '$user'";
        $user_result = $conn->query($user_sql);
        if ($user_result->num_rows > 0) {
            $user_row = $user_result->fetch_assoc();
            $pages = explode(',', $user_row['pages']);
            for ($i = 0; $i < count($pages); $i += 2) {
                if ($pages[$i] == $page) {
                    $pages[$i + 1] = $access;
                    break;
                }
            }
            $updated_pages = implode(',', $pages);
            $update_sql = "UPDATE page_access SET pages = '$updated_pages' WHERE user_access = '$user'";
            if ($conn->query($update_sql) !== TRUE) {
                echo "Error updating page permissions for $user: " . $conn->error;
            }
        }
    }

    // Remove page from user
    if ($action === 'remove_user_page' && isset($_POST['user_select']) && isset($_POST['user_page_select'])) {
        $user = $_POST['user_select'];
        $page_to_remove = $_POST['user_page_select'];
        $user_sql = "SELECT pages FROM page_access WHERE user_access = '$user'";
        $user_result = $conn->query($user_sql);
        if ($user_result->num_rows > 0) {
            $user_row = $user_result->fetch_assoc();
            $pages = explode(',', $user_row['pages']);
            for ($i = 0; $i < count($pages); $i += 2) {
                if ($pages[$i] == $page_to_remove) {
                    array_splice($pages, $i, 2);
                    break;
                }
            }
            $updated_pages = implode(',', $pages);
            $update_sql = "UPDATE page_access SET pages = '$updated_pages' WHERE user_access = '$user'";
            if ($conn->query($update_sql) !== TRUE) {
                echo "Error removing page from $user: " . $conn->error;
            }
        }
    }

    // Add new page to user
    if ($action === 'add_user_page' && isset($_POST['user_select']) && isset($_POST['new_user_page']) && !empty($_POST['new_user_page'])) {
        $user = $_POST['user_select'];
        $new_page = $_POST['new_user_page'];
        $user_sql = "SELECT pages FROM page_access WHERE user_access = '$user'";
        $user_result = $conn->query($user_sql);
        if ($user_result->num_rows > 0) {
            $user_row = $user_result->fetch_assoc();
            $pages = $user_row['pages'] . ',' . $new_page . ',1';
            $update_sql = "UPDATE page_access SET pages = '$pages' WHERE user_access = '$user'";
            if ($conn->query($update_sql) !== TRUE) {
                echo "Error adding new page to $user: " . $conn->error;
            }
        }
    }

    // Remove page from default
    if ($action === 'remove_page' && isset($_POST['default_page_select'])) {
        $page_to_remove = $_POST['default_page_select'];
        $default_sql = "SELECT pages FROM page_access WHERE user_access = 'default'";
        $default_result = $conn->query($default_sql);
        if ($default_result->num_rows > 0) {
            $default_row = $default_result->fetch_assoc();
            $pages = explode(',', $default_row['pages']);
            for ($i = 0; $i < count($pages); $i += 2) {
                if ($pages[$i] == $page_to_remove) {
                    array_splice($pages, $i, 2);
                    break;
                }
            }
            $updated_pages = implode(',', $pages);
            $update_sql = "UPDATE page_access SET pages = '$updated_pages' WHERE user_access = 'default'";
            if ($conn->query($update_sql) !== TRUE) {
                echo "Error removing page from default: " . $conn->error;
            }
        }
    }

    // Add new page to default
    if ($action === 'add_page' && isset($_POST['new_default_page']) && !empty($_POST['new_default_page'])) {
        $new_page = $_POST['new_default_page'];
        $default_sql = "SELECT pages FROM page_access WHERE user_access = 'default'";
        $default_result = $conn->query($default_sql);
        if ($default_result->num_rows > 0) {
            $default_row = $default_result->fetch_assoc();
            $pages = $default_row['pages'] . ',' . $new_page . ',1';
            $update_sql = "UPDATE page_access SET pages = '$pages' WHERE user_access = 'default'";
            if ($conn->query($update_sql) !== TRUE) {
                echo "Error adding new page to default: " . $conn->error;
            }
        }
    }
}

// Handle AJAX request to get user pages
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['user_access'])) {
    $user_access = $_POST['user_access'];
    $user_sql = "SELECT pages FROM page_access WHERE user_access = '$user_access'";
    $user_result = $conn->query($user_sql);
    if ($user_result->num_rows > 0) {
        $user_row = $user_result->fetch_assoc();
        $pages = explode(',', $user_row['pages']);
        foreach ($pages as $i => $page) {
            if ($i % 2 == 0) {
                $access = $pages[$i + 1] == '1' ? ' (Access)' : ' (No Access)';
                echo "<option value='" . $page . "'>" . $page . $access . "</option>";
            }
        }
    } else {
        echo "<option value=''>No pages found</option>";
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>General Control Panel</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        .form-group {
            margin: 10px 0;
        }
    </style>
    <script>
        function showRuleForm() {
            var ruleName = document.getElementById("ruleSelect").value;
            var forms = document.getElementsByClassName("rule-form");
            for (var i = 0; i < forms.length; i++) {
                forms[i].style.display = "none";
            }
            if (ruleName === "register") {
                window.location.href = "register.php";
            } else if (ruleName !== "") {
                document.getElementById(ruleName + "Form").style.display = "block";
            }
        }

        function updateUserPages() {
            var userSelect = document.getElementById("user_select").value;
            var xhr = new XMLHttpRequest();
            xhr.open("POST", "general_cpanel.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    document.getElementById("user_page_select").innerHTML = xhr.responseText;
                    document.getElementById("page_select").innerHTML = xhr.responseText;
                }
            };
            xhr.send("user_access=" + userSelect);
        }
    </script>
    <style>
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
        .container {
            width: 80%;
            margin: 0 auto;
            text-align: center;
            padding: 50px;
        }
        .container select{
            width: 20%;
            margin: 0 auto;
            text-align: center;
            padding: 8px;
        }
        .rule-form {
            margin: 16px 0;
        }
        .rule-form label {
            font-size: 16px;
            margin-right: 10px;
        }
        .rule-form select, .form-group input {
            padding: 10px;
            font-size: 14px;
        }
        .rule-form button {
            padding: 10px 20px;
            font-size: 14px;
            background-color: #007BFF;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .rule-form button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="image-container">
        <div class="image-link">
            <a href="welcome.php"><img src="/images/icons/home.png" alt="home"></a>
        </div>
        <div class="image-link">
            <a href="cpanel.php"><img src="/images/icons/cpanel.png" alt="Cpanel"></a>
        </div>
        <div class="image-link">
            <a href="logout.php"><img src="/images/icons/logout.png" alt="logout"></a>
        </div>
    </div>
    <div class="container">
        <h2>General Control Panel</h2>
        <label for="ruleSelect">Select Rule:</label>
        <select id="ruleSelect" onchange="showRuleForm()">
            <option value="">Select a rule</option>
            <option value="register">User Management</option>
            <option value="update_department_groups">Update Department Groups</option>
            <option value="update_user_permissions">Update User Permissions</option>
            <option value="update_page_permissions">Update Page Permissions</option>
        </select>

        <div id="update_department_groupsForm" class="rule-form" style="display:none;">
            <h3>Update Department Groups</h3>
            <form method="POST" action="general_cpanel.php">
                <input type="hidden" name="rule_name" value="update_department_groups">
                <div class="form-group">
                    <label for="departments_to_update">Current Departments:</label>
                    <div id="department_groups_list">
                        <table>
                        <?php
                        $departments_sql = "SELECT department, group_name, group_name1, group_name2 FROM department_groups";
                        $departments_result = $conn->query($departments_sql);
                        if ($departments_result->num_rows > 0) {
                            while ($row = $departments_result->fetch_assoc()) {
                                echo "<tr><div><input type='hidden' name='departments_to_update[" . $row['department'] . "]' value='" . $row['department'] . "'></tr>";
                                echo "<td>Department: <input type='text' name='departments_to_update[" . $row['department'] . "]' value='" . $row['department'] . "'></td>";
                                echo "<td>Group Name: <input type='text' name='groups_to_update[" . $row['department'] . "][group_name]' value='" . $row['group_name'] . "'></td>";
                                echo "<td>Group Name1: <input type='text' name='groups_to_update[" . $row['department'] . "][group_name1]' value='" . $row['group_name1'] . "'></td>";
                                echo "<td>Group Name2: <input type='text' name='groups_to_update[" . $row['department'] . "][group_name2]' value='" . $row['group_name2'] . "'></td></div>";
                            }
                        } else {
                            echo "No departments found.";
                        }
                        ?>
                        </table>
                    </div>
                </div>
                <div class="form-group">
                    <h4>Add New Department</h4>
                    <label for="new_department">Department:</label>
                    <input type="text" id="new_department" name="new_department"><br>
                    <label for="new_group_name">Group Name:</label>
                    <input type="text" id="new_group_name" name="new_group_name"><br>
                    <label for="new_group_name1">Group Name1:</label>
                    <input type="text" id="new_group_name1" name="new_group_name1"><br>
                    <label for="new_group_name2">Group Name2:</label>
                    <input type="text" id="new_group_name2" name="new_group_name2">
                </div>
                <button type="submit">Update</button>
            </form>
        </div>

        <div id="update_user_permissionsForm" class="rule-form" style="display:none;">
            <h3>Update User Permissions</h3>
            <form method="POST" action="general_cpanel.php">
                <input type="hidden" name="rule_name" value="update_user_permissions">
                <div class="form-group">
                    <label for="users_to_update">Current Users:</label>
                    <div id="user_permissions_list">
                        <table>
                        <?php
                        $users_sql = "SELECT username, group_name_1, group_name_2, group_name_3 FROM user_permissions";
                        $users_result = $conn->query($users_sql);
                        if ($users_result->num_rows > 0) {
                            while ($row = $users_result->fetch_assoc()) {
                                echo "<tr><div><input type='hidden' name='users_to_update[" . $row['username'] . "]' value='" . $row['username'] . "'></tr>";
                                echo "<td>Username: <input type='text' name='users_to_update[" . $row['username'] . "][username]' value='" . $row['username'] . "'></td>";
                                echo "<td>Group Name 1: <input type='text' name='users_to_update[" . $row['username'] . "][group_name_1]' value='" . $row['group_name_1'] . "'></td>";
                                echo "<td>Group Name 2: <input type='text' name='users_to_update[" . $row['username'] . "][group_name_2]' value='" . $row['group_name_2'] . "'></td>";
                                echo "<td>Group Name 3: <input type='text' name='users_to_update[" . $row['username'] . "][group_name_3]' value='" . $row['group_name_3'] . "'></div></td>";
                            }
                        } else {
                            echo "No users found.";
                        }
                        ?>
                        </table>
                    </div>
                </div>
                <div class="form-group">
                    <h4>Add New User</h4>
                    <label for="new_username">Username:</label>
                    <input type="text" id="new_username" name="new_username"><br>
                    <label for="new_group_name_1">Group Name 1:</label>
                    <input type="text" id="new_group_name_1" name="new_group_name_1"><br>
                    <label for="new_group_name_2">Group Name 2:</label>
                    <input type="text" id="new_group_name_2" name="new_group_name_2"><br>
                    <label for="new_group_name_3">Group Name 3:</label>
                    <input type="text" id="new_group_name_3" name="new_group_name_3">
                </div>
                <button type="submit">Update</button>
            </form>
        </div>

        <div id="update_page_permissionsForm" class="rule-form" style="display:none;">
            <h3>Update Page Permissions</h3>
            <form method="POST" action="general_cpanel.php">
                <input type="hidden" name="rule_name" value="update_page_permissions">
                <div class="form-group">
                    <h4>Current Users</h4>
                    <label for="user_select">Select User:</label>
                    <select id="user_select" name="user_select" onchange="updateUserPages()">
                        <?php
                        $users_sql = "SELECT user_access FROM page_access";
                        $users_result = $conn->query($users_sql);
                        if ($users_result->num_rows > 0) {
                            while ($row = $users_result->fetch_assoc()) {
                                echo "<option value='" . $row['user_access'] . "'>" . $row['user_access'] . "</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <h4>Update Page Access</h4>
                    <label for="page_select">Select Page:</label>
                    <select id="page_select" name="page_select">
                        <!-- Options will be populated by JavaScript -->
                    </select>
                    <label for="page_access">Access:</label>
                    <input type="radio" id="access_yes" name="page_access" value="1"> Yes
                    <input type="radio" id="access_no" name="page_access" value="0"> No
                    <button type="submit" name="action" value="update_page_access">Update Access</button>
                </div>
                <div class="form-group">
                    <h4>Modify User Pages</h4>
                    <label for="user_page_select">Select Page to Remove:</label>
                    <select id="user_page_select" name="user_page_select">
                        <!-- Options will be populated by JavaScript -->
                    </select>
                    <button type="submit" name="action" value="remove_user_page">Remove Page</button>
                    <label for="new_user_page">Add New Page:</label>
                    <input type="text" id="new_user_page" name="new_user_page">
                    <button type="submit" name="action" value="add_user_page">Add Page</button>
                </div>
                <div class="form-group">
                    <h4>Modify Default Pages</h4>
                    <label for="default_page_select">Remove Page:</label>
                    <select id="default_page_select" name="default_page_select">
                        <?php
                        $default_sql = "SELECT pages FROM page_access WHERE user_access = 'default'";
                        $default_result = $conn->query($default_sql);
                        if ($default_result->num_rows > 0) {
                            $default_row = $default_result->fetch_assoc();
                            $pages = explode(',', $default_row['pages']);
                            for ($i = 0; $i < count($pages); $i += 2) {
                                echo "<option value='" . $pages[$i] . "'>" . $pages[$i] . "</option>";
                            }
                        }
                        ?>
                    </select>
                    <button type="submit" name="action" value="remove_page">Remove Page</button>
                    <label for="new_default_page">Add New Page:</label>
                    <input type="text" id="new_default_page" name="new_default_page">
                    <button type="submit" name="action" value="add_page">Add Page</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
