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

/** export.js cssIsClean(): the generated CSS uses only these characters and no fetching function. */
function omochix_logo_motion_clean_css($css) {
    return 1 === preg_match('/^[\w\s.#%(),:;{}\[\]="\'*>+\-@]*$/', $css)
        && 0 === preg_match('/url\s*\(|@import|@font-face|expression|javascript:|<|\\\\/i', $css);
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
    // No document type, entities or processing instructions: an export never has them.
    if (preg_match('/<!DOCTYPE|<!ENTITY|<\?(?!xml\s)/i', $text)) {
        $out['problems'][] = 'doctype';
        return $out;
    }
    $previous = libxml_use_internal_errors(true);
    $doc      = new DOMDocument();
    $loaded   = $doc->loadXML($text, LIBXML_NONET | LIBXML_COMPACT);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $svg = $loaded ? $doc->documentElement : null;
    if (!$svg || 'svg' !== $svg->localName || OMOCHIX_LOGO_MOTION_SVG_NS !== $svg->namespaceURI) {
        $out['problems'][] = 'parse';
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
        if (!omochix_logo_motion_clean_css($first->textContent)) {
            $out['problems'][] = 'style';
        }
        $out['animated'] = false !== strpos($first->textContent, '@keyframes') || false !== strpos($first->textContent, 'animation:');
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
            } elseif (in_array($child->nodeType, [XML_PI_NODE, XML_ENTITY_REF_NODE, XML_DOCUMENT_TYPE_NODE, XML_COMMENT_NODE], true)) {
                $out['problems'][] = 'node';
            }
        }
    };
    $walk($svg, 1);
    $out['problems'] = array_values(array_unique($out['problems']));
    return $out;
}

/**
 * The verified pair for a name, or an error code. Only plain names, only regular files directly inside the folder.
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
    $files = [];
    foreach (['animated' => $name . '.svg', 'still' => $name . '-static.svg'] as $role => $file) {
        $path = $dir . '/' . $file;
        if (is_link($path)) {
            return ['error' => 'link'];
        }
        $real = is_file($path) ? realpath($path) : false;
        if (!$real) {
            return ['error' => 'missing'];
        }
        if (dirname($real) !== $base) {
            return ['error' => 'outside'];
        }
        $files[$role] = $real;
    }
    $stamp = [];
    foreach ($files as $real) {
        clearstatcache(true, $real);
        $stamp[] = filemtime($real) . ':' . filesize($real);
    }
    $key = $name . '|' . implode('|', $stamp);
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $checks = [];
    foreach ($files as $role => $real) {
        $size = filesize($real);
        if (false === $size || $size > OMOCHIX_LOGO_MOTION_MAX) {
            return $cache[$key] = ['error' => 'size'];
        }
        $checks[$role] = omochix_logo_motion_check_svg((string) file_get_contents($real));
        if ($checks[$role]['problems']) {
            return $cache[$key] = ['error' => 'invalid', 'problems' => $checks[$role]['problems']];
        }
    }
    // The pair belongs together: same logo (scope and viewBox); one moves, the other does not.
    if ($checks['animated']['scope'] !== $checks['still']['scope'] || $checks['animated']['viewBox'] !== $checks['still']['viewBox']
        || !$checks['animated']['animated'] || $checks['still']['animated']) {
        return $cache[$key] = ['error' => 'pair'];
    }
    $url = trailingslashit($uploads['baseurl']) . OMOCHIX_LOGO_MOTION_DIR . '/';
    return $cache[$key] = [
        'animated' => $url . $name . '.svg',
        'still'    => $url . $name . '-static.svg',
        'version'  => substr(md5(implode('|', $stamp)), 0, 10),
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
