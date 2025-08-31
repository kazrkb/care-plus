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
        p.appointmentID,
        u.Name as doctorName,
        d.specialty,
        d.hospital,
        d.licNo,
        pat.Name as patientName,
        pat.userID as patientUserID
    FROM prescription p
    JOIN appointment a ON p.appointmentID = a.appointmentID
    JOIN users u ON p.doctorID = u.userID
    JOIN users pat ON a.patientID = pat.userID
    JOIN doctor d ON p.doctorID = d.doctorID
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
    <!-- PDF Generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
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
        
        /* Modal styles */
        .modal-overlay {
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(2px);
        }
        
        /* Prescription styles */
        .prescription-header {
            background: linear-gradient(135deg, #9932CC 0%, #DA70D6 100%);
        }
        
        .prescription-content {
            font-family: 'Times New Roman', Times, serif;
        }
        
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; background: white; }
            .prescription-modal { background: white; box-shadow: none; }
        }
        
        .line-clamp-3 {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
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
                <a href="doctorBooking.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg">
                    <i class="fa-solid fa-user-doctor w-5"></i>
                    <span>Doctor Appointments</span>
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
            <div id="prescriptions" class="bg-white rounded-lg shadow-lg p-6 mb-8">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6 gap-4">
                    <div class="flex items-center">
                        <h2 class="text-2xl font-bold text-slate-800 mr-3">
                            <i class="fa-solid fa-pills text-purple-600 mr-2"></i>
                            Recent Prescriptions
                        </h2>
                        <div class="text-sm text-gray-500 bg-gray-100 px-3 py-2 rounded-lg">
                            <i class="fa-solid fa-user-doctor mr-2"></i>
                            Prescribed by your doctors
                        </div>
                    </div>
                    <div class="flex items-center space-x-3 w-full md:w-auto">
                        <input id="prescriptionSearch" type="text" placeholder="Search by doctor, medicine or date" class="w-full md:w-80 px-3 py-2 border rounded-lg text-sm" />
                        <select id="prescriptionFilter" class="px-3 py-2 border rounded-lg text-sm">
                            <option value="all">All</option>
                            <option value="last30">Last 30 days</option>
                            <option value="last90">Last 90 days</option>
                        </select>
                    </div>
                </div>

                <?php if (empty($prescriptions)): ?>
                    <div class="text-center py-8">
                        <i class="fa-solid fa-pills fa-3x text-gray-300 mb-4"></i>
                        <h3 class="text-lg font-semibold text-gray-600 mb-2">No Prescriptions Found</h3>
                        <p class="text-gray-500">Prescriptions from your doctors will appear here after consultations.</p>
                    </div>
                <?php else: ?>
                    <div id="prescriptionList" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <?php foreach ($prescriptions as $prescription): ?>
                            <div class="prescription-card border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow" data-prescriptionid="<?php echo $prescription['prescriptionID']; ?>" data-doctor="<?php echo htmlspecialchars(strtolower($prescription['doctorName'])); ?>" data-medicine="<?php echo htmlspecialchars(strtolower($prescription['medicineInfo'])); ?>" data-date="<?php echo htmlspecialchars($prescription['date']); ?>">
                                <div class="flex justify-between items-start mb-3">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center">
                                            <i class="fa-solid fa-prescription text-purple-600"></i>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-gray-800 text-sm">Dr. <?php echo htmlspecialchars($prescription['doctorName']); ?></h3>
                                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($prescription['specialty']); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo formatDate($prescription['date']); ?></p>
                                        </div>
                                    </div>
                                    <span class="text-xs bg-purple-100 text-purple-800 px-2 py-1 rounded-full">#<?php echo $prescription['prescriptionID']; ?></span>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="flex flex-wrap gap-2">
                                        <?php foreach (explode(',', $prescription['medicineInfo']) as $med): ?>
                                            <span class="text-xs bg-gray-100 text-gray-800 px-2 py-1 rounded-full"><?php echo htmlspecialchars(trim($med)); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <div class="flex justify-between items-center">
                                    <button onclick="openPrescriptionModalById(<?php echo $prescription['prescriptionID']; ?>)" 
                                            class="bg-purple-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-purple-700 transition-colors">
                                        <i class="fa-solid fa-eye mr-1"></i>
                                        View Full
                                    </button>
                                    <div class="flex items-center space-x-2">
                                        <button onclick="downloadPrescriptionById(<?php echo $prescription['prescriptionID']; ?>)" 
                                                class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 transition-colors">
                                            <i class="fa-solid fa-download mr-1"></i>
                                            PDF
                                        </button>
                                        <button onclick="openPrescriptionModalById(<?php echo $prescription['prescriptionID']; ?>)" class="text-gray-500 text-sm">Details</button>
                                    </div>
                                </div>
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

    <!-- Prescription Modal -->
    <div id="prescriptionModal" class="fixed inset-0 modal-overlay hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-2xl max-w-4xl w-full m-4 max-h-screen overflow-y-auto prescription-modal border-2 border-gray-200">
            <!-- Modal Header -->
            <div class="bg-gray-100 border-b-2 border-gray-300 p-4 no-print">
                <div class="flex justify-between items-center">
                    <h3 class="text-xl font-bold text-gray-800" style="font-family: 'Times New Roman', serif;">
                        <i class="fa-solid fa-prescription mr-2"></i>
                        Medical Prescription
                    </h3>
                    <div class="flex items-center space-x-3">
                        <button onclick="downloadModalPDF()" class="bg-blue-700 hover:bg-blue-800 text-white px-4 py-2 rounded-lg transition-colors text-sm">
                            <i class="fa-solid fa-file-pdf mr-2"></i>
                            Print as PDF
                        </button>
                        <button onclick="printPrescription()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition-colors text-sm">
                            <i class="fa-solid fa-print mr-2"></i>
                            Print
                        </button>
                        <button onclick="closePrescriptionModal()" class="text-gray-600 hover:text-gray-800 p-2">
                            <i class="fa-solid fa-times fa-lg"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Prescription Content -->
            <div id="prescriptionContent" class="prescription-content p-8 bg-white">
                <!-- This will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <script>
        let currentPrescription = null;
    // Load prescriptions into a JS array for safe client-side actions
    const PRESCRIPTIONS = <?php echo json_encode($prescriptions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        function openPrescriptionModal(prescription) {
            currentPrescription = prescription;
            const modal = document.getElementById('prescriptionModal');
            const content = document.getElementById('prescriptionContent');
            
            // Generate prescription content
            content.innerHTML = generatePrescriptionHTML(prescription);
            
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }

        function closePrescriptionModal() {
            const modal = document.getElementById('prescriptionModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = 'auto';
            currentPrescription = null;
        }

        function generatePrescriptionHTML(prescription) {
            const currentDate = new Date().toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });

            return `
            <div style="font-family: Arial, Helvetica, sans-serif; color: #111;">
                <div style="display:flex;justify-content:space-between;align-items:flex-end;border-bottom:2px solid #e5e7eb;padding-bottom:12px;margin-bottom:12px;">
                    <div style="display:flex;align-items:center;">
                        <img src="/uploads/logo.png" alt="Clinic Logo" onerror="this.style.display='none'" style="width:56px;height:56px;object-fit:contain;margin-right:12px;">
                        <div>
                            <div style="font-family: Georgia, serif; font-size:22px; font-weight:700; color:#111; margin-bottom:2px;">Dr. ${prescription.doctorName}</div>
                            <div style="font-family: Georgia, serif; font-size:14px; color:#374151;">${prescription.specialty}</div>
                            <div style="font-family: Georgia, serif; font-size:13px; color:#374151;">${prescription.hospital}</div>
                            ${prescription.licNo ? `<div style="font-family: Georgia, serif; font-size:12px; color:#374151;">Reg. No: ${prescription.licNo}</div>` : ''}
                        </div>
                    </div>
                    <div style="text-align:right; min-width:140px;">
                        <div style="font-size:13px; color:#374151;">Date: ${new Date(prescription.date).toLocaleDateString()}</div>
                        <div style="font-size:13px; color:#374151;">Prescription No: <strong>${String(prescription.prescriptionID).padStart(6, '0')}</strong></div>
                    </div>
                </div>

                <div style="font-size:12px; color:#6b7280; margin-bottom:8px;">CarePlus Healthcare Center, 123 Medical District, Dhaka - 1205 | +880-1234-567890</div>

                <div style="margin-bottom:8px;">
                    <strong>Patient:</strong> ${prescription.patientName} &nbsp;&nbsp; <strong>Patient ID:</strong> ${prescription.patientUserID}
                </div>

                <div style="display:flex;align-items:flex-start;margin-top:12px;">
                    <div style="font-size:40px;font-family: Georgia, serif; line-height:1; margin-right:12px;">℞</div>
                    <div style="flex:1;">
                        <div style="font-size:16px; color:#111; line-height:1.5; min-height:100px;">
                            ${prescription.medicineInfo
                                .split(/,\s*/)
                                .map(med => `<div style='margin-bottom:6px;'>${med.trim()}</div>`)
                                .join('')}
                        </div>
                    </div>
                </div>

                ${prescription.instructions ? `
                <div style="margin-top:12px;">
                    <strong>Instructions:</strong>
                    <div style="white-space:pre-line;margin-top:6px;">${prescription.instructions}</div>
                </div>
                ` : ''}

                <div style="margin-top:40px;text-align:right;">
                    <div style="border-top:1px solid #e5e7eb;padding-top:6px;display:inline-block;min-width:200px;">
                        <div style="font-style:italic;color:#6b7280;">Signature</div>
                        <div style="font-family: Georgia, serif; font-weight:700;">Dr. ${prescription.doctorName}</div>
                    </div>
                </div>

                <div style="margin-top:16px;border-top:1px solid #eee;padding-top:8px;font-size:11px;color:#6b7280;text-align:center;">
                    This is a computer generated prescription. Valid for 30 days from date of issue.<br>
                    Generated on: ${currentDate}
                </div>
            </div>
            `;
        }

        function printPrescription() {
            window.print();
        }

        // Open a new window with printable prescription HTML and trigger print (reliable fallback)
        function openPrintWindowForPrescription(prescription) {
            try {
                const printWindow = window.open('', '_blank', 'width=900,height=1100');
                if (!printWindow) { alert('Unable to open print window. Please allow popups for this site.'); return; }
                const html = `
                    <html>
                    <head>
                        <title>Prescription - ${prescription.patientName}</title>
                        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
                        <style>
                            body { font-family: Arial, Helvetica, sans-serif; padding: 24px; color: #111; }
                            @media print { @page { margin: 20mm; } }
                        </style>
                    </head>
                    <body>
                        ${generatePrescriptionHTML(prescription)}
                        <script>
                            setTimeout(() => { window.print(); }, 400);
                            window.onafterprint = function() { window.close(); };
                        <\/script>
                    </body>
                    </html>
                `;
                printWindow.document.open();
                printWindow.document.write(html);
                printWindow.document.close();
            } catch (err) {
                console.error('print window error', err);
                // fallback: open modal then let user use Print as PDF
                currentPrescription = prescription;
                openPrescriptionModal(prescription);
                alert('Unable to open print window automatically. The prescription has been opened in a modal. Use the Print as PDF button there.');
            }
        }

        function downloadModalPDF() {
            if (!currentPrescription) return;
            openPrintWindowForPrescription(currentPrescription);
        }

        function openPrescriptionModalById(id) {
            console.log('openPrescriptionModalById', id);
            let pres = null;
            if (typeof PRESCRIPTIONS !== 'undefined' && Array.isArray(PRESCRIPTIONS)) {
                pres = PRESCRIPTIONS.find(p => Number(p.prescriptionID) === Number(id));
            }
            // fallback: try to read data attributes from the card
            if (!pres) {
                const card = document.querySelector(`#prescriptionList .prescription-card[data-date][data-doctor][data-medicine][data-prescriptionid='${id}']`) || document.querySelector(`#prescriptionList .prescription-card[data-prescriptionid='${id}']`);
                if (card) {
                    pres = {
                        prescriptionID: id,
                        doctorName: card.querySelector('h3') ? card.querySelector('h3').innerText.replace(/^Dr\.\s*/,'') : 'Doctor',
                        specialty: card.querySelector('.text-xs') ? card.querySelector('.text-xs').innerText : '',
                        date: card.getAttribute('data-date') || '',
                        medicineInfo: card.getAttribute('data-medicine') || '',
                        patientName: '<?php echo addslashes($userName); ?>',
                        patientUserID: '<?php echo $userID; ?>'
                    };
                }
            }
            if (!pres) { console.warn('Prescription data not found for id', id); return; }
            openPrescriptionModal(pres);
        }

        function downloadPrescriptionById(id) {
            console.log('downloadPrescriptionById', id);
            const pres = (typeof PRESCRIPTIONS !== 'undefined' && Array.isArray(PRESCRIPTIONS)) ? PRESCRIPTIONS.find(p => Number(p.prescriptionID) === Number(id)) : null;
            if (!pres) {
                console.warn('Prescription not found in PRESCRIPTIONS for id', id);
                // Try to build minimal from DOM
                const card = document.querySelector(`#prescriptionList .prescription-card[data-prescriptionid='${id}']`);
                if (card) {
                    const fallback = {
                        prescriptionID: id,
                        doctorName: card.querySelector('h3') ? card.querySelector('h3').innerText.replace(/^Dr\.?\s*/,'') : 'Doctor',
                        specialty: card.querySelector('.text-xs') ? card.querySelector('.text-xs').innerText : '',
                        date: card.getAttribute('data-date') || '',
                        medicineInfo: card.getAttribute('data-medicine') || '',
                        patientName: '<?php echo addslashes($userName); ?>',
                        patientUserID: '<?php echo $userID; ?>'
                    };
                    openPrintWindowForPrescription(fallback);
                    return;
                }
                return;
            }
            openPrintWindowForPrescription(pres);
        }

        function downloadPrescriptionPDF(prescription) {
            // Create a temporary container for PDF generation
            const tempContainer = document.createElement('div');
            tempContainer.style.position = 'absolute';
            tempContainer.style.left = '-9999px';
            tempContainer.style.background = 'white';
            tempContainer.style.padding = '20px';
            tempContainer.innerHTML = generatePrescriptionHTML(prescription);
            
            document.body.appendChild(tempContainer);
            
            const opt = {
                margin: 0.5,
                filename: `prescription_${prescription.prescriptionID}_${prescription.patientName.replace(/\s+/g, '_')}.pdf`,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
            };
            
            html2pdf().set(opt).from(tempContainer).save().then(() => {
                document.body.removeChild(tempContainer);
            });
        }

        // Close modal when clicking outside
        document.getElementById('prescriptionModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePrescriptionModal();
            }
        });

        // Handle hash navigation to prescriptions section
        window.addEventListener('DOMContentLoaded', function() {
            if (window.location.hash === '#prescriptions') {
                document.getElementById('prescriptions').scrollIntoView({ behavior: 'smooth' });
            }
            // Prescription search/filter
            const searchInput = document.getElementById('prescriptionSearch');
            const filterSelect = document.getElementById('prescriptionFilter');
            if (searchInput) {
                searchInput.addEventListener('input', applyPrescriptionFilters);
            }
            if (filterSelect) {
                filterSelect.addEventListener('change', applyPrescriptionFilters);
            }
        });

        function applyPrescriptionFilters() {
            const q = document.getElementById('prescriptionSearch').value.trim().toLowerCase();
            const range = document.getElementById('prescriptionFilter').value;
            const cards = document.querySelectorAll('#prescriptionList .prescription-card');
            const now = new Date();
            cards.forEach(card => {
                const doctor = card.getAttribute('data-doctor') || '';
                const medicine = card.getAttribute('data-medicine') || '';
                const dateStr = card.getAttribute('data-date') || '';
                let visible = true;
                if (q) {
                    visible = doctor.includes(q) || medicine.includes(q) || dateStr.includes(q);
                }
                if (visible && range !== 'all' && dateStr) {
                    const d = new Date(dateStr);
                    const diffDays = Math.floor((now - d) / (1000 * 60 * 60 * 24));
                    if (range === 'last30' && diffDays > 30) visible = false;
                    if (range === 'last90' && diffDays > 90) visible = false;
                }
                card.style.display = visible ? '' : 'none';
            });
        }
    </script>
</body>
</html>
