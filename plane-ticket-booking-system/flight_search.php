<?php
session_start(); // 🔐 Start session

// 🔒 Allow only logged-in users
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Include database connection
include('db.php');

// Initialize variables
$departure_city = $arrival_city = $departure_date = "";
$search_results = [];
$error = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['departure_city'], $_GET['arrival_city'], $_GET['departure_date'])) {

    // 🔒 Sanitize input
    $departure_city = strtolower(trim($_GET['departure_city']));
    $arrival_city = strtolower(trim($_GET['arrival_city']));
    $departure_date = $_GET['departure_date'];

    // ❗ Validation: empty fields
    if (empty($departure_city) || empty($arrival_city) || empty($departure_date)) {
        $error = "All fields are required.";
    }

    // ❗ Validation: letters only
    elseif (!preg_match("/^[a-zA-Z ]+$/", $departure_city) || !preg_match("/^[a-zA-Z ]+$/", $arrival_city)) {
        $error = "City names must contain only letters.";
    }

    // ❗ Validation: same city
    elseif ($departure_city === $arrival_city) {
        $error = "Departure and arrival cities cannot be the same.";
    }

    // ❗ Validation: past date
    else {
        $today = date("Y-m-d");

        if ($departure_date < $today) {
            $error = "Departure date cannot be in the past.";
        }
    }

    // ✅ Run query if valid
    if (empty($error)) {
        try {
            // ✅ Exact match + case-insensitive
            $sql = "SELECT * FROM flights 
                    WHERE LOWER(departure_city) = :departure_city
                    AND LOWER(arrival_city) = :arrival_city
                    AND departure_date = :departure_date";

            $stmt = $conn->prepare($sql);

            $stmt->bindParam(':departure_city', $departure_city);
            $stmt->bindParam(':arrival_city', $arrival_city);
            $stmt->bindParam(':departure_date', $departure_date);

            $stmt->execute();

            $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($search_results)) {
                $search_results[] = "No flights found for the given search criteria.";
            }

        } catch (PDOException $e) {
            error_log($e->getMessage());
            echo "Something went wrong. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Flight Search</title>

<style>
body {
    font-family: Arial, sans-serif;
    background: #f2f2f2;
}

h1 {
    text-align: center;
    background: #4CAF50;
    color: white;
    padding: 15px;
}

.search-container {
    background: white;
    padding: 20px;
    max-width: 400px;
    margin: 20px auto;
    border-radius: 5px;
    border: 1px solid #ddd;
}

label {
    font-weight: bold;
    margin-top: 10px;
    display: block;
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
    margin-top: 15px;
    background: #4CAF50;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.error {
    color: red;
    text-align: center;
    margin-bottom: 10px;
}

/* Logout */
.logout-btn {
    text-align: right;
    margin: 20px;
}

.logout-btn a {
    background: red;
    color: white;
    padding: 10px 20px;
    text-decoration: none;
}

/* My Bookings Button */
.my-bookings-btn {
    display: block;
    width: 100%;
    text-align: center;
    margin-top: 10px;
    padding: 10px;
    background: #2196F3;
    color: white;
    border-radius: 4px;
    text-decoration: none;
}

.my-bookings-btn:hover {
    background: #1976D2;
}
</style>
</head>

<body>

<h1>Plane Ticket Booking</h1>

<!-- Logout -->
<div class="logout-btn">
    <a href="logout.php">Logout</a>
</div>

<div class="search-container">
    <h2>Search for Flights</h2>

    <!-- Error Message -->
    <?php if (!empty($error)): ?>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <!-- Form -->
    <form action="flight_results.php" method="GET">
        <label>Departure City:</label>
        <input type="text" name="departure_city"
        value="<?php echo htmlspecialchars($departure_city); ?>" required
        oninput="this.value=this.value.replace(/[^a-zA-Z ]/g,'')">

        <label>Arrival City:</label>
        <input type="text" name="arrival_city"
        value="<?php echo htmlspecialchars($arrival_city); ?>" required
        oninput="this.value=this.value.replace(/[^a-zA-Z ]/g,'')">

        <label>Departure Date:</label>
        <input type="date" name="departure_date"
        value="<?php echo htmlspecialchars($departure_date); ?>"
        min="<?php echo date('Y-m-d'); ?>" required>

        <button type="submit">Search Flights</button>
    </form>

    <!-- My Bookings -->
    <a href="booking_history.php" class="my-bookings-btn">My Bookings</a>

</div>

</body>
</html>