const menuToggle = document.querySelector('.menu-toggle');
const sidebar = document.querySelector('.sidebar');
const mainContent = document.querySelector('.main-content');

if (menuToggle) {
  menuToggle.addEventListener('click', () => {
    sidebar.classList.toggle('open');
    mainContent.style.marginLeft = sidebar.classList.contains('open') ? '250px' : '0';
  });
}

document.addEventListener('click', (e) => {
  if (window.innerWidth <= 768 && sidebar.classList.contains('open') &&
      !sidebar.contains(e.target) && e.target !== menuToggle) {
    sidebar.classList.remove('open');
    mainContent.style.marginLeft = '0';
  }
});

window.financeApp = {
  formatCurrency(amount, currency = 'USD') {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency,
      minimumFractionDigits: 2
    }).format(amount);
  },
  formatDate(date, format = 'medium') {
    const dateObj = new Date(date);
    const options = {
      short: { month: 'numeric', day: 'numeric', year: '2-digit' },
      medium: { month: 'short', day: 'numeric', year: 'numeric' },
      long: { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }
    };
    return dateObj.toLocaleDateString('en-US', options[format]);
  },
  showNotification(message, type = 'info', duration = 3000) {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `<span>${message}</span><button onclick="this.parentElement.remove()">×</button>`;
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), duration);
  },
  async makeRequest(url, method = 'GET', data = null) {
    try {
      const options = {
        method,
        headers: { 'Content-Type': 'application/json' }
      };
      if (data && (method === 'POST' || method === 'PUT')) {
        options.body = JSON.stringify(data);
      }
      const response = await fetch(url, options);
      if (!response.ok) throw new Error(`Error: ${response.status}`);
      return await response.json();
    } catch (err) {
      this.showNotification(err.message, 'error');
      throw err;
    }
  }
};
