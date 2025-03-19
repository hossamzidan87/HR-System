<?php
if (!isset($_SESSION['username'])) {
    echo "Access Denied. You are not logged in.";
    exit;
}

$username = $_SESSION['username'];
$current_page = basename($_SERVER['PHP_SELF']);

$access_sql = "SELECT pages FROM page_access WHERE user_access = '$username'";
$access_result = $conn->query($access_sql);

if ($access_result->num_rows > 0) {
    $access_row = $access_result->fetch_assoc();
    $pages = explode(',', $access_row['pages']);
    $has_access = false;

    for ($i = 0; $i < count($pages); $i += 2) {
        if ($pages[$i] == $current_page && $pages[$i + 1] == '1') {
            $has_access = true;
            break;
        }
    }

    if (!$has_access) {
        echo "<div style='color: red; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; font-size: 24px;'>Access Denied. You do not have permission to view this page <a href='welcome.php'><img src='/images/icons/home.png' alt='home' style='width:40px;height:40px;'></a></div>";
        exit;
    }
} else {
    echo "<div style='color: red; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; font-size: 24px;'>Access Denied. You do not have permission to view this page <a href='welcome.php'><img src='/images/icons/home.png' alt='home' style='width:40px;height:40px;'></a></div>";
    exit;
}
?>