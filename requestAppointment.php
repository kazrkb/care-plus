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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Appointment - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-dark-orchid { background-color: #9932CC; }
        .text-dark-orchid { color: #9932CC; }
        .shadow-orchid-custom { box-shadow: 0 4px 6px -1px rgba(153, 50, 204, 0.1), 0 2px 4px -2px rgba(153, 50, 204, 0.1); }
        .provider-card {
            transition: all 0.2s ease-in-out;
            cursor: pointer;
        }
        .provider-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px -5px rgba(153, 50, 204, 0.15);
        }
        .provider-card.selected {
            border-color: #9932CC;
            background-color: #f3e8ff;
        }
        .time-slot {
            transition: all 0.2s ease-in-out;
            cursor: pointer;
        }
        .time-slot:hover {
            background-color: #e5e7eb;
        }
        .time-slot.selected {
            background-color: #9932CC;
            color: white;
        }
    </style>
</head>
<body class="bg-purple-50">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="w-64 bg-white border-r">
            <div class="p-6">
                <a href="#" class="text-2xl font-bold text-dark-orchid">CarePlus</a>
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
                <a href="requestAppointment.php" class="flex items-center space-x-3 px-4 py-3 bg-purple-100 text-dark-orchid rounded-lg">
                    <i class="fa-solid fa-calendar-plus w-5"></i>
                    <span>Request Appointment</span>
                </a>
                <a href="#" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg">
                    <i class="fa-solid fa-hands-holding-child w-5"></i>
                    <span>My Caregiver Bookings</span>
                </a>
                <a href="#" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg">
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
                    <h1 class="text-3xl font-bold text-slate-800">Request New Appointment</h1>
                    <p class="text-gray-600 mt-1">Choose a healthcare provider and preferred time</p>
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

            <!-- Messages -->
            <?php if ($successMsg): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <i class="fa-solid fa-check-circle mr-2"></i><?php echo $successMsg; ?>
                <div class="mt-2">
                    <a href="patientAppointments.php" class="text-green-800 underline hover:text-green-900">
                        View your appointment requests →
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($errorMsg): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fa-solid fa-exclamation-circle mr-2"></i><?php echo $errorMsg; ?>
            </div>
            <?php endif; ?>

            <!-- Appointment Request Form -->
            <form method="POST" class="space-y-8">
                <input type="hidden" name="action" value="request_appointment">
                <input type="hidden" name="providerID" id="selectedProviderID">

                <!-- Step 1: Choose Provider -->
                <div class="bg-white p-6 rounded-lg shadow-orchid-custom">
                    <h3 class="text-xl font-bold text-slate-800 mb-6">
                        <span class="bg-dark-orchid text-white rounded-full w-8 h-8 inline-flex items-center justify-center text-sm mr-3">1</span>
                        Choose Healthcare Provider
                    </h3>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <?php foreach ($providers as $provider): ?>
                        <div class="provider-card border-2 border-gray-200 rounded-lg p-4" 
                             onclick="selectProvider(<?php echo $provider['userID']; ?>, '<?php echo htmlspecialchars($provider['Name']); ?>')">
                            <div class="flex items-start space-x-4">
                                <div class="flex-shrink-0">
                                    <?php if ($provider['role'] == 'Doctor'): ?>
                                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                            <i class="fa-solid fa-user-doctor text-blue-600 text-xl"></i>
                                        </div>
                                    <?php else: ?>
                                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                            <i class="fa-solid fa-utensils text-green-600 text-xl"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1">
                                    <h4 class="text-lg font-semibold text-slate-800"><?php echo htmlspecialchars($provider['Name']); ?></h4>
                                    <p class="text-sm text-gray-600 mb-1">
                                        <i class="fa-solid fa-stethoscope mr-1"></i>
                                        <?php echo htmlspecialchars($provider['specialty']); ?>
                                    </p>
                                    <p class="text-sm text-gray-600 mb-2">
                                        <i class="fa-solid fa-money-bill mr-1"></i>
                                        Consultation Fee: ৳<?php echo number_format($provider['consultationFees'], 0); ?>
                                    </p>
                                    <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full 
                                        <?php echo $provider['role'] == 'Doctor' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'; ?>">
                                        <?php echo $provider['role']; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Step 2: Choose Date -->
                <div class="bg-white p-6 rounded-lg shadow-orchid-custom">
                    <h3 class="text-xl font-bold text-slate-800 mb-6">
                        <span class="bg-dark-orchid text-white rounded-full w-8 h-8 inline-flex items-center justify-center text-sm mr-3">2</span>
                        Select Preferred Date
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Appointment Date</label>
                            <input type="date" name="appointmentDate" 
                                   min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                   max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>"
                                   class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500" 
                                   required>
                            <p class="text-xs text-gray-500 mt-1">Select a date up to 30 days in advance</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Preferred Time</label>
                            <select name="appointmentTime" class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500" required>
                                <option value="">Select time</option>
                                <?php foreach ($timeSlots as $slot): ?>
                                <option value="<?php echo $slot; ?>">
                                    <?php echo date('g:i A', strtotime($slot)); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Available hours: 9:00 AM - 5:00 PM</p>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Additional Notes -->
                <div class="bg-white p-6 rounded-lg shadow-orchid-custom">
                    <h3 class="text-xl font-bold text-slate-800 mb-6">
                        <span class="bg-dark-orchid text-white rounded-full w-8 h-8 inline-flex items-center justify-center text-sm mr-3">3</span>
                        Additional Information
                    </h3>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Notes (Optional)</label>
                        <textarea name="notes" rows="4" 
                                  class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                                  placeholder="Describe your health concerns or specific requirements for this appointment..."></textarea>
                        <p class="text-xs text-gray-500 mt-1">This information helps the provider prepare for your consultation</p>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-between items-center">
                    <a href="patientAppointments.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fa-solid fa-arrow-left mr-2"></i>Back to Appointments
                    </a>
                    
                    <button type="submit" id="submitBtn" 
                            class="bg-dark-orchid text-white px-8 py-3 rounded-lg font-semibold hover:bg-purple-700 transition duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                            disabled>
                        <i class="fa-solid fa-paper-plane mr-2"></i>Submit Appointment Request
                    </button>
                </div>
            </form>
        </main>
    </div>

    <script>
        let selectedProvider = null;

        function selectProvider(providerID, providerName) {
            // Remove previous selection
            document.querySelectorAll('.provider-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selection to clicked card
            event.currentTarget.classList.add('selected');
            
            // Update hidden input
            document.getElementById('selectedProviderID').value = providerID;
            selectedProvider = {id: providerID, name: providerName};
            
            checkFormValidity();
        }

        function checkFormValidity() {
            const providerSelected = selectedProvider !== null;
            const dateSelected = document.querySelector('input[name="appointmentDate"]').value !== '';
            const timeSelected = document.querySelector('select[name="appointmentTime"]').value !== '';
            
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = !(providerSelected && dateSelected && timeSelected);
        }

        // Add event listeners to form inputs
        document.querySelector('input[name="appointmentDate"]').addEventListener('change', checkFormValidity);
        document.querySelector('select[name="appointmentTime"]').addEventListener('change', checkFormValidity);

        // Form submission confirmation
        document.querySelector('form').addEventListener('submit', function(e) {
            if (!selectedProvider) {
                e.preventDefault();
                alert('Please select a healthcare provider first.');
                return;
            }
            
            const date = document.querySelector('input[name="appointmentDate"]').value;
            const time = document.querySelector('select[name="appointmentTime"]').value;
            const formattedTime = new Date(`2000-01-01 ${time}`).toLocaleString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
            
            const confirmMsg = `Confirm appointment request:\n\nProvider: ${selectedProvider.name}\nDate: ${date}\nTime: ${formattedTime}\n\nThis request will be sent for approval.`;
            
            if (!confirm(confirmMsg)) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
