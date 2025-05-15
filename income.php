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

// Handle income form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_income'])) {
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
        // Insert income record
        $sql = "INSERT INTO income (user_id, category_id, amount, description, date) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iidss", $user_id, $category_id, $amount, $description, $date);
        
        if ($stmt->execute()) {
            $message = "Income added successfully!";
        } else {
            $error = "Error adding income: " . $conn->error;
        }
        
        $stmt->close();
    }
}

// Handle income deletion
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['delete_id'])) {
    $income_id = filter_input(INPUT_GET, 'delete_id', FILTER_VALIDATE_INT);
    
    if ($income_id) {
        // Delete income record
        $sql = "DELETE FROM income WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $income_id, $user_id);
        
        if ($stmt->execute()) {
            $message = "Income deleted successfully!";
        } else {
            $error = "Error deleting income: " . $conn->error;
        }
        
        $stmt->close();
    }
}

// Get income categories
$sql = "SELECT id, name FROM categories WHERE user_id = ? AND type = 'income' ORDER BY name";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$categories_result = $stmt->get_result();
$stmt->close();

// Get income records with category names
$sql = "SELECT i.id, i.amount, i.description, i.date, c.name as category_name 
        FROM income i 
        JOIN categories c ON i.category_id = c.id 
        WHERE i.user_id = ? 
        ORDER BY i.date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$income_result = $stmt->get_result();
$stmt->close();

// Calculate total income
$sql = "SELECT SUM(amount) as total FROM income WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_result = $stmt->get_result();
$total_income = $total_result->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Get monthly income summary
$sql = "SELECT 
            YEAR(date) as year,
            MONTH(date) as month,
            SUM(amount) as monthly_total
        FROM income 
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

// Get income by category
$sql = "SELECT 
            c.name as category_name,
            SUM(i.amount) as category_total
        FROM income i
        JOIN categories c ON i.category_id = c.id
        WHERE i.user_id = ?
        GROUP BY i.category_id
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
    <title>Income - FinanceTrack</title>
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
                <h1 class="logo">Finance Track</h1>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="index.php" class="nav-item"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                    <li><a href="income.php" class="nav-item active"><i class="fas fa-coins"></i> Income</a></li>
                    <li><a href="expenses.php" class="nav-item"><i class="fas fa-receipt"></i> Expenses</a></li>
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
                    <h2>Income Management</h2>
                    <p>Manage and track your income sources</p>
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
                
                <div class="income-dashboard">
                    <div class="summary-card total-card">
                        <div class="card-icon">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <div class="card-content">
                            <h3>Total Income</h3>
                            <p class="amount">$<?php echo number_format($total_income, 2); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="income-content">
                    <!-- Income Form -->
                    <div class="income-form-container">
                        <div class="section-header">
                            <h3>Add New Income</h3>
                        </div>
                        <form class="income-form" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
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
                                    <input type="text" id="description" name="description" placeholder="Description of income">
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="submit" name="add_income" class="btn primary-btn">Add Income</button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Income Charts -->
                    <div class="income-charts">
                        <div class="chart-container">
                            <div class="chart-card">
                                <h3>Monthly Income</h3>
                                <canvas id="monthly-income-chart" width="400" height="400"></canvas>
                            </div>
                            <div class="chart-card">
                                <h3>Income by Category</h3>
                                <canvas id="category-income-chart" width="400" height="400"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Income List -->
                <div class="income-list-container">
                    <div class="section-header">
                        <h3>Income History</h3>
                    </div>
                    
                    <div class="income-list">
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
                                <?php if ($income_result->num_rows > 0): ?>
                                    <?php while ($income = $income_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($income['date'])); ?></td>
                                        <td><?php echo htmlspecialchars($income['category_name']); ?></td>
                                        <td><?php echo htmlspecialchars($income['description'] ?? 'N/A'); ?></td>
                                        <td class="amount">$<?php echo number_format($income['amount'], 2); ?></td>
                                        <td class="actions">
                                            <a href="edit_income.php?id=<?php echo $income['id']; ?>" class="btn-icon edit-btn" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?delete_id=<?php echo $income['id']; ?>" 
                                               class="btn-icon delete-btn" 
                                               title="Delete"
                                               onclick="return confirm('Are you sure you want to delete this income record?');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="no-records">No income records found</td>
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
            // Monthly Income Chart
            const monthlyCtx = document.getElementById('monthly-income-chart').getContext('2d');
            const monthlyChart = new Chart(monthlyCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($months); ?>,
                    datasets: [{
                        label: 'Monthly Income',
                        data: <?php echo json_encode($monthly_totals); ?>,
                        backgroundColor: 'rgba(12, 206, 107, 0.7)',
                        borderColor: 'rgba(12, 206, 107, 1)',
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
            
            // Category Income Chart
            const categoryCtx = document.getElementById('category-income-chart').getContext('2d');
            const categoryChart = new Chart(categoryCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($category_names); ?>,
                    datasets: [{
                        data: <?php echo json_encode($category_totals); ?>,
                        backgroundColor: [
                            'rgba(4, 102, 200, 0.7)',
                            'rgba(12, 206, 107, 0.7)',
                            'rgba(255, 159, 28, 0.7)',
                            'rgba(230, 57, 70, 0.7)',
                            'rgba(152, 67, 230, 0.7)',
                            'rgba(58, 134, 255, 0.7)'
                        ],
                        borderColor: [
                            'rgba(4, 102, 200, 1)',
                            'rgba(12, 206, 107, 1)',
                            'rgba(255, 159, 28, 1)',
                            'rgba(230, 57, 70, 1)',
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
        });
    </script>
</body>
</html>