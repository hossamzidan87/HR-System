<?php
session_start();

if (isset($_COOKIE['remember_me'])) {
    header("Location: welcome.php");
    exit();
}

$loginError = isset($_GET['error']) ? trim($_GET['error']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Rubyred Fetih HR Management System</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="icon" type="image/png" href="images/logo.png">
</head>
<body>
    <main class="auth-shell">
        <section class="auth-panel">
            <div class="auth-hero">
                <div class="brand-block">
                    <div class="brand-mark"><img src="images/logo.png" alt="Logo"></div>
                    <div class="brand-copy">
                        <h1>Rubyred Fetih</h1>
                        <p>HR operations dashboard for overtime, shifts, evaluations, and salary advance workflows.</p>
                    </div>
                </div>

                <div class="hero-copy">
                    <span class="hero-kicker">Modernized workspace</span>
                    <h2>Faster daily operations with a cleaner, dynamic interface.</h2>
                    <p>The refreshed homepage introduces a live dashboard, reusable navigation, responsive cards, and JavaScript-powered status updates without changing your core PHP business logic.</p>
                </div>

                <div class="auth-highlights">
                    <div class="auth-highlight">
                        <strong>Live dashboard</strong>
                        <p>See employees, overtime, shifts, and evaluation progress at a glance after login.</p>
                    </div>
                    <div class="auth-highlight">
                        <strong>Quicker navigation</strong>
                        <p>Module cards, filters, and focused shortcuts reduce extra clicks across the application.</p>
                    </div>
                    <div class="auth-highlight">
                        <strong>API-ready structure</strong>
                        <p>The new frontend layer gives us a solid base to keep modernizing other pages incrementally.</p>
                    </div>
                </div>

                <div class="auth-actions">
                    <a class="btn-secondary" href="../meeting/">Meeting Room Booking</a>
                </div>
            </div>

            <div class="auth-card">
                <h2>Sign in</h2>
                <p>Use your existing system account to continue.</p>

                <div class="form-note">The app keeps your current authentication flow and now provides clearer validation feedback.</div>
                <div class="error-banner <?php echo $loginError ? '' : 'is-hidden'; ?>" data-login-error><?php echo htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8'); ?></div>

                <form action="login.php" method="post" data-login-form>
                    <div class="field">
                        <label for="User">Username</label>
                        <input type="text" name="username" id="User" placeholder="Enter username" autocomplete="username">
                    </div>

                    <div class="field">
                        <label for="Password">Password</label>
                        <input type="password" name="password" id="Password" placeholder="Enter password" autocomplete="current-password">
                    </div>

                    <div class="auth-actions">
                        <button class="btn-primary" type="submit">Log In</button>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <script src="assets/js/app.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var userField = document.getElementById('User');
            if (userField) {
                userField.focus();
            }
        });
    </script>
</body>
</html>
