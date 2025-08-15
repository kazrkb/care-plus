<?php
session_start();

// --- Database Connection ---
$conn = require_once 'config.php';

$errorMsg = "";
$successMsg = "";

// Check if the user was just redirected from the registration page
if (isset($_GET['registered']) && $_GET['registered'] === 'success') {
    $successMsg = "Registration successful! Please log in.";
}

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $inputPassword = $_POST['password'];

    // Prepare a statement to select the user from the database
    $stmt = $conn->prepare("SELECT userID, Name, password, role FROM Users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    // Check if a user with that email exists
    if ($stmt->num_rows === 1) {
        $stmt->bind_result($userID, $Name, $hashedPassword, $role);
        $stmt->fetch();

        // Verify the password
        if (password_verify($inputPassword, $hashedPassword)) {
            // Password is correct, so start a new session
            $_SESSION['userID'] = $userID;
            $_SESSION['Name'] = $Name;
            $_SESSION['role'] = $role;

            // --- REDIRECT TO ROLE-SPECIFIC DASHBOARD ---
            switch ($role) {
                case 'Patient':
                    header("Location: patientDashboard.php");
                    break;
                case 'Doctor':
                    header("Location: doctorDashboard.php");
                    break;
                case 'Nutritionist':
                    header("Location: nutritionistDashboard.php");
                    break;
                case 'CareGiver':
                    header("Location: careGiverDashboard.php");
                    break;
                case 'Admin':
                    header("Location: adminDashboard.php");
                    break;
                default:
                    $errorMsg = "Invalid user role.";
                    break;
            }
            exit();
        } else {
            $errorMsg = "Incorrect password.";
        }
    } else {
        $errorMsg = "No user found with that email address.";
    }
    $stmt->close();
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

