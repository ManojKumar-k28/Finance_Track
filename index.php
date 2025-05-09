<?php
session_start();
require_once 'config/db.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch totals for income and expenses for the logged-in user
$income_total_result = $conn->query("SELECT SUM(amount) AS total FROM income WHERE user_id = $user_id");
$total_income = $income_total_result->fetch_assoc()['total'] ?? 0;

$expense_total_result = $conn->query("SELECT SUM(amount) AS total FROM expenses WHERE user_id = $user_id");
$total_expenses = $expense_total_result->fetch_assoc()['total'] ?? 0;

$balance = $total_income - $total_expenses;
$savings_rate = ($total_income > 0) ? (($balance / $total_income) * 100) : 0;

// Fetch unified monthly income and expense totals
$months_map = [];

$income_monthly = $conn->query("
    SELECT DATE_FORMAT(date, '%b') AS month, MONTH(date) AS month_num, SUM(amount) AS total
    FROM income
    WHERE user_id = $user_id
    GROUP BY MONTH(date)
");

while ($row = $income_monthly->fetch_assoc()) {
    $month = $row['month'];
    $months_map[$month]['income'] = (float)$row['total'];
    $months_map[$month]['month_num'] = $row['month_num'];
}

$expense_monthly = $conn->query("
    SELECT DATE_FORMAT(date, '%b') AS month, MONTH(date) AS month_num, SUM(amount) AS total
    FROM expenses
    WHERE user_id = $user_id
    GROUP BY MONTH(date)
");

while ($row = $expense_monthly->fetch_assoc()) {
    $month = $row['month'];
    $months_map[$month]['expenses'] = (float)$row['total'];
    $months_map[$month]['month_num'] = $row['month_num'];
}

// Sort months by numerical order
usort($months_map, fn($a, $b) => $a['month_num'] <=> $b['month_num']);

$months = [];
$income_totals = [];
$expense_totals = [];

foreach ($months_map as $month_name => $data) {
    $months[] = $month_name;
    $income_totals[] = $data['income'] ?? 0;
    $expense_totals[] = $data['expenses'] ?? 0;
}

// Expense breakdown by category
$expense_cat_query = $conn->query("
    SELECT c.name, SUM(e.amount) AS amount
    FROM expenses e
    JOIN categories c ON e.category_id = c.id
    WHERE e.user_id = $user_id
    GROUP BY e.category_id
");

$expense_categories = [];
while ($row = $expense_cat_query->fetch_assoc()) {
    $expense_categories[] = ['name' => $row['name'], 'amount' => (float)$row['amount']];
}

// Income breakdown by category
$income_cat_query = $conn->query("
    SELECT c.name, SUM(i.amount) AS amount
    FROM income i
    JOIN categories c ON i.category_id = c.id
    WHERE i.user_id = $user_id
    GROUP BY i.category_id
");

$income_categories = [];
while ($row = $income_cat_query->fetch_assoc()) {
    $income_categories[] = ['name' => $row['name'], 'amount' => (float)$row['amount']];
}

// Recent transactions
$recent_transactions = [];

$recent_income = $conn->query("
    SELECT 'Income' AS type, amount, date, description 
    FROM income 
    WHERE user_id = $user_id 
    ORDER BY date DESC LIMIT 5
");

while ($row = $recent_income->fetch_assoc()) $recent_transactions[] = $row;

$recent_expenses = $conn->query("
    SELECT 'Expense' AS type, amount, date, description 
    FROM expenses 
    WHERE user_id = $user_id 
    ORDER BY date DESC LIMIT 5
");

while ($row = $recent_expenses->fetch_assoc()) $recent_transactions[] = $row;

usort($recent_transactions, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));
$recent_transactions = array_slice($recent_transactions, 0, 5);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FinanceTrack - Dashboard</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script type="text/javascript">
        const balance = <?php echo json_encode($balance); ?>;
        const savingsRate = <?php echo json_encode($savings_rate); ?>;
        const totalIncome = <?php echo json_encode($total_income); ?>;
        const totalExpenses = <?php echo json_encode($total_expenses); ?>;

        function updateBalanceAndSavings() {
            document.getElementById("balance").innerText = `$${balance.toFixed(2)}`;
            document.getElementById("savings-rate").innerText = savingsRate.toFixed(2);
        }
        window.onload = updateBalanceAndSavings;
    </script>
</head>
<body>
<?php if(isset($_SESSION['user_id'])): ?>
<div class="app-container">
    <!-- Sidebar Navigation -->
    <aside class="sidebar">
        <div class="sidebar-header"><h1 class="logo">Finance Track</h1></div>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="index.php" class="nav-item active"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                <li><a href="income.php" class="nav-item"><i class="fas fa-coins"></i> Income</a></li>
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

    <!-- Main Content -->
    <main class="main-content">
        <header class="top-nav">
            <div class="menu-toggle"><i class="fas fa-bars"></i></div>
            <div class="user-info">Welcome,<?php echo $_SESSION['username'] ?? 'User'; ?></div>
        </header>

        <div class="content-container">
            <div class="dashboard-header">
                <h2>Financial Dashboard</h2>
                <div class="period-selector">
                    <button class="period-btn active" data-period="month">Month</button>
                    <button class="period-btn" data-period="quarter">Quarter</button>
                    <button class="period-btn" data-period="year">Year</button>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="summary-cards">
                <div class="card income-card">
                    <div class="card-icon"><i class="fas fa-arrow-up"></i></div>
                    <div class="card-content">
                        <h3>Total Income</h3>
                        <p class="amount">$<span id="total-income"><?php echo number_format($total_income, 2); ?></span></p>
                    </div>
                </div>
                <div class="card expense-card">
                    <div class="card-icon"><i class="fas fa-arrow-down"></i></div>
                    <div class="card-content">
                        <h3>Total Expenses</h3>
                        <p class="amount">$<span id="total-expenses"><?php echo number_format($total_expenses, 2); ?></span></p>
                    </div>
                </div>
                <div class="card balance-card">
                    <div class="card-icon"><i class="fas fa-wallet"></i></div>
                    <div class="card-content">
                        <h3>Balance</h3>
                        <p class="amount"><span id="balance">0.00</span></p>
                    </div>
                </div>
                <div class="card savings-card">
                    <div class="card-icon"><i class="fas fa-piggy-bank"></i></div>
                    <div class="card-content">
                        <h3>Savings Rate</h3>
                        <p class="amount"><span id="savings-rate">0</span>%</p>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="chart-container">
                <div class="chart-card">
                    <h3>Income vs Expenses</h3>
                    <canvas id="incomeExpenseChart" width="400" height="400"></canvas>
                </div>
                <div class="chart-card">
                    <h3>Income & Expense by Categories</h3>
                    <canvas id="categoryPieChart" width="400" height="400"></canvas>
                </div>
            </div>

            <div class="transactions-container">
                <div class="section-header">
                    <h3>Recent Transactions</h3>
                    <a href="#" class="view-all">View All</a>
                </div>

                <div class="transactions-list" id="recent-transactions">
                    <?php foreach ($recent_transactions as $transaction): ?>
                        <div class="transaction-item <?php echo strtolower($transaction['type']); ?>-transaction">
                            <div class="transaction-icon">
                                <i class="fas <?php echo $transaction['type'] === 'Income' ? 'fa-arrow-up' : 'fa-arrow-down'; ?>"></i>
                            </div>
                            <div class="transaction-details">
                                <p class="transaction-description"><?php echo $transaction['description']; ?></p>
                                <p class="transaction-date"><?php echo date("M d, Y", strtotime($transaction['date'])); ?></p>
                            </div>
                            <div class="transaction-amount">
                                $<?php echo number_format($transaction['amount'], 2); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Charts JS -->
<script type="text/javascript">
    // Income vs Expense Bar Chart
    const ctx1 = document.getElementById('incomeExpenseChart').getContext('2d');
    const incomeExpenseChart = new Chart(ctx1, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($months); ?>,
            datasets: [
                {
                    label: 'Income',
                    data: <?php echo json_encode($income_totals); ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.5)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Expenses',
                    data: <?php echo json_encode($expense_totals); ?>,
                    backgroundColor: 'rgba(255, 99, 132, 0.5)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                }
            ]
        }
    });

    // Combined Category Pie Chart
    const ctx2 = document.getElementById('categoryPieChart').getContext('2d');
    const categoryPieChart = new Chart(ctx2, {
        type: 'pie',
        data: {
            labels: [
                ...<?php echo json_encode(array_map(fn($i) => "Income: " . $i['name'], $income_categories)); ?>,
                ...<?php echo json_encode(array_map(fn($e) => "Expense: " . $e['name'], $expense_categories)); ?>
            ],
            datasets: [{
                data: [
                    ...<?php echo json_encode(array_column($income_categories, 'amount')); ?>,
                    ...<?php echo json_encode(array_column($expense_categories, 'amount')); ?>
                ],
                backgroundColor: [
                    'rgba(54, 162, 235, 0.5)',
                    'rgba(75, 192, 192, 0.5)',
                    'rgba(153, 102, 255, 0.5)',
                    'rgba(255, 159, 64, 0.5)',
                    'rgba(255, 99, 132, 0.5)',
                    'rgba(255, 206, 86, 0.5)',
                    'rgba(201, 203, 207, 0.5)',
                    'rgba(255, 99, 71, 0.5)'
                ],
                borderColor: '#fff',
                borderWidth: 1
            }]
        }
    });
</script>
<?php endif; ?>
</body>
</html>
