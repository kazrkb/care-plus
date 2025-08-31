<?php
session_start();
$conn = require_once 'config.php';
date_default_timezone_set('Asia/Dhaka');

// Protect this page: ensure a user is logged in
if (!isset($_SESSION['userID'])) {
    header("Location: login.php");
    exit();
}

$userID = $_SESSION['userID'];
$userRole = $_SESSION['role'];
$userName = $_SESSION['Name'];
$userAvatar = strtoupper(substr($userName, 0, 2));

// --- Handle Filters ---
$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$filter_start_date = $_GET['start_date'] ?? '';
$filter_end_date = $_GET['end_date'] ?? '';

// --- Build the correct query based on the user's role ---
$query = "";
$params = [];
$types = "";

switch ($userRole) {
    case 'Patient':
        $query = "
            (SELECT t.transactionID, t.timestamp, t.amount, t.transactionType, 'Paid for Registration' as description, t.status FROM transaction t WHERE t.userID = ?)
            UNION ALL
            (SELECT t.transactionID, t.timestamp, t.amount, t.transactionType, CONCAT('Payment to ', prov.Name) as description, t.status FROM transaction t JOIN appointment a ON t.appointmentID = a.appointmentID JOIN users prov ON a.providerID = prov.userID WHERE a.patientID = ?)
            UNION ALL
            (SELECT t.transactionID, t.timestamp, t.amount, t.transactionType, CONCAT('Payment to ', prov.Name) as description, t.status FROM transaction t JOIN caregiverbooking cb ON t.careProviderBookingID = cb.bookingID JOIN users prov ON cb.careGiverID = prov.userID WHERE cb.patientID = ?)
            ORDER BY timestamp DESC
        ";
        $params = [$userID, $userID, $userID];
        $types = "iii";
        break;

    case 'Doctor':
    case 'Nutritionist':
        $query = "
            (SELECT t.transactionID, t.timestamp, t.amount, t.transactionType, 'Registration Fee Paid' as description, t.status FROM transaction t WHERE t.userID = ? AND MONTH(t.timestamp) = ? AND YEAR(t.timestamp) = ?)
            UNION ALL
            (SELECT t.transactionID, t.timestamp, t.amount, t.transactionType, CONCAT('Payment from ', pat.Name) as description, t.status FROM transaction t JOIN appointment a ON t.appointmentID = a.appointmentID JOIN users pat ON a.patientID = pat.userID WHERE a.providerID = ? AND MONTH(t.timestamp) = ? AND YEAR(t.timestamp) = ?)
            ORDER BY timestamp DESC
        ";
        $params = [$userID, $selectedMonth, $selectedYear, $userID, $selectedMonth, $selectedYear];
        $types = "iiiiii";
        break;
    
    case 'CareGiver':
        // --- FIXED: Corrected the JOIN condition for CareGivers ---
        // `transaction.careProviderBookingID` now correctly joins with `caregiverbooking.bookingID`.
        $query = "
            (SELECT t.transactionID, t.timestamp, t.amount, t.transactionType, 'Registration Fee Paid' as description, t.status FROM transaction t WHERE t.userID = ? AND MONTH(t.timestamp) = ? AND YEAR(t.timestamp) = ?)
            UNION ALL
            (SELECT t.transactionID, t.timestamp, t.amount, t.transactionType, CONCAT('Payment from ', pat.Name) as description, t.status FROM transaction t 
             JOIN caregiverbooking cb ON t.careProviderBookingID = cb.bookingID 
             JOIN users pat ON cb.patientID = pat.userID 
             WHERE cb.careGiverID = ? AND MONTH(t.timestamp) = ? AND YEAR(t.timestamp) = ?)
            ORDER BY timestamp DESC
        ";
        $params = [$userID, $selectedMonth, $selectedYear, $userID, $selectedMonth, $selectedYear];
        $types = "iiiiii";
        break;
}

$transactions = [];
if ($query) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Apply date range filter for Patients using PHP
if ($userRole === 'Patient' && !empty($filter_start_date) && !empty($filter_end_date)) {
    $transactions = array_filter($transactions, function($trans) use ($filter_start_date, $filter_end_date) {
        $transDate = date('Y-m-d', strtotime($trans['timestamp']));
        return $transDate >= $filter_start_date && $transDate <= $filter_end_date;
    });
}


// Calculate income for providers
$grossIncome = 0;
if ($userRole !== 'Patient') {
    foreach ($transactions as $trans) {
        if ($trans['transactionType'] !== 'Registration Fee') {
            $grossIncome += $trans['amount'];
        }
    }
}
$platformCommission = $grossIncome * 0.30;
$netIncome = $grossIncome - $platformCommission;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Transactions - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style> body { font-family: 'Inter', sans-serif; } .bg-dark-orchid { background-color: #9932CC; } .text-dark-orchid { color: #9932CC; } </style>
</head>
<body class="bg-gray-100">
    <div class="flex min-h-screen">
        <aside class="w-64 bg-white border-r">
            <div class="p-6"><a href="#" class="text-2xl font-bold text-dark-orchid">CarePlus</a></div>
            <nav class="px-4 space-y-2">
                <a href="<?php echo strtolower($userRole); ?>Dashboard.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-table-columns w-5"></i><span>Dashboard</span></a>
                <a href="my_transactions.php" class="flex items-center space-x-3 px-4 py-3 bg-purple-100 text-dark-orchid rounded-lg"><i class="fa-solid fa-money-bill-wave w-5"></i><span>My Transactions</span></a>
                <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg mt-8"><i class="fa-solid fa-arrow-right-from-bracket w-5"></i><span>Logout</span></a>
            </nav>
        </aside>

        <main class="flex-1 p-8">
            <header class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-800">My Transactions</h1>
                    <p class="text-gray-600 mt-1">A history of all your payments and earnings.</p>
                </div>
                 <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <p class="font-semibold text-slate-700"><?php echo htmlspecialchars($userName); ?></p>
                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($userRole); ?></p>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-dark-orchid text-white flex items-center justify-center font-bold text-lg"><?php echo htmlspecialchars($userAvatar); ?></div>
                </div>
            </header>

            <div class="mb-6 bg-white p-4 rounded-lg shadow-md">
                <?php if ($userRole !== 'Patient'): ?>
                <form method="GET" class="flex flex-wrap items-end gap-4">
                    <div>
                        <label class="text-sm font-medium text-gray-700">View earnings for:</label>
                        <div class="flex items-center space-x-2 mt-1">
                            <select name="month" class="p-2 border rounded-md bg-white">
                                <?php for($m=1; $m<=12; $m++): $monthName = date('F', mktime(0,0,0,$m, 10)); ?>
                                    <option value="<?php echo $m; ?>" <?php if($m == $selectedMonth) echo 'selected'; ?>><?php echo $monthName; ?></option>
                                <?php endfor; ?>
                            </select>
                            <select name="year" class="p-2 border rounded-md bg-white">
                                 <?php for($y=date('Y'); $y>=2024; $y--): ?>
                                    <option value="<?php echo $y; ?>" <?php if($y == $selectedYear) echo 'selected'; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-dark-orchid text-white rounded-md font-semibold hover:bg-purple-700">Filter</button>
                    <a href="my_transactions.php" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md">Clear</a>
                </form>
                <?php else: ?>
                <form method="GET" class="flex flex-wrap items-end gap-4">
                    <div><label class="text-sm font-medium text-gray-700">Start Date</label><input type="date" name="start_date" value="<?php echo htmlspecialchars($filter_start_date); ?>" class="w-full mt-1 p-2 border rounded-md"></div>
                    <div><label class="text-sm font-medium text-gray-700">End Date</label><input type="date" name="end_date" value="<?php echo htmlspecialchars($filter_end_date); ?>" class="w-full mt-1 p-2 border rounded-md"></div>
                    <button type="submit" class="px-4 py-2 bg-dark-orchid text-white rounded-md font-semibold">Filter</button>
                    <a href="my_transactions.php" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md">Clear</a>
                </form>
                <?php endif; ?>
            </div>
            
            <?php if ($userRole !== 'Patient'): ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white p-6 rounded-lg shadow-md"><p class="text-sm text-gray-500">Total Earnings (Gross)</p><p class="text-2xl font-bold text-slate-800">৳<?php echo number_format($grossIncome, 2); ?></p></div>
                <div class="bg-white p-6 rounded-lg shadow-md"><p class="text-sm text-gray-500">Platform Fee (30%)</p><p class="text-2xl font-bold text-red-600">- ৳<?php echo number_format($platformCommission, 2); ?></p></div>
                <div class="bg-white p-6 rounded-lg shadow-md"><p class="text-sm text-gray-500">Your Payout (Net)</p><p class="text-2xl font-bold text-green-600">৳<?php echo number_format($netIncome, 2); ?></p></div>
            </div>
            <?php endif; ?>
            
            <div class="bg-white p-6 rounded-xl shadow-lg">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Transaction History</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                            <tr>
                                <th class="px-6 py-3">Date</th>
                                <th class="px-6 py-3">Description</th>
                                <th class="px-6 py-3">Type</th>
                                <th class="px-6 py-3 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactions)): ?>
                                <tr><td colspan="4" class="text-center py-10 text-gray-500">No transactions found for this period.</td></tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $trans): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="px-6 py-4 font-semibold text-gray-700"><?php echo date("M d, Y", strtotime($trans['timestamp'])); ?></td>
                                    <td class="px-6 py-4"><?php echo htmlspecialchars($trans['description']); ?></td>
                                    <td class="px-6 py-4"><?php echo htmlspecialchars($trans['transactionType']); ?></td>
                                    <td class="px-6 py-4 text-right font-semibold text-lg">
                                        <?php if ($userRole === 'Patient' || ($userRole !== 'Patient' && $trans['transactionType'] === 'Registration Fee')): ?>
                                            <span class="text-red-600">- ৳<?php echo htmlspecialchars(number_format($trans['amount'], 2)); ?></span>
                                        <?php else: ?>
                                             <span class="text-green-600">+ ৳<?php echo htmlspecialchars(number_format($trans['amount'], 2)); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>