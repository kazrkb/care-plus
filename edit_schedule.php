<?php
session_start();

// Protect this page
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Doctor') {
    header("Location: login.php");
    exit();
}

// --- Database Connection ---
$host = "localhost";
$username = "root";
$password = "";
$database = "healthcare";

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$doctorID = $_SESSION['userID'];
$userName = $_SESSION['Name'];
$userAvatar = strtoupper(substr($userName, 0, 2));
$errorMsg = "";
$successMsg = "";
$schedule = null;

// --- Get Schedule ID from URL ---
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header("Location: doctor_schedule.php");
    exit();
}
$scheduleID = $_GET['id'];

// --- Handle Form Submission to Update ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_schedule'])) {
    $newDate = $_POST['availableDate'];
    $newStartTime = $_POST['startTime'];
    $newEndTime = $_POST['endTime'];
    $newStatus = $_POST['status'];
    
    $sql = "UPDATE schedule SET availableDate = ?, startTime = ?, endTime = ?, status = ? WHERE scheduleID = ? AND providerID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssii", $newDate, $newStartTime, $newEndTime, $newStatus, $scheduleID, $doctorID);

    if ($stmt->execute()) {
        $successMsg = "Schedule updated successfully!";
    } else {
        $errorMsg = "Failed to update schedule.";
    }
    $stmt->close();
}


// --- Fetch the specific schedule details to pre-fill the form ---
$sql = "SELECT availableDate, startTime, endTime, status FROM schedule WHERE scheduleID = ? AND providerID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $scheduleID, $doctorID);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $schedule = $result->fetch_assoc();
} else {
    // If schedule not found or doesn't belong to the doctor, redirect
    header("Location: doctor_schedule.php");
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
        .hover\:bg-dark-orchid-darker:hover { background-color: #8A2BE2; }
    </style>
</head>
<body class="bg-purple-50">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="w-64 bg-white border-r">
            <div class="p-6">
                <a href="#" class="text-2xl font-bold text-dark-orchid">CarePlus</a>
            </div>
            <nav class="px-4">
                <a href="doctor_schedule.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-arrow-left w-5"></i><span>Back to Schedule</span></a>
            </nav>
        </aside>
        <!-- Main Content -->
        <main class="flex-1 p-8">
             <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-lg mx-auto">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Edit Schedule</h2>

                <?php if ($successMsg): ?>
                    <div class="mb-4 p-3 bg-green-100 text-green-700 rounded-md"><?php echo $successMsg; ?></div>
                <?php endif; ?>
                <?php if ($errorMsg): ?>
                    <div class="mb-4 p-3 bg-red-100 text-red-700 rounded-md"><?php echo $errorMsg; ?></div>
                <?php endif; ?>

                <form action="edit_schedule.php?id=<?php echo $scheduleID; ?>" method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Date</label>
                        <input type="date" name="availableDate" value="<?php echo htmlspecialchars($schedule['availableDate']); ?>" required class="w-full mt-1 p-2 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500">
                    </div>
                     <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Start Time</label>
                            <input type="time" name="startTime" value="<?php echo htmlspecialchars($schedule['startTime']); ?>" required class="w-full mt-1 p-2 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">End Time</label>
                            <input type="time" name="endTime" value="<?php echo htmlspecialchars($schedule['endTime']); ?>" required class="w-full mt-1 p-2 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status</label>
                        <select name="status" required class="w-full mt-1 p-2 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500">
                            <option value="Available" <?php echo ($schedule['status'] === 'Available') ? 'selected' : ''; ?>>Available</option>
                            <option value="Canceled" <?php echo ($schedule['status'] === 'Canceled') ? 'selected' : ''; ?>>Canceled</option>
                            <option value="Rescheduled" <?php echo ($schedule['status'] === 'Rescheduled') ? 'selected' : ''; ?>>Rescheduled</option>
                        </select>
                    </div>
                    <button type="submit" name="update_schedule" class="w-full py-2 px-4 bg-dark-orchid text-white rounded-md font-semibold hover:bg-dark-orchid-darker transition shadow-sm">
                        Update Schedule
                    </button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
