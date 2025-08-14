<?php
session_start();

// Protect this page: ensure a user is pending completion
if (!isset($_SESSION['pending_user_id']) || $_SESSION['pending_user_role'] !== 'Patient') {
    header("Location: register.php");
    exit();
}

// --- Database Connection ---
$host = "localhost";
$username = "root";
$password = "";
$database = "healthcare";

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$userID = $_SESSION['pending_user_id'];
$errorMsg = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $age = $_POST['age'];
    $height = $_POST['height'];
    $weight = $_POST['weight'];
    $gender = $_POST['gender'];

    $sql = "UPDATE Patient SET age=?, height=?, weight=?, gender=? WHERE patientID=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iddsi", $age, $height, $weight, $gender, $userID);

    if ($stmt->execute()) {
        // Clear the session variables and redirect to login
        unset($_SESSION['pending_user_id']);
        unset($_SESSION['pending_user_role']);
        header("Location: login.php?registered=success");
        exit();
    } else {
        $errorMsg = "Failed to update profile. Please try again.";
    }
    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Complete Your Patient Profile - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex items-center justify-center min-h-screen bg-gray-100">
    <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-lg">
        <h2 class="text-3xl font-bold text-center mb-6 text-gray-800">Patient Details</h2>
        <form action="patientDetailsForm.php" method="POST" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700">Age</label><input type="number" name="age" class="w-full mt-1 p-2 border rounded"></div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700">Gender</label>
                    <div class="mt-2 flex items-center space-x-6">
                        <label class="inline-flex items-center">
                            <input type="radio" name="gender" value="Male" class="form-radio h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                            <span class="ml-2 text-gray-700">Male</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="gender" value="Female" class="form-radio h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                            <span class="ml-2 text-gray-700">Female</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="gender" value="Other" class="form-radio h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                            <span class="ml-2 text-gray-700">Other</span>
                        </label>
                    </div>
                </div>

                <div><label class="block text-sm font-medium text-gray-700">Height (cm)</label><input type="text" name="height" class="w-full mt-1 p-2 border rounded"></div>
                <div><label class="block text-sm font-medium text-gray-700">Weight (kg)</label><input type="text" name="weight" class="w-full mt-1 p-2 border rounded"></div>
            </div>
            <button type="submit" class="w-full mt-6 py-2 px-4 bg-indigo-600 text-white rounded-md font-semibold hover:bg-indigo-700 transition">
                Complete Registration
            </button>
        </form>
    </div>
</body>
</html>
