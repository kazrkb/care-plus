<?php
session_start();
$conn = require_once 'config.php';
date_default_timezone_set('Asia/Dhaka');

// Protect this page: ensure the user is a logged-in Admin
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$userName = $_SESSION['Name'];
$userAvatar = strtoupper(substr($userName, 0, 2));

// --- Handle Filters ---
$filter_user = $_GET['user_name'] ?? '';
$filter_start_date = $_GET['start_date'] ?? '';
$filter_end_date = $_GET['end_date'] ?? '';
$filter_type = $_GET['type'] ?? '';

// --- Fetch Summary Stats ---
$totalRevenue = $conn->query("SELECT SUM(amount) as total FROM transaction")->fetch_assoc()['total'] ?? 0;
$todayRevenue = $conn->query("SELECT SUM(amount) as total FROM transaction WHERE DATE(timestamp) = CURDATE()")->fetch_assoc()['total'] ?? 0;
$totalTransactions = $conn->query("SELECT COUNT(*) as count FROM transaction")->fetch_assoc()['count'];
$platformRevenueQuery = "SELECT SUM(amount) as total FROM transaction WHERE transactionType != 'Registration Fee'";
$billableRevenue = $conn->query($platformRevenueQuery)->fetch_assoc()['total'] ?? 0;
$platformEarnings = $billableRevenue * 0.30;


// --- Build Dynamic Query to Fetch Transactions with User Role ---
$query = "
    SELECT
        t.transactionID, t.amount, t.transactionType, t.status, t.timestamp, t.gatewayTransactionID,
        COALESCE(u_reg.Name, u_appt.Name, u_cg.Name) as userName,
        COALESCE(u_reg.role, u_appt.role, u_cg.role) as userRole
    FROM transaction t
    LEFT JOIN users u_reg ON t.userID = u_reg.userID
    LEFT JOIN appointment a ON t.appointmentID = a.appointmentID
    LEFT JOIN users u_appt ON a.patientID = u_appt.userID
    LEFT JOIN caregiverbooking cb ON t.careProviderBookingID = cb.bookingID
    LEFT JOIN users u_cg ON cb.patientID = u_cg.userID
";

$whereClauses = [];
$params = [];
$types = "";

if (!empty($filter_start_date)) {
    $whereClauses[] = "DATE(t.timestamp) >= ?";
    $params[] = $filter_start_date;
    $types .= "s";
}
if (!empty($filter_end_date)) {
    $whereClauses[] = "DATE(t.timestamp) <= ?";
    $params[] = $filter_end_date;
    $types .= "s";
}
if (!empty($filter_type)) {
    $whereClauses[] = "t.transactionType = ?";
    $params[] = $filter_type;
    $types .= "s";
}

if (!empty($whereClauses)) {
    $query .= " WHERE " . implode(" AND ", $whereClauses);
}

if (!empty($filter_user)) {
    $query .= " HAVING userName LIKE ?";
    $params[] = "%" . $filter_user . "%";
    $types .= "s";
}

$query .= " ORDER BY t.timestamp DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$allTransactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// --- Separate transactions into role-based arrays for the tabs ---
$transactionsByRole = [
    'Doctor' => [], 'Patient' => [], 'Nutritionist' => [], 'CareGiver' => []
];
foreach ($allTransactions as $trans) {
    if (isset($transactionsByRole[$trans['userRole']])) {
        $transactionsByRole[$trans['userRole']][] = $trans;
    }
}

$conn->close();

// Helper function to render the table for each tab
function render_transaction_table($transactions) {
    if (empty($transactions)) {
        return '<p class="text-center py-10 text-gray-500">No transactions found matching your criteria.</p>';
    }
    $html = '<div class="overflow-x-auto"><table class="w-full text-sm text-left"><thead class="text-xs text-gray-700 uppercase bg-gray-50"><tr><th class="px-6 py-3">Transaction ID</th><th class="px-6 py-3">User Name</th><th class="px-6 py-3">Amount</th><th class="px-6 py-3">Type</th><th class="px-6 py-3">Status</th><th class="px-6 py-3">Date</th></tr></thead><tbody>';
    foreach ($transactions as $trans) {
        $html .= '<tr class="border-b hover:bg-gray-50">';
        $html .= '<td class="px-6 py-4 font-mono text-xs text-gray-500">#'.htmlspecialchars($trans['gatewayTransactionID'] ?? $trans['transactionID']).'</td>';
        $html .= '<td class="px-6 py-4 font-semibold">'.htmlspecialchars($trans['userName'] ?? 'N/A').'</td>';
        $html .= '<td class="px-6 py-4">৳'.htmlspecialchars(number_format($trans['amount'], 2)).'</td>';
        $html .= '<td class="px-6 py-4">'.htmlspecialchars($trans['transactionType']).'</td>';
        $html .= '<td class="px-6 py-4"><span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">'.htmlspecialchars($trans['status']).'</span></td>';
        $html .= '<td class="px-6 py-4">'.date("M d, Y h:i A", strtotime($trans['timestamp'])).'</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table></div>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transaction Management - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-dark-orchid { background-color: #9932CC; }
        .text-dark-orchid { color: #9932CC; }
        .tab-button { border-bottom: 3px solid transparent; }
        .tab-button.active { border-bottom-color: #9932CC; color: #9932CC; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex min-h-screen">
        <aside class="w-64 bg-white border-r">
            <div class="p-6"><a href="adminDashboard.php" class="text-2xl font-bold text-dark-orchid">CarePlus</a></div>
            <nav class="px-4">
                <a href="adminDashboard.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-table-columns w-5"></i><span>Dashboard</span></a>
                <a href="admin_verification.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-user-check w-5"></i><span>Verifications</span></a>
                <a href="user_management.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-users-cog w-5"></i><span>User Management</span></a>
                <a href="admin_transactions.php" class="flex items-center space-x-3 px-4 py-3 bg-purple-100 text-dark-orchid rounded-lg"><i class="fa-solid fa-money-bill-wave w-5"></i><span>Transactions</span></a>
                <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg mt-8"><i class="fa-solid fa-arrow-right-from-bracket w-5"></i><span>Logout</span></a>
            </nav>
        </aside>

        <main class="flex-1 p-8">
            <header class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-800">Transaction Management</h1>
                    <p class="text-gray-600 mt-1">View and filter all payments on the platform.</p>
                </div>
            </header>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white p-6 rounded-lg shadow-md"><p class="text-sm text-gray-500">Total Revenue</p><p class="text-2xl font-bold text-slate-800">৳<?php echo number_format($totalRevenue, 2); ?></p></div>
                <div class="bg-white p-6 rounded-lg shadow-md"><p class="text-sm text-gray-500">Platform Earnings (30%)</p><p class="text-2xl font-bold text-green-600">৳<?php echo number_format($platformEarnings, 2); ?></p></div>
                <div class="bg-white p-6 rounded-lg shadow-md"><p class="text-sm text-gray-500">Revenue (Today)</p><p class="text-2xl font-bold text-slate-800">৳<?php echo number_format($todayRevenue, 2); ?></p></div>
                <div class="bg-white p-6 rounded-lg shadow-md"><p class="text-sm text-gray-500">Total Transactions</p><p class="text-2xl font-bold text-slate-800"><?php echo $totalTransactions; ?></p></div>
            </div>
            
            <div class="bg-white p-4 rounded-lg shadow-md mb-6">
                 <form method="GET" class="flex flex-wrap items-end gap-4">
                    <div><label class="text-sm font-medium">User Name</label><input type="text" name="user_name" value="<?php echo htmlspecialchars($filter_user); ?>" placeholder="Search by name..." class="w-full mt-1 p-2 border rounded-md"></div>
                    <div><label class="text-sm font-medium">Start Date</label><input type="date" name="start_date" value="<?php echo htmlspecialchars($filter_start_date); ?>" class="w-full mt-1 p-2 border rounded-md"></div>
                    <div><label class="text-sm font-medium">End Date</label><input type="date" name="end_date" value="<?php echo htmlspecialchars($filter_end_date); ?>" class="w-full mt-1 p-2 border rounded-md"></div>
                    <div>
                        <label class="text-sm font-medium">Type</label>
                        <select name="type" class="w-full mt-1 p-2 border rounded-md bg-white">
                            <option value="">All Types</option>
                            <option value="Registration Fee" <?php if($filter_type === 'Registration Fee') echo 'selected'; ?>>Registration Fee</option>
                            <option value="Appointment Fee" <?php if($filter_type === 'Appointment Fee') echo 'selected'; ?>>Appointment Fee</option>
                        </select>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-dark-orchid text-white rounded-md font-semibold">Filter</button>
                    <a href="admin_transactions.php" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md">Clear</a>
                </form>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-lg">
                <div class="border-b border-gray-200">
                    <nav class="flex space-x-8 -mb-px" aria-label="Tabs">
                        <button onclick="openTab(event, 'all')" class="tab-button active py-4 px-1 font-medium text-sm">All Transactions</button>
                        <button onclick="openTab(event, 'doctors')" class="tab-button py-4 px-1 font-medium text-sm">Doctors</button>
                        <button onclick="openTab(event, 'nutritionists')" class="tab-button py-4 px-1 font-medium text-sm">Nutritionists</button>
                        <button onclick="openTab(event, 'patients')" class="tab-button py-4 px-1 font-medium text-sm">Patients</button>
                        <button onclick="openTab(event, 'caregivers')" class="tab-button py-4 px-1 font-medium text-sm">Caregivers</button>
                    </nav>
                </div>
                
                <div id="all-panel" class="tab-panel mt-4"><?php echo render_transaction_table($allTransactions); ?></div>
                <div id="doctors-panel" class="tab-panel mt-4 hidden"><?php echo render_transaction_table($transactionsByRole['Doctor']); ?></div>
                <div id="nutritionists-panel" class="tab-panel mt-4 hidden"><?php echo render_transaction_table($transactionsByRole['Nutritionist']); ?></div>
                <div id="patients-panel" class="tab-panel mt-4 hidden"><?php echo render_transaction_table($transactionsByRole['Patient']); ?></div>
                <div id="caregivers-panel" class="tab-panel mt-4 hidden"><?php echo render_transaction_table($transactionsByRole['CareGiver']); ?></div>
            </div>
        </main>
    </div>
    <script>
        function openTab(evt, tabName) {
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('hidden'));
            document.querySelectorAll('.tab-button').forEach(b => {
                b.classList.remove('active', 'text-purple-600', 'border-purple-500');
                b.classList.add('text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
            });
            document.getElementById(tabName + '-panel').classList.remove('hidden');
            evt.currentTarget.classList.add('active', 'text-purple-600', 'border-purple-500');
            evt.currentTarget.classList.remove('text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
        }
        // Set the default tab to be active on page load
        document.querySelector('.tab-button').click();
    </script>
</body>
</html>