(function () {
  document.addEventListener('submit', function (e) { var f = e.target; if (f.matches && f.matches('form[data-confirm]') && !f.dataset.ok) { e.preventDefault(); if (window.confirm(f.getAttribute('data-confirm'))) { f.dataset.ok = '1'; f.submit(); } } });
  var el = document.getElementById('chartData'); if (!el || !window.Chart) return;
  var d = JSON.parse(el.textContent), pal = ['#2563eb', '#f59e0b', '#16a34a', '#db2777', '#7c3aed', '#0891b2', '#ea580c', '#64748b'];
  new Chart(document.getElementById('chRev'), { data: { labels: d.labels, datasets: [
    { type: 'bar', label: 'Revenue (₹)', data: d.revenue, backgroundColor: '#f59e0b', yAxisID: 'y' },
    { type: 'line', label: 'Orders', data: d.orders, borderColor: '#1f2a44', backgroundColor: '#1f2a44', yAxisID: 'y1', tension: .3 }] },
    options: { responsive: true, interaction: { mode: 'index', intersect: false }, scales: { y: { beginAtZero: true, title: { display: true, text: '₹' } }, y1: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, ticks: { precision: 0 } } } } });
  new Chart(document.getElementById('chStatus'), { type: 'doughnut', data: { labels: d.status.labels, datasets: [{ data: d.status.data, backgroundColor: pal }] }, options: { plugins: { legend: { position: 'bottom' } } } });
  new Chart(document.getElementById('chCat'), { type: 'bar', data: { labels: d.cat.labels, datasets: [{ label: 'Revenue (₹)', data: d.cat.data, backgroundColor: '#2563eb' }] }, options: { indexAxis: 'y', plugins: { legend: { display: false } } } });
})();
