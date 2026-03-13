<?php
require 'auth.php';

$users = load_users();
$error = null;
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (isset($users[$username]) && password_verify($password, $users[$username]['password_hash'])) {
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'username' => $username,
            'displayName' => $users[$username]['display_name'],
            'role' => $users[$username]['role'],
            'allowed_sections' => $users[$username]['allowed_sections'] ?? []
        ];

        header("Location: /reports.php");
        exit;
    } else {
        $error = "Invalid username or password";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="/styles/login.css">
</head>
<body>
    <div class="login-card">
        <h2>Login</h2>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST">
            <label>Username</label>
            <input type="text" name="username" value="<?= htmlspecialchars($username) ?>" required>

            <label>Password</label>
            <input type="password" name="password" required>

            <button type="submit">Login</button>
        </form>

        <p class="hint">Super admin: <strong>superadmin / superpw</strong></p>
    </div>
</body>
</html>
