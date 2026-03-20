<?php
session_start();

// 🔒 Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include('db.php');

// 🔒 Validate flight_id input
if (!isset($_GET['flight_id'])) {
    echo "Invalid request";
    exit;
}

$flight_id = $_GET['flight_id'];

// 🔍 Fetch flight details securely
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
    // ❗ Do not expose detailed DB errors to users
    error_log($e->getMessage()); // Log internally
    echo "Something went wrong. Please try again later.";
    exit;
}

// 🧠 Initialize validation variables
$card_error = "";
$expiry_error = "";
$cvv_error = "";

$card = "";
$expiry = "";
$cvv = "";

$showSuccess = false;

// 🚀 Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 🔒 Sanitize inputs
    $card = trim($_POST['card']);
    $expiry = trim($_POST['expiry']);
    $cvv = trim($_POST['cvv']);

    $valid = true;

    // 🔹 Validate card number (16 digits)
    if (empty($card)) {
        $card_error = "Card number is required";
        $valid = false;
    } elseif (!preg_match("/^[0-9]{16}$/", $card)) {
        $card_error = "Card must be 16 digits";
        $valid = false;
    }

    // 🔹 Validate expiry format and logic
    if (empty($expiry)) {
        $expiry_error = "Expiry date is required";
        $valid = false;
    } elseif (!preg_match("/^(0[1-9]|1[0-2])\/\d{2}$/", $expiry)) {
        $expiry_error = "Format must be MM/YY";
        $valid = false;
    } else {
        list($exp_month, $exp_year) = explode('/', $expiry);
        $exp_year = 2000 + (int)$exp_year;

        $current_month = date("m");
        $current_year = date("Y");

        // ❗ Check if card is expired
        if ($exp_year < $current_year || 
           ($exp_year == $current_year && $exp_month < $current_month)) {
            $expiry_error = "Card is expired";
            $valid = false;
        }
    }

    // 🔹 Validate CVV (3 digits)
    if (empty($cvv)) {
        $cvv_error = "CVV is required";
        $valid = false;
    } elseif (!preg_match("/^[0-9]{3}$/", $cvv)) {
        $cvv_error = "CVV must be 3 digits";
        $valid = false;
    }

    // ✅ If all validations pass
    if ($valid) {
        try {
            $user_id = $_SESSION['user_id'];

            // 📝 Insert booking into database
            $sql = "INSERT INTO bookings 
            (user_id, flight_id, flight_number, departure_city, arrival_city, departure_date, price)
            VALUES 
            (:user_id, :flight_id, :flight_number, :departure_city, :arrival_city, :departure_date, :price)";

            $stmt = $conn->prepare($sql);

            $stmt->execute([
                ':user_id' => $user_id,
                ':flight_id' => $flight['id'],
                ':flight_number' => $flight['flight_number'],
                ':departure_city' => $flight['departure_city'],
                ':arrival_city' => $flight['arrival_city'],
                ':departure_date' => $flight['departure_date'],
                ':price' => $flight['price']
            ]);

            // 🔄 Reduce available seats
            $update = "UPDATE flights 
                       SET available_seats = available_seats - 1 
                       WHERE id = :id";

            $stmt = $conn->prepare($update);
            $stmt->bindParam(':id', $flight['id']);
            $stmt->execute();

            // 🎉 Trigger success modal
            $showSuccess = true;

        } catch (PDOException $e) {
            // ❗ Log error internally, show safe message
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

/* Logout */
.logout-btn {
    text-align: right;
    margin: 20px;
}

.logout-btn a {
    background-color: #f44336;
    color: white;
    padding: 10px 20px;
    border-radius: 4px;
    text-decoration: none;
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

<!-- Logout -->
<div class="logout-btn">
    <a href="logout.php">Logout</a>
</div>

<div class="container">
    <h2>Payment</h2>

    <!-- Flight Details -->
    <p><b>Flight:</b> <?php echo htmlspecialchars($flight['flight_number']); ?></p>
    <p><b>From:</b> <?php echo htmlspecialchars($flight['departure_city']); ?></p>
    <p><b>To:</b> <?php echo htmlspecialchars($flight['arrival_city']); ?></p>
    <p><b>Price:</b> $<?php echo number_format($flight['price'], 2); ?></p>

    <!-- Payment Form -->
    <form method="POST">
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

        <button type="submit">Pay Now</button>
    </form>
</div>

<!-- Back -->
<div class="back-btn">
  <a href="passenger_details.php?flight_id=<?php echo $flight['id']; ?>">Back</a>
</div>

<!-- Success Modal -->
<div id="successModal" class="modal">
    <div class="modal-content">
        <h2>Payment Successful</h2>
        <p>Your flight has been booked successfully.</p>
        <button onclick="window.location='flight_search.php'">OK</button>
    </div>
</div>

<?php if ($showSuccess): ?>
<script>
document.getElementById("successModal").style.display = "flex";
</script>
<?php endif; ?>

</body>
</html>