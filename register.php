<?php
session_start();
require_once 'config/db.php';

// Already logged in user redirected to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

// Process registration form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate input
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long";
    } else {
        // Check if email already exists
        $sql = "SELECT id FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Email already in use";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user
            $sql = "INSERT INTO users (username, email, password, created_at) VALUES (?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $username, $email, $hashed_password);
            
            if ($stmt->execute()) {
                $success = "Registration successful! You can now login.";
                
                // Create default income categories for the new user
                $user_id = $stmt->insert_id;
                $default_income_categories = [
                    ['Salary', 'Regular employment income', 'income'],
                    ['Freelance', 'Income from freelance work', 'income'],
                    ['Investments', 'Income from investments', 'income'],
                    ['Gifts', 'Money received as gifts', 'income'],
                    ['Other', 'Other sources of income', 'income']
                ];
                
                $sql = "INSERT INTO categories (user_id, name, description, type) VALUES (?, ?, ?, ?)";
                $cat_stmt = $conn->prepare($sql);
                
                foreach ($default_income_categories as $category) {
                    $cat_stmt->bind_param("isss", $user_id, $category[0], $category[1], $category[2]);
                    $cat_stmt->execute();
                }
                
                // Create default expense categories
                $default_expense_categories = [
                    ['Housing', 'Rent, mortgage, utilities', 'expense'],
                    ['Food', 'Groceries and dining out', 'expense'],
                    ['Transportation', 'Fuel, public transit, car maintenance', 'expense'],
                    ['Entertainment', 'Movies, events, subscriptions', 'expense'],
                    ['Shopping', 'Clothing, electronics, personal items', 'expense'],
                    ['Health', 'Medical expenses, insurance', 'expense'],
                    ['Education', 'Tuition, books, courses', 'expense'],
                    ['Personal Care', 'Haircuts, gym, spa', 'expense'],
                    ['Travel', 'Vacations, trips', 'expense'],
                    ['Miscellaneous', 'Other expenses', 'expense']
                ];
                
                foreach ($default_expense_categories as $category) {
                    $cat_stmt->bind_param("isss", $user_id, $category[0], $category[1], $category[2]);
                    $cat_stmt->execute();
                }
                
                $cat_stmt->close();
            } else {
                $error = "Registration failed: " . $conn->error;
            }
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
    <title>Register - FinanceTrack</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-left">
            <div class="auth-content">
                <h1>FinanceTrack</h1>
                <p>Create an account and start managing your finances today.</p>
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
                    <h2>Create Account</h2>
                    <p>Fill in your information to get started</p>
                </div>
                
                <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <div class="mt-2">
                        <a href="login.php" class="btn secondary-btn">Go to Login</a>
                    </div>
                </div>
                <?php else: ?>
                
                <form class="auth-form" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <div class="input-with-icon">
                            <i class="fas fa-user"></i>
                            <input type="text" id="username" name="username" placeholder="Choose a username" required>
                        </div>
                    </div>
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
                            <input type="password" id="password" name="password" placeholder="Choose a password" required>
                        </div>
                        <div class="password-strength" id="password-strength"></div>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <div class="input-with-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
                        </div>
                    </div>
                    <div class="form-agreement">
                        <input type="checkbox" id="terms" name="terms" required>
                        <label for="terms">I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></label>
                    </div>
                    <button type="submit" class="btn primary-btn register-btn">Create Account</button>
                </form>
                <div class="auth-links">
                    <p>Already have an account? <a href="login.php">Login</a></p>
                </div>
                
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="assets/js/auth.js"></script>
</body>
</html>