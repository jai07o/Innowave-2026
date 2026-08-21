# InnoWave-2026 — Event Website + Registration Backend

A complete, self-contained event website for **InnoWave-2026**, the National-Level Project Expo by the **IEEE Student Branch, PSCMR College of Engineering & Technology, Vijayawada**, developed by **Jaideep Raj**.

It includes:

* A **public event website** (home, eligibility, process, 20 tracks, contacts, patrons)
* An online **registration form** that saves every entry to a database
* A password-protected **Organizer Dashboard** (`/admin`) with live stats, a searchable table, and a **Download Excel** button that exports all registrations to a `.xlsx` spreadsheet
* A **UPI payment step** where participants can pay through a UPI QR code / UPI ID
* Collection of the participant's **UPI transaction reference**
* Automatic generation of a **unique Team ID** (e.g. `IW26-IOT-0001`)
* A **printable confirmation slip** containing registration and payment details
* Payment verification from the organizer dashboard

**Developed by:** Jaideep Raj

**Organization:** IEEE Student Branch, PSCMR College of Engineering & Technology, Vijayawada, Andhra Pradesh

**Stack:** Node.js · Express · SQLite (better-sqlite3) · ExcelJS · qrcode — no external accounts or services needed.

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

The default admin password is **`innowave2026`**.

---

## 2. Change the admin password (recommended)

Set an `ADMIN_PASSWORD` environment variable before starting:

```bash
# macOS / Linux
ADMIN_PASSWORD="YourStrongPassword" npm start

# Windows (PowerShell)
$env:ADMIN_PASSWORD="YourStrongPassword"; npm start
```

You can also change the port the same way, e.g. `PORT=8080 npm start`.

---

## 2b. IMPORTANT — set your UPI ID (to collect payments)

Payments use a **UPI QR + reference** flow. Set your real collection UPI ID before going live, otherwise the QR points to a placeholder:

```bash
# macOS / Linux
UPI_VPA="yourupi@okbank" UPI_PAYEE_NAME="PSCMR IEEE Student Branch" npm start

# Windows (PowerShell)
$env:UPI_VPA="yourupi@okbank"; $env:UPI_PAYEE_NAME="PSCMR IEEE Student Branch"; npm start
```

How it works: the participant scans the QR (or pays to your UPI ID) with any UPI app (GPay/PhonePe/Paytm), pays the exact amount, enters the **UPI transaction/reference ID**, and receives a **unique Team ID** + printable slip. The entry is saved with status **Pending Verification**. The organizer then confirms the payment in the admin dashboard (**✓ Verify**), which changes it to **Paid** and adds the amount to **Amount Collected**.

> Want fully automatic online payments (instant verification, cards/netbanking too)? That requires a Razorpay account and additional payment-gateway integration.

---

## 3. Where the data lives

All registrations are stored in a SQLite database file:

```text
data/innowave.db
```

This file is created automatically on first run.

Back up this file regularly to keep registrations safe.

Two demo rows may exist from testing — delete them from the admin dashboard using the **Delete** button on each row before going live, or delete the `data/` folder to start completely fresh.

---

## 4. Viewing / exporting registrations (your Excel sheet)

1. Go to **http://localhost:3000/admin**
2. Sign in with the admin password
3. You'll see **Total Teams**, **Total Participants**, and **IEEE Member Teams**, along with the payment statistics
4. Use the **status filter** (All / Paid / To verify / Unpaid) and the **✓ Verify** button on each row to confirm UPI payments
5. Click **⬇ Download Excel** to download `InnoWave-2026_Registrations.xlsx`
6. The Excel file contains the registration, team, project, track, participant, contact, IEEE, payment, and timestamp information

The Excel file is generated fresh each time you click the download button, so it always reflects the latest registrations.

---

## 5. Registration fee logic (built in)

The registration fee is calculated automatically based on the team's IEEE membership status.

* **IEEE members:** ₹100 per head → the confirmation shows ₹100 × team size
* **Non-IEEE members:** ₹150 per team (flat)

The applicable fee is calculated automatically and stored with each registration.

---

## 6. Registration & Team ID system

After successfully submitting the registration and payment reference, the system automatically generates a unique Team ID.

Example:

```text
IW26-IOT-0001
```

The Team ID consists of:

* `IW26` — InnoWave 2026
* `IOT` — selected project track
* `0001` — unique registration number

The generated Team ID is displayed on the confirmation page and printable confirmation slip.

---

## 7. Admin Dashboard

The organizer dashboard is available at:

```text
http://localhost:3000/admin
```

It is password protected and provides organizers with complete control over the registrations.

The dashboard includes:

* Total registered teams
* Total participants
* IEEE member teams
* Paid registrations
* Pending payment verification
* Total amount collected
* Searchable registration table
* Payment status filtering
* UPI transaction/reference details
* **✓ Verify** payment button
* **Delete** registration button
* **⬇ Download Excel** button

Only verified payments are included in the **Amount Collected** statistics.

---

## 8. Putting it online (optional)

This runs on any Node.js-compatible host.

Possible hosting options include:

* **Render.com**
* **Railway.app**
* A college/server-hosted Node.js environment

For a typical deployment:

```text
Build Command:
npm install

Start Command:
npm start
```

Add the following environment variables on the hosting platform:

```text
ADMIN_PASSWORD=YourStrongPassword
UPI_VPA=yourupi@okbank
UPI_PAYEE_NAME=PSCMR IEEE Student Branch
```

### Important database requirement

The project uses SQLite and stores registrations inside:

```text
data/innowave.db
```

Make sure the hosting provider provides **persistent storage** for the `data/` directory. Otherwise, registrations may be lost when the application is redeployed or restarted.

For a college server with Node.js, copy the project folder to the server, run:

```bash
npm install
npm start
```

You can use **PM2** or a systemd service to keep the application running continuously.

---

## 9. Files overview

```text
innowave-website/
├─ server.js            # Express server + SQLite + Excel export API
├─ package.json         # dependencies & start script
├─ README.md            # project documentation
├─ public/
│  ├─ index.html        # public event website + registration form
│  ├─ admin.html        # organizer dashboard
│  └─ assets/           # College, IEEE and IIC logos
└─ data/
   └─ innowave.db       # SQLite database (auto-created)
```

---

## 10. Developer Information

**Project:** InnoWave-2026

**Developed by:** **Jaideep Raj**

**Project Type:** Full-Stack Event Website & Registration Management System

**Event:** National-Level Project Expo — InnoWave-2026

**Organized by:** IEEE Student Branch, PSCMR College of Engineering & Technology

**Location:** Vijayawada, Andhra Pradesh

**Developer Responsibilities:**

* Full-stack website development
* Frontend design and implementation
* Backend development using Node.js and Express
* SQLite database integration
* Online registration system
* Automatic fee calculation
* UPI QR payment workflow
* UPI transaction reference collection
* Unique Team ID generation
* Printable confirmation slip
* Organizer authentication
* Admin dashboard development
* Payment verification system
* Registration search and filtering
* Excel report generation
* Database management
* Deployment configuration
* Project documentation

---

*Developed by **Jaideep Raj** · Organized by the **IEEE Student Branch · PSCMR College of Engineering & Technology · Vijayawada, Andhra Pradesh***
