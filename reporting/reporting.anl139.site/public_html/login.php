<?php
session_start();
$users = [
    'superadmin' => [
        'password_hash' => password_hash('superpw', PASSWORD_DEFAULT),
        'display_name' => 'Super Admin',
        'role' => 'super_admin',
        'allowed_sections' => [] 
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
        'allowed_sections' => ['performance','behavioral'] 
    ],
    'viewer1' => [
        'password_hash' => password_hash('viewer123', PASSWORD_DEFAULT),
        'display_name' => 'Viewer One',
        'role' => 'viewer',
        'allowed_sections' => []
    ]
];

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (isset($users[$username]) && password_verify($password, $users[$username]['password_hash'])) {
        // Regenerate session ID for security
        session_regenerate_id(true);

        // Store user info in session
        $_SESSION['user'] = [
            'username'    => $username,
            'displayName' => $users[$username]['display_name'],
            'role'        => $users[$username]['role'],
            'allowed_sections' => $users[$username]['allowed_sections']
        ];

        // Redirect to reports page
        header("Location: /reports.php");
        exit;
    } else {
        $error = "Invalid username or password";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
</head>
<body>
    <h2>Login</h2>

    <?php if ($error): ?>
        <p style="color:red;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST">
        <label>Username</label><br>
        <input type="text" name="username" value="<?= htmlspecialchars($username ?? '') ?>" required><br><br>

        <label>Password</label><br>
        <input type="password" name="password" required><br><br>

        <button type="submit">Login</button>
    </form>
</body>
</html>
