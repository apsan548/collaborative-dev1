<?php
// 🔗 Include the database connection file
include('db.php');

// 🔐 Start session for managing user data
include('session_config.php');
// 🔐 Generate CSRF token (only once per session)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
// 🚀 Check if the form has been submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    try {
        // 🔐 Validate CSRF token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    die("Invalid request.");
}

        // 🔒 Sanitize form inputs (remove extra spaces)

        $name = htmlspecialchars(trim($_POST['name']));
$email = htmlspecialchars(trim($_POST['email']));
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);

 // ❗ Terms must be accepted FIRST
if (!isset($_POST['terms'])) {
    $error_message = "You must agree to the Terms & Conditions.";
}

// ❗ Validate email
elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
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
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
    <style>
        .terms-container {
    display: flex;
    align-items: center;
    gap: 10px;
}

/* override global input rule */
.terms-container input {
    width: auto;
   
    margin: 0;
    padding: 0;
}

/* fix label stacking */
.terms-container label {
    display: inline;
    margin: 0;
}
#password-match-message {
    text-align: left;
    padding-left: 2px;
    font-size: 13px; 
    margin-top: -15px; 
    margin-bottom: 15px;
}
</style>
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
<input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
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
            <p id="password-match-message"></p>
  <div class="terms-container">
    <input type="checkbox" id="terms" name="terms">
    <label for="terms" >I agree to the Terms & Conditions</label>
</div>
<p id="terms-error" style="color:red; font-size: 13px; text-align:center;"></p>            <!-- 🚀 Submit -->
            <button type="submit">Register</button>
        </form>

        <!-- 🔗 Login link -->
        <p>Already have an account? <a href="login.php">Login</a></p>
    </div>
<script>
const password = document.getElementById("password");
const confirmPassword = document.getElementById("confirm_password");
const message = document.getElementById("password-match-message");

confirmPassword.addEventListener("keyup", function () {

    if (confirmPassword.value === "") {
        message.textContent = "";
        return;
    }

    if (password.value === confirmPassword.value) {
        message.textContent = "✔ Passwords match";
        message.style.color = "green";
    } else {
        message.textContent = "✖ Passwords do not match";
        message.style.color = "red";
    }

});
</script>
</body>
</html>