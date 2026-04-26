<?php
include('session_config.php');
//  Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
// ⏱️ Session timeout (5 minutes)
$timeout = 300; // seconds

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
// 🔒 Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include('db.php');

// 🔒 Validate flight_id
if (!isset($_GET['flight_id']) || !is_numeric($_GET['flight_id'])) {
    echo "Invalid request";
    exit;
}

$flight_id = $_GET['flight_id'];
// 🔐 Get final price from URL
if (!isset($_GET['price']) || !is_numeric($_GET['price'])) {
    die("Invalid price.");
}

$final_price = (float) $_GET['price'];
// 🔍 Fetch flight
try {
    $sql = "SELECT * FROM flights WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $flight_id);
    $stmt->execute();

    $flight = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$flight) {
        echo "Flight not found";
        exit;
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    echo "Something went wrong. Please try again later.";
    exit;
}

// 🧠 Validation variables
$card_error = "";
$expiry_error = "";
$cvv_error = "";
$payment_error = "";
$card = "";
$expiry = "";
$cvv = "";

// 🚀 Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 🚫 Prevent double payment (VERY IMPORTANT)
// 🚫 Prevent double payment (SHOW MESSAGE instead of die)
if (isset($_SESSION['payment_done']) && $_SESSION['payment_done'] === true) {
    $payment_error = "Payment already processed.";
    $valid = false;
}
    // 🔐 CSRF validation
if (!isset($_POST['csrf_token']) || 
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    die("Invalid request.");
}

    // 🔒 Sanitize input
    $card = trim($_POST['card']);
    $expiry = trim($_POST['expiry']);
    $cvv = trim($_POST['cvv']);

    $valid = true;

    // 🔹 Card validation
    if (empty($card)) {
        $card_error = "Card number is required";
        $valid = false;
    } elseif (!preg_match("/^[0-9]{16}$/", $card)) {
        $card_error = "Card must be 16 digits";
        $valid = false;
    }

    // 🔹 Expiry validation
    if (empty($expiry)) {
        $expiry_error = "Expiry date is required";
        $valid = false;
    } elseif (!preg_match("/^(0[1-9]|1[0-2])\/\d{2}$/", $expiry)) {
        $expiry_error = "Format must be MM/YY";
        $valid = false;
    } else {
        list($exp_month, $exp_year) = explode('/', $expiry);
        $exp_year = 2000 + (int)$exp_year;

        if ($exp_year < date("Y") || 
           ($exp_year == date("Y") && $exp_month < date("m"))) {
            $expiry_error = "Card is expired";
            $valid = false;
        }
    }

    // 🔹 CVV validation
    if (empty($cvv)) {
        $cvv_error = "CVV is required";
        $valid = false;
    } elseif (!preg_match("/^[0-9]{3}$/", $cvv)) {
        $cvv_error = "CVV must be 3 digits";
        $valid = false;
    }

    // ✅ If valid
   if ($valid) {
        try {
            $user_id = $_SESSION['user_id'];

       // ✅ GET passenger_id (ADD HERE)
$passenger_stmt = $conn->prepare("
    SELECT id FROM passengers 
    WHERE user_id = :user_id 
    ORDER BY id DESC LIMIT 1
");

$passenger_stmt->execute([
    ':user_id' => $user_id
]);

$passenger = $passenger_stmt->fetch(PDO::FETCH_ASSOC);

if (!$passenger) {
    die("Passenger not found.");
}

$passenger_id = $passenger['id'];
          
            // 🔒 Seat check
            if ($flight['available_seats'] <= 0) {
                echo "<p style='color:red; text-align:center;'>
                        No seats available for this flight.
                      </p>";
                exit;
            }

            // 📝 Insert booking
           $insert_sql = "INSERT INTO bookings 
(user_id, passenger_id, flight_id, flight_number, departure_city, arrival_city, departure_date, price, status)
VALUES 
(:user_id, :passenger_id, :flight_id, :flight_number, :departure_city, :arrival_city, :departure_date, :price, 'Active')";
            $stmt = $conn->prepare($insert_sql);
            $stmt->execute([
    ':user_id' => $user_id,
    ':passenger_id' => $passenger_id, 
    ':flight_id' => $flight['id'],
    ':flight_number' => $flight['flight_number'],
    ':departure_city' => $flight['departure_city'],
    ':arrival_city' => $flight['arrival_city'],
    ':departure_date' => $flight['departure_date'],
    ':price' => $final_price
]);

            // ✅ Get booking ID
            $booking_id = $conn->lastInsertId();
            
            // ✅ Mark payment as completed
$_SESSION['payment_done'] = true;
            // 🔄 Reduce seats
            $update_sql = "UPDATE flights 
                           SET available_seats = available_seats - 1 
                           WHERE id = :id AND available_seats > 0";

            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bindParam(':id', $flight['id']);
            $update_stmt->execute();


            // 🔁 Redirect
            header("Location: confirmation.php?id=" . $booking_id);
            exit;

        } catch (PDOException $e) {
            error_log($e->getMessage());
            echo "Booking failed. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payment</title>

<style>
body {
    font-family: Arial;
    background: #f2f2f2;
}

/* Container */
.container {
    background: white;
    padding: 20px;
    max-width: 400px;
    margin: 20px auto;
    border-radius: 5px;
    border: 1px solid #ddd;
}

h2 {
    text-align: center;
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
}

/* Back Button */
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

/* Modal */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.6);
    justify-content: center;
    align-items: center;
}

.modal-content {
    background: white;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
}
</style>
</head>

<body>

<div class="container">
    <h2>Payment</h2>

    <!-- Flight Details -->
    <p><b>Flight:</b> <?php echo htmlspecialchars($flight['flight_number']); ?></p>
    <p><b>From:</b> <?php echo htmlspecialchars($flight['departure_city']); ?></p>
    <p><b>To:</b> <?php echo htmlspecialchars($flight['arrival_city']); ?></p>
    <p><b>Total Price:</b> $<?php echo number_format($final_price, 2); ?></p>
<p><b>Seat Class:</b> <?php echo htmlspecialchars($_GET['seat_class'] ?? 'Economy'); ?></p>
    <!-- Payment Form -->
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <label>Card Number</label>
        <input type="text" name="card" maxlength="16"
        value="<?php echo htmlspecialchars($card); ?>"
        oninput="this.value=this.value.replace(/[^0-9]/g,'')">
        <div class="error"><?php echo $card_error; ?></div>

        <label>Expiry</label>
        <input type="text" name="expiry" maxlength="5"
        value="<?php echo htmlspecialchars($expiry); ?>"
        oninput="this.value=this.value.replace(/[^0-9\/]/g,'')">
        <div class="error"><?php echo $expiry_error; ?></div>

        <label>CVV</label>
        <input type="text" name="cvv" maxlength="3"
        value="<?php echo htmlspecialchars($cvv); ?>"
        oninput="this.value=this.value.replace(/[^0-9]/g,'')">
        <div class="error"><?php echo $cvv_error; ?></div>

      <button type="submit" <?php if(!empty($payment_error)) echo 'disabled'; ?>>
    Pay Now
</button>
    </form>
</div>
</div>  <!-- container ends -->

<!-- 🔴 ADD THIS MODAL HERE -->
<div id="paymentErrorModal" class="modal">
    <div class="modal-content">
        <h2 style="color:red;">Error</h2>
        <p><?php echo htmlspecialchars($payment_error); ?></p>
        <button onclick="closeModal()">OK</button>
    </div>
</div>

<!-- Back -->
<div class="back-btn">
<!-- Back -->
<div class="back-btn">
  <a href="passenger_details.php?flight_id=<?php echo $flight['id']; ?>">Back</a>
</div>
<script>
function closeModal() {
    document.getElementById("paymentErrorModal").style.display = "none";
}

// Show modal if error exists
<?php if (!empty($payment_error)): ?>
document.getElementById("paymentErrorModal").style.display = "flex";
<?php endif; ?>
</script>
</body>
</html>