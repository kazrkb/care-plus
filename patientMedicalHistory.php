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

// Get patient's medical history (read-only, added by doctors)
$historyQuery = "
    SELECT 
        ph.historyID,
        ph.visitDate,
        ph.diagnosis,
        ph.labResultsFile,
        ph.medicalHistory,
        (SELECT u.Name FROM users u 
         JOIN appointment a ON u.userID = a.providerID 
         WHERE a.patientID = ph.patientID 
         AND DATE(a.appointmentDate) = ph.visitDate
         AND u.role = 'Doctor'
         LIMIT 1) as doctorName
    FROM patienthistory ph
    WHERE ph.patientID = ?
    ORDER BY ph.visitDate DESC
";

$historyStmt = $conn->prepare($historyQuery);
$historyStmt->bind_param("i", $userID);
$historyStmt->execute();
$historyResult = $historyStmt->get_result();
$medicalHistory = $historyResult->fetch_all(MYSQLI_ASSOC);

// Get prescriptions (read-only, prescribed by doctors)
$prescriptionsQuery = "
    SELECT 
        p.prescriptionID,
        p.`medicineNames-dosages` as medicineInfo,
        p.instructions,
        p.date,
        u.Name as doctorName
    FROM prescription p
    JOIN appointment a ON p.appointmentID = a.appointmentID
    JOIN users u ON p.doctorID = u.userID
    WHERE a.patientID = ?
    ORDER BY p.date DESC
";

$prescriptionsStmt = $conn->prepare($prescriptionsQuery);
$prescriptionsStmt->bind_param("i", $userID);
$prescriptionsStmt->execute();
$prescriptionsResult = $prescriptionsStmt->get_result();
$prescriptions = $prescriptionsResult->fetch_all(MYSQLI_ASSOC);

// Get medical documents
$documentsQuery = "
    SELECT 
        md.documentID,
        md.documentURL,
        ph.visitDate
    FROM medicaldocuments md
    JOIN patienthistory ph ON md.historyID = ph.historyID
    WHERE ph.patientID = ?
    ORDER BY ph.visitDate DESC
";

$documentsStmt = $conn->prepare($documentsQuery);
$documentsStmt->bind_param("i", $userID);
$documentsStmt->execute();
$documentsResult = $documentsStmt->get_result();
$medicalDocuments = $documentsResult->fetch_all(MYSQLI_ASSOC);

$conn->close();

// Helper function to format date
function formatDate($date) {
    if (!$date) return 'N/A';
    return date('M j, Y', strtotime($date));
}

function formatDateTime($datetime) {
    if (!$datetime) return 'N/A';
    return date('M j, Y g:i A', strtotime($datetime));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical History - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'dark-orchid': '#8B008B',
                        'light-orchid': '#DA70D6'
                    }
                }
            }
        }
    </script>
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #8B008B 0%, #DA70D6 100%);
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .history-card {
            transition: all 0.3s ease;
        }
        .history-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .bg-dark-orchid { background-color: #9932CC; }
        .text-dark-orchid { color: #9932CC; }
        .shadow-orchid-custom { box-shadow: 0 4px 6px -1px rgba(153, 50, 204, 0.1), 0 2px 4px -2px rgba(153, 50, 204, 0.1); }
    </style>
</head>
<body class="bg-purple-50">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="w-64 bg-white border-r">
            <div class="p-6">
                <a href="patientDashboard.php" class="text-2xl font-bold text-dark-orchid">CarePlus</a>
            </div>
            
            <nav class="px-4 space-y-2">
                <a href="patientDashboard.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg">
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
                <a href="patientMedicalHistory.php" class="flex items-center space-x-3 px-4 py-3 bg-purple-100 text-dark-orchid rounded-lg">
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
                    <h1 class="text-3xl font-bold text-slate-800">Medical History</h1>
                    <p class="text-gray-600 mt-1">Your complete medical records managed by your healthcare providers</p>
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
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="stat-card text-white p-6 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100">Medical Records</p>
                            <p class="text-3xl font-bold"><?php echo count($medicalHistory); ?></p>
                        </div>
                        <i class="fa-solid fa-file-medical fa-2x text-blue-200"></i>
                    </div>
                </div>
                
                <div class="bg-green-500 text-white p-6 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100">Documents</p>
                            <p class="text-3xl font-bold"><?php echo count($medicalDocuments); ?></p>
                        </div>
                        <i class="fa-solid fa-file-pdf fa-2x text-green-200"></i>
                    </div>
                </div>
                
                <div class="bg-purple-500 text-white p-6 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100">Prescriptions</p>
                            <p class="text-3xl font-bold"><?php echo count($prescriptions); ?></p>
                        </div>
                        <i class="fa-solid fa-pills fa-2x text-purple-200"></i>
                    </div>
                </div>
            </div>

            <!-- Medical History Section -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-slate-800">
                        <i class="fa-solid fa-file-medical text-dark-orchid mr-2"></i>
                        Medical History
                    </h2>
                    <div class="text-sm text-gray-500 bg-gray-100 px-3 py-2 rounded-lg">
                        <i class="fa-solid fa-info-circle mr-2"></i>
                        Records are managed by your doctors
                    </div>
                </div>

                <?php if (empty($medicalHistory)): ?>
                    <div class="text-center py-12">
                        <i class="fa-solid fa-file-medical fa-4x text-gray-300 mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-600 mb-2">No Medical History Found</h3>
                        <p class="text-gray-500 mb-4">Your medical history will appear here after your consultations with doctors.</p>
                        <p class="text-sm text-gray-400">Medical records are added by your healthcare providers during appointments.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($medicalHistory as $record): ?>
                            <div class="history-card border border-gray-200 rounded-lg p-6">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                            <i class="fa-solid fa-stethoscope text-blue-600"></i>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-gray-800">Visit Date: <?php echo formatDate($record['visitDate']); ?></h3>
                                            <?php if ($record['doctorName']): ?>
                                                <p class="text-sm text-gray-500">Added by Dr. <?php echo htmlspecialchars($record['doctorName']); ?></p>
                                            <?php else: ?>
                                                <p class="text-sm text-gray-500">Added by your healthcare provider</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded-full">Record #<?php echo $record['historyID']; ?></span>
                                </div>
                                
                                <?php if ($record['diagnosis']): ?>
                                    <div class="mb-4">
                                        <h4 class="font-medium text-gray-700 mb-2">Diagnosis:</h4>
                                        <p class="text-gray-600 bg-gray-50 p-3 rounded-lg"><?php echo htmlspecialchars($record['diagnosis']); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($record['medicalHistory']): ?>
                                    <div class="mb-4">
                                        <h4 class="font-medium text-gray-700 mb-2">Medical Notes:</h4>
                                        <p class="text-gray-600 bg-gray-50 p-3 rounded-lg"><?php echo htmlspecialchars($record['medicalHistory']); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($record['labResultsFile']): ?>
                                    <div class="mt-4">
                                        <h4 class="font-medium text-gray-700 mb-2">Lab Results:</h4>
                                        <a href="<?php echo htmlspecialchars($record['labResultsFile']); ?>" class="inline-flex items-center text-blue-600 hover:text-blue-800" target="_blank">
                                            <i class="fa-solid fa-file-pdf mr-2"></i>
                                            View Lab Results
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Prescriptions Section -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-slate-800">
                        <i class="fa-solid fa-pills text-purple-600 mr-2"></i>
                        Recent Prescriptions
                    </h2>
                    <div class="text-sm text-gray-500 bg-gray-100 px-3 py-2 rounded-lg">
                        <i class="fa-solid fa-user-doctor mr-2"></i>
                        Prescribed by your doctors
                    </div>
                </div>

                <?php if (empty($prescriptions)): ?>
                    <div class="text-center py-8">
                        <i class="fa-solid fa-pills fa-3x text-gray-300 mb-4"></i>
                        <h3 class="text-lg font-semibold text-gray-600 mb-2">No Prescriptions Found</h3>
                        <p class="text-gray-500">Prescriptions from your doctors will appear here after consultations.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($prescriptions as $prescription): ?>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex justify-between items-start mb-3">
                                    <div>
                                        <h3 class="font-semibold text-gray-800">Medicine & Dosage</h3>
                                        <p class="text-sm text-gray-500">Prescribed by Dr. <?php echo htmlspecialchars($prescription['doctorName']); ?></p>
                                        <p class="text-sm text-gray-500"><?php echo formatDate($prescription['date']); ?></p>
                                    </div>
                                    <span class="text-xs bg-purple-100 text-purple-800 px-2 py-1 rounded-full">Prescription #<?php echo $prescription['prescriptionID']; ?></span>
                                </div>
                                
                                <div class="mb-3">
                                    <h4 class="font-medium text-gray-700 mb-1">Medicine & Dosage:</h4>
                                    <p class="text-gray-600 bg-gray-50 p-2 rounded"><?php echo htmlspecialchars($prescription['medicineInfo']); ?></p>
                                </div>
                                
                                <?php if ($prescription['instructions']): ?>
                                    <div>
                                        <h4 class="font-medium text-gray-700 mb-1">Instructions:</h4>
                                        <p class="text-gray-600 bg-gray-50 p-2 rounded"><?php echo htmlspecialchars($prescription['instructions']); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Medical Documents Section (if any) -->
            <?php if (!empty($medicalDocuments)): ?>
            <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
                <h2 class="text-2xl font-bold text-slate-800 mb-6">
                    <i class="fa-solid fa-file-pdf text-red-600 mr-2"></i>
                    Medical Documents
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($medicalDocuments as $document): ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition duration-200">
                            <div class="text-center">
                                <i class="fa-solid fa-file-pdf fa-3x text-red-500 mb-3"></i>
                                <p class="text-sm text-gray-600 mb-2">Visit: <?php echo formatDate($document['visitDate']); ?></p>
                                <a href="<?php echo htmlspecialchars($document['documentURL']); ?>" target="_blank" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                    <i class="fa-solid fa-external-link mr-1"></i>
                                    View Document
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
