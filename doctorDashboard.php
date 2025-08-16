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

// --- (MODIFIED) Handle Accept/Deny Actions ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['appointmentID'])) {
    $appointmentID = (int)$_POST['appointmentID'];
    $action = $_POST['action'];

    // Verify the appointment belongs to this doctor
    $verifyQuery = "SELECT appointmentID FROM appointment WHERE appointmentID = ? AND providerID = ?";
    $verifyStmt = $conn->prepare($verifyQuery);
    $verifyStmt->bind_param("ii", $appointmentID, $doctorID);
    $verifyStmt->execute();
    $verifyResult = $verifyStmt->get_result();

    if ($verifyResult->num_rows > 0) {
        $newStatus = '';
        
        // Logic for ACCEPTING a request
        if ($action === 'accept') {
            $newStatus = 'Scheduled';

            // --- Auto-generate a unique Google Meet link ---
            $part1 = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 3);
            $part2 = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 4);
            $part3 = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 3);
            $consultationLink = "https://meet.google.com/{$part1}-{$part2}-{$part3}";
            
            // Prepare query to update status AND the new consultation link
            $updateQuery = "UPDATE appointment SET status = ?, consultation_link = ? WHERE appointmentID = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param("ssi", $newStatus, $consultationLink, $appointmentID);

        // Logic for DENYING a request
        } elseif ($action === 'deny') {
            $newStatus = 'Denied';
            
            // Prepare query to update status only
            $updateQuery = "UPDATE appointment SET status = ? WHERE appointmentID = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param("si", $newStatus, $appointmentID);
        }

        // Execute the prepared statement if it was set
        if (isset($updateStmt)) {
            if ($updateStmt->execute()) {
                $successMsg = "Appointment has been successfully " . strtolower($newStatus) . ".";
            } else {
                $errorMsg = "Failed to update appointment status. Please try again.";
            }
            $updateStmt->close();
        }
    } else {
        $errorMsg = "Invalid appointment or you do not have permission to modify it.";
    }
    $verifyStmt->close();
}


// --- Fetch New Appointment Requests ---
$requestsQuery = "
    SELECT 
        a.appointmentID, 
        a.appointmentDate, 
        a.notes, 
        u.Name as patientName 
    FROM appointment a 
    JOIN users u ON a.patientID = u.userID 
    WHERE a.providerID = ? AND a.status = 'Requested' 
    ORDER BY a.appointmentDate ASC
";
$requestsStmt = $conn->prepare($requestsQuery);
$requestsStmt->bind_param("i", $doctorID);
$requestsStmt->execute();
$appointmentRequests = $requestsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$requestsStmt->close();
$conn->close();

// Helper function to format date
function formatAppointmentDate($datetime) {
    $date = new DateTime($datetime);
    return $date->format('D, M j, Y @ g:i A');
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
        .shadow-orchid-custom { box-shadow: 0 4px 6px -1px rgba(153, 50, 204, 0.1), 0 2px 4px -2px rgba(153, 50, 204, 0.1); }
        .feature-card {
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(153, 50, 204, 0.15), 0 4px 6px -2px rgba(153, 50, 204, 0.1);
        }
        .request-card { transition: box-shadow 0.2s ease-in-out; }
        .request-card:hover { box-shadow: 0 8px 25px -5px rgba(153, 50, 204, 0.15); }
    </style>
</head>
<body class="bg-purple-50">
    <div class="flex min-h-screen">
        <aside class="w-64 bg-white border-r">
            <div class="p-6">
                <a href="#" class="text-2xl font-bold text-dark-orchid">CarePlus</a>
            </div>
            <nav class="px-4">
                <a href="doctorDashboard.php" class="flex items-center space-x-3 px-4 py-3 bg-purple-100 text-dark-orchid rounded-lg"><i class="fa-solid fa-table-columns w-5"></i><span>Dashboard</span></a>
                <a href="doctorProfile.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-regular fa-user w-5"></i><span>My Profile</span></a>
                <a href="doctor_schedule.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-calendar-days w-5"></i><span>Provide Schedule</span></a>
                <a href="consultationInfo.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-laptop-medical w-5"></i><span>Consultations</span></a>
                <a href="#" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-file-waveform w-5"></i><span>Patient History</span></a>
                <a href="createPrescription.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-pills w-5"></i><span>Prescriptions</span></a>
                <a href="#" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-money-bill-wave w-5"></i><span>Transactions</span></a>
                <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg mt-8"><i class="fa-solid fa-arrow-right-from-bracket w-5"></i><span>Logout</span></a>
            </nav>
        </aside>
        <main class="flex-1 p-8">
            <header class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-800">Doctor Dashboard</h1>
                    <p class="text-gray-600 mt-1">Welcome back, Dr. <?php echo htmlspecialchars(explode(' ', $userName)[0]); ?>!</p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <p class="font-semibold text-slate-700"><?php echo htmlspecialchars($userName); ?></p>
                        <p class="text-sm text-gray-500">Doctor</p>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-dark-orchid text-white flex items-center justify-center font-bold text-lg"><?php echo htmlspecialchars($userAvatar); ?></div>
                </div>
            </header>

            <?php if ($successMsg): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <i class="fa-solid fa-check-circle mr-2"></i><?php echo $successMsg; ?>
            </div>
            <?php endif; ?>
            <?php if ($errorMsg): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fa-solid fa-exclamation-circle mr-2"></i><?php echo $errorMsg; ?>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <a href="doctor_schedule.php" class="feature-card bg-white p-6 rounded-lg shadow-orchid-custom text-center">
                    <i class="fa-solid fa-calendar-days fa-2x text-dark-orchid mb-3"></i>
                    <h3 class="text-lg font-semibold text-slate-700">Manage Schedule</h3>
                    <p class="text-sm text-gray-500">Set your available hours</p>
                </a>
                <a href="createPrescription.php" class="feature-card bg-white p-6 rounded-lg shadow-orchid-custom text-center">
                    <i class="fa-solid fa-pills fa-2x text-dark-orchid mb-3"></i>
                    <h3 class="text-lg font-semibold text-slate-700">Prepare Prescription</h3>
                    <p class="text-sm text-gray-500">Create new prescriptions</p>
                </a>
                <a href="#" class="feature-card bg-white p-6 rounded-lg shadow-orchid-custom text-center">
                    <i class="fa-solid fa-file-waveform fa-2x text-dark-orchid mb-3"></i>
                    <h3 class="text-lg font-semibold text-slate-700">View Patient History</h3>
                    <p class="text-sm text-gray-500">Access medical records</p>
                </a>
            </div>
            
            <hr>

            <div class="mt-8">
                <h2 class="text-2xl font-bold text-slate-800 mb-4">New Appointment Requests</h2>
                
                <?php if (empty($appointmentRequests)): ?>
                <div class="text-center py-12 bg-white rounded-lg shadow-orchid-custom">
                    <i class="fa-solid fa-check-circle fa-4x text-gray-400 mb-4"></i>
                    <h3 class="text-lg font-semibold text-gray-600">All Caught Up!</h3>
                    <p class="text-gray-500">You have no new appointment requests at the moment.</p>
                </div>
                <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($appointmentRequests as $request): ?>
                    <div class="request-card bg-white p-5 rounded-lg shadow-orchid-custom flex items-start justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-800"><?php echo htmlspecialchars($request['patientName']); ?></h3>
                            <div class="flex items-center space-x-4 text-gray-600 mt-1 mb-3">
                                <div class="flex items-center space-x-1.5">
                                    <i class="fa-solid fa-calendar-alt text-sm"></i>
                                    <span class="text-sm font-medium"><?php echo formatAppointmentDate($request['appointmentDate']); ?></span>
                                </div>
                            </div>
                            <?php if (!empty($request['notes'])): ?>
                            <div class="bg-gray-50 p-3 rounded-lg max-w-lg">
                                <p class="text-sm text-gray-700">
                                    <i class="fa-solid fa-sticky-note mr-1.5"></i>
                                    <strong>Patient Notes:</strong> <?php echo htmlspecialchars($request['notes']); ?>
                                </p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center space-x-3 ml-4">
                            <form method="POST" class="inline-block">
                                <input type="hidden" name="appointmentID" value="<?php echo $request['appointmentID']; ?>">
                                <button type="submit" name="action" value="deny" class="px-4 py-2 border border-red-300 text-red-700 rounded-lg text-sm font-medium hover:bg-red-50 transition">
                                    <i class="fa-solid fa-times mr-1"></i>Deny
                                </button>
                            </form>
                            <form method="POST" class="inline-block">
                                <input type="hidden" name="appointmentID" value="<?php echo $request['appointmentID']; ?>">
                                <button type="submit" name="action" value="accept" class="px-4 py-2 bg-green-500 text-white rounded-lg text-sm font-medium hover:bg-green-600 transition">
                                    <i class="fa-solid fa-check mr-1"></i>Accept
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>