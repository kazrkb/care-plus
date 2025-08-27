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

// --- Fetch stats for the dashboard cards ---
$totalUsers = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$totalDoctors = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'Doctor'")->fetch_assoc()['count'];
$totalPatients = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'Patient'")->fetch_assoc()['count'];
$totalAppointments = $conn->query("SELECT COUNT(*) as count FROM appointment")->fetch_assoc()['count'];

// --- Fetch data for the User Role Distribution (Pie Chart) ---
$roleDataQuery = $conn->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
$pieChartLabels = [];
$pieChartData = [];
while($row = $roleDataQuery->fetch_assoc()) {
    $pieChartLabels[] = $row['role'];
    $pieChartData[] = $row['count'];
}

// --- (NEW) Fetch data for Appointments in Last 15 Days (Line Chart) ---
$lineChartQuery = $conn->query("
    SELECT DATE(appointmentDate) as date, COUNT(*) as count 
    FROM appointment 
    WHERE appointmentDate >= DATE_SUB(CURDATE(), INTERVAL 15 DAY)
    GROUP BY DATE(appointmentDate) 
    ORDER BY date ASC
");
$lineChartLabels = [];
$lineChartData = [];
while($row = $lineChartQuery->fetch_assoc()) {
    // Format the date for display
    $lineChartLabels[] = date("M d", strtotime($row['date']));
    $lineChartData[] = $row['count'];
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
<body class="bg-purple-50">
     <div class="flex min-h-screen">
        <aside class="w-64 bg-white border-r">
            <div class="p-6">
                <a href="#" class="text-2xl font-bold text-dark-orchid">CarePlus</a>
            </div>
            <nav class="px-4">
                <a href="adminDashboard.php" class="flex items-center space-x-3 px-4 py-3 bg-purple-100 text-dark-orchid rounded-lg"><i class="fa-solid fa-table-columns w-5"></i><span>Dashboard</span></a>
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
                <div class="stat-card bg-white p-6 rounded-lg shadow-lg flex items-center space-x-4">
                    <i class="fa-solid fa-users fa-3x text-blue-500"></i>
                    <div><p class="text-sm text-gray-500">Total Users</p><p class="text-2xl font-bold text-slate-800"><?php echo $totalUsers; ?></p></div>
                </div>
                <div class="stat-card bg-white p-6 rounded-lg shadow-lg flex items-center space-x-4">
                    <i class="fa-solid fa-user-doctor fa-3x text-green-500"></i>
                    <div><p class="text-sm text-gray-500">Total Doctors</p><p class="text-2xl font-bold text-slate-800"><?php echo $totalDoctors; ?></p></div>
                </div>
                 <div class="stat-card bg-white p-6 rounded-lg shadow-lg flex items-center space-x-4">
                    <i class="fa-solid fa-user-injured fa-3x text-yellow-500"></i>
                    <div><p class="text-sm text-gray-500">Total Patients</p><p class="text-2xl font-bold text-slate-800"><?php echo $totalPatients; ?></p></div>
                </div>
                <div class="stat-card bg-white p-6 rounded-lg shadow-lg flex items-center space-x-4">
                    <i class="fa-solid fa-calendar-check fa-3x text-purple-500"></i>
                    <div><p class="text-sm text-gray-500">Total Appointments</p><p class="text-2xl font-bold text-slate-800"><?php echo $totalAppointments; ?></p></div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-5 gap-8">
                <div class="lg:col-span-3 bg-white p-6 rounded-lg shadow-lg">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Appointments in Last 15 Days</h2>
                    <canvas id="appointmentsChart"></canvas>
                </div>

                <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-lg">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">User Role Distribution</h2>
                    <div class="max-w-sm mx-auto"> <canvas id="userRolesChart"></canvas>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // --- (NEW) Line Chart for Appointments ---
            const lineCtx = document.getElementById('appointmentsChart').getContext('2d');
            new Chart(lineCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($lineChartLabels); ?>,
                    datasets: [{
                        label: 'Appointments',
                        data: <?php echo json_encode($lineChartData); ?>,
                        backgroundColor: 'rgba(153, 102, 255, 0.2)',
                        borderColor: 'rgba(153, 102, 255, 1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    scales: { y: { beginAtZero: true } },
                    plugins: { legend: { display: false } }
                }
            });

            // --- Pie Chart for User Roles ---
            const pieCtx = document.getElementById('userRolesChart').getContext('2d');
            new Chart(pieCtx, {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode($pieChartLabels); ?>,
                    datasets: [{
                        label: 'Number of Users',
                        data: <?php echo json_encode($pieChartData); ?>,
                        backgroundColor: [
                            'rgba(54, 162, 235, 0.8)',
                            'rgba(255, 99, 132, 0.8)',
                            'rgba(75, 192, 192, 0.8)',
                            'rgba(255, 206, 86, 0.8)',
                            'rgba(153, 102, 255, 0.8)',
                        ],
                        borderColor: 'rgba(255, 255, 255, 1)',
                        borderWidth: 2
                    }]
                },
                options: { responsive: true, plugins: { legend: { position: 'top' } } }
            });
        });
    </script>
</body>
</html>