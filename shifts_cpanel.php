<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';

// Initialize variables
$shift_start = '';
$shift_end = '';
$selected_option = $_POST['selected_option'] ?? '';

// Fetch current close time settings
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_close_time'])) {
    $shift_start = filter_input(INPUT_POST, 'shift_start', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $shift_end = filter_input(INPUT_POST, 'shift_end', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    // Update the close time in the rules table
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
    <style>
        .container { width: 50%; margin: 0 auto; padding: 20px; text-align: center; }
        .form-group { margin: 15px 0; }
        label { font-size: 18px; margin-right: 10px; }
        input, select { padding: 10px; font-size: 16px; }
        button { padding: 10px 20px; font-size: 16px; background-color: #007BFF; color: white; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background-color: #0056b3; }
        .hidden { display: none; }
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
    <script>
        function showOptionForm() {
            document.getElementById('optionForm').submit();
        }
    </script>
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
        <h1>Shifts Cpanel</h1>
        <form id="optionForm" method="POST" action="shifts_cpanel.php">
            <div class="form-group">
                <label for="selected_option">Select Option:</label>
                <select id="selected_option" name="selected_option" onchange="showOptionForm()" required>
                    <option value="">Select an option</option>
                    <option value="close_time" <?php echo ($selected_option === 'close_time') ? 'selected' : ''; ?>>Close Time</option>
                </select>
            </div>
        </form>

        <?php if ($selected_option === 'close_time'): ?>
        <form method="POST" action="shifts_cpanel.php">
            <input type="hidden" name="selected_option" value="close_time">
            <div class="form-group">
                <label for="shift_start">Shift Start:</label>
                <input type="datetime-local" id="shift_start" name="shift_start" value="<?php echo htmlspecialchars($shift_start); ?>" required>
            </div>
            <div class="form-group">
                <label for="shift_end">Shift End:</label>
                <input type="datetime-local" id="shift_end" name="shift_end" value="<?php echo htmlspecialchars($shift_end); ?>" required>
            </div>
            <button type="submit" name="update_close_time">Update Close Time</button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>
