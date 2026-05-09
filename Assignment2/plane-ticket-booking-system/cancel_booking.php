<?php
// 🔐 Start session safely
if (session_status() === PHP_SESSION_NONE) {
    include('session_config.php');
}

// 🔒 Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 🔗 Database connection
include('db.php');

// 🔒 Validate booking ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "Invalid request.";
    exit;
}

$booking_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

try {
    // 🔍 Check booking exists and belongs to user
    $check = $conn->prepare("
        SELECT id, status FROM bookings 
        WHERE id = :id AND user_id = :user_id
    ");
    $check->execute([
        ':id' => $booking_id,
        ':user_id' => $user_id
    ]);

    $booking = $check->fetch(PDO::FETCH_ASSOC);

    // ❗ If booking not found
    if (!$booking) {
        echo "Booking not found.";
        exit;
    }

    // ❗ Prevent cancelling again
    if ($booking['status'] === 'Cancelled') {
        header("Location: booking_history.php");
        exit;
    }

    // 🔄 Update booking status
    $update = $conn->prepare("
        UPDATE bookings 
        SET status = 'Cancelled' 
        WHERE id = :id AND user_id = :user_id
    ");

    $update->execute([
        ':id' => $booking_id,
        ':user_id' => $user_id
    ]);

    // 🔁 (OPTIONAL BONUS) Restore seat
    $restoreSeat = $conn->prepare("
        UPDATE flights 
        SET available_seats = available_seats + 1 
        WHERE id = (
            SELECT flight_id FROM bookings WHERE id = :id
        )
    ");

    $restoreSeat->execute([':id' => $booking_id]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    echo "Something went wrong.";
    exit;
}

// 🔁 Redirect back
header("Location: booking_history.php");
exit;
?>