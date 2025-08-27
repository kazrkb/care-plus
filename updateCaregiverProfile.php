<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is a caregiver
if (!isset($_SESSION['userID']) || $_SESSION['role'] != 'CareGiver') {
    header("Location: login.php");
    exit();
}

$caregiverID = $_SESSION['userID'];
$message = '';
$messageType = '';

// Handle form submission
if ($_POST) {
    try {
        $conn->begin_transaction();
        
        $uploadErrors = [];
        
        // Update users table
        $updateUserQuery = "UPDATE users SET Name = ?, email = ?, contactNo = ? WHERE userID = ?";
        $userStmt = $conn->prepare($updateUserQuery);
        $userStmt->bind_param("sssi", $_POST['name'], $_POST['email'], $_POST['contactNo'], $caregiverID);
        $userStmt->execute();
        
        // Update caregiver table (excluding NID number as it's fixed)
        $updateCaregiverQuery = "UPDATE caregiver SET 
                                careGiverType = ?, 
                                certifications = ?, 
                                dailyRate = ?, 
                                weeklyRate = ?, 
                                monthlyRate = ?
                                WHERE careGiverID = ?";
        $caregiverStmt = $conn->prepare($updateCaregiverQuery);
        $caregiverStmt->bind_param("ssdddi", 
            $_POST['careGiverType'], 
            $_POST['certifications'], 
            $_POST['dailyRate'], 
            $_POST['weeklyRate'], 
            $_POST['monthlyRate'], 
            $caregiverID
        );
        $caregiverStmt->execute();
        
        // Handle profile photo upload
        if (isset($_FILES['profilePhoto']) && $_FILES['profilePhoto']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Validate file type and size
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
            $fileExtension = strtolower(pathinfo($_FILES['profilePhoto']['name'], PATHINFO_EXTENSION));
            $fileSize = $_FILES['profilePhoto']['size'];
            
            if (!in_array($fileExtension, $allowedTypes)) {
                $uploadErrors[] = "Profile photo must be JPG, JPEG, PNG, or GIF";
            } elseif ($fileSize > 5 * 1024 * 1024) { // 5MB limit
                $uploadErrors[] = "Profile photo must be less than 5MB";
            } else {
                $fileName = 'profile_' . $caregiverID . '_' . time() . '.' . $fileExtension;
                $uploadPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['profilePhoto']['tmp_name'], $uploadPath)) {
                    $updatePhotoQuery = "UPDATE users SET profilePhoto = ? WHERE userID = ?";
                    $photoStmt = $conn->prepare($updatePhotoQuery);
                    $photoStmt->bind_param("si", $uploadPath, $caregiverID);
                    $photoStmt->execute();
                } else {
                    $uploadErrors[] = "Failed to upload profile photo";
                }
            }
        }
        
        // Handle NID copy upload
        if (isset($_FILES['nidCopy']) && $_FILES['nidCopy']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/documents/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf'];
            $fileExtension = strtolower(pathinfo($_FILES['nidCopy']['name'], PATHINFO_EXTENSION));
            $fileSize = $_FILES['nidCopy']['size'];
            
            if (!in_array($fileExtension, $allowedTypes)) {
                $uploadErrors[] = "NID copy must be JPG, JPEG, PNG, or PDF";
            } elseif ($fileSize > 10 * 1024 * 1024) { // 10MB limit for documents
                $uploadErrors[] = "NID copy must be less than 10MB";
            } else {
                $fileName = 'nid_' . $caregiverID . '_' . time() . '.' . $fileExtension;
                $uploadPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['nidCopy']['tmp_name'], $uploadPath)) {
                    $updateNidQuery = "UPDATE caregiver SET nidCopyURL = ? WHERE careGiverID = ?";
                    $nidStmt = $conn->prepare($updateNidQuery);
                    $nidStmt->bind_param("si", $uploadPath, $caregiverID);
                    $nidStmt->execute();
                } else {
                    $uploadErrors[] = "Failed to upload NID copy";
                }
            }
        }
        
        // Handle certification documents upload
        if (isset($_FILES['certificationDoc']) && $_FILES['certificationDoc']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/certificates/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf'];
            $fileExtension = strtolower(pathinfo($_FILES['certificationDoc']['name'], PATHINFO_EXTENSION));
            $fileSize = $_FILES['certificationDoc']['size'];
            
            if (!in_array($fileExtension, $allowedTypes)) {
                $uploadErrors[] = "Certificate document must be JPG, JPEG, PNG, or PDF";
            } elseif ($fileSize > 10 * 1024 * 1024) { // 10MB limit for documents
                $uploadErrors[] = "Certificate document must be less than 10MB";
            } else {
                $fileName = 'cert_' . $caregiverID . '_' . time() . '.' . $fileExtension;
                $uploadPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['certificationDoc']['tmp_name'], $uploadPath)) {
                    $updateCertQuery = "UPDATE caregiver SET certificationURL = ? WHERE careGiverID = ?";
                    $certStmt = $conn->prepare($updateCertQuery);
                    $certStmt->bind_param("si", $uploadPath, $caregiverID);
                    $certStmt->execute();
                } else {
                    $uploadErrors[] = "Failed to upload certificate document";
                }
            }
        }
        
        $conn->commit();
        
        if (empty($uploadErrors)) {
            $message = "Profile updated successfully!";
            $messageType = "success";
        } else {
            $message = "Profile updated with some issues: " . implode(", ", $uploadErrors);
            $messageType = "warning";
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error updating profile: " . $e->getMessage();
        $messageType = "error";
    }
}

// Fetch current caregiver data
$query = "SELECT u.userID, u.Name, u.email, u.contactNo, u.profilePhoto,
                 c.careGiverType, c.certifications, c.dailyRate, c.weeklyRate, 
                 c.monthlyRate, c.nidNumber, c.nidCopyURL, c.certificationURL
          FROM users u 
          LEFT JOIN caregiver c ON u.userID = c.careGiverID 
          WHERE u.userID = ? AND u.role = 'CareGiver'";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $caregiverID);
$stmt->execute();
$result = $stmt->get_result();
$caregiver = $result->fetch_assoc();

if (!$caregiver) {
    echo "Caregiver profile not found.";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Profile - <?php echo htmlspecialchars($caregiver['Name']); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .update-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header-section {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header-section h1 {
            font-size: 2.2em;
            margin-bottom: 10px;
            font-weight: 300;
        }

        .header-section p {
            font-size: 1.1em;
            opacity: 0.9;
        }

        .form-container {
            padding: 40px;
        }

        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .message.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .form-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            border-left: 5px solid #3498db;
        }

        .section-title {
            font-size: 1.3em;
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: #3498db;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #5a6c7d;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 1em;
            transition: border-color 0.3s ease;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .form-control[readonly] {
            background-color: #f8f9fa;
            color: #6c757d;
        }

        select.form-control {
            cursor: pointer;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .rate-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }

        .photo-upload {
            grid-column: 1 / -1;
            text-align: center;
            padding: 25px;
            background: white;
            border-radius: 15px;
            border: 2px dashed #3498db;
            margin-bottom: 30px;
        }

        .current-photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 15px;
            border: 4px solid #3498db;
        }

        .file-input-wrapper {
            position: relative;
            display: inline-block;
            cursor: pointer;
            background: #3498db;
            color: white;
            padding: 12px 25px;
            border-radius: 25px;
            transition: all 0.3s ease;
            border: 2px solid #3498db;
            font-weight: 600;
            text-align: center;
            width: 100%;
            margin-top: 10px;
        }

        .file-input-wrapper:hover {
            background: #2980b9;
            border-color: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }

        .file-input-wrapper input[type="file"] {
            position: absolute;
            left: -9999px;
            opacity: 0;
        }

        .file-input-wrapper.selected {
            background: #27ae60;
            border-color: #27ae60;
        }

        .file-input-wrapper.selected:hover {
            background: #229954;
            border-color: #229954;
        }

        .action-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-top: 30px;
        }

        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 25px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            min-width: 150px;
            justify-content: center;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .input-group {
            position: relative;
        }

        .input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        .input-group .form-control {
            padding-left: 45px;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .rate-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
                align-items: center;
            }

            .form-container {
                padding: 20px;
            }
        }

        .required {
            color: #e74c3c;
        }

        .help-text {
            font-size: 0.9em;
            color: #6c757d;
            margin-top: 5px;
        }

        .current-document {
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            border-left: 3px solid #28a745;
        }

        .current-document a {
            color: #28a745;
            text-decoration: none;
            font-weight: 500;
        }

        .current-document a:hover {
            text-decoration: underline;
        }

        .current-document i {
            margin-right: 8px;
            color: #28a745;
        }

        .file-preview {
            margin-top: 10px;
            text-align: center;
        }

        .file-preview img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 10px;
            border: 2px solid #3498db;
        }
    </style>
</head>
<body>
    <div class="update-container">
        <!-- Header Section -->
        <div class="header-section">
            <h1><i class="fas fa-user-edit"></i> Update Profile</h1>
            <p>Keep your professional information up to date</p>
        </div>

        <!-- Form Container -->
        <div class="form-container">
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Profile Photo Upload -->
            <div class="photo-upload">
                <img src="<?php echo $caregiver['profilePhoto'] ? htmlspecialchars($caregiver['profilePhoto']) : 'uploads/default-avatar.png'; ?>" 
                     alt="Current Photo" class="current-photo">
                <h3>Profile Photo</h3>
                <p class="help-text">Upload a professional photo (JPG, PNG - Max 5MB)</p>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <!-- Photo Upload Field -->
                <div class="form-group">
                    <div class="file-input-wrapper">
                        <i class="fas fa-camera"></i>
                        Choose New Photo
                        <input type="file" name="profilePhoto" accept="image/*" id="profilePhotoInput">
                    </div>
                </div>

                <!-- Document Upload Section -->
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nidCopy">NID Copy</label>
                        <div class="file-input-wrapper">
                            <i class="fas fa-file-upload"></i>
                            Upload NID Copy
                            <input type="file" name="nidCopy" accept="image/*,.pdf" id="nidCopyInput">
                        </div>
                        <div class="help-text">Upload clear copy of your National ID (PDF or Image)</div>
                        <?php if ($caregiver['nidCopyURL']): ?>
                            <div class="current-document">
                                <i class="fas fa-file"></i>
                                <a href="<?php echo htmlspecialchars($caregiver['nidCopyURL']); ?>" target="_blank">View Current NID Copy</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="certificationDoc">Professional Certificates</label>
                        <div class="file-input-wrapper">
                            <i class="fas fa-certificate"></i>
                            Upload Certificates
                            <input type="file" name="certificationDoc" accept="image/*,.pdf" id="certDocInput">
                        </div>
                        <div class="help-text">Upload your professional certification documents</div>
                        <?php if ($caregiver['certificationURL']): ?>
                            <div class="current-document">
                                <i class="fas fa-certificate"></i>
                                <a href="<?php echo htmlspecialchars($caregiver['certificationURL']); ?>" target="_blank">View Current Certificates</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-grid">
                    <!-- Personal Information -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-user"></i>
                            Personal Information
                        </div>

                        <div class="form-group">
                            <label for="name">Full Name <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-user"></i>
                                <input type="text" id="name" name="name" class="form-control" 
                                       value="<?php echo htmlspecialchars($caregiver['Name']); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-envelope"></i>
                                <input type="email" id="email" name="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($caregiver['email']); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="contactNo">Contact Number <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-phone"></i>
                                <input type="tel" id="contactNo" name="contactNo" class="form-control" 
                                       value="<?php echo htmlspecialchars($caregiver['contactNo'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="nidNumber">NID Number</label>
                            <div class="input-group">
                                <i class="fas fa-id-card"></i>
                                <input type="text" id="nidNumber" name="nidNumber" class="form-control" 
                                       value="<?php echo htmlspecialchars($caregiver['nidNumber'] ?? ''); ?>" readonly>
                            </div>
                            <div class="help-text">NID number cannot be changed for security reasons</div>
                        </div>
                    </div>

                    <!-- Professional Information -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-stethoscope"></i>
                            Professional Information
                        </div>

                        <div class="form-group">
                            <label for="careGiverType">Professional Type <span class="required">*</span></label>
                            <select id="careGiverType" name="careGiverType" class="form-control" required>
                                <option value="">Select Type</option>
                                <option value="Nurse" <?php echo $caregiver['careGiverType'] === 'Nurse' ? 'selected' : ''; ?>>Nurse</option>
                                <option value="Physiotherapist" <?php echo $caregiver['careGiverType'] === 'Physiotherapist' ? 'selected' : ''; ?>>Physiotherapist</option>
                                <option value="Home Care Assistant" <?php echo $caregiver['careGiverType'] === 'Home Care Assistant' ? 'selected' : ''; ?>>Home Care Assistant</option>
                                <option value="Medical Attendant" <?php echo $caregiver['careGiverType'] === 'Medical Attendant' ? 'selected' : ''; ?>>Medical Attendant</option>
                                <option value="Elder Care Specialist" <?php echo $caregiver['careGiverType'] === 'Elder Care Specialist' ? 'selected' : ''; ?>>Elder Care Specialist</option>
                                <option value="Rehabilitation Specialist" <?php echo $caregiver['careGiverType'] === 'Rehabilitation Specialist' ? 'selected' : ''; ?>>Rehabilitation Specialist</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="certifications">Certifications & Qualifications</label>
                            <textarea id="certifications" name="certifications" class="form-control" 
                                      placeholder="Enter your certifications, separated by commas"><?php echo htmlspecialchars($caregiver['certifications'] ?? ''); ?></textarea>
                            <div class="help-text">List your professional certifications, licenses, and qualifications</div>
                        </div>
                    </div>
                </div>

                <!-- Service Rates Section -->
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-dollar-sign"></i>
                        Service Rates (BDT)
                    </div>
                    <div class="rate-grid">
                        <div class="form-group">
                            <label for="dailyRate">Daily Rate <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-money-bill"></i>
                                <input type="number" id="dailyRate" name="dailyRate" class="form-control" 
                                       value="<?php echo $caregiver['dailyRate'] ?? ''; ?>" min="0" step="0.01" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="weeklyRate">Weekly Rate <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-money-bill"></i>
                                <input type="number" id="weeklyRate" name="weeklyRate" class="form-control" 
                                       value="<?php echo $caregiver['weeklyRate'] ?? ''; ?>" min="0" step="0.01" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="monthlyRate">Monthly Rate <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-money-bill"></i>
                                <input type="number" id="monthlyRate" name="monthlyRate" class="form-control" 
                                       value="<?php echo $caregiver['monthlyRate'] ?? ''; ?>" min="0" step="0.01" required>
                            </div>
                        </div>
                    </div>
                    <div class="help-text">Set competitive rates for your services. These will be visible to patients.</div>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Update Profile
                    </button>
                    <a href="caregiverProfile.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // File input preview for profile photo
        document.getElementById('profilePhotoInput').addEventListener('change', function(e) {
            const wrapper = this.parentNode;
            if (e.target.files[0]) {
                const file = e.target.files[0];
                wrapper.classList.add('selected');
                wrapper.innerHTML = `<i class="fas fa-check"></i> Photo Selected: ${file.name}`;
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.querySelector('.current-photo').src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });

        // File input preview for NID copy
        document.getElementById('nidCopyInput').addEventListener('change', function(e) {
            const wrapper = this.parentNode;
            if (e.target.files[0]) {
                const file = e.target.files[0];
                const fileName = file.name;
                const fileSize = (file.size / 1024 / 1024).toFixed(2); // MB
                
                wrapper.classList.add('selected');
                wrapper.innerHTML = `<i class="fas fa-check"></i> NID Selected: ${fileName}`;
                
                // Create preview element
                let preview = document.querySelector('.nid-preview');
                if (!preview) {
                    preview = document.createElement('div');
                    preview.className = 'file-preview nid-preview';
                    wrapper.insertAdjacentElement('afterend', preview);
                }
                
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.innerHTML = `
                            <img src="${e.target.result}" alt="NID Preview">
                            <p><strong>File:</strong> ${fileName} (${fileSize} MB)</p>
                        `;
                    };
                    reader.readAsDataURL(file);
                } else {
                    preview.innerHTML = `
                        <i class="fas fa-file-pdf" style="font-size: 3em; color: #dc3545;"></i>
                        <p><strong>File:</strong> ${fileName} (${fileSize} MB)</p>
                    `;
                }
            }
        });

        // File input preview for certification documents
        document.getElementById('certDocInput').addEventListener('change', function(e) {
            const wrapper = this.parentNode;
            if (e.target.files[0]) {
                const file = e.target.files[0];
                const fileName = file.name;
                const fileSize = (file.size / 1024 / 1024).toFixed(2); // MB
                
                wrapper.classList.add('selected');
                wrapper.innerHTML = `<i class="fas fa-check"></i> Certificate Selected: ${fileName}`;
                
                // Create preview element
                let preview = document.querySelector('.cert-preview');
                if (!preview) {
                    preview = document.createElement('div');
                    preview.className = 'file-preview cert-preview';
                    wrapper.insertAdjacentElement('afterend', preview);
                }
                
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.innerHTML = `
                            <img src="${e.target.result}" alt="Certificate Preview">
                            <p><strong>File:</strong> ${fileName} (${fileSize} MB)</p>
                        `;
                    };
                    reader.readAsDataURL(file);
                } else {
                    preview.innerHTML = `
                        <i class="fas fa-file-pdf" style="font-size: 3em; color: #dc3545;"></i>
                        <p><strong>File:</strong> ${fileName} (${fileSize} MB)</p>
                    `;
                }
            }
        });

        // Form validation with file upload checks
        document.querySelector('form').addEventListener('submit', function(e) {
            const requiredFields = document.querySelectorAll('[required]');
            let isValid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = '#e74c3c';
                    isValid = false;
                } else {
                    field.style.borderColor = '#e9ecef';
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }

            // Validate file sizes and types
            const fileInputs = document.querySelectorAll('input[type="file"]');
            let fileErrors = [];
            
            fileInputs.forEach(input => {
                if (input.files[0]) {
                    const file = input.files[0];
                    const fileSize = file.size / 1024 / 1024; // MB
                    const fileName = file.name;
                    const fileExtension = fileName.split('.').pop().toLowerCase();
                    
                    // Check file size
                    if (input.name === 'profilePhoto' && fileSize > 5) {
                        fileErrors.push(`Profile photo (${fileName}) must be less than 5MB`);
                    } else if ((input.name === 'nidCopy' || input.name === 'certificationDoc') && fileSize > 10) {
                        fileErrors.push(`Document (${fileName}) must be less than 10MB`);
                    }
                    
                    // Check file type
                    if (input.name === 'profilePhoto') {
                        const allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
                        if (!allowedTypes.includes(fileExtension)) {
                            fileErrors.push(`Profile photo must be JPG, PNG, or GIF format`);
                        }
                    } else {
                        const allowedTypes = ['jpg', 'jpeg', 'png', 'pdf'];
                        if (!allowedTypes.includes(fileExtension)) {
                            fileErrors.push(`Documents must be JPG, PNG, or PDF format`);
                        }
                    }
                }
            });

            if (fileErrors.length > 0) {
                e.preventDefault();
                alert('File upload errors:\n' + fileErrors.join('\n'));
                return false;
            }

            if (isValid && fileErrors.length === 0) {
                // Show loading state
                const submitBtn = document.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating Profile...';
                submitBtn.disabled = true;
                
                // Show upload progress message
                const formContainer = document.querySelector('.form-container');
                const progressMessage = document.createElement('div');
                progressMessage.className = 'message warning';
                progressMessage.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Uploading files, please wait...';
                formContainer.insertBefore(progressMessage, formContainer.firstChild);
            }
        });

        // Auto-calculate weekly/monthly rates based on daily rate
        document.getElementById('dailyRate').addEventListener('input', function() {
            const dailyRate = parseFloat(this.value) || 0;
            const weeklyField = document.getElementById('weeklyRate');
            const monthlyField = document.getElementById('monthlyRate');
            
            if (dailyRate > 0 && !weeklyField.value) {
                weeklyField.value = (dailyRate * 7 * 0.9).toFixed(2); // 10% discount for weekly
            }
            
            if (dailyRate > 0 && !monthlyField.value) {
                monthlyField.value = (dailyRate * 30 * 0.8).toFixed(2); // 20% discount for monthly
            }
        });

        // Add drag and drop functionality
        document.querySelectorAll('.file-input-wrapper').forEach(wrapper => {
            wrapper.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.style.backgroundColor = '#2980b9';
                this.style.transform = 'scale(1.02)';
            });
            
            wrapper.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.style.backgroundColor = '#3498db';
                this.style.transform = 'scale(1)';
            });
            
            wrapper.addEventListener('drop', function(e) {
                e.preventDefault();
                this.style.backgroundColor = '#3498db';
                this.style.transform = 'scale(1)';
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    const input = this.querySelector('input[type="file"]');
                    input.files = files;
                    input.dispatchEvent(new Event('change'));
                }
            });
        });
    </script>
</body>
</html>
