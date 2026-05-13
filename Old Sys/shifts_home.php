<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';
$username = $_SESSION['username'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Night Shift Request - Home</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        .container {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            gap: 20px;
        }
        .image-link {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .image-link img {
            width: 100px;
            height: 100px;
        }
        .image-link:hover {
            box-shadow: 0 0 2px 1px rgba(0, 140, 186, 0.5);
        }
        .ico-container { /* New container */
    display: flex;
    justify-content: flex-end; /* Align items to the right */
    align-items: center; /* Vertically center items */
}
.ico-link {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 5px;
    width: 25px; /* Adjust as needed */
    margin: 0 5px; /* Space between images */
    display: inline-block; /* to prevent collapsing margins */
}

.ico-link:hover {
    box-shadow: 0 0 2px 1px rgba(0, 140, 186, 0.5);
}

.ico-link img {
    width: 100%; /* Make image fill container */
    height: auto; /* Maintain aspect ratio */
    display: block; /* Prevents small gap below image */
}
    </style>
</head>
<body>
<div class="ico-container">
    <div class="ico-link">
        <a href="welcome.php"><img src="/images/icons/home.png" alt="home"></a>
    </div>
    <div class="ico-link">
        <a href="logout.php"><img src="/images/icons/logout.png" alt="logout"></a>
    </div>
</div>
<h1 style="text-align: center; font-family: Arial; color: #333;">Night Shift Request</h1>
    <div class="container">
        <div class="image-link">
            <a href="night_shift.php"><img src="\images\welcome\night.png" alt="Overtime"></a>
            <p>Night Shift Request</p>
        </div>
        <div class="image-link">
            <a href="night_report.php"><img src="\images\welcome\report.png" alt="Overtime Report"></a>
            <p>Night Shift Report</p>
        </div>
        <?php if ($username === 'admin') { ?>
        <div class="image-link">
            <a href="shifts_cpanel.php"><img src="\images\welcome\cpanel.png" alt="Control Panel"></a>
            <p>Control Panel</p>
        </div>
        <?php } ?>
    </div>
</body>
</html>
