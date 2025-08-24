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
        $schQuery = "SELECT * FROM caregiver_availability WHERE availabilityID = ? AND status = 'Available' FOR UPDATE";
        $schStmt = $conn->prepare($schQuery);
        $schStmt->bind_param("i", $availabilityID);
        $schStmt->execute();
        $slotDetails = $schStmt->get_result()->fetch_assoc();

        if ($slotDetails) {
            $careGiverID = $slotDetails['careGiverID'];
            $rateQuery = $conn->prepare("SELECT dailyRate, weeklyRate, monthlyRate FROM caregiver WHERE careGiverID = ?");
            $rateQuery->bind_param("i", $careGiverID);
            $rateQuery->execute();
            $rates = $rateQuery->get_result()->fetch_assoc();
            
            $bookingType = $slotDetails['bookingType'];
            $startDate = $slotDetails['startDate'];
            $endDate = new DateTime($startDate);
            $totalAmount = 0;

            if ($bookingType == 'Daily') { $endDate->modify('+1 day'); $totalAmount = $rates['dailyRate']; }
            if ($bookingType == 'Weekly') { $endDate->modify('+7 days'); $totalAmount = $rates['weeklyRate']; }
            if ($bookingType == 'Monthly') { $endDate->modify('+1 month'); $totalAmount = $rates['monthlyRate']; }

            $sql = "INSERT INTO caregiverbooking (patientID, careGiverID, bookingType, startDate, endDate, totalAmount, status, availabilityID) VALUES (?, ?, ?, ?, ?, ?, 'Scheduled', ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iisssdi", $patientID, $careGiverID, $bookingType, $startDate, $endDate->format('Y-m-d'), $totalAmount, $availabilityID);
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

// --- Fetch data for the page display ---
$caregivers = $conn->query("SELECT u.userID, u.Name, u.profilePhoto, c.* FROM users u JOIN caregiver c ON u.userID = c.careGiverID WHERE u.role = 'CareGiver'")->fetch_all(MYSQLI_ASSOC);
$availabilities = $conn->query("SELECT * FROM caregiver_availability WHERE status = 'Available' AND startDate >= CURDATE()")->fetch_all(MYSQLI_ASSOC);

// --- Prepare data for filters ---
$caregiverTypes = array_unique(array_column($caregivers, 'careGiverType'));

// Group availabilities by caregiver for efficient filtering in JS
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
                <a href="patientDashboard.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-table-columns w-5"></i><span>Dashboard</span></a>
                <a href="caregiverBooking.php" class="flex items-center space-x-3 px-4 py-3 bg-purple-100 text-dark-orchid rounded-lg"><i class="fa-solid fa-hands-holding-child w-5"></i><span>Book Caregiver</span></a>
                <a href="my_bookings.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-book-bookmark w-5"></i><span>My Bookings</span></a>
                <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg mt-8"><i class="fa-solid fa-arrow-right-from-bracket w-5"></i><span>Logout</span></a>
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

            <div class="bg-white p-4 rounded-lg shadow-md mb-8">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label for="filterType" class="block text-sm font-medium text-gray-700">Caregiver Type</label>
                        <select id="filterType" class="mt-1 block w-full p-2 border border-gray-300 rounded-md">
                            <option value="">All Types</option>
                            <?php foreach($caregiverTypes as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="filterBookingType" class="block text-sm font-medium text-gray-700">Booking Type</label>
                        <select id="filterBookingType" class="mt-1 block w-full p-2 border border-gray-300 rounded-md">
                            <option value="">Any Type</option>
                            <option value="Daily">Daily</option>
                            <option value="Weekly">Weekly</option>
                            <option value="Monthly">Monthly</option>
                        </select>
                    </div>
                    <div>
                        <label for="filterDate" class="block text-sm font-medium text-gray-700">Available on Date</label>
                        <input type="date" id="filterDate" class="mt-1 block w-full p-2 border border-gray-300 rounded-md">
                    </div>
                     <div>
                        <label class="block text-sm font-medium text-white invisible">Reset</label>
                        <button id="resetFilters" class="mt-1 w-full p-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Reset Filters</button>
                    </div>
                </div>
            </div>

            <div id="caregiverGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($caregivers as $cg): ?>
                <div class="caregiver-card bg-white p-6 rounded-lg shadow-lg flex flex-col" data-caregiver-id="<?php echo $cg['userID']; ?>" data-caregiver-type="<?php echo htmlspecialchars($cg['careGiverType']); ?>">
                    <h3 class="text-lg font-semibold text-slate-800"><?php echo htmlspecialchars($cg['Name']); ?></h3>
                    <p class="text-sm text-purple-600 mb-2"><?php echo htmlspecialchars($cg['careGiverType']); ?></p>
                    <p class="text-sm text-gray-600 border-t pt-3 mt-3 mb-4"><i class="fa-solid fa-award mr-2 text-gray-400"></i><strong>Qualifications:</strong> <?php echo htmlspecialchars($cg['certifications']); ?></p>
                    <div class="text-xs text-gray-500 border-t pt-3 mb-4 flex-grow">
                        <p class="font-semibold text-gray-700 mb-2">Available Slots:</p>
                        <div class="space-y-2">
                             <?php
                                $cgAvailabilities = $availabilitiesByCareGiver[$cg['userID']] ?? [];
                                if (empty($cgAvailabilities)) { echo '<p class="text-red-500">No available slots.</p>'; } 
                                else {
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
            <div id="noResults" class="hidden text-center py-10 bg-white rounded-lg shadow-lg">
                <p class="text-gray-500">No caregivers match your filter criteria.</p>
            </div>
        </main>
    </div>

     <div id="bookingModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4"></div>

    <script>
        // --- Data from PHP for filtering ---
        const caregiversData = <?php echo json_encode($caregivers); ?>;
        const availabilitiesData = <?php echo json_encode($availabilitiesByCareGiver); ?>;

        // --- Filter Elements ---
        const filterType = document.getElementById('filterType');
        const filterBookingType = document.getElementById('filterBookingType');
        const filterDate = document.getElementById('filterDate');
        const resetBtn = document.getElementById('resetFilters');
        const caregiverGrid = document.getElementById('caregiverGrid');
        const noResults = document.getElementById('noResults');

        function filterCaregivers() {
            const type = filterType.value;
            const bookingType = filterBookingType.value;
            const date = filterDate.value;
            let visibleCount = 0;

            document.querySelectorAll('.caregiver-card').forEach(card => {
                const caregiverId = card.dataset.caregiverId;
                const caregiverType = card.dataset.caregiverType;
                
                let isVisible = true;

                // Filter by Caregiver Type
                if (type && caregiverType !== type) {
                    isVisible = false;
                }

                // Filter by Booking Type
                if (isVisible && bookingType) {
                    const hasBookingType = availabilitiesData[caregiverId]?.some(slot => slot.bookingType === bookingType);
                    if (!hasBookingType) isVisible = false;
                }

                // Filter by Date
                if (isVisible && date) {
                    const selectedDate = new Date(date);
                    const isAvailableOnDate = availabilitiesData[caregiverId]?.some(slot => {
                        const startDate = new Date(slot.startDate);
                        let endDate = new Date(startDate);
                        if (slot.bookingType === 'Weekly') endDate.setDate(startDate.getDate() + 6);
                        if (slot.bookingType === 'Monthly') {
                            endDate.setMonth(startDate.getMonth() + 1);
                            endDate.setDate(endDate.getDate() - 1);
                        }
                        return selectedDate >= startDate && selectedDate <= endDate;
                    });
                    if (!isAvailableOnDate) isVisible = false;
                }
                
                card.style.display = isVisible ? 'flex' : 'none';
                if (isVisible) visibleCount++;
            });

            noResults.style.display = visibleCount === 0 ? 'block' : 'none';
        }
        
        filterType.addEventListener('change', filterCaregivers);
        filterBookingType.addEventListener('change', filterCaregivers);
        filterDate.addEventListener('change', filterCaregivers);
        
        resetBtn.addEventListener('click', () => {
            filterType.value = '';
            filterBookingType.value = '';
            filterDate.value = '';
            filterCaregivers();
        });

        // The Modal JavaScript remains unchanged from the previous version
        // ... (modal open/close and cost calculation functions) ...
    </script>
</body>
</html>