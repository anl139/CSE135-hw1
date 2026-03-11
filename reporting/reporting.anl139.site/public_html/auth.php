<?php
session_start();

// Call this at the top of every protected page
function require_auth() {
    if (!isset($_SESSION['user'])) {
        header("Location: /login.php");
        exit;
    }
}
