<?php
/**
 * AI glossary: keyword filter (on the page, nothing is sent) and category links.
 *
 * Without JavaScript every term stays listed and the category links work;
 * the script only hides cards that do not match. The script is static: no
 * value from the database or the request is written into it.
 *
 * @package OmochiX
 *
 * @var array $args ['categories' => WP_Term[], 'current' => ?WP_Term, 'index_url' => string]
 */

if (!defined('ABSPATH')) {
    exit;
}
$omochix_cats    = (array) ($args['categories'] ?? []);
$omochix_current = $args['current'] ?? null;
$omochix_index   = (string) ($args['index_url'] ?? '');
?>
<div class="glossary-tools">
    <div class="glossary-search" role="search">
        <label class="glossary-search__label" for="glossary-filter"><?php esc_html_e('用語をしぼり込む', 'omochix'); ?></label>
        <input class="glossary-search__input" id="glossary-filter" type="search" autocomplete="off" spellcheck="false" maxlength="40" aria-describedby="glossary-filter-status" placeholder="<?php esc_attr_e('例：RAG、とーくん、Transformer', 'omochix'); ?>" hidden>
        <p class="glossary-search__status" id="glossary-filter-status" aria-live="polite"></p>
    </div>
    <?php if ($omochix_cats) : ?>
        <nav class="glossary-cats" aria-label="<?php esc_attr_e('用語のカテゴリ', 'omochix'); ?>">
            <ul>
                <li><a href="<?php echo esc_url($omochix_index); ?>"<?php echo $omochix_current ? '' : ' aria-current="page"'; ?>><?php esc_html_e('すべて', 'omochix'); ?></a></li>
                <?php foreach ($omochix_cats as $omochix_c) :
                    $omochix_l = get_term_link($omochix_c);
                    if (!is_string($omochix_l)) { continue; } ?>
                    <li><a href="<?php echo esc_url($omochix_l); ?>"<?php echo ($omochix_current instanceof WP_Term && $omochix_current->term_id === $omochix_c->term_id) ? ' aria-current="page"' : ''; ?>><?php echo esc_html($omochix_c->name); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>
<script>
(function () {
    var input = document.getElementById('glossary-filter');
    var status = document.getElementById('glossary-filter-status');
    if (!input || !status || !('normalize' in String.prototype)) { return; }
    var cards = Array.prototype.slice.call(document.querySelectorAll('.glossary-card[data-glossary-text]'));
    var groups = Array.prototype.slice.call(document.querySelectorAll('.glossary-group'));
    var norm = function (s) {
        // Width, case and katakana -> hiragana, so "トークン", "とーくん" and "ﾄｰｸﾝ" match.
        return s.normalize('NFKC').toLowerCase().replace(/[ァ-ヶ]/g, function (c) { return String.fromCharCode(c.charCodeAt(0) - 0x60); }).trim();
    };
    var texts = cards.map(function (c) { return norm(c.getAttribute('data-glossary-text') || ''); });
    var timer = null;
    var run = function () {
        var q = norm(input.value).slice(0, 40);
        var shown = 0;
        cards.forEach(function (c, i) {
            var hit = '' === q || -1 !== texts[i].indexOf(q);
            c.hidden = !hit;
            if (hit) { shown++; }
        });
        groups.forEach(function (g) {
            g.hidden = !g.querySelector('.glossary-card:not([hidden])');
        });
        status.textContent = '' === q ? '' : (shown ? shown + '件の用語が見つかりました' : '一致する用語はありません');
    };
    input.hidden = false;
    input.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(run, 120); });
    input.addEventListener('keydown', function (e) { if ('Escape' === e.key && input.value) { input.value = ''; run(); } });
}());
</script>
