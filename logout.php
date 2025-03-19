<?php
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session.
session_destroy();

// Clear the "remember me" cookie
setcookie("remember_me", "", time() - 3600, "/", "", true, true);

// If it's desired to kill the session, also delete the session cookie. This part is generally recommended.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}


// Redirect to login page
header("Location: index.php");
exit();
?>