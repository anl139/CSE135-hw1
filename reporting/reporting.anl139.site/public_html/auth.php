<?php
session_start();

// Call this at the top of every protected pages
function require_auth() {
    if (!isset($_SESSION['user'])) {
        header("Location: /login.php");
        exit;
    }
    // Return the user info
    return $_SESSION['user'];
}

// Helper to check section access
function check_access($section) {
    $user = $_SESSION['user'];
    if ($user['role'] === 'super_admin') return true;
    if ($user['role'] === 'analyst') {
        return in_array($section, $user['allowed_sections']);
    }
    return false; // viewers cannot access dynamic sections
}
