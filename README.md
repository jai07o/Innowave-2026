# 🚀 INNOWAVE-2K26 — Official Event Website & Management System

> **National-Level Technical Fest Platform, Registration System, Dynamic UPI Payment Verification, Official 4K Delegate Pass Auto-Generator, and Public QR Verification System.**

---

## 🌟 Executive Project Summary

**INNOWAVE-2K26** is a state-of-the-art, full-stack event management and registration web application designed for the **Engineer's Day Celebration National Level Fest**, organized by the **IEEE Student Branch (STB18301)** at **PSCMR College of Engineering and Technology**.

The platform provides a complete end-to-end event workflow: interactive 3D landing pages, IEEE and Non-IEEE registration forms with automated team fee calculation, dynamic UPI QR payment verification, auto-generated official 4K Delegate Passes with brochure checklists, instant QR-code mobile verification, and a comprehensive password-protected Admin Dashboard.

---

## ✨ Complete Feature Breakdown

### 1. 🌐 Interactive 3D Event Homepage (`public/index.html`)
- **3D Animated Background**: Built using **Three.js** with interactive particle cloud animations that adapt to the active color theme.
- **Brochure Event Cards**: Interactive modal details for 6 national-level brochure events:
  1. 🧠 **Technical Quiz**
  2. 💻 **Coding Challenge**
  3. 🗺️ **Tech Treasure Hunt**
  4. 🚀 **Project Expo**
  5. 🤖 **Prompt Engineering**
  6. 🎬 **Reels (1 Min)**
- **Committee Section**: Full listing of IEEE Branch Committee Members, Faculty Coordinators, Student Chairs, and Department Heads.
- **Live Registration Status Lookup**: Instant search box allowing participants to check their registration status by Registration ID (`IW26-XXXX`), Mobile Number, or Email.

---

### 🎨 2. Dual-Theme Engine (Dark Mode & 2-Color Light Mode)
- **Dark Mode**: Premium futuristic dark glassmorphism theme (`#040406` primary navy, glowing cyan borders, gold accents).
- **Redesigned Light Theme**: Ultra-attractive, high-contrast light theme engineered around **2 Particular Signature Colors**:
  - 🔷 **Color 1: Sapphire Electric Navy (`#0284c7` / `#0369a1`)**: Used for navigation bars, card structures, main headers, active borders, and primary CTA buttons.
  - 🔶 **Color 2: Vibrant Warm Amber (`#f59e0b` / `#d97706`)**: Used for IEEE badges, crowns, highlight pill tags, price tags, and secondary action buttons.
- **Instant Toggle**: Smooth, persistent theme switching using the `☀️ LIGHT MODE` / `🌙 DARK MODE` toggle button across all pages.

---

### 📝 3. IEEE & Non-IEEE Registration Portals (`register-ieee.html` & `register-non-ieee.html`)
- **Dynamic Fee Calculator**: Automatically computes team registration fees based on membership status and team size.
- **UPI QR Payment Integration**: Renders a dynamic UPI QR code linked to the official payee (`6309419599@axl` / `PSCMR IEEE Student Branch`).
- **Payment UTR Reference Verification**: Accepts transaction UTR reference numbers and stores proof for manual organizer auditing.
- **Automated Success Receipt**: Generates a printable digital registration slip upon submission.

---

### 🪪 4. Official 4K Ultra-Res Delegate Pass Auto-Generator
- **Exact Graphical Layout**: 1:1 pass card matching official fest design standards.
- **Prominent HD Participant ID Badge**: High-contrast glowing cyan & gold pill badge (**`🆔 PARTICIPANT ID: IW26-XXXX`**) rendered in crystal-clear typography.
- **Brochure Events Evaluation Checklist**: Integrated 6-event evaluation checklist table with check boxes (`[✓]`) and Event Head signature lines.
- **4K PNG Image Export**: Powered by `html2canvas` (`scale: 4`, ~1760×2900 resolution, 300+ DPI) to download **ONLY the full Delegate Pass graphic card itself** without extra fitting or modal clutter.

---

### 📱 5. Public Universal QR Code Verification System (`verify-id.html`)
- **Dynamic Host Resolution**: Every Delegate Pass embeds a scannable QR Code encoding the active server URL (`http://<HOST>/verify-id.html?id=...`).
- **Instant Mobile Verification**: Scanning the QR Code on any smartphone, iPhone, Android, camera app, or scanner connected to the network immediately opens the official verification page.
- **Verified Status Badge**: Displays `VERIFIED ✅` symbol badge alongside the participant's name, Registration ID, College Name, Branch, Year, Mobile Number, and Email Address.

---

### ⚙️ 6. Admin Management Dashboard (`public/admin.html`)
- **Password Protection**: Secure login gate (default password: `innowave2k26`).
- **Real-Time Analytics Grid**: Displays total registrations, IEEE members count, Non-IEEE count, total revenue generated, and pending IEEE card approvals.
- **IEEE Card Verification Workflow**: Dedicated approval system for IEEE member ID cards.
- **Participant Search & Filter**: Instant search by ID, Name, Mobile, Email, or College.
- **One-Click Excel Export**: Generates a complete `.xlsx` spreadsheet of all registered teams via **ExcelJS**.
- **Delegate Pass Modal Viewer**: View, print, or download 4K PNG passes directly from the admin panel.

---

### 🌐 7. Universal Zero-Configuration Portability
- **Dynamic Base URL Resolution (`getBaseUrl(req)`)**: Automatically detects custom domains (`https://innowave2k26.org`), cloud hosts (Render, Vercel, Railway, Heroku, AWS), or local network IPs without hardcoded domain names.
- **SQLite Database Auto-Initialization**: Auto-creates `data/innowave.db` and all required tables on first run.
- **Environment Configuration**: Configurable via `.env` file with safe defaults.

---

## 🛠️ Technology Stack

| Layer | Technology Used |
| :--- | :--- |
| **Backend Runtime** | Node.js (v16+) |
| **Web Framework** | Express.js |
| **Database** | SQLite3 via `better-sqlite3` (WAL mode enabled) |
| **QR Code Engine** | `qrcode` |
| **Excel Export** | `exceljs` |
| **Config Engine** | `dotenv` |
| **Frontend UI** | HTML5, Vanilla CSS3 (Custom Design System) |
| **3D Animations** | Three.js (r128) |
| **4K Pass Rendering** | `html2canvas` (v1.4.1) |
| **Typography** | Google Fonts (*Space Grotesk*, *Inter*, *Montserrat*) |

---

## 📁 Repository Structure

```text
innowave-website/
├── public/
│   ├── index.html            # Main Event Landing Page, 3D Canvas & Events
│   ├── register-ieee.html    # IEEE Member Registration & Auto Pass Slip
│   ├── register-non-ieee.html# Non-IEEE Registration & Auto Pass Slip
│   ├── admin.html            # Admin Management Dashboard & ID Card Modal
│   └── verify-id.html        # Public QR Verification Target Page
├── data/
│   └── innowave.db           # Auto-Created SQLite Database
├── server.js                 # Express Backend API & Host Resolution Engine
├── .env.example              # Environment Configuration Template
├── package.json              # Node.js Dependencies & Scripts
└── README.md                 # Documentation & Deployment Guide
```

---

## 🚀 Quick Start Guide

### 1. Installation
```bash
# Clone the repository from GitHub
git clone https://github.com/your-username/innowave-website.git
cd innowave-website

# Install dependencies
npm install
```

### 2. Running Locally
```bash
npm start
```
- **Main Website**: [http://localhost:3000](http://localhost:3000)
- **Admin Portal**: [http://localhost:3000/admin.html](http://localhost:3000/admin.html) *(Password: `innowave2k26`)*

---

## 🌐 Custom Domain & Cloud Deployment

### Environment Variables (`.env`)
Copy `.env.example` to `.env` to override default settings:
```env
PORT=3000
ADMIN_PASSWORD=your_secure_password
UPI_VPA=yourupi@bank
UPI_PAYEE_NAME=Your Organization Name
BASE_URL=https://yourdomain.com
```

### Deployment Commands:
- **Render / Railway / Heroku**: Connect GitHub repo. Build command: `npm install`, Start command: `node server.js`.
- **VPS / Linux Server (Nginx + PM2)**:
  ```bash
  npm install
  npm install -g pm2
  pm2 start server.js --name innowave
  ```

---

## 👑 Author & Project Attribution

```text
================================================================
🚀 INNOWAVE-2K26 EVENT WEBSITE & MANAGEMENT SYSTEM
================================================================
 Designed, Architected, and Developed by : K. JAIDEEP RAJ
 Organization                            : PSCMR IEEE Student Branch (STB18301)
 Event                                   : INNOWAVE-2K26 National Level Fest
 Status                                  : 100% Verified & Production Ready
================================================================
```

*This project was created and developed by **K. JAIDEEP RAJ**.*

---
© 2026 INNOWAVE-2K26 · IEEE Student Branch (STB18301) · Created by K. JAIDEEP RAJ
