document.addEventListener('DOMContentLoaded', () => {
    const dataEl = document.getElementById('dashboard-data');
    const dashboardData = dataEl ? JSON.parse(dataEl.textContent || '{}') : {};

    const activitySeries = dashboardData.activitySeries || [];
    const performanceSeries = dashboardData.performanceSeries || [];

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
                labels: performanceSeries.map((row, i) => row.label || `Log ${i + 1}`),
                datasets: [
                    {
                        label: 'DOM Interactive',
                        data: performanceSeries.map(row => row.domInteractive),
                    },
                    {
                        label: 'Total Load',
                        data: performanceSeries.map(row => row.totalLoadTime),
                    },
                    {
                        label: 'LCP',
                        data: performanceSeries.map(row => row.lcp),
                    },
                    {
                        label: 'CLS',
                        data: performanceSeries.map(row => row.cls),
                    },
                    {
                        label: 'INP',
                        data: performanceSeries.map(row => row.inp),
                    },
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
            }
        });
    }

    function initActivityChart() {
        const canvas = document.getElementById('activityChart');
        if (!canvas) return;

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: activitySeries.map((row, i) => row.label || `Log ${i + 1}`),
                datasets: [
                    {
                        label: 'Clicks',
                        data: activitySeries.map(row => row.clicks),
                    },
                    {
                        label: 'Scrolls',
                        data: activitySeries.map(row => row.scrolls),
                    },
                    {
                        label: 'MouseMoves',
                        data: activitySeries.map(row => row.mouseMoves),
                    },
                    {
                        label: 'Errors',
                        data: activitySeries.map(row => row.errors),
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
            }
        });
    }

    function exportPDF(tabId) {
        const el = document.getElementById(tabId);
        if (!el || typeof html2pdf === 'undefined') return;

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

    document.querySelectorAll('[data-export-pdf]').forEach(button => {
        button.addEventListener('click', () => {
            exportPDF(button.dataset.exportPdf);
        });
    });

    if (sidebarLinks.length) {
        const initial = document.querySelector('.sidebar a.active') || sidebarLinks[0];
        if (initial) {
            showTab(initial.dataset.tab);
        }
    }

    initNavChart();
    initActivityChart();
    window.exportPDF = exportPDF;
});
