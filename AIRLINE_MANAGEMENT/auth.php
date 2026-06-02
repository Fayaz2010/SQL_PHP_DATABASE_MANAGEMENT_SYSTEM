<?php
session_start();

function requireRole($allowed_roles) {
    if (!isset($_SESSION['role'])) {
        header("Location: " . getLoginPath() . "?error=Please+log+in+first");
        exit;
    }
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        die("<p style='font-family:Arial;color:red;margin:30px'>
             Access denied. This page is not available for your account type.
             <br><a href='" . getLoginPath() . "'>Back to Login</a></p>");
    }
}

function getLoginPath() {
    $depth = substr_count($_SERVER['PHP_SELF'], "/") - 2;
    return str_repeat("../", $depth) . "login.php";
}

function isAdmin()     { return isset($_SESSION['role']) && $_SESSION['role'] === 'admin'; }
function isEmployee()  { return isset($_SESSION['role']) && $_SESSION['role'] === 'employee'; }
function isPassenger() { return isset($_SESSION['role']) && $_SESSION['role'] === 'passenger'; }

function currentUserId()   { return $_SESSION['user_id']   ?? null; }
function currentUserName() { return $_SESSION['user_name'] ?? 'User'; }
function currentRole()     { return $_SESSION['role']      ?? ''; }
?>
