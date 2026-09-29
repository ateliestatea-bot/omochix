<?php
/**
 * AI Tool detail page v2: rich-UI helpers shared by every ai_tool post.
 *
 * Everything here reads generic post data (post_content headings, the
 * key_features/recommended_use_cases meta arrays) and contains no
 * slug-specific or tool-specific branching. The same functions run
 * identically for all 52 AI Tools.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Extract every <h2>...</h2> heading from an AI Tool's post_content, in
 * document order, each paired with the anchor id that
 * omochix_inject_ai_tool_heading_ids() gives the matching rendered heading.
 * Both functions share the exact same slugify+dedupe logic below, so a TOC
 * built from this list always points at real ids in the rendered page.
 *
 * @param string $content Raw post_content (before wpautop/the_content filters).
 * @return array<int, array{id: string, text: string}>
 */
function omochix_get_ai_tool_toc_headings($content) {
    if ('' === trim((string) $content)) {
        return [];
    }

    $headings = [];
    $seen_ids = [];
    if (preg_match_all('/<h2[^>]*>(.*?)<\/h2>/is', $content, $matches)) {
        foreach ($matches[1] as $raw_text) {
            $text = trim(wp_strip_all_tags($raw_text));
            if ('' === $text) {
                continue;
            }
            $base_id = sanitize_title($text) ?: 'section';
            $id = $base_id;
            $suffix = 2;
            while (isset($seen_ids[$id])) {
                $id = $base_id . '-' . $suffix;
                ++$suffix;
            }
            $seen_ids[$id] = true;
            $headings[] = ['id' => $id, 'text' => $text];
        }
    }

    return $headings;
}

/**
 * Give every rendered <h2> in an AI Tool's body an anchor id, so the sticky
 * table of contents (built from the same heading list, see above) always
 * has something real to scroll to. A heading that already carries an id
 * (an editor set one deliberately, or a block variant already adds one) is
 * left untouched.
 *
 * Scoped to is_singular('ai_tool') only; every other post type's content
 * (News, Prompts, Pages) is returned unmodified.
 *
 * @param string $content Filtered post content.
 * @return string
 */
function omochix_inject_ai_tool_heading_ids($content) {
    if (!is_singular('ai_tool') || !in_the_loop() || !is_main_query()) {
        return $content;
    }
    if (false === stripos($content, '<h2')) {
        return $content;
    }

    $seen_ids = [];
    $result = preg_replace_callback(
        '/<h2([^>]*)>(.*?)<\/h2>/is',
        static function ($match) use (&$seen_ids) {
            // Respect an id the editor (or a block) already set.
            if (preg_match('/\bid=/i', $match[1])) {
                return $match[0];
            }
            $text = trim(wp_strip_all_tags($match[2]));
            if ('' === $text) {
                return $match[0];
            }
            $base_id = sanitize_title($text) ?: 'section';
            $id = $base_id;
            $suffix = 2;
            while (isset($seen_ids[$id])) {
                $id = $base_id . '-' . $suffix;
                ++$suffix;
            }
            $seen_ids[$id] = true;
            return '<h2' . $match[1] . ' id="' . esc_attr($id) . '">' . $match[2] . '</h2>';
        },
        $content
    );

    return null !== $result ? $result : $content;
}
add_filter('the_content', 'omochix_inject_ai_tool_heading_ids', 9);

/**
 * Split one key_features line into a card title + short description.
 *
 * The Content Updater workflow's own convention (see
 * docs/content-drafts/heygen-ai-tool-content-v1.md) writes each entry as
 * "Feature name：description" (a full-width colon) or "Feature name:
 * description" (an ASCII colon). Only the FIRST colon in the string is
 * treated as the separator, so a description that itself contains a colon
 * is never mis-split. A line with no colon at all (or where splitting would
 * leave either side empty) is not a parsing failure -- it is simply shown
 * as a title-only card, which is exactly as valid a card as any other.
 *
 * @param string $line One key_features array entry.
 * @return array{title: string, desc: string}
 */
function omochix_parse_ai_tool_feature_line($line) {
    $line = trim((string) $line);
    $pos = false;
    foreach (['：', ':'] as $separator) {
        $candidate = mb_strpos($line, $separator);
        if (false !== $candidate && (false === $pos || $candidate < $pos)) {
            $pos = $candidate;
        }
    }

    if (false === $pos) {
        return ['title' => $line, 'desc' => ''];
    }

    $title = trim(mb_substr($line, 0, $pos));
    $desc  = trim(mb_substr($line, $pos + 1));

    if ('' === $title || '' === $desc) {
        return ['title' => $line, 'desc' => ''];
    }

    return ['title' => $title, 'desc' => $desc];
}

/**
 * Map a free-text use-case/recommendation label to one of the existing
 * omx-icon glyphs (see the --omx-icon-* custom properties in style.css),
 * by keyword. Every branch here is a generic category name, never a tool
 * name, so this applies identically to any AI Tool's own wording. A label
 * that matches nothing gets the neutral "dot" glyph rather than guessing --
 * an unstyled-looking icon is a far smaller problem than a wrong one.
 *
 * @param string $label One recommended_use_cases (or recommended_for) entry.
 * @return string One of the omx-icon--* modifier suffixes.
 */
function omochix_get_ai_tool_use_case_icon($label) {
    $label = (string) $label;
    // Order matters: more specific business-intent keywords are checked
    // first. "chat" (SNS/動画/配信) is deliberately last among the real
    // matches, since "動画" alone appears in almost every use-case label a
    // video-generation tool writes and would otherwise swallow every other
    // category before a more specific keyword got a chance to match.
    $map = [
        'shield' => ['研修', '教育', 'トレーニング', 'オンボーディング', 'コンプライアンス', 'セキュリティ'],
        'coin'   => ['マーケティング', '広告', 'マーケ', 'セールス', '営業', '販促'],
        'chart'  => ['分析', 'レポート', 'データ'],
        'plug'   => ['連携', '自動化', 'ワークフロー', 'API', '統合'],
        'flask'  => ['開発', 'プログラミング', 'コード', 'テスト', '検証'],
        'bolt'   => ['生産性', '効率', '時短', '業務効率'],
        'chat'   => ['SNS', 'YouTube', '動画', '配信', '投稿'],
    ];
    foreach ($map as $icon => $keywords) {
        foreach ($keywords as $keyword) {
            if ('' !== $keyword && false !== mb_stripos($label, $keyword)) {
                return $icon;
            }
        }
    }
    return 'dot';
}
