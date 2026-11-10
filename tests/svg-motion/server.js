// Test servers on 127.0.0.1 only. 8770 (or SVGM_PORT, when something else holds 8770): harness pages, theme assets, fixtures.
// 8771: canary, where any request is an outbound leak (fixed: the harness pages name it).
const http = require('http'), fs = require('fs'), path = require('path');
const repo = path.join(__dirname, '../..');
const PORT = Number(process.env.SVGM_PORT) || 8770;
const routes = { '/exported/': path.join(__dirname, 'results/exported'), '/pages/': path.join(__dirname, 'pages'), '/fixtures/': path.join(__dirname, 'fixtures'), '/theme/': path.join(repo, 'wp-theme/omochix-theme') };
const types = { '.html': 'text/html; charset=utf-8', '.js': 'text/javascript; charset=utf-8', '.json': 'application/json', '.css': 'text/css', '.svg': 'image/svg+xml' };
function start() {
  const results = path.join(__dirname, 'results'); fs.mkdirSync(results, { recursive: true });
  const main = http.createServer((q, s) => {
    const u = new URL(q.url, 'http://x');
    if (q.method === 'POST' && u.pathname === '/results') { let b = ''; q.on('data', d => b += d); q.on('end', () => { fs.writeFileSync(path.join(results, u.searchParams.get('name').replace(/[^\w.-]/g, '') + '.json'), b); s.end('ok'); }); return; }
    // Control for csp-tests.js only: the real measuring frame without its CSP (so a test can show the CSP is what blocks requests).
    if (u.pathname === '/control/measure-frame-nocsp.html') { const html = fs.readFileSync(path.join(routes['/theme/'], 'assets/svg-motion/measure-frame.html'), 'utf8').replace(/<meta http-equiv="Content-Security-Policy"[^>]*>\n?/, '').replace('../js/svg-motion/', '/theme/assets/js/svg-motion/'); s.setHeader('Content-Type', types['.html']); return s.end(html); }
    for (const pre in routes) if (u.pathname.startsWith(pre)) {
      const f = path.normalize(path.join(routes[pre], u.pathname.slice(pre.length)));
      if (!f.startsWith(routes[pre]) || !fs.existsSync(f) || fs.statSync(f).isDirectory()) break;
      s.setHeader('Content-Type', types[path.extname(f)] || 'text/plain');
      // MVP-4: the shortcode's folder is served as production should serve it (docs/motion/SVG-MOTION-MVP-4-RESULT.md):
      // opened on its own, an SVG there runs no script and loads nothing.
      if (u.pathname.startsWith('/exported/sc/uploads/') && f.endsWith('.svg')) { s.setHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox"); s.setHeader('X-Content-Type-Options', 'nosniff'); }
      return s.end(fs.readFileSync(f));
    }
    s.statusCode = 404; s.end();
  }).listen(PORT, '127.0.0.1');
  main.on('error', err => { console.error('harness server: ' + err.code + ' on 127.0.0.1:' + PORT + ' (set SVGM_PORT to another port)'); process.exit(2); });
  const hits = [];
  const canary = http.createServer((q, s) => {
    const u = new URL(q.url, 'http://x');
    if (u.pathname === '/__dump') { s.setHeader('Access-Control-Allow-Origin', '*'); s.setHeader('Content-Type', 'application/json'); return s.end(JSON.stringify(hits.filter(h => h.startsWith('/c/' + u.searchParams.get('run') + '/')))); }
    hits.push(u.pathname); s.setHeader('Access-Control-Allow-Origin', '*'); s.setHeader('Content-Type', 'image/png');
    s.end(Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==', 'base64'));
  });
  canary.on('upgrade', (q, sock) => { hits.push(new URL(q.url, 'http://x').pathname); sock.destroy(); });
  canary.listen(8771, '127.0.0.1');
  return () => { main.close(); canary.close(); };
}
if (require.main === module) { start(); console.log('http://127.0.0.1:' + PORT + '/pages/ (Ctrl+C to stop)'); }
module.exports = { start, PORT };
