<?php
session_start();
$conn = require_once 'config.php'; 

// Protect this page
if (!isset($_SESSION['pending_user_id']) || !isset($_SESSION['pending_user_role']) || $_SESSION['pending_user_role'] !== 'Nutritionist') {
    header("Location: login.php");
    exit();
}

$userID = $_SESSION['pending_user_id'];
$errorMsg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nidCopyURL = null; // Initialize as null

    // --- Handle NID File Upload ---
    if (isset($_FILES["nid_copy"]) && $_FILES["nid_copy"]["error"] === UPLOAD_ERR_OK) {
        $uploadDir = "uploads/docs/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $filename = "nid_nutritionist_" . $userID . "_" . time() . "_" . basename($_FILES["nid_copy"]["name"]);
        $uploadPath = $uploadDir . $filename;

        // Ensure the uploaded file is an image or PDF
        $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf'];
        $fileType = strtolower(pathinfo($uploadPath, PATHINFO_EXTENSION));
        if (in_array($fileType, $allowedTypes)) {
            if (move_uploaded_file($_FILES["nid_copy"]["tmp_name"], $uploadPath)) {
                $nidCopyURL = $uploadPath;
            } else {
                $errorMsg = "Sorry, there was an error uploading your NID file.";
            }
        } else {
            $errorMsg = "Invalid file type. Please upload a JPG, PNG, or PDF.";
        }
    } else {
        $errorMsg = "NID Copy is a required document.";
    }

    if (empty($errorMsg)) {
        // Prepare to update the database
        $sql = "UPDATE Nutritionist SET specialty=?, yearsOfExp=?, consultationFees=?, nidNumber=?, degree=?, nidCopyURL=? WHERE nutritionistID=?";
        $stmt = $conn->prepare($sql);
        
        // --- FIXED: Removed the extra space in the type string from "sids ssi" to "sidsssi" ---
        $stmt->bind_param("sidsssi",
            $_POST['specialty'],
            $_POST['yearsOfExp'],
            $_POST['consultationFees'],
            $_POST['nidNumber'],
            $_POST['degree'],
            $nidCopyURL,
            $userID
        );

        if ($stmt->execute()) {
            header("Location: payment.php");
            exit();
        } else {
            $errorMsg = "Failed to update profile. Please try again.";
        }
        $stmt->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Complete Your Nutritionist Profile - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-dark-orchid { background-color: #9932CC; }
        .text-dark-orchid { color: #9932CC; }
    </style>
</head>
<body class="bg-purple-50 flex items-center justify-center min-h-screen p-4">
    <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-lg">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-dark-orchid">Nutritionist Details</h1>
            <h2 class="text-3xl font-bold text-gray-800">Complete Your Professional Profile</h2>
            <p class="text-gray-500 mt-2">This information will be verified by an administrator.</p>
        </div>

        <?php if ($errorMsg): ?>
            <div class="mb-4 p-3 bg-red-100 text-red-700 rounded-md"><?php echo $errorMsg; ?></div>
        <?php endif; ?>

        <form action="nutritionistDetailsForm.php" method="POST" class="space-y-4" enctype="multipart/form-data">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Specialty</label>
                    <select name="specialty" required class="w-full mt-1 p-2 border rounded-md bg-white border-gray-300 focus:ring-purple-500 focus:border-purple-500">
                        <option value="">-- Select Specialty --</option>
                        <option value="Weight Management">Weight Management</option>
                        <option value="Sports Nutrition">Sports Nutrition</option>
                        <option value="Pediatric Nutrition">Pediatric Nutrition</option>
                        <option value="Clinical Nutrition">Clinical Nutrition</option>
                        <option value="Public Health Nutrition">Public Health Nutrition</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Years of Experience</label>
                    <input type="number" name="yearsOfExp" required class="w-full mt-1 p-2 border rounded-md border-gray-300 focus:ring-purple-500 focus:border-purple-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Consultation Fees (BDT)</label>
                    <input type="number" step="0.01" name="consultationFees" required placeholder="e.g., 700" class="w-full mt-1 p-2 border rounded-md border-gray-300 focus:ring-purple-500 focus:border-purple-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">NID Number</label>
                    <input type="text" name="nidNumber" required class="w-full mt-1 p-2 border rounded-md border-gray-300 focus:ring-purple-500 focus:border-purple-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Degree</label>
                    <input type="text" name="degree" required placeholder="e.g., MPH in Community Nutrition" class="w-full mt-1 p-2 border rounded-md border-gray-300 focus:ring-purple-500 focus:border-purple-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Upload NID Copy (PDF, JPG, PNG)</label>
                    <input type="file" name="nid_copy" required accept=".pdf,.jpg,.jpeg,.png" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100 mt-1">
                </div>
            </div>
            <button type="submit" class="w-full mt-6 py-3 px-4 bg-dark-orchid text-white rounded-md font-semibold hover:bg-purple-700 transition text-lg">
                Save & Proceed to Payment
            </button>
        </form>
    </div>
</body>
</html>