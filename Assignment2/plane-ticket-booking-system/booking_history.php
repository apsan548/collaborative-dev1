<?php
include('session_config.php');

/* SECURITY HEADERS */
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

/* ⏱️ SESSION TIMEOUT */
$timeout = 300;

if (isset($_SESSION['last_activity'])) {
    $inactive_time = time() - $_SESSION['last_activity'];

    if ($inactive_time > $timeout) {
        session_unset();
        session_destroy();
        header("Location: login.php?timeout=1");
        exit;
    }
}

$_SESSION['last_activity'] = time();

/* 🔒 VALIDATE USER SESSION */
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];

/* 🔐 CSRF TOKEN */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* 🚫 RATE LIMIT (Basic Protection) */
if (!isset($_SESSION['view_count'])) {
    $_SESSION['view_count'] = 0;
}
$_SESSION['view_count']++;

if ($_SESSION['view_count'] > 100) {
    die("Too many requests.");
}

include('db.php');

$bookings = [];

try {
    /* 🛡️ SECURE QUERY */
    $sql = "SELECT b.*, p.full_name, p.email, p.seat_class
            FROM bookings b
            JOIN passengers p ON b.passenger_id = p.id
            WHERE b.user_id = :user_id
            ORDER BY b.id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();

    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    die("Server error.");
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

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    background: white;
}

th, td {
    border: 1px solid #aaa;
    padding: 8px;
}

th {
    background: #eee;
}

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

.logout { background: #f44336; }
.back { background: #4CAF50; }

.status {
    padding: 5px 10px;
    border-radius: 4px;
    font-weight: bold;
}

.status.active {
    background-color: #d4edda;
    color: #155724;
}

.status.cancelled {
    background-color: #f8d7da;
    color: #721c24;
}

.btn-cancel {
    background-color: #f44336;
    color: white;
    padding: 5px 10px;
    border-radius: 4px;
    text-decoration: none;
    font-size: 14px;
}

.disabled {
    color: gray;
    font-style: italic;
}
</style>
</head>

<body>

<h1>Booking History</h1>

<div class="top-bar">
    <a href="home.php" class="back">Back</a>
    <a href="logout.php" class="logout">Logout</a>
</div>

<div class="container">

<?php if (!empty($bookings)): ?>

<table>
<tr>
    <th>Full Name</th>
    <th>Email</th>
    <th>Flight Number</th>
    <th>From</th>
    <th>To</th>
    <th>Date</th>
    <th>Class</th>
    <th>Price</th>
    <th>Status</th>
    <th>Action</th>
</tr>

<?php foreach ($bookings as $booking): ?>
<tr>
    <td><?php echo htmlspecialchars($booking['full_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
    <td><?php echo htmlspecialchars($booking['email'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
    <td><?php echo htmlspecialchars($booking['flight_number'], ENT_QUOTES, 'UTF-8'); ?></td>
    <td><?php echo htmlspecialchars($booking['departure_city'], ENT_QUOTES, 'UTF-8'); ?></td>
    <td><?php echo htmlspecialchars($booking['arrival_city'], ENT_QUOTES, 'UTF-8'); ?></td>
    <td><?php echo htmlspecialchars($booking['departure_date'], ENT_QUOTES, 'UTF-8'); ?></td>
    <td><?php echo htmlspecialchars($booking['seat_class'] ?? 'Economy', ENT_QUOTES, 'UTF-8'); ?></td>
    <td>$<?php echo number_format($booking['price'], 2); ?></td>

    <td>
        <?php if ($booking['status'] === 'Active'): ?>
            <span class="status active">Active</span>
        <?php else: ?>
            <span class="status cancelled">Cancelled</span>
        <?php endif; ?>
    </td>

    <td>
        <?php if ($booking['status'] === 'Active'): ?>
            <a href="cancel_booking.php?id=<?php echo $booking['id']; ?>&csrf=<?php echo $_SESSION['csrf_token']; ?>"
               class="btn-cancel"
               onclick="return confirm('Cancel this booking?')">
               Cancel
            </a>
        <?php else: ?>
            <span class="disabled">Cancelled</span>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>

</table>

<?php else: ?>
<p style="text-align:center;">No bookings found.</p>
<?php endif; ?>

</div>

</body>
</html>