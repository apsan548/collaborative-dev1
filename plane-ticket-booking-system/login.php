<?php
// 🔗 Include the database connection file
include('db.php'); // Make sure the path to db.php is correct

// 🔐 Start session to manage logged-in users
session_start();

// 🚀 Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    try {
        // 🔒 Sanitize user input (remove extra spaces)
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);

        // ❗ Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Please enter a valid email address.";
        }

        // ✅ Proceed only if no validation error
        if (!isset($error_message)) {

            // 🔍 Prepare SQL query to fetch user by email
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");

            // 🛡️ Execute query securely (prevents SQL injection)
            $stmt->execute([$email]);

            // 📦 Fetch user data
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // 🔐 Verify user exists AND password is correct
            if ($user && password_verify($password, $user['password'])) {

                // ✅ Store user info in session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];

                // 🔒 Session fixation protection
                session_regenerate_id(true);

                // 🔁 Redirect to main page after login
                header('Location: flight_search.php');
                exit;

            } else {
                // ❌ Invalid credentials
                $error_message = "Invalid email or password.";
            }
        }

    } catch (PDOException $e) {
        // 🛑 Log error internally (do not expose sensitive info)
        error_log($e->getMessage());

        // ⚠️ Show safe message to user
        $error_message = "Something went wrong. Please try again later.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <!-- 📱 Responsive design -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>User Login</title>

    <!-- 🎨 External CSS -->
    <link rel="stylesheet" href="style.css">

    <!-- 🔗 Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>

    <!-- 📦 Main login container -->
    <div class="container">
        <h2>Login</h2>

        <!-- ❗ Show error message -->
        <?php if (isset($error_message)) { ?>
            <p style="color: red; text-align: center; font-size: 14px;">
                <?php echo $error_message; ?>
            </p>
        <?php } ?>

        <!-- 📝 Login form -->
        <form action="login.php" method="POST">

            <!-- 📧 Email input -->
            <label for="email">Email Address:</label>
            <input type="email" id="email" name="email" required>

            <!-- 🔐 Password input -->
            <label for="password">Password:</label>
            <div class="password-container">

                <!-- Password field -->
                <input type="password" id="password" name="password" required>

                <!-- 👁️ Toggle icon -->
                <i id="toggle-password" class="fa fa-eye" style="cursor: pointer;"></i>
            </div>

            <!-- 🔗 Forgot password (demo only) -->
            <div class="forgot-password-container">
                <a href="#" id="forgot-password-link">Forgot Password?</a>
            </div>

            <!-- 🚀 Submit -->
            <button type="submit">Login</button>
        </form>

        <!-- 🔗 Registration link -->
        <p>Don't have an account? <a href="register.html">Sign up</a></p>
    </div>

    <script>
        // 👁️ Show/Hide password functionality
        document.getElementById("toggle-password").addEventListener("click", function() {
            var passwordField = document.getElementById("password");
            var toggleIcon = document.getElementById("toggle-password");

            // 🔄 Toggle password visibility
            if (passwordField.type === "password") {
                passwordField.type = "text";
                toggleIcon.classList.remove("fa-eye");
                toggleIcon.classList.add("fa-eye-slash");
            } else {
                passwordField.type = "password";
                toggleIcon.classList.remove("fa-eye-slash");
                toggleIcon.classList.add("fa-eye");
            }
        });

        // ⚠️ Demo alert for forgot password
        document.getElementById("forgot-password-link").addEventListener("click", function(e) {
            e.preventDefault();
            alert("This feature is not implemented for now.");
        });
    </script>

</body>
</html>
