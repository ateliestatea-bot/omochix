<?php
/**
 * Article Design v2.
 *
 * Opt-in: only posts whose body contains a real element with the class token `omx2-article` use it.
 *
 * Design rules (see docs/Article-Design-v2-Spec.md):
 *  - Nothing is ever written back. The stored post_content, the global $post and the post objects are never changed.
 *    The display-time correction is applied to the already split page text ($pages, which feeds get_the_content() and
 *    therefore the table of contents in single.php) and again, idempotently, through the_content.
 *  - This is NOT a sanitizer. Input safety stays with WordPress (kses at save time, capability unfiltered_html).
 *    On any internal failure the original text is returned untouched, which is exactly what WordPress would have
 *    printed without this feature, so a failure can never expose markup that was not already stored.
 *  - Legacy articles cost one stripos() and nothing else.
 *  - Markup is read by a small linear-time scanner (no backtracking regular expressions over the whole text), so
 *    unclosed tags, comments or attributes cannot make processing time grow quadratically.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) { exit; }

/** Bodies larger than this are treated as legacy (typical articles are 30-200 KB; see tests for measurements). */
const OMOCHIX_ARTICLE_V2_MAX_BYTES = 1048576;

/**
 * Upper bound for the number of tokens (tags and comments) the scanner will build. Every token is an array of roughly
 * 230 bytes, so without a bound 1 MB of tiny tags costs ~76 MB. The longest of the 100 real articles has 1,169 tokens
 * (52 KB); the limit leaves a ~17x margin and keeps the worst case near 5 MB.
 */
const OMOCHIX_ARTICLE_V2_MAX_TOKENS = 20000;

/** Values accepted by data-brand on the .omx2-article wrapper (CSS falls back to "generic" for anything else). */
function omochix_article_v2_brands() {
    return ['openai', 'anthropic', 'google', 'meta', 'nvidia', 'microsoft', 'mistral', 'xai', 'generic'];
}

/** preg_match wrapper: a regex engine failure is an exception, never a silent "no match". */
function omochix_article_v2_match($pattern, $subject, &$m, $offset) {
    $r = preg_match($pattern, $subject, $m, PREG_OFFSET_CAPTURE, $offset);
    if (false === $r) { throw new RuntimeException('pcre: ' . preg_last_error_msg()); }
    return 1 === $r;
}

/**
 * Split markup into tokens in a single left-to-right pass.
 * Token: [type, start, end, name]; type is c (comment), o (open tag), x (close tag) or r (raw/inert element: its whole
 * span, e.g. <pre>…</pre>, <script>…</script>). Open tokens also carry [4] = self-closing flag.
 * Text between tokens is implicit. Unterminated constructs are consumed once and never rescanned.
 * Throws (callers treat that as "do not touch this text") when the token limit is exceeded or when a raw element such
 * as <pre> or <script> is never closed: browsers and this scanner would disagree about everything after it.
 */
function omochix_article_v2_scan($html) {
    $len = strlen($html);
    $pos = 0;
    $tokens = [];
    $raw = ['script' => 1, 'style' => 1, 'textarea' => 1, 'template' => 1, 'noscript' => 1, 'pre' => 1];
    $open = '/<([a-zA-Z][a-zA-Z0-9:-]*+)((?:\s++|[^\s"\'<>\/=]++(?:\s*+=\s*+(?:"[^"]*+"|\'[^\']*+\'|[^\s"\'<>=`]++))?|\/(?!>))*+)(\/?)>/A';
    $close = '/<\/([a-zA-Z][a-zA-Z0-9:-]*+)[^>]*+>/A';
    while ($pos < $len) {
        if (count($tokens) >= OMOCHIX_ARTICLE_V2_MAX_TOKENS) { throw new RuntimeException('too many tokens'); }
        $lt = strpos($html, '<', $pos);
        if (false === $lt) { break; }
        if ('<!--' === substr($html, $lt, 4)) {
            $end = strpos($html, '-->', $lt + 4);
            $end = false === $end ? $len : $end + 3;
            $tokens[] = ['c', $lt, $end, ''];
            $pos = $end;
            continue;
        }
        $next = $html[$lt + 1] ?? '';
        if ('/' === $next) {
            if (omochix_article_v2_match($close, $html, $m, $lt)) {
                $tokens[] = ['x', $lt, $lt + strlen($m[0][0]), strtolower($m[1][0])];
                $pos = $lt + strlen($m[0][0]);
            } else { $pos = $lt + 1; }
            continue;
        }
        if (!ctype_alpha($next) || !omochix_article_v2_match($open, $html, $m, $lt)) { $pos = $lt + 1; continue; }
        $name = strtolower($m[1][0]);
        $end = $lt + strlen($m[0][0]);
        $self = '/' === $m[3][0];
        if (isset($raw[$name]) && !$self) {
            $from = $end;
            $stop = null;
            while (false !== ($c = stripos($html, '</' . $name, $from))) {
                $after = $html[$c + 2 + strlen($name)] ?? '>';
                if ('>' === $after || '/' === $after || ctype_space($after)) { $gt = strpos($html, '>', $c); $stop = false === $gt ? null : $gt + 1; break; }
                $from = $c + 2;
            }
            if (null === $stop) { throw new RuntimeException('unterminated <' . $name . '>'); }
            $tokens[] = ['r', $lt, $stop, $name];
            $pos = $stop;
            continue;
        }
        $tokens[] = ['o', $lt, $end, $name, $self];
        $pos = $end;
    }
    return $tokens;
}

/** Parse the attributes inside one open tag: [name(lowercase), value|null, start, end]; offsets relative to $tag. */
function omochix_article_v2_attrs($tag) {
    $out = [];
    $pos = 1;
    $len = strlen($tag);
    while ($pos < $len && !ctype_space($tag[$pos]) && '/' !== $tag[$pos] && '>' !== $tag[$pos]) { $pos++; } // skip "<name"
    $re = '/(?:\s++|\/)*+([^\s"\'<>\/=]++)(?:\s*+=\s*+(?:"([^"]*+)"|\'([^\']*+)\'|([^\s"\'<>=`]++)))?/A';
    while ($pos < $len && omochix_article_v2_match($re, $tag, $m, $pos)) {
        $value = null;
        foreach ([2, 3, 4] as $g) { if (isset($m[$g]) && $m[$g][1] >= 0) { $value = $m[$g][0]; break; } }
        $out[] = [strtolower($m[1][0]), $value, $pos, $pos + strlen($m[0][0])];
        $pos += strlen($m[0][0]);
    }
    return $out;
}

/** Class tokens of an open tag text. */
function omochix_article_v2_classes($tag) {
    foreach (omochix_article_v2_attrs($tag) as $a) {
        if ('class' === $a[0] && null !== $a[1]) { return preg_split('/\s+/', trim($a[1]), -1, PREG_SPLIT_NO_EMPTY); }
    }
    return [];
}

/** True when the HTML has a div/section/article element carrying the exact class token omx2-article. */
function omochix_article_v2_detect($html) {
    if (!is_string($html) || '' === $html || strlen($html) > OMOCHIX_ARTICLE_V2_MAX_BYTES || false === stripos($html, 'omx2-article')) { return false; }
    try {
        $code = 0; $svg = 0; $found = false;
        foreach (omochix_article_v2_scan($html) as $t) {
            if ('c' === $t[0] || 'r' === $t[0]) { continue; }
            if ('code' === $t[3]) { $code = max(0, $code + ('o' === $t[0] ? ($t[4] ? 0 : 1) : -1)); continue; }
            if ('svg' === $t[3]) { $svg = max(0, $svg + ('o' === $t[0] ? ($t[4] ? 0 : 1) : -1)); continue; }
            if ($found || 'o' !== $t[0] || $code > 0 || $svg > 0 || !in_array($t[3], ['div', 'section', 'article'], true)) { continue; }
            if (in_array('omx2-article', omochix_article_v2_classes(substr($html, $t[1], $t[2] - $t[1])), true)) { $found = true; }
        }
        return $found && 0 === $code; // an unclosed <code> hides the rest of the text from the correction: not v2
    } catch (Throwable $e) {
        return false; // fail safe: treat as legacy
    }
}

/** Whether a post uses v2 (posts only, cached for the request). */
function omochix_article_v2_is_v2_post($post = null) {
    static $cache = [];
    $post = get_post($post);
    if (!$post instanceof WP_Post || 'post' !== $post->post_type || !is_string($post->post_content) || false === stripos($post->post_content, 'omx2-article')) { return false; }
    $key = $post->ID . ':' . md5($post->post_content);
    if (!isset($cache[$key])) { $cache[$key] = omochix_article_v2_detect($post->post_content); }
    return $cache[$key];
}

/** Whether the current request is a single post rendered with v2. */
function omochix_article_v2_active() {
    return is_singular('post') && omochix_article_v2_is_v2_post(get_queried_object());
}

/**
 * Display-time correction of a v2 body: drop <style> blocks (the theme owns the styling), turn <h1> into <h2> (the page
 * already has one H1) and make every <h2> carry a unique, valid id so the table of contents works. Valid unique ids are
 * kept untouched and everything else is left byte for byte as it is. All-or-nothing: on any failure, or for text over
 * the size limit, the original is returned unchanged.
 */
function omochix_article_v2_prepare($html) {
    if (!is_string($html) || '' === $html || strlen($html) > OMOCHIX_ARTICLE_V2_MAX_BYTES) { return $html; }
    try {
        $tokens = omochix_article_v2_scan($html);
        $used = [];
        $code = 0;
        foreach ($tokens as $t) { // pass 1: ids of other elements are reserved so generated ids never collide
            if ('code' === $t[3]) { $code = max(0, $code + ('o' === $t[0] ? ($t[4] ? 0 : 1) : -1)); continue; }
            if ('o' !== $t[0] || $code > 0 || 'h1' === $t[3] || 'h2' === $t[3]) { continue; }
            foreach (omochix_article_v2_attrs(substr($html, $t[1], $t[2] - $t[1])) as $a) { if ('id' === $a[0] && null !== $a[1]) { $used[$a[1]] = true; } }
        }
        if ($code > 0) { return $html; } // unclosed <code>: browsers read the rest differently from this scanner, so touch nothing
        $out = '';
        $cursor = 0;
        $next = 1;
        $code = 0; $svg = 0; $h1 = 0;
        foreach ($tokens as $t) { // pass 2: edits, in document order
            [$type, $start, $end, $name] = $t;
            if ('code' === $name) { $code = max(0, $code + ('o' === $type ? ($t[4] ? 0 : 1) : -1)); continue; }
            if ('svg' === $name) { $svg = max(0, $svg + ('o' === $type ? ($t[4] ? 0 : 1) : -1)); continue; }
            if ($code > 0) { continue; }
            if ('r' === $type && 'style' === $name && 0 === $svg) { // the whole <style>…</style> block
                $out .= substr($html, $cursor, $start - $cursor);
                $cursor = $end;
            } elseif ('x' === $type && 'h1' === $name) {
                if ($h1 < 1) { continue; } // a closing tag without an opening <h1> is not ours to rewrite
                $h1--;
                $out .= substr($html, $cursor, $start - $cursor) . '</h2' . substr($html, $start + 4, $end - $start - 4);
                $cursor = $end;
            } elseif ('o' === $type && ('h1' === $name || 'h2' === $name)) {
                if ('h1' === $name) { $h1++; }
                $tag = substr($html, $start, $end - $start);
                $idAttr = null;
                foreach (omochix_article_v2_attrs($tag) as $a) { if ('id' === $a[0]) { $idAttr = $a; break; } }
                $id = $idAttr ? $idAttr[1] : null;
                $keep = null !== $id && 1 === preg_match('/^[A-Za-z][A-Za-z0-9_:.\-]*$/', $id) && !isset($used[$id]);
                if ($keep) { $used[$id] = true; }
                if ($keep && 'h2' === $name) { continue; } // already fine: untouched
                $rest = $tag;
                if (!$keep) {
                    do { $new = 'section-' . $next++; } while (isset($used[$new]));
                    $used[$new] = true;
                    if ($idAttr) { $rest = substr($tag, 0, $idAttr[2]) . substr($tag, $idAttr[3]); } // drop the old id (and the space before it)
                }
                $rest = substr($rest, 3);
                $out .= substr($html, $cursor, $start - $cursor) . '<h2' . ($keep ? '' : ' id="' . $new . '"') . $rest;
                $cursor = $end;
            }
        }
        return $out . substr($html, $cursor);
    } catch (Throwable $e) {
        return $html;
    }
}

/**
 * A natural lead sentence from the body: no labels, no markup, never from code blocks or styles. Inline code is kept.
 * HTML entities are deliberately NOT decoded (escaping stays the job of each output); they are returned as stored.
 */
function omochix_article_v2_lead($html, $max = 110) {
    if (!is_string($html) || '' === trim($html) || strlen($html) > OMOCHIX_ARTICLE_V2_MAX_BYTES) { return ''; }
    $norm = static function ($s) { return trim(str_replace('&nbsp;', ' ', (string) preg_replace('/[ \t\r\n\f]+/', ' ', $s))); };
    try {
        $void = ['br' => 1, 'img' => 1, 'hr' => 1, 'input' => 1, 'meta' => 1, 'link' => 1, 'wbr' => 1];
        $paras = []; $all = ''; $buf = null; $skipTag = null; $skipDepth = 0; $svg = 0; $pos = 0;
        $take = static function ($text) use (&$buf, &$all, &$skipDepth, &$svg) {
            if ($skipDepth > 0 || $svg > 0) { return; }
            if (null !== $buf) { $buf .= $text; }
            $all .= $text;
        };
        foreach (omochix_article_v2_scan($html) as $t) {
            [$type, $start, $end, $name] = $t;
            $take(substr($html, $pos, $start - $pos));
            $pos = $end;
            if ('c' === $type || 'r' === $type) { continue; }
            if ('svg' === $name) { $svg = max(0, $svg + ('o' === $type ? ($t[4] ? 0 : 1) : -1)); continue; }
            if ($skipDepth > 0) {
                if ($name === $skipTag && !isset($void[$name])) { $skipDepth += 'o' === $type ? ($t[4] ? 0 : 1) : -1; }
                continue;
            }
            if ('o' === $type && !isset($void[$name]) && !$t[4]) {
                foreach (omochix_article_v2_classes(substr($html, $start, $end - $start)) as $c) {
                    if (in_array($c, ['omx2-label', 'omx2-kicker', 'omx2-sources', 'omx2-cta', 'omx2-code'], true)) { $skipTag = $name; $skipDepth = 1; break; }
                }
                if ($skipDepth > 0) { continue; }
            }
            if ('p' === $name && 'o' === $type) { $buf = ''; }
            elseif ('p' === $name && 'x' === $type && null !== $buf) { $paras[] = $norm($buf); $buf = null; }
        }
        $take(substr($html, $pos));
        $text = '';
        foreach ($paras as $p) { if (mb_strlen($p) >= 30) { $text = $p; break; } }
        if ('' === $text) { $text = $norm($all); }
    } catch (Throwable $e) {
        return '';
    }
    if (mb_strlen($text) <= $max) { return $text; }
    $cut = mb_substr($text, 0, $max);
    $stop = mb_strrpos($cut, '。');
    if (false !== $stop && $stop >= 40) { return mb_substr($cut, 0, $stop + 1); }
    $cut = (string) preg_replace('/&[#A-Za-z0-9]*$/', '', $cut); // never end inside an entity
    return rtrim($cut) . '…';
}

// Display-time only. $pages is the runtime array behind get_the_content(); single.php builds its table of contents from
// get_the_content(), so correcting it keeps the TOC and the anchors in sync without touching any post object or the DB.
add_action('the_post', static function ($post = null, $query = null) {
    if (!$query instanceof WP_Query || !$query->is_main_query() || !is_singular('post') || !omochix_article_v2_is_v2_post($post)) { return; }
    global $pages;
    if (is_array($pages)) { foreach ($pages as $i => $page) { $pages[$i] = omochix_article_v2_prepare($page); } }
}, 10, 2);

// Same correction on the way out. Idempotent, so it is harmless when $pages was already corrected.
add_filter('the_content', static function ($content) {
    if (!in_the_loop() || !is_main_query() || !is_singular('post')) { return $content; }
    $post = get_post();
    if (!$post instanceof WP_Post || (int) $post->ID !== (int) get_queried_object_id() || !omochix_article_v2_is_v2_post($post)) { return $content; }
    return omochix_article_v2_prepare($content);
}, 9);

add_filter('body_class', static function ($classes) {
    if (omochix_article_v2_active()) { $classes[] = 'omx2-article-page'; }
    return $classes;
});

add_action('wp_enqueue_scripts', static function () {
    if (!omochix_article_v2_active()) { return; }
    wp_enqueue_style('omochix-article-v2', get_theme_file_uri('/assets/css/article-v2.css'), ['omochix-style'], (string) filemtime(get_theme_file_path('/assets/css/article-v2.css')));
}, 20);

// Prefer the editor's excerpt; without one, use a clean lead instead of WordPress' raw first words.
add_filter('get_the_excerpt', static function ($excerpt, $post = null) {
    $post = get_post($post);
    if (!$post instanceof WP_Post || '' !== trim((string) $post->post_excerpt) || !omochix_article_v2_is_v2_post($post)) { return $excerpt; }
    $lead = omochix_article_v2_lead($post->post_content);
    return '' !== $lead ? $lead : $excerpt;
}, 20, 2);
