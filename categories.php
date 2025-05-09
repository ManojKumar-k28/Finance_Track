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

// Handle category form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_category'])) {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
    
    if (empty($name)) {
        $error = "Category name is required";
    } elseif (!in_array($type, ['income', 'expense'])) {
        $error = "Invalid category type";
    } else {
        // Check if category already exists
        $sql = "SELECT id FROM categories WHERE user_id = ? AND name = ? AND type = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $user_id, $name, $type);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "A category with this name already exists";
        } else {
            // Insert new category
            $sql = "INSERT INTO categories (user_id, name, description, type) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isss", $user_id, $name, $description, $type);
            
            if ($stmt->execute()) {
                $message = "Category added successfully!";
            } else {
                $error = "Error adding category: " . $conn->error;
            }
        }
        
        $stmt->close();
    }
}

// Handle category deletion
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['delete_id'])) {
    $category_id = filter_input(INPUT_GET, 'delete_id', FILTER_VALIDATE_INT);
    
    if ($category_id) {
        // Check if category is in use
        $sql = "SELECT COUNT(*) as count FROM income WHERE category_id = ? AND user_id = ?
                UNION ALL
                SELECT COUNT(*) as count FROM expenses WHERE category_id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiii", $category_id, $user_id, $category_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row1 = $result->fetch_assoc();
        $row2 = $result->fetch_assoc();
        $count = ($row1['count'] ?? 0) + ($row2['count'] ?? 0);
        
        if ($count > 0) {
            $error = "Cannot delete category because it's being used in transactions";
        } else {
            // Delete category
            $sql = "DELETE FROM categories WHERE id = ? AND user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $category_id, $user_id);
            
            if ($stmt->execute()) {
                $message = "Category deleted successfully!";
            } else {
                $error = "Error deleting category: " . $conn->error;
            }
        }
        
        $stmt->close();
    }
}

// Get income categories
$sql = "SELECT id, name, description FROM categories WHERE user_id = ? AND type = 'income' ORDER BY name";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$income_categories = $stmt->get_result();
$stmt->close();

// Get expense categories
$sql = "SELECT id, name, description FROM categories WHERE user_id = ? AND type = 'expense' ORDER BY name";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$expense_categories = $stmt->get_result();
$stmt->close();

// Count usage of each category
function getCategoryUsage($conn, $category_id, $user_id) {
    $sql = "SELECT COUNT(*) as income_count FROM income WHERE category_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $category_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $income_count = $result->fetch_assoc()['income_count'] ?? 0;
    $stmt->close();
    
    $sql = "SELECT COUNT(*) as expense_count FROM expenses WHERE category_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $category_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $expense_count = $result->fetch_assoc()['expense_count'] ?? 0;
    $stmt->close();
    
    return $income_count + $expense_count;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - FinanceTrack</title>
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
                    <li><a href="categories.php" class="nav-item active"><i class="fas fa-tags"></i> Categories</a></li>
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
                    <h2>Manage Categories</h2>
                    <p>Create and manage categories for your income and expenses</p>
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
                
                <div class="categories-content">
                    <!-- Add Category Form -->
                    <div class="category-form-container">
                        <div class="section-header">
                            <h3>Add New Category</h3>
                        </div>
                        <form class="category-form" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="name">Category Name</label>
                                    <input type="text" id="name" name="name" placeholder="Enter category name" required>
                                </div>
                                <div class="form-group">
                                    <label for="type">Category Type</label>
                                    <select id="type" name="type" required>
                                        <option value="">Select type</option>
                                        <option value="income">Income</option>
                                        <option value="expense">Expense</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="description">Description (Optional)</label>
                                    <textarea id="description" name="description" placeholder="Brief description of this category"></textarea>
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="submit" name="add_category" class="btn primary-btn">Add Category</button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Categories Lists -->
                    <div class="categories-lists">
                        <!-- Income Categories -->
                        <div class="category-list-container">
                            <div class="section-header">
                                <h3>Income Categories</h3>
                            </div>
                            <div class="category-list">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Category Name</th>
                                            <th>Description</th>
                                            <th>Usage</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($income_categories->num_rows > 0): ?>
                                            <?php while ($category = $income_categories->fetch_assoc()): 
                                                $usage_count = getCategoryUsage($conn, $category['id'], $user_id);
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($category['name']); ?></td>
                                                <td><?php echo htmlspecialchars($category['description'] ?? 'N/A'); ?></td>
                                                <td><?php echo $usage_count; ?> transaction<?php echo $usage_count != 1 ? 's' : ''; ?></td>
                                                <td class="actions">
                                                    <a href="edit_category.php?id=<?php echo $category['id']; ?>" class="btn-icon edit-btn" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if ($usage_count == 0): ?>
                                                    <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?delete_id=<?php echo $category['id']; ?>" 
                                                       class="btn-icon delete-btn" 
                                                       title="Delete"
                                                       onclick="return confirm('Are you sure you want to delete this category?');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="no-records">No income categories found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Expense Categories -->
                        <div class="category-list-container">
                            <div class="section-header">
                                <h3>Expense Categories</h3>
                            </div>
                            <div class="category-list">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Category Name</th>
                                            <th>Description</th>
                                            <th>Usage</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($expense_categories->num_rows > 0): ?>
                                            <?php while ($category = $expense_categories->fetch_assoc()): 
                                                $usage_count = getCategoryUsage($conn, $category['id'], $user_id);
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($category['name']); ?></td>
                                                <td><?php echo htmlspecialchars($category['description'] ?? 'N/A'); ?></td>
                                                <td><?php echo $usage_count; ?> transaction<?php echo $usage_count != 1 ? 's' : ''; ?></td>
                                                <td class="actions">
                                                    <a href="edit_category.php?id=<?php echo $category['id']; ?>" class="btn-icon edit-btn" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if ($usage_count == 0): ?>
                                                    <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?delete_id=<?php echo $category['id']; ?>" 
                                                       class="btn-icon delete-btn" 
                                                       title="Delete"
                                                       onclick="return confirm('Are you sure you want to delete this category?');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="no-records">No expense categories found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add fade-in animation to category tables
            const categoryLists = document.querySelectorAll('.category-list-container');
            categoryLists.forEach((list, index) => {
                list.style.opacity = '0';
                list.style.transform = 'translateY(20px)';
                list.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                
                setTimeout(() => {
                    list.style.opacity = '1';
                    list.style.transform = 'translateY(0)';
                }, 200 + (index * 200));
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