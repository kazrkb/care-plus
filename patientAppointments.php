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
$userAvatar = strtoupper(substr($userName, 0, 2));

// Handle appointment cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    $appointmentID = $_POST['appointmentID'];
    
    // Update appointment status to canceled
    $cancelQuery = "UPDATE appointment SET status = 'Canceled' WHERE appointmentID = ? AND patientID = ?";
    $cancelStmt = $conn->prepare($cancelQuery);
    $cancelStmt->bind_param("ii", $appointmentID, $userID);
    
    if ($cancelStmt->execute()) {
        // Update associated schedule back to available if it exists
        $updateScheduleQuery = "UPDATE schedule s 
                               INNER JOIN appointment a ON s.scheduleID = a.scheduleID 
                               SET s.status = 'Available' 
                               WHERE a.appointmentID = ?";
        $updateScheduleStmt = $conn->prepare($updateScheduleQuery);
        $updateScheduleStmt->bind_param("i", $appointmentID);
        $updateScheduleStmt->execute();
        
        $success_message = "Appointment canceled successfully.";
    } else {
        $error_message = "Failed to cancel appointment.";
    }
}

// Get all appointments for the patient
$appointmentsQuery = "
    SELECT 
        a.appointmentID,
        a.appointmentDate,
        a.status,
        a.consultation_link,
        a.notes,
        u.Name as providerName,
        u.role as providerRole,
        CASE 
            WHEN u.role = 'Doctor' THEN d.specialty
            WHEN u.role = 'Nutritionist' THEN n.specialty
            ELSE 'N/A'
        END as specialty,
        CASE 
            WHEN u.role = 'Doctor' THEN d.consultationFees
            WHEN u.role = 'Nutritionist' THEN n.consultationFees
            ELSE 0
        END as consultationFees
    FROM appointment a
    INNER JOIN users u ON a.providerID = u.userID
    LEFT JOIN doctor d ON u.userID = d.doctorID AND u.role = 'Doctor'
    LEFT JOIN nutritionist n ON u.userID = n.nutritionistID AND u.role = 'Nutritionist'
    WHERE a.patientID = ?
    ORDER BY a.appointmentDate DESC
";

$appointmentsStmt = $conn->prepare($appointmentsQuery);
$appointmentsStmt->bind_param("i", $userID);
$appointmentsStmt->execute();
$appointmentsResult = $appointmentsStmt->get_result();
$appointments = $appointmentsResult->fetch_all(MYSQLI_ASSOC);

$conn->close();
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
            box-shadow: 0 8px 25px -5px rgba(0, 0, 0, 0.1);
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
                    <p class="text-gray-600 mt-1">Manage your appointments with doctors and nutritionists</p>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="bookAppointment.php" class="bg-dark-orchid text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition-colors">
                        <i class="fa-solid fa-plus mr-2"></i>
                        Book New Appointment
                    </a>
                    <div class="text-right">
                        <p class="font-semibold text-slate-700"><?php echo htmlspecialchars($userName); ?></p>
                        <p class="text-sm text-gray-500">Patient</p>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-dark-orchid text-white flex items-center justify-center font-bold text-lg">
                        <?php echo htmlspecialchars($userAvatar); ?>
                    </div>
                </div>
            </header>

            <!-- Success/Error Messages -->
            <?php if (isset($success_message)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6" role="alert">
                    <div class="flex">
                        <div class="py-1">
                            <i class="fa-solid fa-check-circle mr-2"></i>
                            <?php echo htmlspecialchars($success_message); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6" role="alert">
                    <div class="flex">
                        <div class="py-1">
                            <i class="fa-solid fa-exclamation-circle mr-2"></i>
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Appointment Tabs -->
            <div class="mb-6">
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                        <button onclick="showTab('upcoming')" id="upcoming-tab" class="border-dark-orchid text-dark-orchid whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                            Upcoming
                        </button>
                        <button onclick="showTab('completed')" id="completed-tab" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                            Completed
                        </button>
                        <button onclick="showTab('canceled')" id="canceled-tab" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                            Canceled
                        </button>
                    </nav>
                </div>
            </div>

            <!-- Appointments Grid -->
            <div id="appointments-container">
                <?php if (empty($appointments)): ?>
                    <div class="text-center py-12">
                        <i class="fa-solid fa-calendar-xmark fa-4x text-gray-400 mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-600 mb-2">No Appointments Found</h3>
                        <p class="text-gray-500 mb-6">You haven't booked any appointments yet.</p>
                        <a href="bookAppointment.php" class="bg-dark-orchid text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition-colors">
                            <i class="fa-solid fa-plus mr-2"></i>
                            Book Your First Appointment
                        </a>
                    </div>
                <?php else: ?>
                    <div class="grid gap-6" id="appointments-grid">
                        <?php foreach ($appointments as $appointment): 
                            $statusClass = '';
                            $statusIcon = '';
                            switch($appointment['status']) {
                                case 'Scheduled':
                                    $statusClass = 'bg-blue-100 text-blue-800';
                                    $statusIcon = 'fa-clock';
                                    break;
                                case 'Completed':
                                    $statusClass = 'bg-green-100 text-green-800';
                                    $statusIcon = 'fa-check-circle';
                                    break;
                                case 'Canceled':
                                    $statusClass = 'bg-red-100 text-red-800';
                                    $statusIcon = 'fa-times-circle';
                                    break;
                                case 'Denied':
                                    $statusClass = 'bg-gray-100 text-gray-800';
                                    $statusIcon = 'fa-ban';
                                    break;
                                default:
                                    $statusClass = 'bg-gray-100 text-gray-800';
                                    $statusIcon = 'fa-question-circle';
                            }
                            
                            $appointmentDateTime = new DateTime($appointment['appointmentDate']);
                            $isUpcoming = $appointmentDateTime > new DateTime() && $appointment['status'] === 'Scheduled';
                            $isPast = $appointmentDateTime <= new DateTime();
                        ?>
                            <div class="appointment-card bg-white rounded-lg shadow-orchid-custom p-6 appointment-item" data-status="<?php echo strtolower($appointment['status']); ?>">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="flex-1">
                                        <div class="flex items-center mb-2">
                                            <h3 class="text-xl font-semibold text-slate-800 mr-3">
                                                <?php echo htmlspecialchars($appointment['providerName']); ?>
                                            </h3>
                                            <span class="<?php echo $statusClass; ?> px-3 py-1 rounded-full text-xs font-medium">
                                                <i class="fa-solid <?php echo $statusIcon; ?> mr-1"></i>
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                        </div>
                                        <div class="flex items-center text-gray-600 mb-2">
                                            <i class="fa-solid fa-user-md mr-2"></i>
                                            <span class="capitalize"><?php echo strtolower($appointment['providerRole']); ?></span>
                                            <?php if ($appointment['specialty']): ?>
                                                <span class="mx-2">•</span>
                                                <span><?php echo htmlspecialchars($appointment['specialty']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex items-center text-gray-600 mb-2">
                                            <i class="fa-solid fa-calendar mr-2"></i>
                                            <span><?php echo $appointmentDateTime->format('l, F j, Y'); ?></span>
                                            <span class="mx-2">•</span>
                                            <i class="fa-solid fa-clock mr-1"></i>
                                            <span><?php echo $appointmentDateTime->format('g:i A'); ?></span>
                                        </div>
                                        <?php if ($appointment['consultationFees']): ?>
                                            <div class="flex items-center text-gray-600">
                                                <i class="fa-solid fa-money-bill mr-2"></i>
                                                <span>৳<?php echo number_format($appointment['consultationFees'], 0); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if ($appointment['notes']): ?>
                                    <div class="bg-gray-50 p-4 rounded-lg mb-4">
                                        <h4 class="font-medium text-gray-700 mb-2">Notes:</h4>
                                        <p class="text-gray-600 text-sm"><?php echo htmlspecialchars($appointment['notes']); ?></p>
                                    </div>
                                <?php endif; ?>

                                <div class="flex justify-between items-center pt-4 border-t border-gray-200">
                                    <div class="flex space-x-3">
                                        <?php if ($appointment['consultation_link'] && $appointment['status'] === 'Scheduled'): ?>
                                            <a href="<?php echo htmlspecialchars($appointment['consultation_link']); ?>" 
                                               target="_blank"
                                               class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 transition-colors">
                                                <i class="fa-solid fa-video mr-2"></i>
                                                Join Meeting
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($appointment['status'] === 'Completed'): ?>
                                            <a href="patientMedicalHistory.php#prescriptions" 
                                               class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 transition-colors">
                                                <i class="fa-solid fa-prescription mr-2"></i>
                                                View Prescription
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($appointment['status'] === 'Scheduled' && !$isPast): ?>
                                        <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to cancel this appointment?');">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="appointmentID" value="<?php echo $appointment['appointmentID']; ?>">
                                            <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-red-700 transition-colors">
                                                <i class="fa-solid fa-times mr-2"></i>
                                                Cancel
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function showTab(tabName) {
            // Update tab buttons
            document.querySelectorAll('[id$="-tab"]').forEach(tab => {
                tab.classList.remove('border-dark-orchid', 'text-dark-orchid');
                tab.classList.add('border-transparent', 'text-gray-500');
            });
            
            document.getElementById(tabName + '-tab').classList.remove('border-transparent', 'text-gray-500');
            document.getElementById(tabName + '-tab').classList.add('border-dark-orchid', 'text-dark-orchid');
            
            // Filter appointments
            const appointments = document.querySelectorAll('.appointment-item');
            appointments.forEach(appointment => {
                const status = appointment.getAttribute('data-status');
                
                if (tabName === 'upcoming' && (status === 'scheduled')) {
                    appointment.style.display = 'block';
                } else if (tabName === 'completed' && status === 'completed') {
                    appointment.style.display = 'block';
                } else if (tabName === 'canceled' && (status === 'canceled' || status === 'denied')) {
                    appointment.style.display = 'block';
                } else {
                    appointment.style.display = 'none';
                }
            });
            
            // Check if any appointments are visible
            const visibleAppointments = Array.from(appointments).filter(app => app.style.display !== 'none');
            const container = document.getElementById('appointments-container');
            
            if (visibleAppointments.length === 0) {
                let message = '';
                switch(tabName) {
                    case 'upcoming':
                        message = 'No upcoming appointments';
                        break;
                    case 'completed':
                        message = 'No completed appointments';
                        break;
                    case 'canceled':
                        message = 'No canceled appointments';
                        break;
                }
                
                if (!document.querySelector('.no-appointments-message')) {
                    const noAppointmentsDiv = document.createElement('div');
                    noAppointmentsDiv.className = 'no-appointments-message text-center py-12';
                    noAppointmentsDiv.innerHTML = `
                        <i class="fa-solid fa-calendar-xmark fa-4x text-gray-400 mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-600 mb-2">${message}</h3>
                    `;
                    container.appendChild(noAppointmentsDiv);
                }
            } else {
                const noAppointmentsMessage = document.querySelector('.no-appointments-message');
                if (noAppointmentsMessage) {
                    noAppointmentsMessage.remove();
                }
            }
        }
        
        // Initialize with upcoming appointments
        document.addEventListener('DOMContentLoaded', function() {
            showTab('upcoming');
        });
    </script>
</body>
</html>
