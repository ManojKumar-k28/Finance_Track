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

// Handle expense form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_expense'])) {
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
        // Insert expense record
        $sql = "INSERT INTO expenses (user_id, category_id, amount, description, date) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iidss", $user_id, $category_id, $amount, $description, $date);
        
        if ($stmt->execute()) {
            $message = "Expense added successfully!";
        } else {
            $error = "Error adding expense: " . $conn->error;
        }
        
        $stmt->close();
    }
}

// Handle expense deletion
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['delete_id'])) {
    $expense_id = filter_input(INPUT_GET, 'delete_id', FILTER_VALIDATE_INT);
    
    if ($expense_id) {
        // Delete expense record
        $sql = "DELETE FROM expenses WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $expense_id, $user_id);
        
        if ($stmt->execute()) {
            $message = "Expense deleted successfully!";
        } else {
            $error = "Error deleting expense: " . $conn->error;
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

// Get expense records with category names
$sql = "SELECT e.id, e.amount, e.description, e.date, c.name as category_name 
        FROM expenses e 
        JOIN categories c ON e.category_id = c.id 
        WHERE e.user_id = ? 
        ORDER BY e.date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$expense_result = $stmt->get_result();
$stmt->close();

// Calculate total expenses
$sql = "SELECT SUM(amount) as total FROM expenses WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_result = $stmt->get_result();
$total_expenses = $total_result->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Get monthly expense summary
$sql = "SELECT 
            YEAR(date) as year,
            MONTH(date) as month,
            SUM(amount) as monthly_total
        FROM expenses 
        WHERE user_id = ?
        GROUP BY YEAR(date), MONTH(date)
        ORDER BY YEAR(date) DESC, MONTH(date) DESC
        LIMIT 6";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$monthly_result = $stmt->get_result();
$stmt->close();

$months = [];
$monthly_totals = [];
while ($row = $monthly_result->fetch_assoc()) {
    $month_name = date('M Y', mktime(0, 0, 0, $row['month'], 1, $row['year']));
    $months[] = $month_name;
    $monthly_totals[] = $row['monthly_total'];
}
// Reverse arrays to show oldest to newest
$months = array_reverse($months);
$monthly_totals = array_reverse($monthly_totals);

// Get expenses by category
$sql = "SELECT 
            c.name as category_name,
            SUM(e.amount) as category_total
        FROM expenses e
        JOIN categories c ON e.category_id = c.id
        WHERE e.user_id = ?
        GROUP BY e.category_id
        ORDER BY category_total DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$category_result = $stmt->get_result();
$stmt->close();

$category_names = [];
$category_totals = [];
while ($row = $category_result->fetch_assoc()) {
    $category_names[] = $row['category_name'];
    $category_totals[] = $row['category_total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses - FinanceTrack</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <h2>Expense Management</h2>
                    <p>Manage and track your expenses</p>
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
                
                <div class="expense-dashboard">
                    <div class="summary-card total-card expense-card">
                        <div class="card-icon">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <div class="card-content">
                            <h3>Total Expenses</h3>
                            <p class="amount">$<?php echo number_format($total_expenses, 2); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="expense-content">
                    <!-- Expense Form -->
                    <div class="expense-form-container">
                        <div class="section-header">
                            <h3>Add New Expense</h3>
                        </div>
                        <form class="expense-form" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="amount">Amount ($)</label>
                                    <input type="number" id="amount" name="amount" min="0.01" step="0.01" placeholder="0.00" required>
                                </div>
                                <div class="form-group">
                                    <label for="category_id">Category</label>
                                    <select id="category_id" name="category_id" required>
                                        <option value="">Select a category</option>
                                        <?php while ($category = $categories_result->fetch_assoc()): ?>
                                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="date">Date</label>
                                    <input type="date" id="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="description">Description</label>
                                    <input type="text" id="description" name="description" placeholder="Description of expense">
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="submit" name="add_expense" class="btn danger-btn">Add Expense</button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Expense Charts -->
                    <div class="expense-charts">
                        <div class="chart-container">
                            <div class="chart-card">
                                <h3>Monthly Expenses</h3>
                                <canvas id="monthly-expense-chart"width="400" height="400"></canvas>
                            </div>
                            <div class="chart-card">
                                <h3>Expenses by Category</h3>
                                <canvas id="category-expense-chart"width="400" height="400"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Expense List -->
                <div class="expense-list-container">
                    <div class="section-header">
                        <h3>Expense History</h3>
                    </div>
                    
                    <div class="expense-list">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Category</th>
                                    <th>Description</th>
                                    <th>Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($expense_result->num_rows > 0): ?>
                                    <?php while ($expense = $expense_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($expense['date'])); ?></td>
                                        <td><?php echo htmlspecialchars($expense['category_name']); ?></td>
                                        <td><?php echo htmlspecialchars($expense['description'] ?? 'N/A'); ?></td>
                                        <td class="amount">$<?php echo number_format($expense['amount'], 2); ?></td>
                                        <td class="actions">
                                            <a href="edit_expense.php?id=<?php echo $expense['id']; ?>" class="btn-icon edit-btn" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?delete_id=<?php echo $expense['id']; ?>" 
                                               class="btn-icon delete-btn" 
                                               title="Delete"
                                               onclick="return confirm('Are you sure you want to delete this expense record?');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="no-records">No expense records found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Monthly Expense Chart
            const monthlyCtx = document.getElementById('monthly-expense-chart').getContext('2d');
            const monthlyChart = new Chart(monthlyCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($months); ?>,
                    datasets: [{
                        label: 'Monthly Expenses',
                        data: <?php echo json_encode($monthly_totals); ?>,
                        backgroundColor: 'rgba(230, 57, 70, 0.7)',
                        borderColor: 'rgba(230, 57, 70, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
            
            // Category Expense Chart
            const categoryCtx = document.getElementById('category-expense-chart').getContext('2d');
            const categoryChart = new Chart(categoryCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($category_names); ?>,
                    datasets: [{
                        data: <?php echo json_encode($category_totals); ?>,
                        backgroundColor: [
                            'rgba(230, 57, 70, 0.7)',
                            'rgba(255, 159, 28, 0.7)',
                            'rgba(4, 102, 200, 0.7)',
                            'rgba(12, 206, 107, 0.7)',
                            'rgba(152, 67, 230, 0.7)',
                            'rgba(58, 134, 255, 0.7)'
                        ],
                        borderColor: [
                            'rgba(230, 57, 70, 1)',
                            'rgba(255, 159, 28, 1)',
                            'rgba(4, 102, 200, 1)',
                            'rgba(12, 206, 107, 1)',
                            'rgba(152, 67, 230, 1)',
                            'rgba(58, 134, 255, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    },
                    cutout: '70%'
                }
            });
            
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