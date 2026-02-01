<?php
session_start();
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $name = $_POST['name'] ?? '';
    $color = $_POST['color'] ?? '';
    $_SESSION['state'] = ['name'=>$name,'color'=>$color,'saved_at'=>date('c')];
    header('Location: state-view-php.php');
    exit;
}
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Set State — PHP</title></head>
<body>
<h1>Set Server-side State</h1>
<form method="post" action="state-set-php.php">
  <label>Name: <input name="name" type="text"></label><br>
  <label>Favorite color: <input name="color" type="text"></label><br>
  <button type="submit">Save to session</button>
</form>
<p><a href="state-view-php.php">View saved state</a></p>
</body>
</html>
