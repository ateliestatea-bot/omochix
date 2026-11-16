// SEO snapshot of key pages (title, description, canonical, robots, headings, JSON-LD types, internal links, 404 status). Dependency-free.
// Usage: node tests/nav-ux/seo-snapshot.mjs <label> [base]  -> docs/nav-ux-update/seo-<label>.json ; node ... diff before after
import fs from 'node:fs'; import path from 'node:path';
const here = path.dirname(new URL(import.meta.url).pathname); const out = (l) => path.join(here, '..', '..', 'docs', 'nav-ux-update', `seo-${l}.json`);
const PAGES = ['/', '/news/', '/ai-tools/', '/learn/', '/labs/', '/prompts/', '/category/ai-development/', '/about/', '/contact/', '/this-page-does-not-exist/'];
const strip = (s) => s.replace(/<[^>]+>/g, '').replace(/&#8211;/g, '–').replace(/\s+/g, ' ').trim();
if (process.argv[2] === 'diff') {
  const a = JSON.parse(fs.readFileSync(out('before'))), b = JSON.parse(fs.readFileSync(out('after'))); const rep = [];
  for (const p of Object.keys(a)) { const x = a[p], y = b[p]; const d = {};
    for (const k of ['status', 'title', 'description', 'canonical', 'robots', 'h1', 'ldTypes', 'ogTags']) if (JSON.stringify(x[k]) !== JSON.stringify(y[k])) d[k] = [x[k], y[k]];
    const h2a = x.h2, h2b = y.h2; if (JSON.stringify(h2a) !== JSON.stringify(h2b)) d.h2 = { removed: h2a.filter((h) => !h2b.includes(h)), added: h2b.filter((h) => !h2a.includes(h)) };
    const la = new Set(x.links), lb = new Set(y.links); const rl = [...la].filter((l) => !lb.has(l)), al = [...lb].filter((l) => !la.has(l)); if (rl.length || al.length) d.links = { removed: rl, added: al };
    rep.push({ path: p, changed: Object.keys(d).length ? d : 'identical' }); }
  console.log(JSON.stringify(rep, null, 1)); process.exit(0);
}
const label = process.argv[2] || 'after', BASE = process.argv[3] || 'http://127.0.0.1:8940'; const res = {};
for (const p of PAGES) {
  const r = await fetch(BASE + p, { redirect: 'manual' }); const h = await r.text();
  const m = (re) => (h.match(re) || [])[1] || null;
  res[p] = { status: r.status, title: strip(m(/<title>([\s\S]*?)<\/title>/) || ''), description: m(/<meta name="description" content="([^"]*)"/), canonical: (m(/<link rel="canonical" href="([^"]*)"/) || '').replace(BASE, '{base}') || null, robots: m(/<meta name="robots" content="([^"]*)"/),
    h1: [...h.matchAll(/<h1[^>]*>([\s\S]*?)<\/h1>/g)].map((x) => strip(x[1])), h2: [...h.matchAll(/<h2[^>]*>([\s\S]*?)<\/h2>/g)].map((x) => strip(x[1])),
    ldTypes: [...h.matchAll(/<script type="application\/ld\+json"[^>]*>([\s\S]*?)<\/script>/g)].flatMap((x) => { try { const j = JSON.parse(x[1]); const arr = Array.isArray(j) ? j : j['@graph'] || [j]; return arr.map((n) => n['@type']); } catch (e) { return ['INVALID_JSON']; } }),
    ogTags: [...h.matchAll(/<meta property="(og:[^"]+)" content="([^"]*)"/g)].map((x) => (x[1] + '=' + x[2]).replace(BASE, '{base}')).sort(),
    links: [...new Set([...h.matchAll(/<a [^>]*href="([^"#]+)"/g)].map((x) => x[1]).filter((u) => u.startsWith(BASE) || u.startsWith('/')).map((u) => u.replace(BASE, '')))].sort() };
}
fs.writeFileSync(out(label), JSON.stringify(res, null, 1)); console.log(label, Object.entries(res).map(([p, v]) => `${p} ${v.status} h1=${v.h1.length} ld=${v.ldTypes.join('|') || '-'}`).join('\n'));
