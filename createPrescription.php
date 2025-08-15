<?php
session_start();
$conn = require_once 'config.php';
date_default_timezone_set('Asia/Dhaka');

// 1. ========= AUTHENTICATION & INITIALIZATION =========
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Doctor') {
    header("Location: login.php");
    exit();
}
$doctorID = $_SESSION['userID'];
$userName = $_SESSION['Name'];
$userAvatar = strtoupper(substr($userName, 0, 2));
$successMsg = "";
$errorMsg = "";
$patientInfo = null;
$appointmentID = null;

// 2. ========= DATA FETCHING (based on URL) =========
if (isset($_GET['appointment_id'])) {
    $appointmentID = (int)$_GET['appointment_id'];

    $patientQuery = "
        SELECT u.Name as patientName, p.patientID, p.age as patientAge, p.gender as patientGender
        FROM appointment a
        JOIN users u ON a.patientID = u.userID
        LEFT JOIN patient p ON u.userID = p.patientID
        WHERE a.appointmentID = ? AND a.providerID = ?
    ";
    $stmt = $conn->prepare($patientQuery);
    $stmt->bind_param("ii", $appointmentID, $doctorID);
    $stmt->execute();
    $patientInfo = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$patientInfo) {
        $errorMsg = "Invalid appointment selected or you do not have permission to access it.";
        $appointmentID = null;
    }
} else if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $errorMsg = "No appointment selected. Please create a prescription from the 'My Consultations' page.";
}

// 3. ========= HANDLE FORM SUBMISSION =========
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['appointment_id'])) {
    $appointmentID = (int)$_POST['appointment_id'];
    $medicineDosages = trim($_POST['medicine_dosages']);
    $instructions = trim($_POST['instructions']);
    $prescriptionDate = $_POST['prescription_date'];
    $patientNameForMessage = $_POST['patient_name']; // Get patient name from hidden input

    if (empty($medicineDosages)) {
        $errorMsg = "Medicine & Dosages field cannot be empty.";
    } else {
        $sql = "INSERT INTO prescription (appointmentID, doctorID, `medicineNames-dosages`, instructions, date) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iisss", $appointmentID, $doctorID, $medicineDosages, $instructions, $prescriptionDate);

        if ($stmt->execute()) {
            $updateSql = "UPDATE appointment SET status = 'Completed' WHERE appointmentID = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("i", $appointmentID);
            $updateStmt->execute();
            
            // --- (MODIFIED) Success Message ---
            $successMsg = "Prescription created for " . htmlspecialchars($patientNameForMessage) . " successfully!";
        } else {
            $errorMsg = "Failed to save prescription.";
        }
        $stmt->close();
    }
}

// Fetch Doctor's Info for the header
$doctorQuery = "SELECT u.Name, d.specialty, d.hospital FROM users u JOIN doctor d ON u.userID = d.doctorID WHERE u.userID = ?";
$docStmt = $conn->prepare($doctorQuery);
$docStmt->bind_param("i", $doctorID);
$docStmt->execute();
$doctorInfo = $docStmt->get_result()->fetch_assoc();
$docStmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Prescription - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-dark-orchid { background-color: #9932CC; }
        .text-dark-orchid { color: #9932CC; }
        .shadow-orchid-custom { box-shadow: 0 4px 6px -1px rgba(153, 50, 204, 0.1), 0 2px 4px -2px rgba(153, 50, 204, 0.1); }
        .prescription-pad { border: 1px solid #e2e8f0; border-radius: 0.5rem; background-color: #fff; padding: 2rem; max-width: 800px; margin: auto; }
        .rx-symbol { font-size: 3rem; font-weight: bold; color: #9932CC; line-height: 1; }
    </style>
</head>
<body class="bg-purple-50">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="w-64 bg-white border-r">
            <div class="p-6">
                <a href="#" class="text-2xl font-bold text-dark-orchid">CarePlus</a>
            </div>
            <nav class="px-4">
                <a href="doctorDashboard.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-table-columns w-5"></i><span>Dashboard</span></a>
                <a href="doctorProfile.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-regular fa-user w-5"></i><span>My Profile</span></a>
                <a href="doctor_schedule.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-calendar-days w-5"></i><span>My Schedule</span></a>
                <a href="consultationInfo.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-laptop-medical w-5"></i><span>Consultations</span></a>
                <a href="createPrescription.php" class="flex items-center space-x-3 px-4 py-3 bg-purple-100 text-dark-orchid rounded-lg"><i class="fa-solid fa-pills w-5"></i><span>Prescriptions</span></a>
                <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg mt-8"><i class="fa-solid fa-arrow-right-from-bracket w-5"></i><span>Logout</span></a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <header class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-800">Create Prescription</h1>
                    <p class="text-gray-600 mt-1">Prescription for appointment #<?php echo htmlspecialchars($appointmentID ?? 'N/A'); ?></p>
                </div>
                 <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <p class="font-semibold text-slate-700"><?php echo htmlspecialchars($userName); ?></p>
                        <p class="text-sm text-gray-500">Doctor</p>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-dark-orchid text-white flex items-center justify-center font-bold text-lg"><?php echo htmlspecialchars($userAvatar); ?></div>
                </div>
            </header>

            <?php if ($successMsg): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-r-lg" role="alert"><p><?php echo $successMsg; ?></p></div>
            <?php endif; ?>
            <?php if ($errorMsg): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-r-lg" role="alert"><p><?php echo $errorMsg; ?></p></div>
            <?php endif; ?>

            <?php if ($patientInfo): ?>
            <form action="createPrescription.php" method="POST">
                <div class="prescription-pad shadow-orchid-custom">
                    <header class="text-center border-b-2 border-gray-200 pb-4 mb-6">
                        <h2 class="text-3xl font-bold text-slate-800">Dr. <?php echo htmlspecialchars($doctorInfo['Name']); ?></h2>
                        <p class="text-md text-gray-600"><?php echo htmlspecialchars($doctorInfo['specialty']); ?></p>
                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($doctorInfo['hospital']); ?></p>
                    </header>

                    <div class="flex justify-between items-center border-b border-gray-200 pb-4 mb-6 text-sm">
                        <div class="w-2/3 space-y-1">
                            <p><strong>Patient Name:</strong> <span><?php echo htmlspecialchars($patientInfo['patientName'] ?? 'N/A'); ?></span></p>
                            <p><strong>Patient ID:</strong> <span><?php echo htmlspecialchars($patientInfo['patientID'] ?? 'N/A'); ?></span></p>
                        </div>
                        <div class="w-1/3 text-right space-y-1">
                            <p><strong>Age:</strong> <span><?php echo htmlspecialchars($patientInfo['patientAge'] ?? 'N/A'); ?></span>, <strong>Gender:</strong> <span><?php echo htmlspecialchars($patientInfo['patientGender'] ?? 'N/A'); ?></span></p>
                            <div>
                                <label for="prescription_date" class="font-bold">Date:</label>
                                <input type="date" id="prescription_date" name="prescription_date" value="<?php echo date('Y-m-d'); ?>" class="p-1 border rounded-md border-gray-200 text-sm">
                            </div>
                        </div>
                    </div>

                    <div class="flex">
                        <div class="rx-symbol mr-4 mt-2">R<sub>x</sub></div>
                        <div class="flex-1 space-y-4">
                             <div>
                                <label for="medicine_dosages" class="block text-sm font-medium text-gray-700">Medicines & Dosages</label>
                                <textarea name="medicine_dosages" id="medicine_dosages" rows="5" class="w-full p-3 mt-1 border border-gray-200 rounded-md focus:ring-2 focus:ring-purple-500" placeholder="e.g.,&#10;1. Napa 500mg (1+0+1) - 7 days&#10;2. Fexo 120mg (0+0+1) - 5 days" required></textarea>
                            </div>
                             <div>
                                <label for="instructions" class="block text-sm font-medium text-gray-700">Advice / Instructions</label>
                                <textarea name="instructions" id="instructions" rows="5" class="w-full p-3 mt-1 border border-gray-200 rounded-md focus:ring-2 focus:ring-purple-500" placeholder="e.g.,&#10;- Take medicine after meals.&#10;- Drink plenty of warm water.&#10;- Follow up after 7 days."></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <input type="hidden" name="appointment_id" value="<?php echo htmlspecialchars($appointmentID); ?>">
                    <!-- (NEW) Hidden input to carry the patient's name for the success message -->
                    <input type="hidden" name="patient_name" value="<?php echo htmlspecialchars($patientInfo['patientName']); ?>">


                    <footer class="mt-8 text-right">
                        <button type="submit" class="bg-dark-orchid text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition font-semibold">
                            <i class="fa-solid fa-save mr-2"></i>Save Prescription
                        </button>
                    </footer>
                </div>
            </form>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>