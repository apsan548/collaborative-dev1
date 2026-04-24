<?php
if (session_status() === PHP_SESSION_NONE) {
    include('session_config.php');
}

// Security headers (VERY IMPORTANT)
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

// ⏱️ Session timeout (5 minutes)
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

// Update activity
$_SESSION['last_activity'] = time();

// 🔐 Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include('db.php');

// 🔒 Secure login check
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// 🔒 Validate booking ID
if (!isset($_GET['id']) || !is_numeric($_GET['id']) || $_GET['id'] <= 0) {
    die("Invalid request.");
}

$booking_id = (int) $_GET['id'];

try {
    // ✅ Secure query (VERY IMPORTANT)
    $sql = "SELECT b.*, p.full_name, p.email, p.seat_class
            FROM bookings b
            JOIN passengers p ON b.passenger_id = p.id
            WHERE b.id = :id AND b.user_id = :user_id";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':id' => $booking_id,
        ':user_id' => $_SESSION['user_id']
    ]);

    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        die("Booking not found.");
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    die("Server error. Please try again later.");
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Booking Confirmation</title>

<style>
body {
    font-family: Arial;
    background: #f2f2f2;
}

.container {
    background: white;
    padding: 20px;
    max-width: 500px;
    margin: 50px auto;
    border-radius: 5px;
    border: 1px solid #ddd;
    text-align: center;
    border-top: 5px solid #4CAF50;
}

h2 {
    color: green;
}

.btn {
    display: inline-block;
    margin-top: 10px;
    padding: 10px 20px;
    background: #4CAF50;
    color: white;
    text-decoration: none;
    border-radius: 4px;
    border: none;
    cursor: pointer;
}

.top-bar-outside {
    text-align: center;
    margin-top: 20px;
}

.nav-btn {
    padding: 8px 15px;
    border-radius: 4px;
    text-decoration: none;
    color: white;
    font-weight: bold;
    margin: 5px;
    display: inline-block;
}

.home {
    background-color: #4CAF50;
}

.bookings {
    background-color: #2196F3;
}
</style>
</head>

<body>

<div class="container">
    <h2>Booking Confirmed 🎉</h2>

    <p><strong>Booking ID:</strong> <?php echo htmlspecialchars($booking['id'], ENT_QUOTES, 'UTF-8'); ?></p>

    <p><strong>Passenger:</strong> <?php echo htmlspecialchars($booking['full_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></p>

    <p><strong>Email:</strong> <?php echo htmlspecialchars($booking['email'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></p>

    <p><strong>Class:</strong> <?php echo htmlspecialchars($booking['seat_class'] ?? 'Economy', ENT_QUOTES, 'UTF-8'); ?></p>

    <p><strong>Flight:</strong> <?php echo htmlspecialchars($booking['flight_number'], ENT_QUOTES, 'UTF-8'); ?></p>

    <p><strong>From:</strong> <?php echo htmlspecialchars($booking['departure_city'], ENT_QUOTES, 'UTF-8'); ?></p>

    <p><strong>To:</strong> <?php echo htmlspecialchars($booking['arrival_city'], ENT_QUOTES, 'UTF-8'); ?></p>

    <p><strong>Date:</strong>
        <?php echo date("d M Y, h:i A", strtotime($booking['departure_date'])); ?>
    </p>

    <p><strong>Total Paid:</strong> $<?php echo number_format($booking['price'], 2); ?></p>

    <!-- 🔐 Secure download -->
    <div>
        <a href="download_ticket.php?id=<?php echo $booking['id']; ?>&csrf=<?php echo $_SESSION['csrf_token']; ?>" class="btn">
            Download Ticket
        </a>
    </div>
</div>

<!-- ✅ OUTSIDE BUTTONS -->
<div class="top-bar-outside">
    <a href="home.php" class="nav-btn home">Home</a>
    <a href="booking_history.php" class="nav-btn bookings">My Bookings</a>
</div>

</body>
</html>