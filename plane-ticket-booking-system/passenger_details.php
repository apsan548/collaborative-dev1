<?php
session_start();

// 🔒 Ensure only logged-in users can access this page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include('db.php');

// 🔒 Validate required parameter (flight_id)
if (!isset($_GET['flight_id'])) {
    echo "Invalid request";
    exit;
}

$flight_id = $_GET['flight_id'];

// 🧠 Initialize variables
$name = $email = $phone = "";
$name_error = $email_error = $phone_error = "";
$booking_error = ""; // ✅ NEW (duplicate booking error)

// 🚀 Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 🔒 Sanitize user input
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);

    $valid = true;

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

    // ✅ NEW: Duplicate booking check
    if ($valid) {
        try {
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
                    (user_id, flight_id, full_name, email, phone) 
                    VALUES (:user_id, :flight_id, :name, :email, :phone)";

            $stmt = $conn->prepare($sql);

            $stmt->execute([
                ':user_id' => $user_id,
                ':flight_id' => $flight_id,
                ':name' => $name,
                ':email' => $email,
                ':phone' => $phone
            ]);

            // 🔁 Redirect to payment
            header("Location: payment.php?flight_id=" . urlencode($flight_id));
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

<div class="logout-btn">
    <a href="logout.php">Logout</a>
</div>

<div class="container">
    <h2>Passenger Details</h2>

    <!-- ✅ Duplicate booking error -->
    <?php if (!empty($booking_error)): ?>
        <div class="error"><?php echo htmlspecialchars($booking_error); ?></div>
    <?php endif; ?>

    <form method="POST">

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

        <button type="submit">Continue to Payment</button>

    </form>
</div>

<div class="back-btn">
    <a href="flight_results.php">Back</a>
</div>

</body>
</html>