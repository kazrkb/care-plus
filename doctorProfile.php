<?php
session_start();

// Protect this page: allow only logged-in Doctors
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Doctor') {
    header("Location: login.php");
    exit();
}

// --- Database Connection ---
$conn = require_once 'config.php';

$doctorID = $_SESSION['userID'];
$userName = $_SESSION['Name'];
$userAvatar = strtoupper(substr($userName, 0, 2));

$successMsg = "";
$errorMsg = "";

// Helper function to handle all file uploads (profile photos and documents)
function handleFileUpload($fileKey, $columnName, $doctorID, $isProfilePhoto, $conn) {
    if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] == UPLOAD_ERR_OK) {
        $uploadDir = $isProfilePhoto ? 'uploads/' : 'uploads/docs/';
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
        $newFileName = $prefix . '_' . $doctorID . '_' . time() . '.' . $fileExtension;
        $newFilePath = $uploadDir . $newFileName;

        // Fetch old file path to delete it later
        $table = $isProfilePhoto ? 'users' : 'doctor';
        $idColumn = $isProfilePhoto ? 'userID' : 'doctorID';
        $oldFileQuery = "SELECT $columnName FROM $table WHERE $idColumn = ?";
        $oldStmt = $conn->prepare($oldFileQuery);
        $oldStmt->bind_param("i", $doctorID);
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
        'profilePhoto'   => ['column' => 'profilePhoto', 'is_photo' => true],
        'nidCopy'        => ['column' => 'nidCopyURL', 'is_photo' => false],
        'bmdcCert'       => ['column' => 'bmdcCertURL', 'is_photo' => false],
        'medicalLicense' => ['column' => 'medicalLicenseURL', 'is_photo' => false]
    ];

    foreach ($fileUploads as $fileKey => $details) {
        $result = handleFileUpload($fileKey, $details['column'], $doctorID, $details['is_photo'], $conn);
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
                $userStmt->bind_param("sssi", $_POST['Name'], $_POST['contactNo'], $updatePaths['profilePhoto'], $doctorID);
            } else {
                $userStmt->bind_param("ssi", $_POST['Name'], $_POST['contactNo'], $doctorID);
            }
            $userStmt->execute();

            // 2. Dynamically build the 'doctor' table update query
            $doctorUpdateParts = [
                "specialty = ?", "consultationFees = ?", "yearsOfExp = ?", "hospital = ?",
                "department = ?", "bmdcRegistrationNumber = ?", "medicalSchool = ?",
                "licNo = ?", "nidNumber = ?", "licenseExpiryDate = ?"
            ];
            $doctorParams = [
                $_POST['specialty'], $_POST['consultationFees'], $_POST['yearsOfExp'], $_POST['hospital'],
                $_POST['department'], $_POST['bmdcRegistrationNumber'], $_POST['medicalSchool'],
                $_POST['licNo'], $_POST['nidNumber'], $_POST['licenseExpiryDate']
            ];
            $doctorTypes = "sdisssssss";

            foreach (['nidCopyURL', 'bmdcCertURL', 'medicalLicenseURL'] as $docColumn) {
                if (isset($updatePaths[$docColumn])) {
                    $doctorUpdateParts[] = "$docColumn = ?";
                    $doctorParams[] = $updatePaths[$docColumn];
                    $doctorTypes .= "s";
                }
            }
            $updateDoctorSql = "UPDATE doctor SET " . implode(", ", $doctorUpdateParts) . " WHERE doctorID = ?";
            $doctorParams[] = $doctorID;
            $doctorTypes .= "i";
            $doctorStmt = $conn->prepare($updateDoctorSql);
            $doctorStmt->bind_param($doctorTypes, ...$doctorParams);
            $doctorStmt->execute();
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

// --- Fetch Doctor's Current Profile Data ---
$query = "SELECT u.Name, u.email, u.contactNo, u.profilePhoto, d.* FROM users u JOIN doctor d ON u.userID = d.doctorID WHERE u.userID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $doctorID);
$stmt->execute();
$result = $stmt->get_result();
$doctorData = $result->fetch_assoc();
if (!$doctorData) {
    die("Error: Could not retrieve doctor profile data.");
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
                <a href="doctorDashboard.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-table-columns w-5"></i><span>Dashboard</span></a>
                <a href="doctorProfile.php" class="flex items-center space-x-3 px-4 py-3 bg-purple-100 text-dark-orchid rounded-lg"><i class="fa-regular fa-user w-5"></i><span>My Profile</span></a>
                <a href="doctor_schedule.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-calendar-days w-5"></i><span>Provide Schedule</span></a>
                <a href="#" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-laptop-medical w-5"></i><span>Consultations</span></a>
                <a href="#" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-file-waveform w-5"></i><span>Patient History</span></a>
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
                        <p class="text-sm text-gray-500">Doctor</p>
                    </div>
                    <?php if (!empty($doctorData['profilePhoto']) && file_exists($doctorData['profilePhoto'])): ?>
                        <img src="<?php echo htmlspecialchars($doctorData['profilePhoto']); ?>" alt="Profile Photo" class="w-12 h-12 rounded-full object-cover">
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
                <form action="doctorProfile.php" method="POST" class="space-y-8" enctype="multipart/form-data">

                    <div>
                        <h3 class="text-xl font-semibold text-slate-800 border-b pb-2 mb-4">Personal Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="Name" class="block text-sm font-medium text-gray-700">Full Name</label>
                                <input type="text" name="Name" id="Name" value="<?php echo htmlspecialchars($doctorData['Name']); ?>" class="form-input" required>
                            </div>
                            <div>
                                <label for="contactNo" class="block text-sm font-medium text-gray-700">Contact Number</label>
                                <input type="tel" name="contactNo" id="contactNo" value="<?php echo htmlspecialchars($doctorData['contactNo']); ?>" class="form-input" required>
                            </div>
                             <div>
                                <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                                <input type="email" id="email" value="<?php echo htmlspecialchars($doctorData['email']); ?>" class="form-input bg-gray-100" readonly>
                            </div>
                             <div>
                                <label for="nidNumber" class="block text-sm font-medium text-gray-700">NID Number</label>
                                <input type="text" name="nidNumber" id="nidNumber" value="<?php echo htmlspecialchars($doctorData['nidNumber']); ?>" class="form-input">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700">Profile Photo</label>
                                <div class="mt-2 flex items-center space-x-6">
                                    <?php if (!empty($doctorData['profilePhoto']) && file_exists($doctorData['profilePhoto'])): ?>
                                        <img src="<?php echo htmlspecialchars($doctorData['profilePhoto']); ?>" alt="Current Profile Photo" class="h-20 w-20 rounded-full object-cover">
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
                        <h3 class="text-xl font-semibold text-slate-800 border-b pb-2 mb-4">Professional & Academic Details</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div class="lg:col-span-3">
                                <label for="specialty" class="block text-sm font-medium text-gray-700">Specialty</label>
                                <input type="text" name="specialty" id="specialty" value="<?php echo htmlspecialchars($doctorData['specialty']); ?>" class="form-input" required>
                            </div>
                            <div class="lg:col-span-3">
                                <label for="medicalSchool" class="block text-sm font-medium text-gray-700">Medical School</label>
                                <input type="text" name="medicalSchool" id="medicalSchool" value="<?php echo htmlspecialchars($doctorData['medicalSchool']); ?>" class="form-input">
                            </div>
                            <div>
                                <label for="hospital" class="block text-sm font-medium text-gray-700">Current Hospital/Clinic</label>
                                <input type="text" name="hospital" id="hospital" value="<?php echo htmlspecialchars($doctorData['hospital']); ?>" class="form-input">
                            </div>
                            <div>
                                <label for="department" class="block text-sm font-medium text-gray-700">Department</label>
                                <input type="text" name="department" id="department" value="<?php echo htmlspecialchars($doctorData['department']); ?>" class="form-input">
                            </div>
                            <div>
                                <label for="yearsOfExp" class="block text-sm font-medium text-gray-700">Years of Experience</label>
                                <input type="number" name="yearsOfExp" id="yearsOfExp" value="<?php echo htmlspecialchars($doctorData['yearsOfExp']); ?>" class="form-input" required>
                            </div>
                            <div>
                                <label for="consultationFees" class="block text-sm font-medium text-gray-700">Consultation Fees (৳)</label>
                                <input type="number" step="0.01" name="consultationFees" id="consultationFees" value="<?php echo htmlspecialchars($doctorData['consultationFees']); ?>" class="form-input" required>
                            </div>
                        </div>
                    </div>

                     <div>
                        <h3 class="text-xl font-semibold text-slate-800 border-b pb-2 mb-4">License & Documents</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                             <div>
                                <label for="licNo" class="block text-sm font-medium text-gray-700">Medical License No.</label>
                                <input type="text" name="licNo" id="licNo" value="<?php echo htmlspecialchars($doctorData['licNo']); ?>" class="form-input">
                            </div>
                             <div>
                                <label for="licenseExpiryDate" class="block text-sm font-medium text-gray-700">License Expiry Date</label>
                                <input type="date" name="licenseExpiryDate" id="licenseExpiryDate" value="<?php echo htmlspecialchars($doctorData['licenseExpiryDate']); ?>" class="form-input">
                            </div>
                            <div>
                                <label for="bmdcRegistrationNumber" class="block text-sm font-medium text-gray-700">BMDC Registration No.</label>
                                <input type="text" name="bmdcRegistrationNumber" id="bmdcRegistrationNumber" value="<?php echo htmlspecialchars($doctorData['bmdcRegistrationNumber']); ?>" class="form-input">
                            </div>
                            <div>
                                <label for="nidCopy" class="block text-sm font-medium text-gray-700">NID Copy</label>
                                <input type="file" name="nidCopy" id="nidCopy" class="mt-1 block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100"/>
                                <?php if(!empty($doctorData['nidCopyURL']) && file_exists($doctorData['nidCopyURL'])): ?>
                                <a href="<?php echo htmlspecialchars($doctorData['nidCopyURL']); ?>" target="_blank" class="text-sm text-purple-600 hover:underline mt-1 inline-block">View Current NID</a>
                                <?php endif; ?>
                            </div>
                            <div>
                                <label for="bmdcCert" class="block text-sm font-medium text-gray-700">BMDC Certificate</label>
                                <input type="file" name="bmdcCert" id="bmdcCert" class="mt-1 block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100"/>
                                <?php if(!empty($doctorData['bmdcCertURL']) && file_exists($doctorData['bmdcCertURL'])): ?>
                                <a href="<?php echo htmlspecialchars($doctorData['bmdcCertURL']); ?>" target="_blank" class="text-sm text-purple-600 hover:underline mt-1 inline-block">View Current Certificate</a>
                                <?php endif; ?>
                            </div>
                             <div>
                                <label for="medicalLicense" class="block text-sm font-medium text-gray-700">Medical License Copy</label>
                                <input type="file" name="medicalLicense" id="medicalLicense" class="mt-1 block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100"/>
                                <?php if(!empty($doctorData['medicalLicenseURL']) && file_exists($doctorData['medicalLicenseURL'])): ?>
                                <a href="<?php echo htmlspecialchars($doctorData['medicalLicenseURL']); ?>" target="_blank" class="text-sm text-purple-600 hover:underline mt-1 inline-block">View Current License</a>
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