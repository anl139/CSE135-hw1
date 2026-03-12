<?php
session_start();
function require_auth() {
    if (!isset($_SESSION['user'])) {
        header("Location: /login.php");
        exit;}
    return $_SESSION['user'];}
function check_access($section) {
    $user = $_SESSION['user'];
    if ($user['role'] === 'super_admin') return true;
    if ($user['role'] === 'analyst') {
        return in_array($section, $user['allowed_sections']);}
    return false;}
