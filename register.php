<?php
session_start();

// --- Database Connection ---
$host = "localhost";
$username = "root";
$password = "";
$database = "healthcare"; // Your database name

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$errorMsg = "";
$showDetailsLink = false;
$detailsPageUrl = "";

// Handle the form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullName = trim($_POST['Name']);
    $email = trim($_POST['email']);
    $contactNo = trim($_POST['contactNo']);
    $userRole = trim($_POST['role']);
    $rawPassword = $_POST['password'];
    $profilePhotoPath = null; // Initialize as null

    // Check if email already exists
    $checkStmt = $conn->prepare("SELECT userID, role FROM Users WHERE email = ?");
    $checkStmt->bind_param("s", $email);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows > 0) {
        $existingUser = $result->fetch_assoc();
        $errorMsg = "This email is already registered. Please fill up your details to log in.";
        $showDetailsLink = true;

        // Set session for the details page
        $_SESSION['pending_user_id'] = $existingUser['userID'];
        $_SESSION['pending_user_role'] = $existingUser['role'];

        // Determine the correct details form URL
        switch ($existingUser['role']) {
            case 'Patient':
                $detailsPageUrl = "patientDetailsForm.php";
                break;
            case 'Doctor':
                $detailsPageUrl = "doctorDetailsForm.php";
                break;
            case 'Nutritionist':
                $detailsPageUrl = "nutritionistDetailsForm.php";
                break;
            case 'CareGiver':
                $detailsPageUrl = "careGiverDetailsForm.php";
                break;
        }

    } else {
        // --- Handle Profile Photo Upload ---
        if (isset($_FILES["profilePhoto"]) && $_FILES["profilePhoto"]["error"] === UPLOAD_ERR_OK) {
            $uploadDir = "uploads/";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $filename = time() . "_" . basename($_FILES["profilePhoto"]["name"]);
            $uploadPath = $uploadDir . $filename;

            if (move_uploaded_file($_FILES["profilePhoto"]["tmp_name"], $uploadPath)) {
                $profilePhotoPath = $uploadPath;
            }
        }
        
        // Start a transaction
        $conn->begin_transaction();

        try {
            // Hash the password
            $hashedPassword = password_hash($rawPassword, PASSWORD_DEFAULT);

            // 1. Insert into the main Users table
            $insertUserStmt = $conn->prepare("INSERT INTO Users (Name, email, contactNo, password, role, profilePhoto) VALUES (?, ?, ?, ?, ?, ?)");
            $insertUserStmt->bind_param("ssssss", $fullName, $email, $contactNo, $hashedPassword, $userRole, $profilePhotoPath);
            $insertUserStmt->execute();

            $newUserId = $insertUserStmt->insert_id;

            // 2. Insert into the role-specific profile table
            $profileSql = "";
            switch ($userRole) {
                case 'Patient':
                    $profileSql = "INSERT INTO Patient (patientID) VALUES (?)";
                    break;
                case 'Doctor':
                    $profileSql = "INSERT INTO Doctor (doctorID) VALUES (?)";
                    break;
                case 'Nutritionist':
                    $profileSql = "INSERT INTO Nutritionist (nutritionistID) VALUES (?)";
                    break;
                case 'CareGiver':
                    $profileSql = "INSERT INTO CareGiver (careGiverID) VALUES (?)";
                    break;
                case 'Admin':
                    $profileSql = "INSERT INTO Admin (adminID) VALUES (?)";
                    break;
            }

            if ($profileSql) {
                $insertProfileStmt = $conn->prepare($profileSql);
                $insertProfileStmt->bind_param("i", $newUserId);
                $insertProfileStmt->execute();
                $insertProfileStmt->close();
            }

            $conn->commit();

            $_SESSION['pending_user_id'] = $newUserId;
            $_SESSION['pending_user_role'] = $userRole;
            
            switch ($userRole) {
                case 'Patient':
                    header("Location: patientDetailsForm.php");
                    break;
                case 'Doctor':
                    header("Location: doctorDetailsForm.php");
                    break;
                case 'Nutritionist':
                    header("Location: nutritionistDetailsForm.php");
                    break;
                case 'CareGiver':
                    header("Location: careGiverDetailsForm.php");
                    break;
                default:
                    header("Location: login.php?registered=success");
                    break;
            }
            exit();

        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $errorMsg = "Registration failed. Please try again.";
        }
        $insertUserStmt->close();
    }
    $checkStmt->close();
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

        <?php if ($showDetailsLink): ?>
            <a href="<?php echo $detailsPageUrl; ?>"
               class="w-full block text-center py-3 px-4 bg-dark-orchid text-white rounded-md font-semibold hover:bg-dark-orchid-darker transition text-lg shadow-md hover:shadow-lg">
                Add Details
            </a>
        <?php else: ?>
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
                <!-- NEW: Profile Photo Input -->
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
        <?php endif; ?>

        <p class="text-sm text-gray-500 mt-6 text-center">
            Already have an account? <a href="login.php" class="text-dark-orchid hover:underline">Login here</a>
        </p>
    </div>
</body>
</html>
