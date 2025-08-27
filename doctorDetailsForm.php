<?php
session_start();

// Protect this page
if (!isset($_SESSION['pending_user_id']) || $_SESSION['pending_user_role'] !== 'Doctor') {
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

// Helper function to handle file uploads securely
function handleFileUpload($fileInputName, $uploadSubDir) {
    if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]["error"] === UPLOAD_ERR_OK) {
        $uploadDir = "uploads/" . $uploadSubDir . "/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        // Sanitize filename and create a unique name to prevent overwrites
        $filename = time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($_FILES[$fileInputName]["name"]));
        $uploadPath = $uploadDir . $filename;

        if (move_uploaded_file($_FILES[$fileInputName]["tmp_name"], $uploadPath)) {
            return $uploadPath; // Return the path to be stored in the DB
        }
    }
    return null; // Return null if upload fails or no file was provided
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Handle file uploads first
    $nidCopyURL = handleFileUpload('nidCopyURL', 'doctor_documents');
    $bmdcCertURL = handleFileUpload('bmdcCertURL', 'doctor_documents');
    $medicalLicenseURL = handleFileUpload('medicalLicenseURL', 'doctor_documents');

    $sql = "UPDATE Doctor SET specialty=?, licNo=?, yearsOfExp=?, consultationFees=?, nidNumber=?, bmdcRegistrationNumber=?, licenseExpiryDate=?, hospital=?, department=?, medicalSchool=?, nidCopyURL=?, bmdcCertURL=?, medicalLicenseURL=? WHERE doctorID=?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssidsisssssssi", 
        $_POST['specialty'], $_POST['licNo'], $_POST['yearsOfExp'], $_POST['consultationFees'],
        $_POST['nidNumber'], $_POST['bmdcRegistrationNumber'], $_POST['licenseExpiryDate'],
        $_POST['hospital'], $_POST['department'], $_POST['medicalSchool'],
        $nidCopyURL, $bmdcCertURL, $medicalLicenseURL, $userID
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
    <title>Complete Your Doctor Profile - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
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
    <div class="bg-white p-6 sm:p-8 rounded-xl shadow-lg w-full max-w-4xl">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-dark-orchid mb-2">CarePlus</h1>
            <h2 class="text-3xl font-bold text-gray-800">Doctor Professional Details</h2>
            <p class="text-gray-500">Please fill out the information below to complete your profile.</p>
        </div>

        <?php if ($errorMsg): ?>
            <div class="mb-4 p-3 bg-red-100 text-red-700 rounded-md"><?php echo $errorMsg; ?></div>
        <?php endif; ?>

        <form action="doctorDetailsForm.php" method="POST" enctype="multipart/form-data" class="space-y-8">
            
            <!-- Professional Information Section -->
            <div>
                <h3 class="text-xl font-semibold text-gray-800 border-b pb-3 mb-6">Professional Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Specialty</label>
                        <select name="specialty" required class="w-full mt-1 p-2 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500">
                            <option value="">-- Select Specialty --</option>
                            <option value="Cardiology">Cardiology</option>
                            <option value="Dermatology">Dermatology</option>
                            <option value="Neurology">Neurology</option>
                            <option value="Pediatrics">Pediatrics</option>
                            <option value="Orthopedics">Orthopedics</option>
                            <option value="General Medicine">General Medicine</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Years of Experience</label>
                        <input type="number" name="yearsOfExp" placeholder="e.g., 10" class="w-full mt-1 p-2 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Hospital / Clinic</label>
                        <input type="text" name="hospital" placeholder="e.g., General Hospital" class="w-full mt-1 p-2 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Department</label>
                        <input type="text" name="department" placeholder="e.g., Cardiology" class="w-full mt-1 p-2 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Medical School</label>
                        <input type="text" name="medicalSchool" placeholder="e.g., Dhaka Medical College" class="w-full mt-1 p-2 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500">
                    </div>
                </div>
            </div>

            <!-- Identification & Credentials Section -->
            <div>
                <h3 class="text-xl font-semibold text-gray-800 border-b pb-3 mb-6">Identification & Credentials</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">NID Number</label>
                        <input type="text" name="nidNumber" placeholder="Enter 10/17 digit NID" class="w-full mt-1 p-2 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">BMDC Registration No.</label>
                        <input type="text" name="bmdcRegistrationNumber" placeholder="e.g., BMDC-9876" class="w-full mt-1 p-2 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Medical License Number</label>
                        <input type="text" name="licNo" required placeholder="e.g., DOC12345" class="w-full mt-1 p-2 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500">
                    </div>
                     <div>
                        <label class="block text-sm font-medium text-gray-700">License Expiry Date</label>
                        <input type="date" name="licenseExpiryDate" class="w-full mt-1 p-2 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500">
                    </div>
                     <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Consultation Fees (BDT)</label>
                        <input type="text" name="consultationFees" placeholder="e.g., 1500" class="w-full mt-1 p-2 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500">
                    </div>
                </div>
            </div>
            
            <!-- Document Upload Section -->
            <div>
                 <h3 class="text-xl font-semibold text-gray-800 border-b pb-3 mb-6">Upload Documents</h3>
                 <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">NID Copy</label>
                        <input type="file" name="nidCopyURL" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file-input-button">
                    </div>
                     <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">BMDC Certificate</label>
                        <input type="file" name="bmdcCertURL" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file-input-button">
                    </div>
                     <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Medical License</label>
                        <input type="file" name="medicalLicenseURL" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file-input-button">
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
