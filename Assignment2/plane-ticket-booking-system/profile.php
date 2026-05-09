<?php
include('session_config.php');
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
// 🔒 Ensure user logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include('db.php');

$user_id = $_SESSION['user_id'];

// 🧠 Initialize
$user = [];
$bookings = [];

try {
    // 🔍 Get user info
    $sql = "SELECT name, email FROM users WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $user_id);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 🔍 Get user bookings
    $sql2 = "SELECT * FROM bookings WHERE user_id = :id ORDER BY id DESC";
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bindParam(':id', $user_id);
    $stmt2->execute();
    $bookings = $stmt2->fetchAll(PDO::FETCH_ASSOC);
$active_count = 0;
$cancelled_count = 0;

foreach ($bookings as $b) {
    if ($b['status'] === 'Active') {
        $active_count++;
    } else {
        $cancelled_count++;
    }
}
} catch (PDOException $e) {
    error_log($e->getMessage());
    echo "Something went wrong.";
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
<title>My Profile</title>

<style>
body {
    font-family: Arial;
    background: #f2f2f2;
}

.container {
    background: white;
    padding: 20px;
    max-width: 700px;
    margin: 30px auto;
    border-radius: 5px;
    border: 1px solid #ddd;
}

h2 {
    text-align: center;
}

.profile-info {
    margin-bottom: 20px;
}

.profile-info p {
    font-size: 16px;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    border: 1px solid #aaa;
    padding: 8px;
}

th {
    background: #eee;
}

.top-bar {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
}

.top-bar a {
    padding: 8px 15px;
    color: white;
    text-decoration: none;
    border-radius: 4px;
}

.home { background: #4CAF50; }
.logout { background: #f44336; }
</style>
</head>

<body>

<div class="container">

<div class="top-bar">
    <a href="home.php" class="home">Back</a>
    <a href="logout.php" class="logout">Logout</a>
</div>

<h2>My Profile</h2>

<div class="profile-info">
    <p><strong>Name:</strong> <?php echo htmlspecialchars($user['name']); ?></p>
    <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
</div>

<h3>Total Bookings</h3>

<div style="text-align:center; font-size:18px; margin-top:20px;">
    <p><strong>Total:</strong> <?php echo count($bookings); ?></p>
    <p style="color:green;"><strong>Active:</strong> <?php echo $active_count; ?></p>
    <p style="color:red;"><strong>Cancelled:</strong> <?php echo $cancelled_count; ?></p>
</div>
</div>

</body>
</html>