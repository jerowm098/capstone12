<?php
// config/db_connect.php
// Iwasan ang session ini_set() errors

// Check muna kung may active session
if (session_status() === PHP_SESSION_NONE) {
    // Saka lang mag-set ng session config
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'pupbc_carelink');

// Create connection PROPERLY
function getConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}

// Global connection variable
$conn = getConnection();
?>