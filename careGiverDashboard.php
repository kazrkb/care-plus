<?php
session_start();
$conn = require_once 'config.php';
date_default_timezone_set('Asia/Dhaka');

// Protect this page: allow only logged-in CareGivers
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'CareGiver') {
    header("Location: login.php");
    exit();
}

$careGiverID = $_SESSION['userID'];
$userName = $_SESSION['Name'];
$userAvatar = strtoupper(substr($userName, 0, 2));
// --- Handle Dismissing a Notification ---
if (isset($_GET['dismiss_notification_id'])) {
    $notificationID = (int)$_GET['dismiss_notification_id'];
    $stmt = $conn->prepare("UPDATE notification SET status = 'Read' WHERE notificationID = ? AND userID = ?");
    $stmt->bind_param("ii", $notificationID, $careGiverID);
    $stmt->execute();
    $stmt->close();
    header("Location: careGiverDashboard.php");
    exit();
}
// --- Fetch all 'Unread' notifications ---
$notifyQuery = "SELECT * FROM notification WHERE userID = ? AND status = 'Unread' ORDER BY notificationID DESC";
$stmt = $conn->prepare($notifyQuery);
$stmt->bind_param("i", $careGiverID);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
// --- Fetch Progress Data (replace with your actual query) ---
$progressData = [
    ['patientName'=>'Alice','dataType'=>'blood_pressure','value'=>120,'recordedDate'=>'2025-08-29 08:00'],
    ['patientName'=>'Alice','dataType'=>'blood_sugar','value'=>5.5,'recordedDate'=>'2025-08-29 09:00'],
    ['patientName'=>'Bob','dataType'=>'weight','value'=>70,'recordedDate'=>'2025-08-30 10:00'],
    ['patientName'=>'Bob','dataType'=>'heart_rate','value'=>75,'recordedDate'=>'2025-08-30 11:00'],
    ['patientName'=>'Charlie','dataType'=>'temperature','value'=>36.6,'recordedDate'=>'2025-08-31 12:00'],
    ['patientName'=>'Charlie','dataType'=>'oxygen_level','value'=>98,'recordedDate'=>'2025-08-31 12:30'],
];
$dates = array_unique(array_map(fn($r)=>date('Y-m-d', strtotime($r['recordedDate'])),$progressData));
sort($dates);

$lineDatasets = [];
$dataTypes = array_unique(array_map(fn($r)=>$r['dataType'],$progressData));
$colors = ['#6D28D9','#2563EB','#059669','#F59E0B','#EF4444','#0EA5E9','#22C55E','#A855F7'];
foreach($dataTypes as $i=>$type){
    $series = [];
    foreach($dates as $d){
        $vals = array_filter($progressData, fn($r)=>$r['dataType']==$type && date('Y-m-d', strtotime($r['recordedDate']))==$d);
        $series[] = $vals ? array_sum(array_column($vals,'value'))/count($vals) : null;
    }
    $lineDatasets[] = [
        'label'=>ucwords(str_replace('_',' ',$type)),
        'data'=>$series,
        'borderColor'=>$colors[$i%count($colors)],
        'tension'=>0.3
    ];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CareGiver Dashboard - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-dark-orchid { background-color: #9932CC; }
        .text-dark-orchid { color: #9932CC; }
    </style>
</head>