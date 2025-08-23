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
                    <button onclick="toggleBookingSection()" class="bg-dark-orchid text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition">
                        <i class="fa-solid fa-plus mr-2"></i>New Booking
                    </button>
                    <div class="text-right">
                        <p class="font-semibold text-slate-700"><?php echo htmlspecialchars($userName); ?></p>
                        <p class="text-sm text-gray-500">Patient</p>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-dark-orchid text-white flex items-center justify-center font-bold text-lg">
                        <?php echo htmlspecialchars($userAvatar); ?>
                    </div>
                </div>
            </header>
            
            <?php if ($successMsg): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                    <p><?php echo $successMsg; ?></p>
                </div>
            <?php endif; ?>
            <?php if ($errorMsg): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p><?php echo $errorMsg; ?></p>
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
                    <h2 class="text-2xl font-bold text-slate-800 mb-6">Available Caregivers</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($caregivers as $cg): ?>
                        <div class="bg-white p-6 rounded-lg shadow-lg border border-gray-200 flex flex-col">
                            <div class="flex items-center space-x-3 mb-3">
                                <?php if (!empty($cg['profilePhoto'])): ?>
                                    <img src="uploads/<?php echo htmlspecialchars($cg['profilePhoto']); ?>" alt="<?php echo htmlspecialchars($cg['Name']); ?>" class="w-12 h-12 rounded-full object-cover">
                                <?php else: ?>
                                    <div class="w-12 h-12 rounded-full bg-dark-orchid text-white flex items-center justify-center font-bold">
                                        <?php echo strtoupper(substr($cg['Name'], 0, 2)); ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <h3 class="text-lg font-semibold text-slate-800"><?php echo htmlspecialchars($cg['Name']); ?></h3>
                                    <p class="text-sm text-purple-600 font-medium"><?php echo htmlspecialchars($cg['careGiverType']); ?></p>
                                </div>
                            </div>
                            
                            <?php if (!empty($cg['certifications'])): ?>
                                <div class="mb-3">
                                    <p class="text-xs text-gray-600">
                                        <i class="fa-solid fa-certificate mr-1"></i>
                                        <?php echo htmlspecialchars($cg['certifications']); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="text-sm text-gray-600 mb-4 flex-grow">
                                <div class="grid grid-cols-1 gap-1">
                                    <p><strong>Daily:</strong> ৳<?php echo number_format($cg['dailyRate'], 2); ?></p>
                                    <p><strong>Weekly:</strong> ৳<?php echo number_format($cg['weeklyRate'], 2); ?></p>
                                    <p><strong>Monthly:</strong> ৳<?php echo number_format($cg['monthlyRate'], 2); ?></p>
                                </div>
                            </div>

                            <?php if (isset($availabilitiesByCareGiver[$cg['careGiverID']])): ?>
                                <div class="mt-4">
                                    <form method="POST" id="booking-form-<?php echo $cg['careGiverID']; ?>">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Available Slots:</label>
                                        <select name="book_availability_id" class="w-full p-2 border border-gray-300 rounded-md mb-3" required onchange="updateBookingInfo(<?php echo $cg['careGiverID']; ?>, this.value)">
                                            <option value="">Select a time slot...</option>
                                            <?php foreach ($availabilitiesByCareGiver[$cg['careGiverID']] as $slot): ?>
                                                <option value="<?php echo $slot['availabilityID']; ?>" 
                                                        data-type="<?php echo $slot['bookingType']; ?>" 
                                                        data-date="<?php echo formatDate($slot['startDate']); ?>"
                                                        data-rate="<?php echo $cg[$slot['bookingType'] === 'Daily' ? 'dailyRate' : ($slot['bookingType'] === 'Weekly' ? 'weeklyRate' : 'monthlyRate')]; ?>">
                                                    <?php echo $slot['bookingType']; ?> - <?php echo formatDate($slot['startDate']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        
                                        <div id="booking-info-<?php echo $cg['careGiverID']; ?>" class="mb-3 p-2 bg-purple-50 rounded text-sm text-gray-700" style="display: none;">
                                            <p id="booking-details-<?php echo $cg['careGiverID']; ?>"></p>
                                        </div>
                                        
                                        <button type="submit" class="w-full bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 transition disabled:bg-gray-400 disabled:cursor-not-allowed" 
                                                id="book-btn-<?php echo $cg['careGiverID']; ?>" disabled>
                                            <i class="fa-solid fa-calendar-plus mr-2"></i>Book This Caregiver
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <div class="mt-4 p-3 bg-gray-100 rounded text-center">
                                    <p class="text-sm text-gray-500">
                                        <i class="fa-solid fa-calendar-times mr-2"></i>No available slots
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
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

        function toggleBookingSection() {
            showTab('available-caregivers');
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
                <strong>Booking Details:</strong><br>
                Type: ${bookingType} Service<br>
                Start Date: ${bookingDate}<br>
                Total Cost: ৳${parseFloat(rate).toLocaleString('en-US', {minimumFractionDigits: 2})}
            `;
            
            infoDiv.style.display = 'block';
            bookBtn.disabled = false;
            
            // Add confirmation on submit
            const form = document.getElementById(`booking-form-${caregiverID}`);
            form.onsubmit = function(e) {
                return confirm(`Confirm booking for ${bookingType} service starting ${bookingDate}?\nTotal cost: ৳${parseFloat(rate).toLocaleString('en-US', {minimumFractionDigits: 2})}`);
            };
        }

        // Set default tab
        document.addEventListener('DOMContentLoaded', function() {
            showTab('my-bookings');
        });
    </script>
</body>
</html>