<?php
/**
 * [omochix_logo_motion name="…" alt="…" size="…"]: shows a logo motion exported by SVG Motion Studio.
 *
 * The two files ({name}.svg and {name}-static.svg) are placed by an administrator in
 * wp-content/uploads/omochix-logo-motion/; nothing can be uploaded or named from a post. The post stores only
 * the shortcode, so wp_kses_post() never sees the markup: every role gets the same <picture>, built here.
 *
 * Both files are checked before they are shown, with the rules of the studio's own export check
 * (assets/js/svg-motion/safety.js and export.js): the SVG element and attribute allow lists, local references
 * only, scoped generated CSS, and a matching pair. They are shown as images only, never inline.
 *
 * Fail-closed (MVP-4): UTF-8 only; one root element and nothing else (no document type, entity, processing
 * instruction, comment or CDATA anywhere); the generated CSS must match the exact grammar the studio writes; each
 * file is read once through one handle whose identity is checked against the path; ?v= comes from the contents.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

const OMOCHIX_LOGO_MOTION_DIR      = 'omochix-logo-motion';
const OMOCHIX_LOGO_MOTION_MAX      = 1048576;
const OMOCHIX_LOGO_MOTION_ELEMENTS = 5000;
const OMOCHIX_LOGO_MOTION_DEPTH    = 32;
const OMOCHIX_LOGO_MOTION_PATH     = 524288;
const OMOCHIX_LOGO_MOTION_SVG_NS   = 'http://www.w3.org/2000/svg';

/** Same lists as assets/js/svg-motion/safety.js (ELEMENTS, ATTRIBUTES) and frame.js (EXTRA_ATTRIBUTE). */
function omochix_logo_motion_rules() {
    static $rules = null;
    if (null === $rules) {
        $rules = [
            'elements'   => array_flip([
                'svg', 'g', 'defs', 'symbol', 'title', 'desc', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
                'text', 'tspan', 'linearGradient', 'radialGradient', 'stop', 'pattern', 'clipPath', 'mask', 'marker', 'image', 'style',
                'filter', 'feGaussianBlur', 'feOffset', 'feBlend', 'feColorMatrix', 'feComposite', 'feFlood', 'feMerge', 'feMergeNode',
                'feDropShadow', 'feComponentTransfer', 'feFuncR', 'feFuncG', 'feFuncB', 'feFuncA', 'feMorphology', 'feTurbulence',
                'feDisplacementMap',
            ]),
            'attributes' => array_flip([
                'id', 'class', 'viewBox', 'preserveAspectRatio', 'width', 'height', 'x', 'y', 'x1', 'y1', 'x2', 'y2',
                'cx', 'cy', 'r', 'rx', 'ry', 'fx', 'fy', 'fr', 'd', 'points', 'transform', 'overflow',
                'fill', 'fill-rule', 'fill-opacity', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin',
                'stroke-miterlimit', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-opacity', 'opacity', 'color',
                'clip-path', 'clip-rule', 'clipPathUnits', 'mask', 'maskUnits', 'maskContentUnits',
                'filter', 'filterUnits', 'primitiveUnits', 'marker-start', 'marker-mid', 'marker-end',
                'markerWidth', 'markerHeight', 'markerUnits', 'refX', 'refY', 'orient',
                'patternUnits', 'patternContentUnits', 'patternTransform', 'gradientUnits', 'gradientTransform',
                'spreadMethod', 'offset', 'stop-color', 'stop-opacity',
                'font-family', 'font-size', 'font-weight', 'font-style', 'text-anchor', 'dominant-baseline',
                'letter-spacing', 'dx', 'dy', 'rotate', 'textLength', 'lengthAdjust',
                'in', 'in2', 'result', 'stdDeviation', 'mode', 'type', 'values', 'operator', 'k1', 'k2', 'k3', 'k4',
                'flood-color', 'flood-opacity', 'tableValues', 'slope', 'intercept', 'amplitude', 'exponent',
                'baseFrequency', 'numOctaves', 'seed', 'stitchTiles', 'scale', 'xChannelSelector', 'yChannelSelector', 'radius',
                'href', 'xlink:href', 'style', 'visibility', 'display', 'mix-blend-mode', 'paint-order', 'vector-effect',
                'shape-rendering', 'pathLength', 'xmlns', 'xmlns:xlink', 'version', 'role', 'aria-label', 'aria-hidden', 'xml:space',
            ]),
        ];
    }
    return $rules;
}

/** safety.js isSafeValue(): every url() is url(#id), nothing that can fetch or escape. */
function omochix_logo_motion_safe_value($value) {
    $value = (string) $value;
    if (preg_match('/[\\\\<>]|\/\*/', $value) || preg_match('/(?:image-set|cross-fade|element\(|paint\(|src\(|var\(|expression|@import|javascript:|vbscript:|data:)/i', $value)) {
        return false;
    }
    return preg_match_all('/url\s*\(/i', $value) === preg_match_all('/url\(\s*(["\']?)#[A-Za-z_][\w.:-]*\1\s*\)/i', $value);
}

/**
 * UTF-8 only, as the studio writes it: no byte order mark, no NUL, valid UTF-8, and an XML declaration (if any) that
 * names UTF-8. Anything else is refused before a parser sees it, so the checks below read the same text the browser does.
 */
function omochix_logo_motion_encoding_ok($text) {
    if (false !== strpos($text, "\0") || 0 === strncmp($text, "\xEF\xBB\xBF", 3) || 0 === strncmp($text, "\xFE\xFF", 2) || 0 === strncmp($text, "\xFF\xFE", 2)) {
        return false;
    }
    if (!preg_match('//u', $text) || '<' !== substr(ltrim($text), 0, 1)) {
        return false;
    }
    if (preg_match('/^\s*<\?xml\s([^?]*)\?>/', $text, $declaration) && preg_match('/\bencoding\s*=\s*["\']([^"\']*)["\']/i', $declaration[1], $encoding)) {
        return 'utf-8' === strtolower($encoding[1]);
    }
    return true;
}

/**
 * The generated style, checked against the exact grammar compile.js planToCss() writes (MVP-4): header rules,
 * @keyframes {scope}-k{n}, one @media (prefers-reduced-motion:reduce) block, selectors limited to this logo's own
 * [data-omx-*] hooks, and a fixed list of properties with fixed value shapes. Anything else is refused.
 */
function omochix_logo_motion_generated_css_ok($css, $scope) {
    $s    = preg_quote($scope, '/');
    $n    = '-?(?:\d+(?:\.\d*)?|\.\d+)(?:e-?\d+)?';
    $hex  = '#[0-9a-fA-F]{3,8}';
    $ease = '(?:linear|ease|ease-in|ease-out|ease-in-out|cubic-bezier\(' . $n . ',' . $n . ',' . $n . ',' . $n . '\))';
    $one  = $s . '-k\d{1,4} ' . $n . 'ms ' . $ease . ' ' . $n . 'ms (?:infinite|' . $n . ') (?:normal|alternate) both';
    $fn   = '(?:translate\(' . $n . 'px,' . $n . 'px\)|translate[XY]\(' . $n . 'px\)|scale\(' . $n . '(?:,' . $n . ')?\)|rotate[XY]?\(' . $n . 'deg\)|perspective\(' . $n . 'px\))';
    $values = [
        'opacity' => $n, 'fill-opacity' => $n, 'stroke-opacity' => $n, 'stroke-width' => $n, 'stroke-dashoffset' => $n,
        'stroke-dasharray' => $n . '(?: ' . $n . '){0,3}',
        'fill' => $hex, 'stroke' => $hex,
        'transform-box' => 'fill-box|view-box',
        'transform-origin' => 'center|' . $n . 'px ' . $n . 'px',
        'translate' => $n . 'px ' . $n . 'px', 'rotate' => $n . 'deg', 'scale' => $n . '(?: ' . $n . ')?',
        'transform' => 'none|' . $fn . '(?: ' . $fn . '){0,3}',
        'filter' => 'none|blur\(' . $n . 'px\)|drop-shadow\(' . $n . 'px ' . $n . 'px ' . $n . 'px ' . $hex . '\)',
        'clip-path' => 'inset\(' . $n . '% ' . $n . '% ' . $n . '% ' . $n . '%\)',
        'animation' => 'none|' . $one . '(?:,' . $one . '){0,15}',
    ];
    $selector = '(?:\.' . $s . ' \[data-omx-(?:root|part|geometry|base|overlay)(?:="\d{1,5}")?\]|svg\.' . $s . ')';
    $decls_ok = function ($body) use ($values) {
        $body = rtrim($body, ';');
        if ('' === $body) {
            return true;
        }
        foreach (explode(';', $body) as $decl) {
            $colon = strpos($decl, ':');
            $name  = false === $colon ? '' : substr($decl, 0, $colon);
            if (!isset($values[$name]) || !preg_match('/^(?:' . $values[$name] . ')$/', substr($decl, $colon + 1))) {
                return false;
            }
        }
        return true;
    };
    $selectors_ok = function ($list) use ($selector) {
        foreach (explode(',', $list) as $part) {
            if (!preg_match('/^' . $selector . '$/', $part)) {
                return false;
            }
        }
        return true;
    };
    $media    = '@media (prefers-reduced-motion:reduce){';
    $frame    = '(?:\d{1,3}(?:\.\d+)?%|from|to)';
    $pos      = 0;
    $len      = strlen($css);
    $in_media = false;
    while ($pos < $len) {
        if ($in_media && '}' === $css[$pos]) {
            $in_media = false;
            $pos++;
        } elseif (!$in_media && preg_match('/\G@keyframes ' . $s . '-k\d{1,4}\{/', $css, $m, 0, $pos)) {
            $pos += strlen($m[0]);
            while ($pos < $len && '}' !== $css[$pos]) {
                if (!preg_match('/\G(' . $frame . '(?:,' . $frame . ')*)\{([^{}]*)\}/', $css, $m, 0, $pos) || !$decls_ok($m[2])) {
                    return false;
                }
                $pos += strlen($m[0]);
            }
            if ($pos >= $len) {
                return false;
            }
            $pos++;
        } elseif (!$in_media && substr($css, $pos, strlen($media)) === $media) {
            $in_media = true;
            $pos     += strlen($media);
        } elseif (preg_match('/\G([^{}@]+)\{([^{}]*)\}/', $css, $m, 0, $pos) && $selectors_ok($m[1]) && $decls_ok($m[2])) {
            $pos += strlen($m[0]);
        } else {
            return false;
        }
    }
    return !$in_media;
}

/** True when every rule of a logo's own style is plain and limited to this logo (export.js: "style:scope"). */
function omochix_logo_motion_scoped_css($css, $scope) {
    if (false !== strpos($css, '@') || !omochix_logo_motion_safe_value($css)) {
        return false;
    }
    $rest = preg_replace('/[^{}]+\{[^{}]*\}/', '', $css);
    if ('' !== trim((string) $rest) || !preg_match_all('/([^{}]+)\{[^{}]*\}/', $css, $rules)) {
        return '' === trim($css);
    }
    foreach ($rules[1] as $selectors) {
        foreach (explode(',', $selectors) as $part) {
            $part = trim($part);
            if (0 !== strpos($part, '.' . $scope . ' ') && 0 !== strpos($part, 'svg.' . $scope)) {
                return false;
            }
        }
    }
    return true;
}

/**
 * Problems in one exported SVG file; empty when it may be shown.
 *
 * @param string $text
 * @return array{problems: string[], scope: string, viewBox: string, animated: bool}
 */
function omochix_logo_motion_check_svg($text) {
    $out = ['problems' => [], 'scope' => '', 'viewBox' => '', 'animated' => false];
    if ('' === $text || strlen($text) > OMOCHIX_LOGO_MOTION_MAX) {
        $out['problems'][] = 'size';
        return $out;
    }
    if (!omochix_logo_motion_encoding_ok($text)) {
        $out['problems'][] = 'encoding';
        return $out;
    }
    // No document type, entities, processing instructions, comments or CDATA: an export never has them. Checked on
    // the text (now known to be UTF-8) and again on the parsed structure below.
    foreach (['doctype' => '/<!DOCTYPE|<!ENTITY/i', 'pi' => '/<\?(?!xml\s)|<\?xml\s.*<\?/s', 'comment' => '/<!--/', 'cdata' => '/<!\[CDATA\[/'] as $code => $pattern) {
        if (preg_match($pattern, $text)) {
            $out['problems'][] = $code;
            return $out;
        }
    }
    // No network, no entity substitution, no DTD loading; and where PHP lets the previous loader be restored, an
    // external entity loader that loads nothing.
    $previous = libxml_use_internal_errors(true);
    $loader   = function_exists('libxml_get_external_entity_loader') ? libxml_get_external_entity_loader() : false;
    if (false !== $loader) {
        libxml_set_external_entity_loader(static function () { return null; });
    }
    $doc    = new DOMDocument();
    $loaded = $doc->loadXML($text, LIBXML_NONET | LIBXML_COMPACT | (defined('LIBXML_NO_XXE') ? LIBXML_NO_XXE : 0));
    if (false !== $loader) {
        libxml_set_external_entity_loader($loader);
    }
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $svg = $loaded ? $doc->documentElement : null;
    if (!$svg || 'svg' !== $svg->localName || OMOCHIX_LOGO_MOTION_SVG_NS !== $svg->namespaceURI) {
        $out['problems'][] = 'parse';
        return $out;
    }
    // The document is the root element and nothing else.
    if (null !== $doc->doctype || 1 !== $doc->childNodes->length) {
        $out['problems'][] = 'outside-root';
        return $out;
    }
    $scope = (string) $svg->getAttribute('class');
    if (!preg_match('/^omx-[a-z0-9]{8}$/', $scope)) {
        $out['problems'][] = 'class';
        return $out;
    }
    $out['scope']   = $scope;
    $out['viewBox'] = (string) $svg->getAttribute('viewBox');

    $rules = omochix_logo_motion_rules();
    $first = null;
    foreach ($svg->childNodes as $child) {
        if (XML_ELEMENT_NODE === $child->nodeType) {
            $first = $child;
            break;
        }
    }
    $generated = null;
    if ($first && 'style' === $first->localName && preg_match('/@keyframes|^\.omx-|^svg\.omx-/', $first->textContent)) {
        $generated = $first;
        if (!omochix_logo_motion_generated_css_ok($first->textContent, $scope)) {
            $out['problems'][] = 'style';
        }
        $out['animated'] = false !== strpos($first->textContent, '@keyframes');
    }

    // Every id carries the export's own prefix (the studio renames them), except the two the overlay presets add.
    $own_id = function ($value) use ($scope) {
        return 0 === strpos($value, $scope . '-') || in_array($value, ['omxoverlay-gradient', 'omxoverlay-mask'], true);
    };
    $count = 0;
    $path  = 0;
    $walk  = function (DOMElement $node, $depth) use (&$walk, &$out, &$count, &$path, $rules, $generated, $scope, $own_id) {
        if (++$count > OMOCHIX_LOGO_MOTION_ELEMENTS || $depth > OMOCHIX_LOGO_MOTION_DEPTH) {
            $out['problems'][] = 'budget';
            return;
        }
        $name = $node->localName;
        if (!isset($rules['elements'][$name])) {
            $out['problems'][] = 'element:' . $name;
        }
        if (OMOCHIX_LOGO_MOTION_SVG_NS !== $node->namespaceURI) {
            $out['problems'][] = 'namespace:' . $name;
        }
        foreach ($node->attributes as $attribute) {
            $key   = $attribute->nodeName;
            $lower = strtolower($key);
            $value = $attribute->nodeValue;
            if (0 === strpos($lower, 'on')) {
                $out['problems'][] = 'handler:' . $lower;
            }
            if ('id' === $key && (!preg_match('/^[A-Za-z_][\w.:-]{0,127}$/', $value) || !$own_id($value))) {
                $out['problems'][] = 'id';
            }
            if ('d' === $key && ($path += strlen($value)) > OMOCHIX_LOGO_MOTION_PATH) {
                $out['problems'][] = 'path';
            }
            if (!isset($rules['attributes'][$key]) && !preg_match('/^data-omx-(?:stage|base|root|part|geometry|overlay)$/', $key)) {
                $out['problems'][] = 'attribute:' . $key;
            }
            if ('href' === $lower || 'xlink:href' === $lower) {
                $local = preg_match('/^#[A-Za-z_][\w.:-]*$/', $value);
                $image = 'image' === $name && preg_match('/^data:image\/(?:png|jpeg|webp);base64,[a-z0-9+\/=\s]+$/i', $value);
                if (!$local && !$image) {
                    $out['problems'][] = 'href:' . $name;
                }
            } elseif ('style' === $lower || preg_match('/url\s*\(/i', $value)) {
                if (!omochix_logo_motion_safe_value($value)) {
                    $out['problems'][] = 'value:' . $lower;
                }
            }
        }
        // A namespaced attribute outside xlink (e.g. xlink:href is fine, ev:event is not) shows up here too.
        if ($node->hasAttributes()) {
            foreach ($node->attributes as $attribute) {
                if ($attribute->namespaceURI && !in_array($attribute->namespaceURI, ['http://www.w3.org/1999/xlink', 'http://www.w3.org/XML/1998/namespace'], true)) {
                    $out['problems'][] = 'attribute-namespace';
                }
            }
        }
        if ('style' === $name && $node !== $generated && !omochix_logo_motion_scoped_css($node->textContent, $scope)) {
            $out['problems'][] = 'style:scope';
        }
        foreach ($node->childNodes as $child) {
            if (XML_ELEMENT_NODE === $child->nodeType) {
                $walk($child, $depth + 1);
            } elseif (XML_TEXT_NODE === $child->nodeType) {
                // Markup-like text could read differently if the file were ever parsed as HTML; an export has none.
                if ('style' !== $name && false !== strpos($child->nodeValue, '<')) {
                    $out['problems'][] = 'text';
                }
            } else {
                $out['problems'][] = [XML_PI_NODE => 'pi', XML_COMMENT_NODE => 'comment', XML_CDATA_SECTION_NODE => 'cdata', XML_ENTITY_REF_NODE => 'entity'][$child->nodeType] ?? 'node';
            }
        }
    };
    $walk($svg, 1);
    // Read as HTML (as a page would if the markup were pasted inline), the logo stays one SVG root with the same
    // elements, all in the SVG namespace. Needs PHP 8.4's HTML5 parser; earlier versions rely on the text rule above.
    if (!$out['problems'] && class_exists('Dom\\HTMLDocument')) {
        $html  = @\Dom\HTMLDocument::createFromString('<!doctype html><html><body>' . $text, LIBXML_NOERROR);
        $body  = $html->body;
        $roots = $body ? $body->children : null;
        $same  = $roots && 1 === $roots->length && 'svg' === $roots->item(0)->localName && $roots->item(0)->getElementsByTagName('*')->length + 1 === $count;
        if ($same) {
            foreach ($roots->item(0)->getElementsByTagName('*') as $element) {
                if (OMOCHIX_LOGO_MOTION_SVG_NS !== $element->namespaceURI) {
                    $same = false;
                    break;
                }
            }
        }
        if (!$same) {
            $out['problems'][] = 'html';
        }
    }
    $out['problems'] = array_values(array_unique($out['problems']));
    return $out;
}

/**
 * One file of the pair, read once through one handle (MVP-4). The path is examined without following links (lstat):
 * a regular file with a single link, directly inside the folder. The open handle must still be that same file
 * (device and inode), so a swap between the check and the read is refused instead of read.
 *
 * @return array{error?: string, text?: string}
 */
function omochix_logo_motion_read($path, $base) {
    clearstatcache(true, $path);
    $link = @lstat($path);
    if (!$link) {
        return ['error' => 'missing'];
    }
    $type = $link['mode'] & 0170000;
    if (0120000 === $type || $link['nlink'] > 1) {
        return ['error' => 'link'];
    }
    if (0100000 !== $type) {
        return ['error' => 'missing'];
    }
    $real = realpath($path);
    if (!$real || dirname($real) !== $base) {
        return ['error' => 'outside'];
    }
    if ($link['size'] > OMOCHIX_LOGO_MOTION_MAX) {
        return ['error' => 'size'];
    }
    $handle = @fopen($real, 'rb');
    if (!$handle) {
        return ['error' => 'missing'];
    }
    $open = fstat($handle);
    $text = $open && $open['dev'] === $link['dev'] && $open['ino'] === $link['ino'] && 1 === $open['nlink'] && 0100000 === ($open['mode'] & 0170000)
        ? stream_get_contents($handle, OMOCHIX_LOGO_MOTION_MAX + 1) : false;
    fclose($handle);
    if (false === $text) {
        return ['error' => 'changed'];
    }
    return strlen($text) > OMOCHIX_LOGO_MOTION_MAX ? ['error' => 'size'] : ['text' => $text];
}

/**
 * The verified pair for a name, or an error code. Only plain names, only regular files directly inside the folder.
 * The result is keyed on the contents themselves, and ?v= is a hash of them: what was checked is what is offered.
 *
 * @param string $name
 * @return array{error?: string, animated?: string, still?: string, url?: string, version?: string, viewBox?: string}
 */
function omochix_logo_motion_resolve($name) {
    static $cache = [];
    if (!is_string($name) || !preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $name)) {
        return ['error' => 'name'];
    }
    $uploads = wp_upload_dir(null, false);
    if (!empty($uploads['error']) || empty($uploads['basedir'])) {
        return ['error' => 'uploads'];
    }
    $dir  = $uploads['basedir'] . '/' . OMOCHIX_LOGO_MOTION_DIR;
    $base = is_dir($dir) && !is_link($dir) ? realpath($dir) : false;
    if (!$base) {
        return ['error' => 'folder'];
    }
    $texts = [];
    foreach (['animated' => $name . '.svg', 'still' => $name . '-static.svg'] as $role => $file) {
        $read = omochix_logo_motion_read($dir . '/' . $file, $base);
        if (isset($read['error'])) {
            return $read;
        }
        $texts[$role] = $read['text'];
    }
    $hash = sha1($texts['animated'] . "\0" . $texts['still']);
    if (isset($cache[$hash])) {
        return $cache[$hash];
    }
    $checks = [];
    foreach ($texts as $role => $text) {
        $checks[$role] = omochix_logo_motion_check_svg($text);
        if ($checks[$role]['problems']) {
            return $cache[$hash] = ['error' => 'invalid', 'problems' => $checks[$role]['problems']];
        }
    }
    // The pair belongs together: same logo (scope and viewBox); one moves, the other does not.
    if ($checks['animated']['scope'] !== $checks['still']['scope'] || $checks['animated']['viewBox'] !== $checks['still']['viewBox']
        || !$checks['animated']['animated'] || $checks['still']['animated']) {
        return $cache[$hash] = ['error' => 'pair'];
    }
    $url = trailingslashit($uploads['baseurl']) . OMOCHIX_LOGO_MOTION_DIR . '/';
    return $cache[$hash] = [
        'animated' => $url . $name . '.svg',
        'still'    => $url . $name . '-static.svg',
        'version'  => substr($hash, 0, 12),
        'viewBox'  => $checks['animated']['viewBox'],
    ];
}

/**
 * @param array|string $atts name, alt, size. Anything else is ignored.
 * @return string
 */
function omochix_logo_motion_render($atts) {
    $atts = shortcode_atts(['name' => '', 'alt' => '', 'size' => ''], is_array($atts) ? $atts : [], 'omochix_logo_motion');
    $pair = omochix_logo_motion_resolve($atts['name']);
    if (isset($pair['error'])) {
        // Said only to people who can edit posts, and only as a code: never the input, never a path.
        return current_user_can('edit_posts') && !is_feed() ? '<!-- omochix_logo_motion: ' . esc_html($pair['error']) . ' -->' : '';
    }
    $size = (string) $atts['size'];
    $size = preg_match('/^\d{1,4}$/', $size) ? max(16, min(1024, (int) $size)) : 200;
    $box  = array_map('floatval', preg_split('/[\s,]+/', trim($pair['viewBox'])) ?: []);
    $ratio = 4 === count($box) && $box[2] > 0 && $box[3] > 0 ? $box[3] / $box[2] : 1;
    $height = max(1, (int) round($size * $ratio));
    $alt  = mb_substr(sanitize_text_field((string) $atts['alt']), 0, 120);
    $v    = '?v=' . $pair['version'];

    return '<picture class="omochix-logo-motion">'
        . '<source media="(prefers-reduced-motion: reduce)" srcset="' . esc_url($pair['still'] . $v) . '">'
        . '<img src="' . esc_url($pair['animated'] . $v) . '" alt="' . esc_attr($alt) . '" width="' . $size . '" height="' . $height . '"'
        . ' loading="lazy" decoding="async" style="max-width:100%;height:auto">'
        . '</picture>';
}

function omochix_logo_motion_register() {
    add_shortcode('omochix_logo_motion', 'omochix_logo_motion_render');
}
add_action('init', 'omochix_logo_motion_register');
