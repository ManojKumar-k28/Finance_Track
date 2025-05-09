<?php
session_start();
require_once 'config/db.php';

// Already logged in user redirected to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';

// Process login form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password";
    } else {
        // Check user credentials
        $sql = "SELECT id, username, email, password FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Password is correct, create session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                
                // Redirect to dashboard
                header("Location: index.php");
                exit();
            } else {
                $error = "Invalid email or password";
            }
        } else {
            $error = "Invalid email or password";
        }
        
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - FinanceTrack</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-left">
            <div class="auth-content">
                <h1>FinanceTrack</h1>
                <p>Take control of your personal finances with our easy-to-use tracking and budgeting tools.</p>
                <div class="feature-list">
                    <div class="feature-item">
                        <i class="fas fa-chart-line"></i>
                        <span>Track income and expenses</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-tags"></i>
                        <span>Categorize transactions</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-chart-pie"></i>
                        <span>Visualize your spending</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-piggy-bank"></i>
                        <span>Set and achieve financial goals</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="auth-right">
            <div class="auth-form-container">
                <div class="auth-form-header">
                    <h2>Welcome Back</h2>
                    <p>Login to your account</p>
                </div>
                
                <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <form class="auth-form" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <div class="input-with-icon">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="email" name="email" placeholder="Enter your email" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-with-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="password" name="password" placeholder="Enter your password" required>
                        </div>
                    </div>
                    <div class="form-options">
                        <div class="remember-me">
                            <input type="checkbox" id="remember" name="remember">
                            <label for="remember">Remember me</label>
                        </div>
                        <a href="forgot_password.php" class="forgot-password">Forgot Password?</a>
                    </div>
                    <button type="submit" class="btn primary-btn login-btn">Login</button>
                </form>
                <div class="auth-links">
                    <p>Don't have an account? <a href="register.php">Sign up</a></p>
                </div>
            </div>
        </div>
    </div>
    
    <script src="assets/js/auth.js"></script>
</body>
</html>