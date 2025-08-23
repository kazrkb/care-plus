<?php
session_start();
$conn = require_once 'config.php';
date_default_timezone_set('Asia/Dhaka');

// Protect this page: allow only logged-in Patients
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Patient') {
    header("Location: login.php");
    exit();
}
$patientID = $_SESSION['userID'];
$userName = $_SESSION['Name'];
$userAvatar = strtoupper(substr($userName, 0, 2));
$successMsg = "";
$errorMsg = "";

// --- API Endpoint: Fetch available slots for a specific caregiver ---
if (isset($_GET['get_slots_for'])) {
    $careGiverID = (int)$_GET['get_slots_for'];
    $stmt = $conn->prepare("SELECT * FROM caregiver_availability WHERE careGiverID = ? AND status = 'Available' AND startDate >= CURDATE() ORDER BY startDate ASC");
    $stmt->bind_param("i", $careGiverID);
    $stmt->execute();
    $slots = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($slots);
    exit();
}

// --- Handle Booking Submission ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['book_availability_id'])) {
    $availabilityID = (int)$_POST['book_availability_id'];
    
    $conn->begin_transaction();
    try {
        // Step 1: Lock and verify the chosen slot is still available
        $schQuery = "SELECT * FROM caregiver_availability WHERE availabilityID = ? AND status = 'Available' FOR UPDATE";
        $schStmt = $conn->prepare($schQuery);
        $schStmt->bind_param("i", $availabilityID);
        $schStmt->execute();
        $slotDetails = $schStmt->get_result()->fetch_assoc();

        if ($slotDetails) {
            $careGiverID = $slotDetails['careGiverID'];
            $bookingType = $slotDetails['bookingType'];
            $startDate = $slotDetails['startDate'];
            
            // Calculate end date from the slot details
            $endDate = new DateTime($startDate);
            if ($bookingType == 'Daily') { $endDate->modify('+1 day'); }
            if ($bookingType == 'Weekly') { $endDate->modify('+7 days'); }
            if ($bookingType == 'Monthly') { $endDate->modify('+1 month'); }
            $endDateStr = $endDate->format('Y-m-d');

            // Check for patient conflicts
            $patientConflictCheck = $conn->prepare("SELECT COUNT(*) AS cnt FROM caregiverbooking WHERE patientID = ? AND status IN ('Scheduled', 'Active') AND ((startDate <= ? AND endDate >= ?) OR (startDate <= ? AND endDate >= ?))");
            $patientConflictCheck->bind_param("issss", $patientID, $startDate, $startDate, $endDateStr, $endDateStr);
            $patientConflictCheck->execute();
            $conflictResult = $patientConflictCheck->get_result()->fetch_assoc();

            if ($conflictResult['cnt'] > 0) {
                throw new Exception("You already have a booking during this time period.");
            }
            $patientConflictCheck->close();

            // All checks passed, proceed with booking
            $rateQuery = $conn->prepare("SELECT dailyRate, weeklyRate, monthlyRate FROM caregiver WHERE careGiverID = ?");
            $rateQuery->bind_param("i", $careGiverID);
            $rateQuery->execute();
            $rates = $rateQuery->get_result()->fetch_assoc();
            
            $totalAmount = 0;
            if ($bookingType == 'Daily') { $totalAmount = $rates['dailyRate']; }
            if ($bookingType == 'Weekly') { $totalAmount = $rates['weeklyRate']; }
            if ($bookingType == 'Monthly') { $totalAmount = $rates['monthlyRate']; }

            $sql = "INSERT INTO caregiverbooking (patientID, careGiverID, bookingType, startDate, endDate, totalAmount, status, availabilityID) VALUES (?, ?, ?, ?, ?, ?, 'Scheduled', ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iisssdi", $patientID, $careGiverID, $bookingType, $startDate, $endDateStr, $totalAmount, $availabilityID);
            $stmt->execute();
            
            $updateSql = "UPDATE caregiver_availability SET status = 'Booked' WHERE availabilityID = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("i", $availabilityID);
            $updateStmt->execute();

            $conn->commit();
            $successMsg = "Caregiver booking successful! Your booking is scheduled.";
        } else {
            throw new Exception("Selected slot is no longer available.");
        }
    } catch (Exception $e) {
        $conn->rollback();
        $errorMsg = "Booking failed: " . $e->getMessage();
    }
}

// --- Handle Booking Cancellation ---
if (isset($_GET['cancel_booking_id'])) {
    $bookingID = (int)$_GET['cancel_booking_id'];

    $findBookingStmt = $conn->prepare("SELECT availabilityID, status, careGiverID, startDate FROM caregiverbooking WHERE bookingID = ? AND patientID = ?");
    $findBookingStmt->bind_param("ii", $bookingID, $patientID);
    $findBookingStmt->execute();
    $booking = $findBookingStmt->get_result()->fetch_assoc();
    $findBookingStmt->close();

    if ($booking && in_array($booking['status'], ['Scheduled'])) {
        $conn->begin_transaction();
        try {
            $cancelStmt = $conn->prepare("UPDATE caregiverbooking SET status = 'Canceled' WHERE bookingID = ? AND patientID = ?");
            $cancelStmt->bind_param("ii", $bookingID, $patientID);
            $cancelStmt->execute();
            $cancelStmt->close();

            if ($booking['availabilityID']) {
                $releaseSlotStmt = $conn->prepare("UPDATE caregiver_availability SET status = 'Available' WHERE availabilityID = ?");
                $releaseSlotStmt->bind_param("i", $booking['availabilityID']);
                $releaseSlotStmt->execute();
                $releaseSlotStmt->close();
            }

            $conn->commit();
            $successMsg = "Booking has been successfully canceled.";
        } catch (Exception $e) {
            $conn->rollback();
            $errorMsg = "Failed to cancel booking. Please try again.";
        }
    } else {
        $errorMsg = "This booking cannot be canceled.";
    }
}

// --- Fetch ALL caregiver data ---
$caregivers = $conn->query("SELECT u.userID, u.Name, u.profilePhoto, c.* FROM users u JOIN caregiver c ON u.userID = c.careGiverID WHERE u.role = 'CareGiver'")->fetch_all(MYSQLI_ASSOC);
$availabilities = $conn->query("SELECT * FROM caregiver_availability WHERE status = 'Available' AND startDate >= CURDATE()")->fetch_all(MYSQLI_ASSOC);

// Group availabilities by caregiver
$availabilitiesByCareGiver = [];
foreach ($availabilities as $avail) {
    $availabilitiesByCareGiver[$avail['careGiverID']][] = $avail;
}

// --- Fetch patient's bookings ---
$query = "
    SELECT b.*, u.Name as careGiverName, c.careGiverType
    FROM caregiverbooking b
    JOIN caregiver c ON b.careGiverID = c.careGiverID
    JOIN users u ON c.careGiverID = u.userID
    WHERE b.patientID = ? AND b.status != 'Canceled'
    ORDER BY b.startDate DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $patientID);
$stmt->execute();
$activeBookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- Fetch patient's canceled bookings ---
$canceledQuery = "
    SELECT b.*, u.Name as careGiverName, c.careGiverType
    FROM caregiverbooking b
    JOIN caregiver c ON b.careGiverID = c.careGiverID
    JOIN users u ON c.careGiverID = u.userID
    WHERE b.patientID = ? AND b.status = 'Canceled'
    ORDER BY b.startDate DESC
";
$stmt = $conn->prepare($canceledQuery);
$stmt->bind_param("i", $patientID);
$stmt->execute();
$canceledBookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

function formatDate($date) {
    return date('M j, Y', strtotime($date));
}

function getStatusColor($status) {
    switch ($status) {
        case 'Scheduled': return 'bg-blue-100 text-blue-800';
        case 'Active': return 'bg-green-100 text-green-800';
        case 'Completed': return 'bg-gray-100 text-gray-800';
        case 'Canceled': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caregiver Bookings - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style> 
        body { font-family: 'Inter', sans-serif; } 
        .bg-dark-orchid { background-color: #9932CC; } 
        .text-dark-orchid { color: #9932CC; }
        .shadow-orchid-custom { box-shadow: 0 4px 6px -1px rgba(153, 50, 204, 0.1), 0 2px 4px -2px rgba(153, 50, 204, 0.1); }
    </style>
</head>
<body class="bg-purple-50">
    <div class="flex min-h-screen">
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
                <a href="caregiverBooking.php" class="flex items-center space-x-3 px-4 py-3 bg-purple-100 text-dark-orchid rounded-lg">
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

        <main class="flex-1 p-8">
            <!-- Header -->
            <header class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-800">Caregiver Bookings</h1>
                    <p class="text-gray-600 mt-1">Book caregivers and manage your caregiver services.</p>
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
            
            <!-- Modern Notifications -->
            <?php if ($successMsg): ?>
                <div class="alert-notification fixed top-4 right-4 z-50 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg transform translate-x-0 transition-all duration-300 ease-in-out" id="success-alert">
                    <div class="flex items-center">
                        <i class="fa-solid fa-check-circle mr-2"></i>
                        <span class="text-sm font-medium"><?php echo $successMsg; ?></span>
                        <button onclick="dismissAlert('success-alert')" class="ml-4 text-green-200 hover:text-white">
                            <i class="fa-solid fa-times"></i>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($errorMsg): ?>
                <div class="alert-notification fixed top-4 right-4 z-50 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg transform translate-x-0 transition-all duration-300 ease-in-out" id="error-alert">
                    <div class="flex items-center">
                        <i class="fa-solid fa-exclamation-circle mr-2"></i>
                        <span class="text-sm font-medium"><?php echo $errorMsg; ?></span>
                        <button onclick="dismissAlert('error-alert')" class="ml-4 text-red-200 hover:text-white">
                            <i class="fa-solid fa-times"></i>
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Tab Navigation -->
            <div class="mb-6">
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                        <button onclick="showTab('my-bookings')" id="my-bookings-tab" class="tab-button border-dark-orchid text-dark-orchid border-b-2 py-2 px-1 text-sm font-medium">
                            Active Bookings
                        </button>
                        <button onclick="showTab('canceled-bookings')" id="canceled-bookings-tab" class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 border-b-2 py-2 px-1 text-sm font-medium">
                            Canceled Bookings
                        </button>
                        <button onclick="showTab('available-caregivers')" id="available-caregivers-tab" class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 border-b-2 py-2 px-1 text-sm font-medium">
                            Available Caregivers
                        </button>
                    </nav>
                </div>
            </div>

            <!-- Active Bookings Tab -->
            <div id="my-bookings-content" class="tab-content">
                <div class="bg-white rounded-lg shadow-orchid-custom p-6 mb-8">
                    <h2 class="text-2xl font-bold text-slate-800 mb-6">My Active Caregiver Bookings</h2>
                    
                    <?php if (empty($activeBookings)): ?>
                        <div class="text-center py-12">
                            <i class="fa-solid fa-calendar-xmark fa-4x text-gray-400 mb-4"></i>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">No active bookings</h3>
                            <p class="text-gray-600 mb-6">You don't have any active caregiver bookings. Start by browsing available caregivers.</p>
                            <button onclick="showTab('available-caregivers')" class="bg-dark-orchid text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition">
                                Browse Caregivers
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($activeBookings as $booking): ?>
                                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <div class="flex items-center space-x-3 mb-2">
                                                <h3 class="text-lg font-semibold text-slate-800"><?php echo htmlspecialchars($booking['careGiverName']); ?></h3>
                                                <span class="px-2 py-1 text-xs rounded-full <?php echo getStatusColor($booking['status']); ?>">
                                                    <?php echo $booking['status']; ?>
                                                </span>
                                            </div>
                                            <p class="text-sm text-gray-600 mb-1">
                                                <i class="fa-solid fa-user-nurse mr-2"></i>
                                                <?php echo htmlspecialchars($booking['careGiverType']); ?>
                                            </p>
                                            <p class="text-sm text-gray-600 mb-1">
                                                <i class="fa-solid fa-calendar mr-2"></i>
                                                <?php echo formatDate($booking['startDate']) . ' - ' . formatDate($booking['endDate']); ?>
                                            </p>
                                            <p class="text-sm text-gray-600 mb-1">
                                                <i class="fa-solid fa-clock mr-2"></i>
                                                <?php echo $booking['bookingType']; ?> Booking
                                            </p>
                                            <p class="text-sm font-semibold text-gray-700">
                                                <i class="fa-solid fa-dollar-sign mr-2"></i>
                                                ৳<?php echo number_format($booking['totalAmount'], 2); ?>
                                            </p>
                                        </div>
                                        <div class="flex flex-col space-y-2">
                                            <?php if ($booking['status'] === 'Scheduled'): ?>
                                                <a href="?cancel_booking_id=<?php echo $booking['bookingID']; ?>" 
                                                   onclick="return confirm('Are you sure you want to cancel this booking?')" 
                                                   class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600 transition">
                                                    <i class="fa-solid fa-times mr-1"></i>Cancel
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Canceled Bookings Tab -->
            <div id="canceled-bookings-content" class="tab-content hidden">
                <div class="bg-white rounded-lg shadow-orchid-custom p-6 mb-8">
                    <h2 class="text-2xl font-bold text-slate-800 mb-6">Canceled Caregiver Bookings</h2>
                    
                    <?php if (empty($canceledBookings)): ?>
                        <div class="text-center py-12">
                            <i class="fa-solid fa-calendar-times fa-4x text-gray-400 mb-4"></i>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">No canceled bookings</h3>
                            <p class="text-gray-600">You haven't canceled any caregiver bookings.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($canceledBookings as $booking): ?>
                                <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <div class="flex items-center space-x-3 mb-2">
                                                <h3 class="text-lg font-semibold text-slate-800"><?php echo htmlspecialchars($booking['careGiverName']); ?></h3>
                                                <span class="px-2 py-1 text-xs rounded-full <?php echo getStatusColor($booking['status']); ?>">
                                                    <?php echo $booking['status']; ?>
                                                </span>
                                            </div>
                                            <p class="text-sm text-gray-600 mb-1">
                                                <i class="fa-solid fa-user-nurse mr-2"></i>
                                                <?php echo htmlspecialchars($booking['careGiverType']); ?>
                                            </p>
                                            <p class="text-sm text-gray-600 mb-1">
                                                <i class="fa-solid fa-calendar mr-2"></i>
                                                <?php echo formatDate($booking['startDate']) . ' - ' . formatDate($booking['endDate']); ?>
                                            </p>
                                            <p class="text-sm text-gray-600 mb-1">
                                                <i class="fa-solid fa-clock mr-2"></i>
                                                <?php echo $booking['bookingType']; ?> Booking
                                            </p>
                                            <p class="text-sm font-semibold text-gray-700">
                                                <i class="fa-solid fa-dollar-sign mr-2"></i>
                                                ৳<?php echo number_format($booking['totalAmount'], 2); ?>
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm text-red-600 font-medium">
                                                <i class="fa-solid fa-ban mr-1"></i>Canceled
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Available Caregivers Tab -->
            <div id="available-caregivers-content" class="tab-content hidden">
                <div class="bg-white rounded-lg shadow-orchid-custom p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold text-slate-800">Available Caregivers</h2>
                        <div class="flex items-center space-x-4">
                            <button onclick="toggleFilters()" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition">
                                <i class="fa-solid fa-filter mr-2"></i>Filters
                            </button>
                        </div>
                    </div>
                    
                    <!-- Filter Section -->
                    <div id="filter-section" class="mb-6 p-4 bg-gray-50 rounded-lg hidden">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Caregiver Type</label>
                                <select id="type-filter" class="w-full p-2 border border-gray-300 rounded-md" onchange="applyFilters()">
                                    <option value="">All Types</option>
                                    <option value="Nurse">Nurse</option>
                                    <option value="Physiotherapist">Physiotherapist</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Daily Rate Range</label>
                                <select id="rate-filter" class="w-full p-2 border border-gray-300 rounded-md" onchange="applyFilters()">
                                    <option value="">All Rates</option>
                                    <option value="0-1200">৳0 - ৳1,200</option>
                                    <option value="1200-1600">৳1,200 - ৳1,600</option>
                                    <option value="1600-2000">৳1,600+</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Availability</label>
                                <select id="availability-filter" class="w-full p-2 border border-gray-300 rounded-md" onchange="applyFilters()">
                                    <option value="">All</option>
                                    <option value="available">Available Now</option>
                                    <option value="unavailable">No Slots</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-4 flex justify-end">
                            <button onclick="clearFilters()" class="text-sm text-gray-600 hover:text-gray-800">Clear All Filters</button>
                        </div>
                    </div>
                    
                    <!-- Caregivers List -->
                    <div class="space-y-4" id="caregivers-container">
                        <?php foreach ($caregivers as $cg): ?>
                        <div class="caregiver-card border border-gray-200 rounded-lg hover:shadow-md transition-shadow" 
                             data-type="<?php echo htmlspecialchars($cg['careGiverType']); ?>" 
                             data-rate="<?php echo $cg['dailyRate']; ?>"
                             data-availability="<?php echo isset($availabilitiesByCareGiver[$cg['careGiverID']]) ? 'available' : 'unavailable'; ?>">
                            
                            <!-- Collapsed Card View -->
                            <div class="p-4 cursor-pointer" onclick="toggleCard(<?php echo $cg['careGiverID']; ?>)">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-4 flex-1">
                                        <!-- Profile Photo -->
                                        <?php if (!empty($cg['profilePhoto'])): ?>
                                            <img src="uploads/<?php echo htmlspecialchars($cg['profilePhoto']); ?>" alt="<?php echo htmlspecialchars($cg['Name']); ?>" class="w-12 h-12 rounded-full object-cover">
                                        <?php else: ?>
                                            <div class="w-12 h-12 rounded-full bg-dark-orchid text-white flex items-center justify-center font-bold">
                                                <?php echo strtoupper(substr($cg['Name'], 0, 2)); ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Basic Info -->
                                        <div class="flex-1">
                                            <div class="flex items-center space-x-3">
                                                <h3 class="text-lg font-semibold text-slate-800"><?php echo htmlspecialchars($cg['Name']); ?></h3>
                                                <span class="px-2 py-1 bg-purple-100 text-purple-800 text-xs rounded-full font-medium">
                                                    <?php echo htmlspecialchars($cg['careGiverType']); ?>
                                                </span>
                                            </div>
                                            <div class="flex items-center space-x-6 mt-1">
                                                <p class="text-sm text-gray-600">
                                                    <i class="fa-solid fa-dollar-sign mr-1"></i>
                                                    Daily: ৳<?php echo number_format($cg['dailyRate'], 0); ?>
                                                </p>
                                                <?php if (!empty($cg['certifications'])): ?>
                                                    <p class="text-sm text-gray-500">
                                                        <i class="fa-solid fa-certificate mr-1"></i>
                                                        <?php echo htmlspecialchars(substr($cg['certifications'], 0, 30)) . (strlen($cg['certifications']) > 30 ? '...' : ''); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Availability Status -->
                                        <div class="flex items-center space-x-3">
                                            <?php if (isset($availabilitiesByCareGiver[$cg['careGiverID']])): ?>
                                                <span class="px-3 py-1 bg-green-100 text-green-800 text-sm rounded-full">
                                                    <i class="fa-solid fa-calendar-check mr-1"></i>
                                                    Available
                                                </span>
                                            <?php else: ?>
                                                <span class="px-3 py-1 bg-gray-100 text-gray-600 text-sm rounded-full">
                                                    <i class="fa-solid fa-calendar-times mr-1"></i>
                                                    No Slots
                                                </span>
                                            <?php endif; ?>
                                            
                                            <!-- Expand Arrow -->
                                            <div class="expand-arrow transform transition-transform duration-200" id="arrow-<?php echo $cg['careGiverID']; ?>">
                                                <i class="fa-solid fa-chevron-down text-gray-400"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Expanded Card Content -->
                            <div id="expanded-<?php echo $cg['careGiverID']; ?>" class="expanded-content hidden border-t border-gray-200 p-4 bg-gray-50">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Left Column: Details -->
                                    <div>
                                        <h4 class="font-semibold text-gray-800 mb-3">Caregiver Details</h4>
                                        <div class="space-y-2 text-sm">
                                            <p><strong>Type:</strong> <?php echo htmlspecialchars($cg['careGiverType']); ?></p>
                                            <?php if (!empty($cg['certifications'])): ?>
                                                <p><strong>Certifications:</strong> <?php echo htmlspecialchars($cg['certifications']); ?></p>
                                            <?php endif; ?>
                                            <div class="grid grid-cols-3 gap-2 mt-3 p-3 bg-white rounded border">
                                                <div class="text-center">
                                                    <p class="text-xs text-gray-500">Daily</p>
                                                    <p class="font-semibold text-green-600">৳<?php echo number_format($cg['dailyRate'], 0); ?></p>
                                                </div>
                                                <div class="text-center">
                                                    <p class="text-xs text-gray-500">Weekly</p>
                                                    <p class="font-semibold text-blue-600">৳<?php echo number_format($cg['weeklyRate'], 0); ?></p>
                                                </div>
                                                <div class="text-center">
                                                    <p class="text-xs text-gray-500">Monthly</p>
                                                    <p class="font-semibold text-purple-600">৳<?php echo number_format($cg['monthlyRate'], 0); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Right Column: Booking -->
                                    <div>
                                        <?php if (isset($availabilitiesByCareGiver[$cg['careGiverID']])): ?>
                                            <h4 class="font-semibold text-gray-800 mb-3">Book This Caregiver</h4>
                                            <form method="POST" id="booking-form-<?php echo $cg['careGiverID']; ?>">
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Select Time Slot:</label>
                                                <select name="book_availability_id" class="w-full p-2 border border-gray-300 rounded-md mb-3" required onchange="updateBookingInfo(<?php echo $cg['careGiverID']; ?>, this.value)">
                                                    <option value="">Choose an available slot...</option>
                                                    <?php foreach ($availabilitiesByCareGiver[$cg['careGiverID']] as $slot): ?>
                                                        <option value="<?php echo $slot['availabilityID']; ?>" 
                                                                data-type="<?php echo $slot['bookingType']; ?>" 
                                                                data-date="<?php echo formatDate($slot['startDate']); ?>"
                                                                data-rate="<?php echo $cg[$slot['bookingType'] === 'Daily' ? 'dailyRate' : ($slot['bookingType'] === 'Weekly' ? 'weeklyRate' : 'monthlyRate')]; ?>">
                                                            <?php echo $slot['bookingType']; ?> - <?php echo formatDate($slot['startDate']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                
                                                <div id="booking-info-<?php echo $cg['careGiverID']; ?>" class="mb-3 p-3 bg-blue-50 rounded-lg text-sm" style="display: none;">
                                                    <div id="booking-details-<?php echo $cg['careGiverID']; ?>"></div>
                                                </div>
                                                
                                                <button type="submit" class="w-full bg-dark-orchid text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition disabled:bg-gray-400 disabled:cursor-not-allowed" 
                                                        id="book-btn-<?php echo $cg['careGiverID']; ?>" disabled>
                                                    <i class="fa-solid fa-calendar-plus mr-2"></i>Book This Caregiver
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <div class="text-center py-8">
                                                <i class="fa-solid fa-calendar-times fa-3x text-gray-400 mb-3"></i>
                                                <h4 class="font-semibold text-gray-600 mb-2">No Available Slots</h4>
                                                <p class="text-sm text-gray-500">This caregiver doesn't have any available time slots at the moment.</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- No Results Message -->
                    <div id="no-results" class="text-center py-12 hidden">
                        <i class="fa-solid fa-search fa-4x text-gray-400 mb-4"></i>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">No caregivers found</h3>
                        <p class="text-gray-600">Try adjusting your filters to see more results.</p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => content.classList.add('hidden'));
            
            // Remove active styles from all tabs
            const tabButtons = document.querySelectorAll('.tab-button');
            tabButtons.forEach(button => {
                button.classList.remove('border-dark-orchid', 'text-dark-orchid');
                button.classList.add('border-transparent', 'text-gray-500');
            });
            
            // Show selected tab content
            document.getElementById(tabName + '-content').classList.remove('hidden');
            
            // Add active styles to selected tab
            const activeTab = document.getElementById(tabName + '-tab');
            activeTab.classList.remove('border-transparent', 'text-gray-500');
            activeTab.classList.add('border-dark-orchid', 'text-dark-orchid');
        }

        function toggleFilters() {
            const filterSection = document.getElementById('filter-section');
            filterSection.classList.toggle('hidden');
        }

        function toggleCard(caregiverID) {
            const expandedContent = document.getElementById(`expanded-${caregiverID}`);
            const arrow = document.getElementById(`arrow-${caregiverID}`);
            
            if (expandedContent.classList.contains('hidden')) {
                // Close all other cards first
                document.querySelectorAll('.expanded-content').forEach(content => {
                    content.classList.add('hidden');
                });
                document.querySelectorAll('.expand-arrow').forEach(arr => {
                    arr.classList.remove('rotate-180');
                });
                
                // Open this card
                expandedContent.classList.remove('hidden');
                arrow.classList.add('rotate-180');
            } else {
                // Close this card
                expandedContent.classList.add('hidden');
                arrow.classList.remove('rotate-180');
            }
        }

        function applyFilters() {
            const typeFilter = document.getElementById('type-filter').value;
            const rateFilter = document.getElementById('rate-filter').value;
            const availabilityFilter = document.getElementById('availability-filter').value;
            
            const cards = document.querySelectorAll('.caregiver-card');
            let visibleCount = 0;
            
            cards.forEach(card => {
                let show = true;
                
                // Type filter
                if (typeFilter && card.dataset.type !== typeFilter) {
                    show = false;
                }
                
                // Rate filter
                if (rateFilter && show) {
                    const rate = parseFloat(card.dataset.rate);
                    const [min, max] = rateFilter.split('-').map(Number);
                    if (max) {
                        show = rate >= min && rate <= max;
                    } else {
                        show = rate >= min;
                    }
                }
                
                // Availability filter
                if (availabilityFilter && show) {
                    show = card.dataset.availability === availabilityFilter;
                }
                
                if (show) {
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                    // Close expanded content if card is hidden
                    const expandedContent = card.querySelector('.expanded-content');
                    if (expandedContent) {
                        expandedContent.classList.add('hidden');
                    }
                    const arrow = card.querySelector('.expand-arrow');
                    if (arrow) {
                        arrow.classList.remove('rotate-180');
                    }
                }
            });
            
            // Show/hide no results message
            const noResults = document.getElementById('no-results');
            if (visibleCount === 0) {
                noResults.classList.remove('hidden');
            } else {
                noResults.classList.add('hidden');
            }
        }

        function clearFilters() {
            document.getElementById('type-filter').value = '';
            document.getElementById('rate-filter').value = '';
            document.getElementById('availability-filter').value = '';
            applyFilters();
        }

        function updateBookingInfo(caregiverID, availabilityID) {
            const select = document.querySelector(`#booking-form-${caregiverID} select`);
            const infoDiv = document.getElementById(`booking-info-${caregiverID}`);
            const detailsP = document.getElementById(`booking-details-${caregiverID}`);
            const bookBtn = document.getElementById(`book-btn-${caregiverID}`);
            
            if (availabilityID === '') {
                infoDiv.style.display = 'none';
                bookBtn.disabled = true;
                return;
            }
            
            const selectedOption = select.options[select.selectedIndex];
            const bookingType = selectedOption.getAttribute('data-type');
            const bookingDate = selectedOption.getAttribute('data-date');
            const rate = selectedOption.getAttribute('data-rate');
            
            detailsP.innerHTML = `
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-medium text-gray-800">${bookingType} Service</p>
                        <p class="text-sm text-gray-600">Start: ${bookingDate}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-bold text-dark-orchid">৳${parseFloat(rate).toLocaleString('en-US', {minimumFractionDigits: 0})}</p>
                        <p class="text-xs text-gray-500">Total Cost</p>
                    </div>
                </div>
            `;
            
            infoDiv.style.display = 'block';
            bookBtn.disabled = false;
            
            // Add confirmation on submit
            const form = document.getElementById(`booking-form-${caregiverID}`);
            form.onsubmit = function(e) {
                return confirm(`Confirm booking for ${bookingType} service starting ${bookingDate}?\n\nTotal cost: ৳${parseFloat(rate).toLocaleString('en-US', {minimumFractionDigits: 0})}`);
            };
        }

        // Set default tab
        document.addEventListener('DOMContentLoaded', function() {
            showTab('my-bookings');
            
            // Auto-dismiss notifications after 2 seconds
            const notifications = document.querySelectorAll('.alert-notification');
            notifications.forEach(function(notification) {
                setTimeout(function() {
                    dismissAlert(notification.id);
                }, 2000);
            });
        });

        // Notification Functions
        function dismissAlert(alertId) {
            const alert = document.getElementById(alertId);
            if (alert) {
                alert.style.transform = 'translateX(100%)';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 300);
            }
        }
    </script>
</body>
</html>