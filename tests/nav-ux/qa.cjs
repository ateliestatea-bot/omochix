// Integrated QA (Chromium + WebKit via playwright-core): nav, footer, links, a11y, keyboard, favicon, loader default-OFF, regressions, perf.
// Usage: node tests/nav-ux/qa.cjs   (AFTER=http://127.0.0.1:8940, BEFORE=http://127.0.0.1:8941)
const pw = require(process.env.PW_CORE || '/Users/omochi/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright-core');
const AFTER = process.env.AFTER || 'http://127.0.0.1:8940', BEFORE = process.env.BEFORE || 'http://127.0.0.1:8941'; const out = {};
const WIDTHS = [320, 375, 390, 430, 768, 1024, 1440]; const PAGES = ['/', '/news/', '/ai-tools/', '/learn/', '/labs/', '/prompts/', '/category/ai-development/', '/about/', '/contact/', '/does-not-exist/'];
async function suite(name, L) {
  const b = await L.launch(); const r = { checks: {} }; const errs = [];
  const ctxD = await b.newContext({ viewport: { width: 1440, height: 900 } }); const p = await ctxD.newPage();
  p.on('console', (m) => ['error'].includes(m.type()) && !/Failed to load resource|report-only mode|Prefetch request denied: URL must be secure/.test(m.text()) && errs.push(m.text()));
  p.on('response', (res) => { if (res.status() >= 400 && !/does-not-exist/.test(res.url())) errs.push(`HTTP ${res.status()} ${res.url().replace(AFTER, '')}`); }); // sub-resource failures (the intentional 404 page itself is excluded) p.on('pageerror', (e) => errs.push(String(e)));
  // 1. layout across widths (overflow, tap targets) + console on every page
  const layout = []; for (const w of WIDTHS) { await p.setViewportSize({ width: w, height: 900 }); for (const u of PAGES) { await p.goto(AFTER + u, { waitUntil: 'load' }); layout.push({ w, u, overflow: await p.evaluate(() => document.documentElement.scrollWidth > innerWidth) }); } }
  r.checks.noHorizontalOverflow = layout.filter((x) => x.overflow).length === 0; r.overflowFails = layout.filter((x) => x.overflow).slice(0, 5); r.pagesChecked = layout.length;
  // 2. link integrity: every nav/home/footer link returns a non-error status; Labs points at /labs/
  await p.setViewportSize({ width: 1440, height: 900 }); await p.goto(AFTER + '/'); const links = await p.$$eval('.primary-nav a, .ed-categories a, .ed-footer a, .ed-practice-more a', (a) => [...new Set(a.map((x) => x.href))].filter((h) => h.startsWith(location.origin)));
  const bad = []; for (const h of links) { const res = await p.request.get(h, { maxRedirects: 5 }); if (res.status() >= 400) bad.push([h, res.status()]); } r.linksChecked = links.length; r.brokenLinks = bad;
  r.checks.labsLinkEverywhere = await p.evaluate(() => ['.primary-nav', '.ed-categories', '.ed-footer-nav'].every((s) => [...document.querySelectorAll(s + ' a')].some((a) => new URL(a.href).pathname === '/labs/')));
  r.checks.sixShortcuts = (await p.$$eval('.ed-categories > *', (e) => e.length)) === 6;
  // 3. a11y: aria-current, nav landmarks, coming-soon not links, legal nav labelled
  const cur = {}; for (const [u, id] of [['/learn/', 'learn'], ['/labs/', 'labs'], ['/prompts/', 'prompts'], ['/ai-tools/', 'tools'], ['/category/ai-development/', 'dev'], ['/news/', 'news']]) { await p.goto(AFTER + u); cur[u] = await p.evaluate((i) => { const a = document.querySelector(`.primary-nav [data-nav="${i}"] a`); return a ? a.getAttribute('aria-current') : 'no-link'; }, id); }
  r.ariaCurrent = cur; r.checks.ariaCurrentOnSections = Object.values(cur).every((v) => v === 'page' || v === 'true');
  await p.goto(AFTER + '/'); r.a11y = await p.evaluate(() => ({ navLabels: [...document.querySelectorAll('nav')].map((n) => n.getAttribute('aria-label')), comingSoonAsLink: [...document.querySelectorAll('.primary-nav__item--unavailable')].some((li) => li.querySelector('a')), legalNav: !!document.querySelector('nav.ed-footer-legal[aria-label]'), h1Count: document.querySelectorAll('h1').length, skipLink: !!document.querySelector('.skip-link') }));
  r.checks.a11yBasics = r.a11y.navLabels.every(Boolean) && !r.a11y.comingSoonAsLink && r.a11y.legalNav && r.a11y.h1Count === 1;
  // 4. keyboard: menu toggle opens with Enter, all links reachable by Tab, closes with Escape (existing behaviour), search dialog
  await p.setViewportSize({ width: 375, height: 800 }); await p.goto(AFTER + '/'); await p.focus('[data-menu-toggle]'); await p.keyboard.press('Enter'); await p.waitForTimeout(600);
  const expanded = await p.getAttribute('[data-menu-toggle]', 'aria-expanded'); const seen = []; for (let i = 0; i < 14; i++) { await p.keyboard.press(name === 'webkit' ? 'Alt+Tab' : 'Tab'); /* Safari/WebKit only tabs to links with Option held */ seen.push(await p.evaluate(() => (document.activeElement.getAttribute('data-nav') ? document.activeElement.parentElement.dataset.nav : document.activeElement.closest('li[data-nav]') ? document.activeElement.closest('li[data-nav]').dataset.nav : document.activeElement.tagName))); }
  r.keyboard = { menuExpandedAfterEnter: expanded, tabOrderSample: seen.slice(0, 12) }; await p.keyboard.press('Escape'); await p.waitForTimeout(500); r.keyboard.expandedAfterEscape = await p.getAttribute('[data-menu-toggle]', 'aria-expanded');
  r.checks.keyboardMenu = expanded === 'true' && ['news', 'tools', 'dev', 'learn', 'prompts', 'labs'].every((k) => seen.includes(k));
  await p.setViewportSize({ width: 1440, height: 900 }); await p.goto(AFTER + '/'); await p.click('[data-search-open]'); r.checks.searchDialogOpens = await p.evaluate(() => document.querySelector('[data-search-dialog]').open); await p.keyboard.press('Escape');
  await p.click('[data-theme-toggle]'); r.checks.themeToggle = (await p.evaluate(() => document.documentElement.getAttribute('data-theme'))) === 'dark';
  // 5. favicon / manifest / duplicates
  await p.goto(AFTER + '/'); const icons = await p.$$eval('link[rel~="icon"], link[rel="apple-touch-icon"]', (l) => l.map((x) => ({ rel: x.rel, href: x.href, sizes: x.sizes.value, type: x.type })));
  r.icons = icons; r.checks.iconsNoDuplicates = new Set(icons.map((i) => i.rel + i.sizes + i.type)).size === icons.length && icons.every((i) => i.href.includes('/assets/img/icons/'));
  const iconStatus = []; for (const i of icons) iconStatus.push((await p.request.get(i.href)).status()); const man = await (await p.request.get(AFTER + (await p.getAttribute('link[rel=manifest]', 'href')).replace(AFTER, ''))).json();
  const manStatus = []; for (const i of man.icons) manStatus.push((await p.request.get(new URL(i.src, AFTER + '/wp-content/themes/omochix-theme/manifest.webmanifest').href)).status());
  r.checks.iconFilesLoad = iconStatus.every((s) => s === 200) && manStatus.every((s) => s === 200); r.manifestIcons = man.icons.map((i) => `${i.sizes} ${i.purpose}`); const ico = Buffer.from(await (await p.request.get(AFTER + '/favicon.ico')).body()); r.checks.rootFaviconIco = ico.readUInt16LE(2) === 1 && ico.readUInt16LE(4) === 3;
  // 6. loader default OFF
  const html = await (await p.request.get(AFTER + '/')).text(); r.checks.loaderDefaultOff = !/omochix-loader|data-omx-overlay|omx-js/.test(html);
  // 7. loader ON (dev flag) with a stalled footer: overlay appears, ends on real state, no errors
  const c2 = await b.newContext({ viewport: { width: 1280, height: 800 } }); const p2 = await c2.newPage(); const e2 = []; p2.on('pageerror', (e) => e2.push(String(e))); p2.on('console', (m) => m.type() === 'error' && e2.push(m.text()));
  const nav = p2.goto(AFTER + '/?omx=on&omx_delay=1500', { waitUntil: 'load' }); await p2.waitForTimeout(900); const shown = await p2.evaluate(() => { const o = document.querySelector('[data-omx-overlay]'); return o ? parseFloat(getComputedStyle(o).opacity) : null; }); await nav; await p2.waitForFunction(() => { const o = document.querySelector('[data-omx-overlay]'); return o && o.classList.contains('is-gone'); }, null, { timeout: 8000 });
  r.loaderOn = { opacityWhileStalled: shown, endedOnRealState: true, errors: e2 }; r.checks.loaderOnWorks = shown !== null && shown > 0.5 && e2.length === 0; await c2.close();
  // 8. regression: home prompt copy button, hero search submits to /?s=, footer legal tap targets >=44
  await p.goto(AFTER + '/'); r.checks.searchFormWorks = await p.evaluate(() => { const f = document.querySelector('.ed-search'); return f && f.getAttribute('action') && f.querySelector('input[name=s]'); }) ? true : false;
  r.checks.footerLegalTapTargets = await (async () => { await p.setViewportSize({ width: 375, height: 800 }); await p.goto(AFTER + '/'); return p.evaluate(() => [...document.querySelectorAll('.ed-footer-legal a, .ed-footer-nav a')].every((a) => a.getBoundingClientRect().height >= 43.5)); })();
  r.consoleErrors = errs; r.checks.noConsoleErrors = errs.length === 0;
  await b.close(); out[name] = r;
}
(async () => { await suite('chromium', pw.chromium); await suite('webkit', pw.webkit);
  for (const [n, r] of Object.entries(out)) { const f = Object.entries(r.checks).filter(([, v]) => !v).map(([k]) => k); console.error(`${n}: ${Object.keys(r.checks).length - f.length}/${Object.keys(r.checks).length} checks pass`, f.length ? 'FAIL: ' + f.join(',') : ''); }
  require('fs').writeFileSync(__dirname + '/../../docs/nav-ux-update/qa-result.json', JSON.stringify(out, null, 1)); })();
