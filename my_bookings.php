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

// --- Handle Deleting a SINGLE historical booking ---
if (isset($_GET['delete_booking_id'])) {
    $bookingID = (int)$_GET['delete_booking_id'];
    // You can only delete your own bookings that are already completed or canceled.
    $stmt = $conn->prepare("DELETE FROM caregiverbooking WHERE bookingID = ? AND patientID = ? AND status IN ('Completed', 'Canceled')");
    $stmt->bind_param("ii", $bookingID, $patientID);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $successMsg = "Booking history item deleted successfully.";
    } else {
        $errorMsg = "Could not delete this item. It might be active or does not exist.";
    }
    $stmt->close();
}

// --- Handle Clearing ALL history ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_all_history'])) {
    // Delete all of the patient's completed or canceled bookings.
    $stmt = $conn->prepare("DELETE FROM caregiverbooking WHERE patientID = ? AND status IN ('Completed', 'Canceled')");
    $stmt->bind_param("i", $patientID);
    if ($stmt->execute()) {
        $successMsg = "All booking history has been cleared.";
    } else {
        $errorMsg = "Could not clear history. Please try again.";
    }
    $stmt->close();
}


// --- Handle Booking Cancellation ---
if (isset($_GET['cancel_booking_id'])) {
    $bookingID = (int)$_GET['cancel_booking_id'];

    $findBookingStmt = $conn->prepare("SELECT availabilityID, status, careGiverID, startDate FROM caregiverbooking WHERE bookingID = ? AND patientID = ?");
    $findBookingStmt->bind_param("ii", $bookingID, $patientID);
    $findBookingStmt->execute();
    $booking = $findBookingStmt->get_result()->fetch_assoc();
    $findBookingStmt->close();

    // Only allow cancellation if the booking is 'Scheduled'
    if ($booking && $booking['status'] === 'Scheduled') {
        $availabilityID = $booking['availabilityID'];
        $careGiverID = $booking['careGiverID'];

        $conn->begin_transaction();
        try {
            // Step 1: Update the booking status to 'Canceled'
            $cancelStmt = $conn->prepare("UPDATE caregiverbooking SET status = 'Canceled' WHERE bookingID = ?");
            $cancelStmt->bind_param("i", $bookingID);
            $cancelStmt->execute();
            $cancelStmt->close();

            // Step 2: Make the original availability slot 'Available' again
            if ($availabilityID) {
                $availStmt = $conn->prepare("UPDATE caregiver_availability SET status = 'Available' WHERE availabilityID = ?");
                $availStmt->bind_param("i", $availabilityID);
                $availStmt->execute();
                $availStmt->close();
            }
            
            // Step 3: Create a notification for the caregiver
            $message = "Your booking with patient " . $userName . " starting on " . date('M j, Y', strtotime($booking['startDate'])) . " has been canceled.";
            $type = "Booking Canceled";
            $status = "Unread";
            $notifyStmt = $conn->prepare("INSERT INTO notification (userID, type, message, sentDate, status) VALUES (?, ?, ?, CURDATE(), ?)");
            $notifyStmt->bind_param("isss", $careGiverID, $type, $message, $status);
            $notifyStmt->execute();
            $notifyStmt->close();

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


// --- Fetch All Bookings for the Patient ---
$query = "
    SELECT b.*, u.Name as careGiverName, c.careGiverType
    FROM caregiverbooking b
    JOIN users u ON b.careGiverID = u.userID
    JOIN caregiver c ON b.careGiverID = c.careGiverID
    WHERE b.patientID = ?
    ORDER BY b.startDate DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $patientID);
$stmt->execute();
$allBookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// Group bookings for display
$upcomingBookings = array_filter($allBookings, fn($b) => in_array($b['status'], ['Scheduled', 'Active']));
$pastBookings = array_filter($allBookings, fn($b) => in_array($b['status'], ['Completed', 'Canceled']));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Bookings - CarePlus</title>
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
                <a href="patientAppointments.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-calendar-days w-5"></i><span>My Appointments</span></a>
                <a href="caregiverBooking.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-hands-holding-child w-5"></i><span>Book Caregiver</span></a>
                <a href="my_bookings.php" class="flex items-center space-x-3 px-4 py-3 bg-purple-100 text-dark-orchid rounded-lg"><i class="fa-solid fa-book-bookmark w-5"></i><span>My Caregiver Bookings</span></a>
                <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg mt-8"><i class="fa-solid fa-arrow-right-from-bracket w-5"></i><span>Logout</span></a>
            </nav>
        </aside>

        <main class="flex-1 p-8">
            <header class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-800">My Caregiver Bookings</h1>
                    <p class="text-gray-600 mt-1">View and manage your caregiver service history.</p>
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
            
            <div class="space-y-10">
                <section>
                    <h2 class="text-2xl font-bold text-slate-800 mb-4">Upcoming & Active</h2>
                    <div class="space-y-4">
                        <?php if (empty($upcomingBookings)): ?>
                            <p class="text-gray-500 bg-white p-6 rounded-lg shadow-sm">You have no upcoming or active bookings.</p>
                        <?php else: ?>
                            <?php foreach ($upcomingBookings as $booking): ?>
                                <div class="bg-white p-5 rounded-lg shadow-sm flex justify-between items-center">
                                    <div>
                                        <p class="font-bold text-slate-800"><?php echo htmlspecialchars($booking['careGiverName']); ?></p>
                                        <p class="text-sm text-purple-600 mb-2"><?php echo htmlspecialchars($booking['careGiverType']); ?></p>
                                        <p class="text-sm text-gray-500">
                                            <i class="fa-solid fa-calendar-range mr-2"></i><?php echo date('M j, Y', strtotime($booking['startDate'])) . ' - ' . date('M j, Y', strtotime($booking['endDate'])); ?>
                                        </p>
                                        <p class="text-sm text-gray-500">
                                            <i class="fa-solid fa-tags mr-2"></i><?php echo htmlspecialchars($booking['bookingType']); ?> booking
                                        </p>
                                    </div>
                                    <div class="text-right">
                                         <?php
                                            $statusClass = '';
                                            if ($booking['status'] == 'Scheduled') $statusClass = 'bg-blue-100 text-blue-800';
                                            if ($booking['status'] == 'Active') $statusClass = 'bg-green-100 text-green-800';
                                        ?>
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full <?php echo $statusClass; ?>"><?php echo htmlspecialchars($booking['status']); ?></span>
                                        <p class="font-semibold text-lg text-slate-700 mt-2">৳<?php echo number_format($booking['totalAmount']); ?></p>
                                        <?php if ($booking['status'] === 'Scheduled'): ?>
                                        <a href="my_bookings.php?cancel_booking_id=<?php echo $booking['bookingID']; ?>"
                                           onclick="return confirm('Are you sure you want to cancel this booking?');"
                                           class="text-xs text-red-500 hover:underline mt-2 inline-block">Cancel Booking</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <section>
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-2xl font-bold text-slate-800">History</h2>
                        <?php if (!empty($pastBookings)): ?>
                        <form method="POST" action="my_bookings.php" onsubmit="return confirm('Are you sure you want to clear your entire booking history? This action cannot be undone.');">
                            <button type="submit" name="clear_all_history" class="text-sm text-gray-500 hover:text-red-600 hover:underline">
                                <i class="fa-solid fa-trash-can mr-1"></i>Clear All
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <div class="space-y-4">
                        <?php if (empty($pastBookings)): ?>
                             <p class="text-gray-500 bg-white p-6 rounded-lg shadow-sm">You have no past bookings.</p>
                        <?php else: ?>
                            <?php foreach ($pastBookings as $booking): ?>
                                <div class="bg-white p-5 rounded-lg shadow-sm flex justify-between items-center opacity-70">
                                    <div>
                                        <p class="font-bold text-slate-800"><?php echo htmlspecialchars($booking['careGiverName']); ?></p>
                                        <p class="text-sm text-gray-500"><i class="fa-solid fa-calendar-range mr-2"></i><?php echo date('M j, Y', strtotime($booking['startDate'])) . ' - ' . date('M j, Y', strtotime($booking['endDate'])); ?></p>
                                    </div>
                                    <div class="flex items-center space-x-4">
                                        <p class="font-semibold text-lg text-slate-700">৳<?php echo number_format($booking['totalAmount']); ?></p>
                                        <?php
                                            $statusClass = '';
                                            if ($booking['status'] == 'Completed') $statusClass = 'bg-gray-200 text-gray-800';
                                            if ($booking['status'] == 'Canceled') $statusClass = 'bg-red-100 text-red-800';
                                        ?>
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full <?php echo $statusClass; ?>"><?php echo htmlspecialchars($booking['status']); ?></span>
                                        <a href="my_bookings.php?delete_booking_id=<?php echo $booking['bookingID']; ?>"
                                           onclick="return confirm('Are you sure you want to delete this history item?');"
                                           class="text-gray-400 hover:text-red-600" title="Delete from History">
                                           <i class="fa-solid fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </main>
    </div>
</body>
</html>