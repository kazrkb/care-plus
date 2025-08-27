<?php
session_start();
$conn = require_once 'config.php';
date_default_timezone_set('Asia/Dhaka');

// Protect this page
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Patient') {
    header("Location: login.php");
    exit();
}

$patientID = $_SESSION['userID'];
$userName = $_SESSION['Name'];
$userAvatar = strtoupper(substr($userName, 0, 2));
$successMsg = "";
$errorMsg = "";

// --- Handle Booking Submission ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['book_appointment'])) {
    
    // --- FIXED: Check if a schedule slot was selected first ---
    if (!isset($_POST['scheduleID'])) {
        $errorMsg = "Please select an available time slot before booking.";
    } else {
        $providerID = (int)$_POST['providerID'];
        $scheduleID = (int)$_POST['scheduleID'];
        $notes = trim($_POST['notes']);

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT availableDate, startTime FROM schedule WHERE scheduleID = ? AND status = 'Available' FOR UPDATE");
            $stmt->bind_param("i", $scheduleID);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("This time slot is no longer available. Please select another.");
            }
            $scheduleDetails = $result->fetch_assoc();
            $stmt->close();

            $stmt = $conn->prepare("UPDATE schedule SET status = 'Booked' WHERE scheduleID = ?");
            $stmt->bind_param("i", $scheduleID);
            $stmt->execute();
            $stmt->close();
            
            $appointmentDateTime = $scheduleDetails['availableDate'] . ' ' . $scheduleDetails['startTime'];
            $stmt = $conn->prepare("INSERT INTO appointment (patientID, providerID, appointmentDate, status, scheduleID, notes) VALUES (?, ?, ?, 'Scheduled', ?, ?)");
            $stmt->bind_param("iisis", $patientID, $providerID, $appointmentDateTime, $scheduleID, $notes);
            $stmt->execute();
            $newAppointmentID = $stmt->insert_id;
            $stmt->close();

            $conn->commit();
            
            $_SESSION['pending_appointment_id'] = $newAppointmentID;
            header("Location: payment.php");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $errorMsg = $e->getMessage();
        }
    }
}


// --- Fetch all Doctors and Nutritionists (with filtering) ---
$baseQuery = "SELECT u.userID, u.Name, u.role, u.profilePhoto, COALESCE(d.specialty, n.specialty) as specialty, COALESCE(d.consultationFees, n.consultationFees) as fees FROM users u LEFT JOIN doctor d ON u.userID = d.doctorID LEFT JOIN nutritionist n ON u.userID = n.nutritionistID";
$whereClauses = ["u.role IN ('Doctor', 'Nutritionist')", "u.verification_status = 'Approved'"];
$params = [];
$types = "";

$specialtyFilter = $_GET['specialty'] ?? '';
$dateFilter = $_GET['date'] ?? '';

if (!empty($specialtyFilter)) {
    $whereClauses[] = "(d.specialty LIKE ? OR n.specialty LIKE ?)";
    $params[] = "%" . $specialtyFilter . "%";
    $params[] = "%" . $specialtyFilter . "%";
    $types .= "ss";
}
if (!empty($dateFilter)) {
    $baseQuery .= " JOIN schedule s ON u.userID = s.providerID ";
    $whereClauses[] = "s.availableDate = ? AND s.status = 'Available'";
    $params[] = $dateFilter;
    $types .= "s";
}

$query = $baseQuery . " WHERE " . implode(" AND ", $whereClauses) . " GROUP BY u.userID ORDER BY u.Name ASC";
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$providers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$doctors = array_filter($providers, fn($p) => $p['role'] === 'Doctor');
$nutritionists = array_filter($providers, fn($p) => $p['role'] === 'Nutritionist');

function generateProviderCards($providers) {
    if (empty($providers)) { return '<p class="text-center text-gray-500 py-10 col-span-full">No providers found matching your criteria.</p>'; }
    $html = '';
    foreach($providers as $provider) {
        $html .= '<div class="provider-card bg-white p-5 rounded-lg shadow-md border flex flex-col">';
        $html .= '<div class="flex items-center space-x-4 mb-4">';
        if(!empty($provider['profilePhoto'])) {
            $html .= '<img src="'.htmlspecialchars($provider['profilePhoto']).'" class="w-16 h-16 rounded-full object-cover">';
        } else {
            $html .= '<div class="w-16 h-16 rounded-full bg-dark-orchid text-white flex items-center justify-center font-bold text-2xl">'.htmlspecialchars(strtoupper(substr($provider['Name'], 0, 2))).'</div>';
        }
        $html .= '<div><h3 class="text-lg font-bold text-slate-800">'.htmlspecialchars($provider['Name']).'</h3><p class="text-sm text-gray-500">'.htmlspecialchars($provider['role']).'</p></div>';
        $html .= '</div>';
        $html .= '<div class="text-sm space-y-2 flex-grow"><p class="text-gray-700"><i class="fa-solid fa-stethoscope w-5 text-gray-400 mr-1"></i><span class="font-semibold">Specialty:</span> '.htmlspecialchars($provider['specialty'] ?? 'N/A').'</p>';
        $html .= '<p class="text-gray-700"><i class="fa-solid fa-money-bill-wave w-5 text-gray-400 mr-1"></i><span class="font-semibold">Fees:</span> ৳'.htmlspecialchars(number_format($provider['fees'] ?? 0, 2)).'</p></div>';
        $html .= '<button onclick="viewProviderDetails('.$provider['userID'].')" class="w-full mt-4 py-2 px-4 bg-dark-orchid text-white rounded-md font-semibold hover:bg-purple-700 transition">View Profile & Schedule</button>';
        $html .= '</div>';
    }
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Book Appointment - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-dark-orchid { background-color: #9932CC; }
        .text-dark-orchid { color: #9932CC; }
        .provider-card { transition: transform 0.2s, box-shadow 0.2s; }
        .provider-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(153,50,204,0.1); }
        .tab-button { border-bottom: 3px solid transparent; }
        .tab-button.active { border-bottom-color: #9932CC; color: #9932CC; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex min-h-screen">
        <aside class="w-64 bg-white border-r">
            <div class="p-6"><a href="#" class="text-2xl font-bold text-dark-orchid">CarePlus</a></div>
            <nav class="px-4 space-y-2">
                <a href="patientDashboard.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-table-columns w-5"></i><span>Dashboard</span></a>
                <a href="patientProfile.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-regular fa-user w-5"></i><span>My Profile</span></a>
                <a href="find_provider.php" class="flex items-center space-x-3 px-4 py-3 bg-purple-100 text-dark-orchid rounded-lg"><i class="fa-solid fa-calendar-plus w-5"></i><span>Book Appointment</span></a>
                <a href="patientAppointments.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-calendar-days w-5"></i><span>My Appointments</span></a>
                <a href="caregiverBooking.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-hands-holding-child w-5"></i><span>Caregiver Bookings</span></a>
                <a href="upload_medical_history.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-file-medical w-5"></i><span>Medical History</span></a>
                <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg mt-8"><i class="fa-solid fa-arrow-right-from-bracket w-5"></i><span>Logout</span></a>
            </nav>
        </aside>

        <main class="flex-1 p-8">
            <header class="flex justify-between items-center mb-8">
                <div><h1 class="text-3xl font-bold text-slate-800">Find a Provider</h1><p class="text-gray-600 mt-1">Browse and book appointments with our specialists.</p></div>
            </header>
            
            <?php if ($successMsg): ?><div class="mb-6 p-4 bg-green-100 text-green-800 rounded-md"><?php echo $successMsg; ?></div><?php endif; ?>
            <?php if ($errorMsg): ?><div class="mb-6 p-4 bg-red-100 text-red-800 rounded-md"><?php echo $errorMsg; ?></div><?php endif; ?>

            <div class="bg-white p-4 rounded-lg shadow-md mb-6">
                <form method="GET" class="flex flex-wrap items-end gap-4">
                    <div><label class="text-sm font-medium">Filter by Specialty</label><input type="text" name="specialty" value="<?php echo htmlspecialchars($specialtyFilter); ?>" placeholder="e.g., Cardiology" class="w-full mt-1 p-2 border rounded-md"></div>
                    <div><label class="text-sm font-medium">Available on Date</label><input type="date" name="date" value="<?php echo htmlspecialchars($dateFilter); ?>" class="w-full mt-1 p-2 border rounded-md"></div>
                    <button type="submit" class="px-4 py-2 bg-dark-orchid text-white rounded-md font-semibold">Filter</button>
                    <a href="find_provider.php" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md">Clear</a>
                </form>
            </div>
            
            <div class="border-b border-gray-200">
                <nav class="flex space-x-8 -mb-px">
                    <button onclick="openTab(event, 'doctors')" class="tab-button active whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Doctors (<?php echo count($doctors); ?>)</button>
                    <button onclick="openTab(event, 'nutritionists')" class="tab-button whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Nutritionists (<?php echo count($nutritionists); ?>)</button>
                </nav>
            </div>

            <div id="doctors" class="tab-panel mt-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"><?php echo generateProviderCards($doctors); ?></div>
            <div id="nutritionists" class="tab-panel mt-6 hidden grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"><?php echo generateProviderCards($nutritionists); ?></div>
        </main>
    </div>

    <div id="detailsModal" class="fixed inset-0 bg-black bg-opacity-60 hidden z-50 flex items-center justify-center p-4">
        <div id="modalContent" class="bg-white rounded-lg shadow-xl w-full max-w-3xl max-h-[90vh] flex flex-col">
            <div class="p-8 text-center">Loading details...</div>
        </div>
    </div>

    <script>
        const modal = document.getElementById('detailsModal');
        const modalContent = document.getElementById('modalContent');

        function openTab(evt, tabName) {
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('hidden'));
            document.querySelectorAll('.tab-button').forEach(b => b.classList.remove('active'));
            document.getElementById(tabName).classList.remove('hidden');
            evt.currentTarget.classList.add('active');
        }

        function closeModal() { modal.classList.add('hidden'); }

        async function viewProviderDetails(providerId) {
            modalContent.innerHTML = '<div class="p-8 text-center text-gray-500"><i class="fa-solid fa-spinner fa-spin fa-2x"></i><p class="mt-2">Loading details...</p></div>';
            modal.classList.remove('hidden');

            try {
                const response = await fetch(`get_provider_details.php?id=${providerId}`);
                const data = await response.json();
                
                if (data.error) throw new Error(data.error);

                let scheduleHtml = '<p class="text-center text-gray-500 py-4">No available slots found.</p>';
                if(data.schedule.length > 0) {
                    scheduleHtml = data.schedule.map(slot => {
                        const date = new Date(slot.availableDate).toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
                        const startTime = new Date(`1970-01-01T${slot.startTime}`).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
                        return `<label class="flex items-center p-3 bg-purple-50 rounded-md cursor-pointer hover:bg-purple-100 transition-colors">
                                    <input type="radio" name="scheduleID" value="${slot.scheduleID}" class="mr-3 h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300" required>
                                    <div>
                                        <p class="font-semibold text-gray-800">${date}</p>
                                        <p class="text-sm text-gray-600">${startTime}</p>
                                    </div>
                                </label>`;
                    }).join('');
                }

                modalContent.innerHTML = `
                    <div class="p-6 border-b flex justify-between items-start">
                        <div class="flex items-center space-x-4">
                            <img src="${data.profile.profilePhoto || 'placeholder.png'}" class="w-20 h-20 rounded-full object-cover border-2 border-purple-100">
                            <div>
                                <h3 class="text-2xl font-bold text-slate-800">${data.profile.Name}</h3>
                                <p class="text-gray-600">${data.profile.specialty}</p>
                            </div>
                        </div>
                        <button onclick="closeModal()" class="text-2xl text-gray-400 hover:text-gray-600 transition-colors">&times;</button>
                    </div>
                    <form method="POST" class="flex-grow flex flex-col overflow-hidden">
                        <div class="p-6 space-y-6 overflow-y-auto">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm text-center">
                                <div class="bg-gray-50 p-3 rounded-lg"><strong class="block text-gray-500">Experience</strong> ${data.profile.experience || 'N/A'} years</div>
                                <div class="bg-gray-50 p-3 rounded-lg"><strong class="block text-gray-500">Clinic</strong> ${data.profile.hospital}</div>
                                <div class="bg-gray-50 p-3 rounded-lg"><strong class="block text-gray-500">Fees</strong> ৳${Number(data.profile.fees).toFixed(2)}</div>
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-800 mb-2">Select an Available Slot</h4>
                                <div class="space-y-3 max-h-48 overflow-y-auto border p-3 rounded-md bg-gray-50">${scheduleHtml}</div>
                            </div>
                            <div>
                                <label for="notes" class="block text-sm font-medium text-gray-700">Notes for the provider (Optional)</label>
                                <textarea name="notes" id="notes" rows="2" class="w-full mt-1 p-2 border rounded-md" placeholder="e.g., Mention your primary health concern..."></textarea>
                            </div>
                        </div>
                        <div class="bg-gray-100 p-4 mt-auto flex justify-end">
                            <input type="hidden" name="providerID" value="${providerId}">
                            <button type="submit" name="book_appointment" class="px-6 py-2 bg-dark-orchid text-white rounded-md font-semibold hover:bg-purple-700">Book Now & Proceed to Pay</button>
                        </div>
                    </form>
                `;
            } catch (error) {
                modalContent.innerHTML = `<div class="p-8 text-center"><p class="text-red-500">Error: Could not load provider details.</p><button onclick="closeModal()" class="mt-4 px-4 py-2 bg-gray-200 rounded-md">Close</button></div>`;
                console.error('Fetch error:', error);
            }
        }
    </script>
</body>
</html>