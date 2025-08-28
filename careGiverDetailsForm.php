<?php
session_start();

// Protect this page
if (!isset($_SESSION['pending_user_id']) || $_SESSION['pending_user_role'] !== 'CareGiver') {
    header("Location: register.php");
    exit();
}

// --- Database Connection ---
require_once 'config.php';

$userID = $_SESSION['pending_user_id'];
$errorMsg = "";

// Helper function to handle file uploads securely
function handleFileUpload($fileInputName, $uploadSubDir) {
    if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]["error"] === UPLOAD_ERR_OK) {
        $uploadDir = "uploads/" . $uploadSubDir . "/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $filename = time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($_FILES[$fileInputName]["name"]));
        $uploadPath = $uploadDir . $filename;

        if (move_uploaded_file($_FILES[$fileInputName]["tmp_name"], $uploadPath)) {
            return $uploadPath;
        }
    }
    return null;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Handle file uploads
    $nidCopyURL = handleFileUpload('nidCopyURL', 'caregiver_documents');
    $certificationURL = handleFileUpload('certificationURL', 'caregiver_documents');

    $sql = "UPDATE CareGiver SET careGiverType=?, certifications=?, dailyRate=?, weeklyRate=?, monthlyRate=?, nidNumber=?, nidCopyURL=?, certificationURL=? WHERE careGiverID=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssdddsisi",
        $_POST['careGiverType'], $_POST['certifications'], $_POST['dailyRate'],
        $_POST['weeklyRate'], $_POST['monthlyRate'], $_POST['nidNumber'],
        $nidCopyURL, $certificationURL, $userID
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your CareGiver Profile - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-dark-orchid { background-color: #9932CC; }
        .hover\:bg-dark-orchid-darker:hover { background-color: #8A2BE2; }
        .text-dark-orchid { color: #9932CC; }
        .file-input-button {
            background-color: #f3e8ff;
            color: #9932CC;
        }
        .file-input-button:hover {
            background-color: #e9d5ff;
        }
    </style>
</head>
<body class="bg-purple-50 flex items-center justify-center min-h-screen p-4">
    <div class="bg-white p-6 sm:p-8 rounded-xl shadow-lg w-full max-w-3xl">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-dark-orchid mb-2">CarePlus</h1>
            <h2 class="text-3xl font-bold text-gray-800">CareGiver Professional Details</h2>
            <p class="text-gray-500">Please provide your professional information to complete your profile.</p>
        </div>

        <?php if ($errorMsg): ?>
            <div class="mb-4 p-3 bg-red-100 text-red-700 rounded-md"><?php echo $errorMsg; ?></div>
        <?php endif; ?>

        <form action="careGiverDetailsForm.php" method="POST" enctype="multipart/form-data" class="space-y-8">
            
            <!-- Professional Information Section -->
            <div>
                <h3 class="text-xl font-semibold text-gray-800 border-b pb-3 mb-6">Professional Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">CareGiver Type</label>
                        <select name="careGiverType" required class="w-full mt-1 p-2 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500">
                            <option value="">-- Select Type --</option>
                            <option value="Nurse">Nurse</option>
                            <option value="Physiotherapist">Physiotherapist</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Certifications</label>
                        <input type="text" name="certifications" placeholder="e.g., Registered Nurse (RN)" class="w-full mt-1 p-2 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">NID Number</label>
                        <input type="text" name="nidNumber" placeholder="Enter 10/17 digit NID" class="w-full mt-1 p-2 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500">
                    </div>
                </div>
            </div>

            <!-- Service Rates Section -->
            <div>
                <h3 class="text-xl font-semibold text-gray-800 border-b pb-3 mb-6">Service Rates (BDT)</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Daily Rate</label>
                        <input type="text" name="dailyRate" placeholder="e.g., 1500" class="w-full mt-1 p-2 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Weekly Rate</label>
                        <input type="text" name="weeklyRate" placeholder="e.g., 9000" class="w-full mt-1 p-2 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Monthly Rate</label>
                        <input type="text" name="monthlyRate" placeholder="e.g., 35000" class="w-full mt-1 p-2 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500">
                    </div>
                </div>
            </div>
            
            <!-- Document Upload Section -->
            <div>
                 <h3 class="text-xl font-semibold text-gray-800 border-b pb-3 mb-6">Upload Documents</h3>
                 <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">NID Copy</label>
                        <input type="file" name="nidCopyURL" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file-input-button">
                    </div>
                     <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Certification Document</label>
                        <input type="file" name="certificationURL" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file-input-button">
                    </div>
                 </div>
            </div>

            <button type="submit" class="w-full mt-8 py-3 px-4 bg-dark-orchid text-white rounded-md font-semibold hover:bg-dark-orchid-darker transition text-lg shadow-md hover:shadow-lg">
                Complete Registration
            </button>
        </form>
    </div>
</body>
</html>
