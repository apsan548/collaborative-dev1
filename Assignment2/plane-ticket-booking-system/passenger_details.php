<?php
include('session_config.php');
// ⏱️ Session timeout (5 minutes)
$timeout = 300; // seconds
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if (isset($_SESSION['last_activity'])) {

    $inactive_time = time() - $_SESSION['last_activity'];

    if ($inactive_time > $timeout) {
        // Destroy session
        session_unset();
        session_destroy();

        header("Location: login.php?timeout=1");
        exit;
    }
}

// Update last activity time
$_SESSION['last_activity'] = time();
// 🔒 Ensure only logged-in users can access this page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include('db.php');

if (!isset($_GET['flight_id']) || !is_numeric($_GET['flight_id'])) {
    die("Invalid flight ID.");
}

$flight_id = (int) $_GET['flight_id'];
// 🔍 Check if flight exists
try {
    $stmt = $conn->prepare("SELECT * FROM flights WHERE id = ?");
    $stmt->execute([$flight_id]);
    $flight = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$flight) {
        die("Flight not found.");
    }
if ($flight['available_seats'] <= 0) {
    $booking_error = "This flight is fully booked.";
   
}
} catch (PDOException $e) {
    error_log($e->getMessage());
    die("Something went wrong.");
}


$flight_id = (int) $_GET['flight_id'];

// 🧠 Initialize variables
$name = $email = $phone = "";
$name_error = $email_error = $phone_error = "";
$booking_error = "";
$seat_class = "";
$dob = "";
 $dob_error = "";// ✅ NEW (duplicate booking error)
$valid = true;
// 🚀 Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {

// 🔐 CSRF Protection
if (!isset($_POST['csrf_token']) || 
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    die("Invalid request.");
}
// 🔴 Double check seats before booking
if ($flight['available_seats'] <= 0) {
    $booking_error = "This flight is fully booked.";
    $valid = false;
}
    // 🔒 Sanitize user input
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
$seat_class = htmlspecialchars($_POST['seat_class']);
$dob = $_POST['dob'];


    // 🔹 Validate full name
    if (empty($name)) {
        $name_error = "Full name is required";
        $valid = false;
    } elseif (!preg_match("/^[a-zA-Z ]{3,100}$/", $name)) {
        $name_error = "Only letters allowed (min 3 characters)";
        $valid = false;
    }

    // 🔹 Validate email
    if (empty($email)) {
        $email_error = "Email is required";
        $valid = false;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email_error = "Invalid email format";
        $valid = false;
    }

    // 🔹 Validate phone
    if (empty($phone)) {
        $phone_error = "Phone number is required";
        $valid = false;
    } elseif (!preg_match("/^[0-9]{10,15}$/", $phone)) {
        $phone_error = "Phone must be 10–15 digits";
        $valid = false;
    }
    // 🔹 Validate seat class
if (empty($seat_class)) {
    $booking_error = "Please select a seat class.";
    $valid = false;
}

// 🔹 Validate DOB

if (empty($dob)) {
    $dob_error = "Date of birth is required.";
    $valid = false;
} else {
    $today = date("Y-m-d");

    if ($dob > $today) {
        $dob_error = "Date of birth cannot be in the future.";
        $valid = false;
    } else {
        $dob_date = new DateTime($dob);
        $today_date = new DateTime();
        $age = $today_date->diff($dob_date)->y;

        if ($age < 18) {
            $dob_error = "You must be at least 18 years old.";
            $valid = false;
        }
    }
}

    // ✅ NEW: Duplicate booking check
    if ($valid) {
        try {
            $flight_stmt = $conn->prepare("SELECT price, available_seats FROM flights WHERE id = :id");
$flight_stmt->execute([':id' => $flight_id]);
$flight = $flight_stmt->fetch(PDO::FETCH_ASSOC);
$price = $flight['price'];

if ($seat_class == 'business') {
    $price *= 1.5;
} elseif ($seat_class == 'first') {
    $price *= 2;
}
            $check_sql = "SELECT id FROM passengers 
                          WHERE flight_id = :flight_id
                          AND full_name = :name
                          AND email = :email
                          AND phone = :phone";

            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->execute([
                ':flight_id' => $flight_id,
                ':name' => $name,
                ':email' => $email,
                ':phone' => $phone
            ]);

            $existing_booking = $check_stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing_booking) {
                $booking_error = "This passenger has already booked this flight.";
                $valid = false;
            }

        } catch (PDOException $e) {
            error_log($e->getMessage());
            echo "Something went wrong. Please try again later.";
        }
    }

    // ✅ Insert only if valid
    if ($valid) {
        try {
            $user_id = $_SESSION['user_id'];
$sql = "INSERT INTO passengers 
(user_id, flight_id, full_name, email, phone, seat_class, dob)
VALUES (:user_id, :flight_id, :name, :email, :phone, :seat_class, :dob)";

            $stmt = $conn->prepare($sql);

           $stmt->execute([
    ':user_id' => $user_id,
    ':flight_id' => $flight_id,
    ':name' => $name,
    ':email' => $email,
    ':phone' => $phone,
    ':seat_class' => $seat_class,
    ':dob' => $dob
]);
            // 🔽 Reduce available seats
$update = $conn->prepare("
    UPDATE flights 
    SET available_seats = available_seats - 1 
    WHERE id = ? AND available_seats > 0
");

$update->execute([$flight_id]);

if ($update->rowCount() === 0) {
    $booking_error = "No seats available.";
    exit;
}

// 🔄 Regenerate CSRF token after successful use
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            // 🔁 Redirect to payment
header("Location: payment.php?flight_id=" . urlencode($flight_id) . "&price=" . urlencode($price));
            exit;

        } catch (PDOException $e) {
            error_log($e->getMessage());
            echo "Something went wrong. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Passenger Details</title>

<style>
body {
    font-family: Arial;
    background: #f2f2f2;
}





.container {
    background: white;
    padding: 20px;
    max-width: 400px;
    margin: 50px auto;
    border-radius: 5px;
    border: 1px solid #ddd;
}

h2 {
    text-align: center;
}

label {
    font-weight: bold;
}

input {
    width: 100%;
    padding: 8px;
    margin-top: 5px;
    border: 1px solid #aaa;
    border-radius: 4px;
}

button {
    width: 100%;
    padding: 10px;
    background: #4CAF50;
    color: white;
    border: none;
    margin-top: 15px;
    border-radius: 4px;
    cursor: pointer;
}

.error {
    color: red;
    font-size: 13px;
    margin-top: 5px;
    text-align: center;
}

.back-btn {
    text-align: center;
    margin-top: 20px;
}

.back-btn a {
    background-color: #4CAF50;
    color: white;
    padding: 10px 20px;
    border-radius: 4px;
    text-decoration: none;
}
</style>
</head>

<body>



<div class="container">
    <h2>Passenger Details</h2>

    <!-- ✅ Duplicate booking error -->
    <?php if (!empty($booking_error)): ?>
        <div class="error"><?php echo htmlspecialchars($booking_error); ?></div>
    <?php endif; ?>

    <form method="POST">
<input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <label>Full Name</label>
        <input type="text" name="name" value="<?php echo htmlspecialchars($name); ?>">
        <div class="error"><?php echo $name_error; ?></div>

        <label>Email</label>
        <input type="text" name="email" value="<?php echo htmlspecialchars($email); ?>">
        <div class="error"><?php echo $email_error; ?></div>

        <label>Phone Number</label>
        <input type="text" name="phone" maxlength="15"
        oninput="this.value=this.value.replace(/[^0-9]/g,'')"
        value="<?php echo htmlspecialchars($phone); ?>">
        <div class="error"><?php echo $phone_error; ?></div>
        <label>Seat Class</label>
<select name="seat_class" required>
    <option value="">Select Class</option>
    <option value="economy" <?php if($seat_class == 'economy') echo 'selected'; ?>>Economy</option>
    <option value="business" <?php if($seat_class == 'business') echo 'selected'; ?>>Business (+50%)</option>
    <option value="first" <?php if($seat_class == 'first') echo 'selected'; ?>>First Class (+100%)</option>
</select>

<div style="flex: 1;">
        <label >Date of Birth</label>
        <input type="date" name="dob" value="<?php echo htmlspecialchars($dob); ?>" max="<?php echo date('Y-m-d'); ?>" required style="width: 100%; padding: 8px; ">
        <div class="error"><?php echo $dob_error; ?></div>
    </div>
        <button type="submit">Continue to Payment</button>

    </form>
</div>

<div class="back-btn">
    <a href="book_flight.php">Back</a>
</div>

</body>
</html>