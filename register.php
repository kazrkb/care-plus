<?php
session_start();
$conn = require_once 'config.php';

$errorMsg = "";

// Handle the form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullName = trim($_POST['Name']);
    $email = trim($_POST['email']);
    $contactNo = trim($_POST['contactNo']);
    $userRole = trim($_POST['role']);
    $rawPassword = $_POST['password'];
    $profilePhotoPath = null;

    // --- Basic Validation ---
    if (empty($fullName) || empty($email) || empty($rawPassword) || empty($userRole)) {
        $errorMsg = "Please fill in all required fields.";
    } else {
        // Check if email already exists in the database
        $checkStmt = $conn->prepare("SELECT userID FROM Users WHERE email = ?");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $result = $checkStmt->get_result();

        if ($result->num_rows > 0) {
            $errorMsg = "This email is already registered. Please <a href='login.php' class='font-bold hover:underline'>login</a> instead.";
        } else {
            // --- Handle Profile Photo Upload ---
            // The photo is temporarily stored until the second step
            if (isset($_FILES["profilePhoto"]) && $_FILES["profilePhoto"]["error"] === UPLOAD_ERR_OK) {
                $uploadDir = "uploads/temp/"; // Use a temporary directory
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $filename = session_id() . "_" . basename($_FILES["profilePhoto"]["name"]);
                $uploadPath = $uploadDir . $filename;

                if (move_uploaded_file($_FILES["profilePhoto"]["tmp_name"], $uploadPath)) {
                    $profilePhotoPath = $uploadPath; // This is a temporary path
                }
            }

            // --- DATA IS NOT SAVED TO DB. Store in session instead. ---
            $_SESSION['registration_data'] = [
                'fullName' => $fullName,
                'email' => $email,
                'contactNo' => $contactNo,
                'password' => $rawPassword, // Store raw password temporarily
                'role' => $userRole,
                'profilePhotoTempPath' => $profilePhotoPath
            ];

            // Redirect to the appropriate details form
            switch ($userRole) {
                case 'Patient':
                    header("Location: patientDetailsForm.php");
                    break;
                case 'Doctor':
                    header("Location: doctorDetailsForm.php");
                    break;
                // ... add cases for Nutritionist and CareGiver
                default:
                    // For roles with no second step (like Admin)
                    // You would have a separate script to handle the final save
                    // For now, redirecting to login.
                    header("Location: login.php");
                    break;
            }
            exit();
        }
        $checkStmt->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - CarePlus</title>
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
            <h2 class="text-3xl font-bold text-gray-800">Create Your Account</h2>
        </div>

        <?php if ($errorMsg): ?>
            <div class="mb-4 p-3 bg-red-100 text-red-700 rounded-md"><?php echo $errorMsg; ?></div>
        <?php endif; ?>

        <form action="register.php" method="POST" enctype="multipart/form-data" class="space-y-5">
            <div>
                <label class="block text-sm font-medium text-gray-700">Full Name</label>
                <input type="text" name="Name" required placeholder="Enter your full name"
                       class="w-full mt-1 p-2 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Email</label>
                <input type="email" name="email" required placeholder="your@email.com"
                       class="w-full mt-1 p-2 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500">
            </div>
             <div>
                <label class="block text-sm font-medium text-gray-700">Contact Number</label>
                <input type="tel" name="contactNo" placeholder="Your phone number"
                       class="w-full mt-1 p-2 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Password</label>
                <input type="password" name="password" required placeholder="••••••••"
                       class="w-full mt-1 p-2 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Profile Photo</label>
                <input type="file" name="profilePhoto" accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Register as</label>
                <select name="role" required class="w-full mt-1 p-2 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500">
                    <option value="">-- Select a Role --</option>
                    <option value="Patient">Patient</option>
                    <option value="Doctor">Doctor</option>
                    <option value="Nutritionist">Nutritionist</option>
                    <option value="CareGiver">CareGiver</option>
                    <option value="Admin">Admin</option>
                </select>
            </div>
            <button type="submit"
                    class="w-full py-3 px-4 bg-dark-orchid text-white rounded-md font-semibold hover:bg-dark-orchid-darker transition text-lg shadow-md hover:shadow-lg">
                Continue
            </button>
        </form>

        <p class="text-sm text-gray-500 mt-6 text-center">
            Already have an account? <a href="login.php" class="text-dark-orchid hover:underline">Login here</a>
        </p>
    </div>
</body>
</html>