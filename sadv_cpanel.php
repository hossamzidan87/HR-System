<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';

// Check if the logged-in user is 'admin'
if (!isset($_SESSION['username']) || $_SESSION['username'] !== 'admin') {
    echo "Access Denied. You do not have permission to view this page.";
    exit;
}

// Initialize variables
$sadv_start = '';
$sadv_end = '';

// Fetch the current opening and closing times from the "rules" table
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

        // Update the "rules" table with the new opening and closing times
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
    <style>
        .container {
            width: 50%;
            margin: 0 auto;
            text-align: center;
            padding: 50px;
        }
        .form-group {
            margin: 20px 0;
        }
        .form-group label {
            font-size: 20px;
            margin-right: 10px;
        }
        .form-group input {
            padding: 10px;
            font-size: 16px;
        }
        .form-group button {
            padding: 10px 20px;
            font-size: 16px;
            background-color: #007BFF;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .form-group button:hover {
            background-color: #0056b3;
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
        <h1>Salary Adv Cpanel</h1>
        <form method="POST" action="sadv_cpanel.php">
            <div class="form-group">
                <label for="sadv_start">Opening Time:</label>
                <input type="datetime-local" id="sadv_start" name="sadv_start" value="<?php echo htmlspecialchars($sadv_start); ?>" required>
            </div>
            <div class="form-group">
                <label for="sadv_end">Closing Time:</label>
                <input type="datetime-local" id="sadv_end" name="sadv_end" value="<?php echo htmlspecialchars($sadv_end); ?>" required>
            </div>
            <div class="form-group">
                <button type="submit">Update Times</button>
            </div>
        </form>
    </div>
</body>
</html>
