<?php
/**
 * OmochiX Labs release gate, PHP side: what a visitor can reach for a given theme.
 *
 * The cost calculator ships in one of two states, set by its catalog status:
 *  - published: its prices must be fully reviewed (global review approved, at
 *    least one approved row, no pending row). tests/labs/data.js --release also
 *    enforces the per-row rules.
 *  - draft: the tool must be unreachable. Its URL is a 404; it is absent from the
 *    list, search, categories, related links, sitemap, structured data, page
 *    config and copy; and no unapproved price reaches a public page.
 * The price review is never skipped: a published calculator always needs it.
 *
 * Needs tests/labs/stub.php. Used by tests/labs/run.php and tests/labs/release-gate.php.
 */

const LABS_COST_SLUG = 'token-cost-calculator';

/** Strings that only belong to the cost calculator. */
function labs_cost_markers() {
    return [LABS_COST_SLUG, 'API料金', '料金計算', 'gpt-6-astra', 'claude-opus-5-5', 'gemini-4-argon', 'platform.claude.com'];
}

/**
 * @param string $theme Theme directory to check.
 * @return string[] Problems; empty when the theme is releasable.
 */
function labs_release_state_problems($theme) {
    $saved = [$GLOBALS['THEME'], $GLOBALS['env'] ?? null, $GLOBALS['user'] ?? null];
    $GLOBALS['THEME'] = $theme;
    $GLOBALS['env']   = 'production';
    unset($GLOBALS['user']); // a visitor
    $problems = [];
    try {
        labs_request();
        $loaded = omochix_labs_load_catalog_from_file(omochix_labs_data_file('catalog'));
        if (!$loaded['ok'] || [] !== $loaded['errors']) {
            return ['catalog is invalid: ' . implode('; ', $loaded['errors'])];
        }
        $status = null;
        foreach ($loaded['catalog']['tools'] as $tool) {
            if (LABS_COST_SLUG === ($tool['slug'] ?? null)) {
                $status = $tool['status'] ?? null;
            }
        }
        $read = omochix_labs_read_guarded_json(omochix_labs_pricing_file());
        $data = $read['ok'] ? $read['data'] : null;

        if ('published' === $status) {
            // Published: the full price review is required, never skipped.
            if (!is_array($data) || !omochix_labs_pricing_approved($data)) {
                $problems[] = 'cost calculator is published but the price review is not approved';
            }
            $states = is_array($data) ? array_map('omochix_labs_row_approval_state', $data['models'] ?? []) : [];
            if (!in_array('approved', $states, true)) {
                $problems[] = 'cost calculator is published but no row is approved';
            }
            if (in_array('pending', $states, true)) {
                $problems[] = 'cost calculator is published but rows are still pending';
            }
        } elseif ('draft' === $status) {
            $problems = array_merge($problems, labs_release_state_draft_problems());
        } else {
            $problems[] = 'cost calculator is missing from the catalog';
        }

        // Either way: no unapproved price is public in production.
        labs_request(LABS_COST_SLUG);
        foreach (omochix_labs_get_pricing()['models'] ?? [] as $model) {
            if ('approved' !== ($model['approval'] ?? null)) {
                $problems[] = 'an unapproved price is public: ' . ($model['id'] ?? '?');
            }
        }
        if (is_array($data) && !omochix_labs_pricing_approved($data) && [] !== (omochix_labs_get_pricing()['models'] ?? [])) {
            $problems[] = 'prices are public while the price review is not approved';
        }
    } finally {
        [$GLOBALS['THEME'], $env, $user] = $saved;
        if (null === $env) { unset($GLOBALS['env']); } else { $GLOBALS['env'] = $env; }
        if (null === $user) { unset($GLOBALS['user']); } else { $GLOBALS['user'] = $user; }
        labs_reset_statics();
    }
    return $problems;
}

/** @return string[] */
function labs_release_state_draft_problems() {
    $problems = [];
    $markers  = labs_cost_markers();
    $leaks    = static function ($label, $text) use ($markers, &$problems) {
        foreach ($markers as $m) {
            if (false !== strpos((string) $text, $m)) {
                $problems[] = "$label mentions \"$m\"";
            }
        }
    };

    labs_request();
    $tools = omochix_labs_get_tools();
    if (isset($tools[LABS_COST_SLUG])) {
        $problems[] = 'cost calculator is listed as a published tool';
    }
    if (count($tools) < 1) {
        $problems[] = 'no published tool';
    }

    // URL: 404, no title or description.
    labs_request(LABS_COST_SLUG);
    do_action('template_redirect');
    if (!is_404()) {
        $problems[] = '/labs/' . LABS_COST_SLUG . '/ is not a 404';
    }

    // List page: cards, categories, search, copy, structured data, page config.
    labs_request();
    ob_start();
    get_template_part('template-parts/labs/index');
    $list = (string) ob_get_clean();
    $leaks('list page', $list);
    if (count($tools) !== substr_count($list, 'data-labs-card')) {
        $problems[] = 'list page card count is not the published tool count';
    }
    if (isset(omochix_labs_get_categories()['ai']) || false !== strpos($list, 'data-labs-cat="ai"')) {
        $problems[] = 'the AI category (cost calculator only) is still shown';
    }
    foreach (['anthropic', 'API料金', 'トークン 料金'] as $q) {
        if ([] !== omochix_labs_filter_tools(['q' => $q])) {
            $problems[] = "search \"$q\" still finds a tool";
        }
    }
    $leaks('list meta description', omochix_labs_meta_description());
    $graph = apply_filters('slim_seo_schema_graph', [['@type' => 'WebPage', '@id' => home_url('/labs/') . '#webpage', 'url' => home_url('/labs/')]]);
    $leaks('list structured data', json_encode($graph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $GLOBALS['enqueued'] = ['styles' => [], 'scripts' => [], 'inline' => []];
    do_action('wp_enqueue_scripts');
    $leaks('list page config', implode('', $GLOBALS['enqueued']['inline']['omochix-labs-shared'] ?? []));

    // Published tools: related links, copy, structured data, page config.
    foreach (array_keys($tools) as $slug) {
        labs_request($slug);
        $tool = omochix_labs_get_tool($slug);
        if (in_array(LABS_COST_SLUG, array_column(omochix_labs_related_tools($tool), 'slug'), true)) {
            $problems[] = "$slug links to the cost calculator";
        }
        ob_start();
        get_template_part('template-parts/labs/tool', null, ['tool' => $tool]);
        $leaks("$slug page", ob_get_clean());
        $url = home_url('/labs/' . $slug . '/');
        $leaks("$slug structured data", json_encode(apply_filters('slim_seo_schema_graph', [['@type' => 'WebPage', '@id' => $url . '#webpage', 'url' => $url]]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $GLOBALS['enqueued'] = ['styles' => [], 'scripts' => [], 'inline' => []];
        do_action('wp_enqueue_scripts');
        $leaks("$slug page config", implode('', $GLOBALS['enqueued']['inline']['omochix-labs-shared'] ?? []));
    }

    // Sitemap and robots.txt.
    labs_request();
    $locs = array_column(omochix_labs_sitemap_entries(), 'loc');
    $leaks('labs-sitemap.xml', implode(' ', $locs));
    if (count($locs) !== count($tools)) {
        $problems[] = 'labs-sitemap.xml does not list exactly the published tools';
    }

    // Copy outside Labs that points at Labs.
    $leaks('page-learn.php', file_get_contents(get_theme_file_path('/page-learn.php')));

    // Copy that the public pages' scripts put on screen (sample inputs, messages).
    $catalog = omochix_labs_load_catalog_from_file(omochix_labs_data_file('catalog'));
    return array_merge($problems, labs_release_state_script_problems($catalog['catalog']['tools']));
}

/* -------------------------------------------------------------------------
 * Script copy: a draft tool must not be named, linked or presented as
 * published by any script that a public Labs page loads.
 * ---------------------------------------------------------------------- */

/** Words that present a tool as available (「…を公開しています」「…が使えます」). */
const LABS_PUBLISH_CLAIM = '/公開|提供|使え|利用でき|ご利用|お試し|ツール集|揃え|リリース|\b(?:available|launched|try)\b/iu';

/**
 * String literals of a JavaScript source, with their line numbers. Comments
 * and regular-expression literals are skipped; escapes are decoded.
 *
 * @return array<int, array{0: int, 1: string}>
 */
function labs_js_string_literals($src) {
    $out  = [];
    $n    = strlen($src);
    $line = 1;
    $prev = '';   // last significant character outside strings and comments
    $word = '';   // last identifier, to tell "return /re/" from "a / b"
    for ($i = 0; $i < $n; $i++) {
        $c = $src[$i];
        if ("\n" === $c) { $line++; continue; }
        if (ctype_space($c)) { continue; }
        if ('/' === $c && '/' === ($src[$i + 1] ?? '')) { // line comment
            while ($i < $n && "\n" !== $src[$i]) { $i++; }
            $i--;
            continue;
        }
        if ('/' === $c && '*' === ($src[$i + 1] ?? '')) { // block comment
            $end = strpos($src, '*/', $i + 2);
            $end = false === $end ? $n : $end + 2;
            $line += substr_count($src, "\n", $i, $end - $i);
            $i = $end - 1;
            continue;
        }
        if ("'" === $c || '"' === $c || '`' === $c) {
            $start = $line;
            $buf   = '';
            for ($i++; $i < $n && $src[$i] !== $c; $i++) {
                if ("\n" === $src[$i]) { $line++; }
                if ('\\' === $src[$i] && $i + 1 < $n) {
                    $e = $src[++$i];
                    if ('u' === $e && preg_match('/\G[0-9a-fA-F]{4}/', $src, $m, 0, $i + 1)) {
                        $buf .= mb_chr(hexdec($m[0]), 'UTF-8');
                        $i   += 4;
                    } else {
                        $buf .= ['n' => "\n", 't' => "\t", 'r' => "\r"][$e] ?? $e;
                        if ("\n" === $e) { $line++; }
                    }
                    continue;
                }
                $buf .= $src[$i];
            }
            $out[] = [$start, $buf];
            $prev  = $c;
            $word  = '';
            continue;
        }
        if ('/' === $c && ('' === $prev || false !== strpos('(,=:[!&|?{};+-*%<>~^', $prev) || in_array($word, ['return', 'typeof', 'case', 'in', 'of', 'new', 'delete', 'void', 'throw'], true))) {
            $class = false; // regular-expression literal: skip to the closing slash
            for ($i++; $i < $n; $i++) {
                $r = $src[$i];
                if ('\\' === $r) { $i++; continue; }
                if ('[' === $r) { $class = true; } elseif (']' === $r) { $class = false; } elseif ('/' === $r && !$class) { break; } elseif ("\n" === $r) { break; }
            }
            $prev = '/';
            $word = '';
            continue;
        }
        if (preg_match('/\G[A-Za-z_$][A-Za-z0-9_$]*/', $src, $m, 0, $i)) {
            $word = $m[0];
            $prev = 'a';
            $i   += strlen($m[0]) - 1;
            continue;
        }
        $prev = $c;
        $word = '';
    }
    return $out;
}

/** Whether a string literal is text for people (not a key, class name, slug or URL). */
function labs_is_display_copy($s) {
    return 1 === preg_match('/[^\x00-\x7F]/', $s) || 1 === preg_match('/[A-Za-z]{2,}[ ,.!?][ ]*[A-Za-z]{2,}/', $s);
}

/**
 * Names that identify each draft tool in copy: its slug, name, title (before
 * "｜") and the parts of its name that no published tool uses. The cost
 * calculator also keeps its fixed markers.
 *
 * @param array<int, array<string, mixed>> $tools Catalog tools.
 * @return array<string, string[]> slug => names
 */
function labs_draft_tool_names(array $tools) {
    $public = '';
    foreach ($tools as $t) {
        if ('published' === ($t['status'] ?? null)) {
            $public .= ' ' . mb_strtolower(($t['name'] ?? '') . ' ' . ($t['title'] ?? '') . ' ' . implode(' ', $t['keywords'] ?? []));
        }
    }
    $names = [];
    foreach ($tools as $t) {
        if ('draft' !== ($t['status'] ?? null)) {
            continue;
        }
        $list = [$t['slug'], $t['name'], explode('｜', $t['title'])[0]];
        foreach (preg_split('/[・｜\s]+/u', $t['name']) as $part) {
            if (mb_strlen($part) >= 3 && false === mb_strpos($public, mb_strtolower($part))) {
                $list[] = $part;
            }
        }
        if (LABS_COST_SLUG === $t['slug']) {
            $list = array_merge($list, labs_cost_markers());
        }
        $names[$t['slug']] = array_values(array_unique(array_filter($list, 'strlen')));
    }
    return $names;
}

/**
 * Theme-relative paths of every script a public Labs page loads (list and
 * each published tool, plus the Worker and the files it imports).
 *
 * @return string[]
 */
function labs_public_script_files() {
    $base  = get_theme_file_uri('');
    $files = [];
    $add   = static function ($url) use ($base, &$files) {
        $url = (string) strtok((string) $url, '?');
        if (0 === strpos($url, $base)) {
            $files[substr($url, strlen($base))] = true;
        }
    };
    labs_request();
    $pages = array_merge([null], array_keys(omochix_labs_get_tools()));
    foreach ($pages as $slug) {
        labs_request($slug);
        $GLOBALS['enqueued'] = ['styles' => [], 'scripts' => [], 'inline' => []];
        do_action('wp_enqueue_scripts');
        foreach ($GLOBALS['enqueued']['scripts'] as $script) {
            $add($script['src']);
        }
        foreach ($GLOBALS['enqueued']['inline']['omochix-labs-shared'] ?? [] as $inline) {
            if (preg_match('/^window\.omochixLabs = (.*);$/s', $inline, $m)) {
                $config = json_decode($m[1], true);
                $add($config['worker']['url'] ?? '');
                foreach ($config['worker']['scripts'] ?? [] as $w) {
                    $add($w);
                }
            }
        }
    }
    ksort($files);
    return array_keys($files);
}

/**
 * @param array<int, array<string, mixed>> $tools Catalog tools.
 * @return string[]
 */
function labs_release_state_script_problems(array $tools) {
    $names    = labs_draft_tool_names($tools);
    $problems = [];
    if ([] === $names) {
        return $problems;
    }
    foreach (labs_public_script_files() as $file) {
        $src = (string) file_get_contents(get_theme_file_path($file));
        foreach (labs_js_string_literals($src) as [$line, $s]) {
            foreach ($names as $slug => $list) {
                if (preg_match('#(?:^|/)labs/' . preg_quote($slug, '#') . '(?:/|$|\?|\#)#', $s)) {
                    $problems[] = "$file:$line links to draft tool \"$slug\"";
                    continue;
                }
                if (!labs_is_display_copy($s)) {
                    continue; // a key, class name, slug or URL: code, not copy
                }
                foreach ($list as $name) {
                    if (false !== mb_stripos($s, $name)) {
                        $what = preg_match(LABS_PUBLISH_CLAIM, $s) ? 'presents draft tool "' . $slug . '" as published' : 'names draft tool "' . $slug . '"';
                        $problems[] = "$file:$line $what (\"$name\")";
                        break;
                    }
                }
            }
        }
    }
    return $problems;
}
