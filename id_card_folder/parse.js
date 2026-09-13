const fs = require('fs');
const path = require('path');

const csvPath = path.join(__dirname, 'participants_data.csv');
const raw = fs.readFileSync(csvPath, 'utf8');

const lines = raw.split(/\r?\n/).filter(l => l.trim().length > 0);
const header = lines[0];
const participants = [];

for (let i = 1; i < lines.length; i++) {
  const line = lines[i].trim();
  if (!line) continue;

  // Regex to match CSV columns enclosed in double quotes
  const regex = /"([^"]*)"/g;
  let match;
  const cols = [];
  while ((match = regex.exec(line)) !== null) {
    cols.push(match[1]);
  }

  if (cols.length >= 6) {
    participants.push({
      index: participants.length + 1,
      id: 'IW26-P' + String(participants.length + 1).padStart(3, '0'),
      timestamp: cols[0],
      name: cols[1].trim(),
      email: cols[2].trim(),
      phone: cols[3].trim().replace(/\s+/g, ''),
      branchYear: cols[4].trim(),
      rollNo: cols[5].trim()
    });
  }
}

console.log('Total participants parsed:', participants.length);
fs.writeFileSync(path.join(__dirname, 'participants.json'), JSON.stringify(participants, null, 2));
