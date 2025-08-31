<?php
session_start();
$conn = require_once 'config.php';
date_default_timezone_set('Asia/Dhaka');

// Protect this page: allow only logged-in CareGivers
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'CareGiver') {
    header("Location: login.php");
    exit();
}

$careGiverID = $_SESSION['userID'];
$userName = $_SESSION['Name'];
$userAvatar = strtoupper(substr($userName, 0, 2));

// --- Handle Dismissing a Notification ---
if (isset($_GET['dismiss_notification_id'])) {
    $notificationID = (int)$_GET['dismiss_notification_id'];
    $stmt = $conn->prepare("UPDATE notification SET status = 'Read' WHERE notificationID = ? AND userID = ?");
    $stmt->bind_param("ii", $notificationID, $careGiverID);
    $stmt->execute();
    $stmt->close();
    header("Location: careGiverDashboard.php");
    exit();
}

// --- Fetch all 'Unread' notifications ---
$notifyQuery = "SELECT * FROM notification WHERE userID = ? AND status = 'Unread' ORDER BY notificationID DESC";
$stmt = $conn->prepare($notifyQuery);
$stmt->bind_param("i", $careGiverID);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- Fetch Progress Data (replace with your actual query) ---
$progressData = [
    ['patientName'=>'Alice','dataType'=>'blood_pressure','value'=>120,'recordedDate'=>'2025-08-29 08:00'],
    ['patientName'=>'Alice','dataType'=>'blood_sugar','value'=>5.5,'recordedDate'=>'2025-08-29 09:00'],
    ['patientName'=>'Bob','dataType'=>'weight','value'=>70,'recordedDate'=>'2025-08-30 10:00'],
    ['patientName'=>'Bob','dataType'=>'heart_rate','value'=>75,'recordedDate'=>'2025-08-30 11:00'],
    ['patientName'=>'Charlie','dataType'=>'temperature','value'=>36.6,'recordedDate'=>'2025-08-31 12:00'],
    ['patientName'=>'Charlie','dataType'=>'oxygen_level','value'=>98,'recordedDate'=>'2025-08-31 12:30'],
];

$dates = array_unique(array_map(fn($r)=>date('Y-m-d', strtotime($r['recordedDate'])),$progressData));
sort($dates);

$lineDatasets = [];
$dataTypes = array_unique(array_map(fn($r)=>$r['dataType'],$progressData));
$colors = ['#6D28D9','#2563EB','#059669','#F59E0B','#EF4444','#0EA5E9','#22C55E','#A855F7'];
foreach($dataTypes as $i=>$type){
    $series = [];
    foreach($dates as $d){
        $vals = array_filter($progressData, fn($r)=>$r['dataType']==$type && date('Y-m-d', strtotime($r['recordedDate']))==$d);
        $series[] = $vals ? array_sum(array_column($vals,'value'))/count($vals) : null;
    }
    $lineDatasets[] = [
        'label'=>ucwords(str_replace('_',' ',$type)),
        'data'=>$series,
        'borderColor'=>$colors[$i%count($colors)],
        'tension'=>0.3
    ];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CareGiver Dashboard - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-dark-orchid { background-color: #9932CC; }
        .text-dark-orchid { color: #9932CC; }
    </style>
</head>
<body class="bg-purple-50">
<div class="flex min-h-screen">
    <aside class="w-64 bg-white border-r">
        <div class="p-6">
            <a href="#" class="text-2xl font-bold text-dark-orchid">CarePlus</a>
        </div>
        <nav class="px-4">
            <a href="careGiverDashboard.php" class="flex items-center space-x-3 px-4 py-3 bg-purple-100 text-dark-orchid rounded-lg"><i class="fa-solid fa-table-columns w-5"></i><span>Dashboard</span></a>
            <a href="caregiverProfile.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-regular fa-user w-5"></i><span>My Profile</span></a>
            <a href="caregiver_availability.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-calendar-days w-5"></i><span>Provide Availability</span></a>
            <a href="#" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-book-medical w-5"></i><span>View Patient Details</span></a>
            <a href="#" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-notes-medical w-5"></i><span>Care Plan</span></a>
            <a href="careGiverProgressAnalytics.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-chart-line w-5"></i><span>Progress Analytics</span></a>
            <a href="careGiverProgressAnalytics.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-money-bill-wave w-5"></i><span>Transactions</span></a>
            <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg mt-8"><i class="fa-solid fa-arrow-right-from-bracket w-5"></i><span>Logout</span></a>
        </nav>
    </aside>
    <main class="flex-1 p-8">
        <header class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-slate-800">CareGiver Dashboard</h1>
            <div class="flex items-center space-x-4">
                 <div class="text-right">
                    <p class="font-semibold text-slate-700"><?php echo htmlspecialchars($userName); ?></p>
                    <p class="text-sm text-gray-500">CareGiver</p>
                </div>
                <div class="w-10 h-10 rounded-full bg-dark-orchid text-white flex items-center justify-center font-bold text-lg"><?php echo htmlspecialchars($userAvatar); ?></div>
            </div>
        </header>

        <?php if (!empty($notifications)): ?>
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-slate-700 mb-3">Notifications</h2>
            <div class="space-y-3">
                <?php foreach ($notifications as $notification): ?>
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-800 p-4 flex justify-between items-center rounded-r-lg shadow-sm">
                    <div class="flex items-center">
                        <i class="fa-solid fa-bell mr-3"></i>
                        <p><?php echo htmlspecialchars($notification['message']); ?></p>
                    </div>
                    <a href="careGiverDashboard.php?dismiss_notification_id=<?php echo $notification['notificationID']; ?>" class="text-sm font-semibold hover:underline" title="Mark as Read">Dismiss</a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Dashboard Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <a href="#" class="bg-white p-6 rounded-lg shadow-lg text-center hover:shadow-xl hover:-translate-y-1 transition-transform">
                <i class="fa-solid fa-book-medical fa-2x text-dark-orchid mb-3"></i>
                <h3 class="text-lg font-semibold">View Patient Details</h3>
            </a>
            <a href="#" class="bg-white p-6 rounded-lg shadow-lg text-center hover:shadow-xl hover:-translate-y-1 transition-transform">
                <i class="fa-solid fa-notes-medical fa-2x text-dark-orchid mb-3"></i>
                <h3 class="text-lg font-semibold">Update Care Plan</h3>
            </a>
            <a href="#" class="bg-white p-6 rounded-lg shadow-lg text-center hover:shadow-xl hover:-translate-y-1 transition-transform">
                <i class="fa-solid fa-chart-line fa-2x text-dark-orchid mb-3"></i>
                <h3 class="text-lg font-semibold">Log Progress</h3>
            </a>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8 mt-6">
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h3 class="text-lg font-semibold mb-4 text-slate-800">Patient Progress Overview</h3>
                <canvas id="lineChart" height="250"></canvas>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h3 class="text-lg font-semibold mb-4 text-slate-800">Data Type Distribution</h3>
                <canvas id="pieChart" height="250"></canvas>
            </div>
        </div>

    </main>
</div>

<script>
// Line Chart
const lineLabels = <?php echo json_encode($dates); ?>;
const lineDatasetsJs = <?php echo json_encode($lineDatasets); ?>;

new Chart(document.getElementById('lineChart').getContext('2d'), {
    type:'line',
    data:{labels:lineLabels,datasets:lineDatasetsJs},
    options:{responsive:true,plugins:{legend:{position:'bottom'}},scales:{y:{beginAtZero:false}}}
});

// Pie Chart
const pieLabels = <?php echo json_encode(array_map(fn($r)=>ucwords(str_replace('_',' ',$r['dataType'])), $progressData)); ?>;
const pieValues = <?php echo json_encode(array_map(fn($r)=>floatval($r['value']), $progressData)); ?>;

new Chart(document.getElementById('pieChart').getContext('2d'), {
    type:'doughnut',
    data:{labels:pieLabels,datasets:[{data:pieValues,borderWidth:1,backgroundColor:['#6D28D9','#2563EB','#059669','#F59E0B','#EF4444','#0EA5E9','#22C55E','#A855F7']}]},
    options:{plugins:{legend:{position:'bottom'}},cutout:'55%'}
});
</script>
</body>
</html>
