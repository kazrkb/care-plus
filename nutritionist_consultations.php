<?php
session_start();
$conn = require_once 'config.php';
date_default_timezone_set('Asia/Dhaka');

// Protect this page: allow only logged-in Nutritionists
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Nutritionist') {
    header("Location: login.php");
    exit();
}

$nutritionistID = $_SESSION['userID'];
$userName = $_SESSION['Name'];
$userAvatar = strtoupper(substr($userName, 0, 2));

// Fetch Scheduled Consultations for this Nutritionist
$query = "
    SELECT 
        a.appointmentID, a.patientID, u.Name as patientName,
        p.age as patientAge, a.appointmentDate, a.consultation_link
    FROM appointment a
    JOIN users u ON a.patientID = u.userID
    LEFT JOIN patient p ON u.userID = p.patientID
    WHERE a.providerID = ? AND a.status = 'Scheduled'
    ORDER BY a.appointmentDate ASC
";