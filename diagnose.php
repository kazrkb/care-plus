<?php
// Set error reporting to show all errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>CarePlus System Diagnostic Tool</h1>";

// Check PHP version
echo "<h2>PHP Configuration</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Post Max Size: " . ini_get('post_max_size') . "</p>";
echo "<p>Upload Max Filesize: " . ini_get('upload_max_filesize') . "</p>";

// Check if the config file exists
echo "<h2>Configuration File</h2>";
if (file_exists('config.php')) {
    echo "<p style='color:green'>✓ config.php exists</p>";
    
    // Include the config file
    require_once 'config.php';
    
    // Check database connection
    echo "<h2>Database Connection</h2>";
    
    // Try port 3307 first (from config)
    $port = 3307;
    $conn3307 = @new mysqli('localhost', 'root', '', 'healthcare', $port);
    
    if (!$conn3307->connect_error) {
        echo "<p style='color:green'>✓ Connected to MySQL on port 3307</p>";
        $conn = $conn3307;
    } else {
        echo "<p style='color:red'>✗ Failed to connect to MySQL on port 3307: " . $conn3307->connect_error . "</p>";
        
        // Try port 3306 as fallback
        $port = 3306;
        $conn3306 = @new mysqli('localhost', 'root', '', 'healthcare', $port);
        
        if (!$conn3306->connect_error) {
            echo "<p style='color:green'>✓ Connected to MySQL on port 3306</p>";
            echo "<p style='color:orange'>⚠️ Your config.php is using port 3307 but your MySQL is running on 3306</p>";
            echo "<p>Consider updating config.php to use port 3306 instead.</p>";
            $conn = $conn3306;
        } else {
            echo "<p style='color:red'>✗ Failed to connect to MySQL on port 3306: " . $conn3306->connect_error . "</p>";
            
            echo "<h3>MySQL Connection Troubleshooting</h3>";
            echo "<ol>
                <li>Make sure MySQL service is running in XAMPP Control Panel</li>
                <li>Check if 'healthcare' database exists in phpMyAdmin</li>
                <li>Try different MySQL credentials if needed</li>
                <li>Verify there are no port conflicts</li>
            </ol>";
            
            die("<p>Cannot continue diagnostics without database connection</p>");
        }
    }
    
    // Check if the Users table exists and has required columns
    echo "<h2>Database Structure</h2>";
    
    $result = $conn->query("SHOW TABLES LIKE 'users'");
    if ($result->num_rows > 0) {
        echo "<p style='color:green'>✓ Users table exists</p>";
        
        // Check columns
        $result = $conn->query("DESCRIBE Users");
        $columns = [];
        while ($row = $result->fetch_assoc()) {
            $columns[$row['Field']] = $row;
        }
        
        $requiredColumns = [
            'userID', 'Name', 'email', 'contactNo', 'password', 'role', 
            'profilePhoto', 'payment_status', 'verification_status'
        ];
        
        foreach ($requiredColumns as $column) {
            if (isset($columns[$column])) {
                echo "<p style='color:green'>✓ Column '$column' exists</p>";
            } else {
                echo "<p style='color:red'>✗ Column '$column' is missing</p>";
            }
        }
        
        // Check role-specific tables
        $roleTables = ['Patient', 'Doctor', 'Nutritionist', 'CareGiver'];
        foreach ($roleTables as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            if ($result->num_rows > 0) {
                echo "<p style='color:green'>✓ $table table exists</p>";
            } else {
                echo "<p style='color:red'>✗ $table table is missing</p>";
            }
        }
    } else {
        echo "<p style='color:red'>✗ Users table does not exist</p>";
        echo "<p>The database appears to be missing required tables. Have you imported the healthcare.sql file?</p>";
    }
    
    // Check for existing users
    $result = $conn->query("SELECT COUNT(*) as count FROM Users");
    if ($result) {
        $count = $result->fetch_assoc()['count'];
        echo "<p>There are currently $count user(s) in the database.</p>";
    }
    
} else {
    echo "<p style='color:red'>✗ config.php does not exist</p>";
}

echo "<h2>Registration Troubleshooting</h2>";
echo "<p>Common reasons for registration failure:</p>";
echo "<ol>
    <li>Database connection issues (checked above)</li>
    <li>Missing tables or columns in the database (checked above)</li>
    <li>Email already exists in the database</li>
    <li>Uploaded file too large (exceeds PHP limits)</li>
    <li>Missing required fields in the form</li>
    <li>Transaction failure during multi-table insertions</li>
</ol>";

echo "<h2>Fix Recommendations</h2>";
echo "<ol>
    <li><strong>If database doesn't exist:</strong> Go to phpMyAdmin and import the healthcare.sql file</li>
    <li><strong>If tables are missing:</strong> Import healthcare.sql file</li>
    <li><strong>If MySQL connection fails:</strong> Update config.php with correct port (likely 3306)</li>
    <li><strong>If registration still fails:</strong> Try registering with a different email address</li>
</ol>";

echo "<p><a href='register.php' style='display:inline-block; padding:10px 20px; background-color:#4CAF50; color:white; text-decoration:none; border-radius:5px;'>Try Registration Again</a></p>";
