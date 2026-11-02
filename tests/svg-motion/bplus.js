// Image preview measurements on Playwright engines and real Safari. Run from tests/svg-motion: node bplus.js
const P = require('playwright'); const fs = require('fs'), path = require('path'); const { execFileSync } = require('child_process'); const { start } = require('./server.js');
const R = path.join(__dirname, 'results'), B = 'http://127.0.0.1:8770/pages/bplus.html';
(async () => { const stop = start(); const since = Date.now();
  for (const e of ['chromium', 'firefox', 'webkit']) { const b = await P[e].launch(); const p = await b.newPage({ viewport: { width: 600, height: 600 } }); await p.goto(B + '?engine=' + e); await p.waitForFunction(() => document.title === 'DONE' || document.title === 'ERR', null, { timeout: 120000 }); console.log(e, await p.title()); await b.close(); }
  const f = path.join(R, 'bplus-safari.json'); execFileSync('open', ['-a', 'Safari', B + '?engine=safari']); for (let i = 0; i < 40 && !(fs.existsSync(f) && fs.statSync(f).mtimeMs > since); i++) await new Promise(r => setTimeout(r, 2000)); console.log('safari', fs.existsSync(f) && fs.statSync(f).mtimeMs > since ? 'done' : 'NO RESULT');
  try { execFileSync('osascript', ['-e', 'tell application "Safari" to close (every tab of every window whose URL starts with "http://127.0.0.1:8770")']); } catch (e) { console.log('tabs left'); }
  stop(); process.exit(0);
})().catch(e => { console.error(e); process.exit(1); });
