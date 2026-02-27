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

---

## Database Table: `logs`

| Column      | Type     | Description                              |
|------------ |--------- |-----------------------------------------|
| id          | SERIAL   | Auto-incrementing primary key            |
| session_id  | VARCHAR  | Unique session identifier                |
| log_type    | VARCHAR  | Type of log (pageview, page-exit, activity) |
| timestamp   | TIMESTAMP| When the event occurred                  |
| data        | JSON     | Full JSON object of event data           |

### Sample Entries

| id | session_id          | log_type   | timestamp           | data |
|----|------------------- |----------- |------------------- |------|
| 8  | testPOST            | pageview   | 2026-02-26 05:33:50 | `{"data":{"page":"home"},"type":"pageview","sessionId":"testPOST","timestamp":"2026-02-25T06:00:00Z"}` |
| 6  | go8xidac28omm1mh267 | page-exit | 2026-02-25 06:16:56 | `{"data":{"page":"about"},"type":"page-exit","sessionId":"testPOST","timestamp":"2026-02-25T06:05:00Z"}` |

---

## API Endpoints

| HTTP Method | Route             | Description                        |
|------------ |----------------- |---------------------------------- |
| GET         | /api/logs         | Retrieve all log entries           |
| GET         | /api/logs/{id}    | Retrieve a specific log entry by ID |
| POST        | /api/logs         | Create a new log entry             |
| PUT         | /api/logs/{id}    | Update an existing log entry by ID |
| DELETE      | /api/logs/{id}    | Delete a log entry by ID           |

**Notes:**  
- All requests and responses use JSON.

---

### Example POST/PUT Payload

```json
{
  "type": "pageview",
  "sessionId": "testPOST",
  "timestamp": "2026-02-25T06:00:00Z",
  "data": {"page": "home"}
}


