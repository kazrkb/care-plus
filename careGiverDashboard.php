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