<?php
include('session_config.php');

// Check if POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    //  Validate CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid request.");
    }

    // 🔥 Destroy session
    session_unset();
    session_destroy();

    header("Location: login.php");
    exit;
} else {
    // 🚫 Block direct access via URL
    header("Location: home.php");
    exit;
}
?>
