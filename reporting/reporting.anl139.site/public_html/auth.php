<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

define('USERS_FILE', __DIR__ . '/data/users.json');

function default_users(): array {
    return [
        'superadmin' => [
            'password_hash' => password_hash('superpw', PASSWORD_DEFAULT),
            'display_name' => 'Super Admin',
            'role' => 'super_admin',
            'allowed_sections' => ['overview', 'performance', 'behavioral']
        ],
        'sam' => [
            'password_hash' => password_hash('sam123', PASSWORD_DEFAULT),
            'display_name' => 'Analyst Sam',
            'role' => 'analyst',
            'allowed_sections' => ['performance']
        ],
        'sally' => [
            'password_hash' => password_hash('sally123', PASSWORD_DEFAULT),
            'display_name' => 'Analyst Sally',
            'role' => 'analyst',
            'allowed_sections' => ['performance', 'behavioral']
        ],
        'viewer1' => [
            'password_hash' => password_hash('viewer123', PASSWORD_DEFAULT),
            'display_name' => 'Viewer One',
            'role' => 'viewer',
            'allowed_sections' => ['overview']
        ]
    ];
}

function ensure_user_store(): void {
    $dir = dirname(USERS_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    if (!file_exists(USERS_FILE)) {
        file_put_contents(
            USERS_FILE,
            json_encode(default_users(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }
}

function load_users(): array {
    ensure_user_store();

    $json = file_get_contents(USERS_FILE);
    $users = json_decode($json, true);

    if (!is_array($users)) {
        $users = default_users();
        save_users($users);
    }

    return $users;
}

function save_users(array $users): void {
    ensure_user_store();
    file_put_contents(
        USERS_FILE,
        json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

function current_user(): ?array {
    if (empty($_SESSION['user']['username'])) {
        return null;
    }

    $users = load_users();
    $username = $_SESSION['user']['username'];

    if (!isset($users[$username])) {
        return null;
    }

    $u = $users[$username];

    return [
        'username' => $username,
        'displayName' => $u['display_name'] ?? $username,
        'role' => $u['role'] ?? 'viewer',
        'allowed_sections' => $u['allowed_sections'] ?? []
    ];
}

function require_auth(): array {
    $user = current_user();

    if (!$user) {
        header("Location: /login.php");
        exit;
    }

    $_SESSION['user'] = $user;
    return $user;
}

function require_admin(): array {
    $user = require_auth();

    if (($user['role'] ?? '') !== 'super_admin') {
        http_response_code(403);
        echo "Access denied";
        exit;
    }

    return $user;
}

function check_access(string $section): bool {
    $user = require_auth();

    if (($user['role'] ?? '') === 'super_admin') {
        return true;
    }

    if (($user['role'] ?? '') === 'viewer') {
        return $section === 'overview';
    }

    return in_array($section, $user['allowed_sections'] ?? [], true);
}
