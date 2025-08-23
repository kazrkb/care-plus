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

$successMsg = "";
$errorMsg = "";

// Handle appointment request submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'request_appointment') {
    $providerID = (int)$_POST['providerID'];
    $appointmentDate = $_POST['appointmentDate'];
    $appointmentTime = $_POST['appointmentTime'];
    $notes = trim($_POST['notes']);
    
    // Combine date and time
    $appointmentDateTime = $appointmentDate . ' ' . $appointmentTime;
    
    // Validate that the appointment is in the future
    if (strtotime($appointmentDateTime) <= time()) {
        $errorMsg = "Please select a future date and time for your appointment.";
    } else {
        // Check if provider exists and is available
        $providerQuery = "SELECT u.userID, u.Name, u.role FROM users u WHERE u.userID = ? AND u.role IN ('Doctor', 'Nutritionist')";
        $providerStmt = $conn->prepare($providerQuery);
        $providerStmt->bind_param("i", $providerID);
        $providerStmt->execute();
        $providerResult = $providerStmt->get_result();
        
        if ($providerResult->num_rows > 0) {
            // Insert appointment request
            $insertQuery = "INSERT INTO appointment (patientID, providerID, appointmentDate, status, notes) VALUES (?, ?, ?, 'Requested', ?)";
            $insertStmt = $conn->prepare($insertQuery);
            $insertStmt->bind_param("iiss", $userID, $providerID, $appointmentDateTime, $notes);
            
            if ($insertStmt->execute()) {
                $provider = $providerResult->fetch_assoc();
                $successMsg = "Appointment request sent successfully to " . htmlspecialchars($provider['Name']) . "! You will be notified once it's approved.";
            } else {
                $errorMsg = "Failed to submit appointment request. Please try again.";
            }
            $insertStmt->close();
        } else {
            $errorMsg = "Selected provider is not available.";
        }
        $providerStmt->close();
    }
}

// Handle appointment cancellation
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    $appointmentID = (int)$_POST['appointmentID'];
    
    // Verify that this appointment belongs to the current patient and is not already canceled
    $verifyQuery = "SELECT appointmentID, status, appointmentDate FROM Appointment WHERE appointmentID = ? AND patientID = ? AND status != 'Canceled'";
    $verifyStmt = $conn->prepare($verifyQuery);
    $verifyStmt->bind_param("ii", $appointmentID, $userID);
    $verifyStmt->execute();
    $verifyResult = $verifyStmt->get_result();
    
    if ($verifyResult->num_rows > 0) {
        $appointment = $verifyResult->fetch_assoc();
        
        // Check if appointment is in the future
        if (strtotime($appointment['appointmentDate']) > time()) {
            // Update appointment status to 'Canceled'
            $cancelQuery = "UPDATE Appointment SET status = 'Canceled' WHERE appointmentID = ?";
            $cancelStmt = $conn->prepare($cancelQuery);
            $cancelStmt->bind_param("i", $appointmentID);
            
            if ($cancelStmt->execute()) {
                $successMsg = "Appointment has been successfully canceled.";
            } else {
                $errorMsg = "Failed to cancel appointment. Please try again.";
            }
            $cancelStmt->close();
        } else {
            $errorMsg = "Cannot cancel past appointments.";
        }
    } else {
        $errorMsg = "Appointment not found or cannot be canceled.";
    }
    $verifyStmt->close();
}

// Get filter parameter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Build query based on filter
$baseQuery = "SELECT a.appointmentID, a.appointmentDate, a.status, a.consultation_link, a.notes, 
              u.Name as providerName, u.role as providerRole 
              FROM Appointment a 
              LEFT JOIN Users u ON a.providerID = u.userID 
              WHERE a.patientID = ?";

switch ($filter) {
    case 'upcoming':
        $query = $baseQuery . " AND a.appointmentDate >= NOW() AND a.status IN ('Scheduled', 'Requested') AND a.status != 'Canceled' ORDER BY a.appointmentDate ASC";
        break;
    case 'requested':
        $query = $baseQuery . " AND a.status = 'Requested' ORDER BY a.appointmentDate ASC";
        break;
    case 'past':
        $query = $baseQuery . " AND a.appointmentDate < NOW() AND a.status IN ('Completed', 'Denied') ORDER BY a.appointmentDate DESC";
        break;
    case 'canceled':
        $query = $baseQuery . " AND a.status = 'Canceled' ORDER BY a.appointmentDate DESC";
        break;
    default:
        $query = $baseQuery . " ORDER BY a.appointmentDate DESC";
}

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userID);
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get appointment counts for filter tabs
$countsQuery = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN appointmentDate >= NOW() AND status IN ('Scheduled', 'Requested') AND status != 'Canceled' THEN 1 ELSE 0 END) as upcoming,
    SUM(CASE WHEN status = 'Requested' THEN 1 ELSE 0 END) as requested,
    SUM(CASE WHEN appointmentDate < NOW() AND status IN ('Completed', 'Denied') THEN 1 ELSE 0 END) as past,
    SUM(CASE WHEN status = 'Canceled' THEN 1 ELSE 0 END) as canceled
    FROM Appointment WHERE patientID = ?";
$countsStmt = $conn->prepare($countsQuery);
$countsStmt->bind_param("i", $userID);
$countsStmt->execute();
$counts = $countsStmt->get_result()->fetch_assoc();

// Get available providers (doctors and nutritionists)
$providersQuery = "
    SELECT u.userID, u.Name, u.role, d.specialty, d.consultationFees, 'Doctor' as provider_type
    FROM users u 
    JOIN doctor d ON u.userID = d.doctorID 
    WHERE u.role = 'Doctor'
    UNION
    SELECT u.userID, u.Name, u.role, n.specialty, n.consultationFees, 'Nutritionist' as provider_type
    FROM users u 
    JOIN nutritionist n ON u.userID = n.nutritionistID 
    WHERE u.role = 'Nutritionist'
    ORDER BY provider_type, Name
";

$providersStmt = $conn->prepare($providersQuery);
$providersStmt->execute();
$providers = $providersStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$conn->close();

// Generate time slots (9 AM to 5 PM)
function generateTimeSlots() {
    $slots = [];
    for ($hour = 9; $hour <= 17; $hour++) {
        $slots[] = sprintf("%02d:00", $hour);
        if ($hour < 17) {
            $slots[] = sprintf("%02d:30", $hour);
        }
    }
    return $slots;
}

$timeSlots = generateTimeSlots();

// Helper function to format status
function getStatusBadge($status) {
    switch (strtolower($status)) {
        case 'requested':
            return '<span class="px-2 py-1 text-xs font-semibold bg-yellow-100 text-yellow-800 rounded-full">Requested</span>';
        case 'scheduled':
            return '<span class="px-2 py-1 text-xs font-semibold bg-blue-100 text-blue-800 rounded-full">Scheduled</span>';
        case 'completed':
            return '<span class="px-2 py-1 text-xs font-semibold bg-green-100 text-green-800 rounded-full">Completed</span>';
        case 'canceled':
            return '<span class="px-2 py-1 text-xs font-semibold bg-red-100 text-red-800 rounded-full">Canceled</span>';
        case 'denied':
            return '<span class="px-2 py-1 text-xs font-semibold bg-red-100 text-red-800 rounded-full">Denied</span>';
        case 'in-progress':
            return '<span class="px-2 py-1 text-xs font-semibold bg-purple-100 text-purple-800 rounded-full">In Progress</span>';
        default:
            return '<span class="px-2 py-1 text-xs font-semibold bg-gray-100 text-gray-800 rounded-full">' . htmlspecialchars($status) . '</span>';
    }
}

// Helper function to format date
function formatAppointmentDate($datetime) {
    $date = new DateTime($datetime);
    $now = new DateTime();
    $diff = $now->diff($date);
    
    if ($date > $now) {
        if ($diff->days == 0) {
            return 'Today at ' . $date->format('g:i A');
        } elseif ($diff->days == 1) {
            return 'Tomorrow at ' . $date->format('g:i A');
        } else {
            return $date->format('M j, Y \a\t g:i A');
        }
    } else {
        if ($diff->days == 0) {
            return 'Today at ' . $date->format('g:i A');
        } elseif ($diff->days == 1) {
            return 'Yesterday at ' . $date->format('g:i A');
        } else {
            return $date->format('M j, Y \a\t g:i A');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-dark-orchid { background-color: #9932CC; }
        .text-dark-orchid { color: #9932CC; }
        .shadow-orchid-custom { box-shadow: 0 4px 6px -1px rgba(153, 50, 204, 0.1), 0 2px 4px -2px rgba(153, 50, 204, 0.1); }
        .appointment-card {
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .appointment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px -5px rgba(153, 50, 204, 0.15);
        }
        .cancel-form {
            display: inline-block;
        }
        .cancel-btn:hover {
            background-color: #fef2f2;
            border-color: #fca5a5;
        }
    </style>
    <script>
        function confirmCancel(appointmentId, providerName, dateTime) {
            const confirmMsg = `Are you sure you want to cancel your appointment with ${providerName} on ${dateTime}?\n\nThis action cannot be undone.`;
            return confirm(confirmMsg);
        }

        // Auto-hide success/error messages after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert-auto-hide');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.remove();
                    }, 500);
                }, 5000);
            });
        });
    </script>
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
                <a href="patientAppointments.php" class="flex items-center space-x-3 px-4 py-3 bg-purple-100 text-dark-orchid rounded-lg">
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
                    <h1 class="text-3xl font-bold text-slate-800">My Appointments</h1>
                    <p class="text-gray-600 mt-1">View and manage your healthcare appointments</p>
                </div>
                <div class="flex items-center space-x-4">
                    <button onclick="openRequestModal()" class="bg-dark-orchid text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition">
                        <i class="fa-solid fa-calendar-plus mr-2"></i>New Appointment
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

            <!-- Messages -->
            <?php if ($successMsg): ?>
            <div class="alert-auto-hide bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <i class="fa-solid fa-check-circle mr-2"></i><?php echo $successMsg; ?>
            </div>
            <?php endif; ?>

            <?php if ($errorMsg): ?>
            <div class="alert-auto-hide bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fa-solid fa-exclamation-circle mr-2"></i><?php echo $errorMsg; ?>
            </div>
            <?php endif; ?>

            <!-- Filter Tabs -->
            <div class="mb-6">
                <div class="flex justify-between items-center mb-4">
                    <div class="border-b border-gray-200 flex-1">
                        <nav class="-mb-px flex space-x-8">
                            <a href="?filter=all" class="<?php echo $filter == 'all' ? 'border-purple-500 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                                All Appointments
                                <span class="ml-2 bg-gray-100 text-gray-900 py-0.5 px-2.5 rounded-full text-xs"><?php echo $counts['total']; ?></span>
                            </a>
                            <a href="?filter=upcoming" class="<?php echo $filter == 'upcoming' ? 'border-purple-500 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                                Upcoming
                                <span class="ml-2 bg-blue-100 text-blue-900 py-0.5 px-2.5 rounded-full text-xs"><?php echo $counts['upcoming']; ?></span>
                            </a>
                            <a href="?filter=requested" class="<?php echo $filter == 'requested' ? 'border-purple-500 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                                Pending Approval
                                <span class="ml-2 bg-yellow-100 text-yellow-900 py-0.5 px-2.5 rounded-full text-xs"><?php echo $counts['requested']; ?></span>
                            </a>
                            <a href="?filter=past" class="<?php echo $filter == 'past' ? 'border-purple-500 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                                Past
                                <span class="ml-2 bg-green-100 text-green-900 py-0.5 px-2.5 rounded-full text-xs"><?php echo $counts['past']; ?></span>
                            </a>
                            <a href="?filter=canceled" class="<?php echo $filter == 'canceled' ? 'border-purple-500 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                                Canceled
                                <span class="ml-2 bg-red-100 text-red-900 py-0.5 px-2.5 rounded-full text-xs"><?php echo $counts['canceled']; ?></span>
                            </a>
                        </nav>
                    </div>
                    <button onclick="openProviderSelectionModal()" class="bg-dark-orchid text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition ml-4">
                        <i class="fa-solid fa-plus mr-2"></i>Book Appointment
                    </button>
                </div>
            </div>

            <!-- Appointments List -->
            <?php if (empty($appointments)): ?>
            <div class="text-center py-12 bg-white rounded-lg shadow-orchid-custom">
                <i class="fa-solid fa-calendar-xmark fa-4x text-gray-400 mb-4"></i>
                <h3 class="text-lg font-semibold text-gray-600 mb-2">No appointments found</h3>
                <p class="text-gray-500">
                    <?php 
                    switch ($filter) {
                        case 'upcoming': echo "You don't have any upcoming appointments."; break;
                        case 'past': echo "You don't have any past appointments."; break;
                        case 'canceled': echo "You don't have any canceled appointments."; break;
                        default: echo "You haven't scheduled any appointments yet.";
                    }
                    ?>
                </p>
            </div>
            <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($appointments as $appointment): ?>
                <div class="appointment-card bg-white p-6 rounded-lg shadow-orchid-custom">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center space-x-3 mb-2">
                                <div class="flex items-center space-x-2">
                                    <?php if ($appointment['providerRole'] == 'Doctor'): ?>
                                        <i class="fa-solid fa-user-doctor text-blue-600"></i>
                                        <span class="text-sm text-blue-600 font-medium">Doctor</span>
                                    <?php elseif ($appointment['providerRole'] == 'Nutritionist'): ?>
                                        <i class="fa-solid fa-utensils text-green-600"></i>
                                        <span class="text-sm text-green-600 font-medium">Nutritionist</span>
                                    <?php else: ?>
                                        <i class="fa-solid fa-user-nurse text-purple-600"></i>
                                        <span class="text-sm text-purple-600 font-medium">Healthcare Provider</span>
                                    <?php endif; ?>
                                </div>
                                <?php echo getStatusBadge($appointment['status']); ?>
                            </div>
                            
                            <h3 class="text-lg font-semibold text-slate-800 mb-1">
                                <?php echo htmlspecialchars($appointment['providerName'] ?? 'Provider Not Assigned'); ?>
                            </h3>
                            
                            <div class="flex items-center space-x-4 text-gray-600 mb-3">
                                <div class="flex items-center space-x-1">
                                    <i class="fa-solid fa-calendar-days text-sm"></i>
                                    <span class="text-sm"><?php echo formatAppointmentDate($appointment['appointmentDate']); ?></span>
                                </div>
                                <div class="flex items-center space-x-1">
                                    <i class="fa-solid fa-hashtag text-sm"></i>
                                    <span class="text-sm">ID: <?php echo $appointment['appointmentID']; ?></span>
                                </div>
                            </div>

                            <?php if (!empty($appointment['notes'])): ?>
                            <div class="bg-gray-50 p-3 rounded-lg">
                                <p class="text-sm text-gray-700">
                                    <i class="fa-solid fa-sticky-note mr-1"></i>
                                    <strong>Notes:</strong> <?php echo htmlspecialchars($appointment['notes']); ?>
                                </p>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="flex flex-col space-y-2 ml-4">
                            <?php if (!empty($appointment['consultation_link']) && $appointment['status'] != 'Canceled'): ?>
                            <a href="<?php echo htmlspecialchars($appointment['consultation_link']); ?>" 
                               target="_blank"
                               class="px-4 py-2 bg-dark-orchid text-white rounded-lg text-sm font-medium hover:bg-purple-700 transition text-center">
                                <i class="fa-solid fa-video mr-1"></i>Join Consultation
                            </a>
                            <?php endif; ?>
                            
                            <?php if (strtotime($appointment['appointmentDate']) > time() && $appointment['status'] != 'Canceled'): ?>
                            <form method="POST" class="cancel-form" onsubmit="return confirmCancel('<?php echo $appointment['appointmentID']; ?>', '<?php echo htmlspecialchars($appointment['providerName'], ENT_QUOTES); ?>', '<?php echo formatAppointmentDate($appointment['appointmentDate']); ?>');">
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="appointmentID" value="<?php echo $appointment['appointmentID']; ?>">
                                <button type="submit" class="cancel-btn px-4 py-2 border border-red-300 text-red-700 rounded-lg text-sm font-medium hover:bg-red-50 transition">
                                    <i class="fa-solid fa-calendar-xmark mr-1"></i>Cancel
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Provider Selection Modal -->
    <div id="providerSelectionModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-6xl w-full max-h-[90vh] overflow-y-auto">
                <div class="p-6">
                    <!-- Modal Header -->
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold text-slate-800">Select Healthcare Provider</h2>
                        <button onclick="closeProviderSelectionModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fa-solid fa-times fa-xl"></i>
                        </button>
                    </div>

                    <!-- Filter Section -->
                    <div class="mb-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-slate-800">Available Providers</h3>
                            <button onclick="toggleProviderFilters()" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition">
                                <i class="fa-solid fa-filter mr-2"></i>Filters
                            </button>
                        </div>
                        
                        <!-- Filter Controls -->
                        <div id="provider-filter-section" class="mb-4 p-4 bg-gray-50 rounded-lg hidden">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Provider Type</label>
                                    <select id="provider-type-filter" class="w-full p-2 border border-gray-300 rounded-md" onchange="applyProviderFilters()">
                                        <option value="">All Types</option>
                                        <option value="Doctor">Doctor</option>
                                        <option value="Nutritionist">Nutritionist</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Specialty</label>
                                    <select id="specialty-filter" class="w-full p-2 border border-gray-300 rounded-md" onchange="applyProviderFilters()">
                                        <option value="">All Specialties</option>
                                        <option value="Cardiology">Cardiology</option>
                                        <option value="General Medicine">General Medicine</option>
                                        <option value="Public Health Nutrition">Public Health Nutrition</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Consultation Fee</label>
                                    <select id="fee-filter" class="w-full p-2 border border-gray-300 rounded-md" onchange="applyProviderFilters()">
                                        <option value="">All Fees</option>
                                        <option value="0-600">৳0 - ৳600</option>
                                        <option value="600-800">৳600 - ৳800</option>
                                        <option value="800-1000">৳800+</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-4 flex justify-end">
                                <button onclick="clearProviderFilters()" class="text-sm text-gray-600 hover:text-gray-800">Clear All Filters</button>
                            </div>
                        </div>
                    </div>

                    <!-- Providers List -->
                    <div class="space-y-4 max-h-96 overflow-y-auto" id="providers-container">
                        <?php foreach ($providers as $provider): ?>
                        <div class="provider-card border border-gray-200 rounded-lg hover:shadow-md transition-shadow cursor-pointer" 
                             data-type="<?php echo htmlspecialchars($provider['role']); ?>" 
                             data-specialty="<?php echo htmlspecialchars($provider['specialty']); ?>"
                             data-fee="<?php echo $provider['consultationFees']; ?>"
                             onclick="selectProviderForAppointment(<?php echo $provider['userID']; ?>, '<?php echo htmlspecialchars($provider['Name']); ?>', '<?php echo htmlspecialchars($provider['role']); ?>')">
                            
                            <!-- Provider Card -->
                            <div class="p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-4 flex-1">
                                        <!-- Provider Icon -->
                                        <div class="flex-shrink-0">
                                            <?php if ($provider['role'] == 'Doctor'): ?>
                                                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                                    <i class="fa-solid fa-user-doctor text-blue-600 fa-lg"></i>
                                                </div>
                                            <?php else: ?>
                                                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                                    <i class="fa-solid fa-utensils text-green-600 fa-lg"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Provider Info -->
                                        <div class="flex-1">
                                            <div class="flex items-center space-x-3 mb-1">
                                                <h3 class="text-lg font-semibold text-slate-800"><?php echo htmlspecialchars($provider['Name']); ?></h3>
                                                <span class="px-2 py-1 text-xs rounded-full font-medium
                                                    <?php echo $provider['role'] == 'Doctor' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'; ?>">
                                                    <?php echo htmlspecialchars($provider['role']); ?>
                                                </span>
                                            </div>
                                            <div class="flex items-center space-x-6">
                                                <p class="text-sm text-gray-600">
                                                    <i class="fa-solid fa-stethoscope mr-1"></i>
                                                    <?php echo htmlspecialchars($provider['specialty']); ?>
                                                </p>
                                                <p class="text-sm font-semibold text-gray-700">
                                                    <i class="fa-solid fa-dollar-sign mr-1"></i>
                                                    Fee: ৳<?php echo number_format($provider['consultationFees'], 0); ?>
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <!-- Selection Indicator -->
                                        <div class="provider-selection-indicator hidden">
                                            <i class="fa-solid fa-check-circle text-green-600 fa-lg"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- No Results Message -->
                    <div id="no-providers-results" class="text-center py-12 hidden">
                        <i class="fa-solid fa-search fa-4x text-gray-400 mb-4"></i>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">No providers found</h3>
                        <p class="text-gray-600">Try adjusting your filters to see more results.</p>
                    </div>
                    
                    <!-- Modal Footer -->
                    <div class="flex justify-between items-center pt-4 border-t mt-6">
                        <div id="selected-provider-info" class="text-sm text-gray-600 hidden">
                            <span id="selected-provider-details"></span>
                        </div>
                        <div class="flex space-x-3">
                            <button type="button" onclick="closeProviderSelectionModal()" 
                                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="button" onclick="proceedToAppointmentBooking()" id="proceed-btn"
                                    class="bg-dark-orchid text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
                                    disabled>
                                <i class="fa-solid fa-arrow-right mr-2"></i>Continue to Book
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Appointment Booking Modal -->
    <div id="appointmentBookingModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div class="p-6">
                    <!-- Modal Header -->
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h2 class="text-2xl font-bold text-slate-800">Book Appointment</h2>
                            <p class="text-sm text-gray-600 mt-1">with <span id="booking-provider-name" class="font-medium"></span></p>
                        </div>
                        <button onclick="closeAppointmentBookingModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fa-solid fa-times fa-xl"></i>
                        </button>
                    </div>

                    <!-- Booking Form -->
                    <form method="POST" id="appointmentForm" class="space-y-6">
                        <input type="hidden" name="action" value="request_appointment">
                        <input type="hidden" name="providerID" id="booking-providerID">

                        <!-- Provider Summary -->
                        <div class="bg-purple-50 p-4 rounded-lg">
                            <div class="flex items-center space-x-3">
                                <div id="booking-provider-icon"></div>
                                <div>
                                    <h3 class="font-semibold text-slate-800" id="booking-provider-title"></h3>
                                    <p class="text-sm text-gray-600" id="booking-provider-specialty"></p>
                                    <p class="text-sm font-medium text-gray-700">Consultation Fee: ৳<span id="booking-provider-fee"></span></p>
                                </div>
                            </div>
                        </div>

                        <!-- Date & Time Selection -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Appointment Date</label>
                                <input type="date" name="appointmentDate" 
                                       min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                       max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>"
                                       class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500" 
                                       required onchange="checkAppointmentFormValidity()">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Preferred Time</label>
                                <select name="appointmentTime" class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500" required onchange="checkAppointmentFormValidity()">
                                    <option value="">Select time</option>
                                    <?php foreach ($timeSlots as $slot): ?>
                                    <option value="<?php echo $slot; ?>">
                                        <?php echo date('g:i A', strtotime($slot)); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Additional Notes (Optional)</label>
                            <textarea name="notes" rows="3" 
                                      class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                                      placeholder="Describe your health concerns or specific requirements..."></textarea>
                        </div>

                        <!-- Modal Footer -->
                        <div class="flex justify-between pt-4 border-t">
                            <button type="button" onclick="backToProviderSelection()" 
                                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                                <i class="fa-solid fa-arrow-left mr-2"></i>Back
                            </button>
                            <button type="submit" id="submit-appointment-btn" 
                                    class="bg-dark-orchid text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
                                    disabled>
                                <i class="fa-solid fa-paper-plane mr-2"></i>Submit Request
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        let selectedProvider = null;

        // Provider Selection Modal Functions
        function openProviderSelectionModal() {
            document.getElementById('providerSelectionModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            clearProviderFilters();
        }

        function closeProviderSelectionModal() {
            document.getElementById('providerSelectionModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
            resetProviderSelection();
        }

        function toggleProviderFilters() {
            const filterSection = document.getElementById('provider-filter-section');
            filterSection.classList.toggle('hidden');
        }

        function selectProviderForAppointment(providerID, providerName, providerRole) {
            // Remove previous selections
            document.querySelectorAll('.provider-card').forEach(card => {
                card.classList.remove('bg-purple-50', 'border-purple-500');
                card.classList.add('border-gray-200');
                card.querySelector('.provider-selection-indicator').classList.add('hidden');
            });
            
            // Add selection to clicked card
            const clickedCard = event.currentTarget;
            clickedCard.classList.add('bg-purple-50', 'border-purple-500');
            clickedCard.classList.remove('border-gray-200');
            clickedCard.querySelector('.provider-selection-indicator').classList.remove('hidden');
            
            // Store selected provider
            const specialty = clickedCard.dataset.specialty;
            const fee = clickedCard.dataset.fee;
            
            selectedProvider = {
                id: providerID,
                name: providerName,
                role: providerRole,
                specialty: specialty,
                fee: fee
            };
            
            // Update UI
            const info = document.getElementById('selected-provider-info');
            const details = document.getElementById('selected-provider-details');
            details.innerHTML = `Selected: <strong>${providerName}</strong> (${providerRole})`;
            info.classList.remove('hidden');
            
            document.getElementById('proceed-btn').disabled = false;
        }

        function proceedToAppointmentBooking() {
            if (!selectedProvider) return;
            
            // Close provider selection modal
            closeProviderSelectionModal();
            
            // Populate appointment booking modal
            document.getElementById('booking-providerID').value = selectedProvider.id;
            document.getElementById('booking-provider-name').textContent = selectedProvider.name;
            document.getElementById('booking-provider-title').textContent = selectedProvider.name;
            document.getElementById('booking-provider-specialty').textContent = selectedProvider.specialty;
            document.getElementById('booking-provider-fee').textContent = parseFloat(selectedProvider.fee).toLocaleString('en-US', {minimumFractionDigits: 0});
            
            // Set provider icon
            const iconContainer = document.getElementById('booking-provider-icon');
            if (selectedProvider.role === 'Doctor') {
                iconContainer.innerHTML = '<div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center"><i class="fa-solid fa-user-doctor text-blue-600"></i></div>';
            } else {
                iconContainer.innerHTML = '<div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center"><i class="fa-solid fa-utensils text-green-600"></i></div>';
            }
            
            // Open appointment booking modal
            document.getElementById('appointmentBookingModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function backToProviderSelection() {
            document.getElementById('appointmentBookingModal').classList.add('hidden');
            document.getElementById('providerSelectionModal').classList.remove('hidden');
        }

        function closeAppointmentBookingModal() {
            document.getElementById('appointmentBookingModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
            
            // Reset form
            document.getElementById('appointmentForm').reset();
            checkAppointmentFormValidity();
        }

        function resetProviderSelection() {
            selectedProvider = null;
            document.getElementById('selected-provider-info').classList.add('hidden');
            document.getElementById('proceed-btn').disabled = true;
            
            document.querySelectorAll('.provider-card').forEach(card => {
                card.classList.remove('bg-purple-50', 'border-purple-500');
                card.classList.add('border-gray-200');
                card.querySelector('.provider-selection-indicator').classList.add('hidden');
            });
        }

        // Filter Functions
        function applyProviderFilters() {
            const typeFilter = document.getElementById('provider-type-filter').value;
            const specialtyFilter = document.getElementById('specialty-filter').value;
            const feeFilter = document.getElementById('fee-filter').value;
            
            const cards = document.querySelectorAll('.provider-card');
            let visibleCount = 0;
            
            cards.forEach(card => {
                let show = true;
                
                // Type filter
                if (typeFilter && card.dataset.type !== typeFilter) {
                    show = false;
                }
                
                // Specialty filter
                if (specialtyFilter && show && card.dataset.specialty !== specialtyFilter) {
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
                
                if (show) {
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                    // Reset selection if hidden card was selected
                    if (card.classList.contains('bg-purple-50')) {
                        resetProviderSelection();
                    }
                }
            });
            
            // Show/hide no results message
            const noResults = document.getElementById('no-providers-results');
            if (visibleCount === 0) {
                noResults.classList.remove('hidden');
            } else {
                noResults.classList.add('hidden');
            }
        }

        function clearProviderFilters() {
            document.getElementById('provider-type-filter').value = '';
            document.getElementById('specialty-filter').value = '';
            document.getElementById('fee-filter').value = '';
            applyProviderFilters();
        }

        function checkAppointmentFormValidity() {
            const dateSelected = document.querySelector('input[name="appointmentDate"]').value !== '';
            const timeSelected = document.querySelector('select[name="appointmentTime"]').value !== '';
            
            const submitBtn = document.getElementById('submit-appointment-btn');
            submitBtn.disabled = !(dateSelected && timeSelected);
        }

        // Event Listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-hide success/error messages after 5 seconds
            const alerts = document.querySelectorAll('.alert-auto-hide');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.remove();
                    }, 500);
                }, 5000);
            });
        });

        // Close modals when clicking outside
        document.getElementById('providerSelectionModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeProviderSelectionModal();
            }
        });

        document.getElementById('appointmentBookingModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAppointmentBookingModal();
            }
        });

        function confirmCancel(appointmentId, providerName, dateTime) {
            const confirmMsg = `Are you sure you want to cancel your appointment with ${providerName} on ${dateTime}?\n\nThis action cannot be undone.`;
            return confirm(confirmMsg);
        }

        // Legacy function for backward compatibility
        function openRequestModal() {
            openProviderSelectionModal();
        }
    </script>
</body>
</html>
