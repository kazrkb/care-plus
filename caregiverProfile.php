<?php
session_start();

// Protect this page: allow only logged-in Caregivers
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'CareGiver') {
    header("Location: login.php");
    exit();
}

// --- Database Connection ---
$conn = require_once 'config.php';

$caregiverID = $_SESSION['userID'];
$userName = $_SESSION['Name'];
$userAvatar = strtoupper(substr($userName, 0, 2));

$successMsg = "";
$errorMsg = "";

// Helper function to handle all file uploads (profile photos and documents)
function handleFileUpload($fileKey, $columnName, $caregiverID, $isProfilePhoto, $conn) {
    if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] == UPLOAD_ERR_OK) {
        $uploadDir = $isProfilePhoto ? 'uploads/' : 'uploads/docs/';
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $allowedTypes = $isProfilePhoto
            ? ['image/jpeg', 'image/png', 'image/webp'] // Stricter types for photos
            : ['image/jpeg', 'image/png', 'image/webp', 'application/pdf']; // Allow PDF for docs
        $maxFileSize = 5 * 1024 * 1024; // 5 MB

        $file = $_FILES[$fileKey];
        $fileType = mime_content_type($file['tmp_name']);

        if (!in_array($fileType, $allowedTypes)) {
            return ['error' => "Invalid file type for $fileKey. Allowed: " . implode(', ', $allowedTypes)];
        }
        if ($file['size'] > $maxFileSize) {
            return ['error' => "File for $fileKey is too large. Maximum size is 5 MB."];
        }

        $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $prefix = $isProfilePhoto ? 'profile' : strtolower($fileKey);
        $newFileName = $prefix . '_' . $caregiverID . '_' . time() . '.' . $fileExtension;
        $newFilePath = $uploadDir . $newFileName;

        // Fetch old file path to delete it later
        $table = $isProfilePhoto ? 'users' : 'caregiver';
        $idColumn = $isProfilePhoto ? 'userID' : 'careGiverID';
        $oldFileQuery = "SELECT $columnName FROM $table WHERE $idColumn = ?";
        $oldStmt = $conn->prepare($oldFileQuery);
        $oldStmt->bind_param("i", $caregiverID);
        $oldStmt->execute();
        $oldFilePath = $oldStmt->get_result()->fetch_assoc()[$columnName] ?? null;

        if (move_uploaded_file($file['tmp_name'], $newFilePath)) {
            if ($oldFilePath && file_exists($oldFilePath)) {
                unlink($oldFilePath);
            }
            return ['path' => $newFilePath];
        } else {
            return ['error' => "Failed to upload $fileKey."];
        }
    }
    return ['path' => null];
}

// --- Handle Profile Update ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $updatePaths = [];

    // Handle all file uploads first
    $fileUploads = [
        'profilePhoto'      => ['column' => 'profilePhoto', 'is_photo' => true],
        'nidCopy'          => ['column' => 'nidCopyURL', 'is_photo' => false],
        'certificationDoc' => ['column' => 'certificationURL', 'is_photo' => false]
    ];

    foreach ($fileUploads as $fileKey => $details) {
        $result = handleFileUpload($fileKey, $details['column'], $caregiverID, $details['is_photo'], $conn);
        if (isset($result['error'])) {
            $errorMsg = $result['error'];
            break; // Stop on first error
        }
        if ($result['path']) {
            $updatePaths[$details['column']] = $result['path'];
        }
    }

    // --- Update Database (only if there were no upload errors) ---
    if (empty($errorMsg)) {
        $conn->begin_transaction();
        try {
            // 1. Update the 'users' table
            $updateUserSql = "UPDATE users SET Name = ?, contactNo = ? " . (isset($updatePaths['profilePhoto']) ? ", profilePhoto = ?" : "") . " WHERE userID = ?";
            $userStmt = $conn->prepare($updateUserSql);
            if (isset($updatePaths['profilePhoto'])) {
                $userStmt->bind_param("sssi", $_POST['Name'], $_POST['contactNo'], $updatePaths['profilePhoto'], $caregiverID);
            } else {
                $userStmt->bind_param("ssi", $_POST['Name'], $_POST['contactNo'], $caregiverID);
            }
            $userStmt->execute();

            // 2. Check if caregiver record exists, if not create one
            $checkCaregiverSql = "SELECT careGiverID FROM caregiver WHERE careGiverID = ?";
            $checkStmt = $conn->prepare($checkCaregiverSql);
            $checkStmt->bind_param("i", $caregiverID);
            $checkStmt->execute();
            $caregiverExists = $checkStmt->get_result()->num_rows > 0;
            
            if ($caregiverExists) {
                // Update existing caregiver record
                $caregiverUpdateParts = [
                    "careGiverType = ?", "certifications = ?", "dailyRate = ?", "weeklyRate = ?",
                    "monthlyRate = ?", "nidNumber = ?"
                ];
                $caregiverParams = [
                    $_POST['careGiverType'], $_POST['certifications'], $_POST['dailyRate'], 
                    $_POST['weeklyRate'], $_POST['monthlyRate'], $_POST['nidNumber']
                ];
                $caregiverTypes = "ssddds";

                foreach (['nidCopyURL', 'certificationURL'] as $docColumn) {
                    if (isset($updatePaths[$docColumn])) {
                        $caregiverUpdateParts[] = "$docColumn = ?";
                        $caregiverParams[] = $updatePaths[$docColumn];
                        $caregiverTypes .= "s";
                    }
                }
                $updateCaregiverSql = "UPDATE caregiver SET " . implode(", ", $caregiverUpdateParts) . " WHERE careGiverID = ?";
                $caregiverParams[] = $caregiverID;
                $caregiverTypes .= "i";
                $caregiverStmt = $conn->prepare($updateCaregiverSql);
                $caregiverStmt->bind_param($caregiverTypes, ...$caregiverParams);
            } else {
                // Insert new caregiver record
                $insertCaregiverSql = "INSERT INTO caregiver (careGiverID, careGiverType, certifications, dailyRate, weeklyRate, monthlyRate, nidNumber, nidCopyURL, certificationURL) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $nidCopyPath = $updatePaths['nidCopyURL'] ?? null;
                $certificationPath = $updatePaths['certificationURL'] ?? null;
                $caregiverStmt = $conn->prepare($insertCaregiverSql);
                $caregiverStmt->bind_param("issdddss", $caregiverID, $_POST['careGiverType'], $_POST['certifications'], $_POST['dailyRate'], $_POST['weeklyRate'], $_POST['monthlyRate'], $_POST['nidNumber'], $nidCopyPath, $certificationPath);
            }
            $caregiverStmt->execute();
            
            $conn->commit();
            $_SESSION['Name'] = $_POST['Name'];
            $userName = $_SESSION['Name'];
            $successMsg = "Profile updated successfully!";
        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $errorMsg = "Error updating profile: " . $exception->getMessage();
            // If DB update fails, delete any newly uploaded files
            foreach ($updatePaths as $path) {
                if ($path && file_exists($path)) unlink($path);
            }
        }
    }
}

// --- Fetch Caregiver's Current Profile Data ---
$query = "SELECT u.Name, u.email, u.contactNo, u.profilePhoto, c.* FROM users u LEFT JOIN caregiver c ON u.userID = c.careGiverID WHERE u.userID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $caregiverID);
$stmt->execute();
$result = $stmt->get_result();
$caregiverData = $result->fetch_assoc();
if (!$caregiverData) {
    die("Error: Could not retrieve caregiver profile data.");
}
$stmt->close();
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
        .form-input { margin-top: 0.25rem; display: block; width: 100%; padding: 0.5rem; border-width: 1px; border-color: #D1D5DB; border-radius: 0.375rem; box-shadow: sm; }
        .form-input:focus { ring-color: #9932CC; border-color: #9932CC; }
    </style>
</head>
<body class="bg-purple-50">
    <div class="flex min-h-screen">
        <aside class="w-64 bg-white border-r">
            <div class="p-6">
                <a href="#" class="text-2xl font-bold text-dark-orchid">CarePlus</a>
            </div>
            <nav class="px-4">
                <a href="careGiverDashboard.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-table-columns w-5"></i><span>Dashboard</span></a>
                <a href="caregiverProfile.php" class="flex items-center space-x-3 px-4 py-3 bg-purple-100 text-dark-orchid rounded-lg"><i class="fa-regular fa-user w-5"></i><span>My Profile</span></a>
                <a href="caregiver_availability.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-calendar-days w-5"></i><span>Availability</span></a>
                <a href="my_bookings.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-calendar-check w-5"></i><span>My Bookings</span></a>
                <a href="#" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-heart-pulse w-5"></i><span>Patient Care</span></a>
                <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg mt-8"><i class="fa-solid fa-arrow-right-from-bracket w-5"></i><span>Logout</span></a>
            </nav>
        </aside>

        <main class="flex-1 p-8">
            <header class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-800">My Profile</h1>
                    <p class="text-gray-600 mt-1">Update your personal and professional information</p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <p class="font-semibold text-slate-700"><?php echo htmlspecialchars($userName); ?></p>
                        <p class="text-sm text-gray-500">CareGiver</p>
                    </div>
                    <?php if (!empty($caregiverData['profilePhoto']) && file_exists($caregiverData['profilePhoto'])): ?>
                        <img src="<?php echo htmlspecialchars($caregiverData['profilePhoto']); ?>" alt="Profile Photo" class="w-12 h-12 rounded-full object-cover">
                    <?php else: ?>
                        <div class="w-12 h-12 rounded-full bg-dark-orchid text-white flex items-center justify-center font-bold text-lg"><?php echo htmlspecialchars($userAvatar); ?></div>
                    <?php endif; ?>
                </div>
            </header>

            <?php if ($successMsg): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6" role="alert"><i class="fa-solid fa-check-circle mr-2"></i><?php echo $successMsg; ?></div>
            <?php endif; ?>
            <?php if ($errorMsg): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6" role="alert"><i class="fa-solid fa-exclamation-circle mr-2"></i><?php echo $errorMsg; ?></div>
            <?php endif; ?>

            <div class="bg-white p-8 rounded-lg shadow-orchid-custom">
                <form action="caregiverProfile.php" method="POST" class="space-y-8" enctype="multipart/form-data">

                    <div>
                        <h3 class="text-xl font-semibold text-slate-800 border-b pb-2 mb-4">Personal Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="Name" class="block text-sm font-medium text-gray-700">Full Name</label>
                                <input type="text" name="Name" id="Name" value="<?php echo htmlspecialchars($caregiverData['Name']); ?>" class="form-input" required>
                            </div>
                            <div>
                                <label for="contactNo" class="block text-sm font-medium text-gray-700">Contact Number</label>
                                <input type="tel" name="contactNo" id="contactNo" value="<?php echo htmlspecialchars($caregiverData['contactNo']); ?>" class="form-input" required>
                            </div>
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                                <input type="email" id="email" value="<?php echo htmlspecialchars($caregiverData['email']); ?>" class="form-input bg-gray-100" readonly>
                            </div>
                            <div>
                                <label for="nidNumber" class="block text-sm font-medium text-gray-700">NID Number</label>
                                <input type="text" name="nidNumber" id="nidNumber" value="<?php echo htmlspecialchars($caregiverData['nidNumber']); ?>" class="form-input bg-gray-100" readonly>
                                <p class="text-xs text-gray-500 mt-1">NID number cannot be changed</p>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700">Profile Photo</label>
                                <div class="mt-2 flex items-center space-x-6">
                                    <?php if (!empty($caregiverData['profilePhoto']) && file_exists($caregiverData['profilePhoto'])): ?>
                                        <img src="<?php echo htmlspecialchars($caregiverData['profilePhoto']); ?>" alt="Current Profile Photo" class="h-20 w-20 rounded-full object-cover">
                                    <?php else: ?>
                                        <div class="h-20 w-20 rounded-full bg-slate-200 flex items-center justify-center text-slate-500 font-bold text-2xl"><?php echo htmlspecialchars($userAvatar); ?></div>
                                    <?php endif; ?>
                                    <div>
                                        <label for="profilePhoto" class="cursor-pointer bg-white py-2 px-3 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                                            <span>Change Photo</span>
                                            <input type="file" name="profilePhoto" id="profilePhoto" class="sr-only" accept="image/*">
                                        </label>
                                        <p class="text-xs text-gray-500 mt-1">PNG, JPG, WEBP up to 5MB.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-xl font-semibold text-slate-800 border-b pb-2 mb-4">Professional Details</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div class="lg:col-span-3">
                                <label for="careGiverType" class="block text-sm font-medium text-gray-700">CareGiver Specialization</label>
                                <select name="careGiverType" id="careGiverType" class="form-input" required>
                                    <option value="">Select Specialization</option>
                                    <option value="Nurse" <?php echo ($caregiverData['careGiverType'] == 'Nurse') ? 'selected' : ''; ?>>Nurse</option>
                                    <option value="Physiotherapist" <?php echo ($caregiverData['careGiverType'] == 'Physiotherapist') ? 'selected' : ''; ?>>Physiotherapist</option>
                                    <option value="Home Care Assistant" <?php echo ($caregiverData['careGiverType'] == 'Home Care Assistant') ? 'selected' : ''; ?>>Home Care Assistant</option>
                                    <option value="Elderly Care Specialist" <?php echo ($caregiverData['careGiverType'] == 'Elderly Care Specialist') ? 'selected' : ''; ?>>Elderly Care Specialist</option>
                                    <option value="Disabled Care Specialist" <?php echo ($caregiverData['careGiverType'] == 'Disabled Care Specialist') ? 'selected' : ''; ?>>Disabled Care Specialist</option>
                                    <option value="Post-Surgery Care" <?php echo ($caregiverData['careGiverType'] == 'Post-Surgery Care') ? 'selected' : ''; ?>>Post-Surgery Care</option>
                                    <option value="Chronic Disease Care" <?php echo ($caregiverData['careGiverType'] == 'Chronic Disease Care') ? 'selected' : ''; ?>>Chronic Disease Care</option>
                                </select>
                            </div>
                            <div class="lg:col-span-3">
                                <label for="certifications" class="block text-sm font-medium text-gray-700">Certifications & Qualifications</label>
                                <textarea name="certifications" id="certifications" rows="3" class="form-input" placeholder="List your certifications, degrees, and professional qualifications..."><?php echo htmlspecialchars($caregiverData['certifications']); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-xl font-semibold text-slate-800 border-b pb-2 mb-4">Service Rates</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="dailyRate" class="block text-sm font-medium text-gray-700">Daily Rate (৳)</label>
                                <input type="number" step="0.01" name="dailyRate" id="dailyRate" value="<?php echo htmlspecialchars($caregiverData['dailyRate']); ?>" class="form-input" required>
                            </div>
                            <div>
                                <label for="weeklyRate" class="block text-sm font-medium text-gray-700">Weekly Rate (৳)</label>
                                <input type="number" step="0.01" name="weeklyRate" id="weeklyRate" value="<?php echo htmlspecialchars($caregiverData['weeklyRate']); ?>" class="form-input" required>
                            </div>
                            <div>
                                <label for="monthlyRate" class="block text-sm font-medium text-gray-700">Monthly Rate (৳)</label>
                                <input type="number" step="0.01" name="monthlyRate" id="monthlyRate" value="<?php echo htmlspecialchars($caregiverData['monthlyRate']); ?>" class="form-input" required>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-xl font-semibold text-slate-800 border-b pb-2 mb-4">Documents & Verification</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="nidCopy" class="block text-sm font-medium text-gray-700">NID Copy</label>
                                <input type="file" name="nidCopy" id="nidCopy" class="mt-1 block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100"/>
                                <?php if(!empty($caregiverData['nidCopyURL']) && file_exists($caregiverData['nidCopyURL'])): ?>
                                <a href="<?php echo htmlspecialchars($caregiverData['nidCopyURL']); ?>" target="_blank" class="text-sm text-purple-600 hover:underline mt-1 inline-block">View Current NID</a>
                                <?php endif; ?>
                            </div>
                            <div>
                                <label for="certificationDoc" class="block text-sm font-medium text-gray-700">Certification Documents</label>
                                <input type="file" name="certificationDoc" id="certificationDoc" class="mt-1 block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100"/>
                                <?php if(!empty($caregiverData['certificationURL']) && file_exists($caregiverData['certificationURL'])): ?>
                                <a href="<?php echo htmlspecialchars($caregiverData['certificationURL']); ?>" target="_blank" class="text-sm text-purple-600 hover:underline mt-1 inline-block">View Current Certificates</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end pt-4 border-t mt-8">
                        <button type="submit" class="bg-dark-orchid text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition font-semibold">
                            <i class="fa-solid fa-save mr-2"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
