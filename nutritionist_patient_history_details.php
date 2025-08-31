<?php
session_start();
$conn = require_once 'config.php';
date_default_timezone_set('Asia/Dhaka');

// Protect this page: allow only logged-in Nutritionists
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Nutritionist') {
    header("Location: login.php");
    exit();
}

$nutritionistID = $_SESSION['userID'];
$userName = $_SESSION['Name'];
$userAvatar = strtoupper(substr($userName, 0, 2));
$errorMsg = "";

$selectedPatient = null;
$patientHistory = [];
$medicalDocuments = [];

// --- A patient MUST be selected to view this page ---
if (isset($_GET['patient_id'])) {
    $selectedPatientID = (int)$_GET['patient_id'];

    // Security Check: Verify this nutritionist has permission to view this patient
    $permStmt = $conn->prepare("SELECT COUNT(*) as count FROM appointment WHERE patientID = ? AND providerID = ?");
    $permStmt->bind_param("ii", $selectedPatientID, $nutritionistID);
    $permStmt->execute();
    $isAllowed = $permStmt->get_result()->fetch_assoc()['count'] > 0;
    $permStmt->close();

    if ($isAllowed) {
        // Fetch patient's basic info
        $patientInfoQuery = "SELECT u.Name, p.age, p.gender FROM users u JOIN patient p ON u.userID = p.patientID WHERE u.userID = ?";
        $stmt = $conn->prepare($patientInfoQuery);
        $stmt->bind_param("i", $selectedPatientID);
        $stmt->execute();
        $selectedPatient = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Fetch the patient's history records
        $historyQuery = "SELECT * FROM patienthistory WHERE patientID = ? ORDER BY visitDate DESC";
        $stmt = $conn->prepare($historyQuery);
        $stmt->bind_param("i", $selectedPatientID);
        $stmt->execute();
        $historyResult = $stmt->get_result();
        $historyIDs = [];
        while($row = $historyResult->fetch_assoc()) {
            $patientHistory[] = $row;
            $historyIDs[] = $row['historyID'];
        }
        $stmt->close();

        // Fetch all related medical documents
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
    } else {
        $errorMsg = "You do not have permission to view this patient's records.";
    }
} else {
    $errorMsg = "No patient selected.";
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patient History Details - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style> body { font-family: 'Inter', sans-serif; } .bg-dark-orchid { background-color: #9932CC; } </style>
</head>
<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <aside class="w-64 bg-white border-r">
            <div class="p-6"><a href="#" class="text-2xl font-bold text-dark-orchid">CarePlus</a></div>
            <nav class="px-4">
                <a href="nutritionistDashboard.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-table-columns w-5"></i><span>Dashboard</span></a>
                <a href="nutritionist_view_history.php" class="flex items-center space-x-3 px-4 py-3 bg-purple-100 text-dark-orchid rounded-lg"><i class="fa-solid fa-file-waveform w-5"></i><span>Patient History</span></a>
                <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg mt-8"><i class="fa-solid fa-arrow-right-from-bracket w-5"></i><span>Logout</span></a>
            </nav>
        </aside>

        <main class="flex-1 p-8">
            <header class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-800">Medical Record</h1>
                    <a href="nutritionist_view_history.php" class="text-sm text-blue-500 hover:text-blue-700 transition-colors"><i class="fa fa-arrow-left mr-1"></i> Back to Patient List</a>
                </div>
            </header>

            <?php if ($errorMsg): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert"><p><?php echo $errorMsg; ?></p></div>
            <?php elseif ($selectedPatient): ?>
            <div class="bg-white p-6 rounded-xl shadow-lg">
                <div class="border-b pb-4 mb-6">
                    <h2 class="text-2xl font-bold text-slate-800"><?php echo htmlspecialchars($selectedPatient['Name']); ?></h2>
                    <p class="text-sm text-gray-500">Age: <?php echo htmlspecialchars($selectedPatient['age']); ?>, Gender: <?php echo htmlspecialchars($selectedPatient['gender']); ?></p>
                </div>
                <div class="space-y-6">
                <?php if (empty($patientHistory)): ?>
                    <p class="text-center text-gray-500 py-10">No medical history found for this patient.</p>
                <?php else: ?>
                    <?php foreach ($patientHistory as $history): ?>
                    <div class="border rounded-lg p-4 bg-gray-50/50">
                        <p class="font-bold text-slate-700 text-lg"><?php echo date('F j, Y', strtotime($history['visitDate'])); ?></p>
                        <div class="mt-2 text-sm text-gray-800 space-y-3">
                            <p><strong>Diagnosis:</strong> <?php echo nl2br(htmlspecialchars($history['diagnosis'])); ?></p>
                            <p><strong>Medical History Notes:</strong> <?php echo nl2br(htmlspecialchars($history['medicalHistory'])); ?></p>
                            <div>
                                <p class="font-semibold">Attached Documents:</p>
                                <ul class="list-disc list-inside mt-1 space-y-1">
                                    <?php if (!empty($history['labResultsFile'])): ?>
                                        <li><a href="<?php echo htmlspecialchars($history['labResultsFile']); ?>" target="_blank" class="text-purple-600 hover:text-purple-800">View Main Lab Result</a></li>
                                    <?php endif; ?>
                                    <?php if (isset($medicalDocuments[$history['historyID']])): ?>
                                        <?php foreach($medicalDocuments[$history['historyID']] as $doc): ?>
                                            <li><a href="<?php echo htmlspecialchars($doc['documentURL']); ?>" target="_blank" class="text-purple-600 hover:text-purple-800">View Document #<?php echo $doc['documentID']; ?></a></li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <?php if (empty($history['labResultsFile']) && !isset($medicalDocuments[$history['historyID']])): ?>
                                        <li class="text-gray-500">No documents attached.</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>