<?php
$servername = "localhost"; // Change if your database server is different
$username = "admin"; // Your database username
$password = "fetihtekstil@2025"; // Your database password
$dbname = "fetih"; // The name of your database

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} else {
    // echo "Connected successfully"; // Optional: Can remove this line after testing
}
?>
