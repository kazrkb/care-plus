<?php
session_start();
$conn = require_once 'config.php';
date_default_timezone_set('Asia/Dhaka');

// Protect this page: ensure the user is a logged-in Admin
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$adminID = $_SESSION['userID'];
$userName = $_SESSION['Name'];
$userAvatar = strtoupper(substr($userName, 0, 2));
$successMsg = "";
$errorMsg = "";

// --- Handle Approve/Reject Actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id_to_verify'])) {
    $userIDToUpdate = (int)$_POST['user_id_to_verify'];
    $action = $_POST['action'];
    $newStatus = ($action === 'Approve') ? 'Approved' : 'Rejected';

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE users SET verification_status = ? WHERE userID = ?");
        $stmt->bind_param("si", $newStatus, $userIDToUpdate);
        $stmt->execute();
        $stmt->close();

        if ($newStatus === 'Approved') {
            $message = "Congratulations! Your account has been verified and approved by an administrator. You now have full access to your dashboard.";
            $stmt = $conn->prepare("INSERT INTO notification (userID, type, message, sentDate, status) VALUES (?, 'Account Approval', ?, CURDATE(), 'Unread')");
            $stmt->bind_param("is", $userIDToUpdate, $message);
            $stmt->execute();
            $stmt->close();
            $successMsg = "User has been approved successfully.";
        } else {
            $message = "We regret to inform you that your application has been rejected. Please contact support for more information.";
            $stmt = $conn->prepare("INSERT INTO notification (userID, type, message, sentDate, status) VALUES (?, 'Account Rejected', ?, CURDATE(), 'Unread')");
            $stmt->bind_param("is", $userIDToUpdate, $message);
            $stmt->execute();
            $stmt->close();
            $successMsg = "User has been rejected.";
        }
        
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        $errorMsg = "An error occurred. Please try again.";
    }
}


// --- Fetch ALL Pending Professionals who have Paid ---
$query = "
    SELECT
        u.userID, u.Name, u.email, u.contactNo, u.role, u.payment_status, u.profilePhoto,
        t.amount as registration_fee, t.gatewayTransactionID,
        d.specialty as doc_specialty, d.bmdcRegistrationNumber, d.nidNumber as doc_nid_num, d.nidCopyURL as doc_nid_url, d.bmdcCertURL,
        n.specialty as nutri_specialty, n.degree, n.nidNumber as nutri_nid_num, n.nidCopyURL as nutri_nid_url,
        c.careGiverType, c.certifications, c.nidNumber as cg_nid_num, c.nidCopyURL as cg_nid_url, c.certificationURL
    FROM users u
    LEFT JOIN doctor d ON u.userID = d.doctorID
    LEFT JOIN nutritionist n ON u.userID = n.nutritionistID
    LEFT JOIN caregiver c ON u.userID = c.careGiverID
    LEFT JOIN transaction t ON u.userID = t.userID AND t.transactionType = 'Registration Fee'
    WHERE u.verification_status = 'Pending' AND u.payment_status = 'Paid' AND u.role IN ('Doctor', 'Nutritionist', 'CareGiver')
    ORDER BY u.userID DESC
";
$allPendingUsers = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

// --- Separate users into role-based arrays for the tabs ---
$pendingDoctors = [];
$pendingNutritionists = [];
$pendingCaregivers = [];

foreach ($allPendingUsers as $user) {
    if ($user['role'] === 'Doctor') {
        $pendingDoctors[] = $user;
    } elseif ($user['role'] === 'Nutritionist') {
        $pendingNutritionists[] = $user;
    } elseif ($user['role'] === 'CareGiver') {
        $pendingCaregivers[] = $user;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Verification - CarePlus</title>
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
<body class="bg-purple-50">
    <div class="flex min-h-screen">
        <aside class="w-64 bg-white border-r">
            <div class="p-6"><a href="adminDashboard.php" class="text-2xl font-bold text-dark-orchid">CarePlus</a></div>
            <nav class="px-4">
                <a href="adminDashboard.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-table-columns w-5"></i><span>Dashboard</span></a>
                <a href="admin_verification.php" class="flex items-center space-x-3 px-4 py-3 bg-purple-100 text-dark-orchid rounded-lg"><i class="fa-solid fa-user-check w-5"></i><span>Verifications</span></a>
                <a href="user_management.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-users-cog w-5"></i><span>User Management</span></a>
                <a href="admin_transactions.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-money-bill-wave w-5"></i><span>Transactions</span></a>
                <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg mt-8"><i class="fa-solid fa-arrow-right-from-bracket w-5"></i><span>Logout</span></a>
            </nav>
        </aside>

        <main class="flex-1 p-8">
            <header class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-800">Provider Verification</h1>
                    <p class="text-gray-600 mt-1">Review and approve new professional accounts that have completed payment.</p>
                </div>
            </header>

            <?php if ($successMsg): ?><div class="mb-6 p-4 bg-green-100 text-green-800 rounded-md"><?php echo $successMsg; ?></div><?php endif; ?>
            <?php if ($errorMsg): ?><div class="mb-6 p-4 bg-red-100 text-red-800 rounded-md"><?php echo $errorMsg; ?></div><?php endif; ?>

            <div class="bg-white p-6 rounded-xl shadow-lg">
                <div class="border-b border-gray-200">
                    <nav class="flex space-x-8" aria-label="Tabs">
                        <button onclick="openTab('doctors')" class="tab-button active px-1 py-4 text-sm font-medium text-gray-500 hover:text-gray-700">Doctors (<?php echo count($pendingDoctors); ?>)</button>
                        <button onclick="openTab('nutritionists')" class="tab-button px-1 py-4 text-sm font-medium text-gray-500 hover:text-gray-700">Nutritionists (<?php echo count($pendingNutritionists); ?>)</button>
                        <button onclick="openTab('caregivers')" class="tab-button px-1 py-4 text-sm font-medium text-gray-500 hover:text-gray-700">Caregivers (<?php echo count($pendingCaregivers); ?>)</button>
                    </nav>
                </div>

                <div id="doctors-panel" class="tab-panel">
                    <?php echo generateTable($pendingDoctors); ?>
                </div>
                <div id="nutritionists-panel" class="tab-panel hidden">
                    <?php echo generateTable($pendingNutritionists); ?>
                </div>
                <div id="caregivers-panel" class="tab-panel hidden">
                    <?php echo generateTable($pendingCaregivers); ?>
                </div>
            </div>
        </main>
    </div>

    <div id="detailsModal" class="fixed inset-0 bg-black bg-opacity-60 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl max-h-[90vh] flex flex-col">
            <div class="p-6 border-b flex justify-between items-center">
                <h3 class="text-2xl font-bold text-slate-800">Verification Details</h3>
                <button onclick="closeDetailsModal()" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <div class="p-6 overflow-y-auto space-y-6">
                <div id="personal-info"></div>
                <div id="professional-info"></div>
                <div id="documents-info"></div>
                <div id="payment-info"></div>
            </div>
            <div class="bg-gray-100 p-4 flex justify-end space-x-3">
                <button type="button" onclick="closeDetailsModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancel</button>
                <form method="POST" id="verificationForm" class="inline-flex space-x-3">
                    <input type="hidden" name="user_id_to_verify" id="modal_user_id">
                    <button type="submit" name="action" value="Reject" class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 font-semibold">Reject Application</button>
                    <button type="submit" name="action" value="Approve" class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 font-semibold">Approve Application</button>
                </form>
            </div>
        </div>
    </div>
    
    <?php
    // Helper function to generate tables for each tab
    function generateTable($users) {
        if (empty($users)) {
            return '<p class="text-center py-10 text-gray-500">No new applications in this category.</p>';
        }
        $html = '<div class="overflow-x-auto"><table class="w-full text-sm text-left"><thead class="text-xs text-gray-700 uppercase bg-gray-50"><tr><th class="px-6 py-3">Name</th><th class="px-6 py-3">Email</th><th class="px-6 py-3 text-center">Action</th></tr></thead><tbody>';
        foreach ($users as $user) {
            $jsonData = htmlspecialchars(json_encode($user), ENT_QUOTES, 'UTF-8');
            $html .= '<tr class="border-b hover:bg-gray-50">';
            $html .= '<td class="px-6 py-4 font-semibold">' . htmlspecialchars($user['Name']) . '</td>';
            $html .= '<td class="px-6 py-4">' . htmlspecialchars($user['email']) . '</td>';
            $html .= '<td class="px-6 py-4 text-center"><button onclick=\'openDetailsModal(' . $jsonData . ')\' class="px-3 py-1 bg-blue-500 text-white rounded-md hover:bg-blue-600 text-xs font-semibold">View Details</button></td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table></div>';
        return $html;
    }
    ?>

    <script>
        const detailsModal = document.getElementById('detailsModal');
        function openTab(tabName) {
            document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.add('hidden'));
            document.querySelectorAll('.tab-button').forEach(button => button.classList.remove('active'));
            document.getElementById(tabName + '-panel').classList.remove('hidden');
            document.querySelector(`button[onclick="openTab('${tabName}')"]`).classList.add('active');
        }

        function closeDetailsModal() {
            detailsModal.classList.add('hidden');
        }

        function openDetailsModal(user) {
            let personalInfoHtml = `<h4 class="font-bold text-lg text-gray-700 border-b pb-2 mb-3">Personal Information</h4><div class="grid grid-cols-2 gap-4 text-sm"><p><strong>Name:</strong> ${user.Name}</p><p><strong>Role:</strong> ${user.role}</p><p><strong>Email:</strong> ${user.email}</p><p><strong>Contact No:</strong> ${user.contactNo || 'N/A'}</p></div>`;
            document.getElementById('personal-info').innerHTML = personalInfoHtml;

            let professionalInfoHtml = `<h4 class="font-bold text-lg text-gray-700 border-b pb-2 mb-3">Professional Credentials</h4><div class="grid grid-cols-2 gap-4 text-sm">`;
            if(user.role === 'Doctor') {
                professionalInfoHtml += `<p><strong>Specialty:</strong> ${user.doc_specialty || 'N/A'}</p><p><strong>BMDC Number:</strong> ${user.bmdcRegistrationNumber || 'N/A'}</p><p><strong>NID Number:</strong> ${user.doc_nid_num || 'N/A'}</p>`;
            } else if(user.role === 'Nutritionist') {
                professionalInfoHtml += `<p><strong>Specialty:</strong> ${user.nutri_specialty || 'N/A'}</p><p><strong>Degree:</strong> ${user.degree || 'N/A'}</p><p><strong>NID Number:</strong> ${user.nutri_nid_num || 'N/A'}</p>`;
            } else if(user.role === 'CareGiver') {
                professionalInfoHtml += `<p><strong>Type:</strong> ${user.careGiverType || 'N/A'}</p><p><strong>Certifications:</strong> ${user.certifications || 'N/A'}</p><p><strong>NID Number:</strong> ${user.cg_nid_num || 'N/A'}</p>`;
            }
            professionalInfoHtml += `</div>`;
            document.getElementById('professional-info').innerHTML = professionalInfoHtml;
            
            let documentsHtml = `<h4 class="font-bold text-lg text-gray-700 border-b pb-2 mb-3">Uploaded Documents</h4><div class="flex space-x-4">`;
            let nidUrl = user.doc_nid_url || user.nutri_nid_url || user.cg_nid_url;
            let licenseUrl = user.bmdcCertURL || user.certificationURL;
            if(nidUrl) {
                documentsHtml += `<a href="${nidUrl}" target="_blank" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">View NID</a>`;
            } else {
                documentsHtml += `<span class="px-4 py-2 bg-gray-100 text-gray-400 rounded-md">NID Not Provided</span>`;
            }
            if(licenseUrl) {
                documentsHtml += `<a href="${licenseUrl}" target="_blank" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">View License/Certificate</a>`;
            } else {
                 documentsHtml += `<span class="px-4 py-2 bg-gray-100 text-gray-400 rounded-md">License Not Provided</span>`;
            }
            documentsHtml += `</div>`;
            document.getElementById('documents-info').innerHTML = documentsHtml;

            let paymentInfoHtml = `<h4 class="font-bold text-lg text-gray-700 border-b pb-2 mb-3">Payment Details</h4><div class="grid grid-cols-2 gap-4 text-sm"><p><strong>Status:</strong> <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">${user.payment_status}</span></p><p><strong>Amount Paid:</strong> ৳${Number(user.registration_fee).toFixed(2)}</p><p><strong>Transaction ID:</strong> ${user.gatewayTransactionID || 'N/A'}</p></div>`;
            document.getElementById('payment-info').innerHTML = paymentInfoHtml;
            
            document.getElementById('modal_user_id').value = user.userID;
            
            detailsModal.classList.remove('hidden');
        }
    </script>
</body>
</html>