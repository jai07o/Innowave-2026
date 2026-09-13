const fs = require('fs');
const path = require('path');

const participants = JSON.parse(fs.readFileSync(path.join(__dirname, 'participants.json'), 'utf8'));

// Generate complete HTML file containing all 109 cards with seamless Print & 4K PNG Download
const htmlContent = `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>InnoWave-2K26 · Official Delegate ID Cards (${participants.length} Participants)</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Space+Grotesk:wght@600;700;800;900&display=swap" rel="stylesheet">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>

  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Inter', sans-serif;
      background: #030712;
      color: #ffffff;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    .font-space { font-family: 'Space Grotesk', sans-serif; }

    /* Sticky Navigation & Controls Bar */
    .header-bar {
      position: sticky;
      top: 0;
      z-index: 100;
      background: rgba(15, 23, 42, 0.96);
      backdrop-filter: blur(14px);
      border-bottom: 2px solid rgba(0, 242, 254, 0.35);
      padding: 12px 20px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.6);
    }
    .header-inner {
      max-width: 1440px;
      margin: 0 auto;
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
    }
    .header-title {
      font-size: 17px;
      font-weight: 900;
      display: flex;
      align-items: center;
      gap: 10px;
      letter-spacing: 0.02em;
    }
    .count-pill {
      background: rgba(0, 242, 254, 0.15);
      border: 1px solid #00f2fe;
      color: #00f2fe;
      font-size: 11.5px;
      padding: 3px 10px;
      border-radius: 12px;
      font-weight: 800;
      font-family: 'Space Grotesk', sans-serif;
    }

    .controls-group {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }
    .search-input {
      background: #04091a;
      border: 1.5px solid rgba(0, 242, 254, 0.35);
      border-radius: 10px;
      padding: 8px 14px;
      color: #ffffff;
      font-size: 13.5px;
      outline: none;
      width: 250px;
      font-family: inherit;
      transition: all 0.2s ease;
    }
    .search-input:focus {
      border-color: #00f2fe;
      box-shadow: 0 0 12px rgba(0, 242, 254, 0.35);
    }

    .btn {
      background: linear-gradient(135deg, #00d4ff 0%, #0066ff 100%);
      color: #041220;
      border: none;
      font-weight: 800;
      font-size: 12.5px;
      padding: 8px 16px;
      border-radius: 10px;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: all 0.2s ease;
      font-family: 'Space Grotesk', sans-serif;
      text-decoration: none;
    }
    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(0, 212, 255, 0.4);
    }
    .btn-gold {
      background: linear-gradient(135deg, #fbbf24 0%, #d97706 100%);
      color: #0f172a;
    }
    .btn-gold:hover {
      box-shadow: 0 6px 20px rgba(251, 191, 36, 0.4);
    }
    .btn-secondary {
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.25);
      color: #ffffff;
    }
    .btn-secondary:hover {
      background: rgba(255, 255, 255, 0.15);
      border-color: #00f2fe;
      color: #00f2fe;
    }

    /* Sub-notice banner */
    .notice-bar {
      background: #0b1329;
      border-bottom: 1px solid rgba(0, 242, 254, 0.15);
      padding: 8px 20px;
      text-align: center;
      font-size: 12px;
      color: #94a3b8;
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 16px;
      flex-wrap: wrap;
    }
    .notice-tag {
      color: #00f2fe;
      font-weight: 800;
    }

    /* Cards Grid */
    .cards-container {
      max-width: 1440px;
      margin: 20px auto 60px;
      padding: 0 16px;
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(440px, 1fr));
      gap: 28px;
      justify-items: center;
    }

    .card-unit {
      display: flex;
      flex-direction: column;
      align-items: center;
      width: 440px;
      position: relative;
    }

    .card-top-bar {
      width: 100%;
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 8px;
      padding: 0 4px;
      font-size: 12px;
      font-weight: 700;
      color: #94a3b8;
    }
    .card-btn-bar {
      width: 100%;
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      gap: 6px;
      margin-top: 10px;
    }
    .card-btn-bar button {
      padding: 8px 6px;
      font-size: 11.5px;
      justify-content: center;
      border-radius: 8px;
    }

    /* THE EXACT REDESIGNED ID CARD (440px) */
    .id-card {
      width: 440px;
      min-width: 440px;
      max-width: 440px;
      background: #04091a;
      border: 2.5px solid #00f2fe;
      border-radius: 24px;
      padding: 20px 16px;
      color: #ffffff;
      box-shadow: 0 10px 35px rgba(0, 0, 0, 0.6), 0 0 25px rgba(0, 242, 254, 0.2);
      text-align: center;
      box-sizing: border-box;
      position: relative;
      background-image: radial-gradient(circle at 50% 0%, rgba(0, 242, 254, 0.12) 0%, transparent 60%);
      cursor: pointer;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .id-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 15px 40px rgba(0, 0, 0, 0.8), 0 0 30px rgba(0, 242, 254, 0.35);
    }

    /* Print Cutting Guide (Only visible in Print) */
    .print-cut-guide {
      display: none;
    }

    /* PRINT STYLES (A4 Portrait - Clean 2 Cards Per Page with Cut Lines) */
    @media print {
      @page {
        size: A4 portrait;
        margin: 8mm 10mm;
      }
      body {
        background: #ffffff !important;
        color: #000000 !important;
        padding: 0 !important;
        margin: 0 !important;
      }
      .no-print, .header-bar, .notice-bar, .card-top-bar, .card-btn-bar, #cardModal, #progressModal {
        display: none !important;
      }
      .cards-container {
        display: block !important;
        margin: 0 !important;
        padding: 0 !important;
        max-width: 100% !important;
      }
      .card-unit {
        page-break-inside: avoid !important;
        break-inside: avoid !important;
        margin: 6mm auto 10mm !important;
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        width: 440px !important;
      }
      .print-cut-guide {
        display: block !important;
        width: 100% !important;
        text-align: center !important;
        margin-top: 8mm !important;
        border-bottom: 1.5px dashed #94a3b8 !important;
        font-size: 9px !important;
        color: #64748b !important;
        letter-spacing: 0.15em !important;
        padding-bottom: 2px !important;
      }
      .id-card {
        box-shadow: none !important;
        border: 2.5px solid #00f2fe !important;
        cursor: default !important;
        transform: none !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
      }
      .id-card * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
      }
    }

    /* Full-Screen Inspection / Preview Modal */
    #cardModal {
      display: none;
      position: fixed;
      inset: 0;
      z-index: 1000;
      background: rgba(0, 0, 0, 0.88);
      backdrop-filter: blur(12px);
      overflow-y: auto;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .modal-dialog {
      background: #0b1329;
      border: 2px solid #00f2fe;
      border-radius: 20px;
      padding: 24px;
      max-width: 600px;
      width: 100%;
      display: flex;
      flex-direction: column;
      align-items: center;
      position: relative;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.8), 0 0 35px rgba(0, 242, 254, 0.3);
    }
    .modal-close-btn {
      position: absolute;
      top: 14px;
      right: 18px;
      background: rgba(255, 255, 255, 0.1);
      border: 1px solid rgba(255, 255, 255, 0.2);
      color: #ffffff;
      width: 34px;
      height: 34px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      font-size: 16px;
      font-weight: 800;
      transition: all 0.2s ease;
    }
    .modal-close-btn:hover {
      background: #ef4444;
      border-color: #ef4444;
    }
    .modal-nav-bar {
      display: flex;
      justify-content: space-between;
      width: 100%;
      margin-bottom: 16px;
      align-items: center;
    }
    .modal-actions {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
      width: 100%;
      margin-top: 18px;
    }
    .modal-actions .btn {
      padding: 12px 16px;
      font-size: 13.5px;
      justify-content: center;
    }

    /* Batch Download Progress Modal */
    #progressModal {
      display: none;
      position: fixed;
      inset: 0;
      z-index: 2000;
      background: rgba(0, 0, 0, 0.9);
      backdrop-filter: blur(14px);
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .progress-box {
      background: #0f172a;
      border: 2px solid #00f2fe;
      border-radius: 18px;
      padding: 30px 24px;
      width: 100%;
      max-width: 480px;
      text-align: center;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.9);
    }
    .progress-track {
      background: #1e293b;
      border-radius: 10px;
      height: 14px;
      width: 100%;
      overflow: hidden;
      margin: 18px 0 10px;
      border: 1px solid rgba(0, 242, 254, 0.3);
    }
    .progress-fill {
      background: linear-gradient(90deg, #00f2fe, #3b82f6);
      height: 100%;
      width: 0%;
      transition: width 0.15s ease;
    }
  </style>
</head>
<body>

  <!-- Hidden iframe for silent, direct 1-click single-card print (no popup blocker) -->
  <iframe id="silentPrintFrame" style="position:fixed; right:0; bottom:0; width:0; height:0; border:0; visibility:hidden;"></iframe>

  <!-- Controls Header Bar -->
  <header class="header-bar no-print">
    <div class="header-inner">
      <div class="header-title">
        <span style="font-size:24px;">🎫</span>
        <div style="display:flex; flex-direction:column; line-height:1.2;">
          <span>INNOWAVE-2K26 · OFFICIAL ID CARDS</span>
          <span style="font-size:11px; font-weight:700; color:#38bdf8;">PRINT & HIGH-RESOLUTION DOWNLOAD SUITE</span>
        </div>
        <span class="count-pill" id="totalCountPill">${participants.length} CARDS READY</span>
      </div>

      <div class="controls-group">
        <input type="text" id="searchInput" class="search-input" placeholder="🔍 Search Name, Roll No, Phone..." oninput="filterCards()">
        <button class="btn" onclick="triggerPrintAll()">
          🖨️ PRINT ALL (A4 SHEETS)
        </button>
        <button class="btn btn-gold" id="btnZipAll" onclick="downloadAllZip()">
          📦 DOWNLOAD ALL (ZIP)
        </button>
      </div>
    </div>
  </header>

  <!-- Notice Subbar -->
  <div class="notice-bar no-print">
    <span><span class="notice-tag">✨ SPECIFICATIONS:</span> College, Branch & Year Removed</span>
    <span><span class="notice-tag">✓ FORMAT:</span> Admission No in Green Badge &nbsp;|&nbsp; 3×2 Event Checkboxes &nbsp;|&nbsp; QR & Signature Line</span>
    <span><span class="notice-tag">💡 TIP:</span> Click any card to inspect or print/download individually!</span>
  </div>

  <!-- Cards Grid Container -->
  <main class="cards-container" id="cardsGrid">
    ${participants.map((p, idx) => `
      <div class="card-unit" id="cardUnit_${idx}" data-name="${p.name.toLowerCase()}" data-roll="${p.rollNo.toLowerCase()}" data-phone="${p.phone}" data-id="${p.id.toLowerCase()}">
        <div class="card-top-bar no-print">
          <span style="color:#38bdf8; font-family:'Space Grotesk',monospace; font-weight:800;">#${p.index} · ${p.id}</span>
          <span style="font-size:11px;">📞 ${p.phone}</span>
        </div>

        <!-- ID Card Body (Clickable to open Preview Modal) -->
        <div class="id-card" id="card_${idx}" onclick="openModal(${idx})" title="Click to inspect, print or download this card">
          <!-- Header -->
          <div style="text-align:center; margin-bottom:10px;">
            <div style="display:inline-block; background:rgba(251, 191, 36, 0.12); border:1.5px solid #fbbf24; color:#fbbf24; border-radius:20px; padding:3px 14px; font-size:10.5px; font-weight:800; text-transform:uppercase; letter-spacing:0.08em; margin-bottom:6px;">
              👑 OFFICIAL DELEGATE PASS
            </div>
            <div style="font-family:'Space Grotesk',sans-serif; font-size:25px; font-weight:900; color:#ffffff; letter-spacing:0.05em; line-height:1.1; margin-bottom:2px;">
              INNOWAVE-2K26
            </div>
            <div style="font-size:11px; font-weight:800; color:#fbbf24; letter-spacing:0.08em; text-transform:uppercase;">
              ENGINEER'S DAY CELEBRATION
            </div>
            <div style="font-size:10px; font-weight:800; color:#38bdf8; letter-spacing:0.1em; text-transform:uppercase; margin-bottom:8px;">
              NATIONAL LEVEL FEST
            </div>
            <div style="display:inline-flex; align-items:center; justify-content:center; gap:6px; background:rgba(0, 242, 254, 0.1); border:1.5px solid #00f2fe; color:#00f2fe; border-radius:20px; padding:4px 16px; font-size:11.5px; font-weight:800; font-family:'Space Grotesk',monospace;">
              <span style="background:#00f2fe; color:#04091a; padding:1px 5px; border-radius:4px; font-size:9.5px; font-weight:900;">ID</span>
              <span>PARTICIPANT ID: <span>${p.id}</span></span>
            </div>
          </div>

          <div style="height:3px; background:linear-gradient(90deg, transparent, #fbbf24, transparent); margin-bottom:12px; border-radius:2px;"></div>

          <!-- White Inner Body -->
          <div style="background:#ffffff; border:2px solid #00f2fe; border-radius:16px; padding:12px; color:#0f172a; margin-bottom:12px; text-align:left;">
            <!-- Name Block -->
            <div style="background:#fffbeb; border:1.5px solid #fef08a; border-radius:12px; padding:9px 12px; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center;">
              <div>
                <div style="color:#92400e; font-size:9.5px; font-weight:800; text-transform:uppercase; letter-spacing:0.05em; display:flex; align-items:center; gap:4px; margin-bottom:1px;">
                  <span>👤</span> REGISTERED PARTICIPANT NAME
                </div>
                <div style="font-family:'Space Grotesk',sans-serif; font-size:19px; font-weight:900; color:#0f172a; line-height:1.2;">
                  ${p.name}
                </div>
              </div>
              <div style="background:#041b2d; color:#00f2fe; border:1px solid #00f2fe; font-size:10.5px; font-weight:800; padding:3px 8px; border-radius:6px; font-family:monospace;">
                ${p.id}
              </div>
            </div>

            <!-- Category & Track Badges -->
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; gap:8px;">
              <div style="display:inline-block; background:rgba(251, 191, 36, 0.15); border:1px solid #d97706; color:#b45309; border-radius:10px; padding:3px 10px; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:0.06em;">
                DELEGATE PARTICIPANT
              </div>
              <div style="color:#64748b; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.05em;">
                TRACK: OPEN INNOVATION
              </div>
            </div>

            <!-- Admission Number Block (Branch, College & Year Removed) -->
            <div style="display:grid; grid-template-columns:1fr; gap:10px; margin-bottom:10px;">
              <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-left:4px solid #16a34a; border-radius:10px; padding:7px 12px; text-align:left;">
                <div style="color:#15803d; font-size:9.5px; font-weight:800; text-transform:uppercase; letter-spacing:0.05em; display:flex; align-items:center; gap:4px; margin-bottom:1px;">
                  <span>🎫</span> ADMISSION / ROLL NUMBER
                </div>
                <div style="font-size:14.5px; font-weight:900; color:#14532d; font-family:'Space Grotesk',monospace; letter-spacing:0.04em;">
                  ${p.rollNo}
                </div>
              </div>
            </div>

            <!-- Events Checklist (3 x 2 Row Checkboxes) -->
            <div style="border:1px solid #cbd5e1; border-radius:12px; overflow:hidden; margin-bottom:10px;">
              <div style="background:#04091a; color:#ffffff; padding:6px 10px; display:flex; justify-content:space-between; align-items:center; font-size:10px; font-weight:800;">
                <div style="display:flex; align-items:center; gap:5px;">
                  <span>📋</span> EVENTS EVALUATION CHECKLIST
                </div>
                <div style="color:#00f2fe; font-family:'Space Grotesk',sans-serif; font-size:9.5px;">
                  INNOWAVE-2K26
                </div>
              </div>

              <div style="background:#ffffff; padding:8px; display:grid; grid-template-columns:repeat(3, 1fr); gap:6px;">
                <div style="display:flex; align-items:center; gap:5px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:5px 6px;">
                  <div style="width:14px; height:14px; min-width:14px; border:1.8px solid #475569; border-radius:3px; background:#ffffff;"></div>
                  <span style="font-size:9.5px; font-weight:800; color:#0f172a; line-height:1.1;">Technical Quiz</span>
                </div>
                <div style="display:flex; align-items:center; gap:5px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:5px 6px;">
                  <div style="width:14px; height:14px; min-width:14px; border:1.8px solid #475569; border-radius:3px; background:#ffffff;"></div>
                  <span style="font-size:9.5px; font-weight:800; color:#0f172a; line-height:1.1;">Coding Challenge</span>
                </div>
                <div style="display:flex; align-items:center; gap:5px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:5px 6px;">
                  <div style="width:14px; height:14px; min-width:14px; border:1.8px solid #475569; border-radius:3px; background:#ffffff;"></div>
                  <span style="font-size:9.5px; font-weight:800; color:#0f172a; line-height:1.1;">Tech Treasure</span>
                </div>
                <div style="display:flex; align-items:center; gap:5px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:5px 6px;">
                  <div style="width:14px; height:14px; min-width:14px; border:1.8px solid #475569; border-radius:3px; background:#ffffff;"></div>
                  <span style="font-size:9.5px; font-weight:800; color:#0f172a; line-height:1.1;">Project Expo</span>
                </div>
                <div style="display:flex; align-items:center; gap:5px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:5px 6px;">
                  <div style="width:14px; height:14px; min-width:14px; border:1.8px solid #475569; border-radius:3px; background:#ffffff;"></div>
                  <span style="font-size:9.5px; font-weight:800; color:#0f172a; line-height:1.1;">Prompt Engg</span>
                </div>
                <div style="display:flex; align-items:center; gap:5px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:5px 6px;">
                  <div style="width:14px; height:14px; min-width:14px; border:1.8px solid #475569; border-radius:3px; background:#ffffff;"></div>
                  <span style="font-size:9.5px; font-weight:800; color:#0f172a; line-height:1.1;">Reels (1 Min)</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Footer with QR & Signature -->
          <div style="display:flex; justify-content:space-between; align-items:flex-end; padding:2px 6px;">
            <div style="display:flex; align-items:center; gap:8px;">
              <div style="background:#ffffff; padding:3px; border-radius:6px; border:1.5px solid #00f2fe; width:54px; height:54px; display:flex; align-items:center; justify-content:center; overflow:hidden;">
                <img class="id-card-qr-img" data-id="${p.id}" src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent('verify-id.html?id=' + p.id)}" alt="Scan to Verify" style="width:100%; height:100%; object-fit:contain; border-radius:2px;" onerror="this.onerror=null; this.src='https://chart.googleapis.com/chart?cht=qr&chs=150x150&chl=' + encodeURIComponent('verify-id.html?id=' + p.id);" />
              </div>
              <div style="text-align:left;">
                <div style="color:#00f2fe; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:0.05em;">SCAN TO VERIFY</div>
                <div style="color:#94a3b8; font-size:8.5px; font-weight:600;">Official Pass</div>
                <div style="color:#64748b; font-size:8.5px; font-weight:600;">InnoWave-2k26</div>
              </div>
            </div>
            <div style="text-align:right;">
              <div style="display:inline-block; width:110px; border-bottom:1.5px solid #64748b; margin-bottom:3px;">&nbsp;</div>
              <div style="color:#fbbf24; font-size:9.5px; font-weight:800; text-transform:uppercase; letter-spacing:0.05em;">REGISTRATION SIGNATURE</div>
            </div>
          </div>
        </div>

        <!-- Cutting Guide Line for Printed A4 Sheets -->
        <div class="print-cut-guide">
          ✂ - - - - - - - - - - - - - - - CUT HERE ALONG DOTTED LINE - - - - - - - - - - - - - - - ✂
        </div>

        <!-- Single Card Action Bar -->
        <div class="card-btn-bar no-print">
          <button class="btn btn-secondary" onclick="openModal(${idx})">
            🔍 Inspect
          </button>
          <button class="btn btn-secondary" onclick="printSingleCard('card_${idx}', '${p.id}', '${p.name.replace(/'/g, "\\'")}')">
            🖨️ Print
          </button>
          <button class="btn" id="btnDl_${idx}" onclick="downloadCardPng('card_${idx}', '${p.id}_${p.name.replace(/[^a-zA-Z0-9]/g, '_')}', this)">
            📥 Save PNG
          </button>
        </div>

      </div>
    `).join('')}
  </main>

  <!-- Card Inspection & Action Modal -->
  <div id="cardModal" class="no-print">
    <div class="modal-dialog">
      <button class="modal-close-btn" onclick="closeModal()">✕</button>

      <div class="modal-nav-bar">
        <button class="btn btn-secondary" style="padding:6px 12px; font-size:12px;" onclick="navigateModal(-1)">◀ Previous</button>
        <div style="text-align:center;">
          <div id="modalParticipantLabel" style="font-weight:800; font-size:13px; color:#38bdf8;">CARD #1</div>
          <div style="font-size:11px; color:#94a3b8;">High-Resolution Pass Preview</div>
        </div>
        <button class="btn btn-secondary" style="padding:6px 12px; font-size:12px;" onclick="navigateModal(1)">Next ▶</button>
      </div>

      <!-- Container where current card clone is rendered -->
      <div id="modalCardHolder" style="display:flex; justify-content:center; width:100%;"></div>

      <!-- Modal Actions -->
      <div class="modal-actions">
        <button class="btn btn-secondary" id="btnModalPrint" onclick="printModalCard()">
          🖨️ PRINT THIS CARD (PDF / PRINTER)
        </button>
        <button class="btn btn-gold" id="btnModalDownload" onclick="downloadModalCard()">
          📥 DOWNLOAD 4K PNG IMAGE
        </button>
      </div>
    </div>
  </div>

  <!-- Batch Progress Modal -->
  <div id="progressModal" class="no-print">
    <div class="progress-box">
      <div style="font-size:32px; margin-bottom:8px;">📦</div>
      <div style="font-family:'Space Grotesk',sans-serif; font-size:19px; font-weight:800; color:#ffffff;" id="progressTitle">
        PACKAGING ALL ID CARDS
      </div>
      <div style="font-size:13px; color:#94a3b8; margin-top:4px;" id="progressSubtitle">
        Rendering cards into high-resolution PNGs...
      </div>

      <div class="progress-track">
        <div class="progress-fill" id="progressBar"></div>
      </div>

      <div style="display:flex; justify-content:space-between; font-size:12px; color:#38bdf8; font-family:monospace; font-weight:700;">
        <span id="progressCount">0 / ${participants.length}</span>
        <span id="progressPercent">0%</span>
      </div>

      <button class="btn btn-secondary" style="margin-top:20px; font-size:12px; padding:6px 16px;" onclick="cancelBatch()">
        ✕ Cancel
      </button>
    </div>
  </div>

  <script>
    const PARTICIPANTS = ${JSON.stringify(participants)};
    let activeModalIndex = 0;
    let cancelBatchRequested = false;

    // Dynamically update QR Codes with absolute host URL so phone camera scanning opens verify-id.html directly
    function updateQRCodesWithCurrentHost() {
      let hostUrl = window.location.origin;
      if (!hostUrl || hostUrl.startsWith('file:') || hostUrl === 'null') {
        hostUrl = window.location.protocol + '//' + (window.location.host || 'localhost:3000');
      }
      
      let rootPath = window.location.pathname;
      rootPath = rootPath.replace(/\/id_card_folder\/(index\.html)?$/i, '');
      if (!rootPath.endsWith('/')) rootPath += '/';
      
      const fullVerifyUrlBase = hostUrl + rootPath + 'verify-id.html';

      document.querySelectorAll('.id-card-qr-img').forEach(img => {
        const participantId = img.getAttribute('data-id');
        if (participantId) {
          const fullUrl = fullVerifyUrlBase + '?id=' + encodeURIComponent(participantId);
          img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' + encodeURIComponent(fullUrl);
        }
      });
    }
    window.addEventListener('DOMContentLoaded', updateQRCodesWithCurrentHost);

    // Search / Filter Function
    function filterCards() {
      const q = document.getElementById('searchInput').value.toLowerCase().trim();
      const units = document.querySelectorAll('.card-unit');
      let visible = 0;
      units.forEach(u => {
        const name = u.getAttribute('data-name') || '';
        const roll = u.getAttribute('data-roll') || '';
        const phone = u.getAttribute('data-phone') || '';
        const id = u.getAttribute('data-id') || '';
        if (!q || name.includes(q) || roll.includes(q) || phone.includes(q) || id.includes(q)) {
          u.style.display = 'flex';
          visible++;
        } else {
          u.style.display = 'none';
        }
      });
      document.getElementById('totalCountPill').textContent = visible + ' CARDS FOUND';
    }

    // Modal Operations
    function openModal(idx) {
      activeModalIndex = idx;
      renderModalCard();
      const modal = document.getElementById('cardModal');
      modal.style.display = 'flex';
      document.body.style.overflow = 'hidden';
    }

    function closeModal() {
      const modal = document.getElementById('cardModal');
      modal.style.display = 'none';
      document.body.style.overflow = 'auto';
    }

    function navigateModal(dir) {
      activeModalIndex += dir;
      if (activeModalIndex < 0) activeModalIndex = PARTICIPANTS.length - 1;
      if (activeModalIndex >= PARTICIPANTS.length) activeModalIndex = 0;
      renderModalCard();
    }

    function renderModalCard() {
      const p = PARTICIPANTS[activeModalIndex];
      document.getElementById('modalParticipantLabel').textContent = 'CARD #' + p.index + ' · ' + p.id + ' (' + (activeModalIndex + 1) + '/' + PARTICIPANTS.length + ')';
      const originalCard = document.getElementById('card_' + activeModalIndex);
      if (!originalCard) return;

      const holder = document.getElementById('modalCardHolder');
      holder.innerHTML = '';
      const clone = originalCard.cloneNode(true);
      clone.id = 'modalCloneCard';
      clone.style.cursor = 'default';
      clone.style.transform = 'none';
      clone.removeAttribute('onclick');
      holder.appendChild(clone);
    }

    // Direct High-Resolution PNG Download for Single Card
    async function downloadCardPng(cardId, filename, triggerBtn) {
      const el = document.getElementById(cardId);
      if (!el) return;

      const originalText = triggerBtn ? triggerBtn.innerHTML : null;
      if (triggerBtn) {
        triggerBtn.disabled = true;
        triggerBtn.innerHTML = '⏳ Saving...';
      }

      try {
        const canvas = await html2canvas(el, {
          scale: 3.5, // 300+ DPI Razor Sharp Output
          useCORS: true,
          allowTaint: true,
          backgroundColor: '#04091a',
          logging: false
        });

        const link = document.createElement('a');
        link.download = filename + '.png';
        link.href = canvas.toDataURL('image/png', 1.0);
        link.click();

        if (triggerBtn) {
          triggerBtn.innerHTML = '✅ Saved!';
          setTimeout(() => {
            triggerBtn.disabled = false;
            triggerBtn.innerHTML = originalText;
          }, 1800);
        }
      } catch (err) {
        console.error('PNG download error:', err);
        alert('Could not render image. Falling back to print...');
        printSingleCard(cardId);
        if (triggerBtn) {
          triggerBtn.disabled = false;
          triggerBtn.innerHTML = originalText;
        }
      }
    }

    // Single Card Direct Print (Using Silent iframe to avoid popup blocker)
    function printSingleCard(cardId, id, name) {
      const el = document.getElementById(cardId);
      if (!el) return;

      const iframe = document.getElementById('silentPrintFrame');
      const doc = iframe.contentWindow.document;
      doc.open();
      doc.write(\`<!DOCTYPE html>
      <html>
      <head>
        <title>ID Card - \${id || 'Delegate'} - \${name || 'InnoWave-2K26'}</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Space+Grotesk:wght@600;700;800;900&display=swap" rel="stylesheet">
        <style>
          @page {
            size: A4 portrait;
            margin: 15mm auto;
          }
          * { box-sizing: border-box; margin: 0; padding: 0; }
          body {
            background: #ffffff;
            font-family: 'Inter', sans-serif;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding-top: 15px;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color-adjust: exact !important;
          }
          .id-card {
            width: 440px !important;
            min-width: 440px !important;
            max-width: 440px !important;
            background: #04091a !important;
            border: 2.5px solid #00f2fe !important;
            border-radius: 24px !important;
            padding: 20px 16px !important;
            color: #ffffff !important;
            box-sizing: border-box !important;
            box-shadow: none !important;
            text-align: center !important;
            background-image: radial-gradient(circle at 50% 0%, rgba(0, 242, 254, 0.12) 0%, transparent 60%) !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color-adjust: exact !important;
          }
          .id-card * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color-adjust: exact !important;
          }
        </style>
      </head>
      <body>
        \${el.outerHTML}
      </body>
      </html>\`);
      doc.close();

      setTimeout(() => {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
      }, 400);
    }

    // Modal action delegates
    function printModalCard() {
      const p = PARTICIPANTS[activeModalIndex];
      printSingleCard('card_' + activeModalIndex, p.id, p.name);
    }

    function downloadModalCard() {
      const p = PARTICIPANTS[activeModalIndex];
      const btn = document.getElementById('btnModalDownload');
      downloadCardPng('card_' + activeModalIndex, p.id + '_' + p.name.replace(/[^a-zA-Z0-9]/g, '_'), btn);
    }

    // Print All 109 Cards on standard A4 Sheets
    function triggerPrintAll() {
      // Ensure all cards are visible before printing
      document.getElementById('searchInput').value = '';
      filterCards();
      window.print();
    }

    // Batch Download All Cards into a single ZIP file with live progress modal
    async function downloadAllZip() {
      const modal = document.getElementById('progressModal');
      const pBar = document.getElementById('progressBar');
      const pCount = document.getElementById('progressCount');
      const pPercent = document.getElementById('progressPercent');
      const pSubtitle = document.getElementById('progressSubtitle');

      cancelBatchRequested = false;
      modal.style.display = 'flex';

      try {
        const zip = new JSZip();
        const folder = zip.folder('InnoWave2k26_ID_Cards');
        const total = PARTICIPANTS.length;

        for (let i = 0; i < total; i++) {
          if (cancelBatchRequested) {
            alert('ZIP generation cancelled.');
            modal.style.display = 'none';
            return;
          }

          const p = PARTICIPANTS[i];
          const pct = Math.round(((i + 1) / total) * 100);
          pBar.style.width = pct + '%';
          pCount.textContent = (i + 1) + ' / ' + total;
          pPercent.textContent = pct + '%';
          pSubtitle.textContent = 'Rendering ' + p.id + ' · ' + p.name;

          const el = document.getElementById('card_' + i);
          if (el) {
            const canvas = await html2canvas(el, {
              scale: 2.8,
              useCORS: true,
              allowTaint: true,
              backgroundColor: '#04091a',
              logging: false
            });
            const dataUrl = canvas.toDataURL('image/png', 1.0).split(',')[1];
            const fname = p.id + '_' + p.name.replace(/[^a-zA-Z0-9]/g, '_') + '.png';
            folder.file(fname, dataUrl, { base64: true });
          }

          // Small yield to keep UI responsive
          await new Promise(resolve => setTimeout(resolve, 20));
        }

        pSubtitle.textContent = 'Compressing into ZIP archive...';
        const blob = await zip.generateAsync({
          type: 'blob',
          compression: 'DEFLATE',
          compressionOptions: { level: 6 }
        });

        saveAs(blob, 'InnoWave2k26_All_109_Delegate_ID_Cards.zip');
      } catch (err) {
        console.error('Batch error:', err);
        alert('Batch generation encountered an error: ' + err.message);
      } finally {
        modal.style.display = 'none';
      }
    }

    function cancelBatch() {
      cancelBatchRequested = true;
    }

    // Keyboard navigation for Modal
    window.addEventListener('keydown', (e) => {
      const modal = document.getElementById('cardModal');
      if (modal.style.display === 'flex') {
        if (e.key === 'Escape') closeModal();
        if (e.key === 'ArrowLeft') navigateModal(-1);
        if (e.key === 'ArrowRight') navigateModal(1);
      }
    });
  </script>

</body>
</html>`;

fs.writeFileSync(path.join(__dirname, 'index.html'), htmlContent);
console.log('Successfully generated public/id_card_folder/index.html with all', participants.length, 'cards!');
