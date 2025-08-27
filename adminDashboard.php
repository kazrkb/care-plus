<?php
session_start();
$conn = require_once 'config.php';
date_default_timezone_set('Asia/Dhaka');

// Protect this page: allow only logged-in Admins
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$userName = $_SESSION['Name'];
$userAvatar = strtoupper(substr($userName, 0, 2));

// --- Handle Year Filter ---
$selectedYear = isset($_GET['filter_year']) && is_numeric($_GET['filter_year']) ? (int)$_GET['filter_year'] : date('Y');

// --- Fetch stats for the main cards ---
$totalUsers = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$pendingVerifications = $conn->query("SELECT COUNT(*) as count FROM users WHERE verification_status = 'Pending'")->fetch_assoc()['count'];
$totalAppointments = $conn->query("SELECT COUNT(*) as count FROM appointment")->fetch_assoc()['count'];
$revenueThisMonth = $conn->query("SELECT SUM(amount) as total FROM transaction WHERE MONTH(timestamp) = MONTH(CURDATE()) AND YEAR(timestamp) = YEAR(CURDATE())")->fetch_assoc()['total'] ?? 0;

// --- 1. Appointments in Last 15 Days (Line Chart) ---
$dailyAppointmentsQuery = $conn->query("
    SELECT DATE(appointmentDate) as date, COUNT(*) as count 
    FROM appointment 
    WHERE appointmentDate >= DATE_SUB(CURDATE(), INTERVAL 15 DAY)
    GROUP BY DATE(appointmentDate) ORDER BY date ASC
");
$dailyApptLabels = [];
$dailyAppData = [];
$dateRange = new DatePeriod(new DateTime('-15 days'), new DateInterval('P1D'), new DateTime('+1 day'));
$appointmentsByDate = [];
foreach ($dateRange as $date) {
    $appointmentsByDate[$date->format('Y-m-d')] = 0;
}
while($row = $dailyAppointmentsQuery->fetch_assoc()) {
    $appointmentsByDate[$row['date']] = $row['count'];
}
foreach ($appointmentsByDate as $date => $count) {
    $dailyApptLabels[] = date("M d", strtotime($date));
    $dailyAppData[] = $count;
}

// --- 2. Monthly Revenue (Line Chart) ---
$monthlyLabels = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
$revenueChartData = array_fill(0, 12, 0);
$stmt = $conn->prepare("
    SELECT MONTH(timestamp) as month, SUM(amount) as total
    FROM transaction WHERE YEAR(timestamp) = ?
    GROUP BY month ORDER BY month ASC
");
$stmt->bind_param("i", $selectedYear);
$stmt->execute();
$revenueResult = $stmt->get_result();
while($row = $revenueResult->fetch_assoc()) {
    $revenueChartData[$row['month'] - 1] = $row['total'];
}
$stmt->close();

// --- 3. Monthly New Users (Bar Chart) ---
$userChartData = array_fill(0, 12, 0);
$stmt = $conn->prepare("
    SELECT MONTH(registrationDate) as month, COUNT(userID) as total
    FROM users WHERE YEAR(registrationDate) = ?
    GROUP BY month ORDER BY month ASC
");
$stmt->bind_param("i", $selectedYear);
$stmt->execute();
$userResult = $stmt->get_result();
while($row = $userResult->fetch_assoc()) {
    $userChartData[$row['month'] - 1] = $row['total'];
}
$stmt->close();

// --- 4. User Role Distribution (Pie Chart) ---
$roleDataQuery = $conn->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
$pieChartLabels = [];
$pieChartData = [];
while($row = $roleDataQuery->fetch_assoc()) {
    $pieChartLabels[] = $row['role'];
    $pieChartData[] = $row['count'];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-dark-orchid { background-color: #9932CC; }
        .text-dark-orchid { color: #9932CC; }
        .stat-card { transition: transform 0.2s, box-shadow 0.2s; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="bg-gray-100">
     <div class="flex min-h-screen">
        <aside class="w-64 bg-white border-r">
            <div class="p-6">
                <a href="#" class="text-2xl font-bold text-dark-orchid">CarePlus</a>
            </div>
            <nav class="px-4">
                <a href="adminDashboard.php" class="flex items-center space-x-3 px-4 py-3 bg-purple-100 text-dark-orchid rounded-lg"><i class="fa-solid fa-table-columns w-5"></i><span>Dashboard</span></a>
                <a href="admin_verification.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-user-check w-5"></i><span>Verifications</span></a>
                <a href="user_management.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-users-cog w-5"></i><span>User Management</span></a>
                <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg mt-8"><i class="fa-solid fa-arrow-right-from-bracket w-5"></i><span>Logout</span></a>
            </nav>
        </aside>
        <main class="flex-1 p-8">
            <header class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-800">Admin Dashboard</h1>
                    <p class="text-gray-600 mt-1">Overview of the CarePlus platform.</p>
                </div>
                 <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <p class="font-semibold text-slate-700"><?php echo htmlspecialchars($userName); ?></p>
                        <p class="text-sm text-gray-500">Admin</p>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-dark-orchid text-white flex items-center justify-center font-bold text-lg"><?php echo htmlspecialchars($userAvatar); ?></div>
                </div>
            </header>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="stat-card bg-white p-6 rounded-lg shadow-md flex items-center space-x-4"><i class="fa-solid fa-users fa-3x text-blue-500"></i><div><p class="text-sm text-gray-500">Total Users</p><p class="text-2xl font-bold text-slate-800"><?php echo $totalUsers; ?></p></div></div>
                <a href="admin_verification.php" class="stat-card bg-white p-6 rounded-lg shadow-md flex items-center space-x-4 border-2 border-transparent hover:border-yellow-500"><i class="fa-solid fa-user-clock fa-3x text-yellow-500"></i><div><p class="text-sm text-gray-500">Pending Verifications</p><p class="text-2xl font-bold text-slate-800"><?php echo $pendingVerifications; ?></p></div></a>
                <div class="stat-card bg-white p-6 rounded-lg shadow-md flex items-center space-x-4"><i class="fa-solid fa-calendar-check fa-3x text-purple-500"></i><div><p class="text-sm text-gray-500">Total Appointments</p><p class="text-2xl font-bold text-slate-800"><?php echo $totalAppointments; ?></p></div></div>
                <div class="stat-card bg-white p-6 rounded-lg shadow-md flex items-center space-x-4"><i class="fa-solid fa-money-bill-trend-up fa-3x text-teal-500"></i><div><p class="text-sm text-gray-500">Revenue (Month)</p><p class="text-2xl font-bold text-slate-800">৳<?php echo number_format($revenueThisMonth, 2); ?></p></div></div>
            </div>

            <div class="mb-6 bg-white p-4 rounded-lg shadow-md">
                <form method="GET" action="adminDashboard.php" class="flex items-center space-x-4">
                    <div>
                        <label for="filter_year" class="text-sm font-medium text-gray-700">View Stats for Year:</label>
                        <select name="filter_year" id="filter_year" class="mt-1 p-2 border rounded-md">
                            <?php
                                $startYear = 2024;
                                $currentYear = date('Y');
                                for ($year = $currentYear; $year >= $startYear; $year--) {
                                    $selected = ($year == $selectedYear) ? 'selected' : '';
                                    echo "<option value='{$year}' {$selected}>{$year}</option>";
                                }
                            ?>
                        </select>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-dark-orchid text-white rounded-md font-semibold hover:bg-purple-700">Filter</button>
                    <a href="adminDashboard.php" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Clear</a>
                </form>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Monthly Revenue (<?php echo $selectedYear; ?>)</h2>
                    <canvas id="revenueChart"></canvas>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">User Role Distribution</h2>
                    <div class="mx-auto" style="max-width: 300px;"><canvas id="userRolesChart"></canvas></div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">New User Registrations (<?php echo $selectedYear; ?>)</h2>
                    <canvas id="usersChart"></canvas>
                </div>
                 <div class="bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Appointments in Last 15 Days</h2>
                    <canvas id="appointmentsChart"></canvas>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // 1. Revenue Chart (Monthly Line)
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($monthlyLabels); ?>,
                    datasets: [{
                        label: 'Revenue (BDT)',
                        data: <?php echo json_encode($revenueChartData); ?>,
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 2, fill: true, tension: 0.4
                    }]
                },
                options: { scales: { y: { beginAtZero: true } }, plugins: { legend: { display: false } } }
            });

            // 2. Pie Chart for User Roles
            const pieCtx = document.getElementById('userRolesChart').getContext('2d');
            new Chart(pieCtx, {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode($pieChartLabels); ?>,
                    datasets: [{
                        label: 'User Roles',
                        data: <?php echo json_encode($pieChartData); ?>,
                        backgroundColor: ['rgba(255, 99, 132, 0.8)','rgba(54, 162, 235, 0.8)','rgba(255, 206, 86, 0.8)','rgba(75, 192, 192, 0.8)','rgba(153, 102, 255, 0.8)'],
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: { responsive: true, plugins: { legend: { position: 'top', labels:{padding: 10} } } }
            });

            // 3. New Users Chart (Monthly Bar)
            const usersCtx = document.getElementById('usersChart').getContext('2d');
            new Chart(usersCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($monthlyLabels); ?>,
                    datasets: [{
                        label: 'New Users',
                        data: <?php echo json_encode($userChartData); ?>,
                        backgroundColor: 'rgba(54, 162, 235, 0.6)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: { scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }, plugins: { legend: { display: false } } }
            });
            
            // 4. Appointments Chart (15-Day Line)
            const appointmentsCtx = document.getElementById('appointmentsChart').getContext('2d');
            new Chart(appointmentsCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($dailyApptLabels); ?>,
                    datasets: [{
                        label: 'Appointments',
                        data: <?php echo json_encode($dailyAppData); ?>,
                        backgroundColor: 'rgba(153, 102, 255, 0.2)',
                        borderColor: 'rgba(153, 102, 255, 1)',
                        borderWidth: 2, fill: true, tension: 0.4
                    }]
                },
                options: { scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }, plugins: { legend: { display: false } } }
            });
        });
    </script>
</body>
</html>