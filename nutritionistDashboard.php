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

// Fetch dashboard statistics
$stats = [];

// Count active diet plans
$activeQuery = "SELECT COUNT(*) as count FROM dietplan WHERE nutritionistID = ? AND endDate >= CURDATE()";
$stmt = $conn->prepare($activeQuery);
$stmt->bind_param("i", $nutritionistID);
$stmt->execute();
$stats['active_plans'] = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Count completed diet plans
$completedQuery = "SELECT COUNT(*) as count FROM dietplan WHERE nutritionistID = ? AND endDate < CURDATE()";
$stmt = $conn->prepare($completedQuery);
$stmt->bind_param("i", $nutritionistID);
$stmt->execute();
$stats['completed_plans'] = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Count upcoming consultations
$upcomingQuery = "SELECT COUNT(*) as count FROM appointment WHERE providerID = ? AND appointmentDate >= NOW() AND status = 'Scheduled'";
$stmt = $conn->prepare($upcomingQuery);
$stmt->bind_param("i", $nutritionistID);
$stmt->execute();
$stats['upcoming_consultations'] = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Count total transactions
$transactionQuery = "SELECT COUNT(*) as count FROM transaction t JOIN appointment a ON t.appointmentID = a.appointmentID WHERE a.providerID = ?";
$stmt = $conn->prepare($transactionQuery);
$stmt->bind_param("i", $nutritionistID);
$stmt->execute();
$stats['total_transactions'] = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Fetch recent diet plans for quick access
$recentPlansQuery = "
    SELECT dp.planID, dp.dietType, dp.startDate, u.Name as patientName, pt.age, pt.gender
    FROM dietplan dp
    JOIN appointment a ON dp.appointmentID = a.appointmentID
    JOIN users u ON a.patientID = u.userID
    LEFT JOIN patient pt ON u.userID = pt.patientID
    WHERE dp.nutritionistID = ?
    ORDER BY dp.startDate DESC
    LIMIT 5
";
$stmt = $conn->prepare($recentPlansQuery);
$stmt->bind_param("i", $nutritionistID);
$stmt->execute();
$recentPlans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch upcoming appointments for today's agenda
$todayQuery = "
    SELECT a.appointmentID, a.appointmentDate, a.notes, u.Name as patientName, pt.age, pt.gender
    FROM appointment a
    JOIN users u ON a.patientID = u.userID
    LEFT JOIN patient pt ON u.userID = pt.patientID
    WHERE a.providerID = ? AND DATE(a.appointmentDate) = CURDATE() AND a.status = 'Scheduled'
    ORDER BY a.appointmentDate ASC
";
$stmt = $conn->prepare($todayQuery);
$stmt->bind_param("i", $nutritionistID);
$stmt->execute();
$todayAppointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Nutritionist Dashboard - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-dark-orchid { background-color: #9932CC; }
        .text-dark-orchid { color: #9932CC; }
        .shadow-orchid-custom { box-shadow: 0 4px 6px -1px rgba(153, 50, 204, 0.1), 0 2px 4px -2px rgba(153, 50, 204, 0.1); }
        .feature-card {
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }
        .feature-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(153, 50, 204, 0.2);
            border-color: #9932CC;
        }
        .stat-card {
            background: linear-gradient(135deg, #f3e8ff 0%, #e9d5ff 100%);
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(153, 50, 204, 0.15);
        }
    </style>
</head>
<body class="bg-purple-50">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="w-64 bg-white border-r shadow-sm">
            <div class="p-6">
                <a href="#" class="text-2xl font-bold text-dark-orchid">CarePlus</a>
            </div>
            <nav class="px-4 space-y-1">
                <a href="nutritionistDashboard.php" class="flex items-center space-x-3 px-4 py-3 bg-purple-100 text-dark-orchid rounded-lg">
                    <i class="fa-solid fa-table-columns w-5"></i><span>Dashboard</span>
                </a>
                <a href="nutritionist_profile.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg">
                    <i class="fa-regular fa-user w-5"></i><span>My Profile</span>
                </a>
                <a href="nutritionist_schedule.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg">
                    <i class="fa-solid fa-calendar-days w-5"></i><span>Schedule</span>
                </a>
                <a href="nutritionist_consultations.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg">
                    <i class="fa-solid fa-laptop-medical w-5"></i><span>Consultations</span>
                </a>
                <a href="nutritionist_view_dietplans.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg">
                    <i class="fa-solid fa-utensils w-5"></i><span>Diet Plans</span>
                </a>
                <a href="nutritionist_view_history.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg">
                    <i class="fa-solid fa-file-waveform w-5"></i><span>Patient History</span>
                </a>
                <a href="my_transactions.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg">
                    <i class="fa-solid fa-money-bill-wave w-5"></i><span>Transactions</span>
                </a>
                <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg mt-8">
                    <i class="fa-solid fa-arrow-right-from-bracket w-5"></i><span>Logout</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <!-- Header -->
            <header class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-800">Welcome Back, <?php echo htmlspecialchars($userName); ?>!</h1>
                    <p class="text-gray-600 mt-1">Here's your nutrition practice overview for today</p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <p class="text-sm text-gray-500"><?php echo date('l, F j, Y'); ?></p>
                        <p class="text-xs text-gray-400"><?php echo date('g:i A'); ?></p>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-dark-orchid text-white flex items-center justify-center font-bold text-lg">
                        <?php echo htmlspecialchars($userAvatar); ?>
                    </div>
                </div>
            </header>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="stat-card p-6 rounded-2xl shadow-lg flex items-center space-x-4">
                    <div class="p-4 rounded-xl bg-purple-500 text-white">
                        <i class="fa-solid fa-chart-pie fa-2x"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-purple-900"><?php echo $stats['active_plans']; ?></h3>
                        <p class="text-purple-700 font-medium">Active Diet Plans</p>
                    </div>
                </div>

                <div class="stat-card p-6 rounded-2xl shadow-lg flex items-center space-x-4">
                    <div class="p-4 rounded-xl bg-green-500 text-white">
                        <i class="fa-solid fa-check-circle fa-2x"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-purple-900"><?php echo $stats['completed_plans']; ?></h3>
                        <p class="text-purple-700 font-medium">Completed Plans</p>
                    </div>
                </div>

                <div class="stat-card p-6 rounded-2xl shadow-lg flex items-center space-x-4">
                    <div class="p-4 rounded-xl bg-blue-500 text-white">
                        <i class="fa-solid fa-calendar-days fa-2x"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-purple-900"><?php echo $stats['upcoming_consultations']; ?></h3>
                        <p class="text-purple-700 font-medium">Upcoming Consults</p>
                    </div>
                </div>

                <div class="stat-card p-6 rounded-2xl shadow-lg flex items-center space-x-4">
                    <div class="p-4 rounded-xl bg-indigo-500 text-white">
                        <i class="fa-solid fa-money-bill-wave fa-2x"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-purple-900"><?php echo $stats['total_transactions']; ?></h3>
                        <p class="text-purple-700 font-medium">Total Transactions</p>
                    </div>
                </div>
            </div>

            <!-- Main Dashboard Content -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Today's Schedule -->
                <div class="bg-white p-6 rounded-2xl shadow-orchid-custom">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-slate-800">Today's Schedule</h2>
                        <i class="fa-solid fa-calendar-day text-purple-500 text-xl"></i>
                    </div>
                    <?php if (empty($todayAppointments)): ?>
                        <div class="text-center py-8">
                            <i class="fa-solid fa-calendar-xmark text-gray-400 text-4xl mb-4"></i>
                            <p class="text-gray-500">No appointments scheduled for today</p>
                            <a href="nutritionist_schedule.php" class="text-purple-600 hover:text-purple-800 mt-2 inline-block">
                                Set up your availability →
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach($todayAppointments as $appointment): ?>
                                <div class="flex items-center p-4 bg-purple-50 rounded-xl border-l-4 border-purple-500">
                                    <div class="flex-1">
                                        <h4 class="font-semibold text-purple-900">
                                            <?php echo htmlspecialchars($appointment['patientName']); ?>
                                        </h4>
                                        <p class="text-sm text-purple-700">
                                            <?php echo date('g:i A', strtotime($appointment['appointmentDate'])); ?> • 
                                            Age: <?php echo $appointment['age'] ?? 'N/A'; ?> • 
                                            <?php echo $appointment['gender'] ?? 'N/A'; ?>
                                        </p>
                                        <?php if ($appointment['notes']): ?>
                                            <p class="text-xs text-gray-600 mt-1 truncate">
                                                <?php echo htmlspecialchars($appointment['notes']); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <a href="nutritionist_consultations.php" class="text-purple-600 hover:text-purple-800">
                                        <i class="fa-solid fa-arrow-right"></i>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Diet Plans -->
                <div class="bg-white p-6 rounded-2xl shadow-orchid-custom">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-slate-800">Recent Diet Plans</h2>
                        <i class="fa-solid fa-apple-alt text-green-500 text-xl"></i>
                    </div>
                    <?php if (empty($recentPlans)): ?>
                        <div class="text-center py-8">
                            <i class="fa-solid fa-utensils text-gray-400 text-4xl mb-4"></i>
                            <p class="text-gray-500">No diet plans created yet</p>
                            <a href="nutritionist_consultations.php" class="text-purple-600 hover:text-purple-800 mt-2 inline-block">
                                Create your first plan →
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach($recentPlans as $plan): ?>
                                <div class="flex items-center p-4 bg-green-50 rounded-xl border-l-4 border-green-500">
                                    <div class="flex-1">
                                        <h4 class="font-semibold text-green-900">
                                            <?php echo htmlspecialchars($plan['patientName']); ?>
                                        </h4>
                                        <p class="text-sm text-green-700">
                                            <?php echo htmlspecialchars($plan['dietType']); ?> • 
                                            Started: <?php echo date('M j, Y', strtotime($plan['startDate'])); ?>
                                        </p>
                                        <p class="text-xs text-gray-600">
                                            Age: <?php echo $plan['age'] ?? 'N/A'; ?> • <?php echo $plan['gender'] ?? 'N/A'; ?>
                                        </p>
                                    </div>
                                    <a href="nutritionist_view_dietplans.php" class="text-green-600 hover:text-green-800">
                                        <i class="fa-solid fa-arrow-right"></i>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white p-6 rounded-2xl shadow-orchid-custom">
                <h2 class="text-xl font-bold text-slate-800 mb-6">Quick Actions</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <a href="nutritionist_consultations.php" class="feature-card p-6 rounded-xl shadow-sm text-center group">
                        <div class="p-4 mx-auto w-16 h-16 bg-purple-100 rounded-2xl mb-4 flex items-center justify-center group-hover:bg-purple-200 transition-colors">
                            <i class="fa-solid fa-utensils fa-2x text-dark-orchid"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800">Create Diet Plan</h3>
                        <p class="text-sm text-gray-600 mt-2">Design personalized nutrition plans</p>
                    </a>

                    <a href="nutritionist_view_dietplans.php" class="feature-card p-6 rounded-xl shadow-sm text-center group">
                        <div class="p-4 mx-auto w-16 h-16 bg-green-100 rounded-2xl mb-4 flex items-center justify-center group-hover:bg-green-200 transition-colors">
                            <i class="fa-solid fa-chart-line fa-2x text-green-600"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800">View Diet Plans</h3>
                        <p class="text-sm text-gray-600 mt-2">Monitor all created plans</p>
                    </a>

                    <a href="nutritionist_view_history.php" class="feature-card p-6 rounded-xl shadow-sm text-center group">
                        <div class="p-4 mx-auto w-16 h-16 bg-blue-100 rounded-2xl mb-4 flex items-center justify-center group-hover:bg-blue-200 transition-colors">
                            <i class="fa-solid fa-file-medical fa-2x text-blue-600"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800">Patient History</h3>
                        <p class="text-sm text-gray-600 mt-2">Review patient records</p>
                    </a>

                    <a href="nutritionist_schedule.php" class="feature-card p-6 rounded-xl shadow-sm text-center group">
                        <div class="p-4 mx-auto w-16 h-16 bg-indigo-100 rounded-2xl mb-4 flex items-center justify-center group-hover:bg-indigo-200 transition-colors">
                            <i class="fa-solid fa-calendar-days fa-2x text-indigo-600"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800">Set Schedule</h3>
                        <p class="text-sm text-gray-600 mt-2">Manage availability</p>
                    </a>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Add some interactive elements
        document.addEventListener('DOMContentLoaded', function() {
            // Animate statistics on page load
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'all 0.6s ease';
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 100);
                }, index * 150);
            });
        });
    </script>
</body>
</html>
