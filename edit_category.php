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
$category = null;

// Get category ID from URL
$category_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$category_id) {
    header("Location: categories.php");
    exit();
}

// Get category data
$sql = "SELECT * FROM categories WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $category_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows != 1) {
    header("Location: categories.php");
    exit();
}

$category = $result->fetch_assoc();
$stmt->close();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_category'])) {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    
    if (empty($name)) {
        $error = "Category name is required";
    } else {
        // Check if another category with the same name exists
        $sql = "SELECT id FROM categories WHERE user_id = ? AND name = ? AND type = ? AND id != ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issi", $user_id, $name, $category['type'], $category_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "A category with this name already exists";
        } else {
            // Update category
            $sql = "UPDATE categories SET name = ?, description = ? WHERE id = ? AND user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssii", $name, $description, $category_id, $user_id);
            
            if ($stmt->execute()) {
                $message = "Category updated successfully!";
                // Refresh category data
                $sql = "SELECT * FROM categories WHERE id = ? AND user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $category_id, $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $category = $result->fetch_assoc();
            } else {
                $error = "Error updating category: " . $conn->error;
            }
        }
        
        $stmt->close();
    }
}

// Count usage of this category
function getCategoryUsage($conn, $category_id, $user_id, $type) {
    if ($type == 'income') {
        $sql = "SELECT COUNT(*) as count FROM income WHERE category_id = ? AND user_id = ?";
    } else {
        $sql = "SELECT COUNT(*) as count FROM expenses WHERE category_id = ? AND user_id = ?";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $category_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'] ?? 0;
    $stmt->close();
    
    return $count;
}

$usage_count = getCategoryUsage($conn, $category_id, $user_id, $category['type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Category - FinanceTrack</title>
    <link rel="stylesheet" href="assets/css/styles.css">
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
                    <h2>Edit Category</h2>
                    <p>Update category details</p>
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
                
                <div class="edit-category-container">
                    <div class="category-info">
                        <p><strong>Category Type:</strong> <?php echo ucfirst($category['type']); ?></p>
                        <p><strong>Usage:</strong> <?php echo $usage_count; ?> transaction<?php echo $usage_count != 1 ? 's' : ''; ?></p>
                    </div>
                    
                    <form class="edit-form" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . $category_id; ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Category Name</label>
                                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($category['name']); ?>" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="description">Description (Optional)</label>
                                <textarea id="description" name="description" placeholder="Brief description of this category"><?php echo htmlspecialchars($category['description'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="update_category" class="btn primary-btn">Update Category</button>
                            <a href="categories.php" class="btn secondary-btn">Cancel</a>
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
            const formElements = document.querySelectorAll('.form-group, .form-actions, .category-info');
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