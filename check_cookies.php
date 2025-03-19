<?php
session_start();

// Check if the session variable is set
if (!isset($_SESSION['username'])) {
    // Check for "remember me" cookie
    if (isset($_COOKIE['remember_me'])) {
        include 'db_connection.php'; // Include your database connection

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
            }
        }
        $stmt->close();
        $conn->close();
        if(!isset($_SESSION['username'])){
            setcookie('remember_me', '', time() - 3600, "/", "", true, true);
            header("Location: index.php");
            exit();
        }
    }
    else{
        // Redirect to login page if session and cookie are not set
        header("Location: index.php");
        exit();
    }
}

// If the code reaches here, the user is logged in (either by session or "remember me")
// You can access the username using $_SESSION['username']
?>