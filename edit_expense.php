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
$expense = null;

// Get expense ID from URL
$expense_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$expense_id) {
    header("Location: expenses.php");
    exit();
}

// Get expense data
$sql = "SELECT * FROM expenses WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $expense_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows != 1) {
    header("Location: expenses.php");
    exit();
}

$expense = $result->fetch_assoc();
$stmt->close();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_expense'])) {
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_STRING);
    
    if (!$amount || $amount <= 0) {
        $error = "Please enter a valid amount";
    } elseif (!$category_id) {
        $error = "Please select a category";
    } elseif (!$date) {
        $error = "Please select a date";
    } else {
        // Update expense record
        $sql = "UPDATE expenses SET amount = ?, category_id = ?, description = ?, date = ? WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("dissii", $amount, $category_id, $description, $date, $expense_id, $user_id);
        
        if ($stmt->execute()) {
            $message = "Expense updated successfully!";
            // Refresh expense data
            $sql = "SELECT * FROM expenses WHERE id = ? AND user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $expense_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $expense = $result->fetch_assoc();
        } else {
            $error = "Error updating expense: " . $conn->error;
        }
        
        $stmt->close();
    }
}

// Get expense categories
$sql = "SELECT id, name FROM categories WHERE user_id = ? AND type = 'expense' ORDER BY name";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$categories_result = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Expense - FinanceTrack</title>
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
                    <li><a href="expenses.php" class="nav-item active"><i class="fas fa-receipt"></i> Expenses</a></li>
                    <li><a href="categories.php" class="nav-item"><i class="fas fa-tags"></i> Categories</a></li>
                    <li><a href="budget.php" class="nav-item"><i class="fas fa-balance-scale"></i> Budget</a></li>
                    <li><a href="settings.php" class="nav-item"><i class="fas fa-cog"></i> Settings</a></li>
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
                    <h2>Edit Expense</h2>
                    <p>Update expense details</p>
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
                
                <div class="edit-expense-container">
                    <form class="edit-form" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . $expense_id; ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="amount">Amount ($)</label>
                                <input type="number" id="amount" name="amount" min="0.01" step="0.01" value="<?php echo htmlspecialchars($expense['amount']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="category_id">Category</label>
                                <select id="category_id" name="category_id" required>
                                    <?php while ($category = $categories_result->fetch_assoc()): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo ($category['id'] == $expense['category_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="date">Date</label>
                                <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($expense['date']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="description">Description</label>
                                <input type="text" id="description" name="description" value="<?php echo htmlspecialchars($expense['description'] ?? ''); ?>" placeholder="Description of expense">
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="update_expense" class="btn primary-btn">Update Expense</button>
                            <a href="expenses.php" class="btn secondary-btn">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    
    <script src="assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add animation to form elements
            const formElements = document.querySelectorAll('.form-group, .form-actions');
            formElements.forEach((element, index) => {
                element.style.opacity = '0';
                element.style.transform = 'translateY(20px)';
                element.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                
                setTimeout(() => {
                    element.style.opacity = '1';
                    element.style.transform = 'translateY(0)';
                }, 100 + (index * 100));
            });
        });
    </script>
</body>
</html>