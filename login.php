<?php
include 'db_connection.php';
session_start();

function findRememberedUser(mysqli $conn, string $token): ?string
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        header("Location: index.php?error=" . urlencode("Username and password are required."));
        exit();
    }

    $stmt = $conn->prepare("SELECT username, password FROM user_management WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($dbUsername, $dbPassword);
        $stmt->fetch();

        if (password_verify($password, $dbPassword)) {
            $_SESSION['username'] = $dbUsername;

            $token = bin2hex(random_bytes(32));
            $hashedToken = password_hash($token, PASSWORD_DEFAULT);

            $stmt2 = $conn->prepare("UPDATE user_management SET remember_token = ? WHERE username = ?");
            $stmt2->bind_param("ss", $hashedToken, $dbUsername);
            $stmt2->execute();
            $stmt2->close();

            setcookie('remember_me', $token, time() + 86400, "/", "", false, true);

            $stmt->close();
            $conn->close();

            header("Location: welcome.php");
            exit();
        }
    }

    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }

    $conn->close();
    header("Location: index.php?error=" . urlencode("Invalid username or password."));
    exit();
}

if (!isset($_SESSION['username']) && isset($_COOKIE['remember_me'])) {
    $rememberedUser = findRememberedUser($conn, $_COOKIE['remember_me']);

    if ($rememberedUser !== null) {
        $_SESSION['username'] = $rememberedUser;
        $conn->close();
        header("Location: welcome.php");
        exit();
    }

    setcookie('remember_me', '', time() - 3600, "/", "", false, true);
}

$conn->close();
header("Location: index.php");
exit();
