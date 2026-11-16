// D1 regression: search suggest must not take keys from an active IME composition (Chromium + WebKit). Firefox: firefox-suggest.cjs.
const pw = require(process.env.PW_CORE || '/Users/omochi/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright-core');
const CHECK = require('./ime-check.js'); const BASE = process.env.AFTER || 'http://127.0.0.1:8940';
(async () => { const out = {};
  for (const [n, L] of [['chromium', pw.chromium], ['webkit', pw.webkit]]) { const b = await L.launch(); const p = await (await b.newContext()).newPage();
    await p.goto(BASE + '/'); await p.waitForFunction(() => document.querySelector('#ed-search-input[role=combobox]'), null, { timeout: 8000 });
    out[n] = await p.evaluate(CHECK); await b.close(); }
  console.log(JSON.stringify(out, null, 1)); process.exit(Object.values(out).every((r) => r.pass) ? 0 : 1);
})();
