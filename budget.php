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

// Handle budget form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_budget'])) {
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $period = filter_input(INPUT_POST, 'period', FILTER_SANITIZE_STRING);
    $start_date = filter_input(INPUT_POST, 'start_date', FILTER_SANITIZE_STRING);
    $end_date = filter_input(INPUT_POST, 'end_date', FILTER_SANITIZE_STRING);
    
    if (!$category_id) {
        $error = "Please select a category";
    } elseif (!$amount || $amount <= 0) {
        $error = "Please enter a valid amount";
    } elseif (!in_array($period, ['daily', 'weekly', 'monthly', 'yearly'])) {
        $error = "Invalid period selected";
    } elseif (!$start_date) {
        $error = "Please select a start date";
    } else {
        // Check if budget already exists for this category and period
        $sql = "SELECT id FROM budgets WHERE user_id = ? AND category_id = ? AND period = ? AND 
                ((? BETWEEN start_date AND COALESCE(end_date, '9999-12-31')) OR
                 (COALESCE(?, '9999-12-31') BETWEEN start_date AND COALESCE(end_date, '9999-12-31')))";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iisss", $user_id, $category_id, $period, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "A budget already exists for this category and period";
        } else {
            // Insert budget record
            $sql = "INSERT INTO budgets (user_id, category_id, amount, period, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iidsss", $user_id, $category_id, $amount, $period, $start_date, $end_date);
            
            if ($stmt->execute()) {
                $message = "Budget added successfully!";
            } else {
                $error = "Error adding budget: " . $conn->error;
            }
        }
        
        $stmt->close();
    }
}

// Handle budget deletion
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['delete_id'])) {
    $budget_id = filter_input(INPUT_GET, 'delete_id', FILTER_VALIDATE_INT);
    
    if ($budget_id) {
        $sql = "DELETE FROM budgets WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $budget_id, $user_id);
        
        if ($stmt->execute()) {
            $message = "Budget deleted successfully!";
        } else {
            $error = "Error deleting budget: " . $conn->error;
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

// Get active budgets
$sql = "SELECT b.*, c.name as category_name 
        FROM budgets b 
        JOIN categories c ON b.category_id = c.id 
        WHERE b.user_id = ? 
        ORDER BY b.start_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$budgets_result = $stmt->get_result();
$stmt->close();

// Calculate budget progress
function calculateBudgetProgress($conn, $budget) {
    $user_id = $_SESSION['user_id'];
    $total_expenses = 0;
    
    // Calculate date range based on period
    $start_date = new DateTime($budget['start_date']);
    $end_date = $budget['end_date'] ? new DateTime($budget['end_date']) : new DateTime();
    $today = new DateTime();
    
    if ($today < $start_date) {
        return ['progress' => 0, 'status' => 'Not started'];
    }
    
    // Get total expenses for the category within the date range
    $sql = "SELECT SUM(amount) as total FROM expenses 
            WHERE user_id = ? AND category_id = ? AND date BETWEEN ? AND ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiss", $user_id, $budget['category_id'], 
                      $budget['start_date'], $end_date->format('Y-m-d'));
    $stmt->execute();
    $result = $stmt->get_result();
    $total_expenses = $result->fetch_assoc()['total'] ?? 0;
    $stmt->close();
    
    $progress = ($total_expenses / $budget['amount']) * 100;
    
    // Determine status
    $status = 'On track';
    if ($progress >= 100) {
        $status = 'Exceeded';
    } elseif ($progress >= 90) {
        $status = 'Warning';
    }
    
    return [
        'progress' => min($progress, 100),
        'status' => $status,
        'spent' => $total_expenses
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Management - FinanceTrack</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h1 class="logo">Finance Track</h1>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="index.php" class="nav-item"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                    <li><a href="income.php" class="nav-item"><i class="fas fa-coins"></i> Income</a></li>
                    <li><a href="expenses.php" class="nav-item"><i class="fas fa-receipt"></i> Expenses</a></li>
                    <li><a href="categories.php" class="nav-item"><i class="fas fa-tags"></i> Categories</a></li>
                    <li><a href="budget.php" class="nav-item active"><i class="fas fa-balance-scale"></i> Budget</a></li>
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
                    <h2>Budget Management</h2>
                    <p>Set and track your spending limits</p>
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
                
                <div class="budget-content">
                    <!-- Budget Form -->
                    <div class="budget-form-container">
                        <div class="section-header">
                            <h3>Create New Budget</h3>
                        </div>
                        <form class="budget-form" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="category_id">Category</label>
                                    <select id="category_id" name="category_id" required>
                                        <option value="">Select a category</option>
                                        <?php while ($category = $categories_result->fetch_assoc()): ?>
                                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="amount">Budget Amount ($)</label>
                                    <input type="number" id="amount" name="amount" min="0.01" step="0.01" placeholder="0.00" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="period">Period</label>
                                    <select id="period" name="period" required>
                                        <option value="">Select period</option>
                                        <option value="monthly">Monthly</option>
                                        <option value="yearly">Yearly</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="start_date">Start Date</label>
                                    <input type="date" id="start_date" name="start_date" required>
                                </div>
                                <div class="form-group">
                                    <label for="end_date">End Date (Optional)</label>
                                    <input type="date" id="end_date" name="end_date">
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="submit" name="add_budget" class="btn primary-btn">Create Budget</button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Active Budgets -->
                    <div class="active-budgets-container">
                        <div class="section-header">
                            <h3>Active Budgets</h3>
                        </div>
                        <div class="budgets-grid">
                            <?php if ($budgets_result->num_rows > 0): ?>
                                <?php while ($budget = $budgets_result->fetch_assoc()):
                                    $progress = calculateBudgetProgress($conn, $budget);
                                    $status_class = '';
                                    switch ($progress['status']) {
                                        case 'Exceeded':
                                            $status_class = 'danger';
                                            break;
                                        case 'Warning':
                                            $status_class = 'warning';
                                            break;
                                        default:
                                            $status_class = 'success';
                                    }
                                ?>
                                <div class="budget-card">
                                    <div class="budget-header">
                                        <h4><?php echo htmlspecialchars($budget['category_name']); ?></h4>
                                        <span class="budget-period"><?php echo ucfirst($budget['period']); ?></span>
                                    </div>
                                    <div class="budget-amount">
                                        <span class="amount">$<?php echo number_format($budget['amount'], 2); ?></span>
                                        
                                    </div>
                                    <div class="budget-progress">
                                        <div class="progress-bar">
                                            <div class="progress <?php echo $status_class; ?>" style="width: <?php echo $progress['progress']; ?>%"></div>
                                        </div>
                                        <span class="progress-text"><?php echo number_format($progress['progress'], 1); ?>%</span>
                                    </div>
                                    <div class="budget-dates">
                                        <span>From: <?php echo date('M d, Y', strtotime($budget['start_date'])); ?></span>
                                        <?php if ($budget['end_date']): ?>
                                        <span>To: <?php echo date('M d, Y', strtotime($budget['end_date'])); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="budget-status <?php echo strtolower($progress['status']); ?>">
                                        <?php echo $progress['status']; ?>
                                    </div>
                                    <div class="budget-actions">
                                        <a href="edit_budget.php?id=<?php echo $budget['id']; ?>" class="btn-icon edit-btn" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?delete_id=<?php echo $budget['id']; ?>" 
                                           class="btn-icon delete-btn" 
                                           title="Delete"
                                           onclick="return confirm('Are you sure you want to delete this budget?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="no-records">No active budgets found</div>
                            <?php endif; ?>
                        </div>
                    </div>
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
            
            // Add animation to budget cards
            const budgetCards = document.querySelectorAll('.budget-card');
            budgetCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 200 + (index * 100));
            });
            
            // Set minimum date for start_date input
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            
            if (startDateInput && endDateInput) {
                const today = new Date().toISOString().split('T')[0];
                startDateInput.min = today;
                
                startDateInput.addEventListener('change', function() {
                    endDateInput.min = this.value;
                    if (endDateInput.value && endDateInput.value < this.value) {
                        endDateInput.value = this.value;
                    }
                });
            }
        });
    </script>
</body>
</html>