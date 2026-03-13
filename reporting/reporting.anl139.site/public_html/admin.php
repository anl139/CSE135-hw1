<?php
require 'auth.php';
$currentUser = require_admin();

$allSections = ['overview', 'performance', 'behavioral'];
$message = '';
$error = '';

$users = load_users();

function normalize_sections(string $role, array $sections, array $allSections): array {
    if ($role === 'super_admin') {
        return $allSections;
    }

    if ($role === 'viewer') {
        return ['overview'];
    }

    return array_values(array_intersect($sections, $allSections));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $users = load_users();

    if ($action === 'add') {
        $username = trim($_POST['username'] ?? '');
        $displayName = trim($_POST['display_name'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'viewer';
        $sections = $_POST['sections'] ?? [];

        if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
            $error = "Username must be 3–30 characters and use letters, numbers, or underscore.";
        } elseif (isset($users[$username])) {
            $error = "That username already exists.";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters.";
        } elseif (!in_array($role, ['viewer', 'analyst'], true)) {
            $error = "Invalid role.";
        } else {
            $users[$username] = [
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'display_name' => $displayName !== '' ? $displayName : $username,
                'role' => $role,
                'allowed_sections' => normalize_sections($role, (array)$sections, $allSections)
            ];

            save_users($users);
            $message = "User added.";
        }
    }

    if ($action === 'update') {
        $username = trim($_POST['username'] ?? '');

        if (!isset($users[$username])) {
            $error = "User not found.";
        } else {
            $displayName = trim($_POST['display_name'] ?? '');
            $role = $_POST['role'] ?? 'viewer';
            $sections = $_POST['sections'] ?? [];
            $newPassword = $_POST['password'] ?? '';

            if ($username === 'superadmin') {
                $role = 'super_admin';
            } elseif (!in_array($role, ['viewer', 'analyst'], true)) {
                $error = "Invalid role.";
            }

            if (!$error) {
                $users[$username]['display_name'] = $displayName !== '' ? $displayName : $username;
                $users[$username]['role'] = $role;
                $users[$username]['allowed_sections'] = normalize_sections($role, (array)$sections, $allSections);

                if ($newPassword !== '') {
                    if (strlen($newPassword) < 6) {
                        $error = "New password must be at least 6 characters.";
                    } else {
                        $users[$username]['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
                    }
                }

                if (!$error) {
                    save_users($users);
                    $message = "User updated.";
                }
            }
        }
    }

    if ($action === 'delete') {
        $username = trim($_POST['username'] ?? '');

        if ($username === $currentUser['username']) {
            $error = "You cannot delete your own account.";
        } elseif ($username === 'superadmin') {
            $error = "The super admin account cannot be deleted.";
        } elseif (!isset($users[$username])) {
            $error = "User not found.";
        } else {
            unset($users[$username]);
            save_users($users);
            $message = "User deleted.";
        }
    }

    $users = load_users();
}

$users = load_users();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Admin</title>
<link rel="stylesheet" href="/styles/dashboard.css">
</head>
<body>
<div class="dashboard-layout">
    <header class="dashboard-header">
        <h1>User Administration</h1>
        <span>User: <?= htmlspecialchars($currentUser['displayName']) ?> (super_admin)</span>
        <a class="top-link" href="/reports.php">Back to Reports</a>
        <button onclick="location.href='/logout.php'">Logout</button>
    </header>

    <main class="main-content full-width">
        <div class="comments">Add, update, or delete users, and control which sections they can access.</div>

        <?php if ($message): ?>
            <div class="success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error-box"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="admin-grid">
            <section class="panel">
                <h2>Add User</h2>
                <form method="POST" class="stack-form">
                    <input type="hidden" name="action" value="add">

                    <label>Username</label>
                    <input type="text" name="username" required>

                    <label>Display Name</label>
                    <input type="text" name="display_name" required>

                    <label>Password</label>
                    <input type="password" name="password" required>

                    <label>Role</label>
                    <select name="role">
                        <option value="viewer">viewer</option>
                        <option value="analyst">analyst</option>
                    </select>

                    <label>Allowed Sections</label>
                    <div class="checkbox-row">
                        <label><input type="checkbox" name="sections[]" value="overview"> Overview</label>
                        <label><input type="checkbox" name="sections[]" value="performance"> Performance</label>
                        <label><input type="checkbox" name="sections[]" value="behavioral"> Behavioral</label>
                    </div>

                    <button type="submit">Add User</button>
                </form>
            </section>

            <section class="panel">
                <h2>Existing Users</h2>

                <div class="user-list">
                    <?php foreach ($users as $username => $u): ?>
                        <form method="POST" class="user-card">
                            <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>">

                            <div class="user-card-head">
                                <strong><?= htmlspecialchars($username) ?></strong>
                                <span><?= htmlspecialchars($u['role'] ?? 'viewer') ?></span>
                            </div>

                            <input type="hidden" name="action" value="update">

                            <label>Display Name</label>
                            <input type="text" name="display_name" value="<?= htmlspecialchars($u['display_name'] ?? $username) ?>">

                            <label>Role</label>
                            <select name="role" <?= $username === 'superadmin' ? 'disabled' : '' ?>>
                                <option value="viewer" <?= (($u['role'] ?? '') === 'viewer') ? 'selected' : '' ?>>viewer</option>
                                <option value="analyst" <?= (($u['role'] ?? '') === 'analyst') ? 'selected' : '' ?>>analyst</option>
                                <option value="super_admin" <?= (($u['role'] ?? '') === 'super_admin') ? 'selected' : '' ?> <?= $username !== 'superadmin' ? 'disabled' : '' ?>>super_admin</option>
                            </select>

                            <label>Allowed Sections</label>
                            <div class="checkbox-row">
                                <label><input type="checkbox" name="sections[]" value="overview" <?= in_array('overview', $u['allowed_sections'] ?? [], true) ? 'checked' : '' ?>> Overview</label>
                                <label><input type="checkbox" name="sections[]" value="performance" <?= in_array('performance', $u['allowed_sections'] ?? [], true) ? 'checked' : '' ?>> Performance</label>
                                <label><input type="checkbox" name="sections[]" value="behavioral" <?= in_array('behavioral', $u['allowed_sections'] ?? [], true) ? 'checked' : '' ?>> Behavioral</label>
                            </div>

                            <label>New Password (optional)</label>
                            <input type="password" name="password" placeholder="Leave blank to keep current">

                            <div class="row-actions">
                                <button type="submit">Save</button>
                            </div>
                        </form>

                        <?php if ($username !== 'superadmin'): ?>
                            <form method="POST" onsubmit="return confirm('Delete user <?= htmlspecialchars($username) ?>?')" class="delete-form">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>">
                                <button type="submit" class="danger">Delete</button>
                            </form>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </main>
</div>
</body>
</html>
