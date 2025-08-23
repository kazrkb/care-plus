<?php
session_start();

// Protect this page: allow only logged-in Patients
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Patient') {
    header("Location: login.php");
    exit();
}

// --- Database Connection ---
$conn = require_once 'config.php';

$userName = $_SESSION['Name'];
$userID = $_SESSION['userID'];
$userAvatar = strtoupper(substr($userName, 0, 2));

$successMsg = "";
$errorMsg = "";

// Handle form submission for profile update
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $updateType = $_POST['update_type'] ?? 'health';
    
    if ($updateType === 'personal') {
        // Handle personal information update
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $contactNo = trim($_POST['contactNo']);
        $photoPath = null;
        $photoUploadError = null;
        
        // Handle photo removal
        $removePhoto = isset($_POST['removePhoto']) && $_POST['removePhoto'] === '1';
        
        // Handle photo upload
        if (isset($_FILES['profilePhoto']) && $_FILES['profilePhoto']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/';
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $maxSize = 5 * 1024 * 1024; // 5MB
            
            $fileType = $_FILES['profilePhoto']['type'];
            $fileSize = $_FILES['profilePhoto']['size'];
            $fileName = $_FILES['profilePhoto']['name'];
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            // Validate file type
            if (!in_array($fileType, $allowedTypes)) {
                $photoUploadError = "Only JPG, PNG, WebP, and GIF files are allowed.";
            }
            // Validate file size
            elseif ($fileSize > $maxSize) {
                $photoUploadError = "File size must be less than 5MB.";
            }
            else {
                // Generate unique filename
                $newFileName = 'profile_' . $userID . '_' . time() . '.' . $fileExt;
                $targetPath = $uploadDir . $newFileName;
                
                // Create uploads directory if it doesn't exist
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                // Move uploaded file
                if (move_uploaded_file($_FILES['profilePhoto']['tmp_name'], $targetPath)) {
                    $photoPath = $targetPath;
                } else {
                    $photoUploadError = "Failed to upload photo. Please try again.";
                }
            }
        }
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMsg = "Please enter a valid email address.";
        } elseif ($photoUploadError) {
            $errorMsg = $photoUploadError;
        } else {
            // Check if email is already taken by another user
            $emailCheckQuery = "SELECT userID FROM users WHERE email = ? AND userID != ?";
            $emailCheckStmt = $conn->prepare($emailCheckQuery);
            $emailCheckStmt->bind_param("si", $email, $userID);
            $emailCheckStmt->execute();
            $emailCheckResult = $emailCheckStmt->get_result();
            
            if ($emailCheckResult->num_rows > 0) {
                $errorMsg = "This email address is already taken by another user.";
            } else {
                // Update personal information in users table
                if ($photoPath) {
                    // Update with new photo
                    $updateUserQuery = "UPDATE users SET Name=?, email=?, contactNo=?, profilePhoto=? WHERE userID=?";
                    $updateUserStmt = $conn->prepare($updateUserQuery);
                    $updateUserStmt->bind_param("ssssi", $name, $email, $contactNo, $photoPath, $userID);
                } elseif ($removePhoto) {
                    // Remove photo
                    $updateUserQuery = "UPDATE users SET Name=?, email=?, contactNo=?, profilePhoto=NULL WHERE userID=?";
                    $updateUserStmt = $conn->prepare($updateUserQuery);
                    $updateUserStmt->bind_param("sssi", $name, $email, $contactNo, $userID);
                } else {
                    // Update without changing photo
                    $updateUserQuery = "UPDATE users SET Name=?, email=?, contactNo=? WHERE userID=?";
                    $updateUserStmt = $conn->prepare($updateUserQuery);
                    $updateUserStmt->bind_param("sssi", $name, $email, $contactNo, $userID);
                }
                
                if ($updateUserStmt->execute()) {
                    $message = "Personal information updated successfully!";
                    if ($photoPath) {
                        $message .= " Profile photo uploaded.";
                    } elseif ($removePhoto) {
                        $message .= " Profile photo removed.";
                    }
                    $successMsg = $message;
                    // Update session name if it was changed
                    $_SESSION['Name'] = $name;
                    $userName = $name;
                    $userAvatar = strtoupper(substr($userName, 0, 2));
                    
                    // Refresh user info to get updated photo
                    $userQuery = "SELECT Name, email, contactNo, profilePhoto FROM Users WHERE userID = ?";
                    $userStmt = $conn->prepare($userQuery);
                    $userStmt->bind_param("i", $userID);
                    $userStmt->execute();
                    $userResult = $userStmt->get_result();
                    $userInfo = $userResult->fetch_assoc();
                } else {
                    $errorMsg = "Failed to update personal information. Please try again.";
                }
                $updateUserStmt->close();
            }
            $emailCheckStmt->close();
        }
    } else {
        // Handle health information update
        $age = $_POST['age'];
        $height = $_POST['height'];
        $weight = $_POST['weight'];
        $gender = $_POST['gender'];

        // Check if patient record exists
        $checkQuery = "SELECT patientID FROM Patient WHERE patientID = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("i", $userID);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            // Update existing record
            $updateQuery = "UPDATE Patient SET age=?, height=?, weight=?, gender=? WHERE patientID=?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param("iddsi", $age, $height, $weight, $gender, $userID);
        } else {
            // Insert new record
            $updateQuery = "INSERT INTO Patient (patientID, age, height, weight, gender) VALUES (?, ?, ?, ?, ?)";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param("iidds", $userID, $age, $height, $weight, $gender);
        }

        if ($updateStmt->execute()) {
            $successMsg = "Health information updated successfully!";
        } else {
            $errorMsg = "Failed to update health information. Please try again.";
        }
        $updateStmt->close();
        $checkStmt->close();
    }
}

// Get current patient info
$patientQuery = "SELECT age, height, weight, gender FROM Patient WHERE patientID = ?";
$patientStmt = $conn->prepare($patientQuery);
$patientStmt->bind_param("i", $userID);
$patientStmt->execute();
$patientResult = $patientStmt->get_result();
$patientInfo = $patientResult->fetch_assoc();

// Get user info from Users table
$userQuery = "SELECT Name, email, contactNo, profilePhoto FROM Users WHERE userID = ?";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("i", $userID);
$userStmt->execute();
$userResult = $userStmt->get_result();
$userInfo = $userResult->fetch_assoc();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-dark-orchid { background-color: #9932CC; }
        .text-dark-orchid { color: #9932CC; }
        .shadow-orchid-custom { box-shadow: 0 4px 6px -1px rgba(153, 50, 204, 0.1), 0 2px 4px -2px rgba(153, 50, 204, 0.1); }
    </style>
</head>
<body class="bg-purple-50">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="w-64 bg-white border-r">
            <div class="p-6">
                <a href="patientDashboard.php" class="text-2xl font-bold text-dark-orchid">CarePlus</a>
            </div>
            <nav class="px-4 space-y-2">
                <a href="patientDashboard.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg">
                    <i class="fa-solid fa-table-columns w-5"></i>
                    <span>Dashboard</span>
                </a>
                <a href="patientProfile.php" class="flex items-center space-x-3 px-4 py-3 bg-purple-100 text-dark-orchid rounded-lg">
                    <i class="fa-regular fa-user w-5"></i>
                    <span>My Profile</span>
                </a>
                <a href="patientAppointments.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg">
                    <i class="fa-solid fa-calendar-days w-5"></i>
                    <span>My Appointments</span>
                </a>
                <a href="caregiverBooking.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg">
                    <i class="fa-solid fa-hands-holding-child w-5"></i>
                    <span>Caregiver Bookings</span>
                </a>
                <a href="patientMedicalHistory.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg">
                    <i class="fa-solid fa-file-medical w-5"></i>
                    <span>Medical History</span>
                </a>
                <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg mt-8">
                    <i class="fa-solid fa-arrow-right-from-bracket w-5"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <!-- Header -->
            <header class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-800">My Profile</h1>
                    <p class="text-gray-600 mt-1">Manage your personal and health information</p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <p class="font-semibold text-slate-700"><?php echo htmlspecialchars($userName); ?></p>
                        <p class="text-sm text-gray-500">Patient</p>
                    </div>
                    <div class="w-12 h-12 rounded-full overflow-hidden">
                        <?php if ($userInfo['profilePhoto'] && file_exists($userInfo['profilePhoto'])): ?>
                            <img src="<?php echo htmlspecialchars($userInfo['profilePhoto']); ?>" alt="Profile Photo" class="w-full h-full object-cover">
                        <?php else: ?>
                            <div class="w-full h-full bg-dark-orchid text-white flex items-center justify-center font-bold text-lg">
                                <?php echo htmlspecialchars($userAvatar); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </header>

            <!-- Modern Notifications -->
            <?php if ($successMsg): ?>
            <div class="alert-notification fixed top-4 right-4 z-50 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg transform translate-x-0 transition-all duration-300 ease-in-out" id="success-alert">
                <div class="flex items-center">
                    <i class="fa-solid fa-check-circle mr-2"></i>
                    <span class="text-sm font-medium"><?php echo $successMsg; ?></span>
                    <button onclick="dismissAlert('success-alert')" class="ml-4 text-green-200 hover:text-white">
                        <i class="fa-solid fa-times"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($errorMsg): ?>
            <div class="alert-notification fixed top-4 right-4 z-50 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg transform translate-x-0 transition-all duration-300 ease-in-out" id="error-alert">
                <div class="flex items-center">
                    <i class="fa-solid fa-exclamation-circle mr-2"></i>
                    <span class="text-sm font-medium"><?php echo $errorMsg; ?></span>
                    <button onclick="dismissAlert('error-alert')" class="ml-4 text-red-200 hover:text-white">
                        <i class="fa-solid fa-times"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Personal Information (Editable) -->
                <div class="bg-white p-6 rounded-lg shadow-orchid-custom">
                    <h3 class="text-xl font-bold text-slate-800 mb-6">Personal Information</h3>
                    <form method="POST" enctype="multipart/form-data" class="space-y-4">
                        <input type="hidden" name="update_type" value="personal">
                        
                        <!-- Current Profile Photo Display -->
                        <div class="flex items-center space-x-4 mb-4">
                            <div class="w-20 h-20 rounded-full overflow-hidden">
                                <?php if ($userInfo['profilePhoto'] && file_exists($userInfo['profilePhoto'])): ?>
                                    <img src="<?php echo htmlspecialchars($userInfo['profilePhoto']); ?>" alt="Profile Photo" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="w-full h-full bg-dark-orchid text-white flex items-center justify-center font-bold text-2xl">
                                        <?php echo htmlspecialchars($userAvatar); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-700">Current Profile Photo</p>
                                <p class="text-xs text-gray-500">
                                    <?php echo $userInfo['profilePhoto'] ? 'Photo uploaded' : 'No photo uploaded'; ?>
                                </p>
                            </div>
                        </div>
                        
                        <!-- Profile Photo Upload -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Upload New Profile Photo</label>
                            <input type="file" name="profilePhoto" accept="image/jpeg,image/png,image/webp,image/gif" class="w-full mt-1 p-3 border rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                            <p class="text-xs text-gray-500 mt-1">Accepted formats: JPG, PNG, WebP, GIF. Max size: 5MB</p>
                            
                            <!-- Remove photo option (only show if user has a photo) -->
                            <?php if ($userInfo['profilePhoto']): ?>
                            <div class="mt-2">
                                <label class="flex items-center space-x-2">
                                    <input type="checkbox" name="removePhoto" value="1" class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                                    <span class="text-sm text-gray-600">Remove current profile photo</span>
                                </label>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Full Name</label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($userInfo['Name'] ?? ''); ?>" class="w-full mt-1 p-3 border rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500" required minlength="2" maxlength="100">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Email</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($userInfo['email'] ?? ''); ?>" class="w-full mt-1 p-3 border rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500" required maxlength="100">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Contact Number</label>
                            <input type="tel" name="contactNo" value="<?php echo htmlspecialchars($userInfo['contactNo'] ?? ''); ?>" class="w-full mt-1 p-3 border rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500" maxlength="20" pattern="[0-9+\-\s]+">
                        </div>
                        <button type="submit" class="w-full bg-dark-orchid text-white py-3 rounded-lg hover:bg-purple-700 transition duration-200 font-semibold">
                            <i class="fa-solid fa-save mr-2"></i>Update Personal Information
                        </button>
                    </form>
                </div>

                <!-- Health Information (Editable) -->
                <div class="bg-white p-6 rounded-lg shadow-orchid-custom">
                    <h3 class="text-xl font-bold text-slate-800 mb-6">Health Information</h3>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="update_type" value="health">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Age</label>
                                <input type="number" name="age" value="<?php echo htmlspecialchars($patientInfo['age'] ?? ''); ?>" class="w-full mt-1 p-3 border rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500" min="1" max="120" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Gender</label>
                                <select name="gender" class="w-full mt-1 p-3 border rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male" <?php echo ($patientInfo['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo ($patientInfo['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo ($patientInfo['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Height (cm)</label>
                                <input type="number" name="height" value="<?php echo htmlspecialchars($patientInfo['height'] ?? ''); ?>" class="w-full mt-1 p-3 border rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500" min="50" max="300" step="0.1" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Weight (kg)</label>
                                <input type="number" name="weight" value="<?php echo htmlspecialchars($patientInfo['weight'] ?? ''); ?>" class="w-full mt-1 p-3 border rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500" min="20" max="500" step="0.1" required>
                            </div>
                        </div>
                        <button type="submit" class="w-full bg-dark-orchid text-white py-3 rounded-lg hover:bg-purple-700 transition duration-200 font-semibold">
                            <i class="fa-solid fa-save mr-2"></i>Update Health Information
                        </button>
                    </form>
                </div>
            </div>

            <!-- BMI Calculation -->
            <?php if ($patientInfo && $patientInfo['height'] && $patientInfo['weight']): ?>
            <?php 
            $heightInMeters = $patientInfo['height'] / 100;
            $bmi = round($patientInfo['weight'] / ($heightInMeters * $heightInMeters), 1);
            $bmiCategory = '';
            $bmiColor = '';
            $bmiIcon = '';
            if ($bmi < 18.5) { 
                $bmiCategory = 'Underweight'; 
                $bmiColor = 'text-blue-600'; 
                $bmiIcon = 'fa-arrow-down';
            } elseif ($bmi < 25) { 
                $bmiCategory = 'Normal'; 
                $bmiColor = 'text-green-600'; 
                $bmiIcon = 'fa-check';
            } elseif ($bmi < 30) { 
                $bmiCategory = 'Overweight'; 
                $bmiColor = 'text-yellow-600'; 
                $bmiIcon = 'fa-arrow-up';
            } else { 
                $bmiCategory = 'Obese'; 
                $bmiColor = 'text-red-600'; 
                $bmiIcon = 'fa-exclamation-triangle';
            }
            ?>
            <div class="mt-8 bg-white p-6 rounded-lg shadow-orchid-custom">
                <h3 class="text-xl font-bold text-slate-800 mb-6">Health Metrics</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="text-center p-4 bg-gradient-to-r from-purple-50 to-pink-50 rounded-lg border border-purple-200">
                        <i class="fa-solid fa-calculator fa-2x text-purple-600 mb-2"></i>
                        <p class="text-gray-700 font-medium">BMI</p>
                        <p class="text-2xl font-bold text-slate-800"><?php echo $bmi; ?></p>
                    </div>
                    <div class="text-center p-4 bg-gradient-to-r from-blue-50 to-cyan-50 rounded-lg border border-blue-200">
                        <i class="fa-solid <?php echo $bmiIcon; ?> fa-2x <?php echo $bmiColor; ?> mb-2"></i>
                        <p class="text-gray-700 font-medium">Category</p>
                        <p class="text-lg font-bold <?php echo $bmiColor; ?>"><?php echo $bmiCategory; ?></p>
                    </div>
                    <div class="text-center p-4 bg-gradient-to-r from-green-50 to-emerald-50 rounded-lg border border-green-200">
                        <i class="fa-solid fa-heart fa-2x text-green-600 mb-2"></i>
                        <p class="text-gray-700 font-medium">Status</p>
                        <p class="text-lg font-bold text-green-600">Healthy</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Auto-dismiss notifications after 2 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const notifications = document.querySelectorAll('.alert-notification');
            notifications.forEach(function(notification) {
                setTimeout(function() {
                    dismissAlert(notification.id);
                }, 2000);
            });
        });

        // Notification Functions
        function dismissAlert(alertId) {
            const alert = document.getElementById(alertId);
            if (alert) {
                alert.style.transform = 'translateX(100%)';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 300);
            }
        }
    </script>
</body>
</html>
