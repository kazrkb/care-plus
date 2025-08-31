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
$successMsg = "";
$errorMsg = "";

// Initialize filter variables
$patientNameFilter = "";
$dateFilter = "";
$showToday = false;

// Handle filter submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['filter_patient']) && !empty($_POST['patient_name'])) {
        $patientNameFilter = trim($_POST['patient_name']);
    }
    
    if (isset($_POST['filter_date']) && !empty($_POST['filter_date'])) {
        $dateFilter = $_POST['filter_date'];
    }
    
    if (isset($_POST['today_filter'])) {
        $showToday = true;
    }
    
    if (isset($_POST['clear_filters'])) {
        $patientNameFilter = "";
        $dateFilter = "";
        $showToday = false;
    }
}

// Handle GET parameters for today filter
if (isset($_GET['today'])) {
    $showToday = true;
}

// --- Handle Deleting a Diet Plan ---
if (isset($_GET['delete_id'])) {
    $planID = (int)$_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM dietplan WHERE planID = ? AND nutritionistID = ?");
    $stmt->bind_param("ii", $planID, $nutritionistID);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $successMsg = "Diet plan deleted successfully.";
    } else {
        $errorMsg = "Could not delete the diet plan.";
    }
    $stmt->close();
}

// --- Handle Clear All Diet Plans ---
if (isset($_GET['clear_all']) && $_GET['clear_all'] === 'confirm') {
    $stmt = $conn->prepare("DELETE FROM dietplan WHERE nutritionistID = ?");
    $stmt->bind_param("i", $nutritionistID);
    if ($stmt->execute()) {
        $successMsg = "All diet plans have been cleared successfully.";
    } else {
        $errorMsg = "Failed to clear all diet plans.";
    }
    $stmt->close();
}

// --- Build dynamic query with filters ---
$query = "
    SELECT
        dp.planID, dp.startDate, dp.endDate, dp.dietType, dp.caloriesPerDay, 
        dp.mealGuidelines, dp.exerciseGuidelines,
        u.Name as patientName, a.appointmentDate, a.appointmentID,
        pt.age as patientAge, pt.gender as patientGender, pt.patientID,
        nu.Name as nutritionistName, n.specialty as nutritionistSpecialty
    FROM dietplan dp
    JOIN appointment a ON dp.appointmentID = a.appointmentID
    JOIN users u ON a.patientID = u.userID
    LEFT JOIN patient pt ON u.userID = pt.patientID
    JOIN nutritionist n ON dp.nutritionistID = n.nutritionistID
    JOIN users nu ON n.nutritionistID = nu.userID
    WHERE dp.nutritionistID = ?
";

$params = [$nutritionistID];
$types = "i";

// Add patient name filter
if (!empty($patientNameFilter)) {
    $query .= " AND u.Name LIKE ?";
    $params[] = "%" . $patientNameFilter . "%";
    $types .= "s";
}

// Add date filter
if (!empty($dateFilter)) {
    $query .= " AND DATE(dp.startDate) = ?";
    $params[] = $dateFilter;
    $types .= "s";
} elseif ($showToday) {
    $query .= " AND DATE(dp.startDate) = CURDATE()";
}

$query .= " ORDER BY dp.startDate DESC";

$stmt = $conn->prepare($query);
if (count($params) > 1) {
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param($types, $params[0]);
}
$stmt->execute();
$dietplans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get total diet plan count for clear all functionality
$totalCountQuery = "SELECT COUNT(*) as total FROM dietplan WHERE nutritionistID = ?";
$totalStmt = $conn->prepare($totalCountQuery);
$totalStmt->bind_param("i", $nutritionistID);
$totalStmt->execute();
$totalCount = $totalStmt->get_result()->fetch_assoc()['total'];
$totalStmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Diet Plans - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Dancing+Script:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-dark-orchid { background-color: #9932CC; }
        .text-dark-orchid { color: #9932CC; }
        .shadow-orchid-custom { box-shadow: 0 4px 6px -1px rgba(153, 50, 204, 0.1), 0 2px 4px -2px rgba(153, 50, 204, 0.1); }
        .action-btn {
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }
        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .btn-view { background: #3b82f6; color: white; }
        .btn-view:hover { background: #8aa7e6ff; }
        .btn-edit { background: #8310b9ff; color: white; }
        .btn-edit:hover { background: #059669; }
        .btn-delete { background: #ef4444; color: white; }
        .btn-delete:hover { background: #dc2626; }
        .dietplan-pad { 
            border: 1px solid #e2e8f0; 
            border-radius: 0.5rem; 
            background-color: #fff; 
            padding: 2rem; 
            max-width: 800px; 
            margin: auto; 
        }
        .diet-symbol { 
            font-size: 3rem; 
            font-weight: bold; 
            color: #28a745; 
            line-height: 1; 
        }
        .signature-area {
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 80"><path d="M20,40 Q50,20 80,40 T140,35 T200,30 Q230,25 260,35" stroke="%2328a745" stroke-width="2" fill="none" stroke-linecap="round"/><path d="M50,55 Q70,45 90,55 T130,50" stroke="%2328a745" stroke-width="1.5" fill="none" stroke-linecap="round"/></svg>') no-repeat;
            background-size: contain;
            background-position: center;
        }
        .signature-text {
            font-family: 'Dancing Script', cursive;
            font-size: 24px;
            color: #0f1010ff;
            font-weight: bold;
        }
        .filter-input {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
        }
        .filter-input:focus {
            background: white;
            border-color: #7d3789ff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
        }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="bg-purple-50">
    <div class="flex min-h-screen">
        <aside class="w-64 bg-white border-r no-print">
            <div class="p-6"><a href="#" class="text-2xl font-bold text-dark-orchid">CarePlus</a></div>
            <nav class="px-4">
                <a href="nutritionistDashboard.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg">
                    <i class="fa-solid fa-table-columns w-5"></i><span>Dashboard</span>
                </a>
                <a href="nutritionist_consultations.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg">
                    <i class="fa-solid fa-laptop-medical w-5"></i><span>Consultations</span>
                </a>
                <a href="nutritionist_view_dietplans.php" class="flex items-center space-x-3 px-4 py-3 bg-purple-100 text-green-600 rounded-lg">
                    <i class="fa-solid fa-apple-alt w-5"></i><span>Diet Plans</span>
                </a>
                <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg mt-8">
                    <i class="fa-solid fa-arrow-right-from-bracket w-5"></i><span>Logout</span>
                </a>
            </nav>
        </aside>

        <main class="flex-1 p-8">
            <header class="flex justify-between items-center mb-8 no-print">
                <div>
                    <h1 class="text-3xl font-bold text-slate-800">Diet Plans</h1>
                    <p class="text-gray-600 mt-1">Manage all diet plans you have created.</p>
                </div>
                <div class="flex space-x-3">
                    <?php if ($totalCount > 0): ?>
                    <button onclick="confirmClearAll()" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 font-semibold">
                        <i class="fa-solid fa-trash-alt mr-2"></i>Clear All
                    </button>
                    <?php endif; ?>
                    <a href="nutritionist_consultations.php" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold">
                        <i class="fa-solid fa-plus mr-2"></i>Create New
                    </a>
                </div>
            </header>

            <?php if ($successMsg): ?>
            <div class="mb-6 p-4 bg-green-100 text-green-800 border-l-4 border-green-500 rounded-r-lg no-print" role="alert">
                <p><?php echo $successMsg; ?></p>
            </div>
            <?php endif; ?>
            
            <?php if ($errorMsg): ?>
            <div class="mb-6 p-4 bg-red-100 text-red-800 border-l-4 border-red-500 rounded-r-lg no-print" role="alert">
                <p><?php echo $errorMsg; ?></p>
            </div>
            <?php endif; ?>

            <!-- Simple Filter Section -->
            <div class="bg-gradient-to-r from-green-600 to-emerald-600 rounded-lg p-6 mb-6 no-print">
                <form method="POST" class="flex flex-wrap items-center gap-4">
                    <div class="flex flex-col">
                        <label class="text-white text-sm font-medium mb-1">Patient Name</label>
                        <input type="text" name="patient_name" value="<?php echo htmlspecialchars($patientNameFilter); ?>" 
                               placeholder="Enter patient name" class="filter-input">
                    </div>
                    <div class="flex flex-col">
                        <label class="text-white text-sm font-medium mb-1">Plan Start Date</label>
                        <input type="date" name="filter_date" value="<?php echo htmlspecialchars($dateFilter); ?>" 
                               class="filter-input">
                    </div>
                    <div class="flex gap-2 mt-5">
                        <button type="submit" name="filter_patient" class="bg-white text-green-700 px-4 py-2 rounded-lg hover:bg-gray-100 font-medium">
                            <i class="fa-solid fa-search mr-1"></i>Filter
                        </button>
                        <button type="submit" name="today_filter" class="bg-yellow-400 text-green-900 px-4 py-2 rounded-lg hover:bg-yellow-300 font-medium">
                            <i class="fa-solid fa-calendar-day mr-1"></i>Today
                        </button>
                        <button type="submit" name="clear_filters" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 font-medium">
                            <i class="fa-solid fa-times mr-1"></i>Clear
                        </button>
                    </div>
                </form>
            </div>
           
            <!-- Results Summary -->
            <div class="mb-4 flex justify-between items-center text-sm text-gray-600 no-print">
                <div>
                    <i class="fa-solid fa-info-circle mr-1"></i>
                    Found <?php echo count($dietplans); ?> diet plan(s)
                </div>
                <div class="text-gray-500">
                    Total diet plans: <?php echo $totalCount; ?>
                </div>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-orchid-custom">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                            <tr>
                                <th class="px-6 py-3">Start Date</th>
                                <th class="px-6 py-3">Patient Name</th>
                                <th class="px-6 py-3">Diet Type</th>
                                <th class="px-6 py-3">Duration</th>
                                <th class="px-6 py-3 text-center no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($dietplans)): ?>
                            <tr><td colspan="5" class="text-center py-10 text-gray-500">
                                <?php if (!empty($patientNameFilter) || !empty($dateFilter) || $showToday): ?>
                                    No diet plans found matching your filter criteria.
                                <?php else: ?>
                                    You have not created any diet plans yet.
                                <?php endif; ?>
                            </td></tr>
                        <?php else: ?>
                            <?php foreach($dietplans as $plan): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-6 py-4 font-semibold"><?php echo date('M j, Y', strtotime($plan['startDate'])); ?></td>
                                <td class="px-6 py-4"><?php echo htmlspecialchars($plan['patientName']); ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">
                                        <?php echo htmlspecialchars($plan['dietType']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <?php 
                                    $start = new DateTime($plan['startDate']);
                                    $end = new DateTime($plan['endDate']);
                                    $duration = $start->diff($end)->days;
                                    echo $duration . ' days';
                                    ?>
                                </td>
                                <td class="px-6 py-4 text-center no-print">
                                    <div class="flex justify-center space-x-2">
                                        <button onclick='openViewModal(<?php echo htmlspecialchars(json_encode($plan), ENT_QUOTES, "UTF-8"); ?>)' class="action-btn btn-view">
                                            <i class="fa-solid fa-eye mr-1"></i>View
                                        </button>
                                        <a href="edit_dietplan.php?id=<?php echo $plan['planID']; ?>" class="action-btn btn-edit inline-block text-center">
                                            <i class="fa-solid fa-edit mr-1"></i>Edit
                                        </a>
                                        <button onclick="confirmDelete(<?php echo $plan['planID']; ?>)" class="action-btn btn-delete">
                                            <i class="fa-solid fa-trash mr-1"></i>Delete
                                        </button>
                                    </div>
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

    <!-- Diet Plan View Modal -->
    <div id="viewModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
            <div class="dietplan-pad">
                <!-- Diet Plan Header -->
                <header class="text-center border-b-2 border-gray-200 pb-4 mb-6">
                    <h2 class="text-3xl font-bold text-slate-800" id="modalNutritionistName">Nutritionist Name</h2>
                    <p class="text-md text-gray-600" id="modalNutritionistSpecialty">Specialty</p>
                    <p class="text-sm text-gray-500">Personalized Diet Plan</p>
                </header>

                <!-- Patient & Plan Info -->
                <div class="flex justify-between items-center border-b border-gray-200 pb-4 mb-6 text-sm">
                    <div class="w-2/3 space-y-1">
                        <p><strong>Patient Name:</strong> <span id="modalPatientName"></span></p>
                        <p><strong>Patient ID:</strong> <span id="modalPatientID"></span></p>
                        <p><strong>Diet Type:</strong> <span id="modalDietType" class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium"></span></p>
                    </div>
                    <div class="w-1/3 text-right space-y-1">
                        <p><strong>Age:</strong> <span id="modalPatientAge"></span>, <strong>Gender:</strong> <span id="modalPatientGender"></span></p>
                        <p><strong>Duration:</strong> <span id="modalStartDate"></span> to <span id="modalEndDate"></span></p>
                        <p><strong>Daily Calories:</strong> <span id="modalCalories"></span> kcal</p>
                    </div>
                </div>

                <!-- Diet Plan Content -->
                <div class="flex">
                    <div class="diet-symbol mr-4 mt-2">🥗</div>
                    <div class="flex-1 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Meal Guidelines & Food Recommendations</label>
                            <div class="p-3 bg-green-50 rounded-md border border-green-200">
                                <pre id="modalMealGuidelines" class="text-sm text-gray-800 whitespace-pre-wrap"></pre>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Exercise Guidelines & Additional Instructions</label>
                            <div class="p-3 bg-blue-50 rounded-md border border-blue-200">
                                <pre id="modalExerciseGuidelines" class="text-sm text-gray-800 whitespace-pre-wrap"></pre>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Nutritionist Signature Area -->
                <div class="mt-8 pt-4 border-t border-gray-200">
                    <div class="flex justify-between items-end">
                        <div class="flex-1"></div>
                        <div class="text-right">
                            <!-- Auto Signature -->
                            <div class="signature-area h-16 w-64 mb-2 flex items-center justify-center">
                                <span class="signature-text" id="autoSignature">Nutritionist Name</span>
                            </div>
                            <!-- Signature Line -->
                            <div class="border-b border-gray-400 w-64 mb-2"></div>
                            <p class="text-sm text-gray-600">Nutritionist's Signature</p>
                            <p class="text-xs text-gray-500 mt-1">Licensed Nutritionist</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Modal Footer -->
            <div class="bg-gray-100 px-6 py-4 flex justify-between rounded-b-lg no-print">
                <button onclick="printDietPlan()" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                    <i class="fa-solid fa-print mr-2"></i>Print
                </button>
                <button type="button" onclick="closeViewModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">
                    Close
                </button>
            </div>
        </div>
    </div>
   
    <script>
        const viewModal = document.getElementById('viewModal');
        
        function openViewModal(data) {
            // Fill diet plan details
            document.getElementById('modalNutritionistName').textContent = data.nutritionistName;
            document.getElementById('modalNutritionistSpecialty').textContent = data.nutritionistSpecialty;
            document.getElementById('modalPatientName').textContent = data.patientName;
            document.getElementById('modalPatientID').textContent = data.patientID || 'N/A';
            document.getElementById('modalPatientAge').textContent = data.patientAge || 'N/A';
            document.getElementById('modalPatientGender').textContent = data.patientGender || 'N/A';
            document.getElementById('modalDietType').textContent = data.dietType;
            document.getElementById('modalStartDate').textContent = new Date(data.startDate).toLocaleDateString();
            document.getElementById('modalEndDate').textContent = new Date(data.endDate).toLocaleDateString();
            document.getElementById('modalCalories').textContent = data.caloriesPerDay;
            document.getElementById('modalMealGuidelines').textContent = data.mealGuidelines;
            document.getElementById('modalExerciseGuidelines').textContent = data.exerciseGuidelines || 'No specific exercise guidelines provided.';
            
            // Auto signature
            document.getElementById('autoSignature').textContent = data.nutritionistName;
            
            viewModal.classList.remove('hidden');
        }
        
        function closeViewModal() { 
            viewModal.classList.add('hidden'); 
        }
        
        function printDietPlan() {
            const dietPlanContent = document.querySelector('.dietplan-pad');
            const originalContents = document.body.innerHTML;
            const printContents = dietPlanContent.outerHTML;
            
            document.body.innerHTML = `
                <div style="font-family: 'Inter', sans-serif; padding: 20px;">
                    ${printContents}
                </div>
            `;
            
            window.print();
            document.body.innerHTML = originalContents;
            location.reload(); // Reload to restore event listeners
        }
        
        function confirmDelete(planID) {
            if (confirm('Are you sure you want to delete this diet plan? This action cannot be undone.')) {
                window.location.href = 'nutritionist_view_dietplans.php?delete_id=' + planID;
            }
        }
        
        function confirmClearAll() {
            if (confirm('Are you sure you want to delete ALL your diet plans? This action cannot be undone and will permanently remove all diet plan records.')) {
                if (confirm('This is your final warning! Clicking OK will delete ALL diet plans permanently. Are you absolutely sure?')) {
                    window.location.href = 'nutritionist_view_dietplans.php?clear_all=confirm';
                }
            }
        }
    </script>
</body>
</html>
