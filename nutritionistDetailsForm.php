<?php
session_start();

// Protect this page
if (!isset($_SESSION['pending_user_id']) || $_SESSION['pending_user_role'] !== 'Nutritionist') {
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

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $sql = "UPDATE Nutritionist SET specialty=?, yearsOfExp=?, consultationFees=?, nidNumber=?, degree=? WHERE nutritionistID=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sidsis",
        $_POST['specialty'], $_POST['yearsOfExp'], $_POST['consultationFees'],
        $_POST['nidNumber'], $_POST['degree'], $userID
    );

    if ($stmt->execute()) {
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
    <title>Complete Your Nutritionist Profile - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex items-center justify-center min-h-screen bg-gray-100">
    <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-lg">
        <h2 class="text-3xl font-bold text-center mb-6 text-gray-800">Nutritionist Details</h2>
        <form action="nutritionistDetailsForm.php" method="POST" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Specialty</label>
                    <select name="specialty" class="w-full mt-1 p-2 border rounded">
                        <option value="">-- Select Specialty --</option>
                        <option value="Weight Management">Weight Management</option>
                        <option value="Sports Nutrition">Sports Nutrition</option>
                        <option value="Pediatric Nutrition">Pediatric Nutrition</option>
                        <option value="Clinical Nutrition">Clinical Nutrition</option>
                        <option value="Public Health Nutrition">Public Health Nutrition</option>
                    </select>
                </div>
                <div><label class="block text-sm font-medium text-gray-700">Years of Experience</label><input type="number" name="yearsOfExp" class="w-full mt-1 p-2 border rounded"></div>
                <div><label class="block text-sm font-medium text-gray-700">Consultation Fees</label><input type="text" name="consultationFees" class="w-full mt-1 p-2 border rounded"></div>
                <div><label class="block text-sm font-medium text-gray-700">NID Number</label><input type="text" name="nidNumber" class="w-full mt-1 p-2 border rounded"></div>
                <div class="md:col-span-2"><label class="block text-sm font-medium text-gray-700">Degree</label><input type="text" name="degree" placeholder="e.g., MPH in Community Nutrition" class="w-full mt-1 p-2 border rounded"></div>
            </div>
            <button type="submit" class="w-full mt-6 py-2 px-4 bg-indigo-600 text-white rounded-md font-semibold hover:bg-indigo-700 transition">
                Complete Registration
            </button>
        </form>
    </div>
</body>
</html>
