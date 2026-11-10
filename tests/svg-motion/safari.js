// Real Safari cannot be driven without enabling safaridriver (needs an administrator), so this serves the pages
// that run by themselves and waits for their results. Run from tests/svg-motion: node safari.js
const { start } = require('./server.js'); const { execFileSync } = require('child_process'); const fs = require('fs'), path = require('path');
const R = path.join(__dirname, 'results'), B = 'http://127.0.0.1:8770/pages/';
(async () => { const stop = start(); const since = Date.now();
  for (const [page, q] of [['security', 'engine=safari&run=safari' + since], ['fidelity', 'engine=safari'], ['fps', 'engine=safari&auto=1']]) {
    const f = path.join(R, page + '-safari.json'); execFileSync('open', ['-a', 'Safari', B + page + '.html?' + q]);
    for (let i = 0; i < 60 && !(fs.existsSync(f) && fs.statSync(f).mtimeMs > since); i++) await new Promise(r => setTimeout(r, 2000));
    console.log(page, fs.existsSync(f) && fs.statSync(f).mtimeMs > since ? 'done' : 'NO RESULT');
  }
  try { execFileSync('osascript', ['-e', 'tell application "Safari" to close (every tab of every window whose URL starts with "http://127.0.0.1:8770")']); } catch (e) { console.log('tabs left open'); }
  stop(); process.exit(0);
})();
