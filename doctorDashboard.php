<?php
session_start();

// Protect this page: allow only logged-in Doctors
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Doctor') {
    header("Location: login.php");
    exit();
}

// --- Database Connection ---
$conn = require_once 'config.php'; 

$doctorID = $_SESSION['userID'];
$userName = $_SESSION['Name'];
$userAvatar = strtoupper(substr($userName, 0, 2));

$successMsg = "";
$errorMsg = "";

// --- Fetch Dashboard Statistics ---
// Total patients consulted
$patientsQuery = "SELECT COUNT(DISTINCT a.patientID) as total_patients 
                  FROM appointment a 
                  WHERE a.providerID = ? AND a.status IN ('Scheduled', 'Completed')";
$patientsStmt = $conn->prepare($patientsQuery);
$patientsStmt->bind_param("i", $doctorID);
$patientsStmt->execute();
$totalPatients = $patientsStmt->get_result()->fetch_assoc()['total_patients'];
$patientsStmt->close();

// Today's appointments
$todayQuery = "SELECT COUNT(*) as today_appointments 
               FROM appointment 
               WHERE providerID = ? AND DATE(appointmentDate) = CURDATE() AND status = 'Scheduled'";
$todayStmt = $conn->prepare($todayQuery);
$todayStmt->bind_param("i", $doctorID);
$todayStmt->execute();
$todayAppointments = $todayStmt->get_result()->fetch_assoc()['today_appointments'];
$todayStmt->close();

// Pending requests
$pendingQuery = "SELECT COUNT(*) as pending_requests 
                 FROM appointment 
                 WHERE providerID = ? AND status = 'Requested'";
$pendingStmt = $conn->prepare($pendingQuery);
$pendingStmt->bind_param("i", $doctorID);
$pendingStmt->execute();
$pendingRequests = $pendingStmt->get_result()->fetch_assoc()['pending_requests'];
$pendingStmt->close();

// Total prescriptions issued
$prescriptionsQuery = "SELECT COUNT(*) as total_prescriptions 
                       FROM prescription 
                       WHERE doctorID = ?";
$prescriptionsStmt = $conn->prepare($prescriptionsQuery);
$prescriptionsStmt->bind_param("i", $doctorID);
$prescriptionsStmt->execute();
$totalPrescriptions = $prescriptionsStmt->get_result()->fetch_assoc()['total_prescriptions'];
$prescriptionsStmt->close();

// --- Fetch Recent Activities ---
// Upcoming appointments (next 7 days)
$upcomingQuery = "SELECT a.appointmentID, a.appointmentDate, u.Name as patientName, a.notes
                  FROM appointment a 
                  JOIN users u ON a.patientID = u.userID 
                  WHERE a.providerID = ? AND a.status = 'Scheduled' 
                  AND a.appointmentDate BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
                  ORDER BY a.appointmentDate ASC LIMIT 5";
$upcomingStmt = $conn->prepare($upcomingQuery);
$upcomingStmt->bind_param("i", $doctorID);
$upcomingStmt->execute();
$upcomingAppointments = $upcomingStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$upcomingStmt->close();

// Recent prescriptions (last 7 days)
$recentPresQuery = "SELECT p.prescriptionID, p.date, u.Name as patientName
                    FROM prescription p
                    JOIN appointment a ON p.appointmentID = a.appointmentID
                    JOIN users u ON a.patientID = u.userID
                    WHERE p.doctorID = ? AND p.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                    ORDER BY p.date DESC LIMIT 5";
$recentPresStmt = $conn->prepare($recentPresQuery);
$recentPresStmt->bind_param("i", $doctorID);
$recentPresStmt->execute();
$recentPrescriptions = $recentPresStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recentPresStmt->close();

$conn->close();

// Helper function to format date
function formatAppointmentDate($datetime) {
    $date = new DateTime($datetime);
    return $date->format('D, M j, Y @ g:i A');
}

function formatDate($date) {
    $dateObj = new DateTime($date);
    return $dateObj->format('M j, Y');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-dark-orchid { background-color: #9932CC; }
        .text-dark-orchid { color: #9932CC; }
        .border-dark-orchid { border-color: #9932CC; }
        .shadow-orchid-custom { box-shadow: 0 4px 6px -1px rgba(153, 50, 204, 0.1), 0 2px 4px -2px rgba(153, 50, 204, 0.1); }
        .feature-card {
            transition: all 0.3s ease-in-out;
        }
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(153, 50, 204, 0.15), 0 10px 10px -5px rgba(153, 50, 204, 0.04);
        }
        .stat-card {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(153, 50, 204, 0.1);
        }
        .activity-item {
            transition: all 0.2s ease;
        }
        .activity-item:hover {
            background-color: #faf5ff;
            border-left: 4px solid #9932CC;
        }
        .nav-active {
            background: linear-gradient(135deg, #e5d5f7 0%, #d6c7f7 100%);
            border-left: 4px solid #9932CC;
        }
    </style>
</head>
<body class="bg-purple-50">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="w-64 bg-white border-r shadow-lg">
            <div class="p-6 border-b">
                <a href="#" class="text-2xl font-bold text-dark-orchid flex items-center">
                    <i class="fa-solid fa-heartbeat mr-2"></i>
                    CarePlus
                </a>
                <p class="text-sm text-gray-500 mt-1">Healthcare Management</p>
            </div>
            
            <!-- Doctor Info Card -->
            <div class="p-4 border-b bg-gradient-to-r from-purple-50 to-purple-100">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 rounded-full bg-dark-orchid text-white flex items-center justify-center font-bold text-lg border-2 border-white shadow-lg">
                        <?php echo htmlspecialchars($userAvatar); ?>
                    </div>
                    <div>
                        <p class="font-semibold text-slate-700 text-sm"><?php echo htmlspecialchars($userName); ?></p>
                        <p class="text-xs text-gray-500">Doctor</p>
                    </div>
                </div>
            </div>
            
            <nav class="px-4 py-4 space-y-1">
                <a href="doctorDashboard.php" class="nav-active flex items-center space-x-3 px-4 py-3 text-dark-orchid rounded-lg font-medium">
                    <i class="fa-solid fa-table-columns w-5"></i><span>Dashboard</span>
                </a>
                <a href="doctorProfile.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg transition-colors">
                    <i class="fa-regular fa-user w-5"></i><span>My Profile</span>
                </a>
                <a href="doctor_schedule.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg transition-colors">
                    <i class="fa-solid fa-calendar-days w-5"></i><span>Provide Schedule</span>
                </a>
                <a href="consultationInfo.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg transition-colors">
                    <i class="fa-solid fa-laptop-medical w-5"></i><span>Consultations</span>
                </a>
                <a href="view_patient_history.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg transition-colors">
                    <i class="fa-solid fa-file-waveform w-5"></i><span>Patient History</span>
                </a>
                <a href="doctor_view_prescriptions.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg transition-colors">
                    <i class="fa-solid fa-pills w-5"></i><span>Prescriptions</span>
                </a>
                <a href="my_transactions.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg transition-colors">
                    <i class="fa-solid fa-money-bill-wave w-5"></i><span>Transactions</span>
                </a>
                
                <div class="pt-6 mt-6 border-t">
                    <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-red-50 hover:text-red-600 rounded-lg transition-colors">
                        <i class="fa-solid fa-arrow-right-from-bracket w-5"></i><span>Logout</span>
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <!-- Header -->
            <header class="mb-8">
                <div class="flex justify-between items-start">
                    <div>
                        <h1 class="text-3xl font-bold text-slate-800 mb-2">Doctor Dashboard</h1>
                        <p class="text-gray-600">Welcome back, Dr. <?php echo htmlspecialchars(explode(' ', $userName)[0]); ?>! Here's your practice overview.</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-500"><?php echo date('l, F j, Y'); ?></p>
                        <p class="text-lg font-semibold text-slate-700"><?php echo date('g:i A'); ?></p>
                    </div>
                </div>
            </header>

            <?php if ($successMsg): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center">
                <i class="fa-solid fa-check-circle mr-2"></i><?php echo $successMsg; ?>
            </div>
            <?php endif; ?>
            <?php if ($errorMsg): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-center">
                <i class="fa-solid fa-exclamation-circle mr-2"></i><?php echo $errorMsg; ?>
            </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="stat-card bg-white p-6 rounded-xl shadow-orchid-custom border-l-4 border-dark-orchid">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-3xl font-bold text-dark-orchid"><?php echo $totalPatients; ?></p>
                            <p class="text-gray-600 text-sm font-medium mt-1">Total Patients</p>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                            <i class="fa-solid fa-users text-dark-orchid text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card bg-white p-6 rounded-xl shadow-orchid-custom border-l-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-3xl font-bold text-green-600"><?php echo $todayAppointments; ?></p>
                            <p class="text-gray-600 text-sm font-medium mt-1">Today's Appointments</p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fa-solid fa-calendar-check text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card bg-white p-6 rounded-xl shadow-orchid-custom border-l-4 border-orange-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-3xl font-bold text-orange-600"><?php echo $pendingRequests; ?></p>
                            <p class="text-gray-600 text-sm font-medium mt-1">Pending Requests</p>
                        </div>
                        <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                            <i class="fa-solid fa-clock text-orange-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card bg-white p-6 rounded-xl shadow-orchid-custom border-l-4 border-blue-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-3xl font-bold text-blue-600"><?php echo $totalPrescriptions; ?></p>
                            <p class="text-gray-600 text-sm font-medium mt-1">Prescriptions Issued</p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fa-solid fa-prescription-bottle-medical text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="mb-8">
                <h2 class="text-xl font-bold text-slate-800 mb-4 flex items-center">
                    <i class="fa-solid fa-bolt mr-2 text-dark-orchid"></i>
                    Quick Actions
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <a href="doctor_schedule.php" class="feature-card bg-white p-6 rounded-xl shadow-orchid-custom text-center group">
                        <div class="w-16 h-16 mx-auto bg-purple-100 rounded-full flex items-center justify-center mb-4 group-hover:bg-dark-orchid transition-colors">
                            <i class="fa-solid fa-calendar-days fa-2x text-dark-orchid group-hover:text-white transition-colors"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-slate-700 mb-2">Manage Schedule</h3>
                        <p class="text-sm text-gray-500">Set your available hours and time slots</p>
                    </a>
                    
                    <a href="doctor_view_prescriptions.php" class="feature-card bg-white p-6 rounded-xl shadow-orchid-custom text-center group">
                        <div class="w-16 h-16 mx-auto bg-purple-100 rounded-full flex items-center justify-center mb-4 group-hover:bg-dark-orchid transition-colors">
                            <i class="fa-solid fa-pills fa-2x text-dark-orchid group-hover:text-white transition-colors"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-slate-700 mb-2"> Prescriptions</h3>
                        <p class="text-sm text-gray-500">Issue new prescriptions for patients</p>
                    </a>
                    
                    <a href="view_patient_history.php" class="feature-card bg-white p-6 rounded-xl shadow-orchid-custom text-center group">
                        <div class="w-16 h-16 mx-auto bg-purple-100 rounded-full flex items-center justify-center mb-4 group-hover:bg-dark-orchid transition-colors">
                            <i class="fa-solid fa-file-waveform fa-2x text-dark-orchid group-hover:text-white transition-colors"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-slate-700 mb-2">Patient History</h3>
                        <p class="text-sm text-gray-500">Access complete medical records</p>
                    </a>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Upcoming Appointments -->
                <div class="bg-white rounded-xl shadow-orchid-custom">
                    <div class="p-6 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-slate-800 flex items-center">
                            <i class="fa-solid fa-calendar-alt mr-2 text-dark-orchid"></i>
                            Upcoming Appointments
                        </h3>
                    </div>
                    <div class="p-6">
                        <?php if (empty($upcomingAppointments)): ?>
                        <div class="text-center py-8">
                            <i class="fa-solid fa-calendar-times fa-3x text-gray-300 mb-3"></i>
                            <p class="text-gray-500">No upcoming appointments</p>
                        </div>
                        <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($upcomingAppointments as $appointment): ?>
                            <div class="activity-item p-4 bg-gray-50 rounded-lg">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-semibold text-slate-700"><?php echo htmlspecialchars($appointment['patientName']); ?></p>
                                        <p class="text-sm text-gray-600 mt-1">
                                            <i class="fa-solid fa-clock mr-1"></i>
                                            <?php echo formatAppointmentDate($appointment['appointmentDate']); ?>
                                        </p>
                                        <?php if (!empty($appointment['notes'])): ?>
                                        <p class="text-xs text-gray-500 mt-1 italic"><?php echo htmlspecialchars($appointment['notes']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded-full">Scheduled</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Prescriptions -->
                <div class="bg-white rounded-xl shadow-orchid-custom">
                    <div class="p-6 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-slate-800 flex items-center">
                            <i class="fa-solid fa-prescription-bottle mr-2 text-dark-orchid"></i>
                            Recent Prescriptions
                        </h3>
                    </div>
                    <div class="p-6">
                        <?php if (empty($recentPrescriptions)): ?>
                        <div class="text-center py-8">
                            <i class="fa-solid fa-pills fa-3x text-gray-300 mb-3"></i>
                            <p class="text-gray-500">No recent prescriptions</p>
                        </div>
                        <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($recentPrescriptions as $prescription): ?>
                            <div class="activity-item p-4 bg-gray-50 rounded-lg">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <p class="font-semibold text-slate-700"><?php echo htmlspecialchars($prescription['patientName']); ?></p>
                                        <p class="text-sm text-gray-600">
                                            <i class="fa-solid fa-calendar mr-1"></i>
                                            <?php echo formatDate($prescription['date']); ?>
                                        </p>
                                    </div>
                                    <a href="doctor_view_prescriptions.php?id=<?php echo $prescription['prescriptionID']; ?>" 
                                       class="text-dark-orchid hover:text-purple-700 transition-colors">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
