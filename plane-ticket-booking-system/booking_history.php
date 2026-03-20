<?php
session_start();

// 🔒 Allow only logged-in users
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Include DB
include('db.php');

$user_id = $_SESSION['user_id'];
$bookings = [];

try {
    // 🛡️ Get user bookings
    $sql = "SELECT * FROM bookings WHERE user_id = :user_id ORDER BY id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();

    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    echo "Something went wrong. Please try again later.";
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Booking History</title>

<style>
body {
    font-family: Arial;
    background: #f2f2f2;
}

h1 {
    text-align: center;
    background: #4CAF50;
    color: white;
    padding: 15px;
}

.container {
    background: white;
    padding: 20px;
    max-width: 800px;
    margin: 20px auto;
    border-radius: 5px;
    border: 1px solid #ddd;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

th, td {
    border: 1px solid #aaa;
    padding: 8px;
}

th {
    background: #eee;
}

/* Buttons */
.top-bar {
    display: flex;
    justify-content: space-between;
    margin: 20px;
}

.top-bar a {
    padding: 10px 20px;
    text-decoration: none;
    border-radius: 4px;
    color: white;
}

.logout {
    background: #f44336;
}

.back {
    background: #4CAF50;
}
</style>
</head>

<body>

<h1>Booking History</h1>

<!-- Top Buttons -->
<div class="top-bar">
    <a href="flight_search.php" class="back">Back</a>
    <a href="logout.php" class="logout">Logout</a>
</div>

<div class="container">

<?php if (!empty($bookings)): ?>

    <table>
        <tr>
            <th>Flight Number</th>
            <th>From</th>
            <th>To</th>
            <th>Date</th>
            <th>Price</th>
        </tr>

        <?php foreach ($bookings as $booking): ?>
            <tr>
                <td><?php echo htmlspecialchars($booking['flight_number']); ?></td>
                <td><?php echo htmlspecialchars($booking['departure_city']); ?></td>
                <td><?php echo htmlspecialchars($booking['arrival_city']); ?></td>
                <td><?php echo htmlspecialchars($booking['departure_date']); ?></td>
                <td>$<?php echo number_format($booking['price'], 2); ?></td>
            </tr>
        <?php endforeach; ?>

    </table>

<?php else: ?>

    <p style="text-align:center;">No bookings found.</p>

<?php endif; ?>

</div>

</body>
</html>