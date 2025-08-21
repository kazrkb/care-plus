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

// --- Handle Deleting a record ---
if (isset($_GET['delete_id'])) {
    $historyID = (int)$_GET['delete_id'];
    
    // First, select the record to get file paths for deletion from server
    $findStmt = $conn->prepare("SELECT labResultsFile FROM patienthistory WHERE historyID = ? AND patientID = ?");
    $findStmt->bind_param("ii", $historyID, $patientID);
    $findStmt->execute();
    $record = $findStmt->get_result()->fetch_assoc();
    $findStmt->close();

    if ($record) {
        $conn->begin_transaction();
        try {
            // Delete the main record from patienthistory (ON DELETE CASCADE will handle medicaldocuments)
            $deleteStmt = $conn->prepare("DELETE FROM patienthistory WHERE historyID = ? AND patientID = ?");
            $deleteStmt->bind_param("ii", $historyID, $patientID);
            $deleteStmt->execute();
            $deleteStmt->close();

            // Delete files from server
            if (!empty($record['labResultsFile']) && file_exists($record['labResultsFile'])) {
                unlink($record['labResultsFile']);
            }
            // (A more robust solution would also find and delete files from the medicaldocuments table)

            $conn->commit();
            $successMsg = "Medical record deleted successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $errorMsg = "Failed to delete record.";
        }
    }
}

// Fetch all history and documents for this patient
$patientHistory = [];
$medicalDocuments = [];
$historyQuery = "SELECT * FROM patienthistory WHERE patientID = ? ORDER BY visitDate DESC";
$stmt = $conn->prepare($historyQuery);
$stmt->bind_param("i", $patientID);
$stmt->execute();
$historyResult = $stmt->get_result();
$historyIDs = [];
while($row = $historyResult->fetch_assoc()) {
    $patientHistory[] = $row;
    $historyIDs[] = $row['historyID'];
}
$stmt->close();
if (!empty($historyIDs)) {
    $placeholders = implode(',', array_fill(0, count($historyIDs), '?'));
    $types = str_repeat('i', count($historyIDs));
    $docsQuery = "SELECT * FROM medicaldocuments WHERE historyID IN ($placeholders)";
    $stmt = $conn->prepare($docsQuery);
    $stmt->bind_param($types, ...$historyIDs);
    $stmt->execute();
    $docsResult = $stmt->get_result();
    while($row = $docsResult->fetch_assoc()) {
        $medicalDocuments[$row['historyID']][] = $row;
    }
    $stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View My Records - CarePlus</title>
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
                    <h1 class="text-3xl font-bold text-slate-800">My Uploaded Records</h1>
                    <p class="text-gray-600 mt-1">Here is a list of your medical history.</p>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="upload_medical_history.php" class="px-4 py-2 bg-dark-orchid text-white rounded-lg hover:bg-purple-700 transition">
                        <i class="fa-solid fa-plus mr-2"></i>Add New Record
                    </a>
                </div>
            </header>

            <?php if ($successMsg): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-r-lg" role="alert"><p><?php echo $successMsg; ?></p></div>
            <?php endif; ?>
            <?php if ($errorMsg): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert"><p><?php echo $errorMsg; ?></p></div>
            <?php endif; ?>
            
             <div class="bg-white p-6 rounded-xl shadow-orchid-custom">
                <div class="space-y-6">
                <?php if (empty($patientHistory)): ?>
                    <div class="text-center text-gray-500 py-10 border-2 border-dashed rounded-lg">
                        <i class="fa-solid fa-file-circle-xmark fa-3x text-gray-400 mb-3"></i>
                        <p>You have not uploaded any medical records yet.</p>
                        <p class="text-sm mt-1">Click the "Add New Record" button to get started.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($patientHistory as $history): ?>
                    <div class="border rounded-lg p-5 relative transition hover:shadow-md">
                         <div class="absolute top-4 right-4 flex space-x-3 text-gray-400">
                            <a href="edit_medical_record.php?history_id=<?php echo $history['historyID']; ?>" class="hover:text-blue-600" title="Edit"><i class="fa-solid fa-pencil"></i></a>
                            <a href="view_my_records.php?delete_id=<?php echo $history['historyID']; ?>" onclick="return confirm('Are you sure you want to permanently delete this record and all its documents?');" class="hover:text-red-600" title="Delete"><i class="fa-solid fa-trash-can"></i></a>
                        </div>
                        <p class="font-bold text-slate-800 text-lg mb-3 pb-3 border-b"><?php echo date('F j, Y', strtotime($history['visitDate'])); ?></p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                            <div class="space-y-3 text-gray-800">
                                <p><strong>Diagnosis:</strong><br><?php echo nl2br(htmlspecialchars($history['diagnosis'])); ?></p>
                                <p><strong>Notes:</strong><br><?php echo nl2br(htmlspecialchars($history['medicalHistory'])); ?></p>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-800 mb-2">Attached Documents:</p>
                                <ul class="list-none space-y-2">
                                    <?php if (!empty($history['labResultsFile'])): ?>
                                        <li class="flex items-center"><i class="fa-solid fa-file-pdf text-red-500 mr-2"></i><a href="<?php echo htmlspecialchars($history['labResultsFile']); ?>" target="_blank" class="text-purple-600 hover:underline">View Main Report</a></li>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($medicalDocuments[$history['historyID']])): ?>
                                        <?php foreach($medicalDocuments[$history['historyID']] as $doc): ?>
                                            <li class="flex items-center"><i class="fa-solid fa-file-lines text-gray-500 mr-2"></i><a href="<?php echo htmlspecialchars($doc['documentURL']); ?>" target="_blank" class="text-purple-600 hover:underline">View Document #<?php echo $doc['documentID']; ?></a></li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>

                                    <?php if (empty($history['labResultsFile']) && !isset($medicalDocuments[$history['historyID']])): ?>
                                        <li class="text-gray-500 italic">No documents were attached.</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>