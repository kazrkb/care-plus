<?php
session_start();

// Protect this page: allow only logged-in Patients
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Patient') {
    header("Location: login.php");
    exit();
}

// Database Connection
$conn = require_once 'config.php';

$userName = $_SESSION['Name'];
$userID = $_SESSION['userID'];
$appointmentID = $_GET['appointmentID'] ?? 0;

// Get prescription details for the specific appointment
$prescriptionQuery = "
    SELECT 
        p.prescriptionID,
        p.`medicineNames-dosages` as medicines,
        p.instructions,
        p.date,
        a.appointmentDate,
        a.notes as appointmentNotes,
        u.Name as doctorName,
        d.specialty,
        d.hospital,
        pat.Name as patientName
    FROM prescription p
    INNER JOIN appointment a ON p.appointmentID = a.appointmentID
    INNER JOIN users u ON p.doctorID = u.userID
    INNER JOIN doctor d ON u.userID = d.doctorID
    INNER JOIN users pat ON a.patientID = pat.userID
    WHERE p.appointmentID = ? AND a.patientID = ?
";

$prescriptionStmt = $conn->prepare($prescriptionQuery);
$prescriptionStmt->bind_param("ii", $appointmentID, $userID);
$prescriptionStmt->execute();
$prescriptionResult = $prescriptionStmt->get_result();
$prescription = $prescriptionResult->fetch_assoc();

$conn->close();

if (!$prescription) {
    header("Location: patientAppointments.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescription - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-dark-orchid { background-color: #9932CC; }
        .text-dark-orchid { color: #9932CC; }
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; }
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen p-4">
        <!-- Header with Back Button -->
        <div class="no-print mb-6 flex justify-between items-center">
            <a href="patientAppointments.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors">
                <i class="fa-solid fa-arrow-left mr-2"></i>
                Back to Appointments
            </a>
            <button onclick="window.print()" class="bg-dark-orchid text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition-colors">
                <i class="fa-solid fa-print mr-2"></i>
                Print Prescription
            </button>
        </div>

        <!-- Prescription Content -->
        <div class="max-w-4xl mx-auto bg-white shadow-lg rounded-lg overflow-hidden">
            <!-- Header -->
            <div class="bg-dark-orchid text-white p-6">
                <div class="flex justify-between items-start">
                    <div>
                        <h1 class="text-2xl font-bold mb-2">CarePlus Healthcare</h1>
                        <p class="text-purple-200">Medical Prescription</p>
                    </div>
                    <div class="text-right">
                        <div class="text-purple-200 text-sm">Prescription ID</div>
                        <div class="text-xl font-bold">#<?php echo str_pad($prescription['prescriptionID'], 6, '0', STR_PAD_LEFT); ?></div>
                    </div>
                </div>
            </div>

            <!-- Doctor Information -->
            <div class="p-6 border-b border-gray-200">
                <div class="grid md:grid-cols-2 gap-6">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 mb-4">Doctor Information</h2>
                        <div class="space-y-2">
                            <div class="flex items-center">
                                <i class="fa-solid fa-user-md w-5 text-gray-500 mr-3"></i>
                                <span class="font-medium">Dr. <?php echo htmlspecialchars($prescription['doctorName']); ?></span>
                            </div>
                            <div class="flex items-center">
                                <i class="fa-solid fa-stethoscope w-5 text-gray-500 mr-3"></i>
                                <span><?php echo htmlspecialchars($prescription['specialty']); ?></span>
                            </div>
                            <div class="flex items-center">
                                <i class="fa-solid fa-hospital w-5 text-gray-500 mr-3"></i>
                                <span><?php echo htmlspecialchars($prescription['hospital']); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 mb-4">Patient Information</h2>
                        <div class="space-y-2">
                            <div class="flex items-center">
                                <i class="fa-solid fa-user w-5 text-gray-500 mr-3"></i>
                                <span class="font-medium"><?php echo htmlspecialchars($prescription['patientName']); ?></span>
                            </div>
                            <div class="flex items-center">
                                <i class="fa-solid fa-calendar w-5 text-gray-500 mr-3"></i>
                                <span><?php echo date('F j, Y', strtotime($prescription['appointmentDate'])); ?></span>
                            </div>
                            <div class="flex items-center">
                                <i class="fa-solid fa-calendar-check w-5 text-gray-500 mr-3"></i>
                                <span>Prescribed: <?php echo date('F j, Y', strtotime($prescription['date'])); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Appointment Notes -->
            <?php if (!empty($prescription['appointmentNotes'])): ?>
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-800 mb-3">Consultation Notes</h2>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($prescription['appointmentNotes'])); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Prescription Details -->
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Prescribed Medications</h2>
                <div class="bg-blue-50 p-4 rounded-lg">
                    <div class="font-medium text-gray-800 mb-2">Medicines & Dosages:</div>
                    <p class="text-gray-700 whitespace-pre-line"><?php echo htmlspecialchars($prescription['medicines']); ?></p>
                </div>
            </div>

            <!-- Instructions -->
            <?php if (!empty($prescription['instructions'])): ?>
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">Instructions</h2>
                    <div class="bg-yellow-50 p-4 rounded-lg border-l-4 border-yellow-400">
                        <p class="text-gray-700 whitespace-pre-line"><?php echo htmlspecialchars($prescription['instructions']); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Important Notes -->
            <div class="p-6 bg-red-50">
                <div class="flex items-start">
                    <i class="fa-solid fa-exclamation-triangle text-red-500 mt-1 mr-3"></i>
                    <div>
                        <h3 class="font-semibold text-red-800 mb-2">Important Notes:</h3>
                        <ul class="text-red-700 text-sm space-y-1">
                            <li>• Take medications as prescribed by your doctor</li>
                            <li>• Complete the full course of antibiotics if prescribed</li>
                            <li>• Contact your doctor if you experience any adverse reactions</li>
                            <li>• Do not share medications with others</li>
                            <li>• Follow up with your doctor as recommended</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="p-6 bg-gray-100 text-center">
                <p class="text-gray-600 text-sm">
                    This prescription was generated electronically by CarePlus Healthcare System.<br>
                    For any queries, please contact your healthcare provider.
                </p>
                <div class="mt-2 text-xs text-gray-500">
                    Generated on: <?php echo date('F j, Y g:i A'); ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
