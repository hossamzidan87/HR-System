<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';
include 'includes/app_helpers.php';
require 'vendor/autoload.php'; // Ensure PHPExcel is installed via Composer

$flash_messages = [];

function app_add_flash_message(array &$messages, string $type, string $text): void
{
    $messages[] = [
        'type' => $type,
        'text' => $text,
    ];
}

function app_parse_pages_csv(string $pagesCsv): array
{
    $map = [];
    $parts = array_map('trim', explode(',', $pagesCsv));

    for ($i = 0; $i < count($parts); $i += 2) {
        $page = $parts[$i] ?? '';
        if ($page === '') {
            continue;
        }

        $map[$page] = (($parts[$i + 1] ?? '0') === '1') ? '1' : '0';
    }

    return $map;
}

function app_build_pages_csv(array $pageMap): string
{
    $parts = [];
    foreach ($pageMap as $page => $access) {
        $pageName = trim((string) $page);
        if ($pageName === '') {
            continue;
        }

        $parts[] = $pageName;
        $parts[] = ($access === '1') ? '1' : '0';
    }

    return implode(',', $parts);
}

function app_get_module_catalog(): array
{
    return [
        'overtime' => [
            'label' => 'Overtime',
            'pages' => ['overtime_home.php', 'overtime.php', 'overtime_report.php'],
        ],
        'shifts' => [
            'label' => 'Night Shift',
            'pages' => ['shifts_home.php', 'night_shift.php', 'night_report.php'],
        ],
        'evaluation' => [
            'label' => 'Evaluation',
            'pages' => ['evaluation.php', 'quarter_evaluation.php', 'quarter_report.php', 'annual_evaluation.php'],
        ],
        'salary_advance' => [
            'label' => 'Salary Advance',
            'pages' => ['sadv_list.php'],
        ],
        'control_panel' => [
            'label' => 'Control Panel',
            'pages' => ['cpanel.php', 'general_cpanel.php', 'overtime_cpanel.php', 'sadv_cpanel.php', 'shifts_cpanel.php', 'evaluation_cpanel.php'],
        ],
    ];
}

function app_get_module_states(array $moduleCatalog, array $pageMap): array
{
    $states = [];
    foreach ($moduleCatalog as $moduleKey => $moduleInfo) {
        $isOpen = false;
        foreach ($moduleInfo['pages'] as $page) {
            if (($pageMap[$page] ?? '0') === '1') {
                $isOpen = true;
                break;
            }
        }
        $states[$moduleKey] = $isOpen;
    }

    return $states;
}


// Handle form submission to update department groups
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rule_name']) && $_POST['rule_name'] === 'update_department_groups') {
    $saved_department = $_POST['save_department'] ?? '';

    // Add new department
    if (isset($_POST['new_department']) && !empty($_POST['new_department'])) {
        $new_department = $_POST['new_department'];
        $group_name = $_POST['new_group_name'];
        $group_name1 = $_POST['new_group_name1'];
        $group_name2 = $_POST['new_group_name2'];
        $insert_sql = "INSERT INTO department_groups (department, group_name, group_name1, group_name2)
                       VALUES ('$new_department', '$group_name', '$group_name1', '$group_name2')";
        if ($conn->query($insert_sql) === TRUE) {
            app_add_flash_message($flash_messages, 'success', 'Department added successfully.');
        } else {
            app_add_flash_message($flash_messages, 'error', 'Error adding department: ' . $conn->error);
        }
    }

    // Update existing departments
    if (isset($_POST['departments_to_update']) && !empty($_POST['departments_to_update'])) {
        foreach ($_POST['departments_to_update'] as $dep_id => $department) {
            if ($saved_department !== '' && $department !== $saved_department) {
                continue;
            }

            $group_name = $_POST['groups_to_update'][$dep_id]['group_name'];
            $group_name1 = $_POST['groups_to_update'][$dep_id]['group_name1'];
            $group_name2 = $_POST['groups_to_update'][$dep_id]['group_name2'];

            $update_sql = "UPDATE department_groups SET 
                           group_name = '$group_name', 
                           group_name1 = '$group_name1', 
                           group_name2 = '$group_name2'
                           WHERE department = '$department'";

            if ($conn->query($update_sql) !== TRUE) {
                app_add_flash_message($flash_messages, 'error', "Error updating department $department: " . $conn->error);
            } elseif ($saved_department !== '') {
                app_add_flash_message($flash_messages, 'success', "Department $department updated successfully.");
            }
        }

        if ($saved_department === '') {
            app_add_flash_message($flash_messages, 'success', 'Department group mappings updated successfully.');
        }
    }

        // Delete existing departments
        if (isset($_POST['delete_department']) && !empty($_POST['delete_department'])) {
            $department = $_POST['delete_department'];
                $delete_sql = "DELETE FROM department_groups WHERE department = '$department'"; 
                if ($conn->query($delete_sql) !== TRUE) {
                    app_add_flash_message($flash_messages, 'error', "Error deleting department $department: " . $conn->error);
                } else {
                    app_add_flash_message($flash_messages, 'success', "Department $department deleted successfully.");
                }
        }
}

// Handle form submission to update user permissions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rule_name']) && $_POST['rule_name'] === 'update_user_permissions') {
    $saved_username = $_POST['save_user_permission'] ?? '';

    // Add new user permission
    if (isset($_POST['new_username']) && !empty($_POST['new_username'])) {
        $new_username = $_POST['new_username'];
        $group_name_1 = $_POST['new_group_name_1'];
        $group_name_2 = $_POST['new_group_name_2'];
        $group_name_3 = $_POST['new_group_name_3'];
        $insert_sql = "INSERT INTO user_permissions (username, group_name_1, group_name_2, group_name_3)
                       VALUES ('$new_username', '$group_name_1', '$group_name_2', '$group_name_3')";
        if ($conn->query($insert_sql) === TRUE) {
            app_add_flash_message($flash_messages, 'success', 'User permissions added successfully.');
        } else {
            app_add_flash_message($flash_messages, 'error', 'Error adding user permissions: ' . $conn->error);
        }
    }

    // Update existing user permissions
    if (isset($_POST['users_to_update']) && !empty($_POST['users_to_update'])) {
        foreach ($_POST['users_to_update'] as $username => $user_perms) {
            if ($saved_username !== '' && $username !== $saved_username) {
                continue;
            }

            $group_name_1 = $user_perms['group_name_1'];
            $group_name_2 = $user_perms['group_name_2'];
            $group_name_3 = $user_perms['group_name_3'];

            $update_sql = "UPDATE user_permissions SET 
                           group_name_1 = '$group_name_1', 
                           group_name_2 = '$group_name_2', 
                           group_name_3 = '$group_name_3'
                           WHERE username = '$username'";
            if ($conn->query($update_sql) !== TRUE) {
                app_add_flash_message($flash_messages, 'error', "Error updating user permissions for $username: " . $conn->error);
            } elseif ($saved_username !== '') {
                app_add_flash_message($flash_messages, 'success', "User permissions updated for $username.");
            }
        }

        if ($saved_username === '') {
            app_add_flash_message($flash_messages, 'success', 'User permissions updated successfully.');
        }
    }

    if (isset($_POST['delete_user_permission']) && $_POST['delete_user_permission'] !== '') {
        $username_to_delete = $_POST['delete_user_permission'];
        $delete_user_sql = "DELETE FROM user_permissions WHERE username = '$username_to_delete'";
        if ($conn->query($delete_user_sql) !== TRUE) {
            app_add_flash_message($flash_messages, 'error', "Error deleting user permissions for $username_to_delete: " . $conn->error);
        } else {
            app_add_flash_message($flash_messages, 'success', "User permissions removed for $username_to_delete.");
        }
    }
}

// Handle form submission to update page permissions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rule_name']) && $_POST['rule_name'] === 'update_page_permissions') {
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

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
                app_add_flash_message($flash_messages, 'success', 'Page permissions added successfully.');
            } else {
                app_add_flash_message($flash_messages, 'error', 'Error adding page permissions: ' . $conn->error);
            }
        } else {
            app_add_flash_message($flash_messages, 'error', 'Default user pages were not found.');
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
                app_add_flash_message($flash_messages, 'error', "Error updating page permissions for $user: " . $conn->error);
            } else {
                app_add_flash_message($flash_messages, 'success', "Page access updated for $user.");
            }
        }
    }

    // Remove page from user
    if ($action === 'remove_user_page' && isset($_POST['user_page_select']) && isset($_POST['user_manage_select'])) {
        $user = $_POST['user_manage_select'];
        $page_to_remove = $_POST['user_page_select'];
        if ($user === '__all__') {
            $all_users_sql = "SELECT user_access, pages FROM page_access WHERE user_access <> 'default'";
            $all_users_result = $conn->query($all_users_sql);
            $updated_count = 0;

            if ($all_users_result && $all_users_result->num_rows > 0) {
                while ($all_user_row = $all_users_result->fetch_assoc()) {
                    $current_user = $all_user_row['user_access'];
                    $pages = explode(',', $all_user_row['pages']);
                    $removed = false;

                    for ($i = 0; $i < count($pages); $i += 2) {
                        if ($pages[$i] == $page_to_remove) {
                            array_splice($pages, $i, 2);
                            $removed = true;
                            break;
                        }
                    }

                    if ($removed) {
                        $updated_pages = implode(',', $pages);
                        $update_sql = "UPDATE page_access SET pages = '$updated_pages' WHERE user_access = '$current_user'";
                        if ($conn->query($update_sql) === TRUE) {
                            $updated_count++;
                        }
                    }
                }

                app_add_flash_message($flash_messages, 'success', "Page removed from $updated_count user profile(s).");
            }
        } else {
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
                    app_add_flash_message($flash_messages, 'error', "Error removing page from $user: " . $conn->error);
                } else {
                    app_add_flash_message($flash_messages, 'success', "Page removed from $user.");
                }
            }
        }
    }

    // Add new page to user
    if ($action === 'add_user_page' && isset($_POST['user_manage_select']) && isset($_POST['new_user_page']) && !empty($_POST['new_user_page'])) {
        $user = $_POST['user_manage_select'];
        $new_page = $_POST['new_user_page'];
        if ($user === '__all__') {
            $all_users_sql = "SELECT user_access, pages FROM page_access WHERE user_access <> 'default'";
            $all_users_result = $conn->query($all_users_sql);
            $updated_count = 0;

            if ($all_users_result && $all_users_result->num_rows > 0) {
                while ($all_user_row = $all_users_result->fetch_assoc()) {
                    $current_user = $all_user_row['user_access'];
                    $pages = explode(',', $all_user_row['pages']);
                    $already_exists = false;

                    for ($i = 0; $i < count($pages); $i += 2) {
                        if ($pages[$i] == $new_page) {
                            $already_exists = true;
                            break;
                        }
                    }

                    if (!$already_exists) {
                        $updated_pages = $all_user_row['pages'] === '' ? $new_page . ',1' : $all_user_row['pages'] . ',' . $new_page . ',1';
                        $update_sql = "UPDATE page_access SET pages = '$updated_pages' WHERE user_access = '$current_user'";
                        if ($conn->query($update_sql) === TRUE) {
                            $updated_count++;
                        }
                    }
                }

                app_add_flash_message($flash_messages, 'success', "New page added to $updated_count user profile(s).");
            }
        } else {
            $user_sql = "SELECT pages FROM page_access WHERE user_access = '$user'";
            $user_result = $conn->query($user_sql);
            if ($user_result->num_rows > 0) {
                $user_row = $user_result->fetch_assoc();
                $pages = explode(',', $user_row['pages']);
                $already_exists = false;
                for ($i = 0; $i < count($pages); $i += 2) {
                    if ($pages[$i] == $new_page) {
                        $already_exists = true;
                        break;
                    }
                }

                if ($already_exists) {
                    app_add_flash_message($flash_messages, 'error', "Page already exists for $user.");
                } else {
                    $updated_pages = $user_row['pages'] === '' ? $new_page . ',1' : $user_row['pages'] . ',' . $new_page . ',1';
                    $update_sql = "UPDATE page_access SET pages = '$updated_pages' WHERE user_access = '$user'";
                    if ($conn->query($update_sql) !== TRUE) {
                        app_add_flash_message($flash_messages, 'error', "Error adding new page to $user: " . $conn->error);
                    } else {
                        app_add_flash_message($flash_messages, 'success', "New page added to $user.");
                    }
                }
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
                app_add_flash_message($flash_messages, 'error', 'Error removing page from default template: ' . $conn->error);
            } else {
                app_add_flash_message($flash_messages, 'success', 'Page removed from default template.');
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
                app_add_flash_message($flash_messages, 'error', 'Error adding page to default template: ' . $conn->error);
            } else {
                app_add_flash_message($flash_messages, 'success', 'New page added to default template.');
            }
        }
    }

    // Open/Close modules for selected user
    if ($action === 'update_module_access' && isset($_POST['module_access_user']) && $_POST['module_access_user'] !== '' && $_POST['module_access_user'] !== '__all__') {
        $targetUser = $_POST['module_access_user'];
        $moduleSelections = isset($_POST['module_access']) && is_array($_POST['module_access']) ? $_POST['module_access'] : [];
        $moduleCatalog = app_get_module_catalog();

        $targetUserEscaped = $conn->real_escape_string($targetUser);
        $targetUserSql = "SELECT pages FROM page_access WHERE user_access = '$targetUserEscaped'";
        $targetUserResult = $conn->query($targetUserSql);

        if ($targetUserResult && $targetUserResult->num_rows > 0) {
            $targetUserRow = $targetUserResult->fetch_assoc();
            $pageMap = app_parse_pages_csv((string) ($targetUserRow['pages'] ?? ''));

            foreach ($moduleCatalog as $moduleKey => $moduleInfo) {
                $shouldOpen = isset($moduleSelections[$moduleKey]);
                foreach ($moduleInfo['pages'] as $modulePage) {
                    if (!array_key_exists($modulePage, $pageMap)) {
                        $pageMap[$modulePage] = '0';
                    }
                    $pageMap[$modulePage] = $shouldOpen ? '1' : '0';
                }
            }

            $updatedPagesCsv = app_build_pages_csv($pageMap);
            $updateModuleSql = "UPDATE page_access SET pages = '$updatedPagesCsv' WHERE user_access = '$targetUserEscaped'";

            if ($conn->query($updateModuleSql) !== TRUE) {
                app_add_flash_message($flash_messages, 'error', "Error updating module access for $targetUser: " . $conn->error);
            } else {
                app_add_flash_message($flash_messages, 'success', "Module access updated for $targetUser.");
            }
        } else {
            app_add_flash_message($flash_messages, 'error', 'Selected user was not found in page_access.');
        }
    }
}

// Handle AJAX request to get user pages
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['user_access'])) {
    $user_access = $_POST['user_access'];
    if ($user_access === '__all__') {
        $all_users_sql = "SELECT pages FROM page_access WHERE user_access <> 'default'";
        $all_users_result = $conn->query($all_users_sql);
        $all_pages = [];

        if ($all_users_result && $all_users_result->num_rows > 0) {
            while ($row = $all_users_result->fetch_assoc()) {
                $pages = explode(',', (string) $row['pages']);
                for ($i = 0; $i < count($pages); $i += 2) {
                    $page_name = trim((string) ($pages[$i] ?? ''));
                    if ($page_name !== '') {
                        $all_pages[$page_name] = true;
                    }
                }
            }

            $page_names = array_keys($all_pages);
            sort($page_names);
            foreach ($page_names as $page_name) {
                echo "<option value='" . $page_name . "'>" . $page_name . "</option>";
            }
        } else {
            echo "<option value=''>No pages found</option>";
        }
    } else {
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
    }
    exit;
}

$active_panel = 'update_department_groups';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rule_name'])) {
    $requested_panel = $_POST['rule_name'];
    if (in_array($requested_panel, ['update_department_groups', 'update_user_permissions', 'update_page_permissions'], true)) {
        $active_panel = $requested_panel;
    }
}
if (isset($_GET['panel']) && in_array($_GET['panel'], ['update_department_groups', 'update_user_permissions', 'update_page_permissions'], true)) {
    $active_panel = $_GET['panel'];
}

$total_departments = 0;
$total_users_permissions = 0;
$total_page_profiles = 0;
$available_groups = [];
$module_catalog = app_get_module_catalog();
$module_access_user = '';
$module_access_states = [];

$departments_count_result = $conn->query("SELECT COUNT(DISTINCT department) AS total_count FROM department_groups");
if ($departments_count_result && $departments_count_result->num_rows > 0) {
    $total_departments = (int) $departments_count_result->fetch_assoc()['total_count'];
}

$users_count_result = $conn->query("SELECT COUNT(DISTINCT username) AS total_count FROM user_permissions");
if ($users_count_result && $users_count_result->num_rows > 0) {
    $total_users_permissions = (int) $users_count_result->fetch_assoc()['total_count'];
}

$profiles_count_result = $conn->query("SELECT COUNT(user_access) AS total_count FROM page_access");
if ($profiles_count_result && $profiles_count_result->num_rows > 0) {
    $total_page_profiles = (int) $profiles_count_result->fetch_assoc()['total_count'];
}

$groups_result = $conn->query("SELECT group_name, group_name1, group_name2 FROM department_groups");
if ($groups_result && $groups_result->num_rows > 0) {
    while ($group_row = $groups_result->fetch_assoc()) {
        foreach (['group_name', 'group_name1', 'group_name2'] as $group_key) {
            $value = trim((string) ($group_row[$group_key] ?? ''));
            if ($value !== '') {
                $available_groups[$value] = true;
            }
        }
    }
}
$available_groups = array_keys($available_groups);
sort($available_groups);

$module_users_sql = "SELECT user_access, pages FROM page_access WHERE user_access <> 'default' ORDER BY user_access";
$module_users_result = $conn->query($module_users_sql);
$module_users = [];
if ($module_users_result && $module_users_result->num_rows > 0) {
    while ($module_user_row = $module_users_result->fetch_assoc()) {
        $module_users[] = $module_user_row;
    }
}

$requested_module_user = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rule_name']) && $_POST['rule_name'] === 'update_page_permissions') {
    $requested_module_user = trim((string) ($_POST['module_access_user'] ?? $_POST['user_manage_select'] ?? $_POST['user_select'] ?? ''));
}

if ($requested_module_user !== '' && $requested_module_user !== '__all__') {
    $module_access_user = $requested_module_user;
} elseif (!empty($module_users)) {
    $module_access_user = (string) $module_users[0]['user_access'];
}

if ($module_access_user !== '') {
    $selected_pages_csv = '';
    foreach ($module_users as $module_user_row) {
        if ((string) $module_user_row['user_access'] === $module_access_user) {
            $selected_pages_csv = (string) ($module_user_row['pages'] ?? '');
            break;
        }
    }

    $module_access_states = app_get_module_states($module_catalog, app_parse_pages_csv($selected_pages_csv));
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
        function openPanel(ruleName) {
            var forms = document.getElementsByClassName("rule-form");
            var tabs = document.getElementsByClassName("panel-tab");

            for (var i = 0; i < forms.length; i++) {
                forms[i].style.display = "none";
            }
            for (var j = 0; j < tabs.length; j++) {
                tabs[j].classList.remove("active");
            }

            if (ruleName === "register") {
                window.location.href = "register.php";
            } else if (ruleName !== "") {
                document.getElementById(ruleName + "Form").style.display = "block";
                var tabButton = document.querySelector('.panel-tab[data-target="' + ruleName + '"]');
                if (tabButton) {
                    tabButton.classList.add("active");
                }
            }
        }

        function updateUserPages() {
            var userSelect = document.getElementById("user_select").value;
            var xhr = new XMLHttpRequest();
            xhr.open("POST", "general_cpanel.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    document.getElementById("page_select").innerHTML = xhr.responseText;
                    filterPageOptions();
                }
            };
            xhr.send("user_access=" + userSelect);
        }

        function updateManagedUserPages() {
            var userSelect = document.getElementById("user_manage_select").value;
            var xhr = new XMLHttpRequest();
            xhr.open("POST", "general_cpanel.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    document.getElementById("user_page_select").innerHTML = xhr.responseText;
                    filterPageOptions();
                }
            };
            xhr.send("user_access=" + encodeURIComponent(userSelect));
        }

        function filterTableRows(inputId, tableId) {
            var query = document.getElementById(inputId).value.toLowerCase().trim();
            var table = document.getElementById(tableId);
            if (!table) return;

            var rows = table.querySelectorAll('tbody tr');
            rows.forEach(function (row) {
                var text = row.textContent.toLowerCase();
                row.style.display = text.indexOf(query) !== -1 ? '' : 'none';
            });
        }

        function filterPageOptions() {
            var searchInput = document.getElementById('page_filter');
            var query = searchInput ? searchInput.value.toLowerCase().trim() : '';
            ['page_select', 'user_page_select', 'default_page_select'].forEach(function (selectId) {
                var selectElement = document.getElementById(selectId);
                if (!selectElement) return;

                Array.from(selectElement.options).forEach(function (option) {
                    var optionText = option.text.toLowerCase();
                    option.hidden = query !== '' && optionText.indexOf(query) === -1;
                });
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            var initialPanel = document.body.getAttribute('data-active-panel') || 'update_department_groups';
            openPanel(initialPanel);
            if (initialPanel === 'update_page_permissions') {
                updateUserPages();
                updateManagedUserPages();
            }
        });
    </script>
    <style>
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
        .panel-tabbar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 10px 0 18px;
        }
        .panel-tab {
            border: 1px solid #cfd7df;
            background: #f3f6fa;
            color: #1f2d3d;
            border-radius: 8px;
            padding: 10px 14px;
            font-weight: 600;
            cursor: pointer;
        }
        .panel-tab.active {
            background: #0a4d8c;
            color: #fff;
            border-color: #0a4d8c;
        }
        .section-block {
            margin-top: 14px;
            padding: 12px;
            border: 1px solid #dbe3ec;
            border-radius: 8px;
            background: #f9fbfd;
        }
        .section-block h4 {
            margin: 0 0 10px;
        }
        .inline-fields {
            display: grid;
            grid-template-columns: repeat(4, minmax(180px, 1fr));
            gap: 10px;
            align-items: end;
        }
        .inline-fields .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .table-scroll {
            overflow-x: auto;
        }
        .table-scroll table {
            width: 100%;
            border-collapse: collapse;
        }
        .table-scroll th,
        .table-scroll td {
            border: 1px solid #d7dee7;
            padding: 8px;
            vertical-align: middle;
        }
        .action-cell {
            white-space: nowrap;
        }
        .action-buttons {
            display: inline-flex;
            gap: 8px;
            align-items: center;
            flex-wrap: nowrap;
        }
        .action-buttons button {
            white-space: nowrap;
        }
        .muted-text {
            color: #61758a;
            margin: 0 0 12px;
        }
        .flash-stack {
            display: grid;
            gap: 8px;
            margin-bottom: 14px;
        }
        .flash-banner {
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid transparent;
            font-weight: 600;
        }
        .flash-success {
            background: #e8f8ef;
            border-color: #b9e7ca;
            color: #1f6f3d;
        }
        .flash-error {
            background: #fdeeee;
            border-color: #f4b9b9;
            color: #8a1f1f;
        }
        @media (max-width: 980px) {
            .inline-fields {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="icon" type="image/png" href="images/logo.png">
</head>
<body data-active-panel="<?php echo htmlspecialchars($active_panel); ?>">
<?php
app_render_page_header('GC', 'General Cpanel', 'Manage system-wide groups and permissions.', [
    ['label' => 'Home', 'href' => 'welcome.php'],
    ['label' => 'Cpanel', 'href' => 'cpanel.php'],
    ['label' => 'Logout', 'href' => 'logout.php'],
]);
app_render_page_hero('Admin area', 'Manage user and department permissions.', 'Update access to pages and modules across the app.', []);
app_open_content_panel('General Control Panel', 'Choose a workspace below to manage departments, user groups, and page access settings.');
?>
        <div class="summary-grid">
            <div class="summary-card"><strong>Total Departments</strong><p><?php echo htmlspecialchars((string) $total_departments); ?></p></div>
            <div class="summary-card"><strong>Total Permission Users</strong><p><?php echo htmlspecialchars((string) $total_users_permissions); ?></p></div>
            <div class="summary-card"><strong>Total Page-Access Profiles</strong><p><?php echo htmlspecialchars((string) $total_page_profiles); ?></p></div>
        </div>

        <?php if (!empty($flash_messages)): ?>
            <div class="flash-stack">
                <?php foreach ($flash_messages as $message): ?>
                    <div class="flash-banner <?php echo $message['type'] === 'success' ? 'flash-success' : 'flash-error'; ?>">
                        <?php echo htmlspecialchars($message['text']); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="panel-tabbar">
            <button type="button" class="panel-tab" data-target="register" onclick="openPanel('register')">User Management</button>
            <button type="button" class="panel-tab" data-target="update_department_groups" onclick="openPanel('update_department_groups')">Department Groups</button>
            <button type="button" class="panel-tab" data-target="update_user_permissions" onclick="openPanel('update_user_permissions')">User Permissions</button>
            <button type="button" class="panel-tab" data-target="update_page_permissions" onclick="openPanel('update_page_permissions')">Page Permissions</button>
        </div>

        <div id="update_department_groupsForm" class="rule-form" style="display:none;">
            <h3>Update Department Groups</h3>
            <p class="muted-text">Department Groups control which departments each group can manage.</p>
            <form method="POST" action="general_cpanel.php">
            <input type="hidden" name="rule_name" value="update_department_groups">
            <div class="section-block">
                <h4>Manage Existing Departments</h4>
                <div class="form-group">
                    <label for="department_filter">Search Department Name</label>
                    <input type="text" id="department_filter" oninput="filterTableRows('department_filter', 'departmentGroupsTable')" placeholder="Type to search departments...">
                </div>
                <div id="department_groups_list">
                <div class="table-scroll"><table id="departmentGroupsTable">
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>Group Name</th>
                        <th>Group Name1</th>
                        <th>Group Name2</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $departments_sql = "SELECT department, group_name, group_name1, group_name2 FROM department_groups";
                $departments_result = $conn->query($departments_sql);
                if ($departments_result->num_rows > 0) {
                    while ($row = $departments_result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td><input type='hidden' name='departments_to_update[" . htmlspecialchars($row['department']) . "]' value='" . htmlspecialchars($row['department']) . "'>" . htmlspecialchars($row['department']) . "</td>";
                    echo "<td><input type='text' name='groups_to_update[" . htmlspecialchars($row['department']) . "][group_name]' value='" . htmlspecialchars($row['group_name']) . "'></td>";
                    echo "<td><input type='text' name='groups_to_update[" . htmlspecialchars($row['department']) . "][group_name1]' value='" . htmlspecialchars($row['group_name1']) . "'></td>";
                    echo "<td><input type='text' name='groups_to_update[" . htmlspecialchars($row['department']) . "][group_name2]' value='" . htmlspecialchars($row['group_name2']) . "'></td>";
                    echo "<td class='action-cell'><span class='action-buttons'><button type='submit' name='save_department' value='" . htmlspecialchars($row['department']) . "'>Save</button><button type='submit' name='delete_department' value='" . htmlspecialchars($row['department']) . "' onclick='return confirm(\"Delete this department mapping?\")'>Delete</button></span></td>";
                    echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='5'>No departments found.</td></tr>";
                }
                ?>
                </tbody>
                </table></div>
                </div>
            </div>
            <div class="section-block">
                <h4>Add New Department</h4>
                <div class="inline-fields">
                    <div class="field"><label for="new_department">Department</label><input type="text" id="new_department" name="new_department"></div>
                    <div class="field"><label for="new_group_name">Group Name</label><input type="text" id="new_group_name" name="new_group_name"></div>
                    <div class="field"><label for="new_group_name1">Group Name1</label><input type="text" id="new_group_name1" name="new_group_name1"></div>
                    <div class="field"><label for="new_group_name2">Group Name2</label><input type="text" id="new_group_name2" name="new_group_name2"></div>
                </div>
            </div>
            <button type="submit">Save All Changes</button>
            </form>
        </div>

        <div id="update_user_permissionsForm" class="rule-form" style="display:none;">
            <h3>Update User Permissions</h3>
            <p class="muted-text">User Permissions maps usernames to up to three groups.</p>
            <form method="POST" action="general_cpanel.php">
                <input type="hidden" name="rule_name" value="update_user_permissions">
                <div class="section-block">
                    <h4>Manage Existing Users</h4>
                    <div class="form-group">
                        <label for="user_filter">Search Username</label>
                        <input type="text" id="user_filter" oninput="filterTableRows('user_filter', 'userPermissionsTable')" placeholder="Type to search users...">
                    </div>
                    <div id="user_permissions_list">
                        <div class="table-scroll"><table id="userPermissionsTable">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Group Name 1</th>
                                <th>Group Name 2</th>
                                <th>Group Name 3</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $users_sql = "SELECT username, group_name_1, group_name_2, group_name_3 FROM user_permissions";
                        $users_result = $conn->query($users_sql);
                        if ($users_result->num_rows > 0) {
                            while ($row = $users_result->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td><input type='hidden' name='users_to_update[" . htmlspecialchars($row['username']) . "][username]' value='" . htmlspecialchars($row['username']) . "'>" . htmlspecialchars($row['username']) . "</td>";
                                echo "<td><select name='users_to_update[" . htmlspecialchars($row['username']) . "][group_name_1]'><option value=''>Select group</option>";
                                foreach ($available_groups as $group_option) {
                                    $selected = ($row['group_name_1'] === $group_option) ? ' selected' : '';
                                    echo "<option value='" . htmlspecialchars($group_option) . "'" . $selected . ">" . htmlspecialchars($group_option) . "</option>";
                                }
                                echo "</select></td>";
                                echo "<td><select name='users_to_update[" . htmlspecialchars($row['username']) . "][group_name_2]'><option value=''>Select group</option>";
                                foreach ($available_groups as $group_option) {
                                    $selected = ($row['group_name_2'] === $group_option) ? ' selected' : '';
                                    echo "<option value='" . htmlspecialchars($group_option) . "'" . $selected . ">" . htmlspecialchars($group_option) . "</option>";
                                }
                                echo "</select></td>";
                                echo "<td><select name='users_to_update[" . htmlspecialchars($row['username']) . "][group_name_3]'><option value=''>Select group</option>";
                                foreach ($available_groups as $group_option) {
                                    $selected = ($row['group_name_3'] === $group_option) ? ' selected' : '';
                                    echo "<option value='" . htmlspecialchars($group_option) . "'" . $selected . ">" . htmlspecialchars($group_option) . "</option>";
                                }
                                echo "</select></td>";
                                echo "<td class='action-cell'><span class='action-buttons'><button type='submit' name='save_user_permission' value='" . htmlspecialchars($row['username']) . "'>Save</button><button type='submit' name='delete_user_permission' value='" . htmlspecialchars($row['username']) . "' onclick='return confirm(\"Delete this user permission mapping?\")'>Delete</button></span></td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5'>No users found.</td></tr>";
                        }
                        ?>
                        </tbody>
                        </table></div>
                    </div>
                </div>
                <div class="section-block">
                    <h4>Add New User</h4>
                    <div class="inline-fields">
                        <div class="field"><label for="new_username">Username</label><input type="text" id="new_username" name="new_username"></div>
                        <div class="field">
                            <label for="new_group_name_1">Group Name 1</label>
                            <select id="new_group_name_1" name="new_group_name_1">
                                <option value="">Select group</option>
                                <?php foreach ($available_groups as $group_option): ?>
                                    <option value="<?php echo htmlspecialchars($group_option); ?>"><?php echo htmlspecialchars($group_option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="new_group_name_2">Group Name 2</label>
                            <select id="new_group_name_2" name="new_group_name_2">
                                <option value="">Select group</option>
                                <?php foreach ($available_groups as $group_option): ?>
                                    <option value="<?php echo htmlspecialchars($group_option); ?>"><?php echo htmlspecialchars($group_option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="new_group_name_3">Group Name 3</label>
                            <select id="new_group_name_3" name="new_group_name_3">
                                <option value="">Select group</option>
                                <?php foreach ($available_groups as $group_option): ?>
                                    <option value="<?php echo htmlspecialchars($group_option); ?>"><?php echo htmlspecialchars($group_option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <button type="submit">Save All Changes</button>
            </form>
        </div>

        <div id="update_page_permissionsForm" class="rule-form" style="display:none;">
            <h3>Update Page Permissions</h3>
            <p class="muted-text">Page Permissions controls which screens each user can access.</p>
            <form method="POST" action="general_cpanel.php">
                <input type="hidden" name="rule_name" value="update_page_permissions">
                <div class="section-block">
                    <h4>User Access by Page</h4>
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
                    <div class="form-group">
                        <label for="page_filter">Search Page Name</label>
                        <input type="text" id="page_filter" oninput="filterPageOptions()" placeholder="Type to filter page lists...">
                    </div>
                    <label for="page_select">Select Page:</label>
                    <select id="page_select" name="page_select">
                        <!-- Options will be populated by JavaScript -->
                    </select>
                    <label for="page_access">Access:</label>
                    <input type="radio" id="access_yes" name="page_access" value="1"> Yes
                    <input type="radio" id="access_no" name="page_access" value="0"> No
                    <button type="submit" name="action" value="update_page_access">Update Access</button>
                </div>
                <div class="section-block">
                    <h4>Add/Remove Pages for Selected User</h4>
                    <label for="user_manage_select">Select User:</label>
                    <select id="user_manage_select" name="user_manage_select" onchange="updateManagedUserPages()">
                        <option value="__all__">All Users</option>
                        <?php
                        $users_sql = "SELECT user_access FROM page_access";
                        $users_result = $conn->query($users_sql);
                        if ($users_result->num_rows > 0) {
                            while ($row = $users_result->fetch_assoc()) {
                                if ($row['user_access'] !== 'default') {
                                    echo "<option value='" . $row['user_access'] . "'>" . $row['user_access'] . "</option>";
                                }
                            }
                        }
                        ?>
                    </select>
                    <label for="user_page_select">Select Page to Remove:</label>
                    <select id="user_page_select" name="user_page_select">
                        <!-- Options will be populated by JavaScript -->
                    </select>
                    <button type="submit" name="action" value="remove_user_page" onclick="return confirm('Remove selected page from this user?')">Remove Page</button>
                    <label for="new_user_page">Add New Page:</label>
                    <input type="text" id="new_user_page" name="new_user_page">
                    <button type="submit" name="action" value="add_user_page">Add Page</button>
                </div>
                <div class="section-block">
                    <h4>Default Page Template</h4>
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
                    <button type="submit" name="action" value="remove_page" onclick="return confirm('Remove selected page from default template?')">Remove Page</button>
                    <label for="new_default_page">Add New Page:</label>
                    <input type="text" id="new_default_page" name="new_default_page">
                    <button type="submit" name="action" value="add_page">Add Page</button>
                </div>
                <div class="section-block">
                    <h4>Module Access (Open/Close)</h4>
                    <p class="muted-text">Toggle complete modules for a user. Open means all module pages are allowed; Close means all are denied.</p>
                    <label for="module_access_user">Select User:</label>
                    <select id="module_access_user" name="module_access_user" onchange="this.form.submit()">
                        <?php if (empty($module_users)): ?>
                            <option value="">No users found</option>
                        <?php else: ?>
                            <?php foreach ($module_users as $module_user_row): ?>
                                <?php $module_user_name = (string) $module_user_row['user_access']; ?>
                                <option value="<?php echo htmlspecialchars($module_user_name); ?>"<?php echo $module_access_user === $module_user_name ? ' selected' : ''; ?>>
                                    <?php echo htmlspecialchars($module_user_name); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>

                    <?php if (!empty($module_catalog) && $module_access_user !== ''): ?>
                        <div class="table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Module</th>
                                        <th>Status</th>
                                        <th>Pages Included</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($module_catalog as $module_key => $module_info): ?>
                                        <?php $is_module_open = !empty($module_access_states[$module_key]); ?>
                                        <tr>
                                            <td>
                                                <label>
                                                    <input type="checkbox" name="module_access[<?php echo htmlspecialchars($module_key); ?>]" value="1"<?php echo $is_module_open ? ' checked' : ''; ?>>
                                                    <?php echo htmlspecialchars($module_info['label']); ?>
                                                </label>
                                            </td>
                                            <td><?php echo $is_module_open ? 'Open' : 'Closed'; ?></td>
                                            <td><?php echo htmlspecialchars(implode(', ', $module_info['pages'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <button type="submit" name="action" value="update_module_access">Save Module Access</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
<?php
app_close_content_panel();
app_render_page_end();
?>
<script src="assets/js/app.js"></script>
</body>
</html>
