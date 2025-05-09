<?php
session_start();
require_once 'config/db.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Get user data
$sql = "SELECT username, email FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($username)) {
        $error = "Username is required";
    } elseif (empty($email)) {
        $error = "Email is required";
    } else {
        // Check if email is already in use by another user
        $sql = "SELECT id FROM users WHERE email = ? AND id != ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Email is already in use";
        } else {
            // Start building the update query
            $updates = [];
            $types = "";
            $params = [];
            
            // Always update username and email
            $updates[] = "username = ?";
            $updates[] = "email = ?";
            $types .= "ss";
            $params[] = $username;
            $params[] = $email;
            
            // If password change is requested
            if (!empty($current_password)) {
                // Verify current password
                $sql = "SELECT password FROM users WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $stored_password = $result->fetch_assoc()['password'];
                
                if (!password_verify($current_password, $stored_password)) {
                    $error = "Current password is incorrect";
                } elseif (empty($new_password)) {
                    $error = "New password is required";
                } elseif ($new_password !== $confirm_password) {
                    $error = "New passwords do not match";
                } elseif (strlen($new_password) < 8) {
                    $error = "New password must be at least 8 characters long";
                } else {
                    // Add password update
                    $updates[] = "password = ?";
                    $types .= "s";
                    $params[] = password_hash($new_password, PASSWORD_DEFAULT);
                }
            }
            
            if (empty($error)) {
                // Add user_id to params
                $types .= "i";
                $params[] = $user_id;
                
                // Build and execute update query
                $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
                $stmt = $conn->prepare($sql);
                
                // Create array reference for bind_param
                $bind_params = array($types);
                foreach ($params as $key => $value) {
                    $bind_params[] = &$params[$key];
                }
                call_user_func_array(array($stmt, 'bind_param'), $bind_params);
                
                if ($stmt->execute()) {
                    $message = "Profile updated successfully!";
                    // Update session variables
                    $_SESSION['username'] = $username;
                    $_SESSION['email'] = $email;
                    // Refresh user data
                    $user['username'] = $username;
                    $user['email'] = $email;
                } else {
                    $error = "Error updating profile: " . $conn->error;
                }
                
                $stmt->close();
            }
        }
    }
}

// Get currency preferences (you would need to add a user_preferences table)
$currency = '$'; // Default currency symbol
$date_format = 'M d, Y'; // Default date format
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - FinanceTrack</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h1 class="logo">FinanceTrack</h1>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="index.php" class="nav-item"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                    <li><a href="income.php" class="nav-item"><i class="fas fa-coins"></i> Income</a></li>
                    <li><a href="expenses.php" class="nav-item"><i class="fas fa-receipt"></i> Expenses</a></li>
                    <li><a href="categories.php" class="nav-item"><i class="fas fa-tags"></i> Categories</a></li>
                    <li><a href="budget.php" class="nav-item"><i class="fas fa-balance-scale"></i> Budget</a></li>
                    <li><a href="settings.php" class="nav-item active"><i class="fas fa-cog"></i> Settings</a></li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="main-content">
            <!-- Top Navigation Bar -->
            <header class="top-nav">
                <div class="menu-toggle">
                    <i class="fas fa-bars"></i>
                </div>
                <div class="user-info">
                    <span>Welcome, <?php echo $_SESSION['username'] ?? 'User'; ?></span>
                    
                </div>
            </header>

            <!-- Page Content -->
            <div class="content-container">
                <div class="page-header">
                    <h2>Settings</h2>
                    <p>Manage your account and preferences</p>
                </div>
                
                <?php if (!empty($message)): ?>
                <div class="alert alert-success">
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <div class="settings-content">
                    <!-- Profile Settings -->
                    <div class="settings-section">
                        <div class="section-header">
                            <h3>Profile Settings</h3>
                        </div>
                        <form class="settings-form" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <div class="form-group">
                                <label for="username">Username</label>
                                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            <div class="password-section">
                                <h4>Change Password</h4>
                                <div class="form-group">
                                    <label for="current_password">Current Password</label>
                                    <input type="password" id="current_password" name="current_password">
                                </div>
                                <div class="form-group">
                                    <label for="new_password">New Password</label>
                                    <input type="password" id="new_password" name="new_password">
                                    <div class="password-strength" id="password-strength"></div>
                                </div>
                                <div class="form-group">
                                    <label for="confirm_password">Confirm New Password</label>
                                    <input type="password" id="confirm_password" name="confirm_password">
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="submit" name="update_profile" class="btn primary-btn">Update Profile</button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Preferences -->
                    <div class="settings-section">
                        <div class="section-header">
                            <h3>Preferences</h3>
                        </div>
                        <form class="settings-form">
                            <div class="form-group">
                                <label for="currency">Currency</label>
                                <select id="currency" name="currency">
                                    <option value="$" <?php echo $currency == '$' ? 'selected' : ''; ?>>USD ($)</option>
                                    <option value="€" <?php echo $currency == '€' ? 'selected' : ''; ?>>EUR (€)</option>
                                    <option value="£" <?php echo $currency == '£' ? 'selected' : ''; ?>>GBP (£)</option>
                                    <option value="¥" <?php echo $currency == '¥' ? 'selected' : ''; ?>>JPY (¥)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="date_format">Date Format</label>
                                <select id="date_format" name="date_format">
                                    <option value="M d, Y" <?php echo $date_format == 'M d, Y' ? 'selected' : ''; ?>>MM, DD, YYYY</option>
                                    <option value="d/m/Y" <?php echo $date_format == 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                                    <option value="Y-m-d" <?php echo $date_format == 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                                </select>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn primary-btn">Save Preferences</button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Data Management -->
                    <div class="settings-section">
                        <div class="section-header">
                            <h3>Data Management</h3>
                        </div>
                        <div class="data-management-options">
                            <div class="option-card">
                                <div class="option-icon">
                                    <i class="fas fa-file-export"></i>
                                </div>
                                <div class="option-content">
                                    <h4>Export Data</h4>
                                    <p>Download your financial data in CSV format</p>
                                    <button class="btn secondary-btn">Export Data</button>
                                </div>
                            </div>
                            <div class="option-card">
                                <div class="option-icon danger">
                                    <i class="fas fa-trash-alt"></i>
                                </div>
                                <div class="option-content">
                                    <h4>Delete Account</h4>
                                    <p>Permanently delete your account and all data</p>
                                    <button class="btn danger-btn">Delete Account</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="assets/js/main.js"></script>
    <script src="assets/js/auth.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add animation to settings sections
            const sections = document.querySelectorAll('.settings-section');
            sections.forEach((section, index) => {
                section.style.opacity = '0';
                section.style.transform = 'translateY(20px)';
                section.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                
                setTimeout(() => {
                    section.style.opacity = '1';
                    section.style.transform = 'translateY(0)';
                }, 100 + (index * 200));
            });
            
            // Password strength indicator
            const newPasswordInput = document.getElementById('new_password');
            const passwordStrength = document.getElementById('password-strength');
            const confirmPasswordInput = document.getElementById('confirm_password');
            
            if (newPasswordInput && passwordStrength) {
                newPasswordInput.addEventListener('input', updatePasswordStrength);
            }
            
            if (newPasswordInput && confirmPasswordInput) {
                confirmPasswordInput.addEventListener('input', checkPasswordMatch);
                newPasswordInput.addEventListener('input', checkPasswordMatch);
            }
            
            // Confirm before account deletion
            const deleteBtn = document.querySelector('.danger-btn');
            if (deleteBtn) {
                deleteBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (confirm('Are you sure you want to delete your account? This action cannot be undone.')) {
                        // Handle account deletion
                    }
                });
            }
        });
    </script>
</body>
</html>