<?php
$host = "localhost:3306";
$username = "root";
$password = "12345678";
$database = "fanimation";

// Create connection
$conn = mysqli_connect($host, $username, $password, $database);

// Check connection
if (!$conn) {
    // Log error for debugging (don't expose in production)
    
    die("Kết nối thất bại: " . mysqli_connect_error());
}

// Set charset to prevent encoding issues
