// R2 client check (Chromium + WebKit): while the server answers 503 "warming", the box shows no broken UI, Enter still runs the
// normal site search, the client does not refetch on every keystroke (Retry-After), and after that delay it retries and gets suggestions.
// Usage: WP_LOAD=/path/to/wp-load.php node tests/nav-ux/suggest-warming.cjs   (dev environment; touches only the suggest transients)
const pw = require(process.env.PW_CORE || '/Users/omochi/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright-core');
const { execFileSync } = require('child_process');
const BASE = process.env.AFTER || 'http://127.0.0.1:8940';
const WP = process.env.WP_LOAD; if (!WP) { console.error('WP_LOAD is required'); process.exit(2); }
const php = (code) => execFileSync('php', ['-r', `require ${JSON.stringify(WP)}; ${code}`]);
const lock = () => php("delete_transient(OMOCHIX_SUGGEST_TRANSIENT); set_transient(OMOCHIX_SUGGEST_TRANSIENT . '_lock', 1, 30);");
const unlock = () => php("delete_transient(OMOCHIX_SUGGEST_TRANSIENT . '_lock');");
const res = {}; let fail = 0;
async function suite(name, L) {
  const b = await L.launch(); const r = {}; const errs = []; const idx = [];
  const p = await (await b.newContext({ viewport: { width: 1280, height: 800 } })).newPage();
  p.on('pageerror', (e) => errs.push(String(e))); p.on('response', (q) => { if (/suggest-index/.test(q.url())) idx.push(q.status()); });
  lock();
  try {
    await p.goto(BASE + '/'); await p.waitForTimeout(300);
    await p.focus('#ed-search-input'); await p.keyboard.type('claude', { delay: 30 }); await p.waitForTimeout(500);
    r.statusWhileWarming = idx.slice();
    r.rowsWhileWarming = await p.$$eval('.omx-suggest li[role=option]', (l) => l.map((x) => x.textContent.trim()));
    await Promise.all([p.waitForURL(/\?s=claude/, { timeout: 5000 }), p.keyboard.press('Enter')]);
    r.enterRunsNormalSearch = /\?s=claude/.test(p.url());
  } finally { unlock(); }
  // Same page (state kept): after Retry-After (5s) the next input refetches and gets the rebuilt index.
  await p.goBack(); await p.waitForTimeout(300); idx.length = 0;
  await p.evaluate(() => { const i = document.getElementById('ed-search-input'); i.value = ''; });
  await p.waitForTimeout(5200);
  await p.focus('#ed-search-input'); await p.keyboard.type('claude', { delay: 30 }); await p.waitForTimeout(600);
  r.statusAfterRebuild = idx.slice();
  r.rowsAfterRebuild = (await p.$$eval('.omx-suggest li', (l) => l.map((x) => x.textContent.trim()))).length;
  r.errors = errs; await b.close();
  r.pass = r.statusWhileWarming.includes(503) && r.statusWhileWarming.length <= 2 && r.enterRunsNormalSearch && r.statusAfterRebuild.includes(200) && r.rowsAfterRebuild > 0 && !errs.length;
  if (!r.pass) fail++; res[name] = r;
}
(async () => { await suite('chromium', pw.chromium); await suite('webkit', pw.webkit); console.log(JSON.stringify(res, null, 1)); console.log(fail ? `FAIL: ${fail}` : 'PASS: warming fallback'); process.exit(fail ? 1 : 0); })().catch((e) => { try { unlock(); } catch (_) {} console.error(e); process.exit(1); });
