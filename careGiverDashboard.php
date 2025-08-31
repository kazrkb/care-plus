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
    // Mark the notification as 'Read' for the logged-in caregiver
    $stmt = $conn->prepare("UPDATE notification SET status = 'Read' WHERE notificationID = ? AND userID = ?");
    $stmt->bind_param("ii", $notificationID, $careGiverID);
    $stmt->execute();
    $stmt->close();
    // Redirect back to the dashboard without the GET parameter
    header("Location: careGiverDashboard.php");
    exit();
}

// --- Fetch all 'Unread' notifications for this caregiver ---
$notifyQuery = "SELECT * FROM notification WHERE userID = ? AND status = 'Unread' ORDER BY notificationID DESC";
$stmt = $conn->prepare($notifyQuery);
$stmt->bind_param("i", $careGiverID);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- Fetch Dashboard Statistics ---
// Check if caregiverbooking table exists
$check_table = $conn->query("SHOW TABLES LIKE 'caregiverbooking'");
$table_exists = $check_table->num_rows > 0;

$totalPatients = 0;
$monthlyBookings = 0;
$statusData = [];
$monthlyTrend = [];
$recentBookings = [];

if ($table_exists) {
    try {
        // Total patients assigned to this caregiver
        $totalPatientsQuery = "SELECT COUNT(DISTINCT patientID) as total FROM caregiverbooking WHERE careGiverID = ?";
        $stmt = $conn->prepare($totalPatientsQuery);
        $stmt->bind_param("i", $careGiverID);
        $stmt->execute();
        $totalPatients = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
        $stmt->close();

        // Total bookings this month
        $currentMonth = date('Y-m');
        $monthlyBookingsQuery = "SELECT COUNT(*) as total FROM caregiverbooking WHERE careGiverID = ? AND DATE_FORMAT(startDate, '%Y-%m') = ?";
        $stmt = $conn->prepare($monthlyBookingsQuery);
        $stmt->bind_param("is", $careGiverID, $currentMonth);
        $stmt->execute();
        $monthlyBookings = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
        $stmt->close();

        // Booking status distribution
        $statusQuery = "SELECT status, COUNT(*) as count FROM caregiverbooking WHERE careGiverID = ? GROUP BY status";
        $stmt = $conn->prepare($statusQuery);
        $stmt->bind_param("i", $careGiverID);
        $stmt->execute();
        $statusData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Bookings by month (last 6 months)
        $monthlyTrendQuery = "
            SELECT DATE_FORMAT(startDate, '%Y-%m') as month, COUNT(*) as count 
            FROM caregiverbooking 
            WHERE careGiverID = ? AND startDate >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(startDate, '%Y-%m')
            ORDER BY month
        ";
        $stmt = $conn->prepare($monthlyTrendQuery);
        $stmt->bind_param("i", $careGiverID);
        $stmt->execute();
        $monthlyTrend = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Recent bookings
        $recentBookingsQuery = "
            SELECT cb.*, u.Name as patientName 
            FROM caregiverbooking cb 
            JOIN users u ON cb.patientID = u.userID 
            WHERE cb.careGiverID = ? 
            ORDER BY cb.startDate DESC, cb.bookingID DESC 
            LIMIT 5
        ";
        $stmt = $conn->prepare($recentBookingsQuery);
        $stmt->bind_param("i", $careGiverID);
        $stmt->execute();
        $recentBookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        // Handle database errors gracefully
        error_log("Dashboard error: " . $e->getMessage());
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CareGiver Dashboard - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
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
                <a href="caregiver_careplan.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-clipboard-list w-5"></i><span>Care Plans</span></a>
                <a href="caregiver_availability.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-calendar-days w-5"></i><span>Provide Availability</span></a>
                <a href="my_bookings.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-calendar-check w-5"></i><span>My Bookings</span></a>
                <a href="caregiver_view_patients.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-book-medical w-5"></i><span>View Patient Details</span></a>
                <a href="#" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-chart-line w-5"></i><span>Progress Analytics</span></a>
                     <a href="my_transactions.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-money-bill-wave w-5"></i><span>Transactions</span></a>
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
                        <a href="careGiverDashboard.php?dismiss_notification_id=<?php echo $notification['notificationID']; ?>" class="text-sm font-semibold hover:underline" title="Mark as Read">
                            Dismiss
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-6 rounded-lg shadow-lg text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm font-medium">Total Patients</p>
                            <p class="text-3xl font-bold"><?php echo $totalPatients; ?></p>
                        </div>
                        <div class="bg-blue-400 bg-opacity-30 p-3 rounded-full">
                            <i class="fa-solid fa-users text-2xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gradient-to-r from-green-500 to-green-600 p-6 rounded-lg shadow-lg text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100 text-sm font-medium">This Month's Bookings</p>
                            <p class="text-3xl font-bold"><?php echo $monthlyBookings; ?></p>
                        </div>
                        <div class="bg-green-400 bg-opacity-30 p-3 rounded-full">
                            <i class="fa-solid fa-calendar-check text-2xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gradient-to-r from-purple-500 to-purple-600 p-6 rounded-lg shadow-lg text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100 text-sm font-medium">Active Care Plans</p>
                            <p class="text-3xl font-bold"><?php echo count($recentBookings); ?></p>
                        </div>
                        <div class="bg-purple-400 bg-opacity-30 p-3 rounded-full">
                            <i class="fa-solid fa-clipboard-list text-2xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gradient-to-r from-orange-500 to-orange-600 p-6 rounded-lg shadow-lg text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-orange-100 text-sm font-medium">Notifications</p>
                            <p class="text-3xl font-bold"><?php echo count($notifications); ?></p>
                        </div>
                        <div class="bg-orange-400 bg-opacity-30 p-3 rounded-full">
                            <i class="fa-solid fa-bell text-2xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Booking Status Chart -->
                <div class="bg-white p-6 rounded-lg shadow-lg">
                    <h3 class="text-lg font-semibold text-slate-800 mb-4">
                        <i class="fa-solid fa-chart-pie mr-2 text-dark-orchid"></i>Booking Status Distribution
                    </h3>
                    <div class="relative h-64">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
                
                <!-- Monthly Trend Chart -->
                <div class="bg-white p-6 rounded-lg shadow-lg">
                    <h3 class="text-lg font-semibold text-slate-800 mb-4">
                        <i class="fa-solid fa-chart-line mr-2 text-dark-orchid"></i>Monthly Booking Trend
                    </h3>
                    <div class="relative h-64">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
                <h3 class="text-lg font-semibold text-slate-800 mb-4">
                    <i class="fa-solid fa-clock mr-2 text-dark-orchid"></i>Recent Bookings
                </h3>
                <?php if (empty($recentBookings)): ?>
                <div class="text-center py-8">
                    <i class="fa-solid fa-calendar-xmark text-gray-400 text-4xl mb-4"></i>
                    <p class="text-gray-500">No recent bookings found.</p>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b">
                                <th class="text-left py-3 px-4 font-semibold text-gray-700">Patient</th>
                                <th class="text-left py-3 px-4 font-semibold text-gray-700">Start Date</th>
                                <th class="text-left py-3 px-4 font-semibold text-gray-700">End Date</th>
                                <th class="text-left py-3 px-4 font-semibold text-gray-700">Type</th>
                                <th class="text-left py-3 px-4 font-semibold text-gray-700">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentBookings as $booking): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-3 px-4 font-medium"><?php echo htmlspecialchars($booking['patientName']); ?></td>
                                <td class="py-3 px-4"><?php echo date('M d, Y', strtotime($booking['startDate'])); ?></td>
                                <td class="py-3 px-4"><?php echo date('M d, Y', strtotime($booking['endDate'])); ?></td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        <?php echo htmlspecialchars($booking['bookingType']); ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                        <?php 
                                        switch($booking['status']) {
                                            case 'Active': echo 'bg-green-100 text-green-800'; break;
                                            case 'Scheduled': echo 'bg-yellow-100 text-yellow-800'; break;
                                            case 'Completed': echo 'bg-blue-100 text-blue-800'; break;
                                            case 'Canceled': echo 'bg-red-100 text-red-800'; break;
                                            default: echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                        <?php echo htmlspecialchars($booking['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- Quick Access Cards -->
            <h3 class="text-xl font-semibold text-slate-800 mb-4">Quick Actions</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
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
        </main>
    </div>

    <script>
        // Booking Status Pie Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusData = <?php echo json_encode($statusData); ?>;
        
        const statusLabels = statusData.map(item => item.status || 'Unknown');
        const statusCounts = statusData.map(item => parseInt(item.count));
        const statusColors = ['#10B981', '#F59E0B', '#3B82F6', '#EF4444', '#8B5CF6'];
        
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusCounts,
                    backgroundColor: statusColors.slice(0, statusLabels.length),
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    }
                }
            }
        });

        // Monthly Trend Line Chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        const trendData = <?php echo json_encode($monthlyTrend); ?>;
        
        // Generate last 6 months
        const months = [];
        const counts = [];
        const now = new Date();
        
        for (let i = 5; i >= 0; i--) {
            const date = new Date(now.getFullYear(), now.getMonth() - i, 1);
            const monthStr = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0');
            const monthName = date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
            
            months.push(monthName);
            
            const found = trendData.find(item => item.month === monthStr);
            counts.push(found ? parseInt(found.count) : 0);
        }
        
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'Bookings',
                    data: counts,
                    borderColor: '#9932CC',
                    backgroundColor: 'rgba(153, 50, 204, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#9932CC',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>