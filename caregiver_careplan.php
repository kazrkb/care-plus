<?php


session_start();

// === AUTHENTICATION & ACCESS CONTROL ===
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'CareGiver') {
    header("Location: login.php");
    exit();
}

// === DATABASE CONNECTION ===
$conn = require_once 'config.php';

// === SESSION DATA ===
$caregiverID = $_SESSION['userID'];
$userName = $_SESSION['Name'];
$userAvatar = strtoupper(substr($userName, 0, 2));

// === FEEDBACK MESSAGES ===
$successMsg = "";
$errorMsg = "";

// === HANDLE CARE PLAN FORM SUBMISSION ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_careplan'])) {
        // Create new care plan
        $bookingID = $_POST['bookingID'];
        $planDate = $_POST['planDate'];
        $exercisePlan = trim($_POST['exercisePlan']);
        $therapyInstructions = trim($_POST['therapyInstructions']);
        $progressNotes = trim($_POST['progressNotes']);
        
        // Validate required fields
        if (empty($bookingID) || empty($planDate)) {
            $errorMsg = "Patient booking and plan date are required.";
        } else {
            try {
                // Verify booking belongs to this caregiver
                $verifyBookingSql = "SELECT bookingID FROM caregiverbooking WHERE bookingID = ? AND careGiverID = ? AND status IN ('Scheduled', 'Active')";
                $verifyStmt = $conn->prepare($verifyBookingSql);
                $verifyStmt->bind_param("ii", $bookingID, $caregiverID);
                $verifyStmt->execute();
                $verifyResult = $verifyStmt->get_result();
                
                if ($verifyResult->num_rows === 0) {
                    $errorMsg = "Invalid booking selection or booking is not active.";
                } else {
                    // Insert new care plan
                    $insertSql = "INSERT INTO careplan (bookingID, careID, exercisePlan, date, therapyInstructions, progressNotes) VALUES (?, ?, ?, ?, ?, ?)";
                    $insertStmt = $conn->prepare($insertSql);
                    $insertStmt->bind_param("iissss", $bookingID, $caregiverID, $exercisePlan, $planDate, $therapyInstructions, $progressNotes);
                    
                    if ($insertStmt->execute()) {
                        $successMsg = "Care plan created successfully! Plan ID: #" . $conn->insert_id;
                        // Clear form data after successful submission
                        $_POST = [];
                    } else {
                        $errorMsg = "Error creating care plan: " . $conn->error;
                    }
                    $insertStmt->close();
                }
                $verifyStmt->close();
            } catch (Exception $e) {
                $errorMsg = "Error creating care plan: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['update_careplan'])) {
        // Update existing care plan
        $planID = $_POST['planID'];
        $exercisePlan = trim($_POST['exercisePlan']);
        $therapyInstructions = trim($_POST['therapyInstructions']);
        $progressNotes = trim($_POST['progressNotes']);
        $planDate = $_POST['planDate'];
        
        try {
            // Verify plan belongs to this caregiver
            $verifyPlanSql = "SELECT planID FROM careplan WHERE planID = ? AND careID = ?";
            $verifyStmt = $conn->prepare($verifyPlanSql);
            $verifyStmt->bind_param("ii", $planID, $caregiverID);
            $verifyStmt->execute();
            $verifyResult = $verifyStmt->get_result();
            
            if ($verifyResult->num_rows === 0) {
                $errorMsg = "Care plan not found or access denied.";
            } else {
                // Update care plan
                $updateSql = "UPDATE careplan SET exercisePlan = ?, therapyInstructions = ?, progressNotes = ?, date = ? WHERE planID = ? AND careID = ?";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param("ssssii", $exercisePlan, $therapyInstructions, $progressNotes, $planDate, $planID, $caregiverID);
                
                if ($updateStmt->execute()) {
                    $successMsg = "Care plan updated successfully!";
                } else {
                    $errorMsg = "Error updating care plan: " . $conn->error;
                }
                $updateStmt->close();
            }
            $verifyStmt->close();
        } catch (Exception $e) {
            $errorMsg = "Error updating care plan: " . $e->getMessage();
        }
    } elseif (isset($_POST['delete_careplan'])) {
        // Delete existing care plan
        $planID = $_POST['planID'];
        
        try {
            // Verify plan belongs to this caregiver before deletion
            $verifyPlanSql = "SELECT planID FROM careplan WHERE planID = ? AND careID = ?";
            $verifyStmt = $conn->prepare($verifyPlanSql);
            $verifyStmt->bind_param("ii", $planID, $caregiverID);
            $verifyStmt->execute();
            $verifyResult = $verifyStmt->get_result();
            
            if ($verifyResult->num_rows === 0) {
                $errorMsg = "Care plan not found or access denied.";
            } else {
                // Delete care plan
                $deleteSql = "DELETE FROM careplan WHERE planID = ? AND careID = ?";
                $deleteStmt = $conn->prepare($deleteSql);
                $deleteStmt->bind_param("ii", $planID, $caregiverID);
                
                if ($deleteStmt->execute()) {
                    $successMsg = "Care plan deleted successfully!";
                } else {
                    $errorMsg = "Error deleting care plan: " . $conn->error;
                }
                $deleteStmt->close();
            }
            $verifyStmt->close();
        } catch (Exception $e) {
            $errorMsg = "Error deleting care plan: " . $e->getMessage();
        }
    }
}

// === FETCH ACTIVE BOOKINGS FOR DROPDOWN ===
$bookingsQuery = "
    SELECT cb.bookingID, cb.patientID, u.Name as patientName, cb.bookingType, cb.startDate, cb.endDate, cb.status
    FROM caregiverbooking cb 
    LEFT JOIN patient p ON cb.patientID = p.patientID 
    LEFT JOIN users u ON p.patientID = u.userID 
    WHERE cb.careGiverID = ? AND cb.status IN ('Scheduled', 'Active')
    ORDER BY cb.startDate ASC
";
$bookingsStmt = $conn->prepare($bookingsQuery);
$bookingsStmt->bind_param("i", $caregiverID);
$bookingsStmt->execute();
$bookingsResult = $bookingsStmt->get_result();
$activeBookings = $bookingsResult->fetch_all(MYSQLI_ASSOC);
$bookingsStmt->close();

// === FETCH EXISTING CARE PLANS ===
$carePlansQuery = "
    SELECT cp.*, cb.patientID, u.Name as patientName, cb.bookingType, cb.startDate, cb.endDate, cb.status
    FROM careplan cp 
    LEFT JOIN caregiverbooking cb ON cp.bookingID = cb.bookingID 
    LEFT JOIN patient p ON cb.patientID = p.patientID 
    LEFT JOIN users u ON p.patientID = u.userID 
    WHERE cp.careID = ? 
    ORDER BY cp.date DESC, cp.planID DESC
";
$carePlansStmt = $conn->prepare($carePlansQuery);
$carePlansStmt->bind_param("i", $caregiverID);
$carePlansStmt->execute();
$carePlansResult = $carePlansStmt->get_result();
$existingPlans = $carePlansResult->fetch_all(MYSQLI_ASSOC);
$carePlansStmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Care Plan Management - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-dark-orchid { background-color: #9932CC; }
        .text-dark-orchid { color: #9932CC; }
        .shadow-orchid-custom { box-shadow: 0 4px 6px -1px rgba(153, 50, 204, 0.1), 0 2px 4px -2px rgba(153, 50, 204, 0.1); }
        .form-input { 
            margin-top: 0.25rem; 
            display: block; 
            width: 100%; 
            padding: 0.75rem; 
            border-width: 1px; 
            border-color: #D1D5DB; 
            border-radius: 0.5rem; 
            font-size: 0.875rem;
            transition: all 0.2s;
        }
        .form-input:focus { 
            outline: none;
            ring-width: 2px;
            ring-color: #9932CC; 
            border-color: #9932CC; 
        }
        .nav-active {
            background-color: #f3e8ff;
            color: #9932CC;
            border-right: 3px solid #9932CC;
        }
    </style>
</head>
<body class="bg-purple-50">
    <div class="flex min-h-screen">
        <!-- === SIDEBAR NAVIGATION === -->
        <aside class="w-64 bg-white border-r shadow-lg">
            <div class="p-6">
                <a href="#" class="text-2xl font-bold text-dark-orchid">CarePlus</a>
                <p class="text-sm text-gray-500 mt-1">CareGiver Portal</p>
            </div>
            
            <nav class="px-4 pb-4">
                <div class="space-y-1">
                    <a href="careGiverDashboard.php" 
                       class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg transition">
                        <i class="fa-solid fa-table-columns w-5"></i>
                        <span>Dashboard</span>
                    </a>
                    
                    <a href="caregiverProfile.php" 
                       class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg transition">
                        <i class="fa-regular fa-user w-5"></i>
                        <span>My Profile</span>
                    </a>
                    
                    <a href="caregiver_careplan.php" 
                       class="flex items-center space-x-3 px-4 py-3 nav-active rounded-lg transition">
                        <i class="fa-solid fa-clipboard-list w-5"></i>
                        <span>Care Plans</span>
                    </a>
                    
                    <a href="caregiver_availability.php" 
                       class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg transition">
                        <i class="fa-solid fa-calendar-days w-5"></i>
                        <span>Availability</span>
                    </a>
                    
                    <a href="my_bookings.php" 
                       class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg transition">
                        <i class="fa-solid fa-calendar-check w-5"></i>
                        <span>My Bookings</span>
                    </a>
                    
                    <a href="#" 
                       class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg transition">
                        <i class="fa-solid fa-heart-pulse w-5"></i>
                        <span>Patient Care</span>
                    </a>
                </div>
                
                <div class="mt-8 pt-4 border-t border-gray-200">
                    <a href="logout.php" 
                       class="flex items-center space-x-3 px-4 py-3 text-red-600 hover:bg-red-50 rounded-lg transition">
                        <i class="fa-solid fa-arrow-right-from-bracket w-5"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </nav>
        </aside>

        <!-- === MAIN CONTENT === -->
        <main class="flex-1 p-8">
            <!-- Header -->
            <header class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-800">Care Plan Management</h1>
                    <p class="text-gray-600 mt-1">Create and manage care plans for your patients</p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <p class="font-semibold text-slate-700"><?php echo htmlspecialchars($userName); ?></p>
                        <p class="text-sm text-gray-500">CareGiver</p>
                    </div>
                    <div class="h-10 w-10 rounded-full bg-dark-orchid flex items-center justify-center text-white font-bold">
                        <?php echo htmlspecialchars($userAvatar); ?>
                    </div>
                </div>
            </header>

            <!-- Success/Error Messages -->
            <?php if ($successMsg): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center">
                    <i class="fa-solid fa-check-circle mr-3 text-green-600"></i>
                    <span><?php echo htmlspecialchars($successMsg); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($errorMsg): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-center">
                    <i class="fa-solid fa-exclamation-circle mr-3 text-red-600"></i>
                    <span><?php echo htmlspecialchars($errorMsg); ?></span>
                </div>
            <?php endif; ?>

            <!-- Quick Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-orchid-custom p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Total Care Plans</p>
                            <p class="text-2xl font-bold text-slate-800"><?php echo count($existingPlans); ?></p>
                        </div>
                        <div class="h-12 w-12 bg-purple-100 rounded-lg flex items-center justify-center">
                            <i class="fa-solid fa-clipboard-list text-dark-orchid text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-orchid-custom p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Active Plans</p>
                            <p class="text-2xl font-bold text-slate-800">
                                <?php echo count(array_filter($existingPlans, function($plan) { 
                                    return $plan['date'] >= date('Y-m-d'); 
                                })); ?>
                            </p>
                        </div>
                        <div class="h-12 w-12 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fa-solid fa-calendar-check text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-orchid-custom p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Active Bookings</p>
                            <p class="text-2xl font-bold text-slate-800"><?php echo count($activeBookings); ?></p>
                        </div>
                        <div class="h-12 w-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fa-solid fa-users text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-orchid-custom p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">This Month</p>
                            <p class="text-2xl font-bold text-slate-800">
                                <?php echo count(array_filter($existingPlans, function($plan) { 
                                    return date('Y-m', strtotime($plan['date'])) === date('Y-m'); 
                                })); ?>
                            </p>
                        </div>
                        <div class="h-12 w-12 bg-orange-100 rounded-lg flex items-center justify-center">
                            <i class="fa-solid fa-calendar-days text-orange-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Create New Care Plan Section -->
            <div class="bg-white rounded-xl shadow-orchid-custom p-6 mb-8">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-slate-800">
                        <i class="fa-solid fa-plus-circle text-dark-orchid mr-2"></i>
                        Create New Care Plan
                    </h2>
                    <?php if (empty($activeBookings)): ?>
                        <span class="text-sm text-orange-600 bg-orange-50 px-3 py-1 rounded-full">
                            <i class="fa-solid fa-exclamation-triangle mr-1"></i>
                            No active bookings available
                        </span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($activeBookings)): ?>
                    <form method="POST" action="" class="space-y-6">
                        <input type="hidden" name="create_careplan" value="1">
                        
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Patient Booking Selection -->
                            <div>
                                <label for="bookingID" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fa-solid fa-user-injured text-blue-600 mr-2"></i>
                                    Select Patient Booking <span class="text-red-500">*</span>
                                </label>
                                <select name="bookingID" id="bookingID" class="form-input" required>
                                    <option value="">Choose a patient booking...</option>
                                    <?php foreach ($activeBookings as $booking): ?>
                                        <option value="<?php echo $booking['bookingID']; ?>" 
                                                <?php echo (isset($_POST['bookingID']) && $_POST['bookingID'] == $booking['bookingID']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($booking['patientName']); ?> - 
                                            <?php echo htmlspecialchars($booking['bookingType']); ?> Care
                                            (<?php echo date('M d, Y', strtotime($booking['startDate'])); ?> - 
                                            <?php echo date('M d, Y', strtotime($booking['endDate'])); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="text-xs text-gray-500 mt-1">Select the patient booking for which you want to create a care plan</p>
                            </div>

                            <!-- Plan Date -->
                            <div>
                                <label for="planDate" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fa-solid fa-calendar text-green-600 mr-2"></i>
                                    Plan Date <span class="text-red-500">*</span>
                                </label>
                                <input type="date" name="planDate" id="planDate" 
                                       value="<?php echo isset($_POST['planDate']) ? $_POST['planDate'] : date('Y-m-d'); ?>" 
                                       class="form-input" required>
                                <p class="text-xs text-gray-500 mt-1">Date when this care plan should be implemented</p>
                            </div>
                        </div>

                        <!-- Exercise Plan -->
                        <div>
                            <label for="exercisePlan" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fa-solid fa-dumbbell text-orange-600 mr-2"></i>
                                Exercise Plan
                            </label>
                            <textarea name="exercisePlan" id="exercisePlan" rows="4" 
                                      class="form-input" 
                                      placeholder="Describe the exercise routine, physical activities, mobility exercises, or rehabilitation activities for the patient..."><?php echo isset($_POST['exercisePlan']) ? htmlspecialchars($_POST['exercisePlan']) : ''; ?></textarea>
                            <p class="text-xs text-gray-500 mt-1">Include specific exercises, duration, frequency, and any precautions</p>
                        </div>

                        <!-- Therapy Instructions -->
                        <div>
                            <label for="therapyInstructions" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fa-solid fa-hand-holding-medical text-purple-600 mr-2"></i>
                                Therapy Instructions
                            </label>
                            <textarea name="therapyInstructions" id="therapyInstructions" rows="4" 
                                      class="form-input" 
                                      placeholder="Provide detailed therapy instructions, treatment procedures, medication schedules, or specialized care instructions..."><?php echo isset($_POST['therapyInstructions']) ? htmlspecialchars($_POST['therapyInstructions']) : ''; ?></textarea>
                            <p class="text-xs text-gray-500 mt-1">Include treatment methods, schedules, and any special considerations</p>
                        </div>

                        <!-- Progress Notes -->
                        <div>
                            <label for="progressNotes" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fa-solid fa-notes-medical text-blue-600 mr-2"></i>
                                Initial Progress Notes
                            </label>
                            <textarea name="progressNotes" id="progressNotes" rows="3" 
                                      class="form-input" 
                                      placeholder="Record initial assessment, baseline measurements, patient condition, or any observations..."><?php echo isset($_POST['progressNotes']) ? htmlspecialchars($_POST['progressNotes']) : ''; ?></textarea>
                            <p class="text-xs text-gray-500 mt-1">Document patient's current condition and initial assessment</p>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex justify-end pt-4 border-t">
                            <button type="submit" class="bg-dark-orchid text-white px-8 py-3 rounded-lg hover:bg-purple-700 transition font-semibold">
                                <i class="fa-solid fa-plus mr-2"></i>Create Care Plan
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-8 text-center">
                        <i class="fa-solid fa-calendar-xmark text-4xl text-gray-300 mb-4"></i>
                        <h3 class="font-semibold text-gray-700 mb-2">No Active Bookings Available</h3>
                        <p class="text-gray-600 mb-4">
                            You don't have any active patient bookings at the moment. 
                            Care plans can only be created for scheduled or active bookings.
                        </p>
                        <a href="my_bookings.php" class="inline-flex items-center text-dark-orchid hover:text-purple-700 font-medium">
                            <i class="fa-solid fa-calendar-check mr-2"></i>
                            View My Bookings
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Existing Care Plans -->
            <div class="bg-white rounded-xl shadow-orchid-custom p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-slate-800">
                        <i class="fa-solid fa-list text-dark-orchid mr-2"></i>
                        My Care Plans
                    </h2>
                    <span class="text-sm text-gray-600 bg-gray-100 px-3 py-1 rounded-full">
                        <?php echo count($existingPlans); ?> Total Plans
                    </span>
                </div>

                <?php if (!empty($existingPlans)): ?>
                    <div class="space-y-4">
                        <?php foreach ($existingPlans as $plan): ?>
                            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition" id="plan-<?php echo $plan['planID']; ?>">
                                <div class="flex justify-between items-start mb-3">
                                    <div>
                                        <h3 class="font-semibold text-slate-800">
                                            <i class="fa-solid fa-user text-blue-600 mr-2"></i>
                                            <?php echo htmlspecialchars($plan['patientName'] ?? 'Unknown Patient'); ?>
                                        </h3>
                                        <p class="text-sm text-gray-600">
                                            <?php echo htmlspecialchars($plan['bookingType']); ?> Care Plan
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xs bg-purple-100 text-purple-700 px-2 py-1 rounded-full">
                                            Plan #<?php echo $plan['planID']; ?>
                                        </span>
                                        <p class="text-xs text-gray-500 mt-1">
                                            <?php echo date('M d, Y', strtotime($plan['date'])); ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <!-- Care Plan Content Display -->
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm mb-4" id="content-<?php echo $plan['planID']; ?>">
                                    <?php if (!empty($plan['exercisePlan'])): ?>
                                        <div>
                                            <label class="font-medium text-orange-700">Exercise Plan:</label>
                                            <p class="text-gray-700 mt-1"><?php echo htmlspecialchars(substr($plan['exercisePlan'], 0, 100)); ?>...</p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($plan['therapyInstructions'])): ?>
                                        <div>
                                            <label class="font-medium text-purple-700">Therapy Instructions:</label>
                                            <p class="text-gray-700 mt-1"><?php echo htmlspecialchars(substr($plan['therapyInstructions'], 0, 100)); ?>...</p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($plan['progressNotes'])): ?>
                                        <div>
                                            <label class="font-medium text-blue-700">Progress Notes:</label>
                                            <p class="text-gray-700 mt-1"><?php echo htmlspecialchars(substr($plan['progressNotes'], 0, 100)); ?>...</p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Edit Form (Initially Hidden) -->
                                <div class="hidden space-y-4" id="edit-form-<?php echo $plan['planID']; ?>">
                                    <form method="POST" action="" class="space-y-4">
                                        <input type="hidden" name="update_careplan" value="1">
                                        <input type="hidden" name="planID" value="<?php echo $plan['planID']; ?>">
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Plan Date</label>
                                            <input type="date" name="planDate" value="<?php echo $plan['date']; ?>" 
                                                   class="form-input" required>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Exercise Plan</label>
                                            <textarea name="exercisePlan" rows="3" class="form-input" 
                                                      placeholder="Update exercise plan..."><?php echo htmlspecialchars($plan['exercisePlan']); ?></textarea>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Therapy Instructions</label>
                                            <textarea name="therapyInstructions" rows="3" class="form-input" 
                                                      placeholder="Update therapy instructions..."><?php echo htmlspecialchars($plan['therapyInstructions']); ?></textarea>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Progress Notes</label>
                                            <textarea name="progressNotes" rows="3" class="form-input" 
                                                      placeholder="Update progress notes..."><?php echo htmlspecialchars($plan['progressNotes']); ?></textarea>
                                        </div>
                                        
                                        <div class="flex justify-end space-x-3 pt-3 border-t">
                                            <button type="button" onclick="cancelEdit(<?php echo $plan['planID']; ?>)" 
                                                    class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium">
                                                <i class="fa-solid fa-times mr-1"></i>Cancel
                                            </button>
                                            <button type="submit" 
                                                    class="px-6 py-2 bg-dark-orchid text-white rounded-lg hover:bg-purple-700 transition font-semibold">
                                                <i class="fa-solid fa-save mr-1"></i>Update Plan
                                            </button>
                                        </div>
                                    </form>
                                </div>
                                
                                <div class="flex justify-between items-center mt-4 pt-3 border-t border-gray-100">
                                    <div class="flex items-center text-xs text-gray-500">
                                        <i class="fa-solid fa-calendar mr-1"></i>
                                        Booking: <?php echo date('M d', strtotime($plan['startDate'])); ?> - 
                                        <?php echo date('M d, Y', strtotime($plan['endDate'])); ?>
                                    </div>
                                    <div class="space-x-2">
                                        <button onclick="toggleView(<?php echo $plan['planID']; ?>)" 
                                                class="text-blue-600 hover:text-blue-800 text-sm font-medium" 
                                                id="view-btn-<?php echo $plan['planID']; ?>">
                                            <i class="fa-solid fa-eye mr-1"></i>View Full
                                        </button>
                                        <button onclick="toggleEdit(<?php echo $plan['planID']; ?>)" 
                                                class="text-purple-600 hover:text-purple-800 text-sm font-medium"
                                                id="edit-btn-<?php echo $plan['planID']; ?>">
                                            <i class="fa-solid fa-edit mr-1"></i>Edit
                                        </button>
                                        <button onclick="deletePlan(<?php echo $plan['planID']; ?>)" 
                                                class="text-red-600 hover:text-red-800 text-sm font-medium">
                                            <i class="fa-solid fa-trash mr-1"></i>Delete
                                        </button>
                                    </div>
                                </div>

                                <!-- Full View Modal Content (Initially Hidden) -->
                                <div class="hidden mt-4 p-4 bg-gray-50 rounded-lg" id="full-view-<?php echo $plan['planID']; ?>">
                                    <h4 class="font-semibold text-gray-800 mb-3">Complete Care Plan Details</h4>
                                    <div class="space-y-3 text-sm">
                                        <?php if (!empty($plan['exercisePlan'])): ?>
                                            <div>
                                                <label class="font-medium text-orange-700">Exercise Plan:</label>
                                                <p class="text-gray-700 mt-1 whitespace-pre-wrap"><?php echo htmlspecialchars($plan['exercisePlan']); ?></p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($plan['therapyInstructions'])): ?>
                                            <div>
                                                <label class="font-medium text-purple-700">Therapy Instructions:</label>
                                                <p class="text-gray-700 mt-1 whitespace-pre-wrap"><?php echo htmlspecialchars($plan['therapyInstructions']); ?></p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($plan['progressNotes'])): ?>
                                            <div>
                                                <label class="font-medium text-blue-700">Progress Notes:</label>
                                                <p class="text-gray-700 mt-1 whitespace-pre-wrap"><?php echo htmlspecialchars($plan['progressNotes']); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <i class="fa-solid fa-clipboard-list text-4xl text-gray-300 mb-4"></i>
                        <h3 class="font-semibold text-gray-700 mb-2">No Care Plans Yet</h3>
                        <p class="text-gray-600">
                            You haven't created any care plans yet. Start by creating a care plan for your active patient bookings.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                    <i class="fa-solid fa-exclamation-triangle text-red-600 text-xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mt-4">Delete Care Plan</h3>
                <div class="mt-2 px-7 py-3">
                    <p class="text-sm text-gray-500">
                        Are you sure you want to delete this care plan? This action cannot be undone.
                    </p>
                </div>
                <form method="POST" action="" id="deleteForm">
                    <input type="hidden" name="delete_careplan" value="1">
                    <input type="hidden" name="planID" id="deletePlanID" value="">
                    
                    <div class="flex justify-center space-x-3 mt-4">
                        <button type="button" onclick="closeDeleteModal()" 
                                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                            <i class="fa-solid fa-trash mr-1"></i>Delete Plan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Auto-hide success/error messages after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.bg-green-50, .bg-red-50');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);

        // Toggle full view of care plan details
        function toggleView(planID) {
            const fullView = document.getElementById('full-view-' + planID);
            const viewBtn = document.getElementById('view-btn-' + planID);
            
            if (fullView.classList.contains('hidden')) {
                fullView.classList.remove('hidden');
                viewBtn.innerHTML = '<i class="fa-solid fa-eye-slash mr-1"></i>Hide Details';
            } else {
                fullView.classList.add('hidden');
                viewBtn.innerHTML = '<i class="fa-solid fa-eye mr-1"></i>View Full';
            }
        }

        // Toggle edit mode for care plan
        function toggleEdit(planID) {
            const content = document.getElementById('content-' + planID);
            const editForm = document.getElementById('edit-form-' + planID);
            const editBtn = document.getElementById('edit-btn-' + planID);
            const fullView = document.getElementById('full-view-' + planID);
            
            if (editForm.classList.contains('hidden')) {
                // Show edit form
                content.classList.add('hidden');
                editForm.classList.remove('hidden');
                fullView.classList.add('hidden'); // Hide full view if open
                editBtn.innerHTML = '<i class="fa-solid fa-times mr-1"></i>Cancel';
                editBtn.className = 'text-gray-600 hover:text-gray-800 text-sm font-medium';
            } else {
                // Hide edit form
                content.classList.remove('hidden');
                editForm.classList.add('hidden');
                editBtn.innerHTML = '<i class="fa-solid fa-edit mr-1"></i>Edit';
                editBtn.className = 'text-purple-600 hover:text-purple-800 text-sm font-medium';
            }
        }

        // Cancel edit mode
        function cancelEdit(planID) {
            const content = document.getElementById('content-' + planID);
            const editForm = document.getElementById('edit-form-' + planID);
            const editBtn = document.getElementById('edit-btn-' + planID);
            
            content.classList.remove('hidden');
            editForm.classList.add('hidden');
            editBtn.innerHTML = '<i class="fa-solid fa-edit mr-1"></i>Edit';
            editBtn.className = 'text-purple-600 hover:text-purple-800 text-sm font-medium';
        }

        // Show delete confirmation modal
        function deletePlan(planID) {
            document.getElementById('deletePlanID').value = planID;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        // Close delete modal
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDeleteModal();
            }
        });
    </script>
</body>
</html>
/**
 * CAREGIVER CARE PLAN MANAGEMENT PAGE
 * 
 * This page allows caregivers to create, view, and manage care plans for their patients.
 * 
 * Database Tables Used:
 * - careplan: Stores care plan details (exercisePlan, therapyInstructions, progressNotes)
 * - caregiverbooking: Links care plans to patient bookings
 * - users: Patient and caregiver information
 * - patient: Patient-specific details
 * 
 * Features:
 * - Create new care plans for active patient bookings
 * - View existing care plans with patient information
 * - Edit and update care plan details
 * - Progress tracking and notes management
 */