<?php
session_start();
unset($_SESSION['state']);
header('Location: state-view-php.php');
exit;
