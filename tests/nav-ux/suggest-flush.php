<?php
/** D5: revisions/autosaves do not rebuild the suggest index; real content changes do. Usage: php tests/nav-ux/suggest-flush.php /path/to/wp-load.php */
require $argv[1];
require_once ABSPATH . 'wp-admin/includes/post.php';
wp_set_current_user(get_users(['role' => 'administrator', 'number' => 1])[0]->ID);
$p = get_posts(['post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 1])[0];
$have = static fn() => false !== get_transient(OMOCHIX_SUGGEST_TRANSIENT);
$r = [];
omochix_suggest_flush(); omochix_suggest_index(); $r['built'] = $have();
wp_update_post(['ID' => $p->ID, 'post_content' => $p->post_content . ' ']);  // creates a revision too (save_post fires for both)
$r['after real update: flushed'] = !$have();
omochix_suggest_index();
$rev = wp_save_post_revision($p->ID) ?: _wp_put_post_revision(['ID' => $p->ID, 'post_title' => $p->post_title, 'post_content' => $p->post_content . 'r'], false);
$r['after revision-only save: kept'] = $have();
$auto = wp_create_post_autosave(['post_ID' => $p->ID, 'post_title' => $p->post_title, 'post_content' => $p->post_content . ' autosave', 'post_type' => 'post']);
$r['after autosave: kept'] = $have();
wp_update_post(['ID' => $p->ID, 'post_content' => rtrim($p->post_content)]);   // restore
$ok = $r['built'] && $r['after real update: flushed'] && $r['after revision-only save: kept'] && $r['after autosave: kept'];
echo json_encode($r, JSON_UNESCAPED_UNICODE), "\n", $ok ? "PASS\n" : "FAIL\n"; exit($ok ? 0 : 1);
