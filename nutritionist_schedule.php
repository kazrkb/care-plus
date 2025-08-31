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

// Mark past 'Booked' schedules as 'Completed'.
$stmt = $conn->prepare("UPDATE schedule SET status = 'Completed' WHERE (availableDate < ? OR (availableDate = ? AND endTime < ?)) AND status = 'Booked' AND providerID = ?");
$stmt->bind_param("sssi", $todayDate, $todayDate, $nowTime, $nutritionistID);
$stmt->execute();
$stmt->close();

// Permanently DELETE any past schedules that were 'Available' (never booked).
$stmt = $conn->prepare("DELETE FROM schedule WHERE (availableDate < ? OR (availableDate = ? AND endTime < ?)) AND status = 'Available' AND providerID = ?");
$stmt->bind_param("sssi", $todayDate, $todayDate, $nowTime, $nutritionistID);
$stmt->execute();
$stmt->close();


// --- Handle Clear History Request ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['clear_history'])) {
    $stmt = $conn->prepare("DELETE FROM schedule WHERE providerID = ? AND (availableDate < ? OR (availableDate = ? AND endTime < ?))");
    $stmt->bind_param("isss", $nutritionistID, $todayDate, $todayDate, $nowTime);
    if ($stmt->execute()) {
        $successMsg = "Schedule history has been cleared.";
    } else {
        $errorMsg = "Could not clear history.";
    }
    $stmt->close();
}

// --- Handle Delete Request for a single available item ---
if (isset($_GET['delete_id'])) {
    $scheduleIdToDelete = (int)$_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM schedule WHERE scheduleID = ? AND providerID = ? AND status = 'Available'");
    $stmt->bind_param("ii", $scheduleIdToDelete, $nutritionistID);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $successMsg = "Availability slot deleted successfully.";
    } else {
        $errorMsg = "Failed to delete slot. It may be booked or already deleted.";
    }
    $stmt->close();
}

// --- Handle Form Submission to Add Schedule ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_schedule'])) {
    $availableDate = $_POST['availableDate'];
    $startTime = $_POST['startTime'];
    $endTime = $_POST['endTime'];
    $status = "Available";
    
    $scheduleStartDateTime = $availableDate . ' ' . $startTime;
    if (strtotime($scheduleStartDateTime) < time()) {
        $errorMsg = "You cannot add a schedule for a date or time that has already passed.";
    } elseif (strtotime($endTime) <= strtotime($startTime)) {
        $errorMsg = "End time must be after the start time.";
    } else {
        $stmt = $conn->prepare("SELECT scheduleID FROM schedule WHERE providerID = ? AND availableDate = ? AND (startTime < ? AND endTime > ?)");
        $stmt->bind_param("isss", $nutritionistID, $availableDate, $endTime, $startTime);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errorMsg = "The new schedule overlaps with an existing time slot.";
        } else {
            $stmt = $conn->prepare("INSERT INTO schedule (providerID, availableDate, startTime, endTime, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $nutritionistID, $availableDate, $startTime, $endTime, $status);
            if ($stmt->execute()) {
                $successMsg = "New availability added successfully!";
            } else {
                $errorMsg = "Failed to add schedule. Please try again.";
            }
        }
        $stmt->close();
    }
}

// --- Fetch Upcoming Availability (status = 'Available') ---
$availability = [];
$stmt = $conn->prepare("SELECT scheduleID, availableDate, startTime, endTime, status FROM schedule WHERE providerID = ? AND status = 'Available' AND (availableDate > ? OR (availableDate = ? AND endTime >= ?)) ORDER BY availableDate ASC, startTime ASC");
$stmt->bind_param("isss", $nutritionistID, $todayDate, $todayDate, $nowTime);
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()) {
    $availability[] = $row;
}
$stmt->close();

// --- Fetch Upcoming Bookings (Booked, Canceled, etc.) ---
$bookings = [];
$stmt = $conn->prepare("SELECT scheduleID, availableDate, startTime, endTime, status FROM schedule WHERE providerID = ? AND status IN ('Booked', 'Canceled', 'Rescheduled') AND (availableDate > ? OR (availableDate = ? AND endTime >= ?)) ORDER BY availableDate ASC, startTime ASC");
$stmt->bind_param("isss", $nutritionistID, $todayDate, $todayDate, $nowTime);
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}
$stmt->close();

// --- Fetch Historical Schedules ---
$historySchedules = [];
$stmt = $conn->prepare("SELECT scheduleID, availableDate, startTime, endTime, status FROM schedule WHERE providerID = ? AND (availableDate < ? OR (availableDate = ? AND endTime < ?)) ORDER BY availableDate DESC, startTime DESC");
$stmt->bind_param("isss", $nutritionistID, $todayDate, $todayDate, $nowTime);
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()) {
    $historySchedules[] = $row;
}
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Schedule - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-dark-orchid { background-color: #9932CC; }
        .text-dark-orchid { color: #9932CC; }
    </style>