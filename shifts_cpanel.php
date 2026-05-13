<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';
include 'includes/app_helpers.php';

$shift_start = '';
$shift_end = '';
$selected_option = $_POST['selected_option'] ?? 'close_time';
if ($selected_option !== 'close_time') {
    $selected_option = 'close_time';
}

$stmt = $conn->prepare("SELECT shift_start, shift_end FROM rules WHERE name = 'close_time' LIMIT 1");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $shift_start = $row['shift_start'];
        $shift_end = $row['shift_end'];
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_close_time'])) {
    $shift_start = filter_input(INPUT_POST, 'shift_start', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $shift_end = filter_input(INPUT_POST, 'shift_end', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    $stmt = $conn->prepare("UPDATE rules SET shift_start = ?, shift_end = ? WHERE name = 'close_time'");
    if ($stmt) {
        $stmt->bind_param("ss", $shift_start, $shift_end);
        if ($stmt->execute()) {
            echo "<p>Close time updated successfully!</p>";
        } else {
            echo "<p>Error: " . $stmt->error . "</p>";
        }
        $stmt->close();
    } else {
        echo "<p>Error preparing statement: " . $conn->error . "</p>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Shifts Cpanel</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="icon" type="image/png" href="images/logo.png">
    <script>
        function openOption(optionName) {
            var forms = document.getElementsByClassName('rule-form');
            var tabs = document.getElementsByClassName('panel-tab');

            for (var i = 0; i < forms.length; i++) {
                forms[i].style.display = 'none';
            }
            for (var j = 0; j < tabs.length; j++) {
                tabs[j].classList.remove('active');
            }

            if (optionName) {
                var form = document.getElementById(optionName + 'Form');
                if (form) {
                    form.style.display = 'block';
                }
                var tab = document.querySelector('.panel-tab[data-target="' + optionName + '"]');
                if (tab) {
                    tab.classList.add('active');
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            var initialOption = document.body.getAttribute('data-active-option') || 'close_time';
            openOption(initialOption);
        });
    </script>
    <style>
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
    </style>
</head>
<body data-active-option="<?php echo htmlspecialchars($selected_option); ?>">
<?php
app_render_page_header('SC', 'Shifts Cpanel', 'Configure when the night shift workflow is available.', [
    ['label' => 'Home', 'href' => 'welcome.php'],
    ['label' => 'Cpanel', 'href' => 'cpanel.php'],
    ['label' => 'Logout', 'href' => 'logout.php'],
]);
app_render_page_hero('Admin area', 'The shift settings page now uses the same responsive shell as the rest of the app.', 'Select a control option and update the shift access window inside a cleaner admin layout.', [
    ['title' => 'Current Start', 'text' => $shift_start ?: 'Not set'],
    ['title' => 'Current End', 'text' => $shift_end ?: 'Not set'],
]);
app_open_content_panel('Shift Window Settings', 'Use the control tabs to manage shift window settings and save the new date-time range.');
?>
<div class="panel-tabbar">
    <button type="button" class="panel-tab" data-target="close_time" onclick="openOption('close_time')">Close Time</button>
</div>

<div id="close_timeForm" class="rule-form" style="display:none;">
    <form method="POST" action="shifts_cpanel.php">
        <input type="hidden" name="selected_option" value="close_time">
        <div class="form-row">
            <label for="shift_start">Shift Start</label>
            <input type="datetime-local" id="shift_start" name="shift_start" value="<?php echo htmlspecialchars($shift_start); ?>" required>
            <label for="shift_end">Shift End</label>
            <input type="datetime-local" id="shift_end" name="shift_end" value="<?php echo htmlspecialchars($shift_end); ?>" required>
            <button type="submit" name="update_close_time">Update Close Time</button>
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
