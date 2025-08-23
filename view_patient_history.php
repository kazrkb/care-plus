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

// --- Fetch appointments from the last 7 days and next 7 days ---
$query = "
    SELECT a.appointmentDate, u.userID as patientID, u.Name as patientName, p.age, p.gender
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

            <div class="space-y-10">
                 <?php function render_patient_card($patient) { ?>
                    <div class="bg-white p-4 rounded-lg shadow-sm flex justify-between items-center">
                        <div class="flex items-center space-x-4">
                            <i class="fa-solid fa-user-circle fa-2x text-gray-400"></i>
                            <div>
                                <p class="font-bold text-slate-800"><?php echo htmlspecialchars($patient['patientName']); ?></p>
                                <p class="text-sm text-gray-500">
                                    ID: <?php echo $patient['patientID']; ?> | 
                                    Age: <?php echo htmlspecialchars($patient['age'] ?? 'N/A'); ?> | 
                                    Gender: <?php echo htmlspecialchars($patient['gender'] ?? 'N/A'); ?>
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                             <p class="text-sm font-semibold text-purple-600">
                                <?php echo date('D, M j, Y', strtotime($patient['appointmentDate'])); ?> at <?php echo date('g:i A', strtotime($patient['appointmentDate'])); ?>
                             </p>
                             <a href="patient_history_details.php?patient_id=<?php echo $patient['patientID']; ?>" class="text-sm text-blue-500 hover:underline">View Full History &rarr;</a>
                        </div>
                    </div>
                <?php } ?>

                <section>
                    <h2 class="text-2xl font-bold text-slate-800 mb-4 border-b pb-2">Today's Appointments</h2>
                    <div class="space-y-3">
                        <?php if (empty($today)): ?>
                            <p class="text-gray-500">No appointments scheduled for today.</p>
                        <?php else: ?>
                            <?php foreach ($today as $patient) { render_patient_card($patient); } ?>
                        <?php endif; ?>
                    </div>
                </section>
                
                <section>
                    <h2 class="text-2xl font-bold text-slate-800 mb-4 border-b pb-2">Upcoming</h2>
                    <div class="space-y-3">
                        <?php if (empty($upcoming)): ?>
                             <p class="text-gray-500">No upcoming appointments in the next 7 days.</p>
                        <?php else: ?>
                            <?php foreach ($upcoming as $patient) { render_patient_card($patient); } ?>
                        <?php endif; ?>
                    </div>
                </section>

                <section>
                    <h2 class="text-2xl font-bold text-slate-800 mb-4 border-b pb-2">Recent</h2>
                     <div class="space-y-3">
                        <?php if (empty($recent)): ?>
                             <p class="text-gray-500">No appointments in the last 7 days.</p>
                        <?php else: ?>
                             <?php foreach (array_reverse($recent) as $patient) { render_patient_card($patient); } // Show most recent first ?>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </main>
    </div>
</body>
</html>