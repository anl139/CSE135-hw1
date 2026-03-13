document.addEventListener("DOMContentLoaded", () => {
  const dashboardData = readDashboardData();

  const dom = {
    tabButtons: Array.from(document.querySelectorAll(".tab-button")),
    tabContents: Array.from(document.querySelectorAll(".tab-content")),
    navChart: document.getElementById("navChart"),
    activityChart: document.getElementById("activityChart"),
    exportButtons: Array.from(document.querySelectorAll("[data-export-pdf]")),
  };

  initTabs(dom);
  initCharts(dashboardData, dom);
  initPdfExport(dom);
});

function readDashboardData() {
  const dataEl = document.getElementById("dashboard-data");
  if (!dataEl) {
    return { activityCounts: [], navTiming: [] };
  }

  try {
    const parsed = JSON.parse(dataEl.textContent || "{}");
    return {
      activityCounts: Array.isArray(parsed.activityCounts) ? parsed.activityCounts : [],
      navTiming: Array.isArray(parsed.navTiming) ? parsed.navTiming : [],
    };
  } catch (error) {
    console.error("Failed to parse dashboard data:", error);
    return { activityCounts: [], navTiming: [] };
  }
}

function initTabs({ tabButtons, tabContents }) {
  if (!tabButtons.length || !tabContents.length) {
    return;
  }

  const setActiveTab = (tabId) => {
    tabButtons.forEach((button) => {
      button.classList.toggle("active", button.dataset.tab === tabId);
    });

    tabContents.forEach((section) => {
      section.classList.toggle("active", section.id === tabId);
    });
  };

  tabButtons.forEach((button) => {
    button.addEventListener("click", () => {
      setActiveTab(button.dataset.tab);
    });
  });
}

function initCharts(dashboardData, dom) {
  initNavChart(dom.navChart, dashboardData.navTiming);
  initActivityChart(dom.activityChart, dashboardData.activityCounts);
}

function initNavChart(canvas, navTiming) {
  if (!canvas || !Array.isArray(navTiming) || navTiming.length === 0 || typeof Chart === "undefined") {
    return;
  }

  const labels = navTiming.map((entry, index) => entry.page || `Page ${index + 1}`);
  const loadTimes = navTiming.map((entry) => Number(entry.loadTime) || 0);

  new Chart(canvas, createLineChartConfig("Load Time (ms)", labels, loadTimes));
}

function initActivityChart(canvas, activityCounts) {
  if (!canvas || !Array.isArray(activityCounts) || activityCounts.length === 0 || typeof Chart === "undefined") {
    return;
  }

  const labels = activityCounts.map((_, index) => `Session ${index + 1}`);
  const clicks = activityCounts.map((entry) => Number(entry.clicks) || 0);
  const scrolls = activityCounts.map((entry) => Number(entry.scrolls) || 0);
  const mouseMoves = activityCounts.map((entry) => Number(entry.mouseMoves) || 0);

  new Chart(canvas, {
    type: "bar",
    data: {
      labels,
      datasets: [
        createBarDataset("Clicks", clicks),
        createBarDataset("Scrolls", scrolls),
        createBarDataset("Mouse Moves", mouseMoves),
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      scales: {
        y: {
          beginAtZero: true,
        },
      },
    },
  });
}

function createLineChartConfig(label, labels, data) {
  return {
    type: "line",
    data: {
      labels,
      datasets: [
        {
          label,
          data,
          borderWidth: 2,
          tension: 0.3,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      scales: {
        y: {
          beginAtZero: true,
        },
      },
    },
  };
}

function createBarDataset(label, data) {
  return {
    label,
    data,
  };
}

function initPdfExport({ tabContents, exportButtons }) {
  if (!exportButtons.length) {
    return;
  }

  const exportSectionToPDF = (tabId) => {
    const el = document.getElementById(tabId);
    if (!el || typeof html2pdf === "undefined") {
      return;
    }

    const hiddenTabs = tabContents.filter((section) => section.id !== tabId);
    const previousDisplayValues = new Map();

    hiddenTabs.forEach((section) => {
      previousDisplayValues.set(section, section.style.display);
      section.style.display = "none";
    });

    const restoreTabs = () => {
      hiddenTabs.forEach((section) => {
        section.style.display = previousDisplayValues.get(section) || "";
      });
    };

    html2pdf()
      .set({
        margin: 0.5,
        filename: `${tabId}_report.pdf`,
        html2canvas: { scale: 2 },
      })
      .from(el)
      .save()
      .then(restoreTabs)
      .catch((error) => {
        restoreTabs();
        console.error("PDF export failed:", error);
      });
  };

  window.exportPDF = exportSectionToPDF;

  exportButtons.forEach((button) => {
    button.addEventListener("click", () => {
      exportSectionToPDF(button.dataset.exportPdf);
    });
  });
}
