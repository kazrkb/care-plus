<?php
session_start();
$conn = require_once 'config.php';

$paymentVerified = false;
$message = "Payment failed. Please try again.";
$redirectUrl = "login.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)$_POST['amount'];
    $gatewayTransactionId = 'TXN_' . time() . '_' . rand(1000, 9999);
    
    $conn->begin_transaction();
    try {
        // --- If it's a CAREGIVER BOOKING payment ---
        if (isset($_POST['caregiver_booking_id'])) {
            $bookingID = (int)$_POST['caregiver_booking_id'];
            
            // Get patient ID from booking
            $stmt = $conn->prepare("SELECT patientID FROM caregiverbooking WHERE bookingID = ?");
            $stmt->bind_param("i", $bookingID);
            $stmt->execute();
            $booking = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            // Insert transaction
            $stmt = $conn->prepare("INSERT INTO transaction (careProviderBookingID, amount, transactionType, status, timestamp) VALUES (?, ?, 'Caregiver Booking', 'Completed', NOW())");
            $stmt->bind_param("id", $bookingID, $amount);
            $stmt->execute();
            $stmt->close();
            
            unset($_SESSION['pending_caregiver_booking_id']);
            $message = "Your caregiver booking payment was successful. You can view the details in 'My Bookings'.";
            $redirectUrl = "my_bookings.php"; // Adjust this to your bookings page
            
        // --- If it's a REGISTRATION payment ---
        } elseif (isset($_POST['user_id'])) {
            $userID = (int)$_POST['user_id'];
            $stmt = $conn->prepare("INSERT INTO transaction (amount, transactionType, status, timestamp) VALUES (?, 'Registration Fee', 'Completed', NOW())");
            $stmt->bind_param("d", $amount);
            $stmt->execute();
            $stmt->close();
            unset($_SESSION['pending_user_id']);
            $message = "Your registration payment was successful. If you are a professional, your account is now under review.";
            $redirectUrl = "login.php";
            
        // --- If it's an APPOINTMENT payment ---
        } elseif (isset($_POST['appointment_id'])) {
            $appointmentID = (int)$_POST['appointment_id'];
            
            // Get patient ID from appointment
            $stmt = $conn->prepare("SELECT patientID FROM appointment WHERE appointmentID = ?");
            $stmt->bind_param("i", $appointmentID);
            $stmt->execute();
            $appointment = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            $stmt = $conn->prepare("INSERT INTO transaction (appointmentID, amount, transactionType, status, timestamp) VALUES (?, ?, 'Appointment Fee', 'Completed', NOW())");
            $stmt->bind_param("id", $appointmentID, $amount);
            $stmt->execute();
            $stmt->close();
            // Optionally, you could update the appointment status to 'Confirmed' here
            // $conn->prepare("UPDATE appointment SET status = 'Confirmed' WHERE appointmentID = ?")->execute([$appointmentID]);
            unset($_SESSION['pending_appointment_id']);
            $message = "Your appointment payment was successful. You can view the details in 'My Appointments'.";
            $redirectUrl = "patientAppointments.php";
        }
        $conn->commit();
        $paymentVerified = true;
    } catch (Exception $e) {
        $conn->rollback();
        $paymentVerified = false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Result - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-dark-orchid { background-color: #9932CC; }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">
    <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-md text-center">
        <div class="mb-6">
            <?php if ($paymentVerified): ?>
                <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fa-solid fa-check text-3xl text-green-600"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-800 mb-2">Payment Successful!</h1>
            <?php else: ?>
                <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fa-solid fa-times text-3xl text-red-600"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-800 mb-2">Payment Failed!</h1>
            <?php endif; ?>
            <p class="text-gray-600"><?php echo htmlspecialchars($message); ?></p>
        </div>
        
        <?php if ($paymentVerified): ?>
        <div class="space-y-3">
            <div class="bg-gray-50 p-4 rounded-lg">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Amount Paid:</span>
                    <span class="font-bold text-gray-800">৳<?php echo number_format($_POST['amount'], 2); ?></span>
                </div>
                <div class="flex justify-between text-sm mt-2">
                    <span class="text-gray-600">Transaction Date:</span>
                    <span class="font-medium text-gray-800"><?php echo date('M d, Y - h:i A'); ?></span>
                </div>
            </div>
            
            <button onclick="window.location.href='<?php echo $redirectUrl; ?>'" 
                class="w-full py-3 px-4 bg-dark-orchid text-white rounded-md font-semibold hover:bg-purple-700 transition">
                Continue
            </button>
        </div>
        <?php else: ?>
        <button onclick="history.back()" 
            class="w-full py-3 px-4 bg-red-600 text-white rounded-md font-semibold hover:bg-red-700 transition">
            Try Again
        </button>
        <?php endif; ?>
    </div>
    
    <script>
        // Auto-redirect after 5 seconds for success
        <?php if ($paymentVerified): ?>
        setTimeout(() => {
            window.location.href = '<?php echo $redirectUrl; ?>';
        }, 5000);
        <?php endif; ?>
    </script>
</body>
</html>
