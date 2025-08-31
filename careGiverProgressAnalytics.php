<?php
// careGiverProgressAnalytics.php
// ------------------------- SECURITY & SESSION -------------------------
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);

session_start();
$conn = require_once 'config.php';
date_default_timezone_set('Asia/Dhaka');
// 30-min session timeout
$timeout = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_unset();
    session_destroy();
    header("Location: login.php?error=Session expired. Please login again");
    exit();
}
$_SESSION['last_activity'] = time();
// Require caregiver
if (!isset($_SESSION['userID'], $_SESSION['role']) || $_SESSION['role'] !== 'CareGiver') {
    header("Location: login.php?error=Access denied. This page is for CareGivers only");
    exit();
}