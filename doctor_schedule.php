<?php
session_start();

// Protect this page: allow only logged-in Doctors
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

// Set a default timezone to ensure accurate date comparisons
date_default_timezone_set('Asia/Dhaka');

$doctorID = $_SESSION['userID'];
$userName = $_SESSION['Name'];
$userAvatar = strtoupper(substr($userName, 0, 2));
$errorMsg = "";
$successMsg = "";

// --- CORRECTED: Handle Delete Request ---
if (isset($_GET['delete_id'])) {
    $scheduleIdToDelete = $_GET['delete_id'];
    
    // Start a transaction to ensure data integrity
    $conn->begin_transaction();
    
    try {
        // 1. First, update any appointments linked to this schedule to set their scheduleID to NULL
        $updateAppointmentsStmt = $conn->prepare("UPDATE appointment SET scheduleID = NULL WHERE scheduleID = ? AND providerID = ?");
        $updateAppointmentsStmt->bind_param("ii", $scheduleIdToDelete, $doctorID);
        $updateAppointmentsStmt->execute();
        $updateAppointmentsStmt->close();

        // 2. Now, it's safe to delete the schedule
        $deleteStmt = $conn->prepare("DELETE FROM schedule WHERE scheduleID = ? AND providerID = ?");
        $deleteStmt->bind_param("ii", $scheduleIdToDelete, $doctorID);
        $deleteStmt->execute();
        $deleteStmt->close();

        // If both queries were successful, commit the transaction
        $conn->commit();
        
        header("Location: doctor_schedule.php?deleted=success");
        exit();

    } catch (mysqli_sql_exception $exception) {
        // If anything went wrong, roll back the transaction
        $conn->rollback();
        $errorMsg = "Failed to delete schedule. It may be in use.";
    }
}


// --- Handle Form Submission to Add Schedule ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_schedule'])) {
    $availableDate = $_POST['availableDate'];
    $startTime = $_POST['startTime'];
    $endTime = $_POST['endTime'];
    $status = "Available"; // Status is now always "Available" on creation

    $today = date('Y-m-d');
    if ($availableDate < $today) {
        $errorMsg = "You cannot add a schedule for a past date.";
    } elseif (strtotime($endTime) <= strtotime($startTime)) {
        $errorMsg = "End time must be after the start time.";
    } else {
        $sql = "INSERT INTO schedule (providerID, availableDate, startTime, endTime, status) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issss", $doctorID, $availableDate, $startTime, $endTime, $status);

        if ($stmt->execute()) {
            $successMsg = "New availability added successfully!";
        } else {
            $errorMsg = "Failed to add schedule. Please try again.";
        }
        $stmt->close();
    }
}

// --- Fetch All Schedules for the Doctor ---
$schedules = [];
$sql = "SELECT scheduleID, availableDate, startTime, endTime, status 
        FROM schedule 
        WHERE providerID = ? 
        ORDER BY availableDate DESC, startTime ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $doctorID);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $schedules[] = $row;
    }
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-dark-orchid { background-color: #9932CC; }
        .text-dark-orchid { color: #9932CC; }
        .hover\:bg-dark-orchid-darker:hover { background-color: #8A2BE2; }
        .shadow-orchid-custom { box-shadow: 0 4px 6px -1px rgba(153, 50, 204, 0.1), 0 2px 4px -2px rgba(153, 50, 204, 0.1); }
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
                <a href="doctorDashboard.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-table-columns w-5"></i><span>Dashboard</span></a>
                <a href="#" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-regular fa-user w-5"></i><span>My Profile</span></a>
                <a href="doctor_schedule.php" class="flex items-center space-x-3 px-4 py-3 bg-purple-100 text-dark-orchid rounded-lg"><i class="fa-solid fa-calendar-days w-5"></i><span>My Schedule</span></a>
                <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg mt-8"><i class="fa-solid fa-arrow-right-from-bracket w-5"></i><span>Logout</span></a>
            </nav>
        </aside>
        <!-- Main Content -->
        <main class="flex-1 p-8">
            <header class="flex justify-between items-center mb-8">
                <h1 class="text-3xl font-bold text-slate-800">My Schedule</h1>
                <div class="flex items-center space-x-2">
                    <div class="w-10 h-10 rounded-full bg-dark-orchid text-white flex items-center justify-center font-bold"><?php echo htmlspecialchars($userAvatar); ?></div>
                    <p class="font-semibold text-slate-700"><?php echo htmlspecialchars($userName); ?></p>
                </div>
            </header>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Add Availability Form -->
                <div class="lg:col-span-1 bg-white p-6 rounded-xl shadow-orchid-custom h-fit">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Add New Availability</h2>
                    <?php if ($successMsg): ?>
                        <div class="mb-4 p-3 bg-green-100 text-green-700 rounded-md"><?php echo $successMsg; ?></div>
                    <?php endif; ?>
                    <?php if ($errorMsg): ?>
                        <div class="mb-4 p-3 bg-red-100 text-red-700 rounded-md"><?php echo $errorMsg; ?></div>
                    <?php endif; ?>
                    <form action="doctor_schedule.php" method="POST" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Date</label>
                            <input type="date" name="availableDate" required class="w-full mt-1 p-2 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500" min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Start Time</label>
                                <input type="time" name="startTime" required class="w-full mt-1 p-2 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">End Time</label>
                                <input type="time" name="endTime" required class="w-full mt-1 p-2 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500">
                            </div>
                        </div>
                        <button type="submit" name="add_schedule" class="w-full py-2 px-4 bg-dark-orchid text-white rounded-md font-semibold hover:bg-dark-orchid-darker transition shadow-sm">
                            Add to Schedule
                        </button>
                    </form>
                </div>

                <!-- My Availability List -->
                <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-orchid-custom">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">My Availability</h2>
                    <div class="space-y-3">
                        <?php if (empty($schedules)): ?>
                            <p class="text-gray-500">You have not added any schedules.</p>
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
                                        <a href="edit_schedule.php?id=<?php echo $schedule['scheduleID']; ?>" class="text-blue-600 hover:underline text-sm">Edit</a>
                                        <a href="doctor_schedule.php?delete_id=<?php echo $schedule['scheduleID']; ?>" onclick="return confirm('Are you sure you want to delete this schedule?');" class="text-red-600 hover:underline text-sm">Delete</a>
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
