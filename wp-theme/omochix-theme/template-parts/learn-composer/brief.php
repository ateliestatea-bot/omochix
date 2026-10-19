<?php
/**
 * Composer brief: the implementation instructions (Markdown) generated
 * from the composition, with the assistant switch, copy / download and the
 * JSON import/export dialog. Filled by learn-composer.js; hidden while the
 * composition is empty.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

$omochix_assistants = omochix_learn_composer_assistants();
?>
<section class="motion-composer__brief" data-composer-brief hidden aria-labelledby="composer-brief-title">
    <div class="motion-composer__toolbar">
        <h2 id="composer-brief-title" class="motion-composer__title"><?php esc_html_e('AIへの実装指示書', 'omochix'); ?></h2>
        <p class="motion-composer__hint"><?php esc_html_e('見本の仕様・調整した値・コードをひとつの指示書にまとめます。コピーして AI に渡してください。', 'omochix'); ?></p>
    </div>
    <div class="motion-composer__brief-options" role="radiogroup" aria-label="<?php esc_attr_e('渡す相手', 'omochix'); ?>">
        <?php $omochix_first = true; foreach ($omochix_assistants as $omochix_key => $omochix_assistant) : ?>
            <label><input type="radio" name="composer-assistant" value="<?php echo esc_attr($omochix_key); ?>" data-composer-assistant <?php checked($omochix_first); ?>> <?php echo esc_html($omochix_assistant['label']); ?></label>
            <?php $omochix_first = false; ?>
        <?php endforeach; ?>
    </div>
    <pre class="motion-composer__brief-text" data-composer-brief-text tabindex="0" aria-label="<?php esc_attr_e('指示書の本文', 'omochix'); ?>"></pre>
    <div class="motion-composer__brief-actions">
        <button type="button" class="motion-copy" data-composer-brief-copy><?php esc_html_e('指示書をコピー', 'omochix'); ?></button>
        <button type="button" class="motion-copy motion-copy--ghost" data-composer-brief-download><?php esc_html_e('.md として保存', 'omochix'); ?></button>
        <button type="button" class="motion-copy motion-copy--ghost" data-composer-json-copy><?php esc_html_e('組み合わせを JSON でコピー', 'omochix'); ?></button>
        <button type="button" class="motion-copy motion-copy--ghost" data-composer-json-open aria-haspopup="dialog"><?php esc_html_e('JSON を貼り付けて読み込む', 'omochix'); ?></button>
        <p class="sr-only" data-omx-copy-status aria-live="polite"></p>
    </div>
    <dialog class="motion-composer__json" data-composer-json-dialog aria-labelledby="composer-json-title">
        <form method="dialog" class="motion-composer__json-frame" data-composer-json-form>
            <h3 id="composer-json-title" class="motion-composer__title"><?php esc_html_e('JSON を貼り付けて読み込む', 'omochix'); ?></h3>
            <label class="sr-only" for="composer-json-text"><?php esc_html_e('組み合わせの JSON', 'omochix'); ?></label>
            <textarea id="composer-json-text" data-composer-json-text spellcheck="false"></textarea>
            <p class="motion-composer__json-error" data-composer-json-error role="alert" hidden></p>
            <div class="motion-composer__actions">
                <button type="submit" class="motion-copy" value="load" data-composer-json-load><?php esc_html_e('読み込む', 'omochix'); ?></button>
                <button type="submit" class="motion-copy motion-copy--ghost" value="cancel"><?php esc_html_e('やめる', 'omochix'); ?></button>
            </div>
        </form>
    </dialog>
</section>
