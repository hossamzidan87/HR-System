<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overtime Report</title>
    <style>
        .container {
            text-align: center;
            margin-top: 50px;
        }
        .form-group select, .form-group input {
            padding: 8px;
            border: 1px solid #ccc;
            font-weight: bold;
            border-radius: 4px;
            font-size: 12px;
        }
        .button-container {
            display: flex;
            justify-content: center;
            gap: 10px;
        }
        .button {
            padding: 10px 20px;
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
</head>
<body>
    <div class="image-container">
    <div class="image-link">
        <a href="welcome.php"><img src="/images/icons/home.png" alt="home"></a>
    </div>
    <div class="image-link">
        <a href="overtime_home.php"><img src="/images/icons/otre.png" alt="Overtime"></a>
        </div>
    <div class="image-link">
        <a href="logout.php"><img src="/images/icons/logout.png" alt="logout"></a>
    </div>
    </div>
    <div class="container">
        <h1>Overtime Reports</h1>
        <div class="button-container">
            <form method="POST" action="daily_report.php">
                <button class="button" name="report_type" value="daily">Daily</button>
            </form>
            <form method="GET" action="weekly_overtime.php">
                <button class="button" name="report_type" value="weekly">Weekly</button>
            </form>
        </div>
    </div>
</body>
</html>
