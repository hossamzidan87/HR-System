<?php

function app_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function app_get_access_map(mysqli $conn, string $username): array
{
    $map = [];
    $stmt = $conn->prepare("SELECT pages FROM page_access WHERE user_access = ?");

    if (!$stmt) {
        return $map;
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->bind_result($pages);

    if ($stmt->fetch() && $pages) {
        $parts = array_map('trim', explode(',', $pages));

        for ($i = 0; $i < count($parts); $i += 2) {
            $page = $parts[$i] ?? null;
            $allowed = $parts[$i + 1] ?? '0';

            if ($page) {
                $map[$page] = $allowed === '1';
            }
        }
    }

    $stmt->close();

    return $map;
}

function app_has_page_access(array $accessMap, string $page): bool
{
    return !empty($accessMap[$page]);
}

function app_is_admin(string $username): bool
{
    return strtolower($username) === 'admin';
}

function app_get_allowed_departments(mysqli $conn, string $username): array
{
    $allowedDepartments = [];

    $stmt = $conn->prepare('SELECT group_name_1, group_name_2, group_name_3 FROM user_permissions WHERE username = ?');
    if (!$stmt) {
        return $allowedDepartments;
    }

    $stmt->bind_param('s', $username);
    $stmt->execute();
    $permissionsResult = $stmt->get_result();
    if ($permissionsResult && $permissionsResult->num_rows > 0) {
        $userPermissions = $permissionsResult->fetch_assoc();
        $userGroups = [];
        foreach (['group_name_1', 'group_name_2', 'group_name_3'] as $groupKey) {
            if (!empty($userPermissions[$groupKey])) {
                $userGroups[] = $userPermissions[$groupKey];
            }
        }

        if (!empty($userGroups)) {
            $escapedGroups = array_map([$conn, 'real_escape_string'], $userGroups);
            $groupsIn = "'" . implode("','", $escapedGroups) . "'";
            $departmentsSql = "SELECT DISTINCT department FROM department_groups WHERE group_name IN ($groupsIn) OR group_name1 IN ($groupsIn) OR group_name2 IN ($groupsIn) ORDER BY department";
            $departmentsResult = $conn->query($departmentsSql);
            if ($departmentsResult) {
                while ($row = $departmentsResult->fetch_assoc()) {
                    $allowedDepartments[] = $row['department'];
                }
            }
        }
    }

    $stmt->close();

    return $allowedDepartments;
}

function app_get_dashboard_modules(array $accessMap, string $username): array
{
    $modules = [
        [
            'id' => 'overtime',
            'title' => 'Overtime',
            'description' => 'Submit, review, and report overtime records.',
            'href' => 'overtime_home.php',
            'accent' => 'ruby',
            'icon' => 'OT',
            'pages' => ['overtime_home.php', 'overtime.php', 'overtime_report.php'],
            'links' => [
                ['label' => 'Request', 'href' => 'overtime.php'],
                ['label' => 'Reports', 'href' => 'overtime_report.php'],
                ['label' => 'Employees', 'href' => 'employee_list.php'],
            ],
        ],
        [
            'id' => 'shifts',
            'title' => 'Night Shift',
            'description' => 'Manage shift windows, assignments, and reports.',
            'href' => 'shifts_home.php',
            'accent' => 'amber',
            'icon' => 'NS',
            'pages' => ['shifts_home.php', 'night_shift.php', 'night_report.php'],
            'links' => [
                ['label' => 'Plan Shift', 'href' => 'night_shift.php'],
                ['label' => 'Reports', 'href' => 'night_report.php'],
            ],
        ],
        [
            'id' => 'evaluation',
            'title' => 'Evaluation',
            'description' => 'Track quarterly and annual performance reviews.',
            'href' => 'evaluation.php',
            'accent' => 'teal',
            'icon' => 'EV',
            'pages' => ['evaluation.php', 'quarter_evaluation.php', 'quarter_report.php', 'annual_evaluation.php'],
            'links' => [
                ['label' => 'Quarterly', 'href' => 'quarter_evaluation.php'],
                ['label' => 'Annual', 'href' => 'annual_evaluation.php'],
                ['label' => 'Reports', 'href' => 'quarter_report.php'],
            ],
        ],
        [
            'id' => 'salary-advance',
            'title' => 'Salary Advance',
            'description' => 'Prepare department-based salary advance sheets.',
            'href' => 'sadv_list.php',
            'accent' => 'slate',
            'icon' => 'SA',
            'pages' => ['sadv_list.php'],
            'links' => [
                ['label' => 'Open List', 'href' => 'sadv_list.php'],
            ],
        ],
    ];

    $visibleModules = [];

    foreach ($modules as $module) {
        foreach ($module['pages'] as $page) {
            if (app_has_page_access($accessMap, $page)) {
                $module['isAdmin'] = false;
                $visibleModules[] = $module;
                break;
            }
        }
    }

    if (app_is_admin($username) && app_has_page_access($accessMap, 'cpanel.php')) {
        $visibleModules[] = [
            'id' => 'control-panel',
            'title' => 'Control Panel',
            'description' => 'Configure rules, permissions, users, and master data.',
            'href' => 'cpanel.php',
            'accent' => 'ink',
            'icon' => 'CP',
            'pages' => ['cpanel.php'],
            'links' => [
                ['label' => 'Open Panel', 'href' => 'cpanel.php'],
                ['label' => 'General', 'href' => 'general_cpanel.php'],
                ['label' => 'Overtime', 'href' => 'overtime_cpanel.php'],
            ],
            'isAdmin' => true,
        ];
    }

    return $visibleModules;
}

function app_get_module_by_id(array $modules, string $id): ?array
{
    foreach ($modules as $module) {
        if (($module['id'] ?? '') === $id) {
            return $module;
        }
    }

    return null;
}

function app_render_page_header(string $mark, string $title, string $subtitle, array $links = []): void
{
    echo '<main class="app-shell">';
    echo '<header class="topbar">';
    echo '<div class="brand-block">';
    echo '<div class="brand-mark"><img src="images/logo.png" alt="Logo"></div>';
    echo '<div class="brand-copy">';
    echo '<h1>' . app_escape($title) . '</h1>';
    echo '<p>' . app_escape($subtitle) . '</p>';
    echo '</div>';
    echo '</div>';
    echo '<div class="toolbar-actions">';

    foreach ($links as $link) {
        $label = $link['label'] ?? '';
        $href = $link['href'] ?? '#';
        echo '<a class="ghost-chip" href="' . app_escape($href) . '">' . app_escape($label) . '</a>';
    }

    echo '</div>';
    echo '</header>';
}

function app_render_page_hero(string $kicker, string $headline, string $description, array $cards = []): void
{
    echo '<section class="hero-panel legacy-hero">';
    echo '<div class="hero-copy">';
    echo '<span class="hero-kicker">' . app_escape($kicker) . '</span>';
    echo '<h2>' . app_escape($headline) . '</h2>';
    echo '<p>' . app_escape($description) . '</p>';
    echo '</div>';
    echo '<aside class="hero-aside">';

    foreach ($cards as $card) {
        echo '<article class="info-card">';
        echo '<strong>' . app_escape($card['title'] ?? '') . '</strong>';
        echo '<p>' . app_escape($card['text'] ?? '') . '</p>';
        echo '</article>';
    }

    echo '</aside>';
    echo '</section>';
}

function app_render_state_page(
    string $mark,
    string $title,
    string $subtitle,
    string $kicker,
    string $headline,
    string $message,
    array $links = [],
    array $cards = [],
    array $actions = [],
    string $panelTitle = 'Status Overview',
    string $panelDescription = 'This workspace is temporarily unavailable right now.'
): void {
    $defaultCards = [
        ['title' => 'Current Time', 'text' => date('Y-m-d H:i')],
        ['title' => 'Status', 'text' => $headline],
    ];

    echo '<!DOCTYPE html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . app_escape($title) . '</title>';
    echo '<link rel="stylesheet" href="assets/css/app.css">';
    echo '<link rel="icon" type="image/png" href="images/logo.png">';
    echo '</head>';
    echo '<body>';

    app_render_page_header($mark, $title, $subtitle, $links);
    app_render_page_hero($kicker, $headline, $message, array_merge($cards, $defaultCards));
    app_open_content_panel($panelTitle, $panelDescription);
    echo '<div class="summary-grid">';
    echo '<article class="summary-card">';
    echo '<strong>What Happened</strong>';
    echo '<p>' . app_escape($message) . '</p>';
    echo '</article>';
    echo '<article class="summary-card">';
    echo '<strong>What You Can Do</strong>';
    echo '<p>Use the quick actions below to return to the related module or navigate back to the dashboard.</p>';
    echo '</article>';
    echo '</div>';

    if (!empty($actions)) {
        echo '<div class="panel-actions">';
        foreach ($actions as $action) {
            $label = $action['label'] ?? '';
            $href = $action['href'] ?? '#';
            echo '<a class="button" href="' . app_escape($href) . '">' . app_escape($label) . '</a>';
        }
        echo '</div>';
    }

    app_close_content_panel();
    app_render_page_end();
    echo '</body>';
    echo '</html>';
    exit;
}

function app_open_content_panel(string $title = '', string $description = ''): void
{
    echo '<section class="content-panel legacy-panel">';

    if ($title !== '' || $description !== '') {
        echo '<div class="section-head">';
        echo '<div class="section-title">';

        if ($title !== '') {
            echo '<h2>' . app_escape($title) . '</h2>';
        }

        if ($description !== '') {
            echo '<p>' . app_escape($description) . '</p>';
        }

        echo '</div>';
        echo '</div>';
    }
}

function app_close_content_panel(): void
{
    echo '</section>';
}

function app_render_page_end(): void
{
    echo '<script src="assets/js/app.js"></script>';
    echo '</main>';
}

