# InnoWave-2026 — Event Website + Registration Backend

A complete, self-contained event website for **InnoWave-2026**, the National-Level Project Expo by the
IEEE Student Branch, PSCMR College of Engineering & Technology, Vijayawada.

It includes:

- A **public event website** (home, eligibility, process, 20 tracks, contacts, patrons)
- An online **registration form** that saves every entry to a database
- A password-protected **Organizer Dashboard** (`/admin`) with live stats, a searchable table, and a
  **Download Excel** button that exports all registrations to a `.xlsx` spreadsheet

It now also includes a **UPI payment step**: after filling the form, each team pays via a **UPI QR code / UPI ID**,
enters their **UPI transaction reference**, and instantly receives a **unique Team ID** (e.g. `IW26-IOT-0001`) on a
printable confirmation slip. Organizers verify payments from the admin dashboard.

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

Payments use a **UPI QR + reference** flow. Set your real collection UPI ID before going live, otherwise the QR points
to a placeholder:

```bash
# macOS / Linux
UPI_VPA="yourupi@okbank" UPI_PAYEE_NAME="PSCMR IEEE Student Branch" npm start

# Windows (PowerShell)
$env:UPI_VPA="yourupi@okbank"; $env:UPI_PAYEE_NAME="PSCMR IEEE Student Branch"; npm start
```

How it works: the participant scans the QR (or pays to your UPI ID) with any UPI app (GPay/PhonePe/Paytm), pays the
exact amount, enters the **UPI transaction/reference ID**, and receives a **unique Team ID** + printable slip. The entry
is saved with status **Pending Verification**. You then confirm the payment in the admin dashboard (**✓ Verify**), which
flips it to **Paid** and adds it to "Amount Collected".

> Want fully automatic online payments (instant verification, cards/netbanking too)? That needs a Razorpay account —
> ask and it can be added.

---

## 3. Where the data lives

All registrations are stored in a SQLite database file:

```
data/innowave.db
```

This file is created automatically on first run. Back it up to keep your registrations safe.
Two demo rows may exist from testing — delete them from the admin dashboard (the **Delete** button on each row)
before going live, or delete the `data/` folder to start completely fresh.

---

## 4. Viewing / exporting registrations (your Excel sheet)

1. Go to **http://localhost:3000/admin**
2. Sign in with the admin password
3. You'll see **Total Teams**, **Total Participants**, and **IEEE Member Teams**, plus a searchable table of every entry
4. Use the **status filter** (All / Paid / To verify / Unpaid) and the **✓ Verify** button on each row to confirm UPI payments
5. Click **⬇ Download Excel** to download `InnoWave-2026_Registrations.xlsx` with all current data
   (formatted headers, one row per team, 22 columns including Team ID, payment status, amount, payment reference,
   project, track, team leader, contacts, IEEE status, team members, and timestamps)

The Excel file is generated fresh each time you click the button, so it always reflects the latest registrations.

---

## 5. Registration fee logic (built in)

- **IEEE members:** ₹100 per head → the confirmation shows ₹100 × team size
- **Non-IEEE members:** ₹150 per team (flat)

The applicable fee is calculated automatically and stored with each registration.

---

## 6. Putting it online (optional)

This runs on any Node host. Easiest free options:

- **Render.com** or **Railway.app:** create a new "Web Service" from this folder / a GitHub repo,
  build command `npm install`, start command `npm start`. Add an `ADMIN_PASSWORD` environment variable in the dashboard.
- Make sure the host gives you a **persistent disk** for the `data/` folder so registrations aren't lost on redeploy
  (on Render, add a Persistent Disk mounted at the project directory).

For a college server with Node, just copy the folder, run `npm install`, and keep `npm start` running
(use `pm2` or a systemd service to keep it alive).

---

## 7. Files overview

```
innowave-website/
├─ server.js            # Express server + SQLite + Excel export API
├─ package.json         # dependencies & start script
├─ README.md            # this file
├─ public/
│  ├─ index.html        # the public event website + registration form
│  ├─ admin.html        # organizer dashboard (login, table, Download Excel)
│  └─ assets/           # the three logos (College, IEEE, IIC)
└─ data/
   └─ innowave.db       # SQLite database (auto-created)
```

---

*Organized by the IEEE Student Branch · PSCMR College of Engineering & Technology · Vijayawada, Andhra Pradesh*
