# InnoWave-2k26 — Event Website + Registration Backend

A complete, self-contained event website for **InnoWave-2k26**, the National-Level Project Expo by the **IEEE Student Branch, PSCMR College of Engineering & Technology, Vijayawada**.

**Project Developed By:** **K. Jaideep Raj**  
**Organization:** IEEE Student Branch (STB18301), PSCMR College of Engineering & Technology, Vijayawada, Andhra Pradesh  
**GitHub Repository:** `https://github.com/jai07o/Innowave-2026.git`

---

## ⚡ Features & System Capabilities

- **Public Event Portal:** Modern single-page website featuring eligibility rules, 6 event categories, 20 project domains, coordinator contacts, and executive committee patrons.
- **Dual Registration Portals:**
  - 🎓 **IEEE Member Portal (`/register-ieee`)**: Exclusive discounted fee structure with IEEE Membership Card proof upload and automated Admin approval workflow.
  - 🚀 **Non-IEEE Student Portal (`/register-non-ieee`)**: Direct instant registration with instant PhonePe / UPI QR code generation.
- **Automated IEEE Card Verification & Email Dispatch:**
  - Admin approves IEEE card in `/admin` $\rightarrow$ System generates unique payment URL (`/register-ieee?pay_id=123`) and dispatches HTML confirmation emails via Nodemailer.
- **Persistent Status Lookup & Payment QR Retrieval:**
  - Participants can enter their **Registration ID (e.g. `IW26-0005`), Phone Number, or Email** anytime before payment completion (`Paid`) to unlock their active UPI Payment QR Code and UTR submission form.
- **Organizer Dashboard (`/admin`)**:
  - Real-time statistics summary, searchable data table, instant status updates, double-confirmed bulk purge (**`🗑️ DELETE ALL`**), and formatted Excel export (**`InnoWave-2k26_Registrations.xlsx`**).
- **Responsive Mobile Layout Fitting:**
  - Fitted multi-column laptop structure onto mobile viewports with touch-friendly navigation header actions and theme toggler buttons.

**Tech Stack:** Node.js · Express · SQLite (better-sqlite3) · ExcelJS · qrcode · Nodemailer — zero external paid API dependencies.

---

## 1. Run Locally (3 steps)

Requires **Node.js 18 or newer** (download from https://nodejs.org).

```bash
# 1. Open terminal inside the repository folder, then install dependencies
npm install

# 2. Start the live server
npm start

# 3. Open in your browser
#    Public Website : http://localhost:3000
#    IEEE Register  : http://localhost:3000/register-ieee
#    Non-IEEE Reg   : http://localhost:3000/register-non-ieee
#    Admin Portal   : http://localhost:3000/admin
```

The default admin password is **`innowave2k26`** (or `innowave2026`).

---

## 2. Environment Variables & Setup

```bash
# Custom Admin Password
ADMIN_PASSWORD="YourStrongPassword" npm start

# Custom Collection UPI VPA ID & Payee Name
UPI_VPA="yourcollege@bank" UPI_PAYEE_NAME="PSCMR IEEE Student Branch" npm start

# Configure SMTP Email Dispatch for Payment Links
SMTP_HOST="smtp.gmail.com" SMTP_USER="your-email@gmail.com" SMTP_PASS="your-app-password" npm start
```

---

## 3. GitHub Synchronization Commands

To push the latest updates to GitHub repository `https://github.com/jai07o/Innowave-2026.git`:

```bash
git add .
git commit -m "Update InnoWave2k26 branding, Excel export format, and K. Jaideep Raj author credit"
git push origin main
```

---

## 👨‍💻 Project Developer Credit

- **Developer:** K. Jaideep Raj
- **Institution:** PSCMR College of Engineering & Technology, Vijayawada, Andhra Pradesh
- **Organization:** IEEE Student Branch (STB18301)
- **Repository:** https://github.com/jai07o/Innowave-2026.git
