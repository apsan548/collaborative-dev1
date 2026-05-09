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
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Dashboard</title>

<style>
body {
    font-family: Arial;
    background: #f2f2f2;
    text-align: center;
}

.container {
    margin-top: 100px;
}

h2 {
    margin-bottom: 30px;
}

.btn {
    display: block;
    width: 250px;
    margin: 15px auto;
    padding: 15px;
    background: #4CAF50;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-size: 18px;
    border: none;          
    cursor: pointer;      
}

.btn:hover {
    background: #45a049;
}

.logout {
    background: #f44336;
}
button.btn {
    border: none;
    outline: none;
    cursor: pointer;
}

</style>
</head>

<body>

<div class="container">
    <h2>Welcome to Flight Booking System ✈️</h2>

    <a href="book_flight.php" class="btn">✈️ Book a Flight</a>
    <a href="booking_history.php" class="btn">📜 My Bookings</a>
    <a href="profile.php" class="btn">👤 Profile</a>
   <form method="POST" action="logout.php" style="width:280px; margin:15px auto;">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    
    <button type="submit"
        style="width:100%; padding:15px; font-size:18px; border:none; border-radius:6px; background:#f44336; color:white; cursor:pointer;"
        onclick="return confirmLogout()">
        Logout
    </button>
</form>
</div>
<script>
function confirmLogout() {
    return confirm("Are you sure you want to logout?");
}
</script>
</body>
</html>