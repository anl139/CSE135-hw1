<?php
session_start();
$state = $_SESSION['state'] ?? null;
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>View State — PHP</title></head>
<body>
<h1>View Server-side State</h1>
<?php if($state): ?>
  <ul>
    <li>Name: <?php echo htmlspecialchars($state['name']); ?></li>
    <li>Favorite color: <?php echo htmlspecialchars($state['color']); ?></li>
    <li>Saved at: <?php echo htmlspecialchars($state['saved_at']); ?></li>
  </ul>
  <p><a href="state-clear-php.php">Clear saved state</a></p>
<?php else: ?>
  <p>No state saved. <a href="state-set-php.php">Set some state</a>.</p>
<?php endif; ?>
</body>
</html>
