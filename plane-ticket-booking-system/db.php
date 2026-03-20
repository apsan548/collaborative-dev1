
<?php
$servername = "localhost"; // Database server (usually localhost)
$username = "root"; // MySQL username (default is 'root')
$password = ""; // MySQL password (leave empty if using default)
$dbname = "plane_ticket_booking"; // The database name

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    exit;
}
?>