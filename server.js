/**
 * InnoWave-2026 — Project Expo
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

const path = require('path');
const fs = require('fs');
const express = require('express');
const Database = require('better-sqlite3');
const ExcelJS = require('exceljs');
const QRCode = require('qrcode');

const PORT = process.env.PORT || 3000;
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || 'innowave2026';
const UPI_VPA = process.env.UPI_VPA || 'yourupi@okbank';          // <-- SET THIS to your real UPI ID
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
 'college_name TEXT','ieee_email TEXT','ieee_grade TEXT','ieee_count INTEGER','non_ieee_count INTEGER'].forEach(d => {
  const [c, ...rest] = d.split(' '); ensureColumn(c, rest.join(' '));
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

// ---------- App ----------
const app = express();
app.use(express.json({ limit: '10mb' }));
app.use(express.urlencoded({ extended: true, limit: '10mb' }));
app.use(express.static(path.join(__dirname, 'public')));

ensureColumn('payment_screenshot', 'TEXT');

// Helpers
function pad(n) { return String(n).padStart(4, '0'); }
function isEmail(s) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(s || ''); }
function isPhone(s) { return /^[0-9]{10}$/.test(String(s || '').replace(/\D/g, '').slice(-10)); }

function computeBrochureFee(ieee_count, non_ieee_count) {
  const ieee = Math.max(0, parseInt(ieee_count, 10) || 0);
  const nonIeee = Math.max(0, parseInt(non_ieee_count, 10) || 0);
  const amount = (ieee * 100) + (nonIeee * 200);
  let label = '';
  if (ieee > 0 && nonIeee > 0) {
    label = `${ieee} IEEE (₹${ieee * 100}) + ${nonIeee} Non-IEEE (₹${nonIeee * 200}) = ₹${amount}`;
  } else if (ieee > 0) {
    label = `${ieee} IEEE Member(s) × ₹100 = ₹${amount}`;
  } else {
    label = `${nonIeee} Non-IEEE Member(s) × ₹200 = ₹${amount}`;
  }
  return { amount, label, ieee, nonIeee, totalSize: ieee + nonIeee };
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
      description: (b.description || 'Participation in INNOWAVE-2026 Engineer\'s Day Celebration events.').trim(),
      leader_name: (b.leader_name || '').trim(),
      leader_email: (b.leader_email || '').trim(),
      leader_phone: (b.leader_phone || '').trim(),
      college_name: (b.college_name || '').trim(),
      roll_no: (b.roll_no || '').trim(),
      branch: (b.branch || '').trim(),
      year: (b.year || '').trim(),
      ieee_member: ieeeCount > 0 ? 'Yes' : 'No',
      ieee_id: (b.ieee_id || '').trim(),
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

    // Strict Duplicate Prevention (Phone, Email, Participant Name, IEEE ID)
    const cleanPhone = String(v.leader_phone || '').replace(/\D/g, '').slice(-10);
    const cleanEmail = (v.leader_email || '').trim().toLowerCase();
    const cleanName = (v.leader_name || '').trim().toLowerCase();
    const cleanIeee = (v.ieee_id || '').trim();

    const allRows = db.prepare(`SELECT team_id, leader_phone, leader_name, leader_email, ieee_id FROM registrations`).all();

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

    const { amount, label } = computeBrochureFee(v.ieee_count, v.non_ieee_count);
    const seq = nextSeq();
    const team_id = `IW26-${pad(seq)}`;
    const created_at = new Date().toISOString();

    const info = db.prepare(`INSERT INTO registrations
      (team_id, reg_seq, project_title, track, events_selected, description, leader_name, leader_email, leader_phone,
       college_name, roll_no, branch, year, ieee_member, ieee_id, ieee_email, ieee_grade, ieee_count, non_ieee_count,
       team_size, member2, member3, member4, amount, fee_label, payment_mode, payment_status, created_at)
      VALUES (@team_id,@reg_seq,@project_title,@track,@events_selected,@description,@leader_name,@leader_email,@leader_phone,
       @college_name,@roll_no,@branch,@year,@ieee_member,@ieee_id,@ieee_email,@ieee_grade,@ieee_count,@non_ieee_count,
       @team_size,@member2,@member3,@member4,@amount,@fee_label,'UPI','Pending',@created_at)`)
      .run({ ...v, team_id, reg_seq: seq, amount, fee_label: label, created_at });

    // Build UPI intent + QR with pre-filled amount parameter (am=amount)
    const note = `InnoWave-2026 ${team_id}`;
    const upiUri = `upi://pay?pa=${encodeURIComponent(UPI_VPA)}&pn=${encodeURIComponent(UPI_PAYEE_NAME)}&am=${amount}&cu=INR&tn=${encodeURIComponent(note)}`;
    const qr = await QRCode.toDataURL(upiUri, { margin: 1, width: 320, color: { dark: '#081226', light: '#ffffff' } });

    return res.json({
      ok: true,
      id: info.lastInsertRowid,
      team_id,
      amount,
      fee_label: label,
      upi: { vpa: UPI_VPA, name: UPI_PAYEE_NAME, note, upiUri, qr }
    });
  } catch (e) {
    console.error(e);
    return res.status(500).json({ ok: false, errors: ['Server error. Please try again.'] });
  }
});

// ---------- Step 2: confirm payment (submit UPI reference + payment screenshot) ----------
app.post('/api/register/:id/confirm', (req, res) => {
  try {
    const id = parseInt(req.params.id, 10);
    const ref = (req.body && req.body.payment_ref || '').trim();
    const screenshot = (req.body && req.body.payment_screenshot || '').trim();

    const row = db.prepare('SELECT * FROM registrations WHERE id = ?').get(id);
    if (!row) return res.status(404).json({ ok: false, errors: ['Registration not found. Please start again.'] });
    if (!ref || ref.length < 6) return res.status(400).json({ ok: false, errors: ['Please enter a valid 12-digit UPI transaction / reference ID (UTR).'] });
    if (row.payment_status === 'Paid') return res.status(400).json({ ok: false, errors: ['This registration is already verified & confirmed.'] });

    const cleanRef = ref.replace(/\s+/g, '');
    const is12DigitNumeric = /^\d{12}$/.test(cleanRef);
    const isValidUtrFormat = /^[A-Za-z0-9]{10,16}$/.test(cleanRef);
    const isDummySpam = /^0+$|^123456789/.test(cleanRef);

    let paymentStatus = 'Pending Verification';
    let autoVerified = false;

    // Automated verification engine: valid UTR formats auto-approve instantly
    if ((is12DigitNumeric || isValidUtrFormat) && !isDummySpam) {
      paymentStatus = 'Paid';
      autoVerified = true;
    }

    db.prepare(`UPDATE registrations SET payment_ref=?, payment_screenshot=?, paid_at=?, payment_status=? WHERE id=?`)
      .run(ref, screenshot, new Date().toISOString(), paymentStatus, id);

    return res.json({
      ok: true,
      team_id: row.team_id,
      amount: row.amount,
      fee_label: row.fee_label,
      payment_ref: ref,
      payment_status: paymentStatus,
      auto_verified: autoVerified,
      project_title: row.project_title,
      track: row.track,
      leader_name: row.leader_name,
      team_size: row.team_size
    });
  } catch (e) {
    console.error(e);
    return res.status(500).json({ ok: false, errors: ['Server error. Please try again.'] });
  }
});

// ---------- Admin auth ----------
function requireAdmin(req, res, next) {
  const auth = req.headers['authorization'] || '';
  const token = auth.startsWith('Bearer ') ? auth.slice(7) : (req.query.key || '');
  if (token === ADMIN_PASSWORD) return next();
  return res.status(401).json({ ok: false, error: 'Unauthorized' });
}
app.post('/api/admin/login', (req, res) => {
  const { password } = req.body || {};
  if (password === ADMIN_PASSWORD) return res.json({ ok: true, token: ADMIN_PASSWORD });
  return res.status(401).json({ ok: false, error: 'Incorrect password' });
});

app.get('/api/admin/registrations', requireAdmin, (req, res) => {
  const rows = db.prepare('SELECT * FROM registrations ORDER BY id DESC').all();
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

app.post('/api/admin/registrations/:id/status', requireAdmin, (req, res) => {
  const status = (req.body && req.body.status) || '';
  const allowed = ['Paid', 'Pending Verification', 'Pending'];
  if (!allowed.includes(status)) return res.status(400).json({ ok: false, error: 'Invalid status' });
  db.prepare('UPDATE registrations SET payment_status=? WHERE id=?').run(status, req.params.id);
  res.json({ ok: true });
});

app.delete('/api/admin/registrations/:id', requireAdmin, (req, res) => {
  db.prepare('DELETE FROM registrations WHERE id = ?').run(req.params.id);
  res.json({ ok: true });
});

// ---------- Excel export ----------
app.get('/api/admin/export.xlsx', requireAdmin, async (req, res) => {
  const rows = db.prepare('SELECT * FROM registrations ORDER BY id ASC').all();
  const wb = new ExcelJS.Workbook();
  wb.creator = 'InnoWave-2026';
  wb.created = new Date();
  const ws = wb.addWorksheet('Registrations', { views: [{ state: 'frozen', ySplit: 1 }] });

  ws.columns = [
    { header: 'S.No', key: 'sno', width: 6 },
    { header: 'Unique Participant ID', key: 'team_id', width: 22 },
    { header: 'Payment Status', key: 'payment_status', width: 20 },
    { header: 'Amount (₹)', key: 'amount', width: 12 },
    { header: 'Fee Category', key: 'fee_label', width: 30 },
    { header: 'Payment Ref (UTR)', key: 'payment_ref', width: 22 },
    { header: 'Participant Name', key: 'leader_name', width: 24 },
    { header: 'Email Address', key: 'leader_email', width: 28 },
    { header: 'Mobile Number', key: 'leader_phone', width: 16 },
    { header: 'College / Institution', key: 'college_name', width: 32 },
    { header: 'Branch / Dept', key: 'branch', width: 16 },
    { header: 'Year of Study', key: 'year', width: 14 },
    { header: 'Roll No', key: 'roll_no', width: 16 },
    { header: 'IEEE Member', key: 'ieee_member', width: 14 },
    { header: 'IEEE ID Number', key: 'ieee_id', width: 20 },
    { header: 'Selected Events', key: 'events_selected', width: 28 },
    { header: 'Paid Date & Time', key: 'paid_at', width: 22 },
    { header: 'Registered Date & Time', key: 'created_at', width: 22 }
  ];

  const header = ws.getRow(1);
  header.font = { bold: true, color: { argb: 'FFFFFFFF' }, size: 11 };
  header.alignment = { vertical: 'middle', horizontal: 'center', wrapText: true };
  header.height = 24;
  header.eachCell((cell) => {
    cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF0F2547' } };
    cell.border = { bottom: { style: 'thin', color: { argb: 'FF2FD3EC' } } };
  });

  const fmtDate = (s) => { const d = new Date(s); return isNaN(d) ? (s || '') : d.toLocaleString('en-IN'); };
  rows.forEach((r, i) => {
    let eventsStr = r.events_selected || '';
    try {
      const parsed = JSON.parse(eventsStr);
      if (Array.isArray(parsed)) eventsStr = parsed.join(', ');
    } catch(e) {}

    ws.addRow({
      sno: i + 1,
      team_id: r.team_id,
      payment_status: r.payment_status || 'Pending',
      amount: r.amount || 0,
      fee_label: r.fee_label || '',
      payment_ref: r.payment_ref || '',
      leader_name: r.leader_name || '',
      leader_email: r.leader_email || '',
      leader_phone: r.leader_phone || '',
      college_name: r.college_name || '',
      branch: r.branch || '',
      year: r.year || '',
      roll_no: r.roll_no || '',
      ieee_member: r.ieee_member || 'No',
      ieee_id: r.ieee_id || '',
      events_selected: eventsStr,
      paid_at: fmtDate(r.paid_at),
      created_at: fmtDate(r.created_at)
    });
  });
  ws.eachRow((row, n) => {
    if (n === 1) return;
    row.alignment = { vertical: 'top', wrapText: true };
    if (n % 2 === 0) row.eachCell((c) => { c.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF2F7FF' } }; });
  });
  ws.autoFilter = { from: 'A1', to: 'R1' };

  const stamp = new Date().toISOString().slice(0, 10);
  res.setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  res.setHeader('Content-Disposition', `attachment; filename="InnoWave-2026_Registrations_${stamp}.xlsx"`);
  await wb.xlsx.write(res);
  res.end();
});

app.get('/api/tracks', (req, res) => res.json({ tracks: TRACKS }));
app.get('/api/events', (req, res) => res.json({ events: EVENTS }));
app.get('/register', (req, res) => res.sendFile(path.join(__dirname, 'public', 'register.html')));
app.get('/admin', (req, res) => res.sendFile(path.join(__dirname, 'public', 'admin.html')));

const os = require('os');
app.listen(PORT, '0.0.0.0', () => {
  const nets = os.networkInterfaces();
  const ips = [];
  for (const name of Object.keys(nets)) {
    for (const net of nets[name]) {
      if (net.family === 'IPv4' && !net.internal) ips.push(net.address);
    }
  }
  console.log(`\n======================================================`);
  console.log(`🚀 INNOWAVE-2026 Live Server is Running!`);
  console.log(`======================================================`);
  console.log(` 🌐 Local Access    : http://localhost:${PORT}`);
  ips.forEach(ip => {
    console.log(` 📶 Network (LAN)   : http://${ip}:${PORT}`);
    console.log(` 📝 Register Page   : http://${ip}:${PORT}/register`);
    console.log(` ⚙️  Admin Portal   : http://${ip}:${PORT}/admin`);
  });
  console.log(`======================================================\n`);
});
