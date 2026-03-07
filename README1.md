# Team Info & Project Setup

## Team
- **Andrew Lam**

---

## Server & Credentials

**Server IP:** `167.172.120.163`

### Grader Account
- **Username:** grader  
- **Password:** anl160

### User Account
- **Username:** andrew  
- **Password:** Anny2001

### Database Login
- **DB Name:** collector_db  
- **User:** collector_andrew  
- **Table:** logs

---

## Website
- [https://anl139.site/](https://anl139.site/)
- [https://collector.anl139.site/](https://collector.anl139.site/)
- [https://reporting.anl139.site/](https://reporting.anl139.site/)
---

## Step 1: Authentication & Navigation

- A login page exists to authenticate users called [https://reporting.anl139.site/login](https://reporting.anl139.site/login).  
- Access to `/reports.php` is **protected**; direct browsing without login is blocked.  
- Logout is implemented to destroy sessions.
- Username: admin
- password: pw
## Step 2: Data Table

- Logs are retrieved from a JSON API endpoint (`/api/logs`).  
- Displayed in an **HTML table** on `/reports.php`.  
- Columns: ID, Session, Type, Timestamp, Data  

**Example Table**:

| ID | Session            | Type       | Timestamp           | Data |
|----|------------------ |----------- |------------------- |------|
| 8  | testPOST           | pageview   | 2026-02-26 05:33:50 | `{"data":{"page":"home"},"type":"pageview","sessionId":"testPOST","timestamp":"2026-02-25T06:00:00Z"}` |
| 6  | go8xidac28omm1mh267| page-exit  | 2026-02-25 06:16:56 | `{"data":{"page":"about"},"type":"page-exit","sessionId":"testPOST","timestamp":"2026-02-25T06:05:00Z"}` |

---
## Step 3: Chart Visualization

- Logs are visualized using **Chart.js**.  
- Example: Bar chart showing log counts by type.  
- Chart updates dynamically from the same `/api/logs` endpoint.  

**Chart Example on `/reports.php`**:

```js
const logs = <?= json_encode($logs) ?>;
const counts = {};
logs.forEach(l => counts[l.log_type] = (counts[l.log_type] || 0) + 1);

new Chart(document.getElementById('logChart'), {
    type: 'bar',
    data: {
        labels: Object.keys(counts),
        datasets: [{
            label: 'Log Count',
            data: Object.values(counts)
        }]
    }
});
