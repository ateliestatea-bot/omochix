<?php
/**
 * Design Library D1 — URL regression snapshot for a THROWAWAY local site.
 * Records status, content type, selected headers and a normalized body hash
 * for existing URLs, so a run before and after the Design Library change can
 * be compared. Local only: refuses any base that is not http://127.0.0.1:PORT.
 *
 *   DL_BASE=http://127.0.0.1:8961 php tests/design-library/regression-snapshot.php > before.json
 *   DL_BASE=... php tests/design-library/regression-snapshot.php --compare before.json
 */
$base = rtrim((string) getenv('DL_BASE'), '/');
if (!preg_match('#^http://127\.0\.0\.1:\d+$#', $base)) { fwrite(STDERR, "DL_BASE must be http://127.0.0.1:PORT\n"); exit(2); }
$theme = dirname(__DIR__, 2) . '/wp-theme/omochix-theme';
$paths = ['/', '/learn/', '/learn/motion/', '/learn/motion/compose/', '/learn/svg-motion/', '/learn/industries/', '/labs/',
    '/ai-tools/', '/ai-tools/chatgpt/', '/prompts/', '/prompts/prompt-1/', '/category/design/', '/design-post-1/', '/news/', '/about/',
    '/contact/', '/privacy-policy/', '/terms/', '/company/', '/sitemap.xml', '/robots.txt', '/wp-json/', '/feed/', '/no-such-page-xyz/',
    '/learn/motion/no-such-sample/', '/labs/no-such-tool/', '/learn/industries/real-estate/', '/?s=AI', '/ai-tools/category/design/'];
foreach (json_decode((string) file_get_contents($theme . '/assets/data/motion/catalog.json'), true)['items'] as $it) { $paths[] = '/learn/motion/' . $it['slug'] . '/'; }
foreach (['json-formatter', 'text-diff', 'robots-txt-generator', 'token-cost-calculator'] as $t) { $paths[] = '/labs/' . $t . '/'; }
function fetch($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 60]);
    $raw = (string) curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $h = [];
    foreach (explode("\r\n", substr($raw, 0, $hs)) as $line) { if (strpos($line, ':')) { [$k, $v] = explode(':', $line, 2); $h[strtolower(trim($k))][] = trim($v); } }
    return [$code, $h, substr($raw, $hs)];
}
function normalize($body) {
    $body = preg_replace('/(nonce|_wpnonce|"nonce")(["\'=:\s]+)[0-9a-f]{10}/i', '$1$2N', $body);
    $body = preg_replace('/(name="[^"]*nonce[^"]*" value=")[0-9a-f]{10}/i', '$1N', $body); // form nonces rotate every 12 hours
    $body = preg_replace('/\?ver=[0-9a-z.\-]+/i', '?ver=V', $body);
    $body = preg_replace('/<lastmod>[^<]*<\/lastmod>/', '', $body);
    $body = preg_replace('/\b\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}\b/', 'T', $body);
    return $body;
}
$out = [];
foreach ($paths as $p) {
    [$code, $h, $body] = fetch($base . $p);
    $keep = [];
    foreach (['content-type', 'content-security-policy', 'content-security-policy-report-only', 'x-robots-tag', 'x-frame-options', 'location'] as $k) { if (isset($h[$k])) { $keep[$k] = implode(' | ', $h[$k]); } }
    $out[$p] = ['status' => $code, 'headers' => $keep, 'sha256' => hash('sha256', normalize($body)), 'design' => substr_count($body, 'omochix-design') + substr_count($body, '/design/')];
}
if (($argv[1] ?? '') === '--compare') {
    $before = json_decode((string) file_get_contents($argv[2]), true); $diff = 0;
    foreach ($before as $p => $b) {
        $a = $out[$p] ?? null;
        if (!$a || $a['status'] !== $b['status'] || $a['headers'] != $b['headers'] || $a['sha256'] !== $b['sha256']) { $diff++; echo "DIFF $p  before=" . json_encode($b) . "\n      after=" . json_encode($a) . "\n"; }
    }
    echo 'compared ' . count($before) . ' URLs, ' . $diff . " differ\n"; exit($diff ? 1 : 0);
}
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
