<?php
session_start();

// Protect this page: allow only logged-in Patients
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Patient') {
    header("Location: login.php");
    exit();
}

// --- Database Connection ---
$conn = require_once 'config.php';

$userName = $_SESSION['Name'];
$userID = $_SESSION['userID'];
$userAvatar = strtoupper(substr($userName, 0, 2));

// Get patient health data
$patientQuery = "SELECT age, height, weight, gender FROM Patient WHERE patientID = ?";
$patientStmt = $conn->prepare($patientQuery);
$patientStmt->bind_param("i", $userID);
$patientStmt->execute();
$patientResult = $patientStmt->get_result();
$patientData = $patientResult->fetch_assoc();

// Calculate BMI if height and weight are available
$bmi = null;
if ($patientData && $patientData['height'] > 0 && $patientData['weight'] > 0) {
    $heightInMeters = $patientData['height'] / 100; // Convert cm to meters
    $bmi = $patientData['weight'] / ($heightInMeters * $heightInMeters);
}

// Get dashboard stats
$appointmentCountQuery = "SELECT COUNT(*) as count FROM Appointment WHERE patientID = ? AND status != 'Canceled'";
$appointmentStmt = $conn->prepare($appointmentCountQuery);
$appointmentStmt->bind_param("i", $userID);
$appointmentStmt->execute();
$appointmentCount = $appointmentStmt->get_result()->fetch_assoc()['count'];

$upcomingAppointmentQuery = "SELECT COUNT(*) as count FROM Appointment WHERE patientID = ? AND appointmentDate >= NOW() AND status IN ('Scheduled', 'Requested')";
$upcomingStmt = $conn->prepare($upcomingAppointmentQuery);
$upcomingStmt->bind_param("i", $userID);
$upcomingStmt->execute();
$upcomingCount = $upcomingStmt->get_result()->fetch_assoc()['count'];

$caregiverCountQuery = "SELECT COUNT(*) as count FROM CareGiverBooking WHERE patientID = ? AND status = 'Active'";
$caregiverStmt = $conn->prepare($caregiverCountQuery);
$caregiverStmt->bind_param("i", $userID);
$caregiverStmt->execute();
$caregiverCount = $caregiverStmt->get_result()->fetch_assoc()['count'];

$historyCountQuery = "SELECT COUNT(*) as count FROM PatientHistory WHERE patientID = ?";
$historyStmt = $conn->prepare($historyCountQuery);
$historyStmt->bind_param("i", $userID);
$historyStmt->execute();
$historyCount = $historyStmt->get_result()->fetch_assoc()['count'];

// Get recent appointments
$recentAppointmentsQuery = "SELECT a.appointmentDate, a.status, u.Name as providerName, u.role as providerRole 
                            FROM Appointment a 
                            LEFT JOIN Users u ON a.providerID = u.userID 
                            WHERE a.patientID = ? 
                            ORDER BY a.appointmentDate DESC 
                            LIMIT 3";
$recentStmt = $conn->prepare($recentAppointmentsQuery);
$recentStmt->bind_param("i", $userID);
$recentStmt->execute();
$recentAppointments = $recentStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$conn->close();

// Helper function to get BMI category
function getBMICategory($bmi) {
    if ($bmi < 18.5) return ['category' => 'Underweight', 'color' => 'text-blue-600', 'bg' => 'bg-blue-100'];
    if ($bmi < 25) return ['category' => 'Normal', 'color' => 'text-green-600', 'bg' => 'bg-green-100'];
    if ($bmi < 30) return ['category' => 'Overweight', 'color' => 'text-yellow-600', 'bg' => 'bg-yellow-100'];
    return ['category' => 'Obese', 'color' => 'text-red-600', 'bg' => 'bg-red-100'];
}

function formatAppointmentDate($datetime) {
    $date = new DateTime($datetime);
    $now = new DateTime();
    $diff = $now->diff($date);
    
    if ($date > $now) {
        if ($diff->days == 0) return 'Today at ' . $date->format('g:i A');
        if ($diff->days == 1) return 'Tomorrow at ' . $date->format('g:i A');
        return $date->format('M j, Y \a\t g:i A');
    } else {
        if ($diff->days == 0) return 'Today at ' . $date->format('g:i A');
        if ($diff->days == 1) return 'Yesterday at ' . $date->format('g:i A');
        return $date->format('M j, Y \a\t g:i A');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-dark-orchid { background-color: #9932CC; }
        .text-dark-orchid { color: #9932CC; }
        .shadow-orchid-custom { box-shadow: 0 4px 6px -1px rgba(153, 50, 204, 0.1), 0 2px 4px -2px rgba(153, 50, 204, 0.1); }
        .dashboard-card {
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .dashboard-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px -5px rgba(153, 50, 204, 0.15);
        }
    </style>
</head>
<body class="bg-purple-50">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="w-64 bg-white border-r">
            <div class="p-6">
                <a href="#" class="text-2xl font-bold text-dark-orchid">CarePlus</a>
            </div>
            <nav class="px-4 space-y-2">
                <a href="patientDashboard.php" class="flex items-center space-x-3 px-4 py-3 bg-purple-100 text-dark-orchid rounded-lg">
                    <i class="fa-solid fa-table-columns w-5"></i>
                    <span>Dashboard</span>
                </a>
                <a href="patientProfile.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg">
                    <i class="fa-regular fa-user w-5"></i>
                    <span>My Profile</span>
                </a>
                <a href="patientAppointments.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg">
                    <i class="fa-solid fa-calendar-days w-5"></i>
                    <span>My Appointments</span>
                </a>
                <a href="#" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg">
                    <i class="fa-solid fa-hands-holding-child w-5"></i>
                    <span>My Caregiver Bookings</span>
                </a>
                <a href="#" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg">
                    <i class="fa-solid fa-file-medical w-5"></i>
                    <span>Medical History</span>
                </a>
                <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg mt-8">
                    <i class="fa-solid fa-arrow-right-from-bracket w-5"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <!-- Header -->
            <header class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-800">Welcome back, <?php echo htmlspecialchars($userName); ?>!</h1>
                    <p class="text-gray-600 mt-1">Here's an overview of your health journey</p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <p class="font-semibold text-slate-700"><?php echo htmlspecialchars($userName); ?></p>
                        <p class="text-sm text-gray-500">Patient</p>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-dark-orchid text-white flex items-center justify-center font-bold text-lg">
                        <?php echo htmlspecialchars($userAvatar); ?>
                    </div>
                </div>
            </header>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="dashboard-card bg-white p-6 rounded-lg shadow-orchid-custom">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm font-medium">Total Appointments</p>
                            <p class="text-2xl font-bold text-slate-800"><?php echo $appointmentCount; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fa-solid fa-calendar-days text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="dashboard-card bg-white p-6 rounded-lg shadow-orchid-custom">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm font-medium">Upcoming</p>
                            <p class="text-2xl font-bold text-slate-800"><?php echo $upcomingCount; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fa-solid fa-clock text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="dashboard-card bg-white p-6 rounded-lg shadow-orchid-custom">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm font-medium">Active Caregivers</p>
                            <p class="text-2xl font-bold text-slate-800"><?php echo $caregiverCount; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                            <i class="fa-solid fa-user-nurse text-purple-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="dashboard-card bg-white p-6 rounded-lg shadow-orchid-custom">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm font-medium">Medical Records</p>
                            <p class="text-2xl font-bold text-slate-800"><?php echo $historyCount; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <i class="fa-solid fa-file-medical text-yellow-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Health Overview -->
                <div class="lg:col-span-2 bg-white rounded-lg shadow-orchid-custom p-6">
                    <h2 class="text-xl font-bold text-slate-800 mb-6">Health Overview</h2>
                    
                    <?php if ($patientData): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Personal Info -->
                        <div class="space-y-4">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                    <i class="fa-solid fa-person text-blue-600"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Age</p>
                                    <p class="font-semibold text-slate-800"><?php echo $patientData['age'] ?? 'Not set'; ?> years</p>
                                </div>
                            </div>

                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                    <i class="fa-solid fa-venus-mars text-green-600"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Gender</p>
                                    <p class="font-semibold text-slate-800"><?php echo ucfirst($patientData['gender'] ?? 'Not set'); ?></p>
                                </div>
                            </div>

                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                                    <i class="fa-solid fa-ruler-vertical text-purple-600"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Height</p>
                                    <p class="font-semibold text-slate-800"><?php echo $patientData['height'] ? $patientData['height'] . ' cm' : 'Not set'; ?></p>
                                </div>
                            </div>

                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                                    <i class="fa-solid fa-weight-scale text-yellow-600"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Weight</p>
                                    <p class="font-semibold text-slate-800"><?php echo $patientData['weight'] ? $patientData['weight'] . ' kg' : 'Not set'; ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- BMI Section -->
                        <div class="flex flex-col justify-center">
                            <?php if ($bmi): 
                                $bmiInfo = getBMICategory($bmi);
                            ?>
                            <div class="text-center p-6 <?php echo $bmiInfo['bg']; ?> rounded-lg">
                                <h3 class="text-lg font-semibold text-slate-800 mb-2">Body Mass Index</h3>
                                <p class="text-3xl font-bold <?php echo $bmiInfo['color']; ?> mb-2">
                                    <?php echo number_format($bmi, 1); ?>
                                </p>
                                <p class="text-sm font-medium <?php echo $bmiInfo['color']; ?>">
                                    <?php echo $bmiInfo['category']; ?>
                                </p>
                            </div>
                            <?php else: ?>
                            <div class="text-center p-6 bg-gray-100 rounded-lg">
                                <h3 class="text-lg font-semibold text-slate-800 mb-2">BMI Calculator</h3>
                                <p class="text-gray-500 mb-4">Complete your height and weight to calculate BMI</p>
                                <a href="patientProfile.php" class="inline-flex items-center px-4 py-2 bg-dark-orchid text-white rounded-lg hover:bg-purple-700 transition">
                                    <i class="fa-solid fa-edit mr-2"></i>Update Profile
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-8">
                        <i class="fa-solid fa-user-plus fa-3x text-gray-400 mb-4"></i>
                        <h3 class="text-lg font-semibold text-slate-800 mb-2">Complete Your Profile</h3>
                        <p class="text-gray-500 mb-4">Add your health information to get personalized insights</p>
                        <a href="patientProfile.php" class="inline-flex items-center px-6 py-3 bg-dark-orchid text-white rounded-lg hover:bg-purple-700 transition">
                            <i class="fa-solid fa-user-edit mr-2"></i>Complete Profile
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Appointments -->
                <div class="bg-white rounded-lg shadow-orchid-custom p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-slate-800">Recent Appointments</h2>
                        <a href="patientAppointments.php" class="text-dark-orchid hover:text-purple-700 text-sm font-medium">
                            View All <i class="fa-solid fa-arrow-right ml-1"></i>
                        </a>
                    </div>

                    <?php if (empty($recentAppointments)): ?>
                    <div class="text-center py-8">
                        <i class="fa-solid fa-calendar-plus fa-2x text-gray-400 mb-3"></i>
                        <p class="text-gray-500 mb-4">No appointments yet</p>
                        <a href="patientAppointments.php" class="inline-flex items-center px-4 py-2 bg-dark-orchid text-white rounded-lg hover:bg-purple-700 transition">
                            <i class="fa-solid fa-plus mr-2"></i>Book Appointment
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($recentAppointments as $appointment): ?>
                        <div class="border-l-4 border-purple-500 pl-4 py-2">
                            <div class="flex items-center justify-between mb-1">
                                <h4 class="font-semibold text-slate-800">
                                    <?php echo htmlspecialchars($appointment['providerName'] ?? 'Provider TBD'); ?>
                                </h4>
                                <span class="text-xs px-2 py-1 rounded-full 
                                    <?php 
                                    switch(strtolower($appointment['status'])) {
                                        case 'scheduled': echo 'bg-blue-100 text-blue-800'; break;
                                        case 'requested': echo 'bg-yellow-100 text-yellow-800'; break;
                                        case 'completed': echo 'bg-green-100 text-green-800'; break;
                                        default: echo 'bg-gray-100 text-gray-800';
                                    }
                                    ?>">
                                    <?php echo ucfirst($appointment['status']); ?>
                                </span>
                            </div>
                            <p class="text-sm text-gray-600">
                                <i class="fa-solid fa-stethoscope mr-1"></i>
                                <?php echo ucfirst($appointment['providerRole'] ?? 'Provider'); ?>
                            </p>
                            <p class="text-sm text-gray-500">
                                <i class="fa-solid fa-clock mr-1"></i>
                                <?php echo formatAppointmentDate($appointment['appointmentDate']); ?>
                            </p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="mt-8">
                <h2 class="text-xl font-bold text-slate-800 mb-6">Quick Actions</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <a href="patientAppointments.php" class="dashboard-card bg-gradient-to-r from-blue-500 to-blue-600 text-white p-6 rounded-lg">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                                <i class="fa-solid fa-calendar-plus text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold">Book Appointment</h3>
                                <p class="text-sm opacity-90">Schedule with doctors & nutritionists</p>
                            </div>
                        </div>
                    </a>

                    <a href="patientProfile.php" class="dashboard-card bg-gradient-to-r from-green-500 to-green-600 text-white p-6 rounded-lg">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                                <i class="fa-solid fa-user-edit text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold">Update Profile</h3>
                                <p class="text-sm opacity-90">Manage your health information</p>
                            </div>
                        </div>
                    </a>

                    <a href="#" class="dashboard-card bg-gradient-to-r from-purple-500 to-purple-600 text-white p-6 rounded-lg">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                                <i class="fa-solid fa-file-medical text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold">Medical History</h3>
                                <p class="text-sm opacity-90">View your health records</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
