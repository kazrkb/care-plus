<?php
session_start();
$conn = require_once 'config.php';

// --- Determine Payment Type and Fetch Details ---
$payment_type = null;
$payment_details = [];

// Check for a pending APPOINTMENT payment first
if (isset($_SESSION['pending_appointment_id'])) {
    $payment_type = 'Appointment Fee';
    $appointmentID = $_SESSION['pending_appointment_id'];
    
    $query = "
        SELECT 
            a.appointmentID as id, 
            u.Name as providerName,
            a.appointmentDate,
            COALESCE(d.consultationFees, n.consultationFees) as amount 
        FROM appointment a 
        JOIN users u ON a.providerID = u.userID 
        LEFT JOIN doctor d ON u.userID = d.doctorID 
        LEFT JOIN nutritionist n ON u.userID = n.nutritionistID 
        WHERE a.appointmentID = ?
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $appointmentID);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $payment_details = $result->fetch_assoc();
    }
    $stmt->close();
} 
// Else, check for a pending REGISTRATION payment
elseif (isset($_SESSION['pending_user_id'])) {
    $payment_type = 'Registration Fee';
    $userID = $_SESSION['pending_user_id'];
    
    $stmt = $conn->prepare("SELECT userID as id, role, Name FROM Users WHERE userID = ?");
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $fee = 0;
    switch ($user['role']) {
        case 'Patient': $fee = 100.00; break;
        case 'CareGiver': $fee = 500.00; break;
        case 'Doctor':
        case 'Nutritionist': $fee = 1000.00; break;
    }
    $payment_details = ['id' => $user['id'], 'providerName' => $user['Name'], 'amount' => $fee, 'role' => $user['role']];
}

// If no valid payment is pending, redirect away
if (empty($payment_details)) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Secure Payment - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-dark-orchid { background-color: #9932CC; }
        .text-dark-orchid { color: #9932CC; }
        .payment-tab { border-bottom: 3px solid transparent; transition: all 0.2s ease-in-out; }
        .payment-tab.active { border-bottom-color: #9932CC; color: #9932CC; }
        .form-input { border: 1px solid #d1d5db; padding: 0.75rem; border-radius: 0.375rem; width: 100%; transition: box-shadow 0.2s, border-color 0.2s; }
        .form-input:focus { outline: none; border-color: #9932CC; box-shadow: 0 0 0 3px rgba(153, 50, 204, 0.2); }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">
    <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-2xl grid lg:grid-cols-2 gap-8">
        <!-- Left Side: Order Summary -->
        <div class="lg:border-r lg:pr-8">
            <div class="text-center lg:text-left">
                <h1 class="text-2xl font-bold text-dark-orchid mb-2">CarePlus</h1>
                <h2 class="text-xl font-bold text-gray-800">Complete Your Payment</h2>
            </div>
            <div class="mt-8 bg-gray-50 p-6 rounded-lg">
                <h3 class="font-semibold text-lg mb-4">Payment Summary</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Payment For:</span>
                        <span class="font-medium text-gray-800"><?php echo htmlspecialchars($payment_type); ?></span>
                    </div>
                    <?php if ($payment_type === 'Registration Fee'): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Account Type:</span>
                            <span class="font-medium text-gray-800"><?php echo htmlspecialchars($payment_details['role']); ?></span>
                        </div>
                    <?php else: ?>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Provider:</span>
                            <span class="font-medium text-gray-800"><?php echo htmlspecialchars($payment_details['providerName']); ?></span>
                        </div>
                         <div class="flex justify-between">
                            <span class="text-gray-600">Date:</span>
                            <span class="font-medium text-gray-800"><?php echo date("M d, Y", strtotime($payment_details['appointmentDate'])); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                 <div class="flex justify-between items-baseline border-t-2 border-dashed mt-6 pt-6">
                    <span class="text-lg font-bold text-gray-800">Total Amount</span>
                    <span class="text-3xl font-bold text-dark-orchid">৳<?php echo number_format($payment_details['amount'], 2); ?></span>
                </div>
            </div>
             <div class="mt-4 text-center text-xs text-gray-400">
                <i class="fa-solid fa-shield-halved"></i> Secure Payment Gateway
            </div>
        </div>

        <!-- Right Side: Payment Method -->
        <div>
            <div class="flex border-b mb-6">
                <button type="button" id="mobile-tab" onclick="showPaymentForm('mobile')" class="payment-tab active flex-1 pb-2 font-semibold text-gray-500"><i class="fa-solid fa-mobile-screen-button mr-2"></i>Mobile Banking</button>
                <button type="button" id="card-tab" onclick="showPaymentForm('card')" class="payment-tab flex-1 pb-2 font-semibold text-gray-500"><i class="fa-regular fa-credit-card mr-2"></i>Card</button>
            </div>
            
            <form action="payment_success.php" method="POST">
                <!-- Hidden fields to pass payment details -->
                <?php if ($payment_type === 'Appointment Fee'): ?>
                    <input type="hidden" name="appointment_id" value="<?php echo $payment_details['id']; ?>">
                <?php else: ?>
                    <input type="hidden" name="user_id" value="<?php echo $payment_details['id']; ?>">
                <?php endif; ?>
                <input type="hidden" name="amount" value="<?php echo $payment_details['amount']; ?>">

                <div class="p-3 mb-4 bg-yellow-100 text-yellow-800 text-sm rounded-lg text-center"><i class="fa-solid fa-triangle-exclamation mr-1"></i><strong>This is a DEMO only.</strong> Do not use real financial information.</div>

                <!-- Mobile Banking Form -->
                <div id="mobile-form" class="space-y-4">
                    <p class="text-center text-gray-600">Pay with NexusPay</p>
                    <div><label for="nexus_number" class="text-sm font-medium text-gray-700">Your NexusPay Account Number</label><input type="tel" id="nexus_number" name="mobile_number" class="form-input mt-1" placeholder="e.g., 01712345678"></div>
                    <div><label for="nexus_pin" class="text-sm font-medium text-gray-700">PIN</label><input type="password" id="nexus_pin" name="mobile_pin" class="form-input mt-1" placeholder="••••"></div>
                    <button type="submit" class="w-full py-3 px-4 bg-purple-600 text-white rounded-md font-semibold hover:bg-purple-700 transition text-lg">Confirm Payment</button>
                </div>

                <!-- Card Form -->
                <div id="card-form" class="hidden space-y-4">
                    <p class="text-center text-gray-600">Pay with Visa or MasterCard</p>
                    <div><label for="card_number" class="text-sm font-medium text-gray-700">Card Number</label><input type="text" id="card_number" name="card_number" class="form-input mt-1" placeholder="0000 0000 0000 0000"></div>
                    <div class="grid grid-cols-2 gap-4">
                        <div><label for="expiry" class="text-sm font-medium text-gray-700">Expiry Date</label><input type="text" id="expiry" name="card_expiry" class="form-input mt-1" placeholder="MM / YY"></div>
                        <div><label for="cvc" class="text-sm font-medium text-gray-700">CVC</label><input type="text" id="cvc" name="card_cvc" class="form-input mt-1" placeholder="123"></div>
                    </div>
                    <button type="submit" class="w-full py-3 px-4 bg-blue-600 text-white rounded-md font-semibold hover:bg-blue-700 transition text-lg">Pay ৳<?php echo number_format($payment_details['amount'], 2); ?></button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const mobileTab = document.getElementById('mobile-tab');
        const cardTab = document.getElementById('card-tab');
        const mobileForm = document.getElementById('mobile-form');
        const cardForm = document.getElementById('card-form');
        const mobileInputs = mobileForm.querySelectorAll('input');
        const cardInputs = cardForm.querySelectorAll('input');

        function showPaymentForm(method) {
            if (method === 'mobile') {
                mobileTab.classList.add('active');
                cardTab.classList.remove('active');
                mobileForm.classList.remove('hidden');
                cardForm.classList.add('hidden');
                mobileInputs.forEach(input => input.required = true);
                cardInputs.forEach(input => input.required = false);
            } else {
                cardTab.classList.add('active');
                mobileTab.classList.remove('active');
                cardForm.classList.remove('hidden');
                mobileForm.classList.add('hidden');
                mobileInputs.forEach(input => input.required = false);
                cardInputs.forEach(input => input.required = true);
            }
        }
        document.addEventListener('DOMContentLoaded', () => { showPaymentForm('mobile'); });
    </script>
</body>
</html>