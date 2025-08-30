<?php
session_start();

// Protect this page
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Nutritionist') {
    header("Location: login.php");
    exit();
}

$conn = require_once 'config.php';
date_default_timezone_set('Asia/Dhaka');

$nutritionistID = $_SESSION['userID'];
$errorMsg = "";
$successMsg = "";
$schedule = null;

// Get Schedule ID from URL
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header("Location: nutritionist_schedule.php");
    exit();
}
$scheduleID = (int)$_GET['id'];

// Handle Form Submission to Update Schedule
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_schedule'])) {
    $newDate = $_POST['availableDate'];
    $newStartTime = $_POST['startTime'];
    $newEndTime = $_POST['endTime'];
    $newStatus = $_POST['status']; 
    
    $scheduleStartDateTime = $newDate . ' ' . $newStartTime;
    if (strtotime($scheduleStartDateTime) < time() && $_POST['original_status'] === 'Available') {
         $errorMsg = "You cannot move an available slot to a time in the past.";
    } elseif (strtotime($newEndTime) <= strtotime($newStartTime)) {
        $errorMsg = "End time must be after the start time.";
    } else {
        $checkSql = "SELECT scheduleID FROM schedule WHERE providerID = ? AND availableDate = ? AND (startTime < ? AND endTime > ?) AND scheduleID != ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("isssi", $nutritionistID, $newDate, $newEndTime, $newStartTime, $scheduleID);
        $checkStmt->execute();
        $result = $checkStmt->get_result();

        if ($result->num_rows > 0) {
            $errorMsg = "The updated schedule overlaps with another existing time slot.";
        } else {
            $updateSql = "UPDATE schedule SET availableDate = ?, startTime = ?, endTime = ?, status = ? WHERE scheduleID = ? AND providerID = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("ssssii", $newDate, $newStartTime, $newEndTime, $newStatus, $scheduleID, $nutritionistID);

            if ($updateStmt->execute()) {
                $successMsg = "Schedule updated successfully!";
            } else {
                $errorMsg = "Failed to update schedule. Please try again.";
            }
            $updateStmt->close();
        }
        $checkStmt->close();
    }
}

// Fetch the specific schedule details
$sql = "SELECT availableDate, startTime, endTime, status FROM schedule WHERE scheduleID = ? AND providerID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $scheduleID, $nutritionistID);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $schedule = $result->fetch_assoc();
    // After a successful update, refresh the schedule data to show the latest changes
    if ($successMsg) {
         $schedule['availableDate'] = $newDate;
         $schedule['startTime'] = $newStartTime;
         $schedule['endTime'] = $newEndTime;
         $schedule['status'] = $newStatus;
    }
} else {
    header("Location: nutritionist_schedule.php");
    exit();
}
$stmt->close(;
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Schedule - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>