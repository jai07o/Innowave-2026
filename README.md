# 🚀 INNOWAVE-2K26 — Official Event Website & Fest Management Platform

> **National-Level Technical Fest Web Platform · IEEE Student Branch (STB18301) · PSCMR College of Engineering and Technology**
>
> Full-Stack Event Registration System, Dynamic Tiered Pricing Engine, Client-Side AI OCR Verification, Institutional Payment Gateway Integration, Pure PHP PDO + MySQL Architecture, Live Admin Control Dashboard, 4K Ultra-HD Printable Delegate Badge Pass Generator, Gate QR Code Verification, and Dual CSV Export Engine.

---

## 🔗 Official GitHub Repository
- **Repository URL**: [https://github.com/jai07o/Innowave-2026.git](https://github.com/jai07o/Innowave-2026.git)
- **Branch**: `main`

---

## 📋 Table of Contents
1. [Executive Overview](#-executive-overview)
2. [Technology Stack & Architecture](#-technology-stack--architecture)
3. [Registration & Participant Workflow](#-registration--participant-workflow)
4. [Organizer Admin Portal Workflow & Lifecycle](#-organizer-admin-portal-workflow--lifecycle)
5. [Granular Feature Breakdown (Point-by-Point)](#-granular-feature-breakdown-point-by-point)
   - [1. Fest Landing Page (`index.html`)](#1-fest-landing-page-indexhtml)
   - [2. IEEE Registration Portal (`register-ieee.html`)](#2-ieee-registration-portal-register-ieeehtml)
   - [3. Non-IEEE Registration Portal (`register-non-ieee.html`)](#3-non-ieee-registration-portal-register-non-ieeehtml)
   - [4. Dynamic Pricing & College Affiliation Engine](#4-dynamic-pricing--college-affiliation-engine)
   - [5. Strict Admission / Roll Number Validation](#5-strict-admission--roll-number-validation)
   - [6. Universal Payment Modal & Bank Account Gateway](#6-universal-payment-modal--bank-account-gateway)
   - [7. Client-Side & Server-Side AI OCR Verification Engine](#7-client-side--server-side-ai-ocr-verification-engine)
   - [8. Organizer Admin Portal (`admin.php` & `admin.html`)](#8-organizer-admin-portal-adminphp--adminhtml)
   - [9. Official 4K Ultra-HD Delegate Pass Engine](#9-official-4k-ultra-hd-delegate-pass-engine)
   - [10. Event Day Gate Pass Verification (`verify-id.html`)](#10-event-day-gate-pass-verification-verify-idhtml)
   - [11. Financial Telemetry & Real-Time Statistics](#11-financial-telemetry--real-time-statistics)
   - [12. Dual Excel (.CSV) Data Export Engine](#12-dual-excel-csv-data-export-engine)
   - [13. Movable & Draggable Scroll-to-Top Control](#13-movable--draggable-scroll-to-top-control)
6. [Database Architecture & Schema Specification](#-database-architecture--schema-specification)
7. [Backend PHP REST API Reference](#-backend-php-rest-api-reference)
8. [Universal Server & cPanel Deployment Guide](#-universal-server--cpanel-deployment-guide)
9. [Security & Data Integrity Standards](#-security--data-integrity-standards)
10. [Complete Directory Map](#-complete-directory-map)
11. [Author & Attribution](#-author--attribution)

---

## 🌟 Executive Overview

**INNOWAVE-2K26** is a production-ready, full-stack event management platform engineered for the **Engineer's Day Celebration National Level Technical Fest**, hosted by the **IEEE Student Branch (STB18301)** at **PSCMR College of Engineering and Technology** (Vijayawada, AP).

The platform handles the complete event lifecycle:
- **Participant Onboarding**: Dual registration streams for IEEE and Non-IEEE students across various colleges.
- **Verification**: Real-time AI OCR screenshot reading for IEEE cards and payment UTR receipts.
- **Finance**: Dynamic tiered fee computation and direct payment confirmation.
- **Administration**: A secure admin control portal (`admin.php`) for live tracking, record approval, bulk operations, and CSV exports.
- **Pass Generation & Security**: Automatic 4K physical badge rendering with embedded 6-event evaluation checklists and encrypted QR gate verification.

Designed to be **100% server-agnostic**, the platform operates on any standard PHP 7.4+ / 8.x + MySQL server (cPanel, Plesk, Apache, Nginx, or Docker) with zero code modifications required.

---

## 🛠️ Technology Stack & Architecture

- **Frontend Core**: HTML5, Vanilla JavaScript (ES6+), Modern CSS3 with HSL CSS variables, Grid/Flexbox layouts, Glassmorphism UI, and dark mode support.
- **Frontend Libraries**:
  - `Tesseract.js`: Browser-side AI Optical Character Recognition (OCR) for IEEE card & UTR verification.
  - `html2canvas`: 300+ DPI high-resolution canvas rendering for printable delegate passes.
  - `QRCode.js`: Dynamic client-side vector QR code generation.
  - `FontAwesome 6.4`: High-density icon system.
  - `Google Fonts`: Inter & Outfit typography system.
- **Backend Stack**: Native PHP PDO (PHP Data Objects) with parameterized queries for complete protection against SQL injection.
- **Database Engine**: MySQL 5.7+ / 8.0+ / MariaDB (`innowave_db`) with InnoDB storage engine, B-Tree indexes, and `utf8mb4_unicode_ci` charset.
- **Routing & Web Server**: Apache `.htaccess` clean URL rewrite engine, GZIP compression headers, and security headers.

---

## 🔄 Registration & Participant Workflow

```text
                                [ User Visits Website (index.html) ]
                                                 │
                        ┌────────────────────────┴────────────────────────┐
                        ▼                                                 ▼
             [ register-ieee.html ]                            [ register-non-ieee.html ]
    • Upload IEEE ID Card Screenshot                  • Select Technical Fest Events (Up to 3)
    • AI OCR Extract ID & Member Name                 • Enter Leader & Team Details
    • Auto-Validate Membership                        • Specify College & Affiliation
                        │                                                 │
                        └────────────────────────┬────────────────────────┘
                                                 ▼
                           [ College Affiliation & Dynamic Fee Engine ]
                  • PSCMR College Student:    ₹50 (IEEE)  | ₹100 (Non-IEEE)
                  • Other College Student:    ₹100 (IEEE) | ₹200 (Non-IEEE)
                                                 │
                                                 ▼
                     [ Admission / Roll Number Length Check (Strict 10-11 Chars) ]
                  • Compulsory for PSCMR CET delegates (e.g. 22HP1A0501).
                  • Length validated client-side & server-side.
                                                 │
                                                 ▼
                         [ Instant Database Record Created (INNO-XXXX) ]
                                                 │
                                                 ▼
                      [ Institutional Bank Account & Payment Box Displayed ]
                  • Payee Name, Account Number, IFSC, and Bank Details presented.
                  • Unique Participant ID linked to transaction note.
                                                 │
                                                 ▼
                       [ 12-Digit UTR Input + Payment Screenshot Upload ]
                  • Client-side AI OCR extracts UTR from payment receipt.
                  • Saved immediately to MySQL `registrations` table.
                  • Direct transition to receipt confirmation state.
```

---

## 👑 Organizer Admin Portal Workflow & Lifecycle

The **Organizer Admin Portal (`admin.php` & `admin.html`)** provides complete end-to-end control over all participant records, payment verifications, delegate pass issuance, and financial telemetry.

```text
                       [ Organizer Opens Admin Portal (admin.php) ]
                                            │
                                            ▼
                    [ Secure Passcode Authentication Verification ]
             • Verifies against `ADMIN_PASSCODE` in `api/config.php`.
                                            │
                                            ▼
                [ Real-Time Data Ingestion & Live Table Rendering ]
             • Direct PDO query against MySQL `registrations` table.
             • Renders ALL registrations immediately (No hidden records).
                                            │
                                            ▼
                   ┌────────────────────────┼────────────────────────┐
                   ▼                        ▼                        ▼
         [ Live Search & Filter ]    [ Financial Telemetry ]   [ Record Verification ]
         • Instant search by:        • Total Expected (₹)      • Click "View Proof" to inspect
           ID (INNO-XXXX), Name,     • Actual Received (Got)     UTR receipt screenshot.
           Email, Phone, College,    • Pending Gap (₹)         • Check ⚠️ Duplicate UTR Warning.
           Roll No, or 12-Digit UTR. • IEEE vs Non-IEEE money  • Inspect IEEE Card & OCR flags.
                   │                        │                        │
                   └────────────────────────┼────────────────────────┘
                                            ▼
                        [ Execute Administrative Action ]
            ┌───────────────────────────────┼───────────────────────────────┐
            ▼                               ▼                               ▼
    [ Approve (Paid) ]              [ Mark Pending ]                [ Reject / Delete ]
  • Status -> "Paid"              • Reverts status to            • Rejects invalid records or
  • Sets `paid_at` timestamp        "Pending Confirmation"         deletes duplicate test entries
  • Unlocks 4K Delegate Pass        for re-inspection.             from MySQL database.
            │
            ▼
    [ Official 4K Delegate Pass Generation ]
  • One-click trigger renders ~1760×2900px badge (`html2canvas`).
  • Renders Admission Number for PSCMR delegates.
  • Displays official 6-Event Brochure Evaluation Checklist ([✓]).
  • Generates embedded gate verification QR code.
            │
            ▼
    [ Event Day Gate Verification (verify-id.html) ]
  • Volunteers scan pass QR code with smartphone.
  • Verifies pass authenticity & event eligibility.
            │
            ▼
    [ 1-Click Excel (.CSV) Data Export Engine ]
  • Downloads full UTF-8 BOM formatted spreadsheet (`innowave_2k26_php_admin_export_YYYY-MM-DD.csv`).
```

### Granular Step-by-Step Admin Workflow:

1. **Authentication & Access Control**:
   - Organizers navigate to `https://your-domain.com/admin.php`.
   - Access is secured by passcode verification configured in [`api/config.php`](file:///api/config.php).
   - Once authenticated, an admin session is initialized.

2. **Real-Time Registration Stream**:
   - All submitted registrations appear in real time without arbitrary status filters.
   - Status indicators clearly demarcate records:
     - 🟢 **Paid / Approved**: Payment confirmed, delegate pass unlocked.
     - 🟡 **Pending Payment Confirmation**: Form submitted, awaiting UTR or proof verification.
     - 🔴 **Rejected**: Invalid transaction or unverified proof.

3. **Proof Inspection & Warning Badges**:
   - **Payment Screenshot Modal**: Organizers click **View Proof** to view the full resolution payment receipt.
   - **IEEE Card Screenshot Modal**: Organizers click **View IEEE Card** to inspect membership cards.
   - **⚠️ Duplicate UTR Badge**: Highlights if a 12-digit UTR number has been submitted by more than one participant.
   - **⚠️ OCR Mismatch Badge**: Flags entries where AI OCR extracted data differs from user input.

4. **Action Handlers (`api/admin-action.php`)**:
   - **Approve (Paid)**: Updates `payment_status = 'Paid'`, updates `paid_at = NOW()`, and enables instant delegate ID pass generation.
   - **Mark Pending**: Resets status to `Pending Payment Confirmation`.
   - **Reject**: Marks status as `Rejected`.
   - **Delete**: Permanently removes entry from the MySQL `registrations` table.

5. **Delegate Pass Generation**:
   - Organizers click **Print / View Pass** on any approved participant row.
   - Opens `#idCardModal`, rendering a high-definition 4K badge (~1760×2900px @ 300+ DPI).
   - Includes student photo, team ID, branch, year, college, roll number (for PSCMR delegates), brochure evaluation checklist, and vector QR verification code.

6. **Gate QR Verification (`verify-id.html`)**:
   - Gate volunteers scan the badge QR code on event day.
   - Instantly checks the live MySQL database and confirms delegate authenticity and eligible events.

7. **Financial Telemetry & CSV Export**:
   - Top metrics dashboard calculates **Total Expected Amount**, **Actual Collected Amount**, **Pending Difference**, **IEEE Revenue**, and **Non-IEEE Revenue**.
   - Organizers click **Export to Excel (.CSV)** to download a complete spreadsheet containing all fields with UTF-8 BOM encoding.

---

## 📌 Granular Feature Breakdown (Point-by-Point)

### 1. Fest Landing Page (`index.html`)
- **Hero Banner & 3D Visuals**: Animated hero header, dynamic event countdown timer, and interactive call-to-action buttons.
- **Event Catalog**: Visual event cards detailing track information, event timing, rules, and coordinators for 6 flagship events:
  1. *Technical Quiz*
  2. *Coding Challenge*
  3. *Tech Treasure Hunt*
  4. *Project Expo*
  5. *Prompt Engineering*
  6. *Reels Making*
- **Live Status Tracker Modal**: Search modal allowing participants to enter their Participant ID (`INNO-XXXX`), Email, or Phone to check registration and payment approval status in real time.

### 2. IEEE Registration Portal (`register-ieee.html`)
- **Card Upload & AI OCR Scanning**: Drag-and-drop file uploader for IEEE ID card screenshots.
- **Client-Side Validation**: Integrates Tesseract.js to scan the uploaded card for IEEE Member ID, Member Name, and Expiry Grade.
- **Dynamic Field Auto-Fill**: Automatically extracts and fills the participant's name and IEEE ID upon successful scanning.
- **Mismatch Warning Flags**: Automatically flags potential mismatches between entered details and scanned card details for admin review.

### 3. Non-IEEE Registration Portal (`register-non-ieee.html`)
- **Multi-Event Selection Matrix**: Allows delegates to select single or multiple events with dynamic fee aggregation.
- **Team Size Selection**: Configurable team size input (1 to 4 members) with input fields for co-team members (Member 2, Member 3, Member 4).
- **Institution Selector**: Auto-completing college selection dropdown featuring PSCMR College as the primary choice alongside custom college input.

### 4. Dynamic Pricing & College Affiliation Engine
The system dynamically computes participation fees based on membership status and institutional affiliation:
- **PSCMR College Students**:
  - IEEE Member: **₹50**
  - Non-IEEE Member: **₹100**
- **Other College Students**:
  - IEEE Member: **₹100**
  - Non-IEEE Member: **₹200**
- **Real-Time Matrix Labeling**: Attaches descriptive fee labels (e.g. `PSCMR CET — IEEE Member Rate (₹50)`) to the database record.

### 5. Strict Admission / Roll Number Validation
- **Target Audience**: Compulsory for all PSCMR College of Engineering & Technology delegates.
- **Constraint**: Input length **MUST be exactly 10 or 11 characters** (e.g., `22HP1A0501`).
- **Real-Time UX**:
  - Input field automatically forces uppercase text (`oninput="this.value = this.value.toUpperCase()"`).
  - Character counter badge (`X / 11`) provides live character feedback.
  - If length is `< 10` or `> 11` characters upon submission, custom modal alert triggers:
    > **⚠️ GIVE FULL ADMISSION NUMBER**  
    > *Please enter your full admission number! PSCMR College Admission / Roll Number must be 10 to 11 characters (e.g. 22HP1A0501).*
- **Server-Side Enforcement**: `api/register.php` verifies the string length server-side and rejects invalid entries.

### 6. Universal Payment Modal & Bank Account Gateway
- **Institutional Bank Details Box**: Displays the institution's official bank account details inside `#paymentModal`:
  - Account Holder Name
  - Bank Name & Branch
  - Account Number
  - IFSC Code
- **Transaction Note**: Displays the participant's unique ID (`INNO-XXXX`) to be entered in payment remarks.
- **UTR Input**: 12-digit numeric Bank UTR / Reference Number input field.
- **Payment Screenshot Upload**: File uploader for proof of transfer receipt.

### 7. Client-Side & Server-Side AI OCR Verification Engine
- **Payment Receipt Scanning**: Runs Tesseract.js on the payment screenshot to search for the 12-digit UTR string.
- **Duplicate UTR Prevention**: Checks submitted UTR numbers against existing database entries. If a UTR number is already registered to another team:
  - Database marks `duplicate_utr = 1`.
  - Admin dashboard displays a **⚠️ Duplicate UTR Warning** badge.

### 8. Organizer Admin Portal (`admin.php` & `admin.html`)
- **Passcode Protection**: Secured via admin passcode verification configured in `api/config.php`.
- **Server-Side & Client-Side Dashboards**:
  - `admin.php`: Native PHP dashboard that queries MySQL directly and renders server-side table rows.
  - `admin.html`: REST API-driven single-page dashboard.
- **Real-Time Management Controls**:
  - Search & filter by Participant ID, Name, Email, Phone, College, UTR, or Status (`Paid`, `Pending`, `Rejected`).
  - Action buttons: **Approve (Paid)**, **Mark Pending**, **Reject**, **Delete**.
  - Modal image previewers for viewing IEEE Cards and Payment Screenshots.
  - 1-click Official Delegate Pass preview & download.

### 9. Official 4K Ultra-HD Delegate Pass Engine
- **Ultra-HD Resolution**: Renders high-density ~1760×2900px @ 300+ DPI physical printable badges using `html2canvas`.
- **PSCMR Admission Number Display**: For PSCMR delegates, their official Admission / Roll Number is prominently rendered on the badge; cleanly omitted for delegates from other colleges.
- **Brochure 6-Event Evaluation Checklist**: Includes official checkmark boxes (`[✓]`) for all fest events:
  - Technical Quiz
  - Coding Challenge
  - Tech Treasure Hunt
  - Project Expo
  - Prompt Engineering
  - Reels Making
- **Encrypted Gate Verification QR**: Generates dynamic vector QR codes linking directly to `verify-id.html?id=INNO-XXXX`.

### 10. Event Day Gate Pass Verification (`verify-id.html`)
- **Mobile QR Scanner**: Gate volunteers scan delegate badges using any smartphone camera or built-in web scanner.
- **Instant Validation Display**: Shows immediate pass status:
  - 🟢 **VERIFIED DELEGATE**: Displays delegate photo, name, college, roll number, fee status, and eligible events.
  - 🟡 **PENDING APPROVAL**: Prompts gate team to verify payment receipt.
  - 🔴 **INVALID / NOT FOUND**: Alerts security of unverified or fraudulent passes.

### 11. Financial Telemetry & Real-Time Statistics
Calculated dynamically across all active database records in `admin.php`:
- **Total Expected Revenue**: Total computed fees for all registered teams.
- **IEEE Expected Revenue**: Sum of expected fees from IEEE members.
- **Non-IEEE Expected Revenue**: Sum of expected fees from Non-IEEE delegates.
- **Actual Received Revenue**: Total fee amount collected from approved (`Paid`) delegates.
- **Pending Revenue Gap**: `Total Expected - Actual Received`.

### 12. Dual Excel (.CSV) Data Export Engine
- **PHP Admin CSV Export (`api/admin.php?action=export-php`)**: Exports complete registration details, UTR flags, fee labels, full team member names, and timestamps.
- **HTML Admin CSV Export (`api/admin.php?action=export-html`)**: Formatted for client-side JavaScript dashboard.
- **UTF-8 Byte Order Mark (BOM)**: Includes `\xEF\xBB\xBF` prefix to ensure numbers and special characters open without character encoding issues in Microsoft Excel.

### 13. Movable & Draggable Scroll-to-Top Control
- Floating action button `#scrollTopBtn` included across all pages (`index.html`, `register-ieee.html`, `register-non-ieee.html`, `admin.php`, `admin.html`, `verify-id.html`).
- **Dual Action**:
  - **Click**: Smoothly scrolls the window to the top.
  - **Drag**: Can be dragged and repositioned anywhere across the viewport via touch or mouse.

---

## 🗄️ Database Architecture & Schema Specification

The database engine is managed by [`api/db.php`](file:///api/db.php) and [`api/config.php`](file:///api/config.php).

### 1. Database Connection & Discovery Rules
1. **Config Loading**: Reads [`api/config.php`](file:///api/config.php) or `.env`.
2. **Auto-Discovery**: Scans `DB_HOST` (`localhost`, `127.0.0.1`), ports (`3306`, `3307`), and standard password fallbacks.
3. **Database Auto-Provisioning**: Creates `innowave_db` automatically if missing with `utf8mb4_unicode_ci` collation.

### 2. MySQL `registrations` Table Schema

```sql
CREATE DATABASE IF NOT EXISTS `innowave_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `innowave_db`;

CREATE TABLE IF NOT EXISTS `registrations` (
  `id`                       INT AUTO_INCREMENT PRIMARY KEY,
  `team_id`                  VARCHAR(100) UNIQUE NOT NULL,
  `reg_seq`                  INT NOT NULL DEFAULT 1,
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
  `ieee_member`              VARCHAR(10) NOT NULL DEFAULT 'No',
  `ieee_id`                  VARCHAR(100) DEFAULT NULL,
  `ieee_card`                LONGTEXT DEFAULT NULL,
  `ieee_verification_status`  VARCHAR(100) DEFAULT 'N/A',
  `ieee_email`               VARCHAR(255) DEFAULT NULL,
  `ieee_grade`               VARCHAR(100) DEFAULT NULL,
  `ieee_count`               INT DEFAULT 0,
  `non_ieee_count`           INT DEFAULT 0,
  `team_size`                INT DEFAULT 1,
  `member2`                  VARCHAR(255) DEFAULT NULL,
  `member3`                  VARCHAR(255) DEFAULT NULL,
  `member4`                  VARCHAR(255) DEFAULT NULL,
  `amount`                   INT DEFAULT 100,
  `fee_label`                VARCHAR(255) DEFAULT NULL,
  `payment_mode`             VARCHAR(100) DEFAULT 'Bank Transfer',
  `payment_status`           VARCHAR(100) DEFAULT 'Pending Payment Confirmation',
  `payment_ref`              VARCHAR(255) DEFAULT NULL,
  `payment_screenshot`       LONGTEXT DEFAULT NULL,
  `payment_proof`            LONGTEXT DEFAULT NULL,
  `duplicate_utr`            INT DEFAULT 0,
  `utr_mismatch`             INT DEFAULT 0,
  `utr_warning`              VARCHAR(255) DEFAULT NULL,
  `ieee_ocr_mismatch`        INT DEFAULT 0,
  `ieee_warning`             VARCHAR(255) DEFAULT NULL,
  `paid_at`                  VARCHAR(100) DEFAULT NULL,
  `created_at`               VARCHAR(100) NOT NULL,
  INDEX `idx_team_id` (`team_id`),
  INDEX `idx_leader_email` (`leader_email`),
  INDEX `idx_leader_phone` (`leader_phone`),
  INDEX `idx_roll_no` (`roll_no`),
  INDEX `idx_payment_status` (`payment_status`),
  INDEX `idx_payment_ref` (`payment_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1001;
```

---

## 📡 Backend PHP REST API Reference

| Endpoint | Method | Purpose | Input Parameters / Body |
| :--- | :---: | :--- | :--- |
| `api/register.php` | `POST` | Processes team & individual registrations | `leader_name`, `leader_email`, `leader_phone`, `college_name`, `roll_no`, `ieee_member`, `ieee_id`, `events_selected` |
| `api/submit-utr.php` | `POST` | Submits 12-digit UTR number & payment receipt | `team_id` or `registration_id`, `payment_ref` (UTR), `payment_screenshot` |
| `api/check-status.php` | `GET` | Fetches participant registration & payment status | `?query=INNO-XXXX` or `?query=email@domain.com` or `?query=9876543210` |
| `api/admin.php` | `GET` | Fetches all registration records for admin dashboard | Protected by admin session / passcode |
| `api/admin.php?action=export-php` | `GET` | Generates UTF-8 BOM CSV spreadsheet download | Protected by admin session |
| `api/admin-action.php` | `POST` | Updates record payment status or deletes entries | `id`, `action` (`approve`, `pending`, `reject`, `delete`) |
| `api/id-card-data.php` | `GET` | Retrieves full JSON payload to render 4K delegate badge | `?id=INNO-XXXX` |

---

## 🌐 Universal Server & cPanel Deployment Guide

### 1. File Upload
- Upload all project files directly to your target directory (e.g., `public_html/` or a subfolder like `public_html/innowave/`).
- Ensure `.htaccess` is uploaded to enable URL rewrites.

### 2. Database Setup
- Open [`api/config.php`](file:///api/config.php) and configure your MySQL database credentials:
  ```php
  define('DB_HOST', 'localhost');
  define('DB_NAME', 'innowave_db');
  define('DB_USER', 'your_db_username');
  define('DB_PASS', 'your_db_password');
  define('DB_PORT', 3306);
  ```
- Alternatively, import [`data/schema_mysql.sql`](file:///data/schema_mysql.sql) using phpMyAdmin.

### 3. Portal Verification
- **Fest Homepage**: `https://your-domain.com/index.html`
- **IEEE Registration**: `https://your-domain.com/register-ieee.html`
- **Non-IEEE Registration**: `https://your-domain.com/register-non-ieee.html`
- **Organizer Admin Dashboard**: `https://your-domain.com/admin.php`
- **Gate Pass Scanner**: `https://your-domain.com/verify-id.html`

---

## 🔒 Security & Data Integrity Standards

1. **SQL Injection Defense**: 100% of database queries use PDO prepared statements with strict parameter binding.
2. **Duplicate UTR Detection**: Duplicate 12-digit UTR numbers are flagged immediately to prevent fraudulent multiple uses of a single payment receipt.
3. **Roll Number Format Enforcement**: Prevents entry of truncated or incorrect roll numbers.
4. **XSS Protection**: HTML special characters are escaped on input and output.
5. **Passcode Protection**: Admin actions require authentication against `ADMIN_PASSCODE` in `api/config.php`.

---

## 📁 Complete Directory Map

```text
innowave-website/
├── index.html                        # Main Fest Landing Page & Status Tracker Modal
├── register-ieee.html                # IEEE Member Registration Portal with AI OCR
├── register-non-ieee.html            # Non-IEEE Registration Portal
├── admin.php                         # Server-Side Organizer Admin Dashboard
├── admin.html                        # Client-Side Single-Page Admin Dashboard
├── verify-id.html                    # Public Gate Verification Portal
├── .htaccess                         # Apache routing & GZIP rules
├── README.md                         # Complete Technical Documentation
│
├── api/                              # Backend PHP REST API Endpoints
│   ├── config.php                    # Database & Admin Passcode Configuration
│   ├── db.php                        # Auto-Discovery MySQL Engine
│   ├── register.php                  # Registration Handler Endpoint
│   ├── submit-utr.php                # UTR & Payment Screenshot Handler
│   ├── check-status.php              # Registration Status Search API
│   ├── admin.php                     # Admin Data & CSV Export Endpoint
│   ├── admin-action.php              # Record Status Update Controller
│   └── id-card-data.php              # ID Card Payload Endpoint
│
└── data/                             # Database Schemas
    └── schema_mysql.sql              # MySQL DDL Schema
```

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

© 2026 INNOWAVE-2K26 · IEEE Student Branch (STB18301) · Created by K. JAIDEEP RAJ
