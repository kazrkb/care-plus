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

// Get patient basic info
$patientQuery = "SELECT age, height, weight, gender FROM Patient WHERE patientID = ?";
$patientStmt = $conn->prepare($patientQuery);
$patientStmt->bind_param("i", $userID);
$patientStmt->execute();
$patientResult = $patientStmt->get_result();
$patientInfo = $patientResult->fetch_assoc();

// Get upcoming appointments count
$upcomingAppointmentsQuery = "SELECT COUNT(*) as count FROM Appointment WHERE patientID = ? AND appointmentDate >= CURDATE() AND status != 'Canceled'";
$upcomingStmt = $conn->prepare($upcomingAppointmentsQuery);
$upcomingStmt->bind_param("i", $userID);
$upcomingStmt->execute();
$upcomingResult = $upcomingStmt->get_result();
$upcomingCount = $upcomingResult->fetch_assoc()['count'];

// Get active caregiver bookings count
$activeCaregiverQuery = "SELECT COUNT(*) as count FROM CaregiverBooking WHERE patientID = ? AND status = 'Active'";
$caregiverStmt = $conn->prepare($activeCaregiverQuery);
$caregiverStmt->bind_param("i", $userID);
$caregiverStmt->execute();
$caregiverResult = $caregiverStmt->get_result();
$activeCaregiverCount = $caregiverResult->fetch_assoc()['count'];

// Get recent medical history count
$medicalHistoryQuery = "SELECT COUNT(*) as count FROM PatientHistory WHERE patientID = ?";
$historyStmt = $conn->prepare($medicalHistoryQuery);
$historyStmt->bind_param("i", $userID);
$historyStmt->execute();
$historyResult = $historyStmt->get_result();
$medicalHistoryCount = $historyResult->fetch_assoc()['count'];

$conn->close();
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
        .feature-card {
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(153, 50, 204, 0.15), 0 4px 6px -2px rgba(153, 50, 204, 0.1);
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .stat-card-alt {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .stat-card-alt2 {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .stat-card-alt3 {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
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
                <a href="caregiverBooking.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg">
                    <i class="fa-solid fa-hands-holding-child w-5"></i>
                    <span>Caregiver Bookings</span>
                </a>
                <a href="patientMedicalHistory.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg">
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
                    <p class="text-gray-600 mt-1">Here's what's happening with your health today</p>
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
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <div class="stat-card text-white p-6 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100">My Appointments</p>
                            <p class="text-3xl font-bold"><?php echo $upcomingCount; ?></p>
                        </div>
                        <i class="fa-solid fa-calendar-check fa-2x text-blue-200"></i>
                    </div>
                </div>
                <div class="stat-card-alt text-white p-6 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-pink-100">Caregiver Bookings</p>
                            <p class="text-3xl font-bold"><?php echo $activeCaregiverCount; ?></p>
                        </div>
                        <i class="fa-solid fa-user-nurse fa-2x text-pink-200"></i>
                    </div>
                </div>
                <div class="stat-card-alt2 text-white p-6 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-cyan-100">Medical Records</p>
                            <p class="text-3xl font-bold"><?php echo $medicalHistoryCount; ?></p>
                        </div>
                        <i class="fa-solid fa-file-medical fa-2x text-cyan-200"></i>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="mb-8">
                <h2 class="text-2xl font-bold text-slate-800 mb-6">Quick Actions</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <a href="requestAppointment.php" class="feature-card bg-white p-6 rounded-lg shadow-orchid-custom text-center hover:shadow-lg">
                        <i class="fa-solid fa-calendar-plus fa-2x text-dark-orchid mb-4"></i>
                        <h3 class="text-lg font-semibold text-slate-800">Request Appointment</h3>
                        <p class="text-gray-600 text-sm mt-2">Book new appointment with providers</p>
                    </a>
                    <a href="patientAppointments.php" class="feature-card bg-white p-6 rounded-lg shadow-orchid-custom text-center hover:shadow-lg">
                        <i class="fa-solid fa-calendar-days fa-2x text-dark-orchid mb-4"></i>
                        <h3 class="text-lg font-semibold text-slate-800">View Appointments</h3>
                        <p class="text-gray-600 text-sm mt-2">Check your scheduled appointments</p>
                    </a>
                    <a href="caregiverBooking.php" class="feature-card bg-white p-6 rounded-lg shadow-orchid-custom text-center hover:shadow-lg">
                        <i class="fa-solid fa-hands-holding-child fa-2x text-dark-orchid mb-4"></i>
                        <h3 class="text-lg font-semibold text-slate-800">Caregiver Bookings</h3>
                        <p class="text-gray-600 text-sm mt-2">Manage your caregiver services</p>
                    </a>
                    <a href="patientMedicalHistory.php" class="feature-card bg-white p-6 rounded-lg shadow-orchid-custom text-center hover:shadow-lg">
                        <i class="fa-solid fa-file-medical fa-2x text-dark-orchid mb-4"></i>
                        <h3 class="text-lg font-semibold text-slate-800">Medical History</h3>
                        <p class="text-gray-600 text-sm mt-2">Access your medical records</p>
                    </a>
                </div>
            </div>

            <!-- Recent Activity & Health Overview -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Health Overview -->
                <div class="bg-white p-6 rounded-lg shadow-orchid-custom">
                    <h3 class="text-xl font-bold text-slate-800 mb-4">Health Overview</h3>
                    <?php if ($patientInfo): ?>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                            <span class="font-medium text-gray-700">Age</span>
                            <span class="text-slate-800"><?php echo $patientInfo['age'] ?? 'Not set'; ?> years</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                            <span class="font-medium text-gray-700">Height</span>
                            <span class="text-slate-800"><?php echo $patientInfo['height'] ?? 'Not set'; ?> cm</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                            <span class="font-medium text-gray-700">Weight</span>
                            <span class="text-slate-800"><?php echo $patientInfo['weight'] ?? 'Not set'; ?> kg</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                            <span class="font-medium text-gray-700">Gender</span>
                            <span class="text-slate-800"><?php echo $patientInfo['gender'] ?? 'Not set'; ?></span>
                        </div>
                        <?php if ($patientInfo['height'] && $patientInfo['weight']): ?>
                        <?php 
                        $heightInMeters = $patientInfo['height'] / 100;
                        $bmi = round($patientInfo['weight'] / ($heightInMeters * $heightInMeters), 1);
                        $bmiCategory = '';
                        $bmiColor = '';
                        if ($bmi < 18.5) { $bmiCategory = 'Underweight'; $bmiColor = 'text-blue-600'; }
                        elseif ($bmi < 25) { $bmiCategory = 'Normal'; $bmiColor = 'text-green-600'; }
                        elseif ($bmi < 30) { $bmiCategory = 'Overweight'; $bmiColor = 'text-yellow-600'; }
                        else { $bmiCategory = 'Obese'; $bmiColor = 'text-red-600'; }
                        ?>
                        <div class="flex justify-between items-center p-3 bg-gradient-to-r from-purple-50 to-pink-50 rounded-lg border border-purple-200">
                            <span class="font-medium text-gray-700">BMI</span>
                            <div class="text-right">
                                <span class="text-slate-800 font-bold"><?php echo $bmi; ?></span>
                                <span class="block text-sm <?php echo $bmiColor; ?>"><?php echo $bmiCategory; ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-8">
                        <i class="fa-solid fa-user-plus fa-3x text-gray-400 mb-4"></i>
                        <p class="text-gray-600">Complete your profile to see health overview</p>
                        <a href="#" class="mt-4 inline-block bg-dark-orchid text-white px-4 py-2 rounded-lg hover:bg-purple-700">
                            Complete Profile
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Activity -->
                <div class="bg-white p-6 rounded-lg shadow-orchid-custom">
                    <h3 class="text-xl font-bold text-slate-800 mb-4">Recent Activity</h3>
                    <div class="space-y-4">
                        <!-- This will be populated with actual data from appointments and bookings -->
                        <div class="text-center py-8 text-gray-500">
                            <i class="fa-solid fa-clock fa-2x mb-3"></i>
                            <p>No recent activity</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
