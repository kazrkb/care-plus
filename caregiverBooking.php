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

            // --- (NEW) Validation: Check if the PATIENT has a conflicting booking ---
            $patientConflictCheck = $conn->prepare("SELECT bookingID FROM caregiverbooking WHERE patientID = ? AND status IN ('Scheduled', 'Active') AND (startDate < ? AND endDate > ?)");
            $patientConflictCheck->bind_param("iss", $patientID, $endDateStr, $startDate);
            $patientConflictCheck->execute();
            if ($patientConflictCheck->get_result()->num_rows > 0) {
                throw new Exception("You already have another caregiver booking scheduled during this time period.");
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
            $successMsg = "Caregiver booked successfully from " . date('d M, Y', strtotime($startDate)) . "!";
        } else {
            throw new Exception("This slot is no longer available.");
        }
    } catch (Exception $e) {
        $conn->rollback();
        $errorMsg = "Booking failed: " . $e->getMessage();
    }
}

// --- Fetch ALL caregiver data ---
$caregivers = $conn->query("SELECT u.userID, u.Name, u.profilePhoto, c.* FROM users u JOIN caregiver c ON u.userID = c.careGiverID WHERE u.role = 'CareGiver'")->fetch_all(MYSQLI_ASSOC);
$availabilities = $conn->query("SELECT * FROM caregiver_availability WHERE status = 'Available' AND startDate >= CURDATE()")->fetch_all(MYSQLI_ASSOC);

// --- (CORRECTED) Group availabilities by caregiver BEFORE the loop for efficiency and to fix the error ---
$availabilitiesByCareGiver = [];
foreach ($availabilities as $avail) {
    $availabilitiesByCareGiver[$avail['careGiverID']][] = $avail;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Book a CareGiver - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style> body { font-family: 'Inter', sans-serif; } .bg-dark-orchid { background-color: #9932CC; } </style>
</head>
<body class="bg-purple-50">
     <div class="flex min-h-screen">
        <aside class="w-64 bg-white border-r">
             <div class="p-6"><a href="#" class="text-2xl font-bold text-dark-orchid">CarePlus</a></div>
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
                <a href="my_bookings.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg">
                    <i class="fa-solid fa-book-bookmark w-5"></i>
                    <span>My Bookings</span>
                </a>
                <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg mt-8">
                    <i class="fa-solid fa-arrow-right-from-bracket w-5"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </aside>
        <main class="flex-1 p-8">
             <header class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-800">Book a CareGiver</h1>
                    <p class="text-gray-600 mt-1">Find and book professional caregivers for your needs.</p>
                </div>
                 <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <p class="font-semibold text-slate-700"><?php echo htmlspecialchars($userName); ?></p>
                        <p class="text-sm text-gray-500">Patient</p>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-dark-orchid text-white flex items-center justify-center font-bold text-lg"><?php echo htmlspecialchars($userAvatar); ?></div>
                </div>
            </header>
            
            <?php if ($successMsg): ?><div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert"><p><?php echo $successMsg; ?></p></div><?php endif; ?>
            <?php if ($errorMsg): ?><div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert"><p><?php echo $errorMsg; ?></p></div><?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($caregivers as $cg): ?>
                <div class="bg-white p-6 rounded-lg shadow-lg flex flex-col">
                    <h3 class="text-lg font-semibold text-slate-800"><?php echo htmlspecialchars($cg['Name']); ?></h3>
                    <p class="text-sm text-purple-600 mb-2"><?php echo htmlspecialchars($cg['careGiverType']); ?></p>
                    <p class="text-sm text-gray-600 border-t pt-3 mt-3 mb-4">
                        <i class="fa-solid fa-award mr-2 text-gray-400"></i>
                        <strong>Qualifications:</strong> <?php echo htmlspecialchars($cg['certifications']); ?>
                    </p>
                    <div class="text-xs text-gray-500 border-t pt-3 mb-4 flex-grow">
                        <p class="font-semibold text-gray-700 mb-2">Available Slots:</p>
                        <div class="space-y-2">
                            <?php
                                // (CORRECTED) Use the pre-grouped array for efficiency
                                $cgAvailabilities = $availabilitiesByCareGiver[$cg['userID']] ?? [];
                                if (empty($cgAvailabilities)) {
                                    echo '<p class="text-red-500">No available slots.</p>';
                                } else {
                                    foreach ($cgAvailabilities as $avail) {
                                        echo '<div class="p-2 bg-gray-50 rounded-md">';
                                        echo '<p class="font-medium text-gray-800">Starts: '. (new DateTime($avail['startDate']))->format('M j, Y') .'</p>';
                                        echo '<p class="text-gray-600">Booking Type: '. htmlspecialchars($avail['bookingType']) .'</p>';
                                        echo '</div>';
                                    }
                                }
                            ?>
                        </div>
                    </div>
                    <button onclick='openBookingModal(<?php echo htmlspecialchars(json_encode($cg), ENT_QUOTES, "UTF-8"); ?>)' class="mt-auto w-full bg-dark-orchid text-white py-2 rounded-lg hover:bg-purple-700 transition">View Slots & Book</button>
                </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>

     <div id="bookingModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg">
            <form method="POST" action="caregiverBooking.php">
                <div class="p-6">
                    <h3 class="text-xl font-bold text-slate-800 mb-2">Book <span id="modalCareGiverName"></span></h3>
                    <p class="text-sm text-gray-500 mb-4">Select an available slot below to see the cost and confirm.</p>
                    <input type="hidden" name="careGiverID" id="modalCareGiverID">
                    <div id="slotsContainer" class="space-y-2 max-h-60 overflow-y-auto border p-3 rounded-md"></div>
                    <div id="costDisplay" class="bg-gray-50 p-3 rounded-md text-sm mt-4 hidden">
                        <p class="flex justify-between"><span>Service Period:</span> <strong id="periodDisplay">--</strong></p>
                        <p class="flex justify-between mt-1"><span>Total Cost:</span> <strong id="totalAmountDisplay">--</strong></p>
                    </div>
                </div>
                <div class="bg-gray-100 px-6 py-3 flex justify-end space-x-3">
                    <button type="button" onclick="closeBookingModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancel</button>
                    <button type="submit" id="confirmBtn" class="px-4 py-2 bg-dark-orchid text-white rounded-md hover:bg-purple-700 disabled:opacity-50" disabled>Confirm Booking</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        const modal = document.getElementById('bookingModal');
        const slotsContainer = document.getElementById('slotsContainer');
        const confirmBtn = document.getElementById('confirmBtn');
        const costDisplay = document.getElementById('costDisplay');
        const periodDisplay = document.getElementById('periodDisplay');
        const totalAmountDisplay = document.getElementById('totalAmountDisplay');
        let currentCareGiver = null;

        async function openBookingModal(careGiverData) {
            currentCareGiver = careGiverData;
            document.getElementById('modalCareGiverName').textContent = careGiverData.Name;
            document.getElementById('modalCareGiverID').value = careGiverData.userID;
            slotsContainer.innerHTML = '<p class="text-center text-gray-500">Loading availability...</p>';
            modal.classList.remove('hidden');

            const response = await fetch(`caregiverBooking.php?get_slots_for=${careGiverData.userID}`);
            const slots = await response.json();

            slotsContainer.innerHTML = '';
            if (slots.length === 0) {
                slotsContainer.innerHTML = '<p class="text-center text-red-500">This caregiver has no available slots.</p>';
            } else {
                slots.forEach(slot => {
                    const date = new Date(slot.startDate).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
                    slotsContainer.innerHTML += `
                        <label class="block p-3 border rounded-md hover:bg-purple-50 cursor-pointer">
                            <input type="radio" name="book_availability_id" value="${slot.availabilityID}" class="mr-3" onchange="updateCost(this)"
                                data-type="${slot.bookingType}" data-startdate="${slot.startDate}">
                            <span class="font-semibold">Starts: ${date}</span>
                            <span class="text-sm text-gray-600">(${slot.bookingType} Slot)</span>
                        </label>
                    `;
                });
            }
        }
        
        function updateCost(radio) {
            const bookingType = radio.dataset.type;
            const startDate = new Date(radio.dataset.startdate);
            let endDate = new Date(startDate);
            let amount = 0;
            
            if(bookingType === 'Daily') { amount = parseFloat(currentCareGiver.dailyRate); endDate.setDate(endDate.getDate() + 1); }
            if(bookingType === 'Weekly') { amount = parseFloat(currentCareGiver.weeklyRate); endDate.setDate(endDate.getDate() + 7); }
            if(bookingType === 'Monthly') { amount = parseFloat(currentCareGiver.monthlyRate); endDate.setMonth(endDate.getMonth() + 1); }

            periodDisplay.textContent = `${startDate.toLocaleDateString('en-CA')} to ${endDate.toLocaleDateString('en-CA')}`;
            totalAmountDisplay.textContent = `৳${amount.toLocaleString()}`;
            costDisplay.classList.remove('hidden');
            confirmBtn.disabled = false;
        }

        function closeBookingModal() {
            modal.classList.add('hidden');
            confirmBtn.disabled = true;
            costDisplay.classList.add('hidden');
        }
    </script>
</body>
</html>