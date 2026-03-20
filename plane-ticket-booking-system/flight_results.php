<?php
session_start(); // 🔐 Start session for login + search data

// 🔒 Allow access only if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Include the database connection
include('db.php');

// 🔍 Handle both scenarios:
// 1. First search using GET
// 2. Returning back using SESSION
if (isset($_GET['departure_city'], $_GET['arrival_city'], $_GET['departure_date'])) {

    // Get values from URL
    $departure_city = $_GET['departure_city'];
    $arrival_city = $_GET['arrival_city'];
    $departure_date = $_GET['departure_date'];

    // Store search data in session for navigation
    $_SESSION['search_data'] = [
        'departure_city' => $departure_city,
        'arrival_city' => $arrival_city,
        'departure_date' => $departure_date
    ];

} elseif (isset($_SESSION['search_data'])) {

    // Retrieve stored values when user navigates back
    $departure_city = $_SESSION['search_data']['departure_city'];
    $arrival_city = $_SESSION['search_data']['arrival_city'];
    $departure_date = $_SESSION['search_data']['departure_date'];

} else {
    // ❗ No valid data available
    echo "Invalid access";
    exit;
}

$search_results = [];
$message = "";

try {
    // 🔍 Prepare values for LIKE query
    $departure_city_param = "%" . $departure_city . "%";
    $arrival_city_param = "%" . $arrival_city . "%";

    // 🛡️ Secure query using prepared statement
    $sql = "SELECT * FROM flights 
            WHERE departure_city LIKE :departure_city 
            AND arrival_city LIKE :arrival_city 
            AND departure_date = :departure_date";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':departure_city', $departure_city_param);
    $stmt->bindParam(':arrival_city', $arrival_city_param);
    $stmt->bindParam(':departure_date', $departure_date);
    $stmt->execute();

    // 📊 Fetch results
    $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ❗ Handle no results case
    if (empty($search_results)) {
        $message = "No flights found from $departure_city to $arrival_city on $departure_date.";
    }

} catch (PDOException $e) {
    // 🔒 Log error internally (do not expose to user)
    error_log($e->getMessage());

    // Show safe message
    echo "Something went wrong. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Flight Results</title>

<style>
    body {
        font-family: Arial, sans-serif;
        background: #f2f2f2;
        padding: 0px;
    }

    h1 {
        text-align: center;
        background: #4CAF50;
        color: white;
        padding: 15px;
        border-radius: 5px;
    }

    .search-container {
        background: white;
        padding: 20px;
        max-width: 700px;
        margin: 20px auto;
        border-radius: 5px;
        border: 1px solid #ddd;
    }

    table {
        width: 100%;
        margin-top: 20px;
        border-collapse: collapse;
    }

    th, td {
        border: 1px solid #aaa;
        padding: 8px;
        text-align: left;
    }

    th {
        background: #eee;
    }

    .no-results-message {
        text-align: center;
        color: red;
        margin-top: 15px;
        font-weight: bold;
    }

    /* Logout Button */
    .logout-btn {
        text-align: right;
        margin-bottom: 20px;
        margin-right: 20px;
    }

    .logout-btn a {
        background-color: #f44336;
        color: white;
        padding: 10px 20px;
        border-radius: 4px;
        text-decoration: none;
    }

    .logout-btn a:hover {
        background-color: #e53935;
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

    .back-btn a:hover {
        background-color: #45a049;
    }

    /* Book Button */
    .book-btn {
        background-color: #4CAF50;
        color: white;
        padding: 5px 10px;
        border-radius: 4px;
        text-decoration: none;
    }

    .book-btn:hover {
        background-color: #45a049;
    }
</style>
</head>

<body>

<h1>Plane Ticket Booking</h1>

<!-- 🔒 Logout Button -->
<div class="logout-btn">
    <a href="logout.php">Logout</a>
</div>

<div class="search-container">
    <h2>Flight Search Results</h2>

    <?php if (!empty($search_results)): ?>
        <table>
            <tr>
                <th>Flight Number</th>
                <th>Departure Time</th>
                <th>Arrival Time</th>
                <th>Price</th>
                <th>Seats Available</th>
                <th>Action</th>
            </tr>

            <?php foreach ($search_results as $flight): ?>
                <tr>
                    <!-- 🛡️ Escape output to prevent XSS -->
                    <td><?php echo htmlspecialchars($flight['flight_number']); ?></td>
                    <td><?php echo htmlspecialchars($flight['departure_time']); ?></td>
                    <td><?php echo htmlspecialchars($flight['arrival_time']); ?></td>
                    <td>$<?php echo number_format($flight['price'], 2); ?></td>
                    <td><?php echo htmlspecialchars($flight['available_seats']); ?></td>

                    <!-- 🎯 Booking action -->
                    <td>
                        <a class="book-btn" href="passenger_details.php?flight_id=<?php echo $flight['id']; ?>">
                            Book
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <!-- ❗ No results message -->
    <?php if (!empty($message)): ?>
        <p class="no-results-message"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

</div>

<!-- 🔙 Back Button -->
<div class="back-btn">
    <a href="flight_search.php">Back</a>
</div>

</body>
</html>