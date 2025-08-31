<?php
/**
 * CAREGIVER VIEW PATIENTS PAGE
 * 
 * This page allows caregivers to view detailed information about their patients
 * from their active and past bookings.
 * 
 * Features:
 * - View all patients from caregiver's bookings
 * - Display patient personal information
 * - Show patient medical details (if available)
 * - View booking history with each patient
 * - Responsive design with patient selection
 */

session_start();

// === AUTHENTICATION & ACCESS CONTROL ===
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'CareGiver') {
    header("Location: login.php");
    exit();
}

// === DATABASE CONNECTION ===
$conn = require_once 'config.php';
date_default_timezone_set('Asia/Dhaka');

// === SESSION DATA ===
$careGiverID = $_SESSION['userID'];
$userName = $_SESSION['Name'];
$userAvatar = strtoupper(substr($userName, 0, 2));

$successMsg = "";
$errorMsg = "";

// === FETCH ALL PATIENTS FROM CAREGIVER'S BOOKINGS ===
$patientsQuery = "
    SELECT DISTINCT 
        u.userID as patientID,
        u.Name as patientName,
        u.email as patientEmail,
        u.contactNo as patientContact,
        u.profilePhoto as patientPhoto,
        COUNT(cb.bookingID) as totalBookings,
        MAX(cb.startDate) as lastBookingDate,
        SUM(CASE WHEN cb.status IN ('Scheduled', 'Active') THEN 1 ELSE 0 END) as activeBookings
    FROM caregiverbooking cb
    JOIN users u ON cb.patientID = u.userID
    WHERE cb.careGiverID = ?
    GROUP BY u.userID, u.Name, u.email, u.contactNo, u.profilePhoto
    ORDER BY MAX(cb.startDate) DESC
";

$stmt = $conn->prepare($patientsQuery);
$stmt->bind_param("i", $careGiverID);
$stmt->execute();
$patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// === HANDLE SELECTED PATIENT ===
$selectedPatientID = $_GET['patient_id'] ?? null;
$selectedPatient = null;
$bookingHistory = [];
$patientDetails = [];

if ($selectedPatientID) {
    // Verify this patient belongs to this caregiver
    $verifyQuery = "SELECT COUNT(*) as count FROM caregiverbooking WHERE careGiverID = ? AND patientID = ?";
    $verifyStmt = $conn->prepare($verifyQuery);
    $verifyStmt->bind_param("ii", $careGiverID, $selectedPatientID);
    $verifyStmt->execute();
    $verifyResult = $verifyStmt->get_result()->fetch_assoc();
    $verifyStmt->close();
    
    if ($verifyResult['count'] > 0) {
        // Get selected patient basic details
        foreach ($patients as $patient) {
            if ($patient['patientID'] == $selectedPatientID) {
                $selectedPatient = $patient;
                break;
            }
        }
        
        // Try to get additional patient details from patient table if it exists
        try {
            $patientDetailsQuery = "SELECT * FROM patient WHERE patientID = ?";
            $detailsStmt = $conn->prepare($patientDetailsQuery);
            $detailsStmt->bind_param("i", $selectedPatientID);
            $detailsStmt->execute();
            $result = $detailsStmt->get_result();
            if ($result->num_rows > 0) {
                $patientDetails = $result->fetch_assoc();
            }
            $detailsStmt->close();
        } catch (Exception $e) {
            // Patient table might not exist or have different structure
            $patientDetails = [];
        }
        
        // Get booking history for selected patient
        $historyQuery = "
            SELECT 
                bookingID,
                bookingType,
                startDate,
                endDate,
                totalAmount,
                status,
                availabilityID
            FROM caregiverbooking 
            WHERE careGiverID = ? AND patientID = ?
            ORDER BY startDate DESC
        ";
        $historyStmt = $conn->prepare($historyQuery);
        $historyStmt->bind_param("ii", $careGiverID, $selectedPatientID);
        $historyStmt->execute();
        $bookingHistory = $historyStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $historyStmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Patient Details - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-dark-orchid { background-color: #9932CC; }
        .text-dark-orchid { color: #9932CC; }
        .shadow-orchid { box-shadow: 0 4px 6px -1px rgba(153, 50, 204, 0.1), 0 2px 4px -1px rgba(153, 50, 204, 0.06); }
    </style>
</head>
<body class="bg-purple-50">
    <div class="flex min-h-screen">
        <!-- Sidebar Navigation -->
        <aside class="w-64 bg-white border-r shadow-sm">
            <div class="p-6">
                <a href="#" class="text-2xl font-bold text-dark-orchid">CarePlus</a>
            </div>
            <nav class="px-4 space-y-1">
                <a href="careGiverDashboard.php" 
                   class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg transition">
                    <i class="fa-solid fa-table-columns w-5"></i>
                    <span>Dashboard</span>
                </a>
                
                <a href="caregiverProfile.php" 
                   class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg transition">
                    <i class="fa-regular fa-user w-5"></i>
                    <span>My Profile</span>
                </a>
                
                <a href="caregiver_careplan.php" 
                   class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg transition">
                    <i class="fa-solid fa-clipboard-list w-5"></i>
                    <span>Care Plans</span>
                </a>
                
                <a href="caregiver_availability.php" 
                   class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg transition">
                    <i class="fa-solid fa-calendar-days w-5"></i>
                    <span>Availability</span>
                </a>
                
                <a href="my_bookings.php" 
                   class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg transition">
                    <i class="fa-solid fa-calendar-check w-5"></i>
                    <span>My Bookings</span>
                </a>
                
                <a href="caregiver_view_patients.php" 
                   class="flex items-center space-x-3 px-4 py-3 bg-purple-100 text-dark-orchid rounded-lg">
                    <i class="fa-solid fa-book-medical w-5"></i>
                    <span>View Patient Details</span>
                </a>
            </nav>
            
            <div class="mt-8 pt-4 border-t border-gray-200 px-4">
                <a href="logout.php" 
                   class="flex items-center space-x-3 px-4 py-3 text-red-600 hover:bg-red-50 rounded-lg transition">
                    <i class="fa-solid fa-arrow-right-from-bracket w-5"></i>
                    <span>Logout</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <!-- Header -->
            <header class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-800">Patient Details</h1>
                    <p class="text-gray-600 mt-1">View and manage your patient information</p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <p class="font-semibold text-slate-700"><?php echo htmlspecialchars($userName); ?></p>
                        <p class="text-sm text-gray-500">CareGiver</p>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-dark-orchid text-white flex items-center justify-center font-bold text-lg">
                        <?php echo htmlspecialchars($userAvatar); ?>
                    </div>
                </div>
            </header>

            <!-- Messages -->
            <?php if ($successMsg): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-r-lg">
                <i class="fa-solid fa-check-circle mr-2"></i><?php echo $successMsg; ?>
            </div>
            <?php endif; ?>

            <?php if ($errorMsg): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-r-lg">
                <i class="fa-solid fa-exclamation-circle mr-2"></i><?php echo $errorMsg; ?>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Patients List -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-xl shadow-orchid">
                        <div class="p-6 border-b border-gray-200">
                            <h2 class="text-xl font-semibold text-slate-800 flex items-center">
                                <i class="fa-solid fa-users text-blue-600 mr-2"></i>
                                My Patients
                                <span class="ml-auto text-sm bg-blue-100 text-blue-800 px-2 py-1 rounded-full">
                                    <?php echo count($patients); ?>
                                </span>
                            </h2>
                        </div>
                        
                        <div class="max-h-96 overflow-y-auto">
                            <?php if (empty($patients)): ?>
                                <div class="p-6 text-center text-gray-500">
                                    <i class="fa-solid fa-user-slash text-4xl mb-3"></i>
                                    <p class="font-semibold">No patients found</p>
                                    <p class="text-sm mt-1">You haven't had any bookings yet</p>
                                    <a href="careGiverDashboard.php" class="inline-block mt-3 text-blue-600 hover:underline">
                                        <i class="fa-solid fa-arrow-left mr-1"></i>Back to Dashboard
                                    </a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($patients as $patient): ?>
                                <a href="?patient_id=<?php echo $patient['patientID']; ?>" 
                                   class="block p-4 border-b border-gray-100 hover:bg-purple-50 transition <?php echo ($selectedPatientID == $patient['patientID']) ? 'bg-purple-50 border-l-4 border-l-purple-500' : ''; ?>">
                                    <div class="flex items-center space-x-3">
                                        <?php if ($patient['patientPhoto']): ?>
                                            <img src="<?php echo htmlspecialchars($patient['patientPhoto']); ?>" 
                                                 class="w-12 h-12 rounded-full object-cover border-2 border-purple-100">
                                        <?php else: ?>
                                            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-purple-400 to-blue-500 text-white flex items-center justify-center font-bold">
                                                <?php echo strtoupper(substr($patient['patientName'], 0, 2)); ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="flex-1 min-w-0">
                                            <p class="font-semibold text-slate-800 truncate">
                                                <?php echo htmlspecialchars($patient['patientName']); ?>
                                            </p>
                                            <p class="text-sm text-gray-600">
                                                <?php echo $patient['totalBookings']; ?> booking(s)
                                            </p>
                                            <?php if ($patient['activeBookings'] > 0): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 mt-1">
                                                    <i class="fa-solid fa-circle text-green-500 mr-1" style="font-size: 6px;"></i>
                                                    <?php echo $patient['activeBookings']; ?> Active
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Patient Details -->
                <div class="lg:col-span-2">
                    <?php if ($selectedPatient): ?>
                        <!-- Patient Information Card -->
                        <div class="bg-white rounded-xl shadow-orchid mb-6">
                            <div class="p-6 border-b border-gray-200">
                                <div class="flex items-center space-x-4">
                                    <?php if ($selectedPatient['patientPhoto']): ?>
                                        <img src="<?php echo htmlspecialchars($selectedPatient['patientPhoto']); ?>" 
                                             class="w-20 h-20 rounded-full object-cover border-3 border-purple-200">
                                    <?php else: ?>
                                        <div class="w-20 h-20 rounded-full bg-gradient-to-br from-purple-400 to-blue-500 text-white flex items-center justify-center font-bold text-2xl">
                                            <?php echo strtoupper(substr($selectedPatient['patientName'], 0, 2)); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div>
                                        <h2 class="text-2xl font-bold text-slate-800">
                                            <?php echo htmlspecialchars($selectedPatient['patientName']); ?>
                                        </h2>
                                        <p class="text-gray-600">Patient</p>
                                        <div class="flex items-center space-x-4 mt-2">
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                <i class="fa-solid fa-calendar-check mr-1"></i>
                                                <?php echo $selectedPatient['totalBookings']; ?> Total Bookings
                                            </span>
                                            <?php if ($selectedPatient['activeBookings'] > 0): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    <i class="fa-solid fa-heartbeat mr-1"></i>
                                                    <?php echo $selectedPatient['activeBookings']; ?> Active
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Contact Information -->
                            <div class="p-6">
                                <h3 class="text-lg font-semibold text-slate-700 mb-4 flex items-center">
                                    <i class="fa-solid fa-address-card text-indigo-600 mr-2"></i>
                                    Contact Information
                                </h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="flex items-center space-x-3">
                                        <i class="fa-solid fa-envelope text-gray-400 w-5"></i>
                                        <div>
                                            <p class="text-sm text-gray-500">Email</p>
                                            <p class="font-medium"><?php echo htmlspecialchars($selectedPatient['patientEmail']); ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center space-x-3">
                                        <i class="fa-solid fa-phone text-gray-400 w-5"></i>
                                        <div>
                                            <p class="text-sm text-gray-500">Phone</p>
                                            <p class="font-medium"><?php echo htmlspecialchars($selectedPatient['patientContact'] ?? 'Not provided'); ?></p>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($patientDetails['emergencyContact'])): ?>
                                    <div class="flex items-center space-x-3">
                                        <i class="fa-solid fa-phone-volume text-red-500 w-5"></i>
                                        <div>
                                            <p class="text-sm text-gray-500">Emergency Contact</p>
                                            <p class="font-medium"><?php echo htmlspecialchars($patientDetails['emergencyContact']); ?></p>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($patientDetails['address'])): ?>
                                    <div class="flex items-center space-x-3">
                                        <i class="fa-solid fa-map-marker-alt text-gray-400 w-5"></i>
                                        <div>
                                            <p class="text-sm text-gray-500">Address</p>
                                            <p class="font-medium"><?php echo htmlspecialchars($patientDetails['address']); ?></p>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Medical Information -->
                        <?php if (!empty($patientDetails)): ?>
                        <div class="bg-white rounded-xl shadow-orchid mb-6">
                            <div class="p-6 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-slate-700 flex items-center">
                                    <i class="fa-solid fa-heart-pulse text-red-600 mr-2"></i>
                                    Medical Information
                                </h3>
                            </div>
                            
                            <div class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                                    <?php if (!empty($patientDetails['dateOfBirth'])): ?>
                                    <div class="text-center p-4 bg-blue-50 rounded-lg">
                                        <i class="fa-solid fa-birthday-cake text-blue-600 text-2xl mb-2"></i>
                                        <p class="text-sm text-gray-500">Date of Birth</p>
                                        <p class="font-semibold text-slate-700">
                                            <?php 
                                            $age = date_diff(date_create($patientDetails['dateOfBirth']), date_create('today'))->y;
                                            echo date('M j, Y', strtotime($patientDetails['dateOfBirth'])) . " ($age years)"; 
                                            ?>
                                        </p>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($patientDetails['gender'])): ?>
                                    <div class="text-center p-4 bg-purple-50 rounded-lg">
                                        <i class="fa-solid fa-venus-mars text-purple-600 text-2xl mb-2"></i>
                                        <p class="text-sm text-gray-500">Gender</p>
                                        <p class="font-semibold text-slate-700"><?php echo htmlspecialchars($patientDetails['gender']); ?></p>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($patientDetails['bloodGroup'])): ?>
                                    <div class="text-center p-4 bg-red-50 rounded-lg">
                                        <i class="fa-solid fa-tint text-red-600 text-2xl mb-2"></i>
                                        <p class="text-sm text-gray-500">Blood Group</p>
                                        <p class="font-semibold text-slate-700"><?php echo htmlspecialchars($patientDetails['bloodGroup']); ?></p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (!empty($patientDetails['medicalHistory'])): ?>
                                <div class="border-t pt-6">
                                    <h4 class="font-semibold text-slate-700 mb-3 flex items-center">
                                        <i class="fa-solid fa-notes-medical text-orange-600 mr-2"></i>
                                        Medical History
                                    </h4>
                                    <div class="bg-gray-50 p-4 rounded-lg">
                                        <p class="text-gray-700 leading-relaxed">
                                            <?php echo nl2br(htmlspecialchars($patientDetails['medicalHistory'])); ?>
                                        </p>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Booking History -->
                        <div class="bg-white rounded-xl shadow-orchid">
                            <div class="p-6 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-slate-700 flex items-center">
                                    <i class="fa-solid fa-history text-purple-600 mr-2"></i>
                                    Booking History
                                    <span class="ml-auto text-sm bg-purple-100 text-purple-800 px-2 py-1 rounded-full">
                                        <?php echo count($bookingHistory); ?>
                                    </span>
                                </h3>
                            </div>
                            
                            <div class="p-6">
                                <?php if (empty($bookingHistory)): ?>
                                    <div class="text-center text-gray-500 py-8">
                                        <i class="fa-solid fa-calendar-xmark text-4xl mb-3"></i>
                                        <p>No booking history found</p>
                                    </div>
                                <?php else: ?>
                                    <div class="space-y-4">
                                        <?php foreach ($bookingHistory as $booking): ?>
                                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                                            <div class="flex items-center justify-between mb-2">
                                                <div class="flex items-center space-x-3">
                                                    <span class="px-3 py-1 rounded-full text-xs font-medium 
                                                        <?php 
                                                        switch($booking['status']) {
                                                            case 'Scheduled': echo 'bg-blue-100 text-blue-800'; break;
                                                            case 'Active': echo 'bg-green-100 text-green-800'; break;
                                                            case 'Completed': echo 'bg-gray-100 text-gray-800'; break;
                                                            case 'Canceled': echo 'bg-red-100 text-red-800'; break;
                                                            default: echo 'bg-gray-100 text-gray-800';
                                                        }
                                                        ?>">
                                                        <?php echo htmlspecialchars($booking['status']); ?>
                                                    </span>
                                                    <span class="text-sm font-medium text-slate-700">
                                                        <?php echo htmlspecialchars($booking['bookingType']); ?> Service
                                                    </span>
                                                </div>
                                                <p class="text-lg font-semibold text-green-600">
                                                    ৳<?php echo number_format($booking['totalAmount'], 2); ?>
                                                </p>
                                            </div>
                                            
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-600">
                                                <div>
                                                    <p><span class="font-medium">Start Date:</span> <?php echo date('M j, Y', strtotime($booking['startDate'])); ?></p>
                                                    <p><span class="font-medium">End Date:</span> <?php echo date('M j, Y', strtotime($booking['endDate'])); ?></p>
                                                </div>
                                                <div>
                                                    <p><span class="font-medium">Amount:</span> ৳<?php echo number_format($booking['totalAmount'], 2); ?></p>
                                                    <p><span class="font-medium">Booking ID:</span> #<?php echo $booking['bookingID']; ?></p>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    <?php else: ?>
                        <!-- No Patient Selected -->
                        <div class="bg-white rounded-xl shadow-orchid h-96 flex items-center justify-center">
                            <div class="text-center text-gray-500">
                                <i class="fa-solid fa-user-doctor text-6xl mb-4"></i>
                                <h3 class="text-xl font-semibold mb-2">Select a Patient</h3>
                                <p>Choose a patient from the list to view their detailed information</p>
                                <?php if (empty($patients)): ?>
                                <div class="mt-6">
                                    <p class="text-gray-600 mb-3">No patients found. This could be because:</p>
                                    <ul class="text-sm text-gray-500 space-y-1 mb-4">
                                        <li>• You haven't had any bookings yet</li>
                                        <li>• No patients have booked your services</li>
                                    </ul>
                                    <a href="careGiverDashboard.php" 
                                       class="inline-block bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition">
                                        <i class="fa-solid fa-arrow-left mr-2"></i>Back to Dashboard
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>