<?php
session_start();
$conn = require_once 'config.php';
date_default_timezone_set('Asia/Dhaka');

// Protect this page: allow only logged-in Doctors
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Doctor') {
    header("Location: login.php");
    exit();
}

$doctorID = $_SESSION['userID'];
$userName = $_SESSION['Name'];
$userAvatar = strtoupper(substr($userName, 0, 2));
$successMsg = "";
$errorMsg = "";
$prescription = null;

// Get prescription ID from URL
if (!isset($_GET['id'])) {
    header("Location: doctor_view_prescriptions.php");
    exit();
}

$prescriptionID = (int)$_GET['id'];

// Fetch prescription details
$query = "
    SELECT p.*, u.Name as patientName, pt.age as patientAge, pt.gender as patientGender, 
           a.appointmentDate, du.Name as doctorName, d.specialty as doctorSpecialty, d.hospital as doctorHospital
    FROM prescription p
    JOIN appointment a ON p.appointmentID = a.appointmentID
    JOIN users u ON a.patientID = u.userID
    LEFT JOIN patient pt ON u.userID = pt.patientID
    JOIN doctor d ON p.doctorID = d.doctorID
    JOIN users du ON d.doctorID = du.userID
    WHERE p.prescriptionID = ? AND p.doctorID = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $prescriptionID, $doctorID);
$stmt->execute();
$prescription = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$prescription) {
    $errorMsg = "Prescription not found or you don't have permission to edit it.";
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && $prescription) {
    $medicineDosages = trim($_POST['medicine_dosages']);
    $instructions = trim($_POST['instructions']);
    $prescriptionDate = $_POST['prescription_date'];
    
    if (empty($medicineDosages)) {
        $errorMsg = "Medicine & Dosages field cannot be empty.";
    } else {
        $updateSql = "UPDATE prescription SET `medicineNames-dosages` = ?, instructions = ?, date = ? WHERE prescriptionID = ? AND doctorID = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("sssii", $medicineDosages, $instructions, $prescriptionDate, $prescriptionID, $doctorID);
        
        if ($updateStmt->execute()) {
            $successMsg = "Prescription updated successfully!";
            // Refresh prescription data
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $prescriptionID, $doctorID);
            $stmt->execute();
            $prescription = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } else {
            $errorMsg = "Failed to update prescription.";
        }
        $updateStmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Prescription - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-dark-orchid { background-color: #9932CC; }
        .text-dark-orchid { color: #9932CC; }
        .shadow-orchid-custom { box-shadow: 0 4px 6px -1px rgba(153, 50, 204, 0.1), 0 2px 4px -2px rgba(153, 50, 204, 0.1); }
        .prescription-pad { 
            border: 1px solid #e2e8f0; 
            border-radius: 0.5rem; 
            background-color: #fff; 
            padding: 2rem; 
            max-width: 800px; 
            margin: auto; 
        }
        .rx-symbol { 
            font-size: 3rem; 
            font-weight: bold; 
            color: #9932CC; 
            line-height: 1; 
        }
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
                <a href="doctorDashboard.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg">
                    <i class="fa-solid fa-table-columns w-5"></i><span>Dashboard</span>
                </a>
                <a href="consultationInfo.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg">
                    <i class="fa-solid fa-laptop-medical w-5"></i><span>Consultations</span>
                </a>
                <a href="doctor_view_prescriptions.php" class="flex items-center space-x-3 px-4 py-3 bg-purple-100 text-dark-orchid rounded-lg">
                    <i class="fa-solid fa-pills w-5"></i><span>My Prescriptions</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <header class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-800">Edit Prescription</h1>
                    <p class="text-gray-600 mt-1">Prescription ID: #<?php echo htmlspecialchars($prescriptionID); ?></p>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="doctor_view_prescriptions.php" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 font-semibold">
                        <i class="fa-solid fa-arrow-left mr-2"></i>Back to List
                    </a>
                    <div class="text-right">
                        <p class="font-semibold text-slate-700"><?php echo htmlspecialchars($userName); ?></p>
                        <p class="text-sm text-gray-500">Doctor</p>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-dark-orchid text-white flex items-center justify-center font-bold text-lg">
                        <?php echo htmlspecialchars($userAvatar); ?>
                    </div>
                </div>
            </header>

            <?php if ($successMsg): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-r-lg" role="alert">
                <p><?php echo $successMsg; ?></p>
            </div>
            <?php endif; ?>
            
            <?php if ($errorMsg): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-r-lg" role="alert">
                <p><?php echo $errorMsg; ?></p>
            </div>
            <?php endif; ?>

            <?php if ($prescription): ?>
            <form action="edit_prescription.php?id=<?php echo $prescriptionID; ?>" method="POST">
                <div class="prescription-pad shadow-orchid-custom">
                    <header class="text-center border-b-2 border-gray-200 pb-4 mb-6">
                        <h2 class="text-3xl font-bold text-slate-800">Dr. <?php echo htmlspecialchars($prescription['doctorName']); ?></h2>
                        <p class="text-md text-gray-600"><?php echo htmlspecialchars($prescription['doctorSpecialty']); ?></p>
                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($prescription['doctorHospital']); ?></p>
                    </header>

                    <div class="flex justify-between items-center border-b border-gray-200 pb-4 mb-6 text-sm">
                        <div class="w-2/3 space-y-1">
                            <p><strong>Patient Name:</strong> <span><?php echo htmlspecialchars($prescription['patientName']); ?></span></p>
                            <p><strong>Appointment Date:</strong> <span><?php echo date('M d, Y', strtotime($prescription['appointmentDate'])); ?></span></p>
                        </div>
                        <div class="w-1/3 text-right space-y-1">
                            <p><strong>Age:</strong> <span><?php echo htmlspecialchars($prescription['patientAge'] ?? 'N/A'); ?></span>, <strong>Gender:</strong> <span><?php echo htmlspecialchars($prescription['patientGender'] ?? 'N/A'); ?></span></p>
                            <div>
                                <label for="prescription_date" class="font-bold">Date:</label>
                                <input type="date" id="prescription_date" name="prescription_date" 
                                       value="<?php echo htmlspecialchars($prescription['date']); ?>" 
                                       class="p-1 border rounded-md border-gray-200 text-sm">
                            </div>
                        </div>
                    </div>

                    <div class="flex">
                        <div class="rx-symbol mr-4 mt-2">R<sub>x</sub></div>
                        <div class="flex-1 space-y-4">
                            <div>
                                <label for="medicine_dosages" class="block text-sm font-medium text-gray-700">Medicines & Dosages</label>
                                <textarea name="medicine_dosages" id="medicine_dosages" rows="6" 
                                          class="w-full p-3 mt-1 border border-gray-200 rounded-md focus:ring-2 focus:ring-purple-500" 
                                          placeholder="e.g.,&#10;1. Napa 500mg (1+0+1) - 7 days&#10;2. Fexo 120mg (0+0+1) - 5 days" 
                                          required><?php echo htmlspecialchars($prescription['medicineNames-dosages']); ?></textarea>
                            </div>
                            <div>
                                <label for="instructions" class="block text-sm font-medium text-gray-700">Advice / Instructions</label>
                                <textarea name="instructions" id="instructions" rows="5" 
                                          class="w-full p-3 mt-1 border border-gray-200 rounded-md focus:ring-2 focus:ring-purple-500" 
                                          placeholder="e.g.,&#10;- Take medicine after meals.&#10;- Drink plenty of warm water.&#10;- Follow up after 7 days."><?php echo htmlspecialchars($prescription['instructions'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <footer class="mt-8 text-right space-x-4">
                        <a href="doctor_view_prescriptions.php" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 transition font-semibold">
                            <i class="fa-solid fa-times mr-2"></i>Cancel
                        </a>
                        <button type="submit" class="bg-dark-orchid text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition font-semibold">
                            <i class="fa-solid fa-save mr-2"></i>Update Prescription
                        </button>
                    </footer>
                </div>
            </form>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
