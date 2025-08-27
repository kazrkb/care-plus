<?php
// Test database connection and setup

$host = "localhost";
$username = "root";
$passwords = ["", "root", "password", "admin"];
$database = "healthcare";

echo "Testing database connections...\n";

foreach ($passwords as $password) {
    echo "Trying password: '" . ($password ?: 'empty') . "'... ";
    
    // First try to connect without specifying database
    $conn = @new mysqli($host, $username, $password);
    
    if (!$conn->connect_error) {
        echo "Connection successful!\n";
        
        // Check if database exists
        $result = $conn->query("SHOW DATABASES LIKE '$database'");
        if ($result->num_rows > 0) {
            echo "Database '$database' exists.\n";
        } else {
            echo "Database '$database' does not exist. Creating...\n";
            if ($conn->query("CREATE DATABASE $database")) {
                echo "Database '$database' created successfully.\n";
            } else {
                echo "Error creating database: " . $conn->error . "\n";
            }
        }
        
        // Now test connection to the specific database
        $conn2 = @new mysqli($host, $username, $password, $database);
        if (!$conn2->connect_error) {
            echo "Connection to '$database' database successful!\n";
            echo "This password works: '$password'\n";
            $conn2->close();
        }
        
        $conn->close();
        break;
    } else {
        echo "Failed: " . $conn->connect_error . "\n";
    }
}
?>
