# InnoWave-2k26 — Event Website + Registration Backend

A complete, self-contained event website for **InnoWave-2k26**, the National-Level Project Expo by the **IEEE Student Branch, PSCMR College of Engineering & Technology, Vijayawada**, developed by **Jaideep Raj**.

Repository: `https://github.com/jai07o/Innowave-2026.git`

It includes:

- A **public event website** (home, eligibility, process, 20 tracks, contacts, patrons)
- Dedicated **IEEE Member** (`/register-ieee`) and **Non-IEEE Student** (`/register-non-ieee`) registration portals
- Automatic **IEEE Card Verification** with payment link generation and automated email dispatch
- Persistent **Status Lookup & Payment QR Retrieval** until payment completion
- A password-protected **Organizer Dashboard** (`/admin`) with live stats, searchable table, **🗑️ DELETE ALL** purge, and **⬇ Download Excel** button exporting `.xlsx` spreadsheets

**Developed by:** Jaideep Raj  
**Organization:** IEEE Student Branch, PSCMR College of Engineering & Technology, Vijayawada, Andhra Pradesh  
**Stack:** Node.js · Express · SQLite (better-sqlite3) · ExcelJS · qrcode · Nodemailer — no external accounts or services needed.

---

## 1. Run it locally (3 steps)

You need **Node.js 18 or newer** installed (download from https://nodejs.org).

```bash
# 1. open a terminal inside this folder, then install dependencies
npm install

# 2. start the server
npm start

# 3. open in your browser
#    Website : http://localhost:3000
#    Admin   : http://localhost:3000/admin
```

The default admin password is **`innowave2k26`** (or `innowave2026`).

---

## 2. Change the admin password (recommended)

Set an `ADMIN_PASSWORD` environment variable before starting:

```bash
# macOS / Linux
ADMIN_PASSWORD="YourStrongPassword" npm start

# Windows (PowerShell)
$env:ADMIN_PASSWORD="YourStrongPassword"; npm start
```

---

## 3. Customize UPI Collection Details & Email Setup

By default, the server uses the test collection UPI ID:

- **UPI ID (VPA):** `gunugukrishnakumar@ybl`
- **Payee Name:** `PSCMR IEEE Student Branch`

To use your official college account, set environment variables:

```bash
# Example (macOS / Linux)
UPI_VPA="yourcollege@bank" UPI_PAYEE_NAME="PSCMR IEEE Student Branch" npm start
```

To configure live email dispatch for IEEE Card Approval payment links:

```bash
SMTP_HOST="smtp.gmail.com" SMTP_USER="your-email@gmail.com" SMTP_PASS="your-app-password" npm start
```

---

## 4. Git Repository Sync

To push the latest updates to GitHub repository `https://github.com/jai07o/Innowave-2026.git`:

```bash
git add .
git commit -m "Update InnoWave2k26 branding and responsive features"
git push origin main
```
