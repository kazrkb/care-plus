<?php
// careGiverProgressAnalytics.php
// ------------------------- SECURITY & SESSION -------------------------
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);

session_start();
$conn = require_once 'config.php';
date_default_timezone_set('Asia/Dhaka');
