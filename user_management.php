<?php
session_start();
$conn = require_once 'config.php';

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

// Display success/error message from redirect
if (isset($_SESSION['message'])) {
    if ($_SESSION['message_type'] === 'success') {
        $successMsg = $_SESSION['message'];
    } else {
        $errorMsg = $_SESSION['message'];
    }
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// --- Handle User Deletion ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $userIDToDelete = (int)$_POST['user_id'];

    if ($userIDToDelete === $_SESSION['userID']) {
        $errorMsg = "You cannot delete your own account.";
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE userID = ?");
        $stmt->bind_param("i", $userIDToDelete);
        if ($stmt->execute()) {
            $successMsg = "User deleted successfully.";
        } else {
            $errorMsg = "Failed to delete user.";
        }
        $stmt->close();
    }
}

// --- FIXED: Fetch only 'Approved' users ---
$allUsersQuery = "SELECT userID, Name, email, contactNo, role FROM users WHERE verification_status = 'Approved' ORDER BY userID DESC";
$allUsers = $conn->query($allUsersQuery)->fetch_all(MYSQLI_ASSOC);

$usersByRole = [ 'Doctor' => [], 'Patient' => [], 'Nutritionist' => [], 'CareGiver' => [], 'Admin' => [] ];
foreach ($allUsers as $user) {
    if (array_key_exists($user['role'], $usersByRole)) {
        $usersByRole[$user['role']][] = $user;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Management - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style> body { font-family: 'Inter', sans-serif; } .bg-dark-orchid { background-color: #9932CC; } </style>
</head>
<body class="bg-purple-50">
    <div class="flex min-h-screen">
        <aside class="w-64 bg-white border-r">
             <div class="p-6"><a href="adminDashboard.php" class="text-2xl font-bold text-dark-orchid">CarePlus</a></div>
            <nav class="px-4">
                <a href="adminDashboard.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-table-columns w-5"></i><span>Dashboard</span></a>
                <a href="admin_verification.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-user-check w-5"></i><span>Verifications</span></a>
                <a href="user_management.php" class="flex items-center space-x-3 px-4 py-3 bg-purple-100 text-dark-orchid rounded-lg"><i class="fa-solid fa-users-cog w-5"></i><span>User Management</span></a>
                <a href="admin_transactions.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-money-bill-wave w-5"></i><span>Transactions</span></a>
                <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg mt-8"><i class="fa-solid fa-arrow-right-from-bracket w-5"></i><span>Logout</span></a>
            </nav>
        </aside>
        <main class="flex-1 p-8">
            <header class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-800">User Management</h1>
                    <p class="text-gray-600 mt-1">View and manage all approved users on the platform.</p>
                </div>
            </header>

            <?php if ($successMsg): ?><div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6"><p><?php echo $successMsg; ?></p></div><?php endif; ?>
            <?php if ($errorMsg): ?><div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6"><p><?php echo $errorMsg; ?></p></div><?php endif; ?>

            <div class="bg-white rounded-xl shadow-lg">
                <div class="border-b border-gray-200">
                    <nav id="userTabs" class="-mb-px flex space-x-8 px-6" aria-label="Tabs">
                        <button onclick="showTab('all')" class="tab-button border-purple-500 text-purple-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">All Users</button>
                        <button onclick="showTab('doctor')" class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Doctors</button>
                        <button onclick="showTab('patient')" class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Patients</button>
                        <button onclick="showTab('nutritionist')" class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Nutritionists</button>
                        <button onclick="showTab('caregiver')" class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">CareGivers</button>
                        <button onclick="showTab('admin')" class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Admins</button>
                    </nav>
                </div>
                <div class="p-6">
                    <input type="text" id="searchInput" placeholder="Search by name or email in the current tab..." class="w-full p-2 border border-gray-300 rounded-md mb-4">
                    
                    <?php function render_user_table($users, $role, $currentUserID) { ?>
                        <div id="tab-content-<?php echo strtolower($role); ?>" class="tab-content overflow-x-auto">
                             <table class="w-full text-sm text-left text-gray-500">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3">ID</th><th class="px-6 py-3">Name</th><th class="px-6 py-3">Email</th>
                                        <th class="px-6 py-3">Contact</th><th class="px-6 py-3">Role</th><th class="px-6 py-3 text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($users)): ?>
                                        <tr><td colspan="6" class="text-center py-10 text-gray-500">No approved users found in this category.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($users as $user): ?>
                                        <tr class="user-row bg-white border-b">
                                            <td class="px-6 py-4">#<?php echo $user['userID']; ?></td>
                                            <td class="px-6 py-4 user-name"><?php echo htmlspecialchars($user['Name']); ?></td>
                                            <td class="px-6 py-4 user-email"><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td class="px-6 py-4"><?php echo htmlspecialchars($user['contactNo']); ?></td>
                                            <td class="px-6 py-4"><span class="px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800"><?php echo htmlspecialchars($user['role']); ?></span></td>
                                            <td class="px-6 py-4 text-center space-x-4">
                                                <a href="admin_edit_user.php?user_id=<?php echo $user['userID']; ?>" class="text-blue-500 hover:text-blue-700" title="Edit User"><i class="fa-solid fa-pencil"></i></a>
                                                
                                                <?php if ($user['userID'] !== $currentUserID): ?>
                                                <form method="POST" action="user_management.php" onsubmit="return confirm('Are you sure you want to delete this user? This is permanent.');" class="inline-block">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['userID']; ?>">
                                                    <button type="submit" name="delete_user" class="text-red-500 hover:text-red-700" title="Delete User"><i class="fa-solid fa-trash-can"></i></button>
                                                </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php } ?>
                    
                    <?php render_user_table($allUsers, 'all', $_SESSION['userID']); ?>
                    <?php render_user_table($usersByRole['Doctor'], 'doctor', $_SESSION['userID']); ?>
                    <?php render_user_table($usersByRole['Patient'], 'patient', $_SESSION['userID']); ?>
                    <?php render_user_table($usersByRole['Nutritionist'], 'nutritionist', $_SESSION['userID']); ?>
                    <?php render_user_table($usersByRole['CareGiver'], 'caregiver', $_SESSION['userID']); ?>
                    <?php render_user_table($usersByRole['Admin'], 'admin', $_SESSION['userID']); ?>
                </div>
            </div>
        </main>
    </div>
    
  <script>
      const tabs = document.querySelectorAll('.tab-button');
      const contents = document.querySelectorAll('.tab-content');
      const searchInput = document.getElementById('searchInput');
      let activeTab = 'all';

      function showTab(role) {
          activeTab = role.toLowerCase();
          tabs.forEach(tab => {
              if (tab.getAttribute('onclick') === `showTab('${role}')`) {
                  tab.classList.add('border-purple-500', 'text-purple-600');
                  tab.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
              } else {
                  tab.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
                  tab.classList.remove('border-purple-500', 'text-purple-600');
              }
          });
          contents.forEach(content => {
              content.style.display = content.id === `tab-content-${activeTab}` ? 'block' : 'none';
          });
          searchInput.value = '';
          filterTable();
      }

      function filterTable() {
          const filter = searchInput.value.toUpperCase();
          const activeTable = document.querySelector(`#tab-content-${activeTab} tbody`);
          if (!activeTable) return;
          const rows = activeTable.getElementsByTagName('tr');
          let found = false;
          
          for (let i = 0; i < rows.length; i++) {
              // Ensure we don't try to filter the 'no users found' row
              if (rows[i].getElementsByTagName('td').length < 2) continue;

              const nameCell = rows[i].querySelector('.user-name');
              const emailCell = rows[i].querySelector('.user-email');
              if (nameCell && emailCell) {
                  const textValue = (nameCell.textContent || nameCell.innerText) + " " + (emailCell.textContent || emailCell.innerText);
                  if (textValue.toUpperCase().indexOf(filter) > -1) {
                      rows[i].style.display = '';
                      found = true;
                  } else {
                      rows[i].style.display = 'none';
                  }
              }
          }
      }

      searchInput.addEventListener('keyup', filterTable);

      // Set the initial view
      document.addEventListener('DOMContentLoaded', () => {
          showTab('all');
      });
  </script>
</body>
</html>