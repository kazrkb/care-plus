<?php
session_start();

// Protect this page: allow only logged-in Nutritionists
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Nutritionist') {
    header("Location: login.php");
    exit();
}

$conn = require_once 'config.php';
date_default_timezone_set('Asia/Dhaka');

// --- Automatic cleanup of past schedules ---
$cleanupSql = "DELETE FROM schedule WHERE availableDate < CURDATE() OR (availableDate = CURDATE() AND endTime < CURTIME())";
$conn->query($cleanupSql);

$nutritionistID = $_SESSION['userID'];
$userName = $_SESSION['Name'];
$userAvatar = strtoupper(substr($userName, 0, 2));
$errorMsg = "";
$successMsg = "";

// --- Handle Delete Request ---
if (isset($_GET['delete_id'])) {
    $scheduleIdToDelete = (int)$_GET['delete_id'];
    
    $conn->begin_transaction();
    try {
        // Unlink any appointments before deleting
        $updateAppointmentsStmt = $conn->prepare("UPDATE appointment SET scheduleID = NULL WHERE scheduleID = ? AND providerID = ?");
        $updateAppointmentsStmt->bind_param("ii", $scheduleIdToDelete, $nutritionistID);
        $updateAppointmentsStmt->execute();
        $updateAppointmentsStmt->close();

        // Delete the schedule
        $deleteStmt = $conn->prepare("DELETE FROM schedule WHERE scheduleID = ? AND providerID = ?");
        $deleteStmt->bind_param("ii", $scheduleIdToDelete, $nutritionistID);
        $deleteStmt->execute();
        $deleteStmt->close();

        $conn->commit();
        $successMsg = "Schedule slot deleted successfully.";
    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        $errorMsg = "Failed to delete schedule. It may be in use by a patient.";
    }
}

// --- Handle Form Submission to Add Schedule with Overlap Validation ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_schedule'])) {
    $availableDate = $_POST['availableDate'];
    $startTime = $_POST['startTime'];
    $endTime = $_POST['endTime'];
    $status = "Available";

    if (strtotime($endTime) <= strtotime($startTime)) {
        $errorMsg = "End time must be after the start time.";
    } else {
        // Check for overlapping schedules
        $checkSql = "
            SELECT scheduleID FROM schedule 
            WHERE providerID = ? AND availableDate = ? 
              AND (startTime < ? AND endTime > ?)
        ";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("isss", $nutritionistID, $availableDate, $endTime, $startTime);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows > 0) {
            $errorMsg = "The new schedule overlaps with an existing time slot.";
        } else {
            // No overlap found, proceed to insert
            $sql = "INSERT INTO schedule (providerID, availableDate, startTime, endTime, status) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issss", $nutritionistID, $availableDate, $startTime, $endTime, $status);

            if ($stmt->execute()) {
                $successMsg = "New availability added successfully!";
            } else {
                $errorMsg = "Failed to add schedule. Please try again.";
            }
            $stmt->close();
        }
        $checkStmt->close();
    }
}

// --- Fetch All Schedules for the Nutritionist ---
$schedules = [];
$sql = "SELECT scheduleID, availableDate, startTime, endTime, status 
        FROM schedule 
        WHERE providerID = ? 
        ORDER BY availableDate ASC, startTime ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $nutritionistID);
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()) {
    $schedules[] = $row;
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
                <a href="#" class="text-2xl font-bold text-dark-orchid">CarePlus</a>
            </div>
            <nav class="px-4">
                <a href="nutritionistDashboard.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-table-columns w-5"></i><span>Dashboard</span></a>
                <a href="#" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-regular fa-user w-5"></i><span>My Profile</span></a>
                <a href="nutritionist_schedule.php" class="flex items-center space-x-3 px-4 py-3 bg-purple-100 text-dark-orchid rounded-lg"><i class="fa-solid fa-calendar-days w-5"></i><span>Provide Schedule</span></a>
                <a href="#" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-laptop-medical w-5"></i><span>Consultations</span></a>
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
                <div class="lg:col-span-1 bg-white p-6 rounded-xl shadow-lg h-fit">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Add New Availability</h2>
                    <?php if ($successMsg): ?>
                        <div class="mb-4 p-3 bg-green-100 text-green-700 rounded-md"><?php echo $successMsg; ?></div>
                    <?php endif; ?>
                    <?php if ($errorMsg): ?>
                        <div class="mb-4 p-3 bg-red-100 text-red-700 rounded-md"><?php echo $errorMsg; ?></div>
                    <?php endif; ?>
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
                        <button type="submit" name="add_schedule" class="w-full py-2 px-4 bg-dark-orchid text-white rounded-md font-semibold hover:bg-purple-700 transition">
                            Add to Schedule
                        </button>
                    </form>
                </div>

                <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-lg">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">My Availability</h2>
                    <div class="space-y-3">
                        <?php if (empty($schedules)): ?>
                            <p class="text-gray-500 text-center py-10">You have not added any available schedules.</p>
                        <?php else: ?>
                            <?php foreach ($schedules as $schedule): ?>
                                <div class="flex justify-between items-center p-4 bg-purple-50 rounded-lg">
                                    <div>
                                        <p class="font-semibold"><?php echo date("F j, Y", strtotime($schedule['availableDate'])); ?></p>
                                        <p class="text-sm text-slate-500"><?php echo date("g:i A", strtotime($schedule['startTime'])) . " - " . date("g:i A", strtotime($schedule['endTime'])); ?></p>
                                    </div>
                                    <div class="flex items-center space-x-4">
                                        <span class="px-2 py-1 text-xs rounded-full <?php echo $schedule['status'] === 'Available' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                            <?php echo htmlspecialchars($schedule['status']); ?>
                                        </span>
                                        <a href="nutritionist_schedule.php?delete_id=<?php echo $schedule['scheduleID']; ?>" onclick="return confirm('Are you sure you want to delete this schedule?');" class="text-red-600 hover:underline text-sm">Delete</a>
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