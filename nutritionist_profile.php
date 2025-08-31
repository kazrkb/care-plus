<?php
session_start();
$conn = require_once 'config.php';

// Protect this page: allow only logged-in Nutritionists
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Nutritionist') {
    header("Location: login.php");
    exit();
}

$nutritionistID = $_SESSION['userID'];
$successMsg = "";
$errorMsg = "";

// --- Handle Profile Update (POST Request) ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Begin a transaction for updating two tables
    $conn->begin_transaction();

    try {
        // 1. Sanitize and retrieve form data
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $contactNo = trim($_POST['contactNo']);
        $specialty = trim($_POST['specialty']);
        $degree = trim($_POST['degree']);
        $yearsOfExp = (int)$_POST['yearsOfExp'];

        // Basic Validation
        if (empty($name) || empty($email)) {
            throw new Exception("Full Name and Email are required fields.");
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address.");
        }

        // 2. Prepare the UPDATE statement for the 'users' table
        $userQuery = "UPDATE users SET Name = ?, email = ?, contactNo = ? WHERE userID = ?";
        $userStmt = $conn->prepare($userQuery);
        $userStmt->bind_param("sssi", $name, $email, $contactNo, $nutritionistID);
        $userStmt->execute();

        // 3. Prepare the UPDATE statement for the 'nutritionist' table
        $nutritionistQuery = "UPDATE nutritionist SET specialty = ?, degree = ?, yearsOfExp = ? WHERE nutritionistID = ?";
        $nutritionistStmt = $conn->prepare($nutritionistQuery);
        // --- THIS LINE IS CORRECTED ---
        $nutritionistStmt->bind_param("ssii", $specialty, $degree, $yearsOfExp, $nutritionistID);
        $nutritionistStmt->execute();

        // 4. If both updates are successful, commit the transaction
        $conn->commit();
        $successMsg = "Profile updated successfully!";
        
        // IMPORTANT: Update session name in case it was changed
        $_SESSION['Name'] = $name;

    } catch (Exception $e) {
        // If any error occurs, roll back the transaction
        $conn->rollback();
        $errorMsg = "Error updating profile: " . $e->getMessage();
    }
}

// --- Fetch Current Profile Data (GET Request) ---
// Correctly JOIN users and nutritionist tables, removing the 'bio' column
$query = "
    SELECT 
        u.Name, u.email, u.contactNo, u.profilePhoto,
        n.specialty, n.degree, n.yearsOfExp, n.consultationFees
    FROM users u
    JOIN nutritionist n ON u.userID = n.nutritionistID
    WHERE u.userID = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $nutritionistID);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc();
$stmt->close();
$conn->close();

if (!$profile) {
    die("Error: Could not retrieve profile data. Make sure a corresponding entry exists in the 'nutritionist' table.");
}

// Update variables for display after potential POST update
$userName = $_SESSION['Name'];
$userAvatar = strtoupper(substr($userName, 0, 2));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-dark-orchid { background-color: #9932CC; }
        .text-dark-orchid { color: #9932CC; }
    </style>
</head>
<body class="bg-purple-50">
    <div class="flex min-h-screen">
        <aside class="w-64 bg-white border-r">
            <div class="p-6"><a href="nutritionistDashboard.php" class="text-2xl font-bold text-dark-orchid">CarePlus</a></div>
            <nav class="px-4">
                <a href="nutritionistDashboard.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-table-columns w-5"></i><span>Dashboard</span></a>
                <a href="nutritionist_profile.php" class="flex items-center space-x-3 px-4 py-3 bg-purple-100 text-dark-orchid rounded-lg"><i class="fa-regular fa-user w-5"></i><span>My Profile</span></a>
                <a href="nutritionist_schedule.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-calendar-days w-5"></i><span>Provide Schedule</span></a>
                <a href="nutritionist_consultations.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-laptop-medical w-5"></i><span>Consultations</span></a>
                <a href="#" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-utensils w-5"></i><span>Diet Plan</span></a>
                <a href="#" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-file-waveform w-5"></i><span>Patient History</span></a>
                <a href="#" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-money-bill-wave w-5"></i><span>Transactions</span></a>
                <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg mt-8"><i class="fa-solid fa-arrow-right-from-bracket w-5"></i><span>Logout</span></a>
            </nav>
        </aside>

        <main class="flex-1 p-8">
            <header class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-800">My Profile</h1>
                    <p class="text-gray-600 mt-1">Update your personal and professional information.</p>
                </div>
                 <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <p class="font-semibold text-slate-700"><?php echo htmlspecialchars($userName); ?></p>
                        <p class="text-sm text-gray-500">Nutritionist</p>
                    </div>
                     <?php if (!empty($profile['profilePhoto'])): ?>
                        <img src="<?php echo htmlspecialchars($profile['profilePhoto']); ?>" alt="Profile Photo" class="w-12 h-12 rounded-full object-cover">
                    <?php else: ?>
                        <div class="w-12 h-12 rounded-full bg-dark-orchid text-white flex items-center justify-center font-bold text-lg"><?php echo htmlspecialchars($userAvatar); ?></div>
                    <?php endif; ?>
                </div>
            </header>

            <?php if ($successMsg): ?><div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert"><p><?php echo $successMsg; ?></p></div><?php endif; ?>
            <?php if ($errorMsg): ?><div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert"><p><?php echo $errorMsg; ?></p></div><?php endif; ?>

            <div class="bg-white p-8 rounded-lg shadow-lg">
                <form method="POST" action="nutritionist_profile.php">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                        <div class="md:col-span-2"><h3 class="text-lg font-semibold text-slate-800 border-b pb-2">Personal Information</h3></div>
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">Full Name</label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($profile['Name']); ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($profile['email']); ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
                        </div>
                        <div>
                            <label for="contactNo" class="block text-sm font-medium text-gray-700">Contact Number</label>
                            <input type="text" id="contactNo" name="contactNo" value="<?php echo htmlspecialchars($profile['contactNo']); ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
                        </div>

                        <div class="md:col-span-2 mt-6"><h3 class="text-lg font-semibold text-slate-800 border-b pb-2">Professional Information</h3></div>
                         <div>
                            <label for="degree" class="block text-sm font-medium text-gray-700">Degree</label>
                            <input type="text" id="degree" name="degree" value="<?php echo htmlspecialchars($profile['degree'] ?? ''); ?>" placeholder="e.g., MSc in Nutrition" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
                        </div>
                        <div>
                            <label for="specialty" class="block text-sm font-medium text-gray-700">Specialty</label>
                            <input type="text" id="specialty" name="specialty" value="<?php echo htmlspecialchars($profile['specialty'] ?? ''); ?>" placeholder="e.g., Sports Nutrition" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
                        </div>
                         <div>
                            <label for="yearsOfExp" class="block text-sm font-medium text-gray-700">Years of Experience</label>
                            <input type="number" id="yearsOfExp" name="yearsOfExp" value="<?php echo htmlspecialchars($profile['yearsOfExp'] ?? 0); ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
                        </div>
                    </div>
                    
                    <div class="mt-8 pt-5 border-t border-gray-200 flex justify-end">
                        <button type="submit" class="px-6 py-2 bg-dark-orchid text-white rounded-lg font-semibold hover:bg-purple-700 transition">Save Changes</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>