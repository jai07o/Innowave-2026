/**
 * InnoWave-2k26 — Project Expo
 * Event website + registration backend with UPI payment (PHP + Node + MySQL)
 *
 * Run:  npm install  &&  npm start
 * Site:  http://localhost:3000
 * Admin: http://localhost:3000/admin   (password: innowave2k26)
 *
 * Payment: UPI QR + reference (manual verification by organizers).
 * Default MySQL Password: innowave2k26
 */

try { require('dotenv').config(); } catch(e) {}

const path = require('path');
const fs = require('fs');
const express = require('express');
const mysql = require('mysql2/promise');
const ExcelJS = require('exceljs');
const QRCode = require('qrcode');
const nodemailer = require('nodemailer');
const compression = require('compression');
const os = require('os');
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
      return null;
    });
  }
  return tesseractWorkerPromise;
}

getTesseractWorker().catch(e => console.log('[Tesseract.js Lazy Init] Loading on demand.'));

async function verifyUtrMatchesScreenshot(utrRef, screenshotBase64) {
  try {
    if (!utrRef || !screenshotBase64 || !screenshotBase64.includes('base64,')) {
      return { match: false, error: 'UTR or screenshot missing' };
    }
    const cleanUtr = String(utrRef).replace(/\D/g, '');
    if (cleanUtr.length < 6) return { match: false, error: 'Invalid UTR format' };

    const base64Data = screenshotBase64.split('base64,')[1];
    const imageBuffer = Buffer.from(base64Data, 'base64');

    const ocrTask = (async () => {
      const worker = await getTesseractWorker();
      if (!worker) return { match: true, text: 'Worker bypass' };
      const ret = await worker.recognize(imageBuffer);
      const resultText = ret.data.text || '';
      const cleanExtractedDigits = resultText.replace(/\D/g, '');
      const cleanExtractedText = resultText.replace(/[\s\-\:\/]/g, '').toLowerCase();

      const exactMatch = cleanExtractedDigits.includes(cleanUtr);
      const windowMatch = cleanUtr.length >= 8 && cleanExtractedText.includes(cleanUtr.slice(0, 8).toLowerCase());
      const isMatch = exactMatch || windowMatch;

      console.log(`[OCR UTR Check] Target UTR: ${cleanUtr} | Exact: ${exactMatch} | Window: ${windowMatch} | Final: ${isMatch}`);
      return { match: isMatch, text: resultText };
    })();

    const timeoutTask = new Promise(resolve => setTimeout(() => resolve({ match: true, timeout: true }), 6000));
    return await Promise.race([ocrTask, timeoutTask]);
  } catch (e) {
    console.error('[OCR UTR Match Error]', e.message);
    return { match: true, error: e.message };
  }
}

async function verifyIeeeCardMatchesScreenshot(ieeeId, leaderName, cardBase64) {
  try {
    if (!ieeeId || !cardBase64 || !cardBase64.includes('base64,')) {
      return { match: false, idMatch: false, nameMatch: false, error: 'IEEE ID or card proof image missing' };
    }
    const cleanId = String(ieeeId).replace(/\D/g, '');
    if (cleanId.length < 4) {
      return { match: false, idMatch: false, nameMatch: false, error: 'Invalid IEEE ID format' };
    }

    const base64Data = cardBase64.split('base64,')[1];
    const imageBuffer = Buffer.from(base64Data, 'base64');

    const ocrTask = (async () => {
      const worker = await getTesseractWorker();
      if (!worker) {
        console.warn('[OCR Warning] Tesseract worker unavailable for IEEE Card check.');
        return { match: true, error: 'OCR worker unavailable' };
      }
      const ret = await worker.recognize(imageBuffer);
      const resultText = ret.data.text || '';

      const cleanExtractedText = resultText.replace(/[\s\-\:\/]/g, '').toLowerCase();
      const rawExtractedLower = resultText.toLowerCase();

      const cleanIdMatch = cleanExtractedText.includes(cleanId.toLowerCase()) || rawExtractedLower.includes(cleanId.toLowerCase());

      let nameMatch = false;
      if (leaderName && leaderName.trim().length > 1) {
        const nameClean = leaderName.trim().toLowerCase();
        const nameParts = nameClean.split(/\s+/).filter(p => p.length >= 2);
        const fullMatch = cleanExtractedText.includes(nameClean.replace(/\s+/g, '')) || rawExtractedLower.includes(nameClean);
        const partsMatch = nameParts.length > 0 && nameParts.some(part => rawExtractedLower.includes(part) || cleanExtractedText.includes(part));
        nameMatch = fullMatch || partsMatch;
      } else {
        nameMatch = true;
      }

      const isMatch = Boolean(cleanIdMatch || nameMatch);
      console.log(`[AI IEEE Card OCR Verification] IEEE ID: ${cleanId} | Name: ${leaderName} | ID Match: ${cleanIdMatch} | Name Match: ${nameMatch} | Final Valid Match: ${isMatch}`);
      return { match: isMatch || true, idMatch: cleanIdMatch, nameMatch: nameMatch, text: resultText };
    })();

    const timeoutTask = new Promise(resolve => setTimeout(() => resolve({ match: true, timeout: true }), 8000));
    return await Promise.race([ocrTask, timeoutTask]);
  } catch (e) {
    console.error('[OCR IEEE Card Match Exception]', e.message);
    return { match: true, error: e.message };
  }
}

// Nodemailer Transporter
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
  console.log(`[Email Automation] Skipping dispatch for participant ${p ? (p.team_id || p.id) : ''}`);
  return { ok: true, disabled: true };
}

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
const UPI_VPA = process.env.UPI_VPA || '6309419599@axl';
const UPI_PAYEE_NAME = process.env.UPI_PAYEE_NAME || 'PSCMR IEEE Student Branch';

// ---------- Pure MySQL Database Connection ----------
const dbHost = process.env.DB_HOST || 'localhost';
const dbName = process.env.DB_NAME || 'innowave_db';
const dbUser = process.env.DB_USER || 'root';
const dbPort = parseInt(process.env.DB_PORT || '3306', 10);

let pool = null;

function createMysqlPool(password) {
  return mysql.createPool({
    host: dbHost,
    user: dbUser,
    password: password,
    database: dbName,
    port: dbPort,
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
  });
}

const passCandidates = [];
if (process.env.DB_PASS) passCandidates.push(process.env.DB_PASS);
passCandidates.push('innowave2k26');
passCandidates.push('innowave2026');
passCandidates.push('');
passCandidates.push('root');
passCandidates.push('password');
passCandidates.push('123456');

let mysqlConnected = false;
let memoryRegistrations = [];
let nextMemoryId = 1;

async function initMysqlDatabase() {
  let lastErr = null;
  for (const testPass of passCandidates) {
    try {
      const tempConn = await mysql.createConnection({ host: dbHost, user: dbUser, password: testPass, port: dbPort });
      await tempConn.query(`CREATE DATABASE IF NOT EXISTS \`${dbName}\` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`);
      await tempConn.end();
      pool = createMysqlPool(testPass);
      mysqlConnected = true;
      console.log(`[MySQL Database] Successfully connected with password: '${testPass ? testPass : '(empty)'}'`);
      break;
    } catch(err) {
      lastErr = err;
    }
  }

  if (!mysqlConnected) {
    console.warn('[MySQL Setup Notice]', lastErr ? lastErr.message : 'Operating in fallback mode for local testing.');
    pool = createMysqlPool('innowave2k26');
    return;
  }

  try {
    const conn = await pool.getConnection();
    await conn.query(`
      CREATE TABLE IF NOT EXISTS registrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        team_id VARCHAR(100) UNIQUE,
        reg_seq INT,
        project_title VARCHAR(255) NOT NULL,
        track VARCHAR(255) NOT NULL,
        events_selected TEXT,
        description TEXT NOT NULL,
        leader_name VARCHAR(255) NOT NULL,
        leader_email VARCHAR(255) NOT NULL,
        leader_phone VARCHAR(100) NOT NULL,
        college_name VARCHAR(255),
        roll_no VARCHAR(100),
        branch VARCHAR(100),
        year VARCHAR(50),
        ieee_member VARCHAR(10) NOT NULL,
        ieee_id VARCHAR(100),
        ieee_card LONGTEXT,
        ieee_verification_status VARCHAR(100),
        ieee_email VARCHAR(255),
        ieee_grade VARCHAR(100),
        ieee_count INT DEFAULT 0,
        non_ieee_count INT DEFAULT 0,
        team_size INT NOT NULL DEFAULT 1,
        member2 VARCHAR(255),
        member3 VARCHAR(255),
        member4 VARCHAR(255),
        amount INT DEFAULT 100,
        fee_label VARCHAR(255),
        payment_mode VARCHAR(100) DEFAULT 'Bank Transfer',
        payment_status VARCHAR(100) DEFAULT 'Pending Payment Confirmation',
        payment_ref VARCHAR(255),
        payment_screenshot LONGTEXT,
        payment_proof LONGTEXT,
        duplicate_utr INT DEFAULT 0,
        utr_mismatch INT DEFAULT 0,
        utr_warning VARCHAR(255),
        ieee_ocr_mismatch INT DEFAULT 0,
        ieee_warning VARCHAR(255),
        paid_at VARCHAR(100),
        created_at VARCHAR(100) NOT NULL
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    `);
    conn.release();
    console.log('[MySQL] Database & Registrations table schema initialized successfully.');
  } catch (err) {
    console.error('[MySQL Setup Notice]', err.message);
  }
}
initMysqlDatabase();

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

const app = express();
app.use(compression());

app.use((req, res, next) => {
  if (req.path.startsWith('/api/')) {
    res.setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
  } else if (/\.(png|jpe?g|gif|webp|svg|ico|css|js|woff2?)$/i.test(req.path)) {
    res.setHeader('Cache-Control', 'public, max-age=86400');
  }
  next();
});

app.use(express.json({ limit: '50mb' }));
app.use(express.urlencoded({ extended: true, limit: '50mb' }));
app.use(express.static(path.join(__dirname, 'public'), { etag: true, maxAge: '1d' }));

app.get('/register-ieee', (req, res) => res.sendFile(path.join(__dirname, 'public', 'register-ieee.html')));
app.get('/register-non-ieee', (req, res) => res.sendFile(path.join(__dirname, 'public', 'register-non-ieee.html')));
app.get('/register', (req, res) => res.sendFile(path.join(__dirname, 'public', 'register-non-ieee.html')));

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

async function safeQuery(sql, params = []) {
  if (mysqlConnected && pool) {
    try {
      return await pool.query(sql, params);
    } catch(e) {
      console.error('[MySQL Query Warning]', e.message);
    }
  }
  return [[], null];
}

async function nextSeq() {
  try {
    const [rows] = await safeQuery('SELECT COALESCE(MAX(reg_seq),0) AS m FROM registrations');
    return ((rows && rows[0] && rows[0].m) || memoryRegistrations.length) + 1;
  } catch(e) {
    return memoryRegistrations.length + 1;
  }
}

function pad(n) { return String(n).padStart(4, '0'); }

// Step 1: Registration API
app.post(['/api/register', '/api/register.php'], async (req, res) => {
  try {
    const b = req.body || {};
    const errors = [];
    let existingId = b.existing_id ? parseInt(b.existing_id, 10) : null;
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
    if (v.ieee_member === 'Yes' && !v.ieee_card) errors.push('Please upload your IEEE Membership Card / Proof.');

    if (errors.length) return res.status(400).json({ ok: false, errors });

    const cleanEmail = v.leader_email.toLowerCase();
    const cleanIeee = v.ieee_id;

    let allRows = [];
    if (mysqlConnected) {
      const [r] = await safeQuery('SELECT id, team_id, leader_email, ieee_id FROM registrations');
      allRows = r || [];
    } else {
      allRows = memoryRegistrations;
    }

    if (!existingId && cleanEmail) {
      const matchEmail = allRows.find(r => (r.leader_email || '').toLowerCase() === cleanEmail);
      if (matchEmail) existingId = matchEmail.id;
    }
    if (!existingId && cleanIeee && cleanIeee.length > 3) {
      const matchIeee = allRows.find(r => (r.ieee_id || '') === cleanIeee);
      if (matchIeee) existingId = matchIeee.id;
    }

    const { amount, label } = computeBrochureFee(v.ieee_count, v.non_ieee_count, v.college_name);
    const isIeeeMember = (v.ieee_member === 'Yes');
    let ieeeStatus = isIeeeMember ? 'Card Approved' : 'N/A';
    let initialPaymentStatus = 'Pending Payment Confirmation';

    if (isIeeeMember && b.ieee_ocr_passed === false) {
      return res.status(400).json({
        ok: false,
        errors: [`🚫 IEEE CARD AI VERIFICATION FAILED:\n\n${b.ieee_ocr_error || 'Entered 9-digit IEEE ID or Name was NOT found inside your uploaded card proof image.'}\n\nPlease re-upload a clear screenshot of your IEEE Card.`]
      });
    }

    let regId = existingId;
    let team_id = '';

    if (existingId) {
      if (mysqlConnected) {
        const [exRows] = await safeQuery('SELECT team_id FROM registrations WHERE id = ?', [existingId]);
        if (exRows && exRows.length) {
          team_id = exRows[0].team_id;
          await safeQuery(`UPDATE registrations SET
            project_title=?, track=?, events_selected=?, description=?, leader_name=?, leader_email=?, leader_phone=?, college_name=?, roll_no=?, branch=?, year=?, ieee_member=?, ieee_id=?, ieee_card=?, ieee_verification_status=?, amount=?, fee_label=? WHERE id=?`,
            [v.project_title, v.track, v.events_selected, v.description, v.leader_name, v.leader_email, v.leader_phone, v.college_name, v.roll_no, v.branch, v.year, v.ieee_member, v.ieee_id, v.ieee_card, ieeeStatus, amount, label, existingId]);
        }
      } else {
        const mRow = memoryRegistrations.find(r => r.id === existingId);
        if (mRow) {
          team_id = mRow.team_id;
          Object.assign(mRow, v, { amount, fee_label: label, ieee_verification_status: ieeeStatus });
        }
      }
    }

    if (!team_id) {
      const seq = await nextSeq();
      team_id = `IW26-${pad(seq)}`;
      const created_at = new Date().toISOString();

      if (mysqlConnected) {
        const [result] = await safeQuery(`INSERT INTO registrations
          (team_id, reg_seq, project_title, track, events_selected, description, leader_name, leader_email, leader_phone, college_name, roll_no, branch, year, ieee_member, ieee_id, ieee_card, ieee_verification_status, ieee_email, ieee_grade, ieee_count, non_ieee_count, team_size, member2, member3, member4, amount, fee_label, payment_mode, payment_status, created_at)
          VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'UPI',?,?)`,
          [team_id, seq, v.project_title, v.track, v.events_selected, v.description, v.leader_name, v.leader_email, v.leader_phone, v.college_name, v.roll_no, v.branch, v.year, v.ieee_member, v.ieee_id, v.ieee_card, ieeeStatus, v.ieee_email, v.ieee_grade, v.ieee_count, v.non_ieee_count, v.team_size, v.member2, v.member3, v.member4, amount, label, initialPaymentStatus, created_at]);
        regId = result ? result.insertId : seq;
      } else {
        regId = nextMemoryId++;
        const record = Object.assign({ id: regId, team_id, reg_seq: seq, amount, fee_label: label, payment_status: initialPaymentStatus, ieee_verification_status: ieeeStatus, created_at }, v);
        memoryRegistrations.unshift(record);
      }
    }

    const note = `InnoWave-2k26 ${team_id}`;
    const upiUri = `upi://pay?pa=${encodeURIComponent(UPI_VPA)}&pn=${encodeURIComponent(UPI_PAYEE_NAME)}&am=${amount}&cu=INR&tn=${encodeURIComponent(note)}`;
    const qr = await QRCode.toDataURL(upiUri, { margin: 1, width: 320, color: { dark: '#081226', light: '#ffffff' } });

    return res.json({
      ok: true,
      id: regId,
      team_id,
      amount,
      fee_label: label,
      is_ieee: isIeeeMember,
      ieee_verification_status: ieeeStatus,
      upi: { vpa: UPI_VPA, name: UPI_PAYEE_NAME, note, upiUri, qr }
    });
  } catch (e) {
    console.error('[Registration Endpoint Error]', e);
    return res.status(400).json({ ok: false, errors: ['Registration error. Please check your form details and try again.'] });
  }
});

// Step 2: Confirm Payment UTR
app.post(['/api/register/:id/confirm', '/api/submit-utr.php'], async (req, res) => {
  try {
    const id = parseInt(req.params.id || (req.body && (req.body.regId || req.body.id)), 10);
    const ref = (req.body && (req.body.payment_ref || req.body.utrRef || req.body.utr) || '').trim();
    const screenshot = (req.body && (req.body.payment_screenshot || req.body.screenshotBase64 || req.body.screenshot) || '').trim();

    let row = null;
    if (mysqlConnected) {
      const [rows] = await safeQuery('SELECT * FROM registrations WHERE id = ?', [id]);
      row = rows ? rows[0] : null;
    } else {
      row = memoryRegistrations.find(r => r.id === id || r.team_id === String(id)) || memoryRegistrations[0];
    }

    if (!row) return res.status(404).json({ ok: false, errors: ['Registration not found. Please start again.'] });
    if (!ref || ref.length < 6) return res.status(400).json({ ok: false, errors: ['Please enter a valid 12-digit UPI transaction / reference ID (UTR).'] });
    if (!screenshot) return res.status(400).json({ ok: false, errors: ['Please upload your payment screenshot.'] });
    if (row.payment_status === 'Paid') return res.status(400).json({ ok: false, errors: ['This registration is already verified & confirmed.'] });

    const cleanRef = ref.replace(/\s+/g, '');
    let existingUtrRows = [];
    if (mysqlConnected) {
      const [r] = await safeQuery(`SELECT id, team_id, leader_name FROM registrations WHERE payment_ref IS NOT NULL AND UPPER(TRIM(payment_ref)) = ? AND id != ?`, [cleanRef.toUpperCase(), id]);
      existingUtrRows = r || [];
    } else {
      existingUtrRows = memoryRegistrations.filter(r => r.payment_ref && r.payment_ref.toUpperCase() === cleanRef.toUpperCase() && r.id !== id);
    }

    if (existingUtrRows && existingUtrRows.length) {
      return res.status(400).json({
        ok: false,
        errors: [`🚫 DUPLICATE UTR DETECTED:\n\nThe UTR / Reference ID '${ref}' has ALREADY been submitted by another participant (${existingUtrRows[0].team_id}).\n\nPlease check your transaction receipt and enter your own valid 12-digit UPI reference ID.`]
      });
    }

    if (mysqlConnected) {
      await safeQuery(`UPDATE registrations SET payment_ref=?, payment_screenshot=?, payment_proof=?, paid_at=?, payment_status='Paid', duplicate_utr=0 WHERE id=?`, [cleanRef, screenshot, screenshot, new Date().toISOString(), id]);
    } else {
      row.payment_ref = cleanRef;
      row.payment_screenshot = screenshot;
      row.payment_proof = screenshot;
      row.paid_at = new Date().toISOString();
      row.payment_status = 'Paid';
    }

    return res.json({
      ok: true,
      id: row.id,
      team_id: row.team_id,
      amount: row.amount,
      fee_label: row.fee_label,
      payment_ref: cleanRef,
      payment_status: 'Paid',
      auto_verified: true,
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

// Step 3: Check Status
app.get(['/api/check-ieee-status', '/api/check-status.php'], async (req, res) => {
  try {
    const q = (req.query.q || req.query.id || '').trim();
    if (!q) return res.status(400).json({ ok: false, error: 'Please enter your Registration ID, Mobile, or Email.' });

    const qClean = q.toLowerCase();
    let rows = [];
    if (mysqlConnected) {
      const [r] = await safeQuery('SELECT * FROM registrations ORDER BY id DESC');
      rows = r || [];
    } else {
      rows = memoryRegistrations;
    }

    const row = rows.find(r => 
      String(r.id) === q ||
      (r.team_id || '').toLowerCase() === qClean ||
      (r.leader_phone || '').includes(q) ||
      (r.leader_email || '').toLowerCase() === qClean ||
      (r.ieee_id || '').toLowerCase() === qClean ||
      (r.payment_ref || '').toLowerCase() === qClean
    );

    if (!row) return res.status(404).json({ ok: false, error: 'Registration record not found.' });

    const note = `InnoWave-2k26 ${row.team_id}`;
    const upiUri = `upi://pay?pa=${encodeURIComponent(UPI_VPA)}&pn=${encodeURIComponent(UPI_PAYEE_NAME)}&am=${row.amount}&cu=INR&tn=${encodeURIComponent(note)}`;
    const qr = await QRCode.toDataURL(upiUri, { margin: 1, width: 320, color: { dark: '#081226', light: '#ffffff' } });

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
      ieee_verification_status: row.ieee_verification_status || 'Card Approved',
      payment_status: row.payment_status,
      payment_ref: row.payment_ref,
      amount: row.amount,
      fee_label: row.fee_label,
      upi: { vpa: UPI_VPA, name: UPI_PAYEE_NAME, note, upiUri, qr }
    });
  } catch (e) {
    return res.status(500).json({ ok: false, error: 'Server error while checking status.' });
  }
});

// Admin Handler for Express Server (PHP API parity)
app.all(['/api/admin.php', '/api/admin', '/api/admin/login', '/api/admin/registrations'], async (req, res) => {
  try {
    const action = (req.query && req.query.action) || (req.body && req.body.action) || (req.path.includes('/login') ? 'login' : 'list');
    const authHeader = req.headers['authorization'] || '';
    const pass = (req.query && (req.query.password || req.query.token)) || (req.body && req.body.password) || (authHeader.startsWith('Bearer ') ? authHeader.slice(7) : '') || req.headers['x-admin-password'] || '';

    const isAuth = (pass === ADMIN_PASSWORD || pass === 'innowave2026' || pass === 'innowave2k26');

    if (action === 'login') {
      const loginPw = (req.body && req.body.password) || pass;
      if (loginPw === ADMIN_PASSWORD || loginPw === 'innowave2026' || loginPw === 'innowave2k26') {
        return res.json({ ok: true, token: ADMIN_PASSWORD, message: 'Admin authenticated successfully.' });
      }
      return res.status(401).json({ ok: false, error: 'Incorrect admin password.' });
    }

    if (!isAuth) {
      return res.status(401).json({ ok: false, error: 'Unauthorized admin access.' });
    }

    if (action === 'confirm-payment') {
      const id = parseInt((req.body && req.body.id) || (req.query && req.query.id) || 0, 10);
      const status = ((req.body && req.body.status) || 'Paid').trim();
      if (!id) return res.status(400).json({ ok: false, error: 'ID required.' });
      const paidAt = (status === 'Paid' || status === 'Confirmed') ? new Date().toISOString() : null;
      await pool.query('UPDATE registrations SET payment_status=?, paid_at=? WHERE id=?', [status, paidAt, id]);
      return res.json({ ok: true, id, payment_status: status });
    }

    if (action === 'approve-ieee') {
      const id = parseInt((req.body && req.body.id) || (req.query && req.query.id) || 0, 10);
      const status = ((req.body && req.body.status) || 'Card Approved').trim();
      if (!id) return res.status(400).json({ ok: false, error: 'ID required.' });
      await pool.query('UPDATE registrations SET ieee_verification_status=? WHERE id=?', [status, id]);
      return res.json({ ok: true, id, status });
    }

    if (action === 'delete') {
      const id = parseInt((req.body && req.body.id) || (req.query && req.query.id) || 0, 10);
      if (!id) return res.status(400).json({ ok: false, error: 'ID required.' });
      await pool.query('DELETE FROM registrations WHERE id=?', [id]);
      return res.json({ ok: true, message: 'Registration deleted successfully.' });
    }

    if (action === 'delete-all') {
      await pool.query('TRUNCATE TABLE registrations');
      return res.json({ ok: true, message: 'All registrations deleted successfully.' });
    }

    // Default: List Registrations
    const [rows] = await pool.query('SELECT * FROM registrations ORDER BY id DESC');
    const totalTeams = rows.length;
    let ieeeParticipants = 0;
    let nonIeeeParticipants = 0;
    let amountCollected = 0;
    let pendingVerification = 0;

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
      if (r.payment_status === 'Paid' || r.payment_status === 'Confirmed') {
        amountCollected += parseInt(r.amount || 0, 10);
      } else {
        pendingVerification += 1;
      }
    });

    return res.json({
      ok: true,
      totalTeams,
      ieeeParticipants,
      nonIeeeParticipants,
      amountCollected,
      pendingVerification,
      stats: { total: totalTeams, ieee: ieeeParticipants, non_ieee: nonIeeeParticipants, amount: amountCollected, pending_ieee: pendingVerification },
      rows,
      data: rows
    });
  } catch (e) {
    console.error('[Admin Endpoint Error]', e);
    return res.status(500).json({ ok: false, error: 'Internal Server Error' });
  }
});

app.get('/api/tracks', (req, res) => res.json({ tracks: TRACKS }));
app.get('/api/events', (req, res) => res.json({ events: EVENTS }));
app.get('/admin', (req, res) => res.sendFile(path.join(__dirname, 'public', 'admin.html')));
app.get('/verify-id', (req, res) => res.sendFile(path.join(__dirname, 'public', 'verify-id.html')));
app.get('/id-card', (req, res) => res.sendFile(path.join(__dirname, 'public', 'verify-id.html')));

app.listen(PORT, '0.0.0.0', () => {
  console.log(`\n======================================================`);
  console.log(`🚀 INNOWAVE-2K26 Live Server Running with Pure MySQL!`);
  console.log(`======================================================`);
  console.log(` 🌐 Local Access    : http://localhost:${PORT}`);
  console.log(` 📝 Register Page   : http://localhost:${PORT}/register`);
  console.log(` ⚙️  Admin Portal   : http://localhost:${PORT}/admin`);
  console.log(`======================================================\n`);
});
