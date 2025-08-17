<?php
session_start();

// Protect this page: allow only logged-in CareGivers
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'CareGiver') {
    header("Location: login.php");
    exit();
}

$userName = $_SESSION['Name'];
$userAvatar = strtoupper(substr($userName, 0, 2));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CareGiver Dashboard - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
        <!-- Sidebar -->
        <aside class="w-64 bg-white border-r">
            <div class="p-6">
                <a href="#" class="text-2xl font-bold text-dark-orchid">CarePlus</a>
            </div>
            <nav class="px-4">
                <a href="careGiverDashboard.php" class="flex items-center space-x-3 px-4 py-3 bg-purple-100 text-dark-orchid rounded-lg"><i class="fa-solid fa-table-columns w-5"></i><span>Dashboard</span></a>
                <a href="#" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-regular fa-user w-5"></i><span>My Profile</span></a>
                <a href="#" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-calendar-days w-5"></i><span>Provide Schedule</span></a>
                <a href="#" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-book-medical w-5"></i><span>View Patient Details</span></a>
                <a href="#" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-notes-medical w-5"></i><span>Care Plan</span></a>
                <a href="#" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-chart-line w-5"></i><span>Progress Analytics</span></a>
                <a href="#" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-money-bill-wave w-5"></i><span>Transactions</span></a>
                <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg mt-8"><i class="fa-solid fa-arrow-right-from-bracket w-5"></i><span>Logout</span></a>
            </nav>
        </aside>
        <!-- Main Content -->
        <main class="flex-1 p-8">
            <header class="flex justify-between items-center mb-8">
                <h1 class="text-3xl font-bold text-slate-800">CareGiver Dashboard</h1>
                <div class="flex items-center space-x-2">
                    <div class="w-10 h-10 rounded-full bg-dark-orchid text-white flex items-center justify-center font-bold"><?php echo htmlspecialchars($userAvatar); ?></div>
                    <p class="font-semibold text-slate-700"><?php echo htmlspecialchars($userName); ?></p>
                </div>
            </header>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <a href="#" class="feature-card bg-white p-6 rounded-lg shadow-orchid-custom text-center">
                    <i class="fa-solid fa-book-medical fa-2x text-dark-orchid mb-3"></i>
                    <h3 class="text-lg font-semibold">View Patient Details</h3>
                </a>
                <a href="#" class="feature-card bg-white p-6 rounded-lg shadow-orchid-custom text-center">
                    <i class="fa-solid fa-notes-medical fa-2x text-dark-orchid mb-3"></i>
                    <h3 class="text-lg font-semibold">Update Care Plan</h3>
                </a>
                <a href="#" class="feature-card bg-white p-6 rounded-lg shadow-orchid-custom text-center">
                    <i class="fa-solid fa-chart-line fa-2x text-dark-orchid mb-3"></i>
                    <h3 class="text-lg font-semibold">Log Progress</h3>
                </a>
            </div>
        </main>
    </div>
</body>
</html>
