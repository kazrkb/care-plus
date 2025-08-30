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

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $nutritionistID);
$stmt->execute();
$consultations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// Group consultations by date
$today = [];
$tomorrow = [];
$now = new DateTime();
$tomorrowDate = (new DateTime())->modify('+1 day');

foreach ($consultations as $consult) {
    $appointmentDate = new DateTime($consult['appointmentDate']);
    if ($appointmentDate->format('Y-m-d') === $now->format('Y-m-d')) {
        $today[] = $consult;
    } elseif ($appointmentDate->format('Y-m-d') === $tomorrowDate->format('Y-m-d')) {
        $tomorrow[] = $consult;
    } elseif ($appointmentDate > $now) {
        $upcoming[] = $consult;
    }
}