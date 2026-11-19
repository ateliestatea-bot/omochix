<?php
/**
 * Isolation probe (TEST ONLY, never shipped). Tries what a broken or
 * tampered demo might try; results go to window.__probe and #probe-out.
 * demo.js sits next to this file and is inlined by the endpoint.
 */
defined('ABSPATH') || exit;
$omx_site = home_url('/');
?>
<main id="probe">
    <h1>Isolation probe</h1>
    <pre id="probe-out">running</pre>
    <form id="probe-form" method="post" action="<?php echo esc_url($omx_site . 'wp-admin/admin-post.php'); ?>"><input type="hidden" name="action" value="probe"></form>
    <a id="probe-top" href="<?php echo esc_url($omx_site . 'wp-admin/'); ?>" target="_top">top</a>
    <img id="probe-img" alt="" width="1" height="1">
</main>
