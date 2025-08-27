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

// --- Handle Archiving a single recent appointment ---
if (isset($_GET['archive_id'])) {
    $appointmentID = (int)$_GET['archive_id'];
    $stmt = $conn->prepare("UPDATE appointment SET status = 'Archived' WHERE appointmentID = ? AND providerID = ? AND status = 'Completed'");
    $stmt->bind_param("ii", $appointmentID, $doctorID);
    if ($stmt->execute()) {
        $successMsg = "Appointment moved to archive.";
    }
    $stmt->close();
}

// --- Handle Clearing all recent history ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_recent_history'])) {
    $stmt = $conn->prepare("UPDATE appointment SET status = 'Archived' WHERE providerID = ? AND status = 'Completed' AND appointmentDate < CURDATE()");
    $stmt->bind_param("i", $doctorID);
    if ($stmt->execute()) {
        $successMsg = "All recent patient history has been cleared from this list.";
    }
    $stmt->close();
}


// --- (MODIFIED) Fetch appointments from the last 7 days and the next 7 days ---
$query = "
    SELECT a.appointmentID, a.appointmentDate, u.userID as patientID, u.Name as patientName, p.age, p.gender
    FROM appointment a
    JOIN users u ON a.patientID = u.userID
    LEFT JOIN patient p ON u.userID = p.patientID
    WHERE a.providerID = ? AND a.status IN ('Scheduled', 'Completed')
      AND a.appointmentDate BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY a.appointmentDate ASC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $doctorID);
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// Group appointments for display
$today = [];
$upcoming = [];
$recent = [];
$now = new DateTime();
$currentDate = $now->format('Y-m-d');

foreach ($appointments as $appt) {
    $apptDate = new DateTime($appt['appointmentDate']);
    $apptDateStr = $apptDate->format('Y-m-d');

    if ($apptDateStr === $currentDate) {
        $today[] = $appt;
    } elseif ($apptDateStr > $currentDate) {
        $upcoming[] = $appt;
    } else {
        $recent[] = $appt;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patient History - CarePlus</title>
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
                <a href="doctorDashboard.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-table-columns w-5"></i><span>Dashboard</span></a>
                <a href="consultationInfo.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-laptop-medical w-5"></i><span>Consultations</span></a>
                <a href="view_patient_history.php" class="flex items-center space-x-3 px-4 py-3 bg-purple-100 text-dark-orchid rounded-lg"><i class="fa-solid fa-file-waveform w-5"></i><span>Patient History</span></a>
                <a href="create_prescription.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-pills w-5"></i><span>Prescriptions</span></a>
                <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg mt-8"><i class="fa-solid fa-arrow-right-from-bracket w-5"></i><span>Logout</span></a>
            </nav>
        </aside>

        <main class="flex-1 p-8">
            <header class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-800">Patient List</h1>
                    <p class="text-gray-600 mt-1">Select a patient to view their detailed medical history.</p>
                </div>
                <div class="flex items-center space-x-4">
                     <div class="text-right">
                        <p class="font-semibold text-slate-700"><?php echo htmlspecialchars($userName); ?></p>
                        <p class="text-sm text-gray-500">Doctor</p>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-dark-orchid text-white flex items-center justify-center font-bold text-lg"><?php echo htmlspecialchars($userAvatar); ?></div>
                </div>
            </header>

            <?php if ($successMsg): ?><div class="mb-6 p-4 bg-green-100 text-green-800 border-l-4 border-green-500 rounded-r-lg" role="alert"><p><?php echo $successMsg; ?></p></div><?php endif; ?>

            <div class="space-y-10">
                 <?php function render_patient_card($patient, $isRecent = false) { ?>
                    <div class="bg-white p-4 rounded-lg shadow-sm flex justify-between items-center transition hover:shadow-md">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center">
                                <i class="fa-solid fa-user fa-lg text-gray-400"></i>
                            </div>
                            <div>
                                <p class="font-bold text-slate-800"><?php echo htmlspecialchars($patient['patientName']); ?></p>
                                <p class="text-sm text-gray-500">
                                    Appointment: <?php echo date('D, M j', strtotime($patient['appointmentDate'])); ?> at <?php echo date('g:i A', strtotime($patient['appointmentDate'])); ?>
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-4">
                             <a href="patient_history_details.php?patient_id=<?php echo $patient['patientID']; ?>" class="px-4 py-2 bg-purple-100 text-purple-700 text-sm font-semibold rounded-md hover:bg-purple-200">View Full History</a>
                             <?php if ($isRecent): ?>
                                <a href="view_patient_history.php?archive_id=<?php echo $patient['appointmentID']; ?>" class="text-gray-400 hover:text-red-600" title="Remove from list">
                                    <i class="fa-solid fa-times-circle"></i>
                                </a>
                             <?php endif; ?>
                        </div>
                    </div>
                <?php } ?>

                <section>
                    <h2 class="text-2xl font-bold text-slate-800 mb-4">Today's Appointments</h2>
                    <div class="space-y-3">
                        <?php if (empty($today)): ?><p class="text-gray-500">No appointments scheduled for today.</p><?php else: ?>
                            <?php foreach ($today as $patient) { render_patient_card($patient); } ?>
                        <?php endif; ?>
                    </div>
                </section>
                
                <section>
                    <h2 class="text-2xl font-bold text-slate-800 mb-4">Upcoming</h2>
                    <div class="space-y-3">
                        <?php if (empty($upcoming)): ?><p class="text-gray-500">No upcoming appointments in the next 7 days.</p><?php else: ?>
                            <?php foreach ($upcoming as $patient) { render_patient_card($patient); } ?>
                        <?php endif; ?>
                    </div>
                </section>

                <section>
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-2xl font-bold text-slate-800">Recent History (Last 7 Days)</h2>
                        <?php if (!empty($recent)): ?>
                        <form method="POST" action="view_patient_history.php" onsubmit="return confirm('Are you sure you want to clear all recent history from this list?');">
                            <button type="submit" name="clear_recent_history" class="text-sm text-gray-500 hover:text-red-600">
                                <i class="fa-solid fa-broom mr-1"></i>Clear Recent History
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                     <div class="space-y-3">
                        <?php if (empty($recent)): ?><p class="text-gray-500">No completed appointments in the last 7 days.</p><?php else: ?>
                             <?php foreach (array_reverse($recent) as $patient) { render_patient_card($patient, true); } ?>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </main>
    </div>
</body>
</html>