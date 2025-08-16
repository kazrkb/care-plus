<?php
// Database configuration
$host = "localhost";
$port = 3306; // XAMPP MySQL port
$username = "root";
$password = ""; // Change this if your MySQL root has a password
$database = "healthcare";

// Try to connect and provide helpful error messages
try {
    $conn = new mysqli($host, $username, $password, $database, $port);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    // Common XAMPP MySQL passwords to try
    $common_passwords = ["", "root", "mysql", "password", "admin"];
    $connection_success = false;
    
    foreach ($common_passwords as $test_password) {
        try {
            $test_conn = new mysqli($host, $username, $test_password, $database, $port);
            if (!$test_conn->connect_error) {
                // Update the password variable if connection succeeds
                $password = $test_password;
                $conn = $test_conn;
                $connection_success = true;
                break;
            }
        } catch (Exception $e) {
            continue;
        }
    }
    
    if (!$connection_success) {
        die("
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
            <h2 style='color: #e74c3c;'>Database Connection Error</h2>
            <p><strong>Error:</strong> " . $e->getMessage() . "</p>
            <h3>How to fix this:</h3>
            <ol>
                <li><strong>Option 1:</strong> Reset MySQL root password in XAMPP
                    <ul>
                        <li>Open XAMPP Control Panel</li>
                        <li>Stop MySQL service</li>
                        <li>Click 'Config' next to MySQL → my.ini</li>
                        <li>Add 'skip-grant-tables' under [mysqld] section</li>
                        <li>Start MySQL and reset password</li>
                    </ul>
                </li>
                <li><strong>Option 2:</strong> Update the password in config.php</li>
                <li><strong>Option 3:</strong> Create 'healthcare' database in phpMyAdmin</li>
            </ol>
            <p><strong>Common XAMPP MySQL passwords:</strong> (empty), 'root', 'mysql', 'password'</p>
        </div>
        ");
    }
}

// Export connection for use in other files
return $conn;
?>
