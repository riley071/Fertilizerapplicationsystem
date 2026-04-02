// Chart.js: Orders Chart
new Chart(document.getElementById('ordersChart'), {
    type: 'line',
    data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
        datasets: [{
            label: 'Orders',
            data: [40, 60, 80, 100, 120, 140],
            borderColor: '#388e3c',
            backgroundColor: 'rgba(56, 142, 60, 0.2)',
            fill: true,
            tension: 0.4
        }]
    }
});

// Chart.js: Region Pie
new Chart(document.getElementById('regionChart'), {
    type: 'doughnut',
    data: {
        labels: ['North', 'Central', 'South'],
        datasets: [{
            data: [35, 45, 20],
            backgroundColor: ['#81c784', '#66bb6a', '#388e3c']
        }]
    }
});
