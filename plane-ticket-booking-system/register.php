<?php
// 🔗 Include the database connection file
include('db.php');

// 🔐 Start session for managing user data
session_start();

// 🚀 Check if the form has been submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    try {
        // 🔒 Sanitize form inputs (remove extra spaces)
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);

        // ❗ Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Please enter a valid email address.";
        }

        // ❗ Validate password length
        elseif (strlen($password) < 8) {
            $error_message = "Password must be at least 8 characters long.";
        }

        // ❗ Check password contains uppercase letter
        elseif (!preg_match('/[A-Z]/', $password)) {
            $error_message = "Password must contain at least one uppercase letter.";
        }

        // ❗ Check password contains number
        elseif (!preg_match('/[0-9]/', $password)) {
            $error_message = "Password must contain at least one number.";
        }

        // ❗ Check password contains special character
        elseif (!preg_match('/[\W_]/', $password)) {
            $error_message = "Password must contain at least one special character (e.g., !@#$%^&*).";
        }

        // ❗ Confirm passwords match
        elseif ($password !== $confirm_password) {
            $error_message = "Passwords do not match.";
        }

        // 🔍 Check if email already exists
        elseif ($stmt = $conn->prepare("SELECT * FROM users WHERE email = ?")) {

            // 🛡️ Execute query securely
            $stmt->execute([$email]);

            // 📦 Check if email is already registered
            if ($stmt->rowCount() > 0) {
                $error_message = "Email is already registered.";
            } else {

                // 🔐 Hash password securely before storing
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // 📝 Insert new user into database
                $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");

                if ($stmt->execute([$name, $email, $hashed_password])) {

                    // ✅ Store user info in session
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;

                    // 🔒 Session fixation protection
                    session_regenerate_id(true);

                    // 🎉 Show success message and redirect to login
                    echo "<script>
                            alert('Registration successful! Please log in.');
                            window.location.href = 'login.php';
                          </script>";
                    exit;

                } else {
                    // ❌ Insert failed
                    $error_message = "Something went wrong. Please try again.";
                }
            }
        }

    } catch (PDOException $e) {
        // 🛑 Log error internally (secure practice)
        error_log($e->getMessage());

        // ⚠️ Show safe error message to user
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

    <title>User Registration</title>

    <!-- 🎨 External CSS -->
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <!-- 📦 Main container -->
    <div class="container">
        <h2>Register</h2>

        <!-- ❗ Display error message -->
        <?php if (isset($error_message)) { ?>
            <p style="color: red; text-align: center; font-size: 14px;">
                <?php echo $error_message; ?>
            </p>
        <?php } ?>

        <!-- 📝 Registration form -->
        <form action="register.php" method="POST">

            <!-- 👤 Full name -->
            <label for="name">Full Name:</label>
            <input type="text" id="name" name="name" required>

            <!-- 📧 Email -->
            <label for="email">Email Address:</label>
            <input type="email" id="email" name="email" required>

            <!-- 🔐 Password -->
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>

            <!-- 🔐 Confirm password -->
            <label for="confirm_password">Confirm Password:</label>
            <input type="password" id="confirm_password" name="confirm_password" required>

            <!-- 🚀 Submit -->
            <button type="submit">Register</button>
        </form>

        <!-- 🔗 Login link -->
        <p>Already have an account? <a href="login.php">Login</a></p>
    </div>

</body>
</html>