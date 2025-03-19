<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';
// Check if the logged-in user is 'admin'

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cpanel</title>
    <style>
        .container {
            width: 35%;
            margin: 0 auto;
            text-align: center;
            padding: 50px;
        }
        .link {
            display: block;
            margin: 20px 0;
            padding: 10px;
            font-size: 20px;
            text-decoration: none;
            color: #fff;
            background-color: #007BFF;
            border-radius: 5px;
        }
        .link:hover {
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
    <a href="logout.php"><img src="/images/icons/logout.png" alt="logout"></a>
</div>
</div>
    <div class="container">
        <h1>Cpanel</h1>
        <a href="general_cpanel.php" class="link">General Cpanel</a>
        <a href="overtime_cpanel.php" class="link">OverTime Cpanel</a>
        <a href="evaluation_cpanel.php" class="link">Evaluation Cpanel</a>
        <a href="sadv_cpanel.php" class="link">Salary Adv Cpanel</a>
    </div>
</body>
</html>