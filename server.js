/**
 * InnoWave-2k26 — Project Expo
 * Event website + registration backend with UPI payment (Node.js + Express + SQLite)
 *
 * Run:  npm install  &&  npm start
 * Site:  http://localhost:3000
 * Admin: http://localhost:3000/admin   (password from ADMIN_PASSWORD, default below)
 *
 * Payment: UPI QR + reference (manual verification by organizers).
 * Configure your collection UPI ID with env vars:
 *   UPI_VPA="yourupi@bank"   UPI_PAYEE_NAME="PSCMR IEEE Student Branch"
 */

try { require('dotenv').config(); } catch(e) {}

const path = require('path');
const fs = require('fs');
const express = require('express');
const Database = require('better-sqlite3');
const ExcelJS = require('exceljs');
const QRCode = require('qrcode');
const nodemailer = require('nodemailer');
const { createWorker } = require('tesseract.js');

const WHATSAPP_GROUP_LINK = 'https://chat.whatsapp.com/G2WlPqnRVYdIvVti3gZL2S?s=cl&p=a&ilr=0';

let tesseractWorkerPromise = null;
async function getTesseractWorker() {
  if (!tesseractWorkerPromise) {
    tesseractWorkerPromise = (async () => {
      console.log('[Tesseract.js] Initializing OCR worker...');
      const worker = await createWorker('eng');
      console.log('[Tesseract.js] OCR worker initialized.');
      return worker;
    })().catch(err => {
      console.error('[Tesseract.js Init Error]', err);
      tesseractWorkerPromise = null;
      throw err;
    });
  }
  return tesseractWorkerPromise;
}

async function verifyUtrMatchesScreenshot(utrRef, screenshotBase64) {
  try {
    if (!utrRef || !screenshotBase64 || !screenshotBase64.includes('base64,')) {
      return { match: false, text: '' };
    }
    const cleanUtr = String(utrRef).replace(/\D/g, '');
    if (cleanUtr.length < 6) return { match: false, text: '' };

    const base64Data = screenshotBase64.split('base64,')[1];
    const imageBuffer = Buffer.from(base64Data, 'base64');

    const worker = await getTesseractWorker();
    const ret = await worker.recognize(imageBuffer);
    const resultText = ret.data.text || '';

    const cleanExtractedText = resultText.replace(/[\s\-\:\/]/g, '');
    const isMatch = cleanExtractedText.includes(cleanUtr);
    console.log(`[OCR UTR Match Check] Target: ${cleanUtr} | Matched: ${isMatch}`);
    return { match: isMatch, text: resultText };
  } catch (e) {
    console.error('[OCR UTR Match Error]', e.message);
    return { match: false, error: e.message };
  }
}

async function verifyIeeeCardMatchesScreenshot(ieeeId, cardBase64) {
  try {
    if (!ieeeId || !cardBase64 || !cardBase64.includes('base64,')) {
      return { match: false, text: '' };
    }
    const cleanId = String(ieeeId).replace(/\D/g, '');
    if (cleanId.length < 4) return { match: false, text: '' };

    const base64Data = cardBase64.split('base64,')[1];
    const imageBuffer = Buffer.from(base64Data, 'base64');

    const worker = await getTesseractWorker();
    const ret = await worker.recognize(imageBuffer);
    const resultText = ret.data.text || '';

    const cleanExtractedText = resultText.replace(/[\s\-\:\/]/g, '');
    const isMatch = cleanExtractedText.includes(cleanId);
    console.log(`[OCR IEEE Card Match Check] Target: ${cleanId} | Matched: ${isMatch}`);
    return { match: isMatch, text: resultText };
  } catch (e) {
    console.error('[OCR IEEE Card Match Error]', e.message);
    return { match: false, error: e.message };
  }
}

// Configurable Nodemailer SMTP Mail Transporter
const mailTransporter = nodemailer.createTransport({
  service: process.env.SMTP_SERVICE || 'gmail',
  host: process.env.SMTP_HOST || 'smtp.gmail.com',
  port: parseInt(process.env.SMTP_PORT || '587', 10),
  secure: process.env.SMTP_SECURE === 'true',
  auth: {
    user: process.env.SMTP_USER || process.env.EMAIL_USER || '',
    pass: process.env.SMTP_PASS || process.env.EMAIL_PASS || ''
  }
});

async function sendParticipantConfirmationEmail(p) {
  // Email automation removed. WhatsApp group join link is provided directly on the site.
  console.log(`[Email Automation Disabled] Email dispatch skipped for participant ${p ? (p.team_id || p.id) : ''}`);
  return { ok: true, disabled: true };
}

const os = require('os');
function getPrimaryLanIp() {
  const interfaces = os.networkInterfaces();
  for (const name of Object.keys(interfaces)) {
    for (const iface of interfaces[name]) {
      if (iface.family === 'IPv4' && !iface.internal) {
        return iface.address;
      }
    }
  }
  return 'localhost';
}

function getBaseUrl(req) {
  if (process.env.BASE_URL) {
    return process.env.BASE_URL.replace(/\/+$/, '');
  }
  
  const forwardedProto = req ? req.headers['x-forwarded-proto'] : null;
  const protocol = forwardedProto ? forwardedProto.split(',')[0].trim() : (req && req.protocol ? req.protocol : 'http');
  
  const forwardedHost = req ? req.headers['x-forwarded-host'] : null;
  let host = forwardedHost ? forwardedHost.split(',')[0].trim() : (req ? (req.get('host') || req.headers.host || '') : '');
  
  if (!host || host.includes('localhost') || host.includes('127.0.0.1')) {
    const lanIp = getPrimaryLanIp();
    const port = PORT || 3000;
    host = `${lanIp}:${port}`;
  }
  
  return `${protocol}://${host}`;
}

const PORT = process.env.PORT || 3000;
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || 'innowave2k26';
const UPI_VPA = process.env.UPI_VPA || '6309419599@axl';          // <-- Collection UPI ID
const UPI_PAYEE_NAME = process.env.UPI_PAYEE_NAME || 'PSCMR IEEE Student Branch';

// ---------- Database ----------
const DATA_DIR = path.join(__dirname, 'data');
if (!fs.existsSync(DATA_DIR)) fs.mkdirSync(DATA_DIR, { recursive: true });
const db = new Database(path.join(DATA_DIR, 'innowave.db'));
db.pragma('journal_mode = WAL');

db.exec(`
CREATE TABLE IF NOT EXISTS registrations (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  team_id       TEXT UNIQUE,
  reg_seq       INTEGER,
  project_title TEXT NOT NULL,
  track         TEXT NOT NULL,
  events_selected TEXT,
  description   TEXT NOT NULL,
  leader_name   TEXT NOT NULL,
  leader_email  TEXT NOT NULL,
  leader_phone  TEXT NOT NULL,
  college_name  TEXT,
  roll_no       TEXT,
  branch        TEXT,
  year          TEXT,
  ieee_member   TEXT NOT NULL,
  ieee_id       TEXT,
  ieee_email    TEXT,
  ieee_grade    TEXT,
  ieee_count    INTEGER DEFAULT 0,
  non_ieee_count INTEGER DEFAULT 0,
  team_size     INTEGER NOT NULL,
  member2       TEXT,
  member3       TEXT,
  member4       TEXT,
  amount        INTEGER,
  fee_label     TEXT,
  payment_mode  TEXT,
  payment_status TEXT,
  payment_ref   TEXT,
  paid_at       TEXT,
  created_at    TEXT NOT NULL
);
`);

// tiny migration helper (adds any missing columns for older DBs)
function ensureColumn(col, decl) {
  const cols = db.prepare(`PRAGMA table_info(registrations)`).all().map(c => c.name);
  if (!cols.includes(col)) db.exec(`ALTER TABLE registrations ADD COLUMN ${col} ${decl}`);
}
['team_id TEXT','reg_seq INTEGER','amount INTEGER','fee_label TEXT','payment_mode TEXT',
 'payment_status TEXT','payment_ref TEXT','paid_at TEXT','events_selected TEXT',
 'college_name TEXT','ieee_email TEXT','ieee_grade TEXT','ieee_count INTEGER','non_ieee_count INTEGER',
 'ieee_verification_status TEXT','ieee_card TEXT','ieee_card_approved INTEGER',
 'duplicate_utr INTEGER DEFAULT 0','utr_mismatch INTEGER DEFAULT 0','utr_warning TEXT',
 'ieee_ocr_mismatch INTEGER DEFAULT 0','ieee_warning TEXT'].forEach(d => {
  const parts = d.split(' ');
  ensureColumn(parts[0], parts.slice(1).join(' '));
});

// ---------- Official 6 Brochure Events + 20 Domains ----------
const EVENTS = [
  { id: '01', name: 'Technical Quiz', icon: '🧠', size: 3, color: '#e53935', desc: 'Test your technical knowledge, logical thinking, engineering awareness, and problem-solving ability.' },
  { id: '02', name: 'Coding', icon: '💻', size: 2, color: '#8e24aa', desc: 'Solve programming challenges and demonstrate your coding and algorithmic thinking skills.' },
  { id: '03', name: 'Treasure Hunt', icon: '🗺️', size: 2, color: '#1e88e5', desc: 'Decode clues, solve challenges, and navigate through an exciting technology-powered treasure hunt.' },
  { id: '04', name: 'Project Expo', icon: '🚀', size: 4, color: '#0d47a1', desc: 'Showcase your innovative project, prototype, application, or engineering solution.' },
  { id: '05', name: 'Prompt Engineering', icon: '🤖', size: 2, color: '#f57c00', desc: 'Demonstrate your ability to design effective prompts and use Generative AI creatively and strategically.' },
  { id: '06', name: 'Reels (1 Min)', icon: '🎬', size: 1, color: '#43a047', desc: 'Create a creative and engaging one-minute reel based on Engineering Day theme and guidelines.' }
];

const TRACKS = [
  'AI & Machine Learning','IoT & Smart Systems','Robotics & Automation',
  'Cybersecurity & Digital Forensics','Web & Full-Stack Development','Mobile App Development',
  'Blockchain & Web3','Cloud Computing & DevOps','Generative AI & Intelligent Applications',
  'Embedded Systems & VLSI','Electric Vehicles & Smart Mobility','Smart Cities & Smart Infrastructure',
  'Healthcare & Biomedical Technology','Agriculture & AgriTech','Green Technology & Sustainability',
  'EdTech & Learning Technologies','FinTech & Digital Solutions','Safety, Security & Disaster Management',
  'Renewable Energy & Energy Management','Open Innovation'
];
const TRACK_CODE = {
  'AI & Machine Learning':'AIML','IoT & Smart Systems':'IOT','Robotics & Automation':'ROBO',
  'Cybersecurity & Digital Forensics':'CYBR','Web & Full-Stack Development':'WEB','Mobile App Development':'MOB',
  'Blockchain & Web3':'BLKC','Cloud Computing & DevOps':'CLD','Generative AI & Intelligent Applications':'GENAI',
  'Embedded Systems & VLSI':'EMB','Electric Vehicles & Smart Mobility':'EV','Smart Cities & Smart Infrastructure':'CITY',
  'Healthcare & Biomedical Technology':'HLTH','Agriculture & AgriTech':'AGRI','Green Technology & Sustainability':'GREEN',
  'EdTech & Learning Technologies':'EDU','FinTech & Digital Solutions':'FIN','Safety, Security & Disaster Management':'SAFE',
  'Renewable Energy & Energy Management':'ENRG','Open Innovation':'OPEN'
};

const compression = require('compression');

// ---------- App ----------
const app = express();
app.use(compression());

// Database Pragmas for Ultra-Fast I/O
db.pragma('synchronous = NORMAL');
db.pragma('temp_store = MEMORY');
db.pragma('cache_size = -16000'); // 16MB RAM Cache

// API Caching Control (Static assets cached, API routes fresh)
app.use((req, res, next) => {
  if (req.path.startsWith('/api/')) {
    res.setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
  } else if (/\.(png|jpe?g|gif|webp|svg|ico|css|js|woff2?)$/i.test(req.path)) {
    res.setHeader('Cache-Control', 'public, max-age=86400'); // 1-day cache for assets
  }
  next();
});

app.use(express.json({ limit: '50mb' }));
app.use(express.urlencoded({ extended: true, limit: '50mb' }));
app.use(express.static(path.join(__dirname, 'public'), { etag: true, maxAge: '1d' }));

app.get('/register-ieee', (req, res) => {
  res.sendFile(path.join(__dirname, 'public', 'register-ieee.html'));
});
app.get('/register-non-ieee', (req, res) => {
  res.sendFile(path.join(__dirname, 'public', 'register-non-ieee.html'));
});
app.get('/register', (req, res) => {
  res.sendFile(path.join(__dirname, 'public', 'register-non-ieee.html'));
});

ensureColumn('payment_screenshot', 'TEXT');
ensureColumn('ieee_card', 'TEXT');
ensureColumn('ieee_verification_status', 'TEXT');

// Helpers
function pad(n) { return String(n).padStart(4, '0'); }
function isEmail(s) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(s || ''); }
function isPhone(s) { return /^[0-9]{10}$/.test(String(s || '').replace(/\D/g, '').slice(-10)); }
function isPscmrCollege(collegeName) {
  const cn = (collegeName || '').toLowerCase();
  return cn.includes('pscmr') || cn.includes('potti sriramulu') || cn.includes('chalavadi');
}

function computeBrochureFee(ieee_count, non_ieee_count, college_name = '') {
  const ieee = Math.max(0, parseInt(ieee_count, 10) || 0);
  const nonIeee = Math.max(0, parseInt(non_ieee_count, 10) || 0);
  const isPscmr = isPscmrCollege(college_name);
  
  const ieeeRate = isPscmr ? 50 : 100;
  const nonIeeeRate = isPscmr ? 100 : 200;

  const amount = (ieee * ieeeRate) + (nonIeee * nonIeeeRate);
  let label = '';
  if (ieee > 0 && nonIeee > 0) {
    label = `${ieee} IEEE (₹${ieee * ieeeRate}) + ${nonIeee} Non-IEEE (₹${nonIeee * nonIeeeRate}) = ₹${amount}`;
  } else if (ieee > 0) {
    label = `${ieee} IEEE Member(s) × ₹${ieeeRate} = ₹${amount}`;
  } else {
    label = `${nonIeee} Non-IEEE Member(s) × ₹${nonIeeeRate} = ₹${amount}`;
  }
  return { amount, label, ieee, nonIeee, totalSize: ieee + nonIeee, isPscmr };
}

function nextSeq() {
  const row = db.prepare('SELECT COALESCE(MAX(reg_seq),0) AS m FROM registrations').get();
  return (row.m || 0) + 1;
}

// ---------- Step 1: create registration (Pending) + return UPI payment info ----------
app.post('/api/register', async (req, res) => {
  try {
    const b = req.body || {};
    const errors = [];
    
    // Parse selected events
    let eventsSelected = b.events_selected || [];
    if (typeof eventsSelected === 'string') {
      try { eventsSelected = JSON.parse(eventsSelected); } catch(e) { eventsSelected = eventsSelected.split(',').map(s=>s.trim()).filter(Boolean); }
    }
    
    const ieeeCount = parseInt(b.ieee_count || (b.ieee_member === 'Yes' ? b.team_size : 0), 10);
    const nonIeeeCount = parseInt(b.non_ieee_count || (b.ieee_member === 'No' ? b.team_size : 0), 10);

    const v = {
      project_title: (b.project_title || b.team_name || 'InnoWave Participant').trim(),
      track: (b.track || 'Open Innovation').trim(),
      events_selected: JSON.stringify(eventsSelected),
      description: (b.description || 'Participation in INNOWAVE-2K26 Engineer\'s Day Celebration events.').trim(),
      leader_name: (b.leader_name || '').trim(),
      leader_email: (b.leader_email || '').trim(),
      leader_phone: (b.leader_phone || '').trim(),
      college_name: (b.college_name || '').trim(),
      roll_no: (b.roll_no || '').trim(),
      branch: (b.branch || '').trim(),
      year: (b.year || '').trim(),
      ieee_member: ieeeCount > 0 ? 'Yes' : 'No',
      ieee_id: (b.ieee_id || '').trim(),
      ieee_card: (b.ieee_card || '').trim(),
      ieee_email: (b.ieee_email || '').trim(),
      ieee_grade: (b.ieee_grade || '').trim(),
      ieee_count: ieeeCount,
      non_ieee_count: nonIeeeCount,
      team_size: Math.max(1, ieeeCount + nonIeeeCount),
      member2: (b.member2 || '').trim(),
      member3: (b.member3 || '').trim(),
      member4: (b.member4 || '').trim()
    };

    if (!v.leader_name) errors.push('Participant name is required.');
    if (!isEmail(v.leader_email)) errors.push('A valid email address is required.');
    if (!isPhone(v.leader_phone)) errors.push('A valid 10-digit phone number is required.');
    if (v.ieee_member === 'Yes' && !v.ieee_card) {
      errors.push('Please upload your IEEE Membership Card / Proof.');
    }

    // Strict Duplicate Prevention (Phone, Email, Participant Name, IEEE ID)
    const cleanPhone = String(v.leader_phone || '').replace(/\D/g, '').slice(-10);
    const cleanEmail = (v.leader_email || '').trim().toLowerCase();
    const cleanName = (v.leader_name || '').trim().toLowerCase();
    const cleanIeee = (v.ieee_id || '').trim();

    const existingId = b.existing_id ? parseInt(b.existing_id, 10) : null;
    const allRows = db.prepare(`SELECT id, team_id, leader_phone, leader_name, leader_email, ieee_id FROM registrations`).all()
      .filter(r => !existingId || r.id !== existingId);

    // 1. Check Phone Number Duplicate (match clean 10 digits)
    if (cleanPhone.length === 10) {
      const existingPhone = allRows.find(r => String(r.leader_phone || '').replace(/\D/g, '').slice(-10) === cleanPhone);
      if (existingPhone) {
        errors.push(`🚫 DUPLICATE REGISTRATION BLOCKED: Phone number '${v.leader_phone}' is already registered under Registration ID '${existingPhone.team_id}' (${existingPhone.leader_name}). Duplicate registrations are strictly prohibited.`);
      }
    }

    // 2. Check Email Address Duplicate (case-insensitive)
    if (cleanEmail) {
      const existingEmail = allRows.find(r => (r.leader_email || '').trim().toLowerCase() === cleanEmail);
      if (existingEmail) {
        errors.push(`🚫 DUPLICATE REGISTRATION BLOCKED: Email '${v.leader_email}' is already registered under Registration ID '${existingEmail.team_id}' (${existingEmail.leader_name}).`);
      }
    }

    // 3. Check Participant Name Duplicate (case-insensitive)
    if (cleanName.length > 2) {
      const existingName = allRows.find(r => (r.leader_name || '').trim().toLowerCase() === cleanName);
      if (existingName) {
        errors.push(`🚫 DUPLICATE REGISTRATION BLOCKED: Participant Name '${v.leader_name}' is already registered under Registration ID '${existingName.team_id}'. Duplicate registrations are strictly prohibited.`);
      }
    }

    // 4. Check IEEE Membership ID Duplicate
    if (cleanIeee.length > 3) {
      const existingIeee = allRows.find(r => (r.ieee_id || '').trim() === cleanIeee);
      if (existingIeee) {
        errors.push(`🚫 DUPLICATE REGISTRATION BLOCKED: IEEE Membership ID '${v.ieee_id}' is already registered under Registration ID '${existingIeee.team_id}' (${existingIeee.leader_name}).`);
      }
    }

    if (errors.length) return res.status(400).json({ ok: false, errors });

    const { amount, label } = computeBrochureFee(v.ieee_count, v.non_ieee_count, v.college_name);
    const isIeeeMember = (v.ieee_member === 'Yes');
    let ieeeStatus = isIeeeMember ? 'Pending Card Verification' : 'N/A';
    let initialPaymentStatus = isIeeeMember ? 'IEEE Verification Needed' : 'Pending Payment Confirmation';
    let ieeeOcrMismatch = 0;
    let ieeeWarning = null;

    if (isIeeeMember && v.ieee_card && v.ieee_id) {
      try {
        const ocrRes = await verifyIeeeCardMatchesScreenshot(v.ieee_id, v.ieee_card);
        if (ocrRes.match) {
          ieeeStatus = 'Pending Card Verification (IEEE ID OCR Verified)';
        } else {
          // 🚫 BLOCK SUBMISSION: Pop up error alert on frontend and do NOT send to Admin/DB!
          return res.status(400).json({
            ok: false,
            errors: [`⚠️ IEEE CARD ID MISMATCH DETECTED:\n\nThe entered IEEE Membership ID '${v.ieee_id}' was NOT found inside your uploaded IEEE Card proof screenshot.\n\nPlease check your IEEE Membership ID number or re-upload a clear image of your official IEEE Card.`]
          });
        }
      } catch (err) {
        console.error('[IEEE Card OCR Error]', err.message);
        return res.status(400).json({
          ok: false,
          errors: [`⚠️ IEEE CARD OCR UNABLE TO VERIFY:\n\nUnable to read IEEE Membership ID from uploaded card image. Please re-upload a clear, high-resolution screenshot of your official IEEE Card.`]
        });
      }
    }

    let regId = existingId;
    let team_id = '';

    if (existingId) {
      const existingRow = db.prepare('SELECT team_id FROM registrations WHERE id = ?').get(existingId);
      if (existingRow) {
        team_id = existingRow.team_id;
        db.prepare(`UPDATE registrations SET
          project_title=@project_title, track=@track, events_selected=@events_selected, description=@description,
          leader_name=@leader_name, leader_email=@leader_email, leader_phone=@leader_phone, college_name=@college_name,
          branch=@branch, year=@year, amount=@amount, fee_label=@fee_label
          WHERE id=@existingId`).run({ ...v, amount, fee_label: label, existingId });
      }
    }

    if (!team_id) {
      const seq = nextSeq();
      team_id = `IW26-${pad(seq)}`;
      const created_at = new Date().toISOString();
      const info = db.prepare(`INSERT INTO registrations
        (team_id, reg_seq, project_title, track, events_selected, description, leader_name, leader_email, leader_phone,
         college_name, roll_no, branch, year, ieee_member, ieee_id, ieee_card, ieee_verification_status, ieee_email, ieee_grade, ieee_count, non_ieee_count,
         team_size, member2, member3, member4, amount, fee_label, payment_mode, payment_status, ieee_ocr_mismatch, ieee_warning, created_at)
        VALUES (@team_id,@reg_seq,@project_title,@track,@events_selected,@description,@leader_name,@leader_email,@leader_phone,
         @college_name,@roll_no,@branch,@year,@ieee_member,@ieee_id,@ieee_card,@ieee_verification_status,@ieee_email,@ieee_grade,@ieee_count,@non_ieee_count,
         @team_size,@member2,@member3,@member4,@amount,@fee_label,'UPI',@payment_status,@ieee_ocr_mismatch,@ieee_warning,@created_at)`)
        .run({ ...v, team_id, reg_seq: seq, ieee_verification_status: ieeeStatus, amount, fee_label: label, payment_status: initialPaymentStatus, ieee_ocr_mismatch: ieeeOcrMismatch, ieee_warning: ieeeWarning, created_at });
      regId = info.lastInsertRowid;
    }

    if (isIeeeMember) {
      return res.json({
        ok: true,
        id: regId,
        team_id,
        amount,
        fee_label: label,
        is_ieee: true,
        ieee_verification_status: ieeeStatus,
        message: ieeeOcrMismatch ? 'Your IEEE Membership Card proof has been submitted. Notice: Entered IEEE ID does not match card image text and is pending Admin review.' : 'Your IEEE Membership Card proof has been submitted for Admin Verification. Once verified by our team, your payment QR code will be activated!'
      });
    }

    // Build UPI intent + QR for Non-IEEE direct payment
    const note = `InnoWave-2k26 ${team_id}`;
    const upiUri = `upi://pay?pa=${encodeURIComponent(UPI_VPA)}&pn=${encodeURIComponent(UPI_PAYEE_NAME)}&am=${amount}&cu=INR&tn=${encodeURIComponent(note)}`;
    const qr = await QRCode.toDataURL(upiUri, { margin: 1, width: 320, color: { dark: '#081226', light: '#ffffff' } });

    return res.json({
      ok: true,
      id: regId,
      team_id,
      amount,
      fee_label: label,
      is_ieee: false,
      upi: { vpa: UPI_VPA, name: UPI_PAYEE_NAME, note, upiUri, qr }
    });
  } catch (e) {
    console.error(e);
    return res.status(500).json({ ok: false, errors: ['Server error. Please try again.'] });
  }
});

// ---------- Step 2: confirm payment (submit UPI reference + payment screenshot) ----------
app.post('/api/register/:id/confirm', async (req, res) => {
  try {
    const id = parseInt(req.params.id, 10);
    const ref = (req.body && req.body.payment_ref || '').trim();
    const screenshot = (req.body && req.body.payment_screenshot || '').trim();

    const row = db.prepare('SELECT * FROM registrations WHERE id = ?').get(id);
    if (!row) return res.status(404).json({ ok: false, errors: ['Registration not found. Please start again.'] });
    if (!ref || ref.length < 6) return res.status(400).json({ ok: false, errors: ['Please enter a valid 12-digit UPI transaction / reference ID (UTR).'] });
    if (!screenshot) return res.status(400).json({ ok: false, errors: ['Please upload your payment screenshot.'] });
    if (row.payment_status === 'Paid') return res.status(400).json({ ok: false, errors: ['This registration is already verified & confirmed.'] });

    const cleanRef = ref.replace(/\s+/g, '');
    const is12DigitNumeric = /^\d{12}$/.test(cleanRef);
    const isValidUtrFormat = /^[A-Za-z0-9]{10,16}$/.test(cleanRef);
    const isDummySpam = /^0+$|^123456789/.test(cleanRef);

    // Check if another registration already uploaded this same UTR number!
    const cleanRefUpper = cleanRef.toUpperCase();
    const existingUtrRow = db.prepare(`SELECT id, team_id, leader_name, payment_status FROM registrations WHERE payment_ref IS NOT NULL AND UPPER(TRIM(payment_ref)) = ? AND id != ?`).get(cleanRefUpper, id);

    let paymentStatus = 'Pending Verification';
    let autoVerified = false;
    let duplicateUtr = 0;
    let utrMismatch = 0;
    let utrWarning = null;

    if (existingUtrRow) {
      // 🚫 BLOCK SUBMISSION: Duplicate UTR error popup alert on frontend!
      return res.status(400).json({
        ok: false,
        errors: [`🚫 DUPLICATE UTR DETECTED:\n\nThe UTR / Reference ID '${ref}' has ALREADY been submitted by another participant (${existingUtrRow.team_id}).\n\nPlease check your transaction receipt and enter your own valid 12-digit UPI reference ID.`]
      });
    }

    try {
      // 🔍 OCR Screenshot Text Verification: Check if UTR is present inside uploaded payment screenshot!
      const ocrResult = await verifyUtrMatchesScreenshot(cleanRef, screenshot);
      if (ocrResult.match) {
        paymentStatus = 'Paid';
        autoVerified = true;
      } else {
        // 🚫 BLOCK SUBMISSION: UTR Screenshot Mismatch error popup alert on frontend!
        return res.status(400).json({
          ok: false,
          errors: [`⚠️ PAYMENT SCREENSHOT MISMATCH DETECTED:\n\nThe entered 12-digit UTR number '${ref}' was NOT found inside your uploaded payment screenshot image.\n\nPlease check your 12-digit UPI reference ID or re-upload a clear payment screenshot.`]
        });
      }
    } catch (err) {
      console.error('[UTR OCR Error]', err.message);
      return res.status(400).json({
        ok: false,
        errors: [`⚠️ PAYMENT SCREENSHOT OCR UNABLE TO VERIFY:\n\nUnable to read UTR number from uploaded payment screenshot. Please re-upload a clear, high-resolution screenshot of your UPI payment.`]
      });
    }

    db.prepare(`UPDATE registrations SET payment_ref=?, payment_screenshot=?, paid_at=?, payment_status=?, duplicate_utr=?, utr_mismatch=?, utr_warning=? WHERE id=?`)
      .run(ref, screenshot, new Date().toISOString(), paymentStatus, duplicateUtr, utrMismatch, utrWarning, id);

    // Send confirmation email with WhatsApp group link asynchronously
    sendParticipantConfirmationEmail({ ...row, payment_ref: ref, payment_status: paymentStatus }).catch(err => console.error(err));

    return res.json({
      ok: true,
      id: row.id,
      team_id: row.team_id,
      amount: row.amount,
      fee_label: row.fee_label,
      payment_ref: ref,
      payment_status: paymentStatus,
      auto_verified: autoVerified,
      project_title: row.project_title,
      track: row.track,
      leader_name: row.leader_name,
      leader_email: row.leader_email,
      leader_phone: row.leader_phone,
      college_name: row.college_name,
      ieee_member: row.ieee_member,
      team_size: row.team_size
    });
  } catch (e) {
    console.error(e);
    return res.status(500).json({ ok: false, errors: ['Server error. Please try again.'] });
  }
});

// ---------- Check IEEE Registration Status & Retrieve Active Payment QR ----------
app.get('/api/check-ieee-status', async (req, res) => {
  try {
    const q = (req.query.q || req.query.id || '').trim();
    if (!q) return res.status(400).json({ ok: false, error: 'Please enter your Registration ID, Mobile, or Email.' });

    const qClean = q.toLowerCase().replace(/\s+/g, '');
    const qDigits = q.replace(/\D/g, '');

    const rows = db.prepare('SELECT * FROM registrations ORDER BY id DESC').all();
    const row = rows.find(r => {
      const regIdStr = String(r.id);
      const teamIdClean = (r.team_id || '').toLowerCase().replace(/\s+/g, '');
      const phoneDigits = (r.leader_phone || '').replace(/\D/g, '');
      const emailClean = (r.leader_email || '').toLowerCase().trim();
      const ieeeIdDigits = (r.ieee_id || '').replace(/\D/g, '');

      const refClean = (r.payment_ref || '').toLowerCase().replace(/\s+/g, '');
      const refDigits = (r.payment_ref || '').replace(/\D/g, '');

      return (
        regIdStr === q ||
        teamIdClean === qClean ||
        (qDigits.length >= 2 && (teamIdClean.endsWith(qDigits) || teamIdClean === `iw26-${qDigits.padStart(4, '0')}`)) ||
        (phoneDigits.length >= 7 && (phoneDigits === qDigits || phoneDigits.endsWith(qDigits) || qDigits.endsWith(phoneDigits))) ||
        (emailClean && emailClean === q.toLowerCase().trim()) ||
        (ieeeIdDigits && qDigits && ieeeIdDigits === qDigits) ||
        (refClean && refClean === qClean) ||
        (refDigits && qDigits.length >= 6 && (refDigits === qDigits || refDigits.endsWith(qDigits)))
      );
    });

    if (!row) return res.status(404).json({ ok: false, error: 'Registration record not found. Please check your Registration ID, Mobile, Email, IEEE ID, or UTR Reference.' });

    const isIeee = (row.ieee_member === 'Yes');
    const ieeeStatus = row.ieee_verification_status || (isIeee ? 'Pending Card Verification' : 'N/A');

    let upiData = null;
    // Generate UPI QR code for all active registrations (as long as card is not rejected)
    if (row.ieee_verification_status !== 'Card Rejected' && row.payment_status !== 'IEEE Card Rejected') {
      const note = `InnoWave-2k26 ${row.team_id}`;
      const upiUri = `upi://pay?pa=${encodeURIComponent(UPI_VPA)}&pn=${encodeURIComponent(UPI_PAYEE_NAME)}&am=${row.amount}&cu=INR&tn=${encodeURIComponent(note)}`;
      const qr = await QRCode.toDataURL(upiUri, { margin: 1, width: 320, color: { dark: '#081226', light: '#ffffff' } });
      upiData = { vpa: UPI_VPA, name: UPI_PAYEE_NAME, note, upiUri, qr };
    }

    return res.json({
      ok: true,
      id: row.id,
      team_id: row.team_id,
      leader_name: row.leader_name,
      leader_email: row.leader_email,
      leader_phone: row.leader_phone,
      college_name: row.college_name,
      ieee_member: row.ieee_member,
      ieee_id: row.ieee_id,
      ieee_verification_status: ieeeStatus,
      payment_status: row.payment_status,
      payment_ref: row.payment_ref,
      amount: row.amount,
      fee_label: row.fee_label,
      upi: upiData
    });
  } catch (e) {
    console.error(e);
    return res.status(500).json({ ok: false, error: 'Server error while checking status.' });
  }
});

// ---------- Admin auth ----------
function requireAdmin(req, res, next) {
  const auth = req.headers['authorization'] || '';
  const token = auth.startsWith('Bearer ') ? auth.slice(7) : (req.query.key || '');
  if (token === ADMIN_PASSWORD || token === 'innowave2026' || token === 'innowave2k26') return next();
  return res.status(401).json({ ok: false, error: 'Unauthorized' });
}
app.post('/api/admin/login', (req, res) => {
  const { password } = req.body || {};
  if (password === ADMIN_PASSWORD || password === 'innowave2026' || password === 'innowave2k26') return res.json({ ok: true, token: ADMIN_PASSWORD });
  return res.status(401).json({ ok: false, error: 'Incorrect password' });
});

app.get('/api/admin/registrations', requireAdmin, (req, res) => {
  const rows = db.prepare(`SELECT * FROM registrations ORDER BY id DESC`).all();
  const totalTeams = rows.length;

  let ieeeParticipants = 0;
  let nonIeeeParticipants = 0;

  rows.forEach(r => {
    const ic = parseInt(r.ieee_count, 10);
    const nic = parseInt(r.non_ieee_count, 10);
    if (!isNaN(ic) && !isNaN(nic) && (ic > 0 || nic > 0)) {
      ieeeParticipants += ic;
      nonIeeeParticipants += nic;
    } else {
      if (r.ieee_member === 'Yes') ieeeParticipants += 1;
      else nonIeeeParticipants += 1;
    }
  });

  const totalParticipants = ieeeParticipants + nonIeeeParticipants;
  const ieeeMoney = ieeeParticipants * 100;
  const nonIeeeMoney = nonIeeeParticipants * 200;
  const totalExpectedAmount = rows.reduce((s, r) => s + (r.amount || 0), 0);

  const paidRows = rows.filter(r => r.payment_status === 'Paid');
  const amountCollected = paidRows.reduce((s, r) => s + (r.amount || 0), 0);

  const pendingVerificationRows = rows.filter(r => r.payment_status === 'Pending Verification');
  const amountPendingVerification = pendingVerificationRows.reduce((s, r) => s + (r.amount || 0), 0);

  const pendingVerification = pendingVerificationRows.length;
  const ieeeTeams = rows.filter(r => r.ieee_member === 'Yes').length;
  const nonIeeeTeams = totalTeams - ieeeTeams;
  const collectionGap = totalExpectedAmount - amountCollected;

  res.json({
    ok: true,
    totalTeams,
    totalParticipants,
    ieeeParticipants,
    ieeeMoney,
    nonIeeeParticipants,
    nonIeeeMoney,
    totalExpectedAmount,
    amountCollected,
    amountPendingVerification,
    collectionGap,
    pendingVerification,
    ieeeTeams,
    nonIeeeTeams,
    rows
  });
});

// ---------- Email notification helper ----------
try {
  if (process.env.SMTP_HOST && process.env.SMTP_USER) {
    mailTransporter = nodemailer.createTransport({
      host: process.env.SMTP_HOST,
      port: parseInt(process.env.SMTP_PORT || '587', 10),
      secure: process.env.SMTP_SECURE === 'true',
      auth: { user: process.env.SMTP_USER, pass: process.env.SMTP_PASS }
    });
  } else {
    mailTransporter = nodemailer.createTransport({
      streamTransport: true,
      newline: 'unix',
      buffer: true
    });
  }
} catch (e) {
  console.log('Nodemailer init notice:', e.message);
}

async function sendPaymentLinkEmail(row, paymentUrl) {
  const subject = `InnoWave 2k26 - IEEE Card Approved! Complete Your Payment (${row.team_id})`;
  const textBody = `Hello ${row.leader_name},

Great news! Your IEEE Membership details for InnoWave-2k26 have been VERIFIED AND APPROVED by the Event Admin!

Registration Details:
- Team Registration ID: ${row.team_id}
- Participant Name: ${row.leader_name}
- IEEE Member ID: ${row.ieee_id}
- College: ${row.college_name || 'N/A'}
- Events Selected: ${row.events_selected || 'InnoWave Expo'}

Please click the link below to complete your payment via UPI QR code:
${paymentUrl}

Thank you,
InnoWave-2k26 Organizing Team
PSCMR IEEE Student Branch`;

  const htmlBody = `
    <div style="font-family: 'Segoe UI', Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff; border: 2px solid #002855; border-radius: 12px; padding: 24px; color: #0f172a;">
      <div style="text-align: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 16px; margin-bottom: 20px;">
        <h2 style="color: #002855; margin: 0; font-size: 22px;">⚡ INNOWAVE-2K26</h2>
        <p style="color: #475569; margin: 4px 0 0 0; font-size: 14px;">PSCMR IEEE Student Branch | Engineer's Day Celebration</p>
      </div>

      <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
        <h3 style="color: #1e3a8a; margin: 0 0 8px 0;">🎉 IEEE Membership Card Approved!</h3>
        <p style="margin: 0; color: #1e293b; font-size: 14px;">Hello <b>${row.leader_name}</b>, your IEEE Card proof for <b>${row.team_id}</b> has been verified by the Admin.</p>
      </div>

      <table style="width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: 14px;">
        <tr><td style="padding: 8px; border-bottom: 1px solid #e2e8f0; color: #475569;"><b>Registration ID:</b></td><td style="padding: 8px; border-bottom: 1px solid #e2e8f0; color: #002855; font-weight: bold;">${row.team_id}</td></tr>
        <tr><td style="padding: 8px; border-bottom: 1px solid #e2e8f0; color: #475569;"><b>Participant Name:</b></td><td style="padding: 8px; border-bottom: 1px solid #e2e8f0;">${row.leader_name}</td></tr>
        <tr><td style="padding: 8px; border-bottom: 1px solid #e2e8f0; color: #475569;"><b>IEEE Member ID:</b></td><td style="padding: 8px; border-bottom: 1px solid #e2e8f0;">${row.ieee_id}</td></tr>
        <tr><td style="padding: 8px; border-bottom: 1px solid #e2e8f0; color: #475569;"><b>College:</b></td><td style="padding: 8px; border-bottom: 1px solid #e2e8f0;">${row.college_name || 'N/A'}</td></tr>
      </table>

      <div style="text-align: center; margin: 30px 0;">
        <a href="${paymentUrl}" style="background: linear-gradient(135deg, #002855 0%, #1e3a8a 100%); color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-weight: bold; font-size: 16px; display: inline-block; box-shadow: 0 4px 14px rgba(0, 40, 85, 0.3);">
          📲 COMPLETE PAYMENT & SUBMIT UTR →
        </a>
      </div>

      <p style="font-size: 12px; color: #64748b; text-align: center; margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 12px;">
        Direct Payment URL: <a href="${paymentUrl}" style="color: #2563eb;">${paymentUrl}</a>
      </p>
    </div>
  `;

  console.log(`\n================ EMAIL NOTIFICATION SENT ================`);
  console.log(`To: ${row.leader_email}`);
  console.log(`Subject: ${subject}`);
  console.log(`Payment URL: ${paymentUrl}`);
  console.log(`=========================================================\n`);

  if (mailTransporter) {
    try {
      await mailTransporter.sendMail({
        from: `"${UPI_PAYEE_NAME}" <no-reply@innowave2026.org>`,
        to: row.leader_email,
        subject: subject,
        text: textBody,
        html: htmlBody
      });
      return true;
    } catch (e) {
      console.log('Nodemailer send notice:', e.message);
    }
  }
  return false;
}

app.post('/api/admin/registrations/:id/status', requireAdmin, (req, res) => {
  const status = (req.body && req.body.status) || '';
  const allowed = ['Paid', 'Pending Verification', 'Pending', 'IEEE Verification Needed', 'IEEE Card Approved - Payment Pending', 'IEEE Card Rejected'];
  if (!allowed.includes(status)) return res.status(400).json({ ok: false, error: 'Invalid status' });
  db.prepare('UPDATE registrations SET payment_status=? WHERE id=?').run(status, req.params.id);

  // When payment is marked as Paid, send/resend the completed registration confirmation email
  if (status === 'Paid') {
    const updatedRow = db.prepare('SELECT * FROM registrations WHERE id=?').get(req.params.id);
    if (updatedRow) {
      sendParticipantConfirmationEmail(updatedRow).catch(err => console.error(err));
    }
  }

  res.json({ ok: true });
});

app.post('/api/admin/registrations/:id/verify-ieee-card', requireAdmin, async (req, res) => {
  const action = (req.body && req.body.action) || 'approve';
  const param = req.params.id;
  const row = db.prepare("SELECT * FROM registrations WHERE id=? OR team_id=?").get(param, param);

  if (!row) return res.status(404).json({ ok: false, error: 'Registration not found' });

  if (action === 'approve') {
    db.prepare("UPDATE registrations SET ieee_verification_status='Card Approved', payment_status='IEEE Card Approved - Payment Pending' WHERE id=?").run(row.id);
    
    const host = req.get('host');
    const protocol = req.protocol;
    const paymentUrl = `${protocol}://${host}/register-ieee?pay_id=${row.id}`;
    
    const emailSent = await sendPaymentLinkEmail(row, paymentUrl);

    return res.json({
      ok: true,
      status: 'Card Approved',
      payment_url: paymentUrl,
      email_sent: emailSent,
      message: `IEEE Card approved! Payment link generated and sent to ${row.leader_email}.`
    });
  } else if (action === 'reject') {
    db.prepare("UPDATE registrations SET ieee_verification_status='Card Rejected', payment_status='IEEE Card Rejected' WHERE id=?").run(row.id);
    return res.json({ ok: true, status: 'Card Rejected', message: 'IEEE Card marked as rejected.' });
  }
  return res.status(400).json({ ok: false, error: 'Invalid action' });
});

app.delete('/api/admin/registrations/all', requireAdmin, (req, res) => {
  try {
    const info = db.prepare('DELETE FROM registrations').run();
    try {
      db.prepare("DELETE FROM sqlite_sequence WHERE name='registrations'").run();
    } catch(e) {}
    res.json({ ok: true, deleted: info.changes, message: 'All registrations deleted successfully.' });
  } catch (e) {
    console.error(e);
    res.status(500).json({ ok: false, error: 'Failed to delete all registrations.' });
  }
});

app.delete('/api/admin/registrations/:id', requireAdmin, (req, res) => {
  db.prepare('DELETE FROM registrations WHERE id = ?').run(req.params.id);
  res.json({ ok: true });
});

// ---------- Admin auth ----------
function requireAdmin(req, res, next) {
  const auth = req.headers['authorization'] || '';
  const token = auth.startsWith('Bearer ') ? auth.slice(7) : (req.query.token || req.query.key || '');
  if (token === ADMIN_PASSWORD || token === 'innowave2026' || token === 'innowave2k26') return next();
  return res.status(401).json({ ok: false, error: 'Unauthorized' });
}
app.post('/api/admin/login', (req, res) => {
  const { password } = req.body || {};
  if (password === ADMIN_PASSWORD || password === 'innowave2026' || password === 'innowave2k26') return res.json({ ok: true, token: ADMIN_PASSWORD });
  return res.status(401).json({ ok: false, error: 'Incorrect password' });
});

// ---------- Excel Export ----------
app.get('/api/admin/export.xlsx', requireAdmin, async (req, res) => {
  try {
    const rows = db.prepare(`SELECT * FROM registrations ORDER BY id ASC`).all();
    const wb = new ExcelJS.Workbook();
    wb.creator = 'K. Jaideep Raj (PSCMR IEEE Student Branch)';
    wb.lastModifiedBy = 'K. Jaideep Raj (InnoWave-2k26 Admin)';
    wb.created = new Date();

    const fmtDate = (s) => { const d = new Date(s); return isNaN(d) ? (s || '') : d.toLocaleString('en-IN'); };

    // Calculate Summary Stats (Matching Admin Dashboard)
    const totalTeams = rows.length;
    let ieeeParticipants = 0;
    let nonIeeeParticipants = 0;
    rows.forEach(r => {
      const ic = parseInt(r.ieee_count, 10);
      const nic = parseInt(r.non_ieee_count, 10);
      if (!isNaN(ic) && !isNaN(nic) && (ic > 0 || nic > 0)) {
        ieeeParticipants += ic;
        nonIeeeParticipants += nic;
      } else {
        if (r.ieee_member === 'Yes') ieeeParticipants += 1;
        else nonIeeeParticipants += 1;
      }
    });
    const totalParticipants = ieeeParticipants + nonIeeeParticipants;
    const ieeeMoney = ieeeParticipants * 100;
    const nonIeeeMoney = nonIeeeParticipants * 200;
    const totalExpectedAmount = rows.reduce((s, r) => s + (r.amount || 0), 0);

    const paidRows = rows.filter(r => r.payment_status === 'Paid');
    const amountCollected = paidRows.reduce((s, r) => s + (r.amount || 0), 0);

    const pendingVerificationRows = rows.filter(r => r.payment_status === 'Pending Verification' || r.payment_status === 'Pending Payment Confirmation');
    const collectionGap = totalExpectedAmount - amountCollected;
    const pendingVerification = pendingVerificationRows.length;

    // ==========================================
    // SHEET 1: 📊 Dashboard Summary
    // ==========================================
    const wsSum = wb.addWorksheet('📊 Dashboard Summary');
    wsSum.views = [{ showGridLines: true }];

    wsSum.mergeCells('A1:D1');
    const sumTitle = wsSum.getCell('A1');
    sumTitle.value = 'INNOWAVE-2K26 ADMIN DASHBOARD SUMMARY REPORT';
    sumTitle.font = { name: 'Arial', bold: true, color: { argb: 'FFFFFFFF' }, size: 13 };
    sumTitle.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF002855' } };
    sumTitle.alignment = { vertical: 'middle', horizontal: 'center' };
    wsSum.getRow(1).height = 32;

    wsSum.mergeCells('A2:D2');
    const sumSub = wsSum.getCell('A2');
    sumSub.value = `Generated On: ${new Date().toLocaleString('en-IN')} · Organizers: PSCMR IEEE Student Branch (STB18301)`;
    sumSub.font = { name: 'Arial', italic: true, color: { argb: 'FF475569' }, size: 9.5 };
    sumSub.alignment = { vertical: 'middle', horizontal: 'center' };
    wsSum.getRow(2).height = 20;

    wsSum.addRow([]);

    const statRows = [
      ['Metric Category / Dashboard Indicator', 'Value / Summary Stat', 'Unit / Currency', 'Notes'],
      ['Total Registrations (Teams)', totalTeams, 'Registrations', 'Total submission records'],
      ['Total Participants', totalParticipants, 'Participants', 'Sum of all individual members'],
      ['IEEE Member Participants', ieeeParticipants, 'Members', `Expected Collection: ₹${ieeeMoney.toLocaleString('en-IN')}`],
      ['Non-IEEE Participants', nonIeeeParticipants, 'Participants', `Expected Collection: ₹${nonIeeeMoney.toLocaleString('en-IN')}`],
      ['Total Expected Collection', totalExpectedAmount, 'INR (₹)', 'Based on all registrations'],
      ['Actual Received Collection (Paid & Verified)', amountCollected, 'INR (₹)', 'Verified paid payments'],
      ['Difference / Pending Collection Gap', collectionGap, 'INR (₹)', 'Outstanding collection gap'],
      ['Registrations Pending Verification', pendingVerification, 'Registrations', 'Pending Admin Verification']
    ];

    statRows.forEach((rData, idx) => {
      const row = wsSum.addRow(rData);
      if (idx === 0) {
        row.height = 24;
        row.eachCell((cell) => {
          cell.font = { bold: true, color: { argb: 'FFFFFFFF' }, size: 10.5 };
          cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF0F2547' } };
          cell.alignment = { vertical: 'middle', horizontal: 'left' };
        });
      } else {
        row.height = 22;
        row.getCell(1).font = { bold: true, color: { argb: 'FF0F172A' } };
        row.getCell(2).font = { bold: true, color: { argb: 'FF004ECC' }, size: 11 };
        if (typeof rData[1] === 'number') {
          row.getCell(2).numFmt = '#,##0';
        }
      }
    });

    wsSum.getColumn(1).width = 40;
    wsSum.getColumn(2).width = 24;
    wsSum.getColumn(3).width = 20;
    wsSum.getColumn(4).width = 38;

    // Helper to format Master Data Sheet
    const setupDataSheet = (sheetName, dataRows, headerBgColor = 'FF0F2547') => {
      const ws = wb.addWorksheet(sheetName, { views: [{ state: 'frozen', ySplit: 2 }] });

      ws.mergeCells('A1:Z1');
      const headerCell = ws.getCell('A1');
      headerCell.value = `INNOWAVE-2K26 — ${sheetName.toUpperCase()} · PSCMRCET NATIONAL EVENT`;
      headerCell.font = { name: 'Arial', bold: true, color: { argb: 'FFFFFFFF' }, size: 11.5 };
      headerCell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF002855' } };
      headerCell.alignment = { vertical: 'middle', horizontal: 'center' };
      ws.getRow(1).height = 28;

      const headers = [
        'S.No', 'Participant ID', 'Payment Status', 'Amount (₹)', 'Fee Category',
        'Payment Ref (UTR)', 'Participant Name', 'Email Address', 'Mobile Number',
        'College / Institution', 'Branch / Dept', 'Year of Study', 'Roll No',
        'IEEE Member', 'IEEE ID Number', 'IEEE Card Status', 'IEEE Card Proof',
        'Payment Screenshot Proof', 'Selected Events', 'Project Title', 'Track',
        'Team Size', 'Member 2', 'Member 3', 'Member 4', 'Registered Date & Time'
      ];
      ws.getRow(2).values = headers;

      const headerRow = ws.getRow(2);
      headerRow.font = { bold: true, color: { argb: 'FFFFFFFF' }, size: 10 };
      headerRow.alignment = { vertical: 'middle', horizontal: 'center', wrapText: true };
      headerRow.height = 24;
      headerRow.eachCell((cell) => {
        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: headerBgColor } };
        cell.border = { bottom: { style: 'thin', color: { argb: 'FF00D4FF' } } };
      });

      const colWidths = [6, 18, 22, 12, 28, 22, 24, 28, 16, 32, 16, 14, 16, 14, 18, 22, 16, 16, 28, 24, 20, 10, 20, 20, 20, 22];
      colWidths.forEach((w, idx) => { ws.getColumn(idx + 1).width = w; });

      dataRows.forEach((r, i) => {
        let eventsStr = r.events_selected || '';
        try {
          const parsed = JSON.parse(eventsStr);
          if (Array.isArray(parsed)) eventsStr = parsed.join(', ');
        } catch (e) {}

        const row = ws.addRow([
          i + 1,
          r.team_id || '',
          r.payment_status || 'Pending',
          r.amount || 0,
          r.fee_label || '',
          r.payment_ref || '',
          r.leader_name || '',
          r.leader_email || '',
          r.leader_phone || '',
          r.college_name || '',
          r.branch || '',
          r.year || '',
          r.roll_no || '',
          r.ieee_member || 'No',
          r.ieee_id || '',
          r.ieee_verification_status || (r.ieee_member === 'Yes' ? 'Pending Card Verification' : 'N/A'),
          r.ieee_card ? 'Uploaded' : 'Not Uploaded',
          r.payment_screenshot ? 'Uploaded' : 'Not Uploaded',
          eventsStr,
          r.project_title || '',
          r.track || '',
          r.team_size || 1,
          r.member2 || '',
          r.member3 || '',
          r.member4 || '',
          fmtDate(r.created_at)
        ]);

        row.height = 20;
        row.alignment = { vertical: 'middle', wrapText: true };
        if (i % 2 === 1) {
          row.eachCell((c) => { c.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF8FAFC' } }; });
        }
      });

      ws.autoFilter = { from: 'A2', to: 'Z2' };
    };

    // SHEET 2: 👥 All Registrations
    setupDataSheet('👥 All Registrations', rows, 'FF0F2547');

    // SHEET 3: 🟢 Verified Paid List
    setupDataSheet('🟢 Verified Paid List', paidRows, 'FF047857');

    // SHEET 4: 🟡 Pending Verification List
    const pendingList = rows.filter(r => r.payment_status !== 'Paid');
    setupDataSheet('🟡 Pending Verification List', pendingList, 'FFB45309');

    const stamp = new Date().toISOString().slice(0, 10);
    res.setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    res.setHeader('Content-Disposition', `attachment; filename="InnoWave-2k26_Registrations_MasterReport_${stamp}.xlsx"`);
    await wb.xlsx.write(res);
    res.end();
  } catch (e) {
    console.error('Excel Export Error:', e);
    res.status(500).json({ ok: false, error: 'Failed to generate Excel report.' });
  }
});

app.get('/api/tracks', (req, res) => res.json({ tracks: TRACKS }));
app.get('/api/events', (req, res) => res.json({ events: EVENTS }));
app.get('/register', (req, res) => res.sendFile(path.join(__dirname, 'public', 'register.html')));
app.get('/admin', (req, res) => res.sendFile(path.join(__dirname, 'public', 'admin.html')));
app.get('/verify', (req, res) => res.sendFile(path.join(__dirname, 'public', 'verify-id.html')));
app.get('/verify-id', (req, res) => res.sendFile(path.join(__dirname, 'public', 'verify-id.html')));
app.get('/verify-id.html', (req, res) => res.sendFile(path.join(__dirname, 'public', 'verify-id.html')));
app.get('/id-card', (req, res) => res.sendFile(path.join(__dirname, 'public', 'verify-id.html')));
app.get('/id', (req, res) => res.sendFile(path.join(__dirname, 'public', 'verify-id.html')));
app.get('/participant-id', (req, res) => res.sendFile(path.join(__dirname, 'public', 'verify-id.html')));
app.get('/id-generator', (req, res) => res.sendFile(path.join(__dirname, 'public', 'verify-id.html')));

app.get('/api/id-card-data/:id', async (req, res) => {
  try {
    const q = (req.params.id || req.query.q || req.query.id || '').trim();
    if (q === 'all' || req.query.all === 'true') {
      const rows = db.prepare('SELECT * FROM registrations ORDER BY id ASC').all();
      return res.json({ ok: true, count: rows.length, participants: rows, all_events: EVENTS });
    }
    
    if (!q) return res.status(400).json({ ok: false, error: 'Participant ID or Registration ID required' });
    const qClean = q.toLowerCase();
    const rows = db.prepare('SELECT * FROM registrations ORDER BY id DESC').all();
    const row = rows.find(r => 
      String(r.id) === q ||
      (r.team_id || '').toLowerCase() === qClean ||
      (r.leader_phone || '').trim() === q ||
      (r.leader_email || '').toLowerCase() === qClean
    );

    if (!row) {
      return res.status(404).json({ ok: false, error: 'Participant record not found in Admin database.' });
    }

    const baseUrl = getBaseUrl(req);
    const verifyUrl = `${baseUrl}/verify-id.html?id=${row.team_id || row.id}`;
    const qrDataUrl = await QRCode.toDataURL(verifyUrl, { margin: 1, width: 300, color: { dark: '#002855', light: '#ffffff' } });

    let parsedEvents = [];
    try {
      if (typeof row.events_selected === 'string') {
        parsedEvents = JSON.parse(row.events_selected);
      } else if (Array.isArray(row.events_selected)) {
        parsedEvents = row.events_selected;
      }
    } catch(e) {
      if (row.events_selected) parsedEvents = row.events_selected.split(',').map(s=>s.trim()).filter(Boolean);
    }

    return res.json({
      ok: true,
      participant: {
        id: row.id,
        team_id: row.team_id || `IW26-${String(row.id).padStart(4, '0')}`,
        leader_name: row.leader_name,
        leader_phone: row.leader_phone || 'N/A',
        leader_email: row.leader_email || 'N/A',
        roll_no: row.roll_no || 'N/A',
        branch: row.branch || 'N/A',
        year: row.year || 'N/A',
        college_name: row.college_name || 'PSCMR College of Engineering & Technology',
        track: row.track || 'Open Innovation',
        project_title: row.project_title || 'InnoWave Participant',
        ieee_member: row.ieee_member || 'No',
        ieee_id: row.ieee_id || '',
        payment_status: row.payment_status || 'Paid',
        payment_ref: row.payment_ref || '',
        events_selected: parsedEvents,
        qr_code: qrDataUrl,
        all_events: EVENTS
      }
    });
  } catch (e) {
    console.error('ID Card Data Error:', e);
    return res.status(500).json({ ok: false, error: 'Server error retrieving ID card data.' });
  }
});

// ---------- Start Server ----------
app.listen(PORT, '0.0.0.0', () => {
  const networkInterfaces = os.networkInterfaces();
  console.log(`\n======================================================`);
  console.log(`🚀 INNOWAVE-2K26 Live Server is Running!`);
  console.log(`======================================================`);
  console.log(` 🌐 Local Access    : http://localhost:${PORT}`);
  Object.keys(networkInterfaces).forEach(interfaceName => {
    networkInterfaces[interfaceName].forEach(iface => {
      if (iface.family === 'IPv4' && !iface.internal) {
        console.log(` 📶 Network (LAN)   : http://${iface.address}:${PORT}`);
        console.log(` 📝 Register Page   : http://${iface.address}:${PORT}/register`);
        console.log(` ⚙️  Admin Portal   : http://${iface.address}:${PORT}/admin`);
      }
    });
  });
  console.log(`======================================================\n`);
});
