const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const chromePath = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const outDir = path.join(__dirname, 'test_out');
if (!fs.existsSync(outDir)) fs.mkdirSync(outDir, { recursive: true });

const testHtml = path.join(outDir, 'test.html');
fs.writeFileSync(testHtml, '<!DOCTYPE html><html><body style="margin:0;background:#04091a;"><h1 style="color:#00f2fe;font-family:sans-serif;padding:20px;">INNOWAVE-2K26 TEST</h1></body></html>');

const testPng = path.join(outDir, 'test.png');
try {
  execSync(`"${chromePath}" --headless=new --disable-gpu --screenshot="${testPng}" --window-size=440,650 "${testHtml}"`);
  console.log('Success! test.png size:', fs.statSync(testPng).size);
} catch (e) {
  console.error('Chrome execution error:', e.message);
}
