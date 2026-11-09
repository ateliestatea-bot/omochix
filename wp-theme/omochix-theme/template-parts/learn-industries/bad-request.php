<?php
/**
 * 400 answer: the conditions in the URL are not valid. No list is shown.
 *
 * Args: reset (URL), index (URL).
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}
$omochix_reset = (string) ($args['reset'] ?? '');
$omochix_index = (string) ($args['index'] ?? '');
?>
<section class="ih-message" role="alert" aria-labelledby="ih-bad-request-title">
    <h2 id="ih-bad-request-title"><?php esc_html_e('指定された条件が正しくありません', 'omochix'); ?></h2>
    <p><?php esc_html_e('URL の絞り込み条件に、使えない値が含まれています。条件を外して表示し直してください。', 'omochix'); ?></p>
    <p class="ih-message__actions">
        <?php if ($omochix_reset) : ?><a class="ih-button" href="<?php echo esc_url($omochix_reset); ?>"><?php esc_html_e('条件を外して表示', 'omochix'); ?></a><?php endif; ?>
        <?php if ($omochix_index && $omochix_index !== $omochix_reset) : ?><a class="ih-link" href="<?php echo esc_url($omochix_index); ?>"><?php esc_html_e('業種の一覧へ', 'omochix'); ?></a><?php endif; ?>
    </p>
</section>
