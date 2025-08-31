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

// --- API Endpoint: Fetch available slots for a specific doctor ---
if (isset($_GET['get_slots_for'])) {
    $doctorID = (int)$_GET['get_slots_for'];
    $stmt = $conn->prepare("SELECT * FROM schedule WHERE providerID = ? AND status = 'Available' AND availableDate >= CURDATE() ORDER BY availableDate ASC, startTime ASC");
    $stmt->bind_param("i", $doctorID);
    $stmt->execute();
    $slots = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($slots);
    exit();
}

// --- Handle Appointment Booking ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['book_schedule_id'])) {
    $scheduleID = (int)$_POST['book_schedule_id'];
    $appointmentDate = $_POST['appointment_date'];
    $notes = $_POST['notes'] ?? '';

    $conn->begin_transaction();
    try {
        // Step 1: Lock and verify the chosen slot is still available
        $schQuery = "SELECT * FROM schedule WHERE scheduleID = ? AND status = 'Available' FOR UPDATE";
        $schStmt = $conn->prepare($schQuery);
        $schStmt->bind_param("i", $scheduleID);
        $schStmt->execute();
        $slotDetails = $schStmt->get_result()->fetch_assoc();

        if ($slotDetails) {
            $providerID = $slotDetails['providerID'];

            // Check for patient conflicts (same day, same provider)
            $conflictCheck = $conn->prepare("SELECT COUNT(*) AS cnt FROM appointment WHERE patientID = ? AND providerID = ? AND DATE(appointmentDate) = DATE(?) AND status IN ('Scheduled', 'Completed')");
            $conflictCheck->bind_param("iis", $patientID, $providerID, $appointmentDate);
            $conflictCheck->execute();
            $conflictResult = $conflictCheck->get_result()->fetch_assoc();

            if ($conflictResult['cnt'] > 0) {
                throw new Exception("You already have an appointment with this doctor on this date.");
            }
            $conflictCheck->close();

            // All checks passed, proceed with booking
            $sql = "INSERT INTO appointment (patientID, providerID, appointmentDate, status, scheduleID, notes) VALUES (?, ?, ?, 'Scheduled', ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iisis", $patientID, $providerID, $appointmentDate, $scheduleID, $notes);
            $stmt->execute();

            $updateSql = "UPDATE schedule SET status = 'Booked' WHERE scheduleID = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("i", $scheduleID);
            $updateStmt->execute();

            $conn->commit();
            $successMsg = "Appointment booked successfully! Your appointment is scheduled.";
        } else {
            throw new Exception("Selected time slot is no longer available.");
        }
    } catch (Exception $e) {
        $conn->rollback();
        $errorMsg = "Booking failed: " . $e->getMessage();
    }
}

// --- Handle Appointment Cancellation ---
if (isset($_GET['cancel_appointment_id'])) {
    $appointmentID = (int)$_GET['cancel_appointment_id'];

    $findAppointmentStmt = $conn->prepare("SELECT scheduleID, status FROM appointment WHERE appointmentID = ? AND patientID = ?");
    $findAppointmentStmt->bind_param("ii", $appointmentID, $patientID);
    $findAppointmentStmt->execute();
    $appointment = $findAppointmentStmt->get_result()->fetch_assoc();
    $findAppointmentStmt->close();

    if ($appointment && in_array($appointment['status'], ['Scheduled'])) {
        $conn->begin_transaction();
        try {
            $cancelStmt = $conn->prepare("UPDATE appointment SET status = 'Canceled' WHERE appointmentID = ? AND patientID = ?");
            $cancelStmt->bind_param("ii", $appointmentID, $patientID);
            $cancelStmt->execute();
            $cancelStmt->close();

            if ($appointment['scheduleID']) {
                $releaseSlotStmt = $conn->prepare("UPDATE schedule SET status = 'Available' WHERE scheduleID = ?");
                $releaseSlotStmt->bind_param("i", $appointment['scheduleID']);
                $releaseSlotStmt->execute();
                $releaseSlotStmt->close();
            }

            $conn->commit();
            $successMsg = "Appointment has been successfully canceled.";
        } catch (Exception $e) {
            $conn->rollback();
            $errorMsg = "Failed to cancel appointment. Please try again.";
        }
    } else {
        $errorMsg = "This appointment cannot be canceled.";
    }
}

// --- Fetch ALL doctor data ---
$doctors = $conn->query("SELECT u.userID, u.Name, u.profilePhoto, d.* FROM users u JOIN doctor d ON u.userID = d.doctorID WHERE u.role = 'Doctor'")->fetch_all(MYSQLI_ASSOC);
$schedules = $conn->query("SELECT * FROM schedule WHERE status = 'Available' AND availableDate >= CURDATE() ORDER BY availableDate ASC, startTime ASC")->fetch_all(MYSQLI_ASSOC);

// Group schedules by doctor
$schedulesByDoctor = [];
foreach ($schedules as $schedule) {
    $schedulesByDoctor[$schedule['providerID']][] = $schedule;
}

// --- Fetch patient's appointments ---
$query = "
    SELECT a.*, u.Name as doctorName, d.specialty, d.hospital, d.consultationFees
    FROM appointment a
    JOIN doctor d ON a.providerID = d.doctorID
    JOIN users u ON d.doctorID = u.userID
    WHERE a.patientID = ? AND a.status != 'Canceled'
    ORDER BY a.appointmentDate DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $patientID);
$stmt->execute();
$activeAppointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- Fetch patient's canceled appointments ---
$canceledQuery = "
    SELECT a.*, u.Name as doctorName, d.specialty, d.hospital, d.consultationFees
    FROM appointment a
    JOIN doctor d ON a.providerID = d.doctorID
    JOIN users u ON d.doctorID = u.userID
    WHERE a.patientID = ? AND a.status = 'Canceled'
    ORDER BY a.appointmentDate DESC
";
$stmt = $conn->prepare($canceledQuery);
$stmt->bind_param("i", $patientID);
$stmt->execute();
$canceledAppointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

function formatDate($date) {
    return date('M j, Y', strtotime($date));
}

function formatDateTime($datetime) {
    return date('M j, Y g:i A', strtotime($datetime));
}

function getStatusColor($status) {
    switch ($status) {
        case 'Scheduled': return 'bg-blue-100 text-blue-800';
        case 'Completed': return 'bg-green-100 text-green-800';
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
    <title>Doctor Appointments - CarePlus</title>
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
                <a href="caregiverBooking.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg">
                    <i class="fa-solid fa-hands-holding-child w-5"></i>
                    <span>Caregiver Bookings</span>
                </a>
                <a href="doctorBooking.php" class="flex items-center space-x-3 px-4 py-3 bg-purple-100 text-dark-orchid rounded-lg">
                    <i class="fa-solid fa-user-doctor w-5"></i>
                    <span>Doctor Appointments</span>
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
                    <h1 class="text-3xl font-bold text-slate-800">Doctor Appointments</h1>
                    <p class="text-gray-600 mt-1">Book appointments with doctors and manage your healthcare services.</p>
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
                        <button onclick="showTab('my-appointments')" id="my-appointments-tab" class="tab-button border-dark-orchid text-dark-orchid border-b-2 py-2 px-1 text-sm font-medium">
                            My Appointments
                        </button>
                        <button onclick="showTab('canceled-appointments')" id="canceled-appointments-tab" class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 border-b-2 py-2 px-1 text-sm font-medium">
                            Canceled Appointments
                        </button>
                        <button onclick="showTab('available-doctors')" id="available-doctors-tab" class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 border-b-2 py-2 px-1 text-sm font-medium">
                            Available Doctors
                        </button>
                    </nav>
                </div>
            </div>

            <!-- My Appointments Tab -->
            <div id="my-appointments-content" class="tab-content">
                <div class="bg-white rounded-lg shadow-orchid-custom p-6 mb-8">
                    <h2 class="text-2xl font-bold text-slate-800 mb-6">My Doctor Appointments</h2>

                    <?php if (empty($activeAppointments)): ?>
                        <div class="text-center py-12">
                            <i class="fa-solid fa-calendar-xmark fa-4x text-gray-400 mb-4"></i>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">No appointments</h3>
                            <p class="text-gray-600 mb-6">You don't have any doctor appointments. Start by browsing available doctors.</p>
                            <button onclick="showTab('available-doctors')" class="bg-dark-orchid text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition">
                                Browse Doctors
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($activeAppointments as $appointment): ?>
                                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <div class="flex items-center space-x-3 mb-2">
                                                <h3 class="text-lg font-semibold text-slate-800">Dr. <?php echo htmlspecialchars($appointment['doctorName']); ?></h3>
                                                <span class="px-2 py-1 text-xs rounded-full <?php echo getStatusColor($appointment['status']); ?>">
                                                    <?php echo $appointment['status']; ?>
                                                </span>
                                            </div>
                                            <p class="text-sm text-gray-600 mb-1">
                                                <i class="fa-solid fa-stethoscope mr-2"></i>
                                                <?php echo htmlspecialchars($appointment['specialty']); ?>
                                            </p>
                                            <p class="text-sm text-gray-600 mb-1">
                                                <i class="fa-solid fa-building mr-2"></i>
                                                <?php echo htmlspecialchars($appointment['hospital']); ?>
                                            </p>
                                            <p class="text-sm text-gray-600 mb-1">
                                                <i class="fa-solid fa-calendar mr-2"></i>
                                                <?php echo formatDateTime($appointment['appointmentDate']); ?>
                                            </p>
                                            <p class="text-sm font-semibold text-gray-700">
                                                <i class="fa-solid fa-dollar-sign mr-2"></i>
                                                ৳<?php echo number_format($appointment['consultationFees'], 2); ?>
                                            </p>
                                            <?php if (!empty($appointment['notes'])): ?>
                                                <p class="text-sm text-gray-600 mb-1">
                                                    <i class="fa-solid fa-sticky-note mr-2"></i>
                                                    <?php echo htmlspecialchars($appointment['notes']); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex flex-col space-y-2">
                                            <?php if ($appointment['status'] === 'Scheduled'): ?>
                                                <a href="?cancel_appointment_id=<?php echo $appointment['appointmentID']; ?>"
                                                   onclick="return confirm('Are you sure you want to cancel this appointment?')"
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

            <!-- Canceled Appointments Tab -->
            <div id="canceled-appointments-content" class="tab-content hidden">
                <div class="bg-white rounded-lg shadow-orchid-custom p-6 mb-8">
                    <h2 class="text-2xl font-bold text-slate-800 mb-6">Canceled Doctor Appointments</h2>

                    <?php if (empty($canceledAppointments)): ?>
                        <div class="text-center py-12">
                            <i class="fa-solid fa-calendar-times fa-4x text-gray-400 mb-4"></i>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">No canceled appointments</h3>
                            <p class="text-gray-600">You haven't canceled any doctor appointments.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($canceledAppointments as $appointment): ?>
                                <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <div class="flex items-center space-x-3 mb-2">
                                                <h3 class="text-lg font-semibold text-slate-800">Dr. <?php echo htmlspecialchars($appointment['doctorName']); ?></h3>
                                                <span class="px-2 py-1 text-xs rounded-full <?php echo getStatusColor($appointment['status']); ?>">
                                                    <?php echo $appointment['status']; ?>
                                                </span>
                                            </div>
                                            <p class="text-sm text-gray-600 mb-1">
                                                <i class="fa-solid fa-stethoscope mr-2"></i>
                                                <?php echo htmlspecialchars($appointment['specialty']); ?>
                                            </p>
                                            <p class="text-sm text-gray-600 mb-1">
                                                <i class="fa-solid fa-building mr-2"></i>
                                                <?php echo htmlspecialchars($appointment['hospital']); ?>
                                            </p>
                                            <p class="text-sm text-gray-600 mb-1">
                                                <i class="fa-solid fa-calendar mr-2"></i>
                                                <?php echo formatDateTime($appointment['appointmentDate']); ?>
                                            </p>
                                            <p class="text-sm font-semibold text-gray-700">
                                                <i class="fa-solid fa-dollar-sign mr-2"></i>
                                                ৳<?php echo number_format($appointment['consultationFees'], 2); ?>
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

            <!-- Available Doctors Tab -->
            <div id="available-doctors-content" class="tab-content hidden">
                <div class="bg-white rounded-lg shadow-orchid-custom p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold text-slate-800">Available Doctors</h2>
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
                                <label class="block text-sm font-medium text-gray-700 mb-2">Specialty</label>
                                <select id="specialty-filter" class="w-full p-2 border border-gray-300 rounded-md" onchange="applyFilters()">
                                    <option value="">All Specialties</option>
                                    <option value="Cardiology">Cardiology</option>
                                    <option value="Dermatology">Dermatology</option>
                                    <option value="Neurology">Neurology</option>
                                    <option value="Orthopedics">Orthopedics</option>
                                    <option value="Pediatrics">Pediatrics</option>
                                    <option value="General Medicine">General Medicine</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Consultation Fee Range</label>
                                <select id="fee-filter" class="w-full p-2 border border-gray-300 rounded-md" onchange="applyFilters()">
                                    <option value="">All Fees</option>
                                    <option value="0-1000">৳0 - ৳1,000</option>
                                    <option value="1000-2000">৳1,000 - ৳2,000</option>
                                    <option value="2000-3000">৳2,000 - ৳3,000</option>
                                    <option value="3000-5000">৳3,000+</option>
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

                    <!-- Doctors List -->
                    <div class="space-y-4" id="doctors-container">
                        <?php foreach ($doctors as $doctor): ?>
                        <div class="doctor-card border border-gray-200 rounded-lg hover:shadow-md transition-shadow"
                             data-specialty="<?php echo htmlspecialchars($doctor['specialty']); ?>"
                             data-fee="<?php echo $doctor['consultationFees']; ?>"
                             data-availability="<?php echo isset($schedulesByDoctor[$doctor['doctorID']]) ? 'available' : 'unavailable'; ?>">

                            <!-- Collapsed Card View -->
                            <div class="p-4 cursor-pointer" onclick="toggleCard(<?php echo $doctor['doctorID']; ?>)">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-4 flex-1">
                                        <!-- Profile Photo -->
                                        <?php if (!empty($doctor['profilePhoto'])): ?>
                                            <img src="uploads/<?php echo htmlspecialchars($doctor['profilePhoto']); ?>" alt="Dr. <?php echo htmlspecialchars($doctor['Name']); ?>" class="w-12 h-12 rounded-full object-cover">
                                        <?php else: ?>
                                            <div class="w-12 h-12 rounded-full bg-dark-orchid text-white flex items-center justify-center font-bold">
                                                <?php echo strtoupper(substr($doctor['Name'], 0, 2)); ?>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Basic Info -->
                                        <div class="flex-1">
                                            <div class="flex items-center space-x-3">
                                                <h3 class="text-lg font-semibold text-slate-800">Dr. <?php echo htmlspecialchars($doctor['Name']); ?></h3>
                                                <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full font-medium">
                                                    <?php echo htmlspecialchars($doctor['specialty']); ?>
                                                </span>
                                            </div>
                                            <div class="flex items-center space-x-6 mt-1">
                                                <p class="text-sm text-gray-600">
                                                    <i class="fa-solid fa-building mr-1"></i>
                                                    <?php echo htmlspecialchars($doctor['hospital']); ?>
                                                </p>
                                                <p class="text-sm text-gray-600">
                                                    <i class="fa-solid fa-dollar-sign mr-1"></i>
                                                    ৳<?php echo number_format($doctor['consultationFees'], 0); ?>
                                                </p>
                                                <?php if (!empty($doctor['yearsOfExp'])): ?>
                                                    <p class="text-sm text-gray-500">
                                                        <i class="fa-solid fa-graduation-cap mr-1"></i>
                                                        <?php echo $doctor['yearsOfExp']; ?> years exp.
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Availability Status -->
                                        <div class="flex items-center space-x-3">
                                            <?php if (isset($schedulesByDoctor[$doctor['doctorID']])): ?>
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
                                            <div class="expand-arrow transform transition-transform duration-200" id="arrow-<?php echo $doctor['doctorID']; ?>">
                                                <i class="fa-solid fa-chevron-down text-gray-400"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Expanded Card Content -->
                            <div id="expanded-<?php echo $doctor['doctorID']; ?>" class="expanded-content hidden border-t border-gray-200 p-4 bg-gray-50">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Left Column: Details -->
                                    <div>
                                        <h4 class="font-semibold text-gray-800 mb-3">Doctor Details</h4>
                                        <div class="space-y-2 text-sm">
                                            <p><strong>Specialty:</strong> <?php echo htmlspecialchars($doctor['specialty']); ?></p>
                                            <p><strong>Hospital:</strong> <?php echo htmlspecialchars($doctor['hospital']); ?></p>
                                            <?php if (!empty($doctor['yearsOfExp'])): ?>
                                                <p><strong>Experience:</strong> <?php echo $doctor['yearsOfExp']; ?> years</p>
                                            <?php endif; ?>
                                            <?php if (!empty($doctor['licNo'])): ?>
                                                <p><strong>License:</strong> <?php echo htmlspecialchars($doctor['licNo']); ?></p>
                                            <?php endif; ?>
                                            <div class="mt-3 p-3 bg-white rounded border">
                                                <div class="text-center">
                                                    <p class="text-xs text-gray-500">Consultation Fee</p>
                                                    <p class="font-semibold text-green-600 text-lg">৳<?php echo number_format($doctor['consultationFees'], 0); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Right Column: Booking -->
                                    <div>
                                        <?php if (isset($schedulesByDoctor[$doctor['doctorID']])): ?>
                                            <h4 class="font-semibold text-gray-800 mb-3">Book Appointment</h4>
                                            <form method="POST" id="booking-form-<?php echo $doctor['doctorID']; ?>">
                                                <input type="hidden" name="book_schedule_id" value="">
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Select Time Slot:</label>
                                                <select name="book_schedule_id" class="w-full p-2 border border-gray-300 rounded-md mb-3" required onchange="updateBookingInfo(<?php echo $doctor['doctorID']; ?>, this.value)">
                                                    <option value="">Choose an available slot...</option>
                                                    <?php foreach ($schedulesByDoctor[$doctor['doctorID']] as $slot): ?>
                                                        <option value="<?php echo $slot['scheduleID']; ?>"
                                                                data-date="<?php echo $slot['availableDate']; ?>"
                                                                data-start="<?php echo $slot['startTime']; ?>"
                                                                data-end="<?php echo $slot['endTime']; ?>"
                                                                data-fee="<?php echo $doctor['consultationFees']; ?>">
                                                            <?php echo formatDate($slot['availableDate']); ?> - <?php echo date('g:i A', strtotime($slot['startTime'])); ?> to <?php echo date('g:i A', strtotime($slot['endTime'])); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>

                                                <label class="block text-sm font-medium text-gray-700 mb-2">Notes (Optional):</label>
                                                <textarea name="notes" class="w-full p-2 border border-gray-300 rounded-md mb-3" rows="2" placeholder="Any special notes for the doctor..."></textarea>

                                                <div id="booking-info-<?php echo $doctor['doctorID']; ?>" class="mb-3 p-3 bg-blue-50 rounded-lg text-sm" style="display: none;">
                                                    <div id="booking-details-<?php echo $doctor['doctorID']; ?>"></div>
                                                </div>

                                                <button type="submit" class="w-full bg-dark-orchid text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition disabled:bg-gray-400 disabled:cursor-not-allowed"
                                                        id="book-btn-<?php echo $doctor['doctorID']; ?>" disabled>
                                                    <i class="fa-solid fa-calendar-plus mr-2"></i>Book Appointment
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <div class="text-center py-8">
                                                <i class="fa-solid fa-calendar-times fa-3x text-gray-400 mb-3"></i>
                                                <h4 class="font-semibold text-gray-600 mb-2">No Available Slots</h4>
                                                <p class="text-sm text-gray-500">This doctor doesn't have any available time slots at the moment.</p>
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
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">No doctors found</h3>
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

        function toggleCard(doctorID) {
            const expandedContent = document.getElementById(`expanded-${doctorID}`);
            const arrow = document.getElementById(`arrow-${doctorID}`);

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
            const specialtyFilter = document.getElementById('specialty-filter').value;
            const feeFilter = document.getElementById('fee-filter').value;
            const availabilityFilter = document.getElementById('availability-filter').value;

            const cards = document.querySelectorAll('.doctor-card');
            let visibleCount = 0;

            cards.forEach(card => {
                let show = true;

                // Specialty filter
                if (specialtyFilter && card.dataset.specialty !== specialtyFilter) {
                    show = false;
                }

                // Fee filter
                if (feeFilter && show) {
                    const fee = parseFloat(card.dataset.fee);
                    const [min, max] = feeFilter.split('-').map(Number);
                    if (max) {
                        show = fee >= min && fee <= max;
                    } else {
                        show = fee >= min;
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
            document.getElementById('specialty-filter').value = '';
            document.getElementById('fee-filter').value = '';
            document.getElementById('availability-filter').value = '';
            applyFilters();
        }

        function updateBookingInfo(doctorID, scheduleID) {
            const select = document.querySelector(`#booking-form-${doctorID} select[name="book_schedule_id"]`);
            const infoDiv = document.getElementById(`booking-info-${doctorID}`);
            const detailsP = document.getElementById(`booking-details-${doctorID}`);
            const bookBtn = document.getElementById(`book-btn-${doctorID}`);

            if (scheduleID === '') {
                infoDiv.style.display = 'none';
                bookBtn.disabled = true;
                return;
            }

            const selectedOption = select.options[select.selectedIndex];
            const bookingDate = selectedOption.getAttribute('data-date');
            const startTime = selectedOption.getAttribute('data-start');
            const endTime = selectedOption.getAttribute('data-end');
            const fee = selectedOption.getAttribute('data-fee');

            // Create appointment datetime
            const appointmentDateTime = bookingDate + ' ' + startTime;

            detailsP.innerHTML = `
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-medium text-gray-800">Appointment Details</p>
                        <p class="text-sm text-gray-600">Date: ${new Date(bookingDate).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</p>
                        <p class="text-sm text-gray-600">Time: ${new Date('1970-01-01 ' + startTime).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })} - ${new Date('1970-01-01 ' + endTime).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-bold text-dark-orchid">৳${parseFloat(fee).toLocaleString('en-US', {minimumFractionDigits: 0})}</p>
                        <p class="text-xs text-gray-500">Consultation Fee</p>
                    </div>
                </div>
            `;

            infoDiv.style.display = 'block';
            bookBtn.disabled = false;

            // Set the appointment date for form submission
            let appointmentDateInput = document.querySelector(`#booking-form-${doctorID} input[name="appointment_date"]`);
            if (!appointmentDateInput) {
                appointmentDateInput = document.createElement('input');
                appointmentDateInput.type = 'hidden';
                appointmentDateInput.name = 'appointment_date';
                document.getElementById(`booking-form-${doctorID}`).appendChild(appointmentDateInput);
            }
            appointmentDateInput.value = appointmentDateTime;

            // Add confirmation on submit
            const form = document.getElementById(`booking-form-${doctorID}`);
            form.onsubmit = function(e) {
                return confirm(`Confirm appointment booking?\n\nDate: ${new Date(bookingDate).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}\nTime: ${new Date('1970-01-01 ' + startTime).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })} - ${new Date('1970-01-01 ' + endTime).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })}\n\nConsultation Fee: ৳${parseFloat(fee).toLocaleString('en-US', {minimumFractionDigits: 0})}`);
            };
        }

        // Set default tab
        document.addEventListener('DOMContentLoaded', function() {
            showTab('my-appointments');
        });
    </script>
</body>
</html>
