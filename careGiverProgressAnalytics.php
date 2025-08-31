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
// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$careGiverID = (int)$_SESSION['userID'];
$userName    = $_SESSION['Name'] ?? 'CareGiver';
// ------------------------- DUMMY DATA -------------------------
$patients = [
    ['patientID' => 1, 'patientName' => 'Alice'],
    ['patientID' => 2, 'patientName' => 'Bob'],
    ['patientID' => 3, 'patientName' => 'Charlie'],
];
$patientIds = array_column($patients, 'patientID');

$progressData = [
    ['patientID'=>1,'dataType'=>'blood_pressure','value'=>120,'measurementUnit'=>'mmHg','recordedDate'=>'2025-08-29 08:00','notes'=>'Normal','patientName'=>'Alice'],
    ['patientID'=>1,'dataType'=>'blood_sugar','value'=>5.5,'measurementUnit'=>'mmol/L','recordedDate'=>'2025-08-29 09:00','notes'=>'After breakfast','patientName'=>'Alice'],
    ['patientID'=>2,'dataType'=>'weight','value'=>70,'measurementUnit'=>'kg','recordedDate'=>'2025-08-30 10:00','notes'=>'','patientName'=>'Bob'],
    ['patientID'=>2,'dataType'=>'heart_rate','value'=>75,'measurementUnit'=>'bpm','recordedDate'=>'2025-08-30 11:00','notes'=>'Resting','patientName'=>'Bob'],
    ['patientID'=>3,'dataType'=>'temperature','value'=>36.6,'measurementUnit'=>'°C','recordedDate'=>'2025-08-31 12:00','notes'=>'Normal','patientName'=>'Charlie'],
    ['patientID'=>3,'dataType'=>'oxygen_level','value'=>98,'measurementUnit'=>'%','recordedDate'=>'2025-08-31 12:30','notes'=>'Good','patientName'=>'Charlie'],
];
