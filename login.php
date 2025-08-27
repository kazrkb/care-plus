<?php
session_start();
$conn = require_once 'config.php';
date_default_timezone_set('Asia/Dhaka');

$errorMsg = "";
$successMsg = ""; 

// Add a success message for pending admin registration
if (isset($_GET['registered']) && $_GET['registered'] === 'admin_pending') {
    $successMsg = "Admin account created! It is now pending approval from a super-administrator.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $inputPassword = $_POST['password'];

    $stmt = $conn->prepare("SELECT userID, Name, password, role, verification_status, payment_status FROM Users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($userID, $Name, $hashedPassword, $role, $verification_status, $payment_status);
        $stmt->fetch();
        $stmt->close(); 

        if (password_verify($inputPassword, $hashedPassword)) {
            $detailsSubmitted = true; 
            $redirectUrl = "";

            // STEP 1: Check if a professional has submitted their details (skip for Admins and Patients)
            if (in_array($role, ['Doctor', 'Nutritionist', 'CareGiver'])) {
                $detailsQuery = "";
                if ($role === 'Doctor') $detailsQuery = "SELECT specialty FROM doctor WHERE doctorID = ?";
                if ($role === 'Nutritionist') $detailsQuery = "SELECT specialty FROM nutritionist WHERE nutritionistID = ?";
                if ($role === 'CareGiver') $detailsQuery = "SELECT careGiverType FROM caregiver WHERE careGiverID = ?";
                
                if ($detailsQuery) {
                    $detailsStmt = $conn->prepare($detailsQuery);
                    $detailsStmt->bind_param("i", $userID);
                    $detailsStmt->execute();
                    $detailsResult = $detailsStmt->get_result()->fetch_assoc();
                    if (!$detailsResult || empty(current($detailsResult))) {
                        $detailsSubmitted = false;
                    }
                    $detailsStmt->close();
                }

                if (!$detailsSubmitted) {
                    $_SESSION['pending_user_id'] = $userID;
                    $_SESSION['pending_user_role'] = $role;
                    switch ($role) {
                        case 'Doctor': $redirectUrl = "doctorDetailsForm.php"; break;
                        case 'Nutritionist': $redirectUrl = "nutritionistDetailsForm.php"; break;
                        case 'CareGiver': $redirectUrl = "careGiverDetailsForm.php"; break;
                    }
                    header("Location: " . $redirectUrl);
                    exit();
                }
            }

            // STEP 2: Check for payment (Admins are exempt)
            if ($payment_status === 'Unpaid') {
                $_SESSION['pending_user_id'] = $userID; 
                header("Location: payment.php");
                exit();
            } 
            
            // STEP 3: For non-patients, check for admin approval
            elseif ($role !== 'Patient' && $verification_status === 'Pending') {
                $errorMsg = "Your account is awaiting approval from an administrator.";
            } 
            elseif ($role !== 'Patient' && $verification_status === 'Rejected') {
                $errorMsg = "Your account application has been rejected. Please contact support.";
            } 
            
            // STEP 4: If all checks pass, log the user in
            elseif ($verification_status === 'Approved' && $payment_status === 'Paid') {
                $_SESSION['userID'] = $userID;
                $_SESSION['Name'] = $Name;
                $_SESSION['role'] = $role;

                $redirectUrl = "login.php";
                switch ($role) {
                    case 'Patient': $redirectUrl = "patientDashboard.php"; break;
                    case 'Doctor': $redirectUrl = "doctorDashboard.php"; break;
                    case 'Nutritionist': $redirectUrl = "nutritionistDashboard.php"; break;
                    case 'CareGiver': $redirectUrl = "careGiverDashboard.php"; break;
                    case 'Admin': $redirectUrl = "adminDashboard.php"; break;
                }
                header("Location: " . $redirectUrl);
                exit();
            } else {
                 $errorMsg = "Your account is inactive. Please contact support.";
            }
        } else {
            $errorMsg = "Incorrect password.";
        }
    } else {
        $errorMsg = "No user found with that email address.";
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-dark-orchid { background-color: #9932CC; }
        .hover\:bg-dark-orchid-darker:hover { background-color: #8A2BE2; }
        .text-dark-orchid { color: #9932CC; }
    </style>
</head>
<body class="bg-purple-50 flex items-center justify-center min-h-screen p-4">
    <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-dark-orchid mb-2">CarePlus</h1>
            <h2 class="text-3xl font-bold text-gray-800">Welcome Back!</h2>
            <p class="text-gray-500">Please log in to your account.</p>
        </div>

        <?php if ($successMsg): ?>
            <div class="mb-4 p-3 bg-green-100 text-green-700 rounded-md"><?php echo $successMsg; ?></div>
        <?php endif; ?>

        <?php if ($errorMsg): ?>
            <div class="mb-4 p-3 bg-red-100 text-red-700 rounded-md"><?php echo $errorMsg; ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST" class="space-y-5">
            <div>
                <label class="block text-sm font-medium text-gray-700">Email</label>
                <input type="email" name="email" required placeholder="your@email.com"
                       class="w-full mt-1 p-2 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Password</label>
                <input type="password" name="password" required placeholder="••••••••"
                       class="w-full mt-1 p-2 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500">
            </div>
            <button type="submit"
                    class="w-full py-3 px-4 bg-dark-orchid text-white rounded-md font-semibold hover:bg-dark-orchid-darker transition text-lg shadow-md hover:shadow-lg">
                Login
            </button>
        </form>

        <p class="text-sm text-gray-500 mt-6 text-center">
            Don’t have an account? <a href="register.php" class="text-dark-orchid hover:underline">Register here</a>
        </p>
    </div>
</body>
</html>