<?php
include 'db_connection.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT username, password FROM user_management WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($dbUsername, $dbPassword);
        $stmt->fetch();

        if (password_verify($password, $dbPassword)) {
            $_SESSION['username'] = $dbUsername;

            // Automatic "remember me" for 24 hours
            $token = bin2hex(random_bytes(32));
            $hashedToken = password_hash($token, PASSWORD_DEFAULT);

            $stmt2 = $conn->prepare("UPDATE user_management SET remember_token = ? WHERE username = ?");
            $stmt2->bind_param("ss", $hashedToken, $dbUsername);
            $stmt2->execute();
            $stmt2->close();

            setcookie('remember_me', $token, time() + 86400, "/", "", true, true); // 24 hours (86400 seconds)

            $stmt->close();
            header("Location: welcome.php");
            exit();
        } else {
            echo "Invalid username or password";
            header("refresh:2;url=index.php");
        }
        } else {
        echo "Invalid username or password";
        header("refresh:2;url=index.php");
        }
    $stmt->close();
}

// Check for "remember me" cookie on page load
if (!isset($_SESSION['username']) && isset($_COOKIE['remember_me'])) {
    $token = $_COOKIE['remember_me'];
    $hashedToken = password_hash($token, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("SELECT username, remember_token FROM user_management WHERE remember_token = ?");
    $stmt->bind_param("s", $hashedToken);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 1) {
        $stmt->bind_result($username, $dbHashedToken);
        $stmt->fetch();
        if (password_verify($token, $dbHashedToken)) {
            $_SESSION['username'] = $username;
            header("Location: welcome.php");
            $stmt->close();
            exit();
        }
    }
    $stmt->close();
    setcookie('remember_me', '', time() - 3600, "/", "", true, true);
}

$conn->close();
?>