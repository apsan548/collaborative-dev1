<?php
include('session_config.php');
//  Session timeout (5 minutes)
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
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include('db.php');

$departure_city = $arrival_city = $departure_date = "";

$search_results = [];
$error = "";

// ✅ Load ALL flights by default
try {
  $sort_by = $_GET['sort_by'] ?? '';
$order_sql = "ORDER BY departure_date ASC";

switch ($sort_by) {
    case 'price_low':
        $order_sql = "ORDER BY price ASC";
        break;
    case 'price_high':
        $order_sql = "ORDER BY price DESC";
        break;
    case 'seats':
        $order_sql = "ORDER BY available_seats DESC";
        break;
}

$stmt = $conn->prepare("SELECT * FROM flights $order_sql");
    $stmt->execute();
    $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    echo "Error loading flights.";
}

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['csrf_token'])) // 🔍 Search logic
 {
  if (!isset($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
        die("Invalid request.");
    }
    $departure_city = strtolower(trim(filter_input(INPUT_GET, 'departure_city', FILTER_SANITIZE_STRING)));
$arrival_city = strtolower(trim(filter_input(INPUT_GET, 'arrival_city', FILTER_SANITIZE_STRING)));
$departure_date = $_GET['departure_date'];

    if (empty($departure_city) || empty($arrival_city) || empty($departure_date)) {
        $error = "All fields are required.";
    }

    elseif (!preg_match("/^[a-zA-Z ]+$/", $departure_city) || !preg_match("/^[a-zA-Z ]+$/", $arrival_city)) {
        $error = "City names must contain only letters.";
    }

    elseif ($departure_city === $arrival_city) {
        $error = "Departure and arrival cities cannot be the same.";
    }

    else {
        $today = date("Y-m-d");

        if ($departure_date < $today) {
            $error = "Departure date cannot be in the past.";
        }
    }

    // ✅ Filter results
    if (empty($error)) {
        try {
            
           $sql = "SELECT * FROM flights 
        WHERE LOWER(departure_city) = :departure_city
        AND LOWER(arrival_city) = :arrival_city
        AND departure_date = :departure_date
        $order_sql";

            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':departure_city' => $departure_city,
                ':arrival_city' => $arrival_city,
                ':departure_date' => $departure_date
            ]);

            $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            if (empty($search_results)) {
                $error = "No flights found for selected criteria.";
            }

        } catch (PDOException $e) {
            error_log($e->getMessage());
            echo "Something went wrong.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Book Flight</title>

<style>
body {
    font-family: Arial;
    background: #f2f2f2;
}

/* Container */
.container {
    max-width: 900px;
    margin: 20px auto;
    background: white;
    padding: 20px;
    border-radius: 5px;
}



/* Back button like your image */
.bottom-back {
    text-align: center;
    margin: 30px 0;
}

.back-btn {
    display: inline-block;
    padding: 10px 25px;
    background: #4CAF50;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-weight: bold;
}

.back-btn:hover {
    background: #45a049;
}

/* Error box at TOP */
.error-text {
    color: red;
    text-align: center;
    margin-bottom: 15px;
    font-weight: bold;
}

/* Form */
input {
    width: 100%;
    padding: 8px;
    margin-top: 5px;
}

button {
    margin-top: 10px;
    padding: 10px;
    background: #4CAF50;
    color: white;
    border: none;
    cursor: pointer;
}

/* Table */
table {
    width: 100%;
    margin-top: 20px;
    border-collapse: collapse;
}

th, td {
    border: 1px solid #ccc;
    padding: 8px;
}

.book-btn {
    background: #4CAF50;
    color: white;
    padding: 5px 10px;
    text-decoration: none;
    border-radius: 4px;
}

</style>
</head>

<body>

<div class="container">



<h2>Book a Flight ✈️</h2>

<!-- ❗ ERROR AT TOP -->
<?php if (!empty($error)): ?>
    <p class="error-text">
        <?php echo htmlspecialchars($error); ?>
    </p>
<?php endif; ?>

<!-- 🔍 SEARCH -->
<form method="GET">
   
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
   <input type="text" name="departure_city"
value="<?php echo htmlspecialchars($departure_city); ?>" placeholder="Departure City" required>

<input type="text" name="arrival_city"
value="<?php echo htmlspecialchars($arrival_city); ?>" placeholder="Arrival City" required>

<input type="date" name="departure_date"
value="<?php echo htmlspecialchars($departure_date); ?>" required>
    <button type="submit">Search Flights</button>
</form>

<!-- ✈️ FLIGHT LIST -->
<h3>Available Flights</h3>
<form method="GET" style="margin-bottom:15px;">

    <!-- keep previous search values -->
    <input type="hidden" name="departure_city" value="<?php echo htmlspecialchars($departure_city); ?>">
    <input type="hidden" name="arrival_city" value="<?php echo htmlspecialchars($arrival_city); ?>">
    <input type="hidden" name="departure_date" value="<?php echo htmlspecialchars($departure_date); ?>">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

    <label><strong>Sort Flights:</strong></label>

    <select name="sort_by" onchange="this.form.submit()" style="padding:6px; border-radius:5px;">
        <option value="">Default</option>
        <option value="price_low" <?php if($sort_by=='price_low') echo 'selected'; ?>>Price (Low → High)</option>
        <option value="price_high" <?php if($sort_by=='price_high') echo 'selected'; ?>>Price (High → Low)</option>
        <option value="date" <?php if($sort_by=='date') echo 'selected'; ?>>Date</option>
        <option value="seats" <?php if($sort_by=='seats') echo 'selected'; ?>>Seats</option>
    </select>

</form>
<table>
<tr>
    <th>Flight</th>
    <th>From</th>
    <th>To</th>
    <th>Date</th>
    <th>Price</th>
    <th>Seats</th>
    <th>Action</th>
</tr>

<?php foreach ($search_results as $flight): ?>
<tr>
    <td><?php echo htmlspecialchars($flight['flight_number']); ?></td>
<td><?php echo htmlspecialchars($flight['departure_city']); ?></td>
<td><?php echo htmlspecialchars($flight['arrival_city']); ?></td>
<td>
<?php 
echo date("d M Y", strtotime($flight['departure_date'])) . "<br>";
echo date("h:i A", strtotime($flight['departure_time'])) . " → " . 
     date("h:i A", strtotime($flight['arrival_time']));
?>
</td>
<td>$<?php echo number_format($flight['price'], 2); ?></td>

<td>
<?php if ($flight['available_seats'] > 10): ?>
    <span style="color:green; font-weight:bold;">
        <?php echo htmlspecialchars($flight['available_seats']); ?> seats
    </span>

<?php elseif ($flight['available_seats'] > 0): ?>
    <span style="color:orange; font-weight:bold;">
        Only <?php echo htmlspecialchars($flight['available_seats']); ?> left!
    </span>

<?php else: ?>
    <span style="color:red; font-weight:bold;">Full</span>
<?php endif; ?>
</td>

    <td>
        <?php if ($flight['available_seats'] > 0): ?>
            <a class="book-btn" href="passenger_details.php?flight_id=<?php echo $flight['id']; ?>">
                Book
            </a>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>

</table>
</table>

<div class="bottom-back">
    <a href="home.php" class="back-btn">Back</a>
</div>

</div>
</div>

</body>
</html>