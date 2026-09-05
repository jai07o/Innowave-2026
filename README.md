# 🚀 INNOWAVE-2K26 — Official Event Website & Fest Management Platform

> **National-Level Technical Fest Platform · IEEE Student Branch (STB18301) · PSCMR College of Engineering and Technology**
>
> Full-Stack Event Registration, Tiered Pricing Engine, Dynamic College UPI Payment Gateway, Client-Side AI OCR Verification, Official 4K Ultra-HD Delegate Pass Auto-Generator, Real-Time Gate Verification System, and Enterprise MySQL Database Architecture.

---

## 🌟 Executive Project Overview

**INNOWAVE-2K26** is a production-grade, full-stack event management web platform engineered for the **Engineer's Day Celebration National Level Technical Fest**, organized by the **IEEE Student Branch (STB18301)** at **PSCMR College of Engineering and Technology** (Vijayawada, Andhra Pradesh).

The entire system is architected to run with **zero code modifications** across **ANY server, domain, or hosting environment** (e.g., `server1.pscmr.ac.in`, cPanel, Plesk, Linux VPS, Apache, Nginx, or local test servers).

### Key Architectural Highlights:
1. **Zero-Configuration Universal Deployment**: All asset paths, API endpoints, and redirects utilize relative or dynamically resolved URLs. The website functions identically whether deployed at the root domain (`https://example.com/`) or within any nested subdirectory (`https://server1.pscmr.ac.in/innowave/`).
2. **Enterprise MySQL as Exclusive Database Engine**: Features automatic host/port discovery (ports 3306 & 3307), automatic database and table creation (`innowave_db`), InnoDB storage, `utf8mb4_unicode_ci` charset, and optimized B-Tree indexes for lightning-fast concurrent lookups. Operates exclusively and purely on MySQL.
3. **Strict 10 to 11 Character PSCMR Admission / Roll Number Validation**: PSCMR CET participants must enter their full 10-11 character roll number (e.g., `22HP1A0501`). Invalid lengths (too short or extra characters) immediately trigger a custom popup alert (`⚠️ GIVE FULL ADMISSION NUMBER`) with real-time character badge feedback.
4. **Official 4K Ultra-HD Delegate Pass Engine**: Generates ~1760×2900px @ 300+ DPI printable physical badges with the official brochure 6-event evaluation checklist and gate verification QR codes. For PSCMR CET participants, their official Admission Number is prominently displayed; for other colleges, it is cleanly omitted.
5. **Mobile View Synchronized with Laptop Experience**: Registration forms, receipt slips, admin action bars, and bank detail containers utilize a modern 2-column grid layout across mobile screens, eliminating cramped single-column stacking while preserving full desktop functionality.
6. **Movable & Draggable Floating "Scroll to Top" Button**: Included across all pages (`index.html`, `register-ieee.html`, `register-non-ieee.html`, `admin.html`, `admin.php`, `verify-id.html`) with dual action: click to smooth-scroll to top, or drag anywhere across the screen.

---

## 🔄 End-to-End System Workflow

```text
                    [ Participant Visits Homepage (index.html) ]
                                       │
                ┌──────────────────────┴──────────────────────┐
                ▼                                             ▼
     [ register-ieee.html ]                       [ register-non-ieee.html ]
   • IEEE Membership Verification               • General Registration
   • Card Screenshot Upload + AI OCR            • Event Selection (Up to 3)
                │                                             │
                └──────────────────────┬──────────────────────┘
                                       ▼
             [ College Affiliation & Dynamic Pricing Calculation ]
      • PSCMR College:     ₹50 (IEEE)  |  ₹100 (Non-IEEE)
      • Other Colleges:    ₹100 (IEEE) |  ₹200 (Non-IEEE)
                                       │
                                       ▼
        [ PSCMR Admission / Roll Number Validation (Strict 10-11 Chars) ]
      • Compulsory for PSCMR CET students (e.g. 22HP1A0501).
      • Length MUST be 10 or 11 characters. If <10 or >11 -> Popup: "⚠️ GIVE FULL ADMISSION NUMBER".
      • Upper-case forced, duplicate roll numbers strictly blocked.
                                       │
                                       ▼
                    [ Unique Participant ID Issued (IW26-XXXX) ]
                                       │
                                       ▼
            [ Dynamic UPI QR Payment Gateway (Karur Vysya Bank) ]
      • Payee: POTTI SRIRAMULU CHALAVADI MALLIKARJUNA RAO COLLEGE
      • VPA: 1414155000131347@kvbl0001414.ifsc
      • Embedded Transaction Note: "InnoWave-2k26 IW26-XXXX"
                                       │
                                       ▼
             [ 12-Digit UTR Submission + Payment Screenshot Upload ]
      • Client-side AI OCR (Tesseract.js) validates UTR in screenshot.
      • Server checks against duplicate UTRs across all registrations.
                                       │
                                       ▼
                [ Pending Approval State: Receipt Slip Download ]
      • Status displays "PENDING ADMIN APPROVAL".
      • Prevents re-submitting payment if screenshot already uploaded.
                                       │
                                       ▼
        [ Organizer Admin Portal (admin.php) — Live MySQL Control ]
      • Protected by secure admin passcode (`innowave2k26`).
      • Organizers verify UTR, payment proof & IEEE card.
      • Actions: "Approve (Paid)", "Mark Pending", "Reject", "Delete".
      • One-click Excel (.CSV) Data Export.
                                       │
                                       ▼
                 [ Official 4K Delegate ID Card Generation ]
      • Rendered via html2canvas (1760×2900px @ 300+ DPI).
      • Displays Admission Number for PSCMR participants.
      • Embedded 6-Event Brochure Evaluation Checklist ([✓]).
      • Scannable gate verification QR code.
                                       │
                                       ▼
               [ Event Day Gate Verification (verify-id.html) ]
      • Gate volunteers scan delegate QR code with any smartphone.
      • Instant authenticity verification & official delegate credentials.
```

---

## 🗄️ Database Architecture (100% MySQL Dedicated)

The database engine is managed by [`public/api/db.php`](file:///public/api/db.php) and [`public/api/config.php`](file:///public/api/config.php).

### 1. Zero-Configuration Auto-Discovery
When the website is loaded, `db.php` automatically performs multi-layer discovery:
1. **Config File**: Reads [`public/api/config.php`](file:///public/api/config.php) (or `.env` if available).
2. **Environment Variables**: Reads `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_PORT` if defined in Apache/Nginx/cPanel.
3. **Host Candidates**: Checks `localhost` and `127.0.0.1`.
4. **Port Auto-Discovery**: Automatically checks standard port **3306** and alternate port **3307**.
5. **Password Fallback**: Tries configured password, then standard host empty password `""`, `root`, `innowave2k26`, `innowave2026`, `password`, `123456`.
6. **Auto-Creation**: If the database `innowave_db` does not exist on the MySQL server, it is created automatically with `utf8mb4_unicode_ci` collation.
7. **Strict MySQL Enforcement**: Operates exclusively against MySQL. If MySQL is unreachable, it provides descriptive connection diagnostic messaging.

### 2. MySQL Table Schema (`registrations`)

```sql
CREATE TABLE IF NOT EXISTS `registrations` (
    `id`                       INT AUTO_INCREMENT PRIMARY KEY,
    `team_id`                  VARCHAR(100) UNIQUE,
    `reg_seq`                  INT,
    `project_title`            VARCHAR(255) DEFAULT 'INNOWAVE-2K26 Registration',
    `track`                    VARCHAR(255) DEFAULT 'Open Innovation',
    `events_selected`          TEXT,
    `description`              TEXT,
    `leader_name`              VARCHAR(255) NOT NULL,
    `leader_email`             VARCHAR(255) NOT NULL,
    `leader_phone`             VARCHAR(100) NOT NULL,
    `college_name`             VARCHAR(255),
    `roll_no`                  VARCHAR(100),
    `branch`                   VARCHAR(100),
    `year`                     VARCHAR(50),
    `ieee_member`              VARCHAR(10) NOT NULL,
    `ieee_id`                  VARCHAR(100),
    `ieee_card`                LONGTEXT,
    `ieee_verification_status`  VARCHAR(100),
    `ieee_email`               VARCHAR(255),
    `ieee_grade`               VARCHAR(100),
    `ieee_count`               INT DEFAULT 0,
    `non_ieee_count`           INT DEFAULT 0,
    `team_size`                INT DEFAULT 1,
    `member2`                  VARCHAR(255),
    `member3`                  VARCHAR(255),
    `member4`                  VARCHAR(255),
    `amount`                   INT DEFAULT 100,
    `fee_label`                VARCHAR(255),
    `payment_mode`             VARCHAR(100) DEFAULT 'Bank Transfer',
    `payment_status`           VARCHAR(100) DEFAULT 'Pending Payment Confirmation',
    `payment_ref`              VARCHAR(255),
    `payment_screenshot`       LONGTEXT,
    `payment_proof`            LONGTEXT,
    `duplicate_utr`            INT DEFAULT 0,
    `utr_mismatch`             INT DEFAULT 0,
    `utr_warning`              VARCHAR(255),
    `ieee_ocr_mismatch`        INT DEFAULT 0,
    `ieee_warning`             VARCHAR(255),
    `paid_at`                  VARCHAR(100),
    `created_at`               VARCHAR(100) NOT NULL,
    INDEX `idx_team_id` (`team_id`),
    INDEX `idx_leader_email` (`leader_email`),
    INDEX `idx_leader_phone` (`leader_phone`),
    INDEX `idx_roll_no` (`roll_no`),
    INDEX `idx_payment_status` (`payment_status`),
    INDEX `idx_payment_ref` (`payment_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 🌐 Production Deployment Guide (ANY Server / Domain / cPanel)

### Deploying to cPanel / PSCMR College Server (`server1.pscmr.ac.in`)

1. **Upload Files**:
   - Upload the contents of the `public/` directory into your target web directory (e.g., `public_html/` or a subfolder like `public_html/innowave/`).
   - Ensure the `.htaccess` file is uploaded.

2. **Configure Database (Optional / Automatic)**:
   - On most servers with standard MySQL running locally, `public/api/db.php` will **automatically connect and provision the database and tables with zero configuration**.
   - If your hosting provider (like cPanel) requires a specific database name and user:
     - Open [`public/api/config.php`](file:///public/api/config.php) and enter your credentials:
       ```php
       define('DB_HOST', 'localhost');
       define('DB_NAME', 'cpaneluser_innowave');
       define('DB_USER', 'cpaneluser_dbuser');
       define('DB_PASS', 'YourPasswordHere');
       define('DB_PORT', 3306);
       ```
     - Alternatively, you can set them in your cPanel `.htaccess`:
       ```apache
       SetEnv DB_HOST localhost
       SetEnv DB_NAME cpaneluser_innowave
       SetEnv DB_USER cpaneluser_dbuser
       SetEnv DB_PASS YourPasswordHere
       ```

3. **Verify File Permissions**:
   ```bash
   chmod -R 755 public_html/
   ```

4. **Access the Website**:
   - Fest Landing Page: `https://your-domain.com/` (or `https://server1.pscmr.ac.in/innowave/`)
   - Organizer Admin Portal: `https://your-domain.com/admin.php` *(Passcode: `innowave2k26`)*
   - Status Tracker: Search directly from the homepage lookup modal or `verify-id.html`.

---

## 🧪 Local & LAN Testing Guide

### Option 1: Built-in PHP Server (Fastest)
```bash
# 1. Open terminal inside the public folder
cd public

# 2. Start server
php -S localhost:8000
```
- Open [http://localhost:8000](http://localhost:8000) in your browser.

### Option 2: Mobile LAN Testing on Same Wi-Fi
```bash
# Bind to all network interfaces
cd public
php -S 0.0.0.0:8000
```
- On your phone browser, open `http://<YOUR_COMPUTER_LOCAL_IP>:8000`.

---

## 📋 Comprehensive Feature Reference

### 1. Admission Number Length Constraint
- **Target**: PSCMR CET College students.
- **Rules**:
  - Length must be between **10 and 11 characters** (e.g., `22HP1A0501`).
  - Cannot type more than 11 characters (`maxlength="11"`).
  - Automatically converted to capital letters (`oninput`).
  - If `< 10` or `> 11` characters, custom modal triggers:
    > **⚠️ GIVE FULL ADMISSION NUMBER**  
    > *Please enter your full admission number! PSCMR College Admission / Roll Number must be 10 to 11 characters (e.g. 22HP1A0501).*
  - Live character badge (`X / 11`) displays real-time character count.
  - Server-side validation in `api/register.php` rejects any invalid length.

### 2. Official Delegate ID Pass Features
- **4K Ultra-HD Resolution**: Renders crisp printable physical badge (~1760×2900px @ 300+ DPI).
- **Admission Number Printing**: For PSCMR CET delegates, the badge includes their Admission / Roll Number. For participants from other colleges, this row is cleanly omitted.
- **6-Event Checklist**: Features official checklist boxes for Technical Quiz, Coding Challenge, Tech Treasure Hunt, Project Expo, Prompt Engineering, and Reels.
- **QR Gate Verification**: Scannable by any mobile device camera to confirm verified registration on `verify-id.html`.

### 3. Dual Distinct Excel (.CSV) Data Exports
The platform provides two dedicated exports tailored specifically to each admin interface:
- **PHP Admin Excel (`api/admin.php?action=export-php`)**:
  - File: `innowave_2k26_php_admin_export_YYYY-MM-DD.csv`
  - Columns: S.No, Participant ID, Payment Status, Amount (₹), Fee Details / Matrix Label, Participant Name, Email Address, Phone Number, College / Institution, PSCMR Admission / Roll Number, Branch / Department, Year of Study, IEEE Member (Yes/No), IEEE Membership ID, IEEE Card Verification Status, Selected Events, 12-Digit UTR / Transaction Ref, Payment Verification Flag, Payment Screenshot Uploaded, Registration Date & Time.
- **HTML Admin Excel (`api/admin.php?action=export-html`)**:
  - File: `innowave_2k26_html_admin_export_YYYY-MM-DD.csv`
  - Columns: S.No, Participant ID, Payment Status, Amount (₹), 12-Digit UTR Reference, UTR Warning / Mismatch Flag, Participant Name, Email Address, Phone Number, College Name, Admission / Roll Number, Branch, Year of Study, IEEE Status, IEEE Membership ID, IEEE Card Verification Status, IEEE Card Uploaded, Payment Screenshot Uploaded, Selected Events, Registered Timestamp.
- Both files include the **UTF-8 Byte Order Mark (BOM)** to ensure flawless opening in Microsoft Excel without character corruption.

### 4. Dynamic Financial Telemetry & Expected Amount Engine
- Real-time calculations computed on the fly across all active registrations:
  - **Total Expected (Should Get)**: Sum of fees across all registered teams.
  - **IEEE Expected Money**: Sum of expected fees specifically for IEEE members.
  - **Non-IEEE Expected Money**: Sum of expected fees specifically for Non-IEEE members.
  - **Actual Received (Got)**: Sum of fees for verified/approved (`Paid`) participants.
  - **Pending Gap (Difference)**: `Total Expected - Actual Received`.
  - Auto-refreshes every 5 seconds dynamically.

### 5. Movable & Draggable Scroll-to-Top Button
- Included on all pages at bottom-right (`#scrollTopBtn`).
- **Click**: Smooth-scrolls viewport to the top.
- **Drag**: Can be dragged and repositioned anywhere on the screen via touch or mouse.

---

## 📁 Repository Directory Structure

```text
innowave2k26/
├── public/                               # Public Web Root Directory
│   ├── index.html                        # Main 3D Fest Landing Page & Status Tracker
│   ├── register-ieee.html                # IEEE Member Registration Portal + AI OCR
│   ├── register-non-ieee.html            # Non-IEEE Registration Portal
│   ├── admin.php                         # Organizer Admin Control & ID Card Generator
│   ├── admin.html                        # Client-side Admin Interface
│   ├── verify-id.html                    # Public Gate Verification Portal
│   ├── .htaccess                         # Apache routing & security rules
│   │
│   ├── api/                              # Backend PHP REST API Endpoints
│   │   ├── config.php                    # Standalone MySQL Database Configuration
│   │   ├── db.php                        # Enterprise MySQL Engine with Auto-Discovery
│   │   ├── register.php                  # Registration processing & admission checks
│   │   ├── submit-utr.php                # UTR submission & duplicate prevention
│   │   ├── check-status.php              # Real-time status lookup API
│   │   ├── admin.php                     # Admin data retrieval & CSV export
│   │   ├── admin-action.php              # Approval, rejection & deletion controller
│   │   └── id-card-data.php              # ID card generation data endpoint
│   │
│   └── data/                             # Data and Schema Definitions
│       └── schema_mysql.sql              # Clean MySQL DDL Schema
└── README.md                             # Root Project Documentation
```

---

## 🔒 Security & Integrity Standards

1. **Gate Pass Integrity**: Delegate badges can only be generated and issued through the authenticated organizer portal (`admin.php`), preventing unauthorized pass downloads.
2. **Strict UTR Deduplication**: Each 12-digit transaction UTR is unique across the entire database; reuse of reference numbers across registrations is blocked.
3. **Strict Duplicate Admission Number Blocking**: Duplicate admission numbers are rejected to prevent identity cloning.
4. **SQL Injection Defense**: Every SQL operation utilizes PHP PDO parameterized prepared statements.
5. **Universal Base URL Resolution**: QR codes on delegate passes dynamically construct verification URLs based on the current host domain, ensuring flawless operation across any live server.

---

## 👑 Author & Attribution

```text
================================================================================
🚀 INNOWAVE-2K26 — NATIONAL LEVEL TECHNICAL FEST WEB PLATFORM
================================================================================
 Designed, Architected, and Engineered by : K. JAIDEEP RAJ
 Organization                            : IEEE Student Branch (STB18301)
 Institution                             : PSCMR College of Engineering & Technology
 Status                                  : 100% Production Ready & Server-Agnostic
================================================================================
```

*Designed, architected, and maintained by **K. JAIDEEP RAJ** for the IEEE Student Branch (STB18301) at PSCMR College of Engineering and Technology.*

© 2026 INNOWAVE-2K26 · IEEE Student Branch (STB18301) · Created by K. JAIDEEP RAJ
