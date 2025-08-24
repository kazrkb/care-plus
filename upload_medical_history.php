<?php
session_start();
$conn = require_once 'config.php';
date_default_timezone_set('Asia/Dhaka');

// Protect this page: allow only logged-in Patients
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Patient') {
    header("Location: login.php");
    exit();
}

$patientID = $_SESSION['userID'];
$userName = $_SESSION['Name'];
$userAvatar = strtoupper(substr($userName, 0, 2));
$successMsg = "";
$errorMsg = "";

// --- Handle File Upload and Form Submission ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_history'])) {
    $visitDate = $_POST['visitDate'];
    $diagnosis = trim($_POST['diagnosis']);
    $medicalHistory = trim($_POST['medicalHistory']);
    $labResultsFilePath = null;
    $uploadDir = 'uploads/patient_history/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $conn->begin_transaction();
    try {
        if (isset($_FILES['labResultsFile']) && $_FILES['labResultsFile']['error'] == UPLOAD_ERR_OK) {
            $file = $_FILES['labResultsFile'];
            $fileName = 'primary_' . $patientID . '_' . time() . '_' . basename($file['name']);
            $targetPath = $uploadDir . $fileName;
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $labResultsFilePath = $targetPath;
            } else { throw new Exception("Failed to upload the primary lab results file."); }
        }

        $sql = "INSERT INTO patienthistory (patientID, visitDate, diagnosis, labResultsFile, medicalHistory) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issss", $patientID, $visitDate, $diagnosis, $labResultsFilePath, $medicalHistory);
        if (!$stmt->execute()) { throw new Exception("Failed to save the medical history record."); }
        $historyID = $conn->insert_id;
        $stmt->close();

        if (isset($_FILES['additional_documents'])) {
            $additionalFiles = $_FILES['additional_documents'];
            $docSql = "INSERT INTO medicaldocuments (historyID, documentURL) VALUES (?, ?)";
            $docStmt = $conn->prepare($docSql);
            foreach ($additionalFiles['name'] as $key => $name) {
                if ($additionalFiles['error'][$key] == UPLOAD_ERR_OK) {
                    $docFileName = 'doc_' . $patientID . '_' . time() . '_' . basename($name);
                    $docTargetPath = $uploadDir . $docFileName;
                    if (move_uploaded_file($additionalFiles['tmp_name'][$key], $docTargetPath)) {
                        $docStmt->bind_param("is", $historyID, $docTargetPath);
                        $docStmt->execute();
                    }
                }
            }
            $docStmt->close();
        }
        $conn->commit();
        $successMsg = "Medical history and documents uploaded successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $errorMsg = $e->getMessage();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Medical History - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style> body { font-family: 'Inter', sans-serif; } .bg-dark-orchid { background-color: #9932CC; } </style>
</head>
<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <aside class="w-64 bg-white border-r">
            <div class="p-6"><a href="#" class="text-2xl font-bold text-dark-orchid">CarePlus</a></div>
            <nav class="px-4 space-y-2">
                <a href="patientDashboard.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-table-columns w-5"></i><span>Dashboard</span></a>
                <a href="patient_medical_history.php" class="flex items-center space-x-3 px-4 py-3 bg-purple-100 text-dark-orchid rounded-lg"><i class="fa-solid fa-file-medical w-5"></i><span>Medical History</span></a>
                <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg mt-8"><i class="fa-solid fa-arrow-right-from-bracket w-5"></i><span>Logout</span></a>
            </nav>
        </aside>

        <main class="flex-1 p-8">
            <header class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-800">Upload Medical Record</h1>
                    <p class="text-gray-600 mt-1">Add a new entry to your personal medical history.</p>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="view_my_records.php" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                        <i class="fa-solid fa-eye mr-2"></i>View Uploaded Records
                    </a>
                    <div class="text-right">
                        <p class="font-semibold text-slate-700"><?php echo htmlspecialchars($userName); ?></p>
                        <p class="text-sm text-gray-500">Patient</p>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-dark-orchid text-white flex items-center justify-center font-bold text-lg"><?php echo htmlspecialchars($userAvatar); ?></div>
                </div>
            </header>

            <?php if ($successMsg): ?><div class="mb-6 p-4 bg-green-100 text-green-800 border-l-4 border-green-500 rounded-r-lg" role="alert"><p><?php echo $successMsg; ?></p></div><?php endif; ?>
            <?php if ($errorMsg): ?><div class="mb-6 p-4 bg-red-100 text-red-800 border-l-4 border-red-500 rounded-r-lg" role="alert"><p><?php echo $errorMsg; ?></p></div><?php endif; ?>

            <div class="bg-white p-6 rounded-xl shadow-lg">
                <form action="patient_medical_history.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="visitDate" class="block text-sm font-medium text-gray-700">Visit Date</label>
                            <input type="date" id="visitDate" name="visitDate" required class="w-full mt-1 p-2 border rounded-md">
                        </div>
                        <div>
                            <label for="diagnosis" class="block text-sm font-medium text-gray-700">Diagnosis / Reason for Visit</label>
                            <input type="text" id="diagnosis" name="diagnosis" required class="w-full mt-1 p-2 border rounded-md" placeholder="e.g., Annual Check-up, Flu">
                        </div>
                    </div>
                    <div>
                        <label for="medicalHistory" class="block text-sm font-medium text-gray-700">Notes</label>
                        <textarea id="medicalHistory" name="medicalHistory" rows="3" class="w-full mt-1 p-2 border rounded-md" placeholder="Enter any relevant notes from the doctor, symptoms, etc."></textarea>
                    </div>
                     <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="labResultsFile" class="block text-sm font-medium text-gray-700">Primary Report (Optional)</label>
                            <input type="file" id="labResultsFile" name="labResultsFile" class="w-full mt-1 text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100">
                        </div>
                        <div>
                            <label for="additional_documents" class="block text-sm font-medium text-gray-700">Additional Documents (Optional)</label>
                            <input type="file" id="additional_documents" name="additional_documents[]" multiple class="w-full mt-1 text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100">
                        </div>
                    </div>
                    <div class="text-right pt-4 border-t">
                         <button type="submit" name="add_history" class="py-2 px-6 bg-dark-orchid text-white rounded-md font-semibold hover:bg-purple-700 transition">
                            <i class="fa-solid fa-upload mr-2"></i>Upload Record
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>