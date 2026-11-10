<?php
/**
 * Industry Hub Phase 1: the private external resource ledger (omx_resource):
 * no public exposure, license record rules, the internal permission
 * reference, the publish guard, the save handler (nonce, capability,
 * sanitize) and escaping.
 * Run: php tests/industry-hub/resource.php
 */
require __DIR__ . '/stub.php';

ih_fresh();
$cpt  = $GLOBALS['registered']['post_types']['omx_resource'] ?? null;
$meta = $GLOBALS['registered']['post_meta']['omx_resource'] ?? [];

// ---------------------------------------------------------- not public
check('cpt: registered', is_array($cpt));
check('cpt: not public, not queryable, not searchable, no REST, no archive, no rewrite, no query var', false === $cpt['public'] && false === $cpt['publicly_queryable'] && true === $cpt['exclude_from_search'] && false === $cpt['show_in_rest'] && false === $cpt['has_archive'] && false === $cpt['rewrite'] && false === $cpt['query_var']);
check('cpt: not in nav menus, admin bar or WordPress exports', false === $cpt['show_in_nav_menus'] && false === $cpt['show_in_admin_bar'] && false === $cpt['can_export']);
check('cpt: no body field (title and revisions only), so content cannot be pasted in', ['title', 'revisions'] === $cpt['supports']);
check('cpt: editors manage it in the admin with the normal post capabilities', true === $cpt['show_ui'] && 'post' === $cpt['capability_type'] && true === $cpt['map_meta_cap']);
check('meta: 14 keys, none in REST, all with sanitize and auth callbacks', 14 === count($meta) && !array_filter($meta, static fn($a) => false !== $a['show_in_rest'] || 'omochix_core_sanitize_registered_resource_meta' !== $a['sanitize_callback'] || 'omochix_core_auth_registered_resource_meta' !== $a['auth_callback']));
check('meta: the permission reference is not copied into revisions', false === $meta['resource_permission_ref']['revisions_enabled'] && true === $meta['resource_url']['revisions_enabled']);
check('meta: the permission reference is protected meta (hidden from the Custom Fields box)', true === apply_filters('is_protected_meta', false, 'resource_permission_ref') && false === apply_filters('is_protected_meta', false, 'resource_url'));
check('sitemap: the ledger is removed from core (name => object) and Slim SEO (list) sitemaps', ['post' => 1, 'page' => 2] === apply_filters('wp_sitemaps_post_types', ['post' => 1, 'omx_resource' => 3, 'page' => 2]) && ['post', 'page'] === apply_filters('slim_seo_sitemap_post_types', ['post', 'omx_resource', 'page']));
check('taxonomies: industries, purposes and provider type can be set on a ledger entry', in_array('omx_resource', $GLOBALS['registered']['taxonomies']['omx_industry']['types'], true) && in_array('omx_resource', $GLOBALS['registered']['taxonomies']['omx_provider_type']['types'], true));

// front end: a guessed URL to an entry is a 404 for visitors
$GLOBALS['wp_query'] = new class { public $is404 = false; function set_404() { $this->is404 = true; } };
$rid = ih_add_post(['post_type' => 'omx_resource', 'post_title' => '動画', 'post_status' => 'publish']);
$GLOBALS['ctx'] = ['is_singular' => true, 'queried' => get_post($rid)];
ih_as([]);
$GLOBALS['status_header'] = null;
omochix_core_resource_block_front_end();
check('front end: a visitor reaching an entry gets a 404', true === $GLOBALS['wp_query']->is404 && 404 === $GLOBALS['status_header']);
$GLOBALS['wp_query']->is404 = false; $GLOBALS['status_header'] = null;
ih_as(['edit_post']);
omochix_core_resource_block_front_end();
check('front end: an editor who can edit the entry may preview it', false === $GLOBALS['wp_query']->is404 && null === $GLOBALS['status_header']);
$GLOBALS['ctx'] = []; ih_as([]);
check('front end: the guard runs before other template_redirect callbacks', 0 === has_action('template_redirect', 'omochix_core_resource_block_front_end'));

// ---------------------------------------------------------------- sanitize
$s = 'omochix_core_sanitize_resource_meta_value';
check('url: https only', 'https://example.com/v?x=1' === $s('https://example.com/v?x=1', 'resource_url') && '' === $s('http://example.com/', 'resource_url'));
check('url: javascript:, data:, protocol-relative and hostless URLs are rejected', '' === $s('javascript:alert(1)', 'resource_url') && '' === $s('data:text/html,<b>x</b>', 'resource_url') && '' === $s('//example.com/x', 'resource_url') && '' === $s('https:///x', 'resource_url'));
check('url: credentials in the URL and whitespace are rejected', '' === $s('https://user:pw@example.com/', 'resource_url') && '' === $s('https://exa mple.com/', 'resource_url') && '' === $s(['https://example.com'], 'resource_url'));
check('enum: kind / basis / embed / lang / price fall back to empty', 'video' === $s('video', 'resource_kind') && '' === $s('podcast', 'resource_kind') && 'partner' === $s('partner', 'resource_license_basis') && '' === $s('all-rights', 'resource_license_basis') && '' === $s('vimeo', 'resource_embed_provider'));
check('text: summary strips tags and is capped at 160', false === strpos($s('<a href="x">紹介</a>', 'resource_summary'), '<') && 160 === mb_strlen($s(str_repeat('あ', 300), 'resource_summary')));
check('reference: a reference number is accepted', 'LIC-2026-014' === $s('LIC-2026-014', 'resource_permission_ref') && 'mail#2026/11/05' === $s('mail#2026/11/05', 'resource_permission_ref'));
check('reference: mail addresses, sentences and long text are refused', '' === $s('taro@example.com', 'resource_permission_ref') && '' === $s('山田さんから許諾', 'resource_permission_ref') && '' === $s('ok thanks', 'resource_permission_ref') && '' === $s(str_repeat('a', 65), 'resource_permission_ref'));
$img = ih_add_post(['post_type' => 'attachment']);
check('thumbnail: only an image attachment ID', $img === $s((string) $img, 'resource_thumbnail') && 0 === $s($rid, 'resource_thumbnail') && 0 === $s('abc', 'resource_thumbnail'));
check('unknown key: null', null === $s('x', 'resource_body'));

// ------------------------------------------------------------- auth / read
ih_as(['edit_post']);
check('auth: an author can write public fields but not the permission reference', true === omochix_core_auth_registered_resource_meta(false, 'resource_url', $rid) && false === omochix_core_auth_registered_resource_meta(false, 'resource_permission_ref', $rid));
ih_as(['edit_post', 'edit_posts', 'edit_others_posts']);
check('auth: an editor with edit_others_posts can write it', true === omochix_core_auth_registered_resource_meta(false, 'resource_permission_ref', $rid));
update_post_meta($rid, 'resource_permission_ref', 'LIC-1');
check('read: the reference is returned only when asked for and allowed', 'LIC-1' === omochix_core_get_resource_meta($rid, true)['resource_permission_ref'] && !array_key_exists('resource_permission_ref', omochix_core_get_resource_meta($rid, false)));
ih_as(['edit_post']);
check('read: …and never to users without edit_others_posts', !array_key_exists('resource_permission_ref', omochix_core_get_resource_meta($rid, true)));

// ------------------------------------------------------------------- card
foreach (['resource_url' => 'https://www.youtube.com/watch?v=abc', 'resource_kind' => 'video', 'resource_provider_name' => '制作者A', 'resource_summary' => '不動産写真の撮り方を短く解説する動画。', 'resource_license_basis' => 'link_only', 'resource_verified_at' => '2026-11-01'] as $k => $v) { update_post_meta($rid, $k, $v); }
wp_set_object_terms($rid, ['creator'], 'omx_provider_type');
$card = omochix_core_get_resource_card($rid);
check('card: public fields only, never the permission reference', is_array($card) && !array_key_exists('permission_ref', $card) && !in_array('LIC-1', $card, true) && 'https://www.youtube.com/watch?v=abc' === $card['url'] && 'creator' === $card['provider_type'] && false === $card['is_pr'] && 'noopener noreferrer' === $card['rel']);
ih_as(['edit_post', 'edit_posts', 'edit_others_posts']); // only editors may mark content as partner (PR)
wp_set_object_terms($rid, ['partner'], 'omx_provider_type');
check('card: partner content is PR with rel=sponsored', true === omochix_core_get_resource_card($rid)['is_pr'] && 'sponsored noopener noreferrer' === omochix_core_get_resource_card($rid)['rel']);
update_post_meta($rid, 'resource_thumbnail', ih_add_post(['post_type' => 'attachment']));
check('card: a live link-only entry edited to carry a thumbnail is no longer shown (rules re-applied on read)', null === omochix_core_get_resource_card($rid));
delete_post_meta($rid, 'resource_thumbnail');
update_post_meta($rid, 'resource_license_basis', '');
check('card: a live entry whose license basis was cleared is not shown', null === omochix_core_get_resource_card($rid));
update_post_meta($rid, 'resource_license_basis', 'link_only');
check('card: shown again once the record is complete', is_array(omochix_core_get_resource_card($rid)));
$GLOBALS['posts'][$rid]->post_status = 'draft';
check('card: an unpublished entry is never shown', null === omochix_core_get_resource_card($rid));
$GLOBALS['posts'][$rid]->post_status = 'publish';
check('card: a non-ledger post is never shown as a resource card', null === omochix_core_get_resource_card(ih_add_post(['post_type' => 'post'])));
check('stale: 180 days ok, 181 stale, missing stale', false === omochix_core_resource_is_stale('2026-01-01', strtotime('2026-01-01 00:00:00 UTC') + 180 * DAY_IN_SECONDS) && true === omochix_core_resource_is_stale('2026-01-01', strtotime('2026-01-01 00:00:00 UTC') + 181 * DAY_IN_SECONDS) && true === omochix_core_resource_is_stale(''));

// --------------------------------------------------------- publish guard
$draft = ih_add_post(['post_type' => 'omx_resource', 'post_status' => 'draft', 'post_title' => '記事']);
// $GLOBALS['ih_tax'] plays the tax_input core resolves into wp_insert_post()'s $postarr.
$guard = static function ($id, $status = 'publish', $title = '記事') { $arr = ['ID' => $id]; if (isset($GLOBALS['ih_tax'])) { $arr['tax_input'] = $GLOBALS['ih_tax']; } return omochix_core_resource_guard_insert_data(['post_type' => 'omx_resource', 'post_status' => $status, 'post_title' => $title], $arr); };
$_POST = [];
ih_as(['edit_post', 'edit_posts', 'edit_others_posts']);
check('guard: an empty entry cannot be published (held as draft, reasons recorded)', 'draft' === $guard($draft)['post_status'] && 'blocked' === get_transient('omochix_core_resource_guard_' . $draft)['type']);
$full = ['resource_url' => 'https://example.com/a', 'resource_kind' => 'article', 'resource_provider_name' => 'B社', 'resource_summary' => '紹介文', 'resource_license_basis' => 'link_only', 'resource_verified_at' => '2026-11-01'];
$_POST = ['omochix_core_resource_meta' => $full, 'omochix_core_resource_nonce' => 'nonce-omochix_core_save_resource'];
check('guard: a complete record without a provider is still held (unclassified is not enough)', 'draft' === $guard($draft)['post_status'] && false !== strpos(implode(' ', get_transient('omochix_core_resource_guard_' . $draft)['lines']), '提供元'));
$GLOBALS['ih_tax'] = ['omx_provider_type' => ['0', (string) ih_term('omx_provider_type', 'external-service')->term_id]];
$_POST['tax_input'] = ['omx_provider_type' => [(string) ih_term('omx_provider_type', 'creator')->term_id, (string) ih_term('omx_provider_type', 'official')->term_id]]; // raw POST is ignored
delete_transient('omochix_core_resource_guard_' . $draft);
check('guard: link_only + provider from this request → publish allowed', 'publish' === $guard($draft)['post_status'] && false === get_transient('omochix_core_resource_guard_' . $draft));
$_POST['omochix_core_resource_meta']['resource_license_basis'] = 'licensed';
check('guard: licensed needs license details and a permission reference', 'draft' === $guard($draft)['post_status'] && false !== strpos(implode(' ', get_transient('omochix_core_resource_guard_' . $draft)['lines']), '許諾の社内参照番号'));
$_POST['omochix_core_resource_meta'] += ['resource_license_detail' => '書面で許諾（2026-10）', 'resource_permission_ref' => 'LIC-2026-014'];
check('guard: licensed with details and reference → allowed', 'publish' === $guard($draft)['post_status']);
$_POST['omochix_core_resource_meta']['resource_license_basis'] = 'link_only';
$_POST['omochix_core_resource_meta']['resource_thumbnail'] = (string) $img;
check('guard: a thumbnail on a link-only entry is refused', 'draft' === $guard($draft)['post_status']);
$_POST['omochix_core_resource_meta']['resource_thumbnail'] = '';
$_POST['omochix_core_resource_meta']['resource_embed_provider'] = 'youtube';
check('guard: an embed without the official_embed basis is refused', 'draft' === $guard($draft)['post_status']);
$_POST['omochix_core_resource_meta']['resource_license_basis'] = 'official_embed';
check('guard: official_embed with YouTube and details → allowed', 'publish' === $guard($draft)['post_status']);
$_POST['omochix_core_resource_meta']['resource_embed_provider'] = '';
check('guard: official_embed without a provider is refused', 'draft' === $guard($draft)['post_status']);
$_POST['omochix_core_resource_meta']['resource_license_basis'] = 'partner';
$_POST['omochix_core_resource_meta']['resource_embed_provider'] = '';
check('guard: partner content must use the partner (PR) provider', 'draft' === $guard($draft)['post_status'] && false !== strpos(implode(' ', get_transient('omochix_core_resource_guard_' . $draft)['lines']), 'パートナー'));
$_POST['omochix_core_resource_meta']['resource_license_basis'] = 'link_only';
$_POST['omochix_core_resource_meta']['resource_summary'] = 'token: ' . 'ghp_' . str_repeat('a', 30);
check('guard: a secret-looking string blocks publishing', 'draft' === $guard($draft)['post_status']);
$_POST['omochix_core_resource_meta']['resource_summary'] = '紹介文';
$saved_nonce = $_POST['omochix_core_resource_nonce'];
$_POST['omochix_core_resource_nonce'] = 'forged';
check('guard: without the box nonce the submitted values do not count (stored record is empty → held)', 'draft' === $guard($draft)['post_status']);
$_POST['omochix_core_resource_nonce'] = $saved_nonce;
ih_as(['edit_post', 'edit_posts']);
$_POST['omochix_core_resource_meta']['resource_license_basis'] = 'licensed';
check('guard: an author cannot satisfy "licensed" with a reference the save handler will drop', 'draft' === $guard($draft)['post_status'] && false !== strpos(implode(' ', get_transient('omochix_core_resource_guard_' . $draft)['lines']), '許諾の社内参照番号'));
ih_as(['edit_post', 'edit_posts', 'edit_others_posts']);
$_POST['omochix_core_resource_meta']['resource_license_basis'] = 'link_only';
$GLOBALS['ih_tax'] = ['omx_provider_type' => [(string) ih_term('omx_provider_type', 'external-service')->term_id, (string) ih_term('omx_provider_type', 'creator')->term_id]];
check('guard: bulk edit merging a second provider into tax_input is treated as unclassified', 'draft' === $guard($draft)['post_status']);
$GLOBALS['ih_tax'] = ['omx_provider_type' => [['nested']]];
check('guard: nested tax_input values are ignored without warnings', 'draft' === $guard($draft)['post_status']);
$GLOBALS['ih_tax'] = ['omx_provider_type' => ['0', (string) ih_term('omx_provider_type', 'external-service')->term_id]];
ih_as(['edit_post', 'edit_posts']);
$GLOBALS['ih_tax'] = ['omx_provider_type' => [(string) ih_term('omx_provider_type', 'official')->term_id]];
$_POST['omochix_core_resource_meta']['resource_license_basis'] = 'link_only';
check('guard: an author posting "official" is judged as unclassified (it would be stripped after the guard)', 'draft' === $guard($draft)['post_status']);
ih_as([]);
$GLOBALS['ih_tax'] = ['omx_provider_type' => [(string) ih_term('omx_provider_type', 'external-service')->term_id]];
check('guard: tax_input from a user who cannot assign terms is ignored (core will not save it)', 'draft' === $guard($draft)['post_status']);
ih_as(['edit_post', 'edit_posts', 'edit_others_posts']);
$GLOBALS['ih_tax'] = ['omx_provider_type' => ['0', (string) ih_term('omx_provider_type', 'external-service')->term_id]];
check('guard: a complete record may be scheduled (future)', 'future' === $guard($draft, 'future')['post_status']);
$_POST['omochix_core_resource_meta']['resource_verified_at'] = '';
check('guard: scheduling (future) is guarded like publish', 'draft' === $guard($draft, 'future')['post_status']);
$_POST['omochix_core_resource_meta']['resource_verified_at'] = '2026-11-01';
$_POST = []; unset($GLOBALS['ih_tax']);
$live = ih_add_post(['post_type' => 'omx_resource', 'post_status' => 'publish']);
check('guard: an entry that is already published is never demoted (warning only)', 'publish' === $guard($live)['post_status'] && 'warn' === get_transient('omochix_core_resource_guard_' . $live)['type']);
check('guard: drafts and other post types are not touched', 'draft' === $guard($draft, 'draft')['post_status'] && 'publish' === omochix_core_resource_guard_insert_data(['post_type' => 'post', 'post_status' => 'publish'], ['ID' => 0])['post_status']);
$_GET = ['post' => (string) $draft];
set_transient('omochix_core_resource_guard_' . $draft, ['type' => 'blocked', 'lines' => ['<b>x</b>']]);
ob_start(); omochix_core_resource_guard_notice(); $n = ob_get_clean();
check('guard notice: shown once, escaped', false !== strpos($n, '&lt;b&gt;') && false === strpos($n, '<b>x') && false === get_transient('omochix_core_resource_guard_' . $draft));
$_GET = [];

// ---------------------------------------------------------- save handler
$e = ih_add_post(['post_type' => 'omx_resource', 'post_status' => 'draft']);
$_POST = ['omochix_core_resource_meta' => ['resource_url' => 'https://example.com/x', 'resource_permission_ref' => 'LIC-9']];
ih_as(['edit_post', 'edit_posts', 'edit_others_posts']);
omochix_core_save_resource_meta($e);
check('save: no nonce → nothing written', [] === get_post_meta($e));
$_POST['omochix_core_resource_nonce'] = 'nonce-something_else';
omochix_core_save_resource_meta($e);
check('save: wrong nonce → nothing written', [] === get_post_meta($e));
$_POST['omochix_core_resource_nonce'] = 'nonce-omochix_core_save_resource';
ih_as([]);
omochix_core_save_resource_meta($e);
check('save: no edit_post → nothing written', [] === get_post_meta($e));
ih_as(['edit_post']);
omochix_core_save_resource_meta($e);
check('save: an author saves public fields, the permission reference is ignored', 'https://example.com/x' === get_post_meta($e, 'resource_url', true) && '' === get_post_meta($e, 'resource_permission_ref', true));
ih_as(['edit_post', 'edit_posts', 'edit_others_posts']);
$_POST['omochix_core_resource_meta'] = ['resource_permission_ref' => 'LIC-9', 'resource_url' => 'javascript:alert(1)', 'resource_attribution' => 'AKIA' . str_repeat('A', 16)];
omochix_core_save_resource_meta($e);
check('save: an editor saves the reference; an unsafe URL is cleared; a secret is never stored', 'LIC-9' === get_post_meta($e, 'resource_permission_ref', true) && '' === get_post_meta($e, 'resource_url', true) && '' === get_post_meta($e, 'resource_attribution', true));
check('save: a different post type is ignored', (static function () { $p = ih_add_post(['post_type' => 'post']); omochix_core_save_resource_meta($p); return [] === get_post_meta($p); })());
$_POST = [];

// ------------------------------------------------------------------ render
update_post_meta($e, 'resource_summary', '"><script>alert(1)</script>');
ih_as(['edit_post']);
ob_start(); omochix_core_render_resource_meta_box(get_post($e)); $box = ob_get_clean();
check('box: nonce printed, values escaped', false !== strpos($box, 'value="nonce-omochix_core_save_resource"') && false === strpos($box, '<script>'));
check('box: authors without edit_others_posts do not see the permission reference', false === strpos($box, 'resource_permission_ref') && false === strpos($box, 'LIC-9'));
ih_as(['edit_post', 'edit_posts', 'edit_others_posts']);
ob_start(); omochix_core_render_resource_meta_box(get_post($e)); $box = ob_get_clean();
check('box: editors see it, with autocomplete off', false !== strpos($box, 'name="omochix_core_resource_meta[resource_permission_ref]"') && false !== strpos($box, 'autocomplete="off"') && false !== strpos($box, 'LIC-9'));
check('box: every license basis is offered with its explanation', 6 === count(array_filter(array_keys(omochix_core_get_resource_license_labels()), static fn($k) => false !== strpos($box, 'value="' . $k . '"'))));

ih_done();
