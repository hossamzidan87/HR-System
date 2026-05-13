<?php
session_start();

function restoreRememberedSession(mysqli $conn, string $token): ?string
{
    $result = $conn->query("SELECT username, remember_token FROM user_management WHERE remember_token IS NOT NULL AND remember_token <> ''");

    if (!$result) {
        return null;
    }

    while ($row = $result->fetch_assoc()) {
        if (!empty($row['remember_token']) && password_verify($token, $row['remember_token'])) {
            return $row['username'];
        }
    }

    return null;
}

if (!isset($_SESSION['username'])) {
    if (isset($_COOKIE['remember_me'])) {
        include 'db_connection.php';

        $rememberedUser = restoreRememberedSession($conn, $_COOKIE['remember_me']);
        $conn->close();

        if ($rememberedUser !== null) {
            $_SESSION['username'] = $rememberedUser;
        } else {
            setcookie('remember_me', '', time() - 3600, "/", "", false, true);
            header("Location: index.php");
            exit();
        }
    } else {
        header("Location: index.php");
        exit();
    }
}
