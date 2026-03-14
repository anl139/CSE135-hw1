// dashboard.js
const dashboardData = window.dashboardData || {
    activityCounts: [],
    navTiming: [],
    logs: []
};
const { role, displayName } = window.DASHBOARD_USER || {};

document.addEventListener('DOMContentLoaded', () => {
    const sidebarLinks = document.querySelectorAll('.sidebar a');
    const tabs = document.querySelectorAll('.tab-content');

    // -------------------- Tab Navigation --------------------
    function showTab(tabId) {
        sidebarLinks.forEach(link => link.classList.toggle('active', link.dataset.tab === tabId));
        tabs.forEach(tab => tab.style.display = (tab.id === tabId) ? 'block' : 'none');
    }

    sidebarLinks.forEach(link => {
        link.addEventListener('click', e => {
            e.preventDefault();
            showTab(link.dataset.tab);
        });
    });

    document.getElementById('logoutBtn')?.addEventListener('click', () => window.location.href = '/logout.php');

    // -------------------- Charts --------------------
    function initNavChart() {
        const canvas = document.getElementById('navChart');
        if (!canvas || !dashboardData.navTiming) return;

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: dashboardData.navTiming.map((_, i) => `Log ${i + 1}`),
                datasets: [
                    { label: 'DOM Interactive', data: dashboardData.navTiming.map(n => n.domContentLoaded) },
                    { label: 'Total Load', data: dashboardData.navTiming.map(n => n.loadTime) }
                ]
            }
        });
    }

    function initActivityChart() {
        const canvas = document.getElementById('activityChart');
        if (!canvas || !dashboardData.activityCounts) return;

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: dashboardData.activityCounts.map((_, i) => `Log ${i + 1}`),
                datasets: [
                    { label: 'Clicks', data: dashboardData.activityCounts.map(a => a.clicks) },
                    { label: 'MouseMoves', data: dashboardData.activityCounts.map(a => a.mouseMoves) },
                    { label: 'Errors', data: dashboardData.activityCounts.map(a => a.errors) },
                    { label: 'Keys', data: dashboardData.activityCounts.map(a => a.keys) }
                ]
            }
        });
    }

    // -------------------- Pagination --------------------
    const recordsPerPage = 10;

    function paginateRecords(tabId, records) {
        let currentPage = 1;
        const totalPages = Math.ceil(records.length / recordsPerPage);
        const tableBody = document.querySelector(`#${tabId} table tbody`);
        const pageInfo = document.querySelector(`#${tabId} .page-info`);
        const prevBtn = document.querySelector(`#${tabId} .prev-btn`);
        const nextBtn = document.querySelector(`#${tabId} .next-btn`);

        function renderPage() {
            const start = (currentPage - 1) * recordsPerPage;
            const pageRecords = records.slice(start, start + recordsPerPage);
            tableBody.innerHTML = '';

            pageRecords.forEach((r, i) => {
                let rowHtml = '';
                if (tabId === 'performance') {
                    rowHtml = `<tr>
                        <td>${start + i + 1}</td>
                        <td>${r.page ?? '-'}</td>
                        <td>${r.lcp ?? '-'}</td>
                        <td>${r.cls ?? '-'}</td>
                        <td>${r.inp ?? '-'}</td>
                        <td>${r.domContentLoaded ?? '-'}</td>
                        <td>${r.loadTime ?? '-'}</td>
                    </tr>`;
                } else if (tabId === 'behavioral') {
                    rowHtml = `<tr>
                        <td>${start + i + 1}</td>
                        <td>${(r.mouse || []).join(', ')}</td>
                        <td>${(r.clicks || []).join(', ')}</td>
                        <td>${(r.keys || []).join(', ')}</td>
                        <td>${(r.errorsList || []).join(', ')}</td>
                    </tr>`;
                }
                tableBody.insertAdjacentHTML('beforeend', rowHtml);
            });

            pageInfo.textContent = `${currentPage} / ${totalPages}`;
            prevBtn.disabled = currentPage === 1;
            nextBtn.disabled = currentPage === totalPages;
        }

        prevBtn.addEventListener('click', () => {
            if (currentPage > 1) { currentPage--; renderPage(); }
        });

        nextBtn.addEventListener('click', () => {
            if (currentPage < totalPages) { currentPage++; renderPage(); }
        });

        renderPage();
    }

    if (dashboardData.navTiming.length) paginateRecords('performance', dashboardData.navTiming);
    if (dashboardData.logs.length) paginateRecords('behavioral', dashboardData.logs);

    // -------------------- PDF Export --------------------
    async function loadPdfLib() {
        if (window.html2pdf) return;
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = "https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js";
            script.defer = true;
            script.onload = resolve;
            script.onerror = reject;
            document.body.appendChild(script);
        });
    }

    async function exportPDF(tabId) {
        const el = document.getElementById(tabId);
        if (!el) return;

        const canComment = ['analyst', 'super_admin'].includes(role);
        let commentText = '';
        let commentEl;

        if (canComment) {
            while (true) {
                commentText = prompt("Enter an analyst comment (max 200 chars):", "");
                if (commentText === null || commentText.length <= 200) break;
                alert("Comment too long!");
            }
            if (commentText) {
                commentEl = document.createElement('div');
                commentEl.style.margin = '10px 0';
                commentEl.style.padding = '5px';
                commentEl.style.border = '1px solid #000';
                commentEl.style.background = '#f8f8f8';
                commentEl.style.fontStyle = 'italic';
                commentEl.textContent = `Analyst Comment by ${displayName}: ${commentText}`;
                el.prepend(commentEl);
            }
        }

        const hiddenTabs = document.querySelectorAll('.tab-content');
        const previousStates = new Map();
        hiddenTabs.forEach(tab => {
            previousStates.set(tab, tab.style.display);
            if (tab.id !== tabId) tab.style.display = 'none';
        });

        try {
            await loadPdfLib();
            await html2pdf().set({
                margin: 0.5,
                filename: `${tabId}_report.pdf`,
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' }
            }).from(el).save();
        } catch (err) {
            console.error("PDF generation failed", err);
        } finally {
            hiddenTabs.forEach(tab => tab.style.display = previousStates.get(tab) || '');
            if (commentEl) el.removeChild(commentEl);
        }
    }

    document.querySelectorAll('[data-export-pdf]').forEach(button => {
        button.addEventListener('click', () => exportPDF(button.dataset.exportPdf));
    });

    // Initial tab
    const initial = document.querySelector('.sidebar a.active') || sidebarLinks[0];
    if (initial) showTab(initial.dataset.tab);

    // Charts
    initNavChart();
    initActivityChart();

    // expose exportPDF for console if needed
    window.exportPDF = exportPDF;
});

