// Test servers on 127.0.0.1 only. 8770: harness pages, theme assets, fixtures. 8771: canary, where any request is an outbound leak.
const http = require('http'), fs = require('fs'), path = require('path');
const repo = path.join(__dirname, '../..');
const routes = { '/exported/': path.join(__dirname, 'results/exported'), '/pages/': path.join(__dirname, 'pages'), '/fixtures/': path.join(__dirname, 'fixtures'), '/theme/': path.join(repo, 'wp-theme/omochix-theme') };
const types = { '.html': 'text/html; charset=utf-8', '.js': 'text/javascript; charset=utf-8', '.json': 'application/json', '.css': 'text/css', '.svg': 'image/svg+xml' };
function start() {
  const results = path.join(__dirname, 'results'); fs.mkdirSync(results, { recursive: true });
  const main = http.createServer((q, s) => {
    const u = new URL(q.url, 'http://x');
    if (q.method === 'POST' && u.pathname === '/results') { let b = ''; q.on('data', d => b += d); q.on('end', () => { fs.writeFileSync(path.join(results, u.searchParams.get('name').replace(/[^\w.-]/g, '') + '.json'), b); s.end('ok'); }); return; }
    for (const pre in routes) if (u.pathname.startsWith(pre)) {
      const f = path.normalize(path.join(routes[pre], u.pathname.slice(pre.length)));
      if (!f.startsWith(routes[pre]) || !fs.existsSync(f) || fs.statSync(f).isDirectory()) break;
      s.setHeader('Content-Type', types[path.extname(f)] || 'text/plain'); return s.end(fs.readFileSync(f));
    }
    s.statusCode = 404; s.end();
  }).listen(8770, '127.0.0.1');
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
if (require.main === module) { start(); console.log('http://127.0.0.1:8770/pages/ (Ctrl+C to stop)'); }
module.exports = { start };
