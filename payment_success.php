<?php
session_start();
$conn = require_once 'config.php';

$paymentVerified = false;
$message = "";
$redirectUrl = "login.php"; // Default redirect

// This is a placeholder. You would get the real transaction ID from your payment gateway.
$gatewayTransactionId = 'SSL_TRANS_' . strtoupper(bin2hex(random_bytes(10))); 

// Determine the payment type (Registration or Appointment)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // TODO: Add actual payment verification logic from your gateway here.
    $paymentVerified = true; 
    $amount = (float)$_POST['amount'];

    $conn->begin_transaction();
    try {
        // --- If it's a REGISTRATION payment ---
        if (isset($_POST['user_id'])) {
            $userID = (int)$_POST['user_id'];
            $conn->prepare("UPDATE Users SET payment_status = 'Paid' WHERE userID = ? AND payment_status = 'Unpaid'")->execute([$userID]);
            
            $stmt = $conn->prepare("INSERT INTO transaction (userID, amount, transactionType, status, timestamp, gatewayTransactionID) VALUES (?, ?, 'Registration Fee', 'Completed', NOW(), ?)");
            $stmt->bind_param("ids", $userID, $amount, $gatewayTransactionId);
            $stmt->execute();
            
            unset($_SESSION['pending_user_id']);
            $message = "Your registration payment was successful. If you are a professional, your account is now under review.";
            $redirectUrl = "login.php";

        // --- If it's an APPOINTMENT payment ---
        } elseif (isset($_POST['appointment_id'])) {
            $appointmentID = (int)$_POST['appointment_id'];

            $stmt = $conn->prepare("INSERT INTO transaction (appointmentID, amount, transactionType, status, timestamp, gatewayTransactionID) VALUES (?, ?, 'Appointment Fee', 'Completed', NOW(), ?)");
            $stmt->bind_param("ids", $appointmentID, $amount, $gatewayTransactionId);
            $stmt->execute();
            
            // Optionally, you could update the appointment status to 'Confirmed' here
            // $conn->prepare("UPDATE appointment SET status = 'Confirmed' WHERE appointmentID = ?")->execute([$appointmentID]);

            unset($_SESSION['pending_appointment_id']);
            $message = "Your appointment payment was successful. You can view the details in 'My Appointments'.";
            $redirectUrl = "patientAppointments.php";
        }
        $conn->commit();
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
    <title>Payment Status - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-dark-orchid { background-color: #9932CC; }
        .text-dark-orchid { color: #9932CC; }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">
    <div class="bg-white p-10 rounded-xl shadow-lg w-full max-w-md text-center">
    <?php if ($paymentVerified): ?>
        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
            <i class="fa-solid fa-check fa-2x text-green-600"></i>
        </div>
        <h1 class="text-3xl font-bold text-gray-800">Payment Successful!</h1>
        <p class="text-gray-600 mt-3"><?php echo htmlspecialchars($message); ?></p>
        <div class="mt-6 text-sm text-gray-500">
            <p>Transaction ID: <?php echo htmlspecialchars($gatewayTransactionId); ?></p>
        </div>
        <a href="<?php echo $redirectUrl; ?>" class="mt-8 inline-block w-full py-3 px-4 bg-dark-orchid text-white rounded-md font-semibold hover:bg-purple-700 transition">
            Continue
        </a>
    <?php else: ?>
        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
            <i class="fa-solid fa-times fa-2x text-red-600"></i>
        </div>
        <h1 class="text-3xl font-bold text-gray-800">Payment Failed</h1>
        <p class="text-gray-600 mt-3">
            There was an issue processing your payment. Please <a href="javascript:history.back()" class="text-dark-orchid hover:underline">try again</a> or contact support.
        </p>
    <?php endif; ?>
    </div>
</body>
</html>