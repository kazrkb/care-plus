<?php
// Redirect to the new doctor booking system
header("Location: doctorBooking.php");
exit();
?>

// Get filter parameters
$provider_type = $_GET['provider_type'] ?? 'all';
$specialty = $_GET['specialty'] ?? '';
$date_filter = $_GET['date_filter'] ?? '';

// Build base query for available schedules with providers
$query = "
    SELECT 
        s.scheduleID,
        s.providerID,
        s.availableDate,
        s.startTime,
        s.endTime,
        u.Name as providerName,
        u.role as providerRole,
        CASE 
            WHEN u.role = 'Doctor' THEN d.specialty
            WHEN u.role = 'Nutritionist' THEN n.specialty
            ELSE 'General'
        END as specialty,
        CASE 
            WHEN u.role = 'Doctor' THEN d.consultationFees
            WHEN u.role = 'Nutritionist' THEN n.consultationFees
            ELSE 0
        END as consultationFees,
        CASE 
            WHEN u.role = 'Doctor' THEN d.yearsOfExp
            WHEN u.role = 'Nutritionist' THEN n.yearsOfExp
            ELSE 0
        END as yearsOfExp,
        CASE 
            WHEN u.role = 'Doctor' THEN d.hospital
            ELSE 'Nutrition Clinic'
        END as workplace
    FROM schedule s
    INNER JOIN users u ON s.providerID = u.userID
    LEFT JOIN doctor d ON u.userID = d.doctorID AND u.role = 'Doctor'
    LEFT JOIN nutritionist n ON u.userID = n.nutritionistID AND u.role = 'Nutritionist'
    WHERE s.status = 'Available' 
    AND s.availableDate >= CURDATE()
    AND (u.role = 'Doctor' OR u.role = 'Nutritionist')
";

$params = [];
$param_types = "";

// Apply filters
if ($provider_type !== 'all') {
    if ($provider_type === 'doctor') {
        $query .= " AND u.role = 'Doctor'";
    } elseif ($provider_type === 'nutritionist') {
        $query .= " AND u.role = 'Nutritionist'";
    }
}

if (!empty($specialty)) {
    $query .= " AND ((u.role = 'Doctor' AND d.specialty LIKE ?) OR (u.role = 'Nutritionist' AND n.specialty LIKE ?))";
    $params[] = "%$specialty%";
    $params[] = "%$specialty%";
    $param_types .= "ss";
}

if (!empty($date_filter)) {
    $query .= " AND s.availableDate = ?";
    $params[] = $date_filter;
    $param_types .= "s";
}

$query .= " ORDER BY s.availableDate, s.startTime";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$schedules = $result->fetch_all(MYSQLI_ASSOC);

// Get unique specialties for filter
$specialtiesQuery = "
    SELECT DISTINCT specialty 
    FROM (
        SELECT specialty FROM doctor WHERE specialty IS NOT NULL AND specialty != ''
        UNION
        SELECT specialty FROM nutritionist WHERE specialty IS NOT NULL AND specialty != ''
    ) as specialties
    ORDER BY specialty
";
$specialtiesResult = $conn->query($specialtiesQuery);
$specialties = $specialtiesResult->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-dark-orchid { background-color: #9932CC; }
        .text-dark-orchid { color: #9932CC; }
        .shadow-orchid-custom { box-shadow: 0 4px 6px -1px rgba(153, 50, 204, 0.1), 0 2px 4px -2px rgba(153, 50, 204, 0.1); }
        .schedule-card {
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .schedule-card:hover {
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
                    <h1 class="text-3xl font-bold text-slate-800">Book Appointment</h1>
                    <p class="text-gray-600 mt-1">Schedule appointments with doctors and nutritionists</p>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="patientAppointments.php" class="bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700 transition-colors">
                        <i class="fa-solid fa-arrow-left mr-2"></i>
                        Back to Appointments
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
            <?php if (!empty($success_message)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6" role="alert">
                    <div class="flex">
                        <div class="py-1">
                            <i class="fa-solid fa-check-circle mr-2"></i>
                            <?php echo htmlspecialchars($success_message); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6" role="alert">
                    <div class="flex">
                        <div class="py-1">
                            <i class="fa-solid fa-exclamation-circle mr-2"></i>
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="bg-white p-6 rounded-lg shadow-orchid-custom mb-8">
                <h2 class="text-xl font-semibold text-slate-800 mb-4">Filter Appointments</h2>
                <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Provider Type</label>
                        <select name="provider_type" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <option value="all" <?php echo $provider_type === 'all' ? 'selected' : ''; ?>>All Providers</option>
                            <option value="doctor" <?php echo $provider_type === 'doctor' ? 'selected' : ''; ?>>Doctors</option>
                            <option value="nutritionist" <?php echo $provider_type === 'nutritionist' ? 'selected' : ''; ?>>Nutritionists</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Specialty</label>
                        <select name="specialty" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <option value="">All Specialties</option>
                            <?php foreach ($specialties as $spec): ?>
                                <option value="<?php echo htmlspecialchars($spec['specialty']); ?>" <?php echo $specialty === $spec['specialty'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($spec['specialty']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date</label>
                        <input type="date" name="date_filter" value="<?php echo htmlspecialchars($date_filter); ?>" 
                               min="<?php echo date('Y-m-d'); ?>"
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-dark-orchid text-white p-3 rounded-lg hover:bg-purple-700 transition-colors">
                            <i class="fa-solid fa-filter mr-2"></i>
                            Apply Filters
                        </button>
                    </div>
                </form>
            </div>

            <!-- Available Schedules -->
            <div class="mb-8">
                <h2 class="text-2xl font-semibold text-slate-800 mb-6">Available Time Slots</h2>
                
                <?php if (empty($schedules)): ?>
                    <div class="text-center py-12">
                        <i class="fa-solid fa-calendar-times fa-4x text-gray-400 mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-600 mb-2">No Available Slots</h3>
                        <p class="text-gray-500">Try adjusting your filters to find more appointments.</p>
                    </div>
                <?php else: ?>
                    <div class="grid gap-6">
                        <?php foreach ($schedules as $schedule): 
                            $scheduleDate = new DateTime($schedule['availableDate']);
                            $startTime = new DateTime($schedule['startTime']);
                            $endTime = new DateTime($schedule['endTime']);
                            
                            // Generate time slots (assuming 30-minute slots)
                            $timeSlots = [];
                            $currentTime = clone $startTime;
                            while ($currentTime < $endTime) {
                                $slotEnd = clone $currentTime;
                                $slotEnd->add(new DateInterval('PT30M'));
                                if ($slotEnd <= $endTime) {
                                    $timeSlots[] = [
                                        'start' => $currentTime->format('H:i'),
                                        'end' => $slotEnd->format('H:i'),
                                        'datetime' => $scheduleDate->format('Y-m-d') . ' ' . $currentTime->format('H:i:s')
                                    ];
                                }
                                $currentTime->add(new DateInterval('PT30M'));
                            }
                        ?>
                            <div class="schedule-card bg-white rounded-lg shadow-orchid-custom p-6">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="flex-1">
                                        <div class="flex items-center mb-2">
                                            <h3 class="text-xl font-semibold text-slate-800 mr-3">
                                                Dr. <?php echo htmlspecialchars($schedule['providerName']); ?>
                                            </h3>
                                            <span class="bg-purple-100 text-dark-orchid px-3 py-1 rounded-full text-xs font-medium capitalize">
                                                <?php echo strtolower($schedule['providerRole']); ?>
                                            </span>
                                        </div>
                                        <div class="grid md:grid-cols-2 gap-4 text-gray-600">
                                            <div class="flex items-center">
                                                <i class="fa-solid fa-stethoscope mr-2"></i>
                                                <span><?php echo htmlspecialchars($schedule['specialty']); ?></span>
                                            </div>
                                            <div class="flex items-center">
                                                <i class="fa-solid fa-building mr-2"></i>
                                                <span><?php echo htmlspecialchars($schedule['workplace']); ?></span>
                                            </div>
                                            <div class="flex items-center">
                                                <i class="fa-solid fa-calendar mr-2"></i>
                                                <span><?php echo $scheduleDate->format('l, F j, Y'); ?></span>
                                            </div>
                                            <div class="flex items-center">
                                                <i class="fa-solid fa-money-bill mr-2"></i>
                                                <span>৳<?php echo number_format($schedule['consultationFees'], 0); ?></span>
                                            </div>
                                            <div class="flex items-center">
                                                <i class="fa-solid fa-graduation-cap mr-2"></i>
                                                <span><?php echo $schedule['yearsOfExp']; ?> years experience</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="border-t border-gray-200 pt-4">
                                    <h4 class="font-medium text-gray-700 mb-3">Available Time Slots:</h4>
                                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 mb-4">
                                        <?php foreach ($timeSlots as $slot): ?>
                                            <button onclick="openBookingModal(<?php echo $schedule['scheduleID']; ?>, <?php echo $schedule['providerID']; ?>, '<?php echo $slot['datetime']; ?>', '<?php echo htmlspecialchars($schedule['providerName']); ?>', '<?php echo $slot['start'] . ' - ' . $slot['end']; ?>', '<?php echo $scheduleDate->format('l, F j, Y'); ?>', <?php echo $schedule['consultationFees']; ?>)" 
                                                    class="bg-blue-100 hover:bg-blue-200 text-blue-800 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                                <?php echo $slot['start']; ?> - <?php echo $slot['end']; ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Booking Modal -->
    <div id="bookingModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full m-4">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-semibold text-slate-800">Confirm Booking</h3>
                    <button onclick="closeBookingModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fa-solid fa-times fa-lg"></i>
                    </button>
                </div>
                
                <form method="POST" id="bookingForm">
                    <input type="hidden" name="action" value="book">
                    <input type="hidden" name="scheduleID" id="modalScheduleID">
                    <input type="hidden" name="providerID" id="modalProviderID">
                    <input type="hidden" name="appointmentDate" id="modalAppointmentDate">
                    
                    <div class="space-y-4">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="font-medium text-gray-700">Appointment Details:</h4>
                            <div class="mt-2 space-y-1 text-sm text-gray-600">
                                <div><strong>Doctor:</strong> <span id="modalProviderName"></span></div>
                                <div><strong>Date:</strong> <span id="modalDate"></span></div>
                                <div><strong>Time:</strong> <span id="modalTime"></span></div>
                                <div><strong>Fee:</strong> ৳<span id="modalFee"></span></div>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Notes (Optional)</label>
                            <textarea name="notes" rows="3" 
                                      placeholder="Describe your symptoms or reason for visit..."
                                      class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"></textarea>
                        </div>
                    </div>
                    
                    <div class="flex space-x-3 mt-6">
                        <button type="button" onclick="closeBookingModal()" 
                                class="flex-1 bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="flex-1 bg-dark-orchid text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition-colors">
                            Book Appointment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openBookingModal(scheduleID, providerID, appointmentDate, providerName, timeSlot, date, fee) {
            document.getElementById('modalScheduleID').value = scheduleID;
            document.getElementById('modalProviderID').value = providerID;
            document.getElementById('modalAppointmentDate').value = appointmentDate;
            document.getElementById('modalProviderName').textContent = 'Dr. ' + providerName;
            document.getElementById('modalDate').textContent = date;
            document.getElementById('modalTime').textContent = timeSlot;
            document.getElementById('modalFee').textContent = fee.toLocaleString();
            
            document.getElementById('bookingModal').classList.remove('hidden');
            document.getElementById('bookingModal').classList.add('flex');
        }
        
        function closeBookingModal() {
            document.getElementById('bookingModal').classList.add('hidden');
            document.getElementById('bookingModal').classList.remove('flex');
        }
        
        // Close modal when clicking outside
        document.getElementById('bookingModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeBookingModal();
            }
        });
    </script>
</body>
</html>
