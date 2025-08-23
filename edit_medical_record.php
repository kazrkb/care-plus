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
$historyRecord = null;

// A history ID must be provided to edit
if (!isset($_GET['history_id'])) {
    header("Location: view_my_records.php");
    exit();
}
$historyID = (int)$_GET['history_id'];

// Handle form submission to UPDATE the record
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_history'])) {
    $visitDate = $_POST['visitDate'];
    $diagnosis = trim($_POST['diagnosis']);
    $medicalHistory = trim($_POST['medicalHistory']);

    $sql = "UPDATE patienthistory SET visitDate = ?, diagnosis = ?, medicalHistory = ? WHERE historyID = ? AND patientID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssii", $visitDate, $diagnosis, $medicalHistory, $historyID, $patientID);
    
    if ($stmt->execute()) {
        $successMsg = "Medical record updated successfully!";
    } else {
        $errorMsg = "Failed to update record.";
    }
    $stmt->close();
}


// Fetch the specific history record to populate the form
$stmt = $conn->prepare("SELECT * FROM patienthistory WHERE historyID = ? AND patientID = ?");
$stmt->bind_param("ii", $historyID, $patientID);
$stmt->execute();
$historyRecord = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

// If record not found or doesn't belong to the user, show an error
if (!$historyRecord) {
    // Redirect or show a message
    header("Location: view_my_records.php?error=notfound");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Medical Record - CarePlus</title>
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
                    <h1 class="text-3xl font-bold text-slate-800">Edit Medical Record</h1>
                    <p class="text-gray-600 mt-1">Update the details for this history entry.</p>
                </div>
                <div><a href="view_my_records.php" class="text-sm text-blue-500 hover:underline">&larr; Back to My Records</a></div>
            </header>

            <?php if ($successMsg): ?><?php endif; ?>
            <?php if ($errorMsg): ?><?php endif; ?>

            <div class="bg-white p-6 rounded-xl shadow-lg">
                 <form action="edit_medical_record.php?history_id=<?php echo $historyID; ?>" method="POST">
                    <input type="hidden" name="update_history" value="1">
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="visitDate" class="block text-sm font-medium text-gray-700">Visit Date</label>
                                <input type="date" id="visitDate" name="visitDate" value="<?php echo htmlspecialchars($historyRecord['visitDate']); ?>" required class="w-full mt-1 p-2 border rounded-md">
                            </div>
                            <div>
                                <label for="diagnosis" class="block text-sm font-medium text-gray-700">Diagnosis / Reason for Visit</label>
                                <input type="text" id="diagnosis" name="diagnosis" value="<?php echo htmlspecialchars($historyRecord['diagnosis']); ?>" required class="w-full mt-1 p-2 border rounded-md">
                            </div>
                        </div>
                        <div>
                            <label for="medicalHistory" class="block text-sm font-medium text-gray-700">Notes</label>
                            <textarea id="medicalHistory" name="medicalHistory" rows="4" class="w-full mt-1 p-2 border rounded-md"><?php echo htmlspecialchars($historyRecord['medicalHistory']); ?></textarea>
                        </div>
                         <div class="text-right pt-4 border-t">
                             <button type="submit" class="py-2 px-6 bg-dark-orchid text-white rounded-md font-semibold hover:bg-purple-700">
                                <i class="fa-solid fa-save mr-2"></i>Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>