<?php
// careGiverProgressAnalytics.php
// ------------------------- SECURITY & SESSION -------------------------
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);

session_start();
$conn = require_once 'config.php';
date_default_timezone_set('Asia/Dhaka');
// 30-min session timeout
$timeout = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_unset();
    session_destroy();
    header("Location: login.php?error=Session expired. Please login again");
    exit();
}
$_SESSION['last_activity'] = time();
// Require caregiver
if (!isset($_SESSION['userID'], $_SESSION['role']) || $_SESSION['role'] !== 'CareGiver') {
    header("Location: login.php?error=Access denied. This page is for CareGivers only");
    exit();
}
// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$careGiverID = (int)$_SESSION['userID'];
$userName    = $_SESSION['Name'] ?? 'CareGiver';
// ------------------------- DUMMY DATA -------------------------
$patients = [
    ['patientID' => 1, 'patientName' => 'Alice'],
    ['patientID' => 2, 'patientName' => 'Bob'],
    ['patientID' => 3, 'patientName' => 'Charlie'],
];
$patientIds = array_column($patients, 'patientID');

$progressData = [
    ['patientID'=>1,'dataType'=>'blood_pressure','value'=>120,'measurementUnit'=>'mmHg','recordedDate'=>'2025-08-29 08:00','notes'=>'Normal','patientName'=>'Alice'],
    ['patientID'=>1,'dataType'=>'blood_sugar','value'=>5.5,'measurementUnit'=>'mmol/L','recordedDate'=>'2025-08-29 09:00','notes'=>'After breakfast','patientName'=>'Alice'],
    ['patientID'=>2,'dataType'=>'weight','value'=>70,'measurementUnit'=>'kg','recordedDate'=>'2025-08-30 10:00','notes'=>'','patientName'=>'Bob'],
    ['patientID'=>2,'dataType'=>'heart_rate','value'=>75,'measurementUnit'=>'bpm','recordedDate'=>'2025-08-30 11:00','notes'=>'Resting','patientName'=>'Bob'],
    ['patientID'=>3,'dataType'=>'temperature','value'=>36.6,'measurementUnit'=>'°C','recordedDate'=>'2025-08-31 12:00','notes'=>'Normal','patientName'=>'Charlie'],
    ['patientID'=>3,'dataType'=>'oxygen_level','value'=>98,'measurementUnit'=>'%','recordedDate'=>'2025-08-31 12:30','notes'=>'Good','patientName'=>'Charlie'],
];
// ------------------------- NORMALIZE -------------------------
foreach ($progressData as &$row) {
    $unitLower = mb_strtolower($row['measurementUnit'] ?? '');
    if ($row['dataType'] === 'blood_sugar' && ($unitLower === 'mmol/l' || $unitLower === 'mmol\\l')) {
        $row['value'] = round(((float)$row['value']) * 18,2);
        $row['measurementUnit'] = 'mg/dL';
    }
    $row['value'] = (float)$row['value'];
}
unset($row);

// ------------------------- STATS & CHART PREP -------------------------
$uniquePatients = [];
$dataTypeCounts = [];
$dateLabelsSet = [];
$byTypeByDate = [];
$avgByType = [];

foreach ($progressData as $row) {
    $uniquePatients[$row['patientID']] = true;
    $type = $row['dataType'];
    $date = date('Y-m-d', strtotime($row['recordedDate']));
    $val = (float)$row['value'];

    $dataTypeCounts[$type] = ($dataTypeCounts[$type] ?? 0) + 1;
    $dateLabelsSet[$date] = true;

    if (!isset($byTypeByDate[$type])) $byTypeByDate[$type] = [];
    if (!isset($byTypeByDate[$type][$date])) $byTypeByDate[$type][$date] = [];
    $byTypeByDate[$type][$date][] = $val;

    if (!isset($avgByType[$type])) $avgByType[$type] = ['sum'=>0,'n'=>0,'unit'=>$row['measurementUnit']];
    $avgByType[$type]['sum'] += $val;
    $avgByType[$type]['n'] += 1;
    $avgByType[$type]['unit'] = $row['measurementUnit'];
}

$totalPatients = count($uniquePatients);
$totalRecords  = count($progressData);
$todayRecords  = 0;
$today = date('Y-m-d');
foreach ($progressData as $r) {
    if (date('Y-m-d', strtotime($r['recordedDate'])) === $today) $todayRecords++;
}
$totalTypes = count($dataTypeCounts);

$labels = array_keys($dateLabelsSet);
sort($labels);

$prettyLabel = function($t){ return ucwords(str_replace('_',' ',$t)); };
$colorPool = ['#6D28D9','#2563EB','#059669','#F59E0B','#EF4444','#0EA5E9','#22C55E','#A855F7'];
$colorIdx = 0;

$lineDatasets = [];
foreach ($byTypeByDate as $type => $dates) {
    $series = [];
    foreach ($labels as $d) {
        $series[] = isset($dates[$d]) ? array_sum($dates[$d])/count($dates[$d]) : null;
    }
    $lineDatasets[] = [
        'label' => $prettyLabel($type) . (isset($avgByType[$type]['unit']) && $avgByType[$type]['unit'] ? " ({$avgByType[$type]['unit']})" : ''),
        'data'  => $series,
        'borderColor' => $colorPool[$colorIdx % count($colorPool)],
        'tension' => 0.3,
        'spanGaps' => true
    ];
    $colorIdx++;
}

// Pie chart
$pieLabels = [];
$pieValues = [];
foreach ($dataTypeCounts as $t=>$cnt){ $pieLabels[]=$prettyLabel($t); $pieValues[]=$cnt; }

// Bar chart
$barLabels = [];
$barValues = [];
foreach ($avgByType as $t=>$agg){
    $barLabels[] = $prettyLabel($t).(isset($agg['unit'])&&$agg['unit']? " ({$agg['unit']})":'');
    $barValues[] = $agg['n'] ? round($agg['sum']/$agg['n'],2):0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Progress Analytics - CarePlus</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
<style>
body { font-family:'Inter',sans-serif }
.bg-dark-orchid { background-color:#7C3AED; }
.text-dark-orchid { color:#7C3AED; }
.card { box-shadow:0 10px 20px rgba(0,0,0,.06); }
</style>
</head>
<body class="bg-purple-50">
<div class="flex min-h-screen">
<aside class="w-64 bg-white border-r p-6">
    <a href="careGiverDashboard.php" class="text-2xl font-bold text-dark-orchid">CarePlus</a>
    <nav class="mt-6 space-y-2">
        <a href="careGiverDashboard.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-purple-100"><i class="fa-solid fa-house w-5"></i><span>Dashboard</span></a>
        <a href="careGiverProgressAnalytics.php" class="flex items-center space-x-3 px-4 py-3 bg-purple-100 text-dark-orchid rounded-lg"><i class="fa-solid fa-chart-line w-5"></i><span>Progress Analytics</span></a>
    </nav>
</aside>

<main class="flex-1 p-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">Progress Analytics</h1>
            <p class="text-sm text-gray-500">Health progress and records for your patients</p>
        </div>
        <div class="flex items-center space-x-3">
            <div class="text-right">
                <p class="font-semibold text-slate-700"><?php echo htmlspecialchars($userName); ?></p>
                <p class="text-sm text-gray-500">CareGiver</p>
            </div>
            <div class="w-10 h-10 rounded-full bg-dark-orchid text-white flex items-center justify-center font-bold">
                <?php echo htmlspecialchars(strtoupper(substr($userName ?? 'CG',0,2))); ?>
            </div>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-6 rounded-lg card"><div class="flex items-center justify-between"><div><p class="text-sm text-gray-500">Total Patients</p><h3 class="text-2xl font-bold text-slate-800"><?php echo $totalPatients; ?></h3></div><div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center"><i class="fa-solid fa-users text-dark-orchid text-xl"></i></div></div></div>
        <div class="bg-white p-6 rounded-lg card"><div class="flex items-center justify-between"><div><p class="text-sm text-gray-500">Total Records</p><h3 class="text-2xl font-bold text-slate-800"><?php echo $totalRecords; ?></h3></div><div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center"><i class="fa-solid fa-notes-medical text-dark-orchid text-xl"></i></div></div></div>
        <div class="bg-white p-6 rounded-lg card"><div class="flex items-center justify-between"><div><p class="text-sm text-gray-500">Today's Records</p><h3 class="text-2xl font-bold text-slate-800"><?php echo $todayRecords; ?></h3></div><div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center"><i class="fa-solid fa-calendar-day text-dark-orchid text-xl"></i></div></div></div>
        <div class="bg-white p-6 rounded-lg card"><div class="flex items-center justify-between"><div><p class="text-sm text-gray-500">Data Types</p><h3 class="text-2xl font-bold text-slate-800"><?php echo $totalTypes; ?></h3></div><div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center"><i class="fa-solid fa-chart-pie text-dark-orchid text-xl"></i></div></div></div>
    </div>

    <!-- Main Chart -->
    <div class="bg-white p-6 rounded-lg card mb-8">
        <h3 class="text-lg font-semibold mb-4 text-slate-800">Comprehensive Patient Progress Overview</h3>
        <div class="h-[380px]"><canvas id="mainChart"></canvas></div>
    </div>

    <!-- Supporting Charts -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="bg-white p-6 rounded-lg card">
            <h3 class="text-lg font-semibold mb-4 text-slate-800">Measurements Distribution</h3>
            <canvas id="distributionPieChart" height="250"></canvas>
        </div>
        <div class="bg-white p-6 rounded-lg card">
            <h3 class="text-lg font-semibold mb-4 text-slate-800">Average by Data Type</h3>
            <canvas id="avgBarChart" height="250"></canvas>
        </div>
    </div>

    <!-- Table with Add Button -->
    <div class="bg-white rounded-lg card mb-8">
        <div class="p-6 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-xl font-semibold text-slate-800">Progress Records</h2>
            <a href="add_progress_record.php" class="px-4 py-2 bg-dark-orchid text-white rounded-lg hover:bg-opacity-90 flex items-center space-x-2"><i class="fa-solid fa-plus"></i><span>Add Record</span></a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-4 font-semibold text-gray-500">PATIENT NAME</th>
                        <th class="px-6 py-4 font-semibold text-gray-500">DATA TYPE</th>
                        <th class="px-6 py-4 font-semibold text-gray-500">VALUE</th>
                        <th class="px-6 py-4 font-semibold text-gray-500">UNIT</th>
                        <th class="px-6 py-4 font-semibold text-gray-500">DATE & TIME</th>
                        <th class="px-6 py-4 font-semibold text-gray-500">NOTES</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($progressData as $rec): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($rec['patientName']); ?></td>
                    <td class="px-6 py-4"><?php echo htmlspecialchars(ucwords(str_replace('_',' ',$rec['dataType']))); ?></td>
                    <td class="px-6 py-4"><?php echo htmlspecialchars($rec['value']); ?></td>
                    <td class="px-6 py-4"><?php echo htmlspecialchars($rec['measurementUnit']); ?></td>
                    <td class="px-6 py-4"><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($rec['recordedDate']))); ?></td>
                    <td class="px-6 py-4"><?php echo htmlspecialchars($rec['notes'] ?: '-'); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
</div>

<script>
// Charts
const labels = <?php echo json_encode($labels, JSON_UNESCAPED_UNICODE); ?>;
const lineDatasets = <?php echo json_encode($lineDatasets, JSON_UNESCAPED_UNICODE); ?>;
const pieLabels = <?php echo json_encode($pieLabels, JSON_UNESCAPED_UNICODE); ?>;
const pieValues = <?php echo json_encode($pieValues, JSON_UNESCAPED_UNICODE); ?>;
const barLabels = <?php echo json_encode($barLabels, JSON_UNESCAPED_UNICODE); ?>;
const barValues = <?php echo json_encode($barValues, JSON_UNESCAPED_UNICODE); ?>;

if(document.getElementById('mainChart')) new Chart(document.getElementById('mainChart').getContext('2d'),{
    type:'line', data:{labels,datasets:lineDatasets}, options:{responsive:true, plugins:{legend:{position:'bottom'}}, scales:{y:{beginAtZero:false}}}
});
if(document.getElementById('distributionPieChart')) new Chart(document.getElementById('distributionPieChart').getContext('2d'),{
    type:'doughnut', data:{labels:pieLabels,datasets:[{data:pieValues,borderWidth:1}]}, options:{plugins:{legend:{position:'bottom'}}, cutout:'55%'}
});
if(document.getElementById('avgBarChart')) new Chart(document.getElementById('avgBarChart').getContext('2d'),{
    type:'bar', data:{labels:barLabels,datasets:[{label:'Average',data:barValues}]}, options:{scales:{y:{beginAtZero:true}},plugins:{legend:{display:false}}}
});
</script>
</body>
</html>
