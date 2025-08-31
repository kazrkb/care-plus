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
</head>
<body class="bg-purple-50">
    <div class="flex min-h-screen">
        <aside class="w-64 bg-white border-r">
            <div class="p-6">
                <a href="nutritionistDashboard.php" class="text-2xl font-bold text-dark-orchid">CarePlus</a>
            </div>
            <nav class="px-4">
                <a href="nutritionistDashboard.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-table-columns w-5"></i><span>Dashboard</span></a>
                <a href="nutritionist_profile.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-regular fa-user w-5"></i><span>My Profile</span></a>
                <a href="nutritionist_schedule.php" class="flex items-center space-x-3 px-4 py-3 bg-purple-100 text-dark-orchid rounded-lg"><i class="fa-solid fa-calendar-days w-5"></i><span>Provide Schedule</span></a>
                <a href="nutritionist_consultations.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-laptop-medical w-5"></i><span>Consultations</span></a>
                <a href="#" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-utensils w-5"></i><span>Diet Plan</span></a>
                <a href="#" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-file-waveform w-5"></i><span>Patient History</span></a>
                <a href="#" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-money-bill-wave w-5"></i><span>Transactions</span></a>
                <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg mt-8"><i class="fa-solid fa-arrow-right-from-bracket w-5"></i><span>Logout</span></a>
            </nav>
        </aside>
        <main class="flex-1 p-8">
            <header class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-800">My Schedule</h1>
                    <p class="text-gray-600 mt-1">Manage your available time slots for patient appointments.</p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <p class="font-semibold text-slate-700"><?php echo htmlspecialchars($userName); ?></p>
                        <p class="text-sm text-gray-500">Nutritionist</p>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-dark-orchid text-white flex items-center justify-center font-bold text-lg"><?php echo htmlspecialchars($userAvatar); ?></div>
                </div>
            </header>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-1 space-y-8">
                    <div class="bg-white p-6 rounded-xl shadow-lg h-fit">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Add New Availability</h2>
                        <?php if ($successMsg): ?><div class="mb-4 p-3 bg-green-100 text-green-700 rounded-md"><?php echo $successMsg; ?></div><?php endif; ?>
                        <?php if ($errorMsg): ?><div class="mb-4 p-3 bg-red-100 text-red-700 rounded-md"><?php echo $errorMsg; ?></div><?php endif; ?>
                        <form action="nutritionist_schedule.php" method="POST" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Date</label>
                                <input type="date" name="availableDate" required class="w-full mt-1 p-2 border rounded-md" min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Start Time</label>
                                    <input type="time" name="startTime" required class="w-full mt-1 p-2 border rounded-md">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">End Time</label>
                                    <input type="time" name="endTime" required class="w-full mt-1 p-2 border rounded-md">
                                </div>
                            </div>
                            <button type="submit" name="add_schedule" class="w-full py-2 px-4 bg-dark-orchid text-white rounded-md font-semibold hover:bg-purple-700 transition">Add to Schedule</button>
                        </form>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow-lg h-fit">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-bold text-gray-800">Schedule History</h2>
                            <?php if (!empty($historySchedules)): ?>
                            <form action="nutritionist_schedule.php" method="POST">
                                <button type="submit" name="clear_history" onclick="return confirm('Are you sure you want to clear all schedule history? This cannot be undone.');" class="px-3 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded-md hover:bg-red-200">Clear All</button>
                            </form>
                            <?php endif; ?>
                        </div>
                        <div class="space-y-3 max-h-96 overflow-y-auto">
                             <?php if (empty($historySchedules)): ?>
                                <p class="text-gray-500 text-center py-10">No completed or expired schedules found.</p>
                            <?php else: ?>
                                <?php foreach ($historySchedules as $history): ?>
                                    <div class="flex justify-between items-center p-3 bg-gray-100 rounded-lg opacity-80">
                                        <div>
                                            <p class="font-semibold text-gray-600"><?php echo date("F j, Y", strtotime($history['availableDate'])); ?></p>
                                            <p class="text-sm text-gray-500"><?php echo date("g:i A", strtotime($history['startTime'])) . " - " . date("g:i A", strtotime($history['endTime'])); ?></p>
                                        </div>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-200 text-gray-600"><?php echo htmlspecialchars($history['status']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-lg">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">My Availability</h2>
                    <div class="space-y-3">
                        <?php if (empty($availability)): ?>
                            <p class="text-gray-500 text-center py-6">You have no empty slots available.</p>
                        <?php else: ?>
                            <?php foreach ($availability as $slot): ?>
                            <div class="flex justify-between items-center p-4 bg-green-50 rounded-lg">
                                <div>
                                    <p class="font-semibold text-green-800"><?php echo date("F j, Y", strtotime($slot['availableDate'])); ?></p>
                                    <p class="text-sm text-slate-600"><?php echo date("g:i A", strtotime($slot['startTime'])) . " - " . date("g:i A", strtotime($slot['endTime'])); ?></p>
                                </div>
                                <div class="flex items-center space-x-4">
                                    <a href="nutritionist_edit_schedule.php?id=<?php echo $slot['scheduleID']; ?>" class="font-medium text-blue-600 hover:text-blue-800 text-sm">Edit</a>
                                    <a href="nutritionist_schedule.php?delete_id=<?php echo $slot['scheduleID']; ?>" onclick="return confirm('Are you sure you want to delete this empty slot?');" class="font-medium text-red-600 hover:text-red-800 text-sm">Delete</a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <hr class="my-8">
                    
                    <h2 class="text-xl font-bold text-gray-800 mb-4">My Bookings</h2>
                    <div class="space-y-3">
                        <?php if (empty($bookings)): ?>
                            <p class="text-gray-500 text-center py-6">You have no upcoming bookings.</p>
                        <?php else: ?>
                            <?php foreach ($bookings as $booking): ?>
                            <div class="flex justify-between items-center p-4 bg-purple-50 rounded-lg">
                                <div>
                                    <p class="font-semibold"><?php echo date("F j, Y", strtotime($booking['availableDate'])); ?></p>
                                    <p class="text-sm text-slate-500"><?php echo date("g:i A", strtotime($booking['startTime'])) . " - " . date("g:i A", strtotime($booking['endTime'])); ?></p>
                                </div>
                                <div class="flex items-center space-x-4">
                                     <?php
                                        $statusClass = 'bg-blue-100 text-blue-800'; // Booked
                                        if (in_array($booking['status'], ['Canceled', 'Rescheduled'])) {
                                            $statusClass = 'bg-yellow-100 text-yellow-800';
                                        }
                                    ?>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars($booking['status']); ?>
                                    </span>
                                    <a href="nutritionist_edit_schedule.php?id=<?php echo $booking['scheduleID']; ?>" class="font-medium text-blue-600 hover:text-blue-800 text-sm">Edit</a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>