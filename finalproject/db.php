<?php
// db.php

// Database Configuration Parameters
$host     = '127.0.0.1';
$db       = 'resort_lost_found';
$user     = 'root';
$pass     = ''; // Set your database password here
$charset  = 'utf8mb4';

// Data Source Name (DSN)
$dsn = "mysql:host={$host};dbname={$db};charset={$charset}";

// PDO Options
$options = [
    // Throw PDOExceptions on errors for easier debugging and transaction rollbacks
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    
    // Fetch associative arrays by default (e.g., $row['column_name'])
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    
    // Disable native prepared statements emulation to force real prepared statements
    PDO::ATTR_EMULATE_PREPARES   => false,
    
    // Use persistent connections to reduce connection overhead (optional)
    PDO::ATTR_PERSISTENT         => false,
];

try {
    // Instantiate PDO instance
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Log exception details server-side (in production logs)
    error_log("Database Connection Error: " . $e->getMessage(), 0);

    // Terminate script execution with a user-friendly error message
    http_response_code(500);
    die("Database connection error. Please contact system administrator.");
}