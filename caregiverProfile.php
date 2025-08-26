<?php
session_start();

// Protect this page: allow only logged-in CareGivers
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'CareGiver') {
    header("Location: login.php");
    exit();
}

// --- Database Connection ---
$conn = require_once 'config.php';

$userName = $_SESSION['Name'];
$userID = $_SESSION['userID'];
$userAvatar = strtoupper(substr($userName, 0, 2));

$successMsg = "";
$errorMsg = "";

// Helper function to handle file uploads securely
function handleFileUpload($fileInputName, $uploadSubDir) {
    if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]["error"] === UPLOAD_ERR_OK) {
        $uploadDir = "uploads/" . $uploadSubDir . "/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        $fileType = $_FILES[$fileInputName]['type'];
        $fileSize = $_FILES[$fileInputName]['size'];
        $fileName = $_FILES[$fileInputName]['name'];
        
        // Validate file type
        if (!in_array($fileType, $allowedTypes)) {
            return ['error' => 'Only JPG, PNG, WebP, GIF, and PDF files are allowed.'];
        }
        
        // Validate file size
        if ($fileSize > $maxSize) {
            return ['error' => 'File size must be less than 5MB.'];
        }
        
        $filename = time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($fileName));
        $uploadPath = $uploadDir . $filename;

        if (move_uploaded_file($_FILES[$fileInputName]["tmp_name"], $uploadPath)) {
            return ['success' => $uploadPath];
        } else {
            return ['error' => 'Failed to upload file.'];
        }
    }
    return null;
}

// Handle form submission for profile update
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $updateType = $_POST['update_type'] ?? 'personal';
    
    if ($updateType === 'personal') {
        // Handle personal information update
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $contactNo = trim($_POST['contactNo']);
        $photoPath = null;
        $photoUploadError = null;
        
        // Handle photo removal
        $removePhoto = isset($_POST['removePhoto']) && $_POST['removePhoto'] === '1';
        
        // Handle photo upload
        if (isset($_FILES['profilePhoto']) && $_FILES['profilePhoto']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/';
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $maxSize = 5 * 1024 * 1024; // 5MB
            
            $fileType = $_FILES['profilePhoto']['type'];
            $fileSize = $_FILES['profilePhoto']['size'];
            $fileName = $_FILES['profilePhoto']['name'];
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            // Validate file type
            if (!in_array($fileType, $allowedTypes)) {
                $photoUploadError = "Only JPG, PNG, WebP, and GIF files are allowed.";
            }
            // Validate file size
            elseif ($fileSize > $maxSize) {
                $photoUploadError = "File size must be less than 5MB.";
            }
            else {
                // Generate unique filename
                $uniqueName = "profile_" . $userID . "_" . time() . "." . $fileExt;
                $photoPath = $uploadDir . $uniqueName;
                
                if (!move_uploaded_file($_FILES['profilePhoto']['tmp_name'], $photoPath)) {
                    $photoUploadError = "Failed to upload photo.";
                    $photoPath = null;
                }
            }
        }
        
        if (empty($photoUploadError)) {
            try {
                $conn->begin_transaction();
                
                // Update users table
                if ($removePhoto) {
                    $sql = "UPDATE users SET Name=?, email=?, contactNo=?, profilePhoto=NULL WHERE userID=?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sssi", $name, $email, $contactNo, $userID);
                } elseif ($photoPath) {
                    $sql = "UPDATE users SET Name=?, email=?, contactNo=?, profilePhoto=? WHERE userID=?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssssi", $name, $email, $contactNo, $photoPath, $userID);
                } else {
                    $sql = "UPDATE users SET Name=?, email=?, contactNo=? WHERE userID=?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sssi", $name, $email, $contactNo, $userID);
                }
                
                if ($stmt->execute()) {
                    $_SESSION['Name'] = $name;
                    $conn->commit();
                    $successMsg = "Personal information updated successfully!";
                } else {
                    throw new Exception("Failed to update personal information.");
                }
                
            } catch (Exception $e) {
                $conn->rollback();
                $errorMsg = "Error: " . $e->getMessage();
            }
        } else {
            $errorMsg = $photoUploadError;
        }
        
    } elseif ($updateType === 'professional') {
        // Handle professional information update
        $careGiverType = trim($_POST['careGiverType']);
        $certifications = trim($_POST['certifications']);
        $dailyRate = floatval($_POST['dailyRate']);
        $weeklyRate = floatval($_POST['weeklyRate']);
        $monthlyRate = floatval($_POST['monthlyRate']);
        $nidNumber = trim($_POST['nidNumber']);
        
        $nidCopyResult = handleFileUpload('nidCopyURL', 'caregiver_documents');
        $certificationResult = handleFileUpload('certificationURL', 'caregiver_documents');
        
        // Check for upload errors
        $uploadError = null;
        if ($nidCopyResult && isset($nidCopyResult['error'])) {
            $uploadError = "NID Copy: " . $nidCopyResult['error'];
        }
        if ($certificationResult && isset($certificationResult['error'])) {
            $uploadError = ($uploadError ? $uploadError . " | " : "") . "Certification: " . $certificationResult['error'];
        }
        
        if (!$uploadError) {
            try {
                $conn->begin_transaction();
                
                // Check if caregiver record exists
                $checkSql = "SELECT careGiverID FROM caregiver WHERE careGiverID = ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("i", $userID);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($result->num_rows > 0) {
                    // Update existing record
                    $updateSql = "UPDATE caregiver SET careGiverType=?, certifications=?, dailyRate=?, weeklyRate=?, monthlyRate=?, nidNumber=?";
                    $params = [$careGiverType, $certifications, $dailyRate, $weeklyRate, $monthlyRate, $nidNumber];
                    $types = "ssddds";
                    
                    if ($nidCopyResult && isset($nidCopyResult['success'])) {
                        $updateSql .= ", nidCopyURL=?";
                        $params[] = $nidCopyResult['success'];
                        $types .= "s";
                    }
                    
                    if ($certificationResult && isset($certificationResult['success'])) {
                        $updateSql .= ", certificationURL=?";
                        $params[] = $certificationResult['success'];
                        $types .= "s";
                    }
                    
                    $updateSql .= " WHERE careGiverID=?";
                    $params[] = $userID;
                    $types .= "i";
                    
                    $stmt = $conn->prepare($updateSql);
                    $stmt->bind_param($types, ...$params);
                } else {
                    // Insert new record
                    $insertSql = "INSERT INTO caregiver (careGiverID, careGiverType, certifications, dailyRate, weeklyRate, monthlyRate, nidNumber, nidCopyURL, certificationURL) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $nidCopyPath = $nidCopyResult ? $nidCopyResult['success'] : null;
                    $certificationPath = $certificationResult ? $certificationResult['success'] : null;
                    
                    $stmt = $conn->prepare($insertSql);
                    $stmt->bind_param("issdddss", $userID, $careGiverType, $certifications, $dailyRate, $weeklyRate, $monthlyRate, $nidNumber, $nidCopyPath, $certificationPath);
                }
                
                if ($stmt->execute()) {
                    $conn->commit();
                    $successMsg = "Professional information updated successfully!";
                } else {
                    throw new Exception("Failed to update professional information.");
                }
                
            } catch (Exception $e) {
                $conn->rollback();
                $errorMsg = "Error: " . $e->getMessage();
            }
        } else {
            $errorMsg = $uploadError;
        }
    }
}

// Fetch current user data
$sql = "SELECT u.*, c.careGiverType, c.certifications, c.dailyRate, c.weeklyRate, c.monthlyRate, c.nidNumber, c.nidCopyURL, c.certificationURL 
        FROM users u 
        LEFT JOIN caregiver c ON u.userID = c.careGiverID 
        WHERE u.userID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();

if (!$userData) {
    $errorMsg = "User data not found.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caregiver Profile - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-dark-orchid { background-color: #9932CC; }
        .text-dark-orchid { color: #9932CC; }
        .border-dark-orchid { border-color: #9932CC; }
        .hover\:bg-dark-orchid:hover { background-color: #9932CC; }
        .focus\:border-dark-orchid:focus { border-color: #9932CC; }
        .focus\:ring-dark-orchid:focus { ring-color: #9932CC; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <h1 class="text-2xl font-bold text-dark-orchid">CarePlus</h1>
                    <span class="ml-2 text-gray-600">Caregiver Profile</span>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-700">Welcome, <?php echo htmlspecialchars($userName); ?></span>
                    <a href="careGiverDashboard.php" class="bg-dark-orchid text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition">
                        <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                    </a>
                    <a href="logout.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <!-- Success/Error Messages -->
        <?php if ($successMsg): ?>
            <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo htmlspecialchars($successMsg); ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMsg): ?>
            <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo htmlspecialchars($errorMsg); ?>
            </div>
        <?php endif; ?>

        <!-- Profile Header -->
        <div class="bg-white rounded-lg shadow-sm mb-6 p-6">
            <div class="flex items-center space-x-4">
                <div class="flex-shrink-0">
                    <?php if ($userData['profilePhoto']): ?>
                        <img class="h-20 w-20 rounded-full object-cover" src="<?php echo htmlspecialchars($userData['profilePhoto']); ?>" alt="Profile">
                    <?php else: ?>
                        <div class="h-20 w-20 rounded-full bg-dark-orchid text-white flex items-center justify-center text-2xl font-bold">
                            <?php echo $userAvatar; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($userData['Name']); ?></h1>
                    <p class="text-gray-600"><?php echo htmlspecialchars($userData['careGiverType'] ?? 'Caregiver'); ?></p>
                    <p class="text-sm text-gray-500">
                        <i class="fas fa-envelope mr-1"></i>
                        <?php echo htmlspecialchars($userData['email']); ?>
                    </p>
                    <?php if ($userData['contactNo']): ?>
                        <p class="text-sm text-gray-500">
                            <i class="fas fa-phone mr-1"></i>
                            <?php echo htmlspecialchars($userData['contactNo']); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="bg-white rounded-lg shadow-sm mb-6">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex">
                    <button onclick="showTab('personal')" id="personal-tab" class="tab-button py-4 px-6 border-b-2 border-dark-orchid text-dark-orchid font-medium">
                        <i class="fas fa-user mr-2"></i>Personal Information
                    </button>
                    <button onclick="showTab('professional')" id="professional-tab" class="tab-button py-4 px-6 border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                        <i class="fas fa-briefcase mr-2"></i>Professional Information
                    </button>
                </nav>
            </div>

            <!-- Personal Information Tab -->
            <div id="personal-content" class="tab-content p-6">
                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    <input type="hidden" name="update_type" value="personal">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($userData['Name']); ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-dark-orchid focus:border-dark-orchid" required>
                        </div>
                        
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address *</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($userData['email']); ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-dark-orchid focus:border-dark-orchid" required>
                        </div>
                        
                        <div>
                            <label for="contactNo" class="block text-sm font-medium text-gray-700 mb-2">Contact Number</label>
                            <input type="tel" id="contactNo" name="contactNo" value="<?php echo htmlspecialchars($userData['contactNo'] ?? ''); ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-dark-orchid focus:border-dark-orchid">
                        </div>
                        
                        <div>
                            <label for="profilePhoto" class="block text-sm font-medium text-gray-700 mb-2">Profile Photo</label>
                            <input type="file" id="profilePhoto" name="profilePhoto" accept="image/*" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-dark-orchid focus:border-dark-orchid">
                            <p class="text-xs text-gray-500 mt-1">JPG, PNG, WebP, GIF (max 5MB)</p>
                            <?php if ($userData['profilePhoto']): ?>
                                <div class="mt-2">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="removePhoto" value="1" class="mr-2">
                                        <span class="text-sm text-red-600">Remove current photo</span>
                                    </label>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="submit" class="bg-dark-orchid text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition">
                            <i class="fas fa-save mr-2"></i>Update Personal Information
                        </button>
                    </div>
                </form>
            </div>

            <!-- Professional Information Tab -->
            <div id="professional-content" class="tab-content p-6 hidden">
                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    <input type="hidden" name="update_type" value="professional">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="careGiverType" class="block text-sm font-medium text-gray-700 mb-2">Caregiver Type *</label>
                            <select id="careGiverType" name="careGiverType" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-dark-orchid focus:border-dark-orchid" required>
                                <option value="">Select Type</option>
                                <option value="Nurse" <?php echo ($userData['careGiverType'] === 'Nurse') ? 'selected' : ''; ?>>Nurse</option>
                                <option value="Home Health Aide" <?php echo ($userData['careGiverType'] === 'Home Health Aide') ? 'selected' : ''; ?>>Home Health Aide</option>
                                <option value="Personal Care Assistant" <?php echo ($userData['careGiverType'] === 'Personal Care Assistant') ? 'selected' : ''; ?>>Personal Care Assistant</option>
                                <option value="Physiotherapist" <?php echo ($userData['careGiverType'] === 'Physiotherapist') ? 'selected' : ''; ?>>Physiotherapist</option>
                                <option value="Occupational Therapist" <?php echo ($userData['careGiverType'] === 'Occupational Therapist') ? 'selected' : ''; ?>>Occupational Therapist</option>
                                <option value="Companion Care" <?php echo ($userData['careGiverType'] === 'Companion Care') ? 'selected' : ''; ?>>Companion Care</option>
                                <option value="Specialized Care" <?php echo ($userData['careGiverType'] === 'Specialized Care') ? 'selected' : ''; ?>>Specialized Care</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="nidNumber" class="block text-sm font-medium text-gray-700 mb-2">National ID Number *</label>
                            <input type="text" id="nidNumber" name="nidNumber" value="<?php echo htmlspecialchars($userData['nidNumber'] ?? ''); ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-dark-orchid focus:border-dark-orchid" required>
                        </div>
                        
                        <div class="md:col-span-2">
                            <label for="certifications" class="block text-sm font-medium text-gray-700 mb-2">Certifications & Qualifications</label>
                            <textarea id="certifications" name="certifications" rows="3" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-dark-orchid focus:border-dark-orchid"
                                      placeholder="List your certifications, licenses, and qualifications..."><?php echo htmlspecialchars($userData['certifications'] ?? ''); ?></textarea>
                        </div>
                        
                        <div>
                            <label for="dailyRate" class="block text-sm font-medium text-gray-700 mb-2">Daily Rate (BDT)</label>
                            <input type="number" id="dailyRate" name="dailyRate" step="0.01" min="0" 
                                   value="<?php echo $userData['dailyRate'] ?? ''; ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-dark-orchid focus:border-dark-orchid">
                        </div>
                        
                        <div>
                            <label for="weeklyRate" class="block text-sm font-medium text-gray-700 mb-2">Weekly Rate (BDT)</label>
                            <input type="number" id="weeklyRate" name="weeklyRate" step="0.01" min="0" 
                                   value="<?php echo $userData['weeklyRate'] ?? ''; ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-dark-orchid focus:border-dark-orchid">
                        </div>
                        
                        <div>
                            <label for="monthlyRate" class="block text-sm font-medium text-gray-700 mb-2">Monthly Rate (BDT)</label>
                            <input type="number" id="monthlyRate" name="monthlyRate" step="0.01" min="0" 
                                   value="<?php echo $userData['monthlyRate'] ?? ''; ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-dark-orchid focus:border-dark-orchid">
                        </div>
                        
                        <div>
                            <label for="nidCopyURL" class="block text-sm font-medium text-gray-700 mb-2">National ID Copy</label>
                            <input type="file" id="nidCopyURL" name="nidCopyURL" accept="image/*,application/pdf" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-dark-orchid focus:border-dark-orchid">
                            <p class="text-xs text-gray-500 mt-1">Image or PDF (max 5MB)</p>
                            <?php if ($userData['nidCopyURL']): ?>
                                <p class="text-xs text-green-600 mt-1">
                                    <i class="fas fa-check mr-1"></i>Current file: <?php echo basename($userData['nidCopyURL']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <label for="certificationURL" class="block text-sm font-medium text-gray-700 mb-2">Certification Documents</label>
                            <input type="file" id="certificationURL" name="certificationURL" accept="image/*,application/pdf" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-dark-orchid focus:border-dark-orchid">
                            <p class="text-xs text-gray-500 mt-1">Image or PDF (max 5MB)</p>
                            <?php if ($userData['certificationURL']): ?>
                                <p class="text-xs text-green-600 mt-1">
                                    <i class="fas fa-check mr-1"></i>Current file: <?php echo basename($userData['certificationURL']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="submit" class="bg-dark-orchid text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition">
                            <i class="fas fa-save mr-2"></i>Update Professional Information
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        function showTab(tabName) {
            // Hide all tab contents
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(content => content.classList.add('hidden'));
            
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.tab-button');
            tabs.forEach(tab => {
                tab.classList.remove('border-dark-orchid', 'text-dark-orchid');
                tab.classList.add('border-transparent', 'text-gray-500');
            });
            
            // Show selected tab content
            document.getElementById(tabName + '-content').classList.remove('hidden');
            
            // Add active class to selected tab
            const activeTab = document.getElementById(tabName + '-tab');
            activeTab.classList.remove('border-transparent', 'text-gray-500');
            activeTab.classList.add('border-dark-orchid', 'text-dark-orchid');
        }

        // Rate calculation helpers
        document.getElementById('dailyRate').addEventListener('input', function() {
            const daily = parseFloat(this.value) || 0;
            if (daily > 0) {
                document.getElementById('weeklyRate').value = (daily * 7).toFixed(2);
                document.getElementById('monthlyRate').value = (daily * 30).toFixed(2);
            }
        });

        document.getElementById('weeklyRate').addEventListener('input', function() {
            const weekly = parseFloat(this.value) || 0;
            if (weekly > 0) {
                document.getElementById('dailyRate').value = (weekly / 7).toFixed(2);
                document.getElementById('monthlyRate').value = (weekly * 4.29).toFixed(2);
            }
        });

        document.getElementById('monthlyRate').addEventListener('input', function() {
            const monthly = parseFloat(this.value) || 0;
            if (monthly > 0) {
                document.getElementById('dailyRate').value = (monthly / 30).toFixed(2);
                document.getElementById('weeklyRate').value = (monthly / 4.29).toFixed(2);
            }
        });
    </script>
</body>
</html>
