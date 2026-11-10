<?php
/**
 * Industry Hub Phase 2 — access-control consistency on a THROWAWAY WordPress.
 * Every public surface must agree on whether an industry is public, also
 * right after the gate cache is invalidated by a content change.
 *
 *   IH_BASE=http://127.0.0.1:8777 IH_WP_ROOT=/path/to/throwaway/wp php tests/industry-hub/http-access-matrix.php
 *
 * It edits the throwaway site's data (and restores it). Never point it at
 * staging or production.
 */
$base = rtrim((string) getenv('IH_BASE'), '/');
$root = rtrim((string) getenv('IH_WP_ROOT'), '/');
if (!preg_match('#^http://(127\.0\.0\.1|localhost)(:\d+)?$#', $base) || !is_file($root . '/wp-load.php')) {
    fwrite(STDERR, "IH_BASE (local) and IH_WP_ROOT (throwaway WordPress) are required\n");
    exit(2);
}
$pass = 0; $fail = 0;
function check($name, $cond, $detail = '') { global $pass, $fail; if ($cond) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name" . ('' !== $detail ? "  [$detail]" : '') . "\n"; } }
function get($path) {
    global $base;
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60]);
    $body = (string) curl_exec($ch);
    return ['code' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'body' => $body];
}
/** Run PHP against the throwaway site in a separate process (fresh request). */
function wp_cli($code) {
    global $root;
    $script = '$_SERVER["HTTP_HOST"]="127.0.0.1";$_SERVER["REQUEST_URI"]="/";require ' . var_export($root . '/wp-load.php', true) . ';wp_set_current_user(1);' . $code;
    return shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' 2>/dev/null');
}

/** What each public surface says about one industry. */
function surfaces($slug, $name) {
    $s = [];
    $s['detail']   = 200 === get("/learn/industries/$slug/")['code'];
    $s['index']    = false !== strpos(get('/learn/industries/')['body'], ">$name<");
    $s['search']   = 200 === get("/learn/industries/?industry=$slug")['code'];
    $rest          = json_decode(get('/wp-json/wp/v2/industries?per_page=100')['body'], true);
    $s['rest']     = is_array($rest) && in_array($slug, array_column($rest, 'slug'), true);
    $ts            = json_decode(get('/wp-json/wp/v2/search?type=term&subtype=omx_industry&per_page=100&search=')['body'], true);
    $s['termsrch'] = is_array($ts) && in_array($name, array_column($ts, 'title'), true);
    $s['feed']     = false; // industry feeds are never offered (404 for every industry)
    return $s;
}
function indexable($slug) {
    $sm = get('/sitemap-taxonomy-omx_industry.xml')['body'];
    $r  = get("/learn/industries/$slug/")['body'];
    return ['sitemap' => false !== strpos($sm, "/learn/industries/$slug/"), 'robots_index' => (bool) preg_match("/<meta name=['\"]robots['\"] content=['\"](?![^'\"]*noindex)/", $r)];
}
function all_same(array $s, $expect) { foreach ($s as $k => $v) { if ('feed' !== $k && $v !== $expect) { return $k; } } return ''; }

// Fixture: real-estate (visible + indexable), restaurant (visible, not indexable), beauty (hidden).
foreach ([['real-estate', '不動産', true, true], ['restaurant', '飲食', true, false], ['beauty', '美容', false, false]] as [$slug, $name, $visible, $idx]) {
    $s   = surfaces($slug, $name);
    $odd = all_same($s, $visible);
    check("consistent ($slug " . ($visible ? 'visible' : 'hidden') . '): detail, index, cross search, REST, term search agree', '' === $odd, $odd . ' ' . json_encode($s));
    $i = indexable($slug);
    check("indexability ($slug): sitemap and robots agree (" . ($idx ? 'indexed' : 'not indexed') . ')', $i['sitemap'] === $idx && ($visible ? $i['robots_index'] === $idx : true), json_encode($i));
    check("feed ($slug): never offered", 404 === get("/learn/industries/$slug/feed/")['code']);
}

// Make beauty public through content changes only; every surface must follow at once.
wp_cli('$t=get_term_by("slug","beauty","omx_industry")->term_id; $b=str_repeat("業務の流れを具体的に説明する本文。",6);
update_term_meta($t,"industry_lead",str_repeat("美容のAI活用を独自に解説する導入文です。",6));
update_term_meta($t,"industry_challenges",[["title"=>"予約","body"=>$b,"purpose"=>"customer-support"],["title"=>"SNS","body"=>$b,"purpose"=>"marketing"],["title"=>"書類","body"=>$b,"purpose"=>"document"]]);
update_term_meta($t,"industry_usecases",[["title"=>"a","body"=>$b,"purpose"=>"marketing"],["title"=>"b","body"=>$b,"purpose"=>"document"],["title"=>"c","body"=>$b,"purpose"=>"customer-support"]]);
update_term_meta($t,"industry_verified_at",gmdate("Y-m-d")); update_term_meta($t,"industry_sources",[["label"=>"s","url"=>"https://example.com/"]]);
$p=get_posts(["post_type"=>"learn_practice","name"=>"ih-wf-beauty-creator","numberposts"=>1])[0]; wp_set_object_terms($p->ID,["official"],"omx_provider_type");');
$s = surfaces('beauty', '美容');
check('after making beauty public: every surface shows it immediately (cache invalidated)', '' === all_same($s, true), json_encode($s));
$i = indexable('beauty');
check('after making beauty public: sitemap and robots index it', true === $i['sitemap'] && true === $i['robots_index'], json_encode($i));

// Hide it again by unchecking G1 only.
wp_cli('update_term_meta(get_term_by("slug","beauty","omx_industry")->term_id,"industry_hub_ready",false);');
$s = surfaces('beauty', '美容');
check('after unchecking G1: every surface hides beauty immediately', '' === all_same($s, false), json_encode($s));
check('after unchecking G1: gone from the sitemap', false === indexable('beauty')['sitemap']);

// Restore the fixture (beauty: flag only, creator workflow, no content).
wp_cli('$t=get_term_by("slug","beauty","omx_industry")->term_id; foreach (["industry_lead","industry_challenges","industry_usecases","industry_verified_at","industry_sources"] as $k) { delete_term_meta($t,$k); } update_term_meta($t,"industry_hub_ready",true);
$p=get_posts(["post_type"=>"learn_practice","name"=>"ih-wf-beauty-creator","numberposts"=>1])[0]; wp_set_object_terms($p->ID,["creator"],"omx_provider_type");');
check('fixture restored: beauty hidden again', 404 === get('/learn/industries/beauty/')['code']);

// §7 rules apply to every list, and counts / pagination follow what is listed.
function section($html, $id) { $i = strpos($html, 'id="' . $id . '"'); if (false === $i) { return ''; } $j = strpos($html, '<section', $i + 1); return substr($html, $i, false === $j ? null : $j - $i); }
function count_shown($html) { return preg_match('#<p class="ih-count"[^>]*>(\d+)件#', $html, $m) ? (int) $m[1] : -1; }
function cards($html) { $m = section($html, 'ih-more'); $i = strpos($m, 'id="ih-list"'); return false === $i ? -1 : substr_count(substr($m, $i), '<article class="ih-card'); } // the main-query list only

// A published workflow that loses a required field (blocked) disappears from the hub list, the index search and the counts.
$before = count_shown(get('/learn/industries/real-estate/?type=workflow')['body']);
wp_cli('$p=get_posts(["post_type"=>"learn_practice","name"=>"ih-bulk-wf-0","numberposts"=>1])[0]; update_post_meta($p->ID,"ih_saved_problem",get_post_meta($p->ID,"workflow_problem",true)); delete_post_meta($p->ID,"workflow_problem"); echo omochix_core_workflow_stored_blockers($p->ID) ? "blocked" : "not blocked";');
$hub = get('/learn/industries/real-estate/?type=workflow')['body'];
check('blocked workflow: not on the hub list, and the count drops by one', false === strpos(section($hub, 'ih-more'), '>Bulk WF 0<') && $before - 1 === count_shown($hub), "before=$before after=" . count_shown($hub));
check('blocked workflow: the count equals the cards listed (one page)', count_shown($hub) === cards($hub) || count_shown($hub) > 12, count_shown($hub) . ' vs ' . cards($hub));
$idx = get('/learn/industries/?type=workflow')['body'];
check('blocked workflow: not in the index search either', false === strpos($idx, '>Bulk WF 0<') && false !== strpos($idx, '>Bulk WF 1<'));
check('blocked workflow: not in section 4', false === strpos(section(get('/learn/industries/real-estate/')['body'], 'ih-workflows'), '>Bulk WF 0<'));
wp_cli('$p=get_posts(["post_type"=>"learn_practice","name"=>"ih-bulk-wf-0","numberposts"=>1])[0]; update_post_meta($p->ID,"workflow_problem",get_post_meta($p->ID,"ih_saved_problem",true)); delete_post_meta($p->ID,"ih_saved_problem");');
check('blocked workflow restored: listed again', false !== strpos(get('/learn/industries/?type=workflow')['body'], '>Bulk WF 0<'));

// Index search: type=practice lists no workflows; discontinued tools are never listed.
$pr = get('/learn/industries/?type=practice')['body'];
check('index search type=practice: no workflow cards', false === strpos($pr, 'ih-card--workflow'));
wp_cli('update_post_meta(get_posts(["post_type"=>"ai_tool","name"=>"ih-bulk-tool-1","numberposts"=>1])[0]->ID,"tool_status","discontinued");');
check('discontinued tool: not in the index search (type=tool)', false === strpos(get('/learn/industries/?type=tool')['body'], '>Bulk tool 1<'));
check('discontinued tool: not in the hub list (all types)', false === strpos(section(get('/learn/industries/real-estate/page/2/')['body'], 'ih-more') . section(get('/learn/industries/real-estate/')['body'], 'ih-more'), '>Bulk tool 1<'));
wp_cli('delete_post_meta(get_posts(["post_type"=>"ai_tool","name"=>"ih-bulk-tool-1","numberposts"=>1])[0]->ID,"tool_status");');

// Curated (featured) workflows follow the chosen challenge's purpose.
$faq = trim((string) preg_replace('/<!--.*?-->/s', '', (string) wp_cli('$p=get_posts(["post_type"=>"learn_practice","name"=>"ih-wf-faq","numberposts"=>1])[0]; $t=get_term_by("slug","real-estate","omx_industry")->term_id; update_term_meta($t,"ih_saved_featured",get_term_meta($t,"industry_featured_ids",true)); update_term_meta($t,"industry_featured_ids",[$p->ID]); echo has_term("marketing","omx_purpose",$p) ? "has-marketing" : get_the_title($p);')));
check('featured fixture: ih-wf-faq has no marketing purpose', '' !== $faq && 'has-marketing' !== $faq, $faq);
check('featured workflow: shown in section 4 without a challenge', false !== strpos(section(get('/learn/industries/real-estate/')['body'], 'ih-workflows'), '>' . $faq . '<'));
check('featured workflow: not shown under another challenge (?purpose=marketing)', false === strpos(section(get('/learn/industries/real-estate/?purpose=marketing')['body'], 'ih-workflows'), '>' . $faq . '<'));
wp_cli('$t=get_term_by("slug","real-estate","omx_industry")->term_id; $v=get_term_meta($t,"ih_saved_featured",true); $v ? update_term_meta($t,"industry_featured_ids",$v) : delete_term_meta($t,"industry_featured_ids"); delete_term_meta($t,"ih_saved_featured");');

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
