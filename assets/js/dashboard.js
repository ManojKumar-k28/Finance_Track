document.addEventListener('DOMContentLoaded', () => {
  const incomeElement = document.getElementById('total-income');
  const expensesElement = document.getElementById('total-expenses');
  const balanceElement = document.getElementById('balance');
  const savingsRateElement = document.getElementById('savings-rate');
  const incomeChangeElement = document.getElementById('income-change');
  const expenseChangeElement = document.getElementById('expense-change');
  const balanceChangeElement = document.getElementById('balance-change');
  const savingsChangeElement = document.getElementById('savings-change');
  const recentTransactionsContainer = document.getElementById('recent-transactions');

  fetch('index.php')
    .then(response => response.json())
    .then(data => {
      incomeElement.textContent = data.total_income.toFixed(2);
      expensesElement.textContent = data.total_expenses.toFixed(2);
      balanceElement.textContent = data.balance.toFixed(2);
      savingsRateElement.textContent = data.savings_rate.toFixed(2);

      incomeChangeElement.textContent = `${data.income_change_percentage.toFixed(2)}%`;
      expenseChangeElement.textContent = `${data.expense_change_percentage.toFixed(2)}%`;
      balanceChangeElement.textContent = `${data.balance_change_percentage.toFixed(2)}%`;
      savingsChangeElement.textContent = `${data.savings_change_percentage.toFixed(2)}%`;

      // Render charts
      renderCharts(data);
    })
    .catch(error => console.error('Dashboard data error:', error));

  function renderCharts(data) {
    // Income vs Expenses (Line Chart)
    const ctx1 = document.getElementById('income-expense-chart').getContext('2d');
    new Chart(ctx1, {
      type: 'line',
      data: {
        labels: data.months,
        datasets: [
          {
            label: 'Income',
            data: data.income_totals,
            borderColor: '#4caf50',
            backgroundColor: 'rgba(76, 175, 80, 0.2)',
            tension: 0.3,
            fill: true
          },
          {
            label: 'Expenses',
            data: data.expense_totals,
            borderColor: '#f44336',
            backgroundColor: 'rgba(244, 67, 54, 0.2)',
            tension: 0.3,
            fill: true
          }
        ]
      },
      options: {
        responsive: true,
        scales: {
          y: {
            beginAtZero: true
          }
        }
      }
    });

    // Expense Breakdown (Pie Chart)
    const ctx2 = document.getElementById('expense-breakdown-chart').getContext('2d');
    new Chart(ctx2, {
      type: 'pie',
      data: {
        labels: data.expense_categories.map(c => c.name),
        datasets: [{
          data: data.expense_categories.map(c => c.amount),
          backgroundColor: data.expense_categories.map(() => getRandomColor())
        }]
      },
      options: {
        responsive: true
      }
    });
  }

  function getRandomColor() {
    const letters = '0123456789ABCDEF';
    let color = '#';
    for (let i = 0; i < 6; i++) {
      color += letters[Math.floor(Math.random() * 16)];
    }
    return color;
  }
});
