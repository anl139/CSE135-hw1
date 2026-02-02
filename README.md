# Team Info & Project Setup

## Team
- **Andrew Lam**

## Credentials
  **IP address for server**
- 167.172.120.163
- **Grader account:**
-  Username: `grader`
-  Password: `anl160`
- **User accounts:**(for both server and website)
  - Username: `andrew`  
    Password: `Anny2001`  

## Website
- [https://anl139.site/](https://anl139.site/)

---

## Auto-Deploy Setup (Using GitHub Webhooks)

I followed a guide to set up auto-deployment using GitHub webhooks:  
[GitHub Auto-Deploy Setup Guide](https://portent.com/blog/design-dev/github-auto-deploy-setup-guide.htm)

### Steps Taken
1. Created an SSH directory for the `www-data` user.  
2. Generated an SSH key for `www-data` and stored it in the SSH directory.  
3. Added the public key to the GitHub repository.  
4. Cloned the repository onto the server.  
5. Created a deployment script (`deploy.php`) in the site root with commands like:
   ```bash
   git pull
   git submodule update --init --recursive
   ```
6.Created a webhook in the GitHub repository settings pointing [to:](https://anl139.site/deploy.php)
## HTML Compression
Using curl:
curl -I https://anl139.site/
Content-Length: 2510 bytes (uncompressed HTML)
Using Chrome / DevTools:
Content-Length: 1065 bytes (gzip-compressed HTML)
This is less than half the original size, as Chrome requests compression automatically.
# Changing the Server Header

To change the server header to **"CSE 135"**:

## 1. Install mod-security for Apache

```bash
sudo apt install libapache2-mod-security2
```
## 2. Edit the security configuration
```bash
/etc/apache2/mods-available/security2.conf
```
## 3. Update the following values
- ServerTokens Prod
- ServerSignature Off
- SecServerSignature "CSE 135"
- Restart and we are done

### Approach 3: Free Choice — Simple Analytics

For the third-party analytics free-choice approach, I evaluated several lightweight, privacy-focused options:

1. **Matomo** – Self-hosted analytics with detailed tracking; setup is heavier and requires server configuration.  
2. **Plausible Analytics** – Lightweight, privacy-first, provides simple metrics; some advanced features require a paid plan.  
3. **Simple Analytics** – Minimal, 100% privacy-focused, free plan available, easy integration with a single script tag.

**Choice:** I selected **Simple Analytics** because:

- Quick and easy to integrate into existing pages.
- It promotes the idea that unlike google analytics, it does not track personal information or fingerprint users.  
- Minimal impact on page load speed.  

**Implementation:**  
- Added the Simple Analytics script to the `<head>` section of `index.html`:

```html
<script async src="https://scripts.simpleanalyticscdn.com/latest.js"></script>


