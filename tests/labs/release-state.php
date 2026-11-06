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
    return $problems;
}
