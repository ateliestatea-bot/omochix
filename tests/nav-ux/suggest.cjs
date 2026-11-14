// Search-suggest QA (Chromium + WebKit): behaviour, a11y, XSS, request count, mobile, dark. Usage: node tests/nav-ux/suggest.cjs
const pw = require(process.env.PW_CORE || '/Users/omochi/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright-core');
const BASE = process.env.AFTER || 'http://127.0.0.1:8940'; const OUT = require('path').join(__dirname, '..', '..', 'docs', 'ux-expansion', 'screens'); require('fs').mkdirSync(OUT, { recursive: true });
const res = {};
async function suite(name, L) {
  const b = await L.launch(); const r = {};
  const ctx = await b.newContext({ viewport: { width: 1280, height: 800 } }); const p = await ctx.newPage(); const dialogs = []; p.on('dialog', (d) => { dialogs.push(d.message()); d.dismiss(); });
  const idxReq = []; p.on('request', (q) => { if (/suggest-index/.test(q.url())) idxReq.push(q.url()); }); const errs = []; p.on('pageerror', (e) => errs.push(String(e)));
  await p.goto(BASE + '/'); await p.waitForTimeout(300); r.requestsBeforeFocus = idxReq.length;
  await p.focus('#ed-search-input'); await p.waitForSelector('.omx-suggest:not([hidden])', { timeout: 4000 }).catch(() => {}); r.emptyQueryRows = await p.$$eval('.omx-suggest li', (l) => l.map((x) => x.textContent.trim()).slice(0, 8));
  const reqAfterFocus = idxReq.length; await p.keyboard.type('cl', { delay: 40 }); await p.waitForTimeout(150); await p.keyboard.type('aude', { delay: 40 }); await p.waitForTimeout(200);
  r.claudeRows = await p.$$eval('.omx-suggest li', (l) => l.map((x) => x.textContent.trim())); r.requestsAfterTyping = idxReq.length - reqAfterFocus;
  r.aria = await p.evaluate(() => { const i = document.getElementById('ed-search-input'); return { role: i.getAttribute('role'), expanded: i.getAttribute('aria-expanded'), controls: !!document.getElementById(i.getAttribute('aria-controls')), optionRoles: [...document.querySelectorAll('.omx-suggest li')].every((li) => li.getAttribute('role') === 'option') }; });
  await p.screenshot({ path: `${OUT}/suggest-${name}-desktop-light.png`, clip: { x: 150, y: 250, width: 1100, height: 450 } });
  await p.keyboard.press('ArrowDown'); r.activeAfterDown = await p.evaluate(() => document.getElementById(document.getElementById('ed-search-input').getAttribute('aria-activedescendant') || 'x')?.textContent.trim());
  await p.keyboard.press('Escape'); r.closedByEsc = await p.$$eval('.omx-suggest', (ls) => ls.every((l) => l.hidden));
  // katakana / hiragana folding + Japanese substring
  await p.fill('#ed-search-input', ''); await p.keyboard.type('ようやく'.slice(0, 0) + 'ようやく'); await p.waitForTimeout(250); r.hiraganaRows = await p.$$eval('.omx-suggest li', (l) => l.map((x) => x.textContent.trim()).slice(0, 4));   // matches 要約? (kanji not folded) -> may be empty: documented
  await p.fill('#ed-search-input', ''); await p.keyboard.type('プロンプト'); await p.waitForTimeout(250); r.jpRows = await p.$$eval('.omx-suggest li', (l) => l.map((x) => x.textContent.trim()).slice(0, 5));
  // XSS: markup typed into the box must stay text
  await p.fill('#ed-search-input', ''); await p.keyboard.type('<img src=x onerror=alert(1)>'); await p.waitForTimeout(250);
  r.xss = await p.evaluate(() => ({ injectedImg: !!document.querySelector('.omx-suggest img'), rowText: (document.querySelector('.omx-suggest li')||{}).textContent })); r.dialogsFired = dialogs.length;
  // Enter on the "search everything" row submits to /?s= ; Enter without selection submits normally
  await p.fill('#ed-search-input', 'Gemini'); await p.waitForTimeout(250); await Promise.all([p.waitForURL(/\?s=Gemini/, { timeout: 5000 }), p.keyboard.press('Enter')]); r.plainEnterGoesToSearch = /\?s=Gemini/.test(p.url());
  // header dialog
  await p.goto(BASE + '/learn/'); await p.click('[data-search-open]'); await p.keyboard.type('chat', { delay: 40 }); await p.waitForTimeout(300);
  r.dialogRows = await p.$$eval('dialog .omx-suggest li', (l) => l.map((x) => x.textContent.trim()).slice(0, 4)); r.dialogListInsideDialog = await p.$eval('dialog .omx-suggest', (l) => !l.hidden);
  await p.screenshot({ path: `${OUT}/suggest-${name}-dialog.png` }); await p.keyboard.press('Escape'); r.dialogStillOpenAfterFirstEsc = await p.$eval('dialog[data-search-dialog]', (d) => d.open); await p.keyboard.press('Escape');
  // no-JS safety: form still present with action
  r.errors = errs; await ctx.close();
  // mobile + dark
  const m = await b.newContext({ viewport: { width: 375, height: 800 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true }); const mp = await m.newPage(); await mp.addInitScript(() => { document.documentElement.setAttribute('data-theme', 'dark'); });
  await mp.goto(BASE + '/'); await mp.tap('#ed-search-input'); await mp.keyboard.type('claude'); await mp.waitForTimeout(400);
  r.mobile = await mp.evaluate(() => { const l = document.querySelector('.omx-suggest:not([hidden])') || document.querySelector('.omx-suggest'); const b = l.getBoundingClientRect(); return { visible: !l.hidden, inViewport: b.left >= 0 && b.right <= innerWidth, rows: l.querySelectorAll('li').length, minRowH: Math.min(...[...l.querySelectorAll('li a')].map((a) => Math.round(a.getBoundingClientRect().height))) }; });
  await mp.screenshot({ path: `${OUT}/suggest-${name}-mobile-dark.png` }); await m.close(); await b.close(); res[name] = r;
}
(async () => { await suite('chromium', pw.chromium); await suite('webkit', pw.webkit); console.log(JSON.stringify(res, null, 1)); require('fs').writeFileSync(OUT + '/../suggest-qa.json', JSON.stringify(res, null, 1)); })().catch((e) => { console.error(e); process.exit(1); });
