import Chart from 'chart.js/auto';

function setupDashboard() {
    const element = document.getElementById('manager-chart-data');
    if (!element) return;
    const data = JSON.parse(element.textContent);
    const isDark = document.documentElement.classList.contains('dark');
    const gridColor = isDark ? '#343843' : '#e3e3e0';
    const textColor = isDark ? '#a0a0a0' : '#706f6c';

    const labels = data.labels;
    const kwhData = data.kwhData;
    const revenueData = data.revenueData;
    const chargerData = data.chargerData;
    const statusData = data.statusData;

    new Chart(document.getElementById('energyChart'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'kWh',
                data: kwhData,
                borderColor: '#e53935',
                backgroundColor: 'rgba(229, 57, 53, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 3,
                pointBackgroundColor: '#e53935',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: gridColor }, ticks: { color: textColor, maxTicksLimit: 10 } },
                y: { grid: { color: gridColor }, ticks: { color: textColor }, beginAtZero: true }
            }
        }
    });

    new Chart(document.getElementById('revenueChart'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'R$',
                data: revenueData,
                backgroundColor: 'rgba(229, 57, 53, 0.7)',
                borderColor: '#e53935',
                borderWidth: 1,
                borderRadius: 4,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: gridColor }, ticks: { color: textColor, maxTicksLimit: 10 } },
                y: { grid: { color: gridColor }, ticks: { color: textColor }, beginAtZero: true }
            }
        }
    });

    const chargerColors = ['#e53935', '#8b5cf6', '#f59e0b', '#10b981', '#3b82f6', '#ec4899'];

    new Chart(document.getElementById('chargerChart'), {
        type: 'pie',
        data: {
            labels: chargerData.map(c => c.charger_name),
            datasets: [{
                data: chargerData.map(c => c.total),
                backgroundColor: chargerColors.slice(0, chargerData.length),
                borderWidth: 0,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { color: textColor, padding: 16 } }
            }
        }
    });

    const statusColors = {
        completed: '#10b981',
        pending: '#f59e0b',
        cancelled: '#ef4444'
    };

    const statusLabels = {
        completed: 'Concluida',
        pending: 'Pendente',
        cancelled: 'Cancelada'
    };

    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: statusData.map(s => statusLabels[s.status] || s.status),
            datasets: [{
                data: statusData.map(s => s.total),
                backgroundColor: statusData.map(s => statusColors[s.status] || '#6b7280'),
                borderWidth: 0,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { color: textColor, padding: 16 } }
            }
        }
    });
    document.addEventListener('portal:themechange', () => {
        const dark = document.documentElement.classList.contains('dark');
        Object.values(Chart.instances).forEach(chart => {
            if (chart.options.scales) Object.values(chart.options.scales).forEach(scale => {
                if (scale.grid) scale.grid.color = dark ? '#2c303c' : '#e5e7ed';
                if (scale.ticks) scale.ticks.color = dark ? '#acb1bf' : '#656b78';
            });
            if (chart.options.plugins.legend.labels) chart.options.plugins.legend.labels.color = dark ? '#acb1bf' : '#656b78';
            chart.update('none');
        });
    });
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', setupDashboard);
else setupDashboard();
