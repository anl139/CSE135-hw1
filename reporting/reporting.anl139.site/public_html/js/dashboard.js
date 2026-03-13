(() => {
  'use strict';

  let navChart = null;
  let activityChart = null;

  function readDashboardData() {
    const el = document.getElementById('dashboard-data');
    if (!el) {
      return { activityCounts: [], navTiming: [] };
    }

    try {
      const parsed = JSON.parse(el.textContent || '{}');
      return {
        activityCounts: parsed.activityCounts ?? [],
        navTiming: parsed.navTiming ?? []
      };
    } catch {
      return { activityCounts: [], navTiming: [] };
    }
  }

  // Preserve the PHP-generated variables as JS constants
  const { activityCounts, navTiming } = readDashboardData();

  function normalizeSeries(input, defaultLabelPrefix) {
    if (Array.isArray(input)) {
      if (input.length && typeof input[0] === 'object' && input[0] !== null) {
        const labels = input.map((item, i) =>
          item.label ?? item.name ?? item.section ?? `Item ${i + 1}`
        );
        const values = input.map((item) =>
          Number(item.value ?? item.count ?? item.time ?? item.ms ?? 0)
        );
        return { labels, values };
      }

      return {
        labels: input.map((_, i) => `${defaultLabelPrefix} ${i + 1}`),
        values: input.map((v) => Number(v) || 0)
      };
    }

    if (input && typeof input === 'object') {
      const labels = Object.keys(input);
      const values = Object.values(input).map((v) => Number(v) || 0);
      return { labels, values };
    }

    return { labels: [], values: [] };
  }

  function showTab(tabId) {
    const tabs = document.querySelectorAll('.tab-content');
    const buttons = document.querySelectorAll('.tab-button');

    tabs.forEach((tab) => {
      tab.classList.toggle('active', tab.id === tabId);
    });

    buttons.forEach((button) => {
      button.classList.toggle('active', button.dataset.tab === tabId);
    });

    // If the user switches to a chart tab, ensure Chart.js sizes correctly.
    requestAnimationFrame(() => {
      if (navChart) navChart.resize();
      if (activityChart) activityChart.resize();
    });
  }

  function initCharts() {
    const navCanvas = document.getElementById('navChart');
    const activityCanvas = document.getElementById('activityChart');

    if (navCanvas) {
      const navSeries = normalizeSeries(navTiming, 'Nav');

      navChart = new Chart(navCanvas, {
        type: 'line',
        data: {
          labels: navSeries.labels,
          datasets: [{
            label: 'Navigation Timing',
            data: navSeries.values,
            tension: 0.3,
            fill: false,
            borderWidth: 2
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: true }
          },
          scales: {
            y: {
              beginAtZero: true
            }
          }
        }
      });
    }

    if (activityCanvas) {
      const activitySeries = normalizeSeries(activityCounts, 'Activity');

      activityChart = new Chart(activityCanvas, {
        type: 'bar',
        data: {
          labels: activitySeries.labels,
          datasets: [{
            label: 'Activity Counts',
            data: activitySeries.values,
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: true }
          },
          scales: {
            y: {
              beginAtZero: true
            }
          }
        }
      });
    }
  }

  function exportPDF(tabId) {
    const target = document.getElementById(tabId);
    if (!target) return;

    const hiddenTabs = Array.from(document.querySelectorAll('.tab-content'))
      .filter((tab) => tab.id !== tabId);

    hiddenTabs.forEach((tab) => {
      tab.dataset.prevDisplay = tab.style.display;
      tab.style.display = 'none';
    });

    const restore = () => {
      hiddenTabs.forEach((tab) => {
        tab.style.display = tab.dataset.prevDisplay || '';
        delete tab.dataset.prevDisplay;
      });
    };

    try {
      if (typeof window.html2pdf !== 'function') {
        restore();
        alert('PDF export is not available because html2pdf is not loaded.');
        return;
      }

      const options = {
        margin: 0.5,
        filename: `${tabId}_report.pdf`,
        html2canvas: { scale: 2 },
        jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
      };

      Promise.resolve(window.html2pdf().set(options).from(target).save())
        .then(restore)
        .catch(() => restore());
    } catch {
      restore();
      throw new Error('PDF export failed.');
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.tab-button').forEach((button) => {
      button.addEventListener('click', () => {
        showTab(button.dataset.tab);
      });
    });

    document.querySelectorAll('[data-export-pdf]').forEach((button) => {
      button.addEventListener('click', () => {
        exportPDF(button.dataset.exportPdf);
      });
    });

    // Start on the first available tab if one is already marked active,
    // otherwise activate the first visible tab.
    const activeTab = document.querySelector('.tab-content.active');
    if (activeTab) {
      showTab(activeTab.id);
    } else {
      const firstTab = document.querySelector('.tab-content');
      if (firstTab) showTab(firstTab.id);
    }

    initCharts();
  });

  // Keep exportPDF available globally in case existing markup calls it directly.
  window.exportPDF = exportPDF;
  window.showTab = showTab;
})();
