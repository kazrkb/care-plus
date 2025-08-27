<?php
// File upload test and debug page
echo "<h2>File Upload Debug Information</h2>";

// Check PHP configuration
echo "<h3>PHP Configuration:</h3>";
echo "file_uploads: " . (ini_get('file_uploads') ? 'ON' : 'OFF') . "<br>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "max_execution_time: " . ini_get('max_execution_time') . " seconds<br>";
echo "memory_limit: " . ini_get('memory_limit') . "<br>";

// Check if uploads directory exists and is writable
$uploadDirs = ['uploads/', 'uploads/documents/', 'uploads/certificates/'];
echo "<h3>Directory Permissions:</h3>";
foreach ($uploadDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    echo "$dir - Exists: " . (is_dir($dir) ? 'YES' : 'NO') . " | Writable: " . (is_writable($dir) ? 'YES' : 'NO') . "<br>";
}

// Check if form was submitted
if ($_POST && isset($_FILES)) {
    echo "<h3>File Upload Test Results:</h3>";
    
    foreach ($_FILES as $fieldName => $file) {
        echo "<strong>Field: $fieldName</strong><br>";
        echo "Name: " . $file['name'] . "<br>";
        echo "Size: " . $file['size'] . " bytes<br>";
        echo "Type: " . $file['type'] . "<br>";
        echo "Error: " . $file['error'] . " (" . getUploadErrorMessage($file['error']) . ")<br>";
        echo "Temp file: " . $file['tmp_name'] . "<br>";
        echo "<br>";
        
        if ($file['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/test/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileName = 'test_' . time() . '_' . $file['name'];
            $uploadPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                echo "<span style='color: green;'>✓ File uploaded successfully to: $uploadPath</span><br>";
            } else {
                echo "<span style='color: red;'>✗ Failed to move uploaded file</span><br>";
            }
        }
    }
}

function getUploadErrorMessage($errorCode) {
    switch ($errorCode) {
        case UPLOAD_ERR_OK:
            return 'No error';
        case UPLOAD_ERR_INI_SIZE:
            return 'File exceeds upload_max_filesize';
        case UPLOAD_ERR_FORM_SIZE:
            return 'File exceeds MAX_FILE_SIZE';
        case UPLOAD_ERR_PARTIAL:
            return 'File was only partially uploaded';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing temporary folder';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write file to disk';
        case UPLOAD_ERR_EXTENSION:
            return 'File upload stopped by extension';
        default:
            return 'Unknown upload error';
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>File Upload Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .form-group { margin: 15px 0; }
        input[type="file"] { padding: 10px; border: 1px solid #ccc; }
        input[type="submit"] { background: #007cba; color: white; padding: 10px 20px; border: none; cursor: pointer; }
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>
    <h3>Test File Upload</h3>
    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label>Profile Photo:</label><br>
            <input type="file" name="profilePhoto" accept="image/*">
        </div>
        
        <div class="form-group">
            <label>NID Copy:</label><br>
            <input type="file" name="nidCopy" accept="image/*,.pdf">
        </div>
        
        <div class="form-group">
            <label>Certificate:</label><br>
            <input type="file" name="certificationDoc" accept="image/*,.pdf">
        </div>
        
        <div class="form-group">
            <input type="submit" value="Test Upload">
        </div>
    </form>
</body>
</html>
