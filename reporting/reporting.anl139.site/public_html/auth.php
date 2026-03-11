<?php
session_start();

// Call this at the top of every protected pagesadfsdfa
function require_auth() {
    if (!isset($_SESSION['user'])) {
        header("Location: /login.php");
        exit;
    }
}
