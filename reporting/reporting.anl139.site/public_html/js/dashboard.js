document.addEventListener('DOMContentLoaded', () => {
  const dataEl = document.getElementById('dashboard-data');
  const dashboardData = dataEl ? JSON.parse(dataEl.textContent || '{}') : {};

  const activityCounts = dashboardData.activityCounts || [];
  const navTiming = dashboardData.navTiming || [];

  const sidebarLinks = document.querySelectorAll('.sidebar a');
  const tabs = document.querySelectorAll('.tab-content');

  function showTab(tabId) {
    sidebarLinks.forEach(link => {
      link.classList.toggle('active', link.dataset.tab === tabId);
    });

    tabs.forEach(tab => {
      tab.style.display = (tab.id === tabId) ? 'block' : 'none';
    });
  }

  sidebarLinks.forEach(link => {
    link.addEventListener('click', e => {
      e.preventDefault();
      showTab(link.dataset.tab);
    });
  });

  const logoutBtn = document.getElementById('logoutBtn');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', () => {
      window.location.href = '/logout.php';
    });
  }

  function initNavChart() {
    const canvas = document.getElementById('navChart');
    if (!canvas) return;

    new Chart(canvas, {
      type: 'bar',
      data: {
        labels: navTiming.map((_, i) => 'Log ' + (i + 1)),
        datasets: [
          { label: 'DOM Interactive', data: navTiming.map(n => n.domContentLoaded) },
          { label: 'Total Load', data: navTiming.map(n => n.loadTime) }
        ]
      }
    });
  }

  function initActivityChart() {
    const canvas = document.getElementById('activityChart');
    if (!canvas) return;

    new Chart(canvas, {
      type: 'bar',
      data: {
        labels: activityCounts.map((_, i) => 'Log ' + (i + 1)),
        datasets: [
          { label: 'Clicks', data: activityCounts.map(a => a.clicks) },
          { label: 'MouseMoves', data: activityCounts.map(a => a.mouseMoves) },
          { label: 'Errors', data: activityCounts.map(a => a.errors) },
          { label: 'Keys', data: activityCounts.map(a => a.keys)}
        ]
      }
    });
  }
  async function loadPdfLib() {
  if (window.html2pdf) return;

  const script = document.createElement('script');
  script.src = "https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js";
  script.defer = true;

  document.body.appendChild(script);

  await new Promise(resolve => {
    script.onload = resolve;
  });
}

  async function exportPDF(tabId) {
  await loadPdfLib();

  const el = document.getElementById(tabId);
  if (!el) return;

  const hiddenTabs = document.querySelectorAll('.tab-content');
  const previousStates = new Map();

  hiddenTabs.forEach(tab => {
    previousStates.set(tab, tab.style.display);
    if (tab.id !== tabId) {
      tab.style.display = 'none';
    }
  });

  html2pdf()
    .set({
      margin: 0.5,
      filename: tabId + '_report.pdf',
      html2canvas: { scale: 2 },
      jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' }
    })
    .from(el)
    .save()
    .then(() => {
      hiddenTabs.forEach(tab => {
        tab.style.display = previousStates.get(tab) || '';
      });
    });
}
