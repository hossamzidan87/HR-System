<?php
include 'check_cookies.php';
include 'db_connection.php';
include 'page_access.php';
$username = $_SESSION['username'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Home</title>
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
    </style>
</head>
<body>
    <div class="container">
        <div class="image-link">
            <a href="overtime_home.php"><img src="\images\welcome\otre.png" alt="Overtime"></a>
            <p>Overtime</p>
        </div>
        <div class="image-link">
            <a href="evaluation.php"><img src="\images\welcome\evaluation.png" alt="Evaluation"></a>
            <p>Evaluation</p>
        </div>
        <div class="image-link">
            <a href="sadv_list.php"><img src="\images\welcome\sadv.png" alt="Salary ADV"></a>
            <p>Salary Advance</p>
        </div>
        <div class="image-link">
            <a href="logout.php"><img src="\images\welcome\logout.png" alt="Logout"></a>
            <p>Logout</p>
        </div>
        <?php if ($username === 'admin') { ?>
        <div class="image-link">
            <a href="cpanel.php"><img src="\images\welcome\cpanel.png" alt="Control Panel"></a>
            <p>Control Panel</p>
        </div>
        <?php } ?>
    </div>
</body>
</html>
