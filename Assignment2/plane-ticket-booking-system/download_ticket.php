<?php
include('session_config.php');
include('db.php');

if (!isset($_GET['id'])) {
    die("Invalid request");
}

$booking_id = $_GET['id'];

// Fetch booking
$stmt = $conn->prepare("
    SELECT b.*, p.full_name, p.email, p.seat_class
    FROM bookings b
    JOIN passengers p ON b.passenger_id = p.id
    WHERE b.id = :id
");
$stmt->execute([':id' => $booking_id]);

$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    die("Booking not found");
}

// 📄 Force download as text file
header("Content-Type: application/octet-stream");
header("Content-Disposition: attachment; filename=ticket_" . $booking_id . ".txt");

echo "===== FLIGHT TICKET =====\n";
echo "Booking ID: " . $booking['id'] . "\n";
echo "Passenger: " . $booking['full_name'] . "\n";
echo "Email: " . $booking['email'] . "\n";
echo "Flight: " . $booking['flight_number'] . "\n";
echo "From: " . $booking['departure_city'] . "\n";
echo "To: " . $booking['arrival_city'] . "\n";
echo "Date: " . $booking['departure_date'] . "\n";
echo "Class: " . $booking['seat_class'] . "\n";
echo "Price: $" . $booking['price'] . "\n";