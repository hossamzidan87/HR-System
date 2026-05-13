<?php
if (!isset($_SESSION['username'])) {
    echo "Access Denied. You are not logged in.";
    exit;
}

$username = $_SESSION['username'];
$current_page = basename($_SERVER['PHP_SELF']);

// If duplicate page entries exist for a user, any explicit deny keeps access blocked.
$stmt = $conn->prepare("SELECT pages FROM page_access WHERE user_access = ?");
$has_access = false;

if ($stmt) {
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $allow_found = false;
        $deny_found = false;

        while ($access_row = $result->fetch_assoc()) {
            $parts = array_map('trim', explode(',', (string) ($access_row['pages'] ?? '')));
            for ($i = 0; $i < count($parts); $i += 2) {
                $page_name = $parts[$i] ?? '';
                $allowed = $parts[$i + 1] ?? '0';

                if ($page_name === $current_page) {
                    if ($allowed === '1') {
                        $allow_found = true;
                    } else {
                        $deny_found = true;
                    }
                }
            }
        }

        $has_access = $allow_found && !$deny_found;
    }

    $stmt->close();
}

if (!$has_access) {
    include_once __DIR__ . '/includes/app_helpers.php';
    app_render_state_page(
        'AD',
        'Access Denied',
        'You do not have permission to view this page.',
        'Restricted',
        'Page Access Denied',
        'Your account does not have access to this page. Please contact your administrator if you believe this is a mistake.',
        [
            ['label' => 'Home', 'href' => 'welcome.php'],
            ['label' => 'Logout', 'href' => 'logout.php'],
        ],
        [],
        [
            ['label' => 'Go to Dashboard', 'href' => 'welcome.php'],
        ],
        'Access Restricted',
        'This area requires explicit permission to enter.'
    );
}
?>