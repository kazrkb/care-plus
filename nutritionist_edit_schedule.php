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
$stmt->close();
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
        body { font-family: 'Inter', sans-serif; }
        .bg-dark-orchid { background-color: #9932CC; }
        .text-dark-orchid { color: #9932CC; }
        input:read-only { background-color: #f3f4f6; cursor: not-allowed; }
    </style>
</head>
<body class="bg-purple-50">
    <div class="flex min-h-screen">
        <aside class="w-64 bg-white border-r">
            <div class="p-6">
                <a href="nutritionistDashboard.php" class="text-2xl font-bold text-dark-orchid">CarePlus</a>
            </div>
            <nav class="px-4">
                <a href="nutritionist_schedule.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg">
                    <i class="fa-solid fa-arrow-left w-5"></i><span>Back to Schedule</span>
                </a>
            </nav>
        </aside>
        <main class="flex-1 p-8">
            <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-lg mx-auto">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Edit Schedule</h2>

                <?php if ($successMsg): ?><div class="mb-4 p-3 bg-green-100 text-green-700 rounded-md"><?php echo htmlspecialchars($successMsg); ?></div><?php endif; ?>
                <?php if ($errorMsg): ?><div class="mb-4 p-3 bg-red-100 text-red-700 rounded-md"><?php echo htmlspecialchars($errorMsg); ?></div><?php endif; ?>

                <form action="nutritionist_edit_schedule.php?id=<?php echo $scheduleID; ?>" method="POST" class="space-y-4">
                    <input type="hidden" name="original_status" value="<?php echo htmlspecialchars($schedule['status']); ?>">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Date</label>
                        <input type="date" name="availableDate" value="<?php echo htmlspecialchars($schedule['availableDate']); ?>" 
                               <?php if ($schedule['status'] !== 'Available') echo 'readonly'; ?> 
                               required class="w-full mt-1 p-2 border border-gray-300 rounded-md shadow-sm">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Start Time</label>
                            <input type="time" name="startTime" value="<?php echo htmlspecialchars($schedule['startTime']); ?>" 
                                   <?php if ($schedule['status'] !== 'Available') echo 'readonly'; ?> 
                                   required class="w-full mt-1 p-2 border border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">End Time</label>
                            <input type="time" name="endTime" value="<?php echo htmlspecialchars($schedule['endTime']); ?>" 
                                   <?php if ($schedule['status'] !== 'Available') echo 'readonly'; ?> 
                                   required class="w-full mt-1 p-2 border border-gray-300 rounded-md shadow-sm">
                        </div>
                    </div>
                    
                    <?php if ($schedule['status'] === 'Available'): ?>
                        <input type="hidden" name="status" value="Available">
                    <?php else: ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Status</label>
                            <select name="status" required class="w-full mt-1 p-2 border border-gray-300 rounded-md shadow-sm">
                                <option value="Booked" <?php echo ($schedule['status'] === 'Booked') ? 'selected' : ''; ?>>Booked</option>
                                <option value="Canceled" <?php echo ($schedule['status'] === 'Canceled') ? 'selected' : ''; ?>>Canceled</option>
                                <option value="Rescheduled" <?php echo ($schedule['status'] === 'Rescheduled') ? 'selected' : ''; ?>>Rescheduled</option>
                            </select>
                        </div>
                    <?php endif; ?>
