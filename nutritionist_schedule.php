<?php
session_start();

// Protect this page: allow only logged-in Nutritionists
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Nutritionist') {
    header("Location: login.php");
    exit();
}

$conn = require_once 'config.php';
date_default_timezone_set('Asia/Dhaka');

$nutritionistID = $_SESSION['userID'];
$userName = $_SESSION['Name'];
$userAvatar = strtoupper(substr($userName, 0, 2));
$errorMsg = "";
$successMsg = "";

// --- Smart Schedule Cleanup Logic ---
$nowTime = date('H:i:s');
$todayDate = date('Y-m-d');
