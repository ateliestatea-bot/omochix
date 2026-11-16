// Real Firefox (Firefox.app, headless, Marionette protocol - no extra libraries) pass for the navigation update.
// Usage: node tests/nav-ux/firefox.cjs   (AFTER=http://127.0.0.1:8940)
const net = require('net'), fs = require('fs'), os = require('os'), path = require('path'), { spawn } = require('child_process');
const AFTER = process.env.AFTER || 'http://127.0.0.1:8940'; const FF = '/Applications/Firefox.app/Contents/MacOS/firefox';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
class Marionette {
  constructor(port) { this.port = port; this.id = 0; this.buf = ''; this.waiters = new Map(); }
  connect() { return new Promise((res, rej) => { this.s = net.connect(this.port, '127.0.0.1'); let greeted = false; this.buf = Buffer.alloc(0);
    this.s.on('error', rej); this.s.on('data', (d) => { this.buf = Buffer.concat([this.buf, d]); for (;;) { const i = this.buf.indexOf(0x3a); if (i < 0) return; const n = +this.buf.slice(0, i).toString(); if (this.buf.length < i + 1 + n) return;   // length prefix is in BYTES (UTF-8)
      const msg = JSON.parse(this.buf.slice(i + 1, i + 1 + n).toString('utf8')); this.buf = this.buf.slice(i + 1 + n);
      if (!greeted) { greeted = true; res(); continue; } const w = this.waiters.get(msg[1]); if (w) { this.waiters.delete(msg[1]); msg[2] ? w.rej(new Error(JSON.stringify(msg[2]))) : w.res(msg[3]); } } }); }); }
  cmd(name, params = {}) { const id = ++this.id; const body = JSON.stringify([0, id, name, params]); return new Promise((res, rej) => { this.waiters.set(id, { res, rej }); this.s.write(Buffer.byteLength(body) + ':' + body); }); }
  async js(script, args = []) { const r = await this.cmd('WebDriver:ExecuteAsyncScript', { script: `const done=arguments[arguments.length-1];(async()=>{try{done({ok:await (async()=>{${script}})()})}catch(e){done({err:String(e)})}})()`, args, newSandbox: false }); if (r.value.err) throw new Error(r.value.err); return r.value.ok; }
  async go(url) { await this.cmd('WebDriver:Navigate', { url }); }
}

async function session(prefs, width, height) {
  const prof = fs.mkdtempSync(path.join(os.tmpdir(), 'ffprof-'));
  fs.writeFileSync(path.join(prof, 'user.js'), Object.entries(prefs).map(([k, v]) => `user_pref("${k}", ${JSON.stringify(v)});`).join('\n'));
  const port = 2828 + Math.floor(Math.random() * 500);
  fs.appendFileSync(path.join(prof, 'user.js'), `\nuser_pref("marionette.port", ${port});`);
  const p = spawn(FF, ['--headless', '--marionette', '--no-remote', '-profile', prof], { stdio: 'ignore' });
  let m; for (let i = 0; i < 60; i++) { try { m = new Marionette(port); await m.connect(); break; } catch (e) { m = null; await sleep(500); } }
  if (!m) { p.kill(); throw new Error('Marionette connect failed'); }
  await m.cmd('WebDriver:NewSession', { capabilities: {} });
  await m.cmd('WebDriver:SetWindowRect', { width, height });
  return { m, close: async () => { try { await m.cmd('Marionette:Quit'); } catch (e) {} p.kill(); } };
}



(async () => {
  const out = {}; const { m, close } = await session({}, 1280, 900);
  await m.go(AFTER + '/'); await sleep(2500);   // the suggest script loads after window "load"
  out.scriptLoaded = await m.js("return typeof window.omochixSuggest === 'object' && !!document.querySelector('#ed-search-input[role=combobox]')");
  await m.js("document.getElementById('ed-search-input').focus();return 1"); await sleep(900);
  await m.js("const i=document.getElementById('ed-search-input');i.value='claude';i.dispatchEvent(new Event('input',{bubbles:true}));return 1"); await sleep(500);
  out.rows = await m.js("return [...document.querySelectorAll('.omx-suggest:not([hidden]) li')].map(l=>l.textContent.trim())");
  out.expanded = await m.js("return document.getElementById('ed-search-input').getAttribute('aria-expanded')");
  await m.js("const i=document.getElementById('ed-search-input');i.value='<img src=x onerror=alert(1)>';i.dispatchEvent(new Event('input',{bubbles:true}));return 1"); await sleep(400);
  out.xssInjected = await m.js("return !!document.querySelector('.omx-suggest img')");
  out.ime = await m.js('return await ' + require('./ime-check.js'));
  await m.go(AFTER + '/?s=json'); await sleep(500); out.relatedLabs = await m.js("return [...document.querySelectorAll('.search-related__list a')].map(a=>a.textContent.trim())");
  for (const u of ['/page/2/', '/learn/page/2/', '/about/page/7/']) { await m.go(AFTER + u); (out.paginationRedirects = out.paginationRedirects || {})[u] = await m.js('return location.pathname'); }
  await close(); console.log(JSON.stringify(out, null, 1));
})().catch((e) => { console.error('FAIL', e); process.exit(1); });
