# 🚀 INNOWAVE-2K26 — Official Event Website & Fest Management Platform

> **National-Level Technical Fest Platform · IEEE Student Branch (STB18301) · PSCMR College of Engineering and Technology**
>
> Full-Stack Event Registration, Tiered Pricing Engine, Official Bank Gateway Integration, Native PHP + MySQL Database Architecture, Client-Side AI OCR Verification, Real-Time Admin Dashboard, 4K Ultra-HD Delegate Pass Generator, and Gate Verification System.

---

## 🔗 Official GitHub Repository
- **Repository URL**: [https://github.com/jai07o/Innowave-2026.git](https://github.com/jai07o/Innowave-2026.git)
- **Branch**: `main`

---

## 🌟 Executive Project Overview

**INNOWAVE-2K26** is a production-grade, full-stack event management web platform engineered for the **Engineer's Day Celebration National Level Technical Fest**, organized by the **IEEE Student Branch (STB18301)** at **PSCMR College of Engineering and Technology** (Vijayawada, Andhra Pradesh).

The entire system is architected to run with **zero code modifications** across **ANY server, domain, or hosting environment** (e.g., cPanel, Plesk, Linux VPS, Apache, Nginx, or local test servers).

### Key Architectural Highlights:
1. **Zero-Configuration Universal Deployment**: All asset paths, API endpoints, and redirects utilize relative or dynamically resolved URLs. The website functions identically whether deployed at the root domain or within any subfolder.
2. **Enterprise Pure MySQL Database Engine**: Features automatic host/port discovery (ports 3306 & 3307), automatic database and table creation (`innowave_db`), InnoDB storage, `utf8mb4_unicode_ci` charset, and optimized B-Tree indexes for lightning-fast concurrent lookups.
3. **Institutional Bank Gateway Integration**: Displays institutional bank account details directly during payment submission.
4. **Strict 10 to 11 Character PSCMR Admission / Roll Number Validation**: PSCMR CET participants must enter their full 10-11 character roll number (e.g., `22HP1A0501`). Invalid lengths trigger a popup alert with real-time character badge feedback.
5. **Real-Time Organizer Admin Dashboard (`admin.php`)**: Protected by secure passcode authentication. Provides real-time visibility into all submitted registrations, UTR proofs, payment statuses, financial telemetry, and 1-click Excel (.CSV) data exports.
6. **Official 4K Ultra-HD Delegate Pass Engine**: Generates ~1760×2900px @ 300+ DPI printable physical badges with the official brochure 6-event evaluation checklist and gate verification QR codes.

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
      • Length MUST be 10 or 11 characters. If <10 or >11 -> Alert Popup.
                                       │
                                       ▼
                    [ Unique Participant ID Issued (IW26-XXXX) ]
                                       │
                                       ▼
            [ College Bank Gateway & Payment Box ]
      • Account Name: [INSTITUTION BANK ACCOUNT NAME]
      • Account Number & IFSC: [CONFIGURED IN API/CONFIG.PHP]
                                       │
                                       ▼
             [ 12-Digit UTR Submission + Payment Screenshot Upload ]
      • Saved immediately into MySQL `registrations` table.
                                       │
                                       ▼
        [ Organizer Admin Portal (admin.php) — Live MySQL Control ]
      • Protected by passcode authentication.
      • Real-time participant lookup, UTR verification & IEEE card checks.
      • Actions: "Approve (Paid)", "Mark Pending", "Reject", "Delete".
      • 1-Click Excel (.CSV) Data Export.
                                       │
                                       ▼
                 [ Official 4K Delegate ID Card Generation ]
      • Rendered via html2canvas (1760×2900px @ 300+ DPI).
      • Embedded 6-Event Brochure Evaluation Checklist ([✓]).
      • Scannable gate verification QR code.
                                       │
                                       ▼
               [ Event Day Gate Verification (verify-id.html) ]
      • Gate volunteers scan delegate QR code with any smartphone.
      • Instant authenticity verification & official delegate credentials.
```

---

## 🗄️ Database Architecture (100% Pure MySQL)

The database engine is managed by [`api/db.php`](file:///api/db.php) and [`api/config.php`](file:///api/config.php).

### 1. Zero-Configuration Auto-Discovery
When the website is loaded, `db.php` automatically performs multi-layer discovery:
1. **Config File**: Reads [`api/config.php`](file:///api/config.php) (or `.env` if available).
2. **Environment Variables**: Reads `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_PORT` if defined.
3. **Host Candidates**: Checks `localhost` and `127.0.0.1`.
4. **Port Auto-Discovery**: Checks standard port **3306** and alternate port **3307**.
5. **Auto-Creation**: If `innowave_db` does not exist on MySQL, it is created automatically with `utf8mb4_unicode_ci` collation.

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1001;
```

---

## 🌐 Production Deployment Guide (cPanel / PHP Hosting)

### 1. Upload Files
- Upload all project files to your web root (e.g., `public_html/`).
- Ensure `.htaccess` is included for clean routing.

### 2. Configure Database
- Configure MySQL credentials in [`api/config.php`](file:///api/config.php) or `.env`:
  ```php
  define('DB_HOST', 'localhost');
  define('DB_NAME', 'innowave_db');
  define('DB_USER', 'your_db_user');
  define('DB_PASS', 'your_db_password');
  define('DB_PORT', 3306);
  ```
- Alternatively, import [`data/schema_mysql.sql`](file:///data/schema_mysql.sql) via phpMyAdmin.

### 3. Key Portal URLs
- **Fest Landing Page**: `https://your-domain.com/index.html`
- **IEEE Registration**: `https://your-domain.com/register-ieee.html`
- **Non-IEEE Registration**: `https://your-domain.com/register-non-ieee.html`
- **Organizer Admin Dashboard**: `https://your-domain.com/admin.php`
- **Gate Pass Verification**: `https://your-domain.com/verify-id.html`

---

## 📁 Directory Structure

```text
innowave-website/
├── index.html                        # Main 3D Fest Landing Page & Status Tracker
├── register-ieee.html                # IEEE Member Registration Portal + AI OCR
├── register-non-ieee.html            # Non-IEEE Registration Portal
├── admin.php                         # Organizer Admin Control & ID Card Generator
├── admin.html                        # Client-side Admin Interface
├── verify-id.html                    # Public Gate Verification Portal
├── .htaccess                         # Apache routing & security rules
├── README.md                         # Project Documentation
│
├── api/                              # Backend PHP REST API Endpoints
│   ├── config.php                    # MySQL Database & Admin Passcode Config
│   ├── db.php                        # Enterprise MySQL Engine with Auto-Discovery
│   ├── register.php                  # Registration processing
│   ├── submit-utr.php                # UTR submission & payment handler
│   ├── check-status.php              # Real-time status lookup API
│   ├── admin.php                     # Admin data API & CSV export
│   ├── admin-action.php              # Approval, rejection & deletion controller
│   └── id-card-data.php              # ID card generation data endpoint
│
└── data/                             # Database Schemas
    └── schema_mysql.sql              # Clean MySQL DDL Schema
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
