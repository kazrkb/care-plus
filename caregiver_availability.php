<?php
session_start();
$conn = require_once 'config.php';
date_default_timezone_set('Asia/Dhaka');

// Protect this page: allow only logged-in CareGivers
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'CareGiver') {
    header("Location: login.php");
    exit();
}

$careGiverID = $_SESSION['userID'];
$userName = $_SESSION['Name'];
$userAvatar = strtoupper(substr($userName, 0, 2));
$successMsg = "";
$errorMsg = "";

// --- (MODIFIED) AUTOMATIC CLEANUP ---
// This now deletes any slot that is expired OR has been canceled.
$cleanupSql = "DELETE FROM caregiver_availability WHERE (startDate < CURDATE() OR status = 'Canceled') AND careGiverID = ?";
$cleanupStmt = $conn->prepare($cleanupSql);
$cleanupStmt->bind_param("i", $careGiverID);
$cleanupStmt->execute();
$cleanupStmt->close();


// --- Handle CANCELING a slot (This logic remains, but cleanup will remove it on next page load) ---
if (isset($_GET['cancel_id'])) {
    $availabilityID = (int)$_GET['cancel_id'];
    $stmt = $conn->prepare("UPDATE caregiver_availability SET status = 'Canceled' WHERE availabilityID = ? AND careGiverID = ? AND status = 'Available'");
    $stmt->bind_param("ii", $availabilityID, $careGiverID);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $successMsg = "Availability slot has been marked for deletion and will be removed.";
    } else {
        $errorMsg = "Could not cancel this slot. It might already be booked.";
    }
    $stmt->close();
}


// --- Handle EDITING an availability block ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_availability'])) {
    $availabilityID = (int)$_POST['availabilityID'];
    $startDate = $_POST['startDate'];
    $bookingType = $_POST['bookingType'];
    
    $sql = "UPDATE caregiver_availability SET startDate = ?, bookingType = ? WHERE availabilityID = ? AND careGiverID = ? AND status = 'Available'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssii", $startDate, $bookingType, $availabilityID, $careGiverID);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $successMsg = "Availability block updated successfully!";
    } else {
        $errorMsg = "Failed to update availability. It may be booked or an overlap occurred.";
    }
    $stmt->close();
}


// --- Handle ADDING a new availability block ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_availability'])) {
    $startDate = $_POST['startDate'];
    $bookingType = $_POST['bookingType'];
    $status = 'Available';
    
    $checkStmt = $conn->prepare("SELECT availabilityID FROM caregiver_availability WHERE careGiverID = ? AND startDate = ? AND status = 'Available'");
    $checkStmt->bind_param("is", $careGiverID, $startDate);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        $errorMsg = "You already have an availability slot for this start date.";
    } else {
        $sql = "INSERT INTO caregiver_availability (careGiverID, bookingType, startDate, status) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isss", $careGiverID, $bookingType, $startDate, $status); 
        if ($stmt->execute()) {
            $successMsg = "New availability slot added successfully!";
        } else {
            $errorMsg = "Failed to add availability.";
        }
        $stmt->close();
    }
    $checkStmt->close();
}


// --- (MODIFIED) Fetch only the relevant (Available or Booked) slots ---
$availabilities = [];
$sql = "SELECT * FROM caregiver_availability WHERE careGiverID = ? AND status IN ('Available', 'Booked') ORDER BY startDate ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $careGiverID);
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()) {
    $availabilities[] = $row;
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Availability - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-dark-orchid { background-color: #9932CC; }
        .text-dark-orchid { color: #9932CC; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <aside class="w-64 bg-white border-r">
            <div class="p-6"><a href="#" class="text-2xl font-bold text-dark-orchid">CarePlus</a></div>
            <nav class="px-4">
                <a href="careGiverDashboard.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg"><i class="fa-solid fa-table-columns w-5"></i><span>Dashboard</span></a>
                <a href="caregiverProfile.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-regular fa-user w-5"></i><span>My Profile</span></a>
                <a href="caregiver_careplan.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-clipboard-list w-5"></i><span>Care Plans</span></a>
                <a href="caregiver_availability.php" class="flex items-center space-x-3 px-4 py-3 bg-purple-50 text-dark-orchid rounded-lg"><i class="fa-solid fa-calendar-days w-5"></i><span>Manage Availability</span></a>
                <a href="my_bookings.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-calendar-check w-5"></i><span>My Bookings</span></a>
                <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg mt-8"><i class="fa-solid fa-arrow-right-from-bracket w-5"></i><span>Logout</span></a>
            </nav>
        </aside>
        <main class="flex-1 p-8">
             <header class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-800">Manage Availability</h1>
                    <p class="text-gray-600 mt-1">Add your available slots for patients to book.</p>
                </div>
                 <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <p class="font-semibold text-slate-700"><?php echo htmlspecialchars($userName); ?></p>
                        <p class="text-sm text-gray-500">CareGiver</p>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-dark-orchid text-white flex items-center justify-center font-bold text-lg"><?php echo htmlspecialchars($userAvatar); ?></div>
                </div>
            </header>
            
            <?php if ($successMsg): ?><div class="mb-6 p-4 bg-green-100 text-green-800 border-l-4 border-green-500 rounded-r-lg" role="alert"><p><strong>Success:</strong> <?php echo $successMsg; ?></p></div><?php endif; ?>
            <?php if ($errorMsg): ?><div class="mb-6 p-4 bg-red-100 text-red-800 border-l-4 border-red-500 rounded-r-lg" role="alert"><p><strong>Error:</strong> <?php echo $errorMsg; ?></p></div><?php endif; ?>
            
            <div class="bg-white p-6 rounded-xl shadow-lg mb-8">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Add New Availability Slot</h2>
                <form id="addAvailabilityForm" action="caregiver_availability.php" method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <div>
                        <label for="bookingType" class="block text-sm font-medium text-gray-700">Booking Type</label>
                        <select id="bookingType" name="bookingType" required class="w-full mt-1 p-2 border border-gray-300 rounded-md">
                            <option value="Daily">Daily</option>
                            <option value="Weekly">Weekly</option>
                            <option value="Monthly">Monthly</option>
                        </select>
                    </div>
                    <div>
                        <label for="startDate" class="block text-sm font-medium text-gray-700">Start Date</label>
                        <input type="date" id="startDate" name="startDate" required class="w-full mt-1 p-2 border border-gray-300 rounded-md" min="<?php echo date('Y-m-d'); ?>">
                    </div>
                     <div>
                        <label for="endDateDisplay" class="block text-sm font-medium text-gray-700">End Date (Auto)</label>
                        <input type="text" id="endDateDisplay" class="w-full mt-1 p-2 border border-gray-200 rounded-md bg-gray-100" readonly>
                    </div>
                    <button type="submit" name="add_availability" class="w-full py-2 px-4 bg-dark-orchid text-white rounded-md font-semibold hover:bg-purple-700 transition"><i class="fa-solid fa-plus mr-2"></i>Add Slot</button>
                </form>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-lg">
                <h2 class="text-xl font-bold text-gray-800 mb-4">My Availability Slots</h2>
                <div class="space-y-4">
                    <?php if (empty($availabilities)): ?>
                        <div class="text-center text-gray-500 py-10 border-2 border-dashed rounded-lg"><i class="fa-solid fa-calendar-xmark fa-3x text-gray-400 mb-3"></i><p>You have no upcoming availability.</p></div>
                    <?php else: ?>
                        <?php foreach ($availabilities as $slot): ?>
                            <?php
                                $statusClass = ($slot['status'] === 'Available') ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800';
                                $sDate = new DateTime($slot['startDate']);
                                $eDate = new DateTime($slot['startDate']);
                                if ($slot['bookingType'] === 'Weekly') $eDate->modify('+6 days');
                                if ($slot['bookingType'] === 'Monthly') $eDate->modify('+1 month -1 day');
                            ?>
                            <div class="flex justify-between items-center p-4 rounded-lg border <?php if($slot['status'] === 'Booked') echo 'opacity-60 bg-gray-50'; ?>">
                                <div class="flex items-center space-x-4">
                                    <i class="fa-solid fa-calendar-check fa-2x <?php echo $slot['status'] === 'Available' ? 'text-purple-500' : 'text-gray-400'; ?>"></i>
                                    <div>
                                        <p class="font-semibold text-slate-800">
                                            <?php echo $sDate->format("M j, Y"); ?>
                                            <?php if ($slot['bookingType'] !== 'Daily'): ?>
                                                &rarr; <?php echo $eDate->format("M j, Y"); ?>
                                            <?php endif; ?>
                                        </p>
                                        <p class="text-sm text-gray-600">Type: <span class="font-medium"><?php echo htmlspecialchars($slot['bookingType']); ?></span></p>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full <?php echo $statusClass; ?>"><?php echo htmlspecialchars($slot['status']); ?></span>
                                    <?php if ($slot['status'] === 'Available'): ?>
                                    <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($slot)); ?>)" class="text-gray-400 hover:text-blue-600" title="Edit"><i class="fa-solid fa-pencil"></i></button>
                                    <a href="caregiver_availability.php?cancel_id=<?php echo $slot['availabilityID']; ?>" onclick="return confirm('Are you sure?');" class="text-gray-400 hover:text-red-600" title="Cancel Slot"><i class="fa-solid fa-times-circle"></i></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
            <form method="POST" action="caregiver_availability.php">
                <input type="hidden" name="edit_availability" value="1">
                <input type="hidden" name="availabilityID" id="editAvailabilityID">
                <div class="p-6">
                    <h3 class="text-xl font-bold text-slate-800 mb-4">Edit Availability Slot</h3>
                    <div class="space-y-4">
                         <div>
                            <label for="editBookingType" class="block text-sm font-medium text-gray-700">Booking Type</label>
                            <select id="editBookingType" name="bookingType" required class="w-full mt-1 p-2 border border-gray-300 rounded-md">
                                <option value="Daily">Daily</option>
                                <option value="Weekly">Weekly</option>
                                <option value="Monthly">Monthly</option>
                            </select>
                        </div>
                        <div>
                            <label for="editStartDate" class="block text-sm font-medium text-gray-700">Start Date</label>
                            <input type="date" id="editStartDate" name="startDate" required class="w-full mt-1 p-2 border rounded-md" min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                </div>
                <div class="bg-gray-100 px-6 py-3 flex justify-end space-x-3">
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Close</button>
                    <button type="submit" class="px-4 py-2 bg-dark-orchid text-white rounded-md hover:bg-purple-700">Update Slot</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // --- Logic for the ADD form's auto-calculated End Date ---
        const addBookingType = document.getElementById('bookingType');
        const addStartDate = document.getElementById('startDate');
        const addEndDateDisplay = document.getElementById('endDateDisplay');

        function calculateAddEndDate() {
            if (!addStartDate.value) { addEndDateDisplay.value = ''; return; }
            const startDate = new Date(addStartDate.value);
            const bookingType = addBookingType.value;
            let endDate = new Date(startDate);
            if (bookingType === 'Weekly') endDate.setDate(startDate.getDate() + 6);
            if (bookingType === 'Monthly') {
                endDate.setMonth(startDate.getMonth() + 1);
                endDate.setDate(endDate.getDate() - 1);
            }
            addEndDateDisplay.value = endDate.toLocaleDateString('en-CA');
        }
        addBookingType.addEventListener('change', calculateAddEndDate);
        addStartDate.addEventListener('change', calculateAddEndDate);
        if (addStartDate.value) { calculateAddEndDate(); } 
        else { addStartDate.value = new Date().toISOString().split('T')[0]; calculateAddEndDate(); }

        // --- Logic for the EDIT modal ---
        const editModal = document.getElementById('editModal');
        function openEditModal(slotData) {
            document.getElementById('editAvailabilityID').value = slotData.availabilityID;
            document.getElementById('editStartDate').value = slotData.startDate;
            document.getElementById('editBookingType').value = slotData.bookingType;
            editModal.classList.remove('hidden');
        }
        function closeEditModal() {
            editModal.classList.add('hidden');
        }
    </script>
</body>
</html>