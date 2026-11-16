<?php
/**
 * D6: article -> tool links use the longest tool name; a shorter name inside it counts only when it also stands alone.
 * Dev-environment test. Usage: php tests/nav-ux/article-tools.php /path/to/wp-load.php   (creates/reuses fixtures named d6-*)
 */
require $argv[1];
$fx = static function ($slug, $title, $type = 'post', $tags = []) {
    $e = get_posts(['name' => $slug, 'post_type' => $type, 'post_status' => 'any', 'numberposts' => 1]);
    $id = $e ? $e[0]->ID : wp_insert_post(['post_name' => $slug, 'post_title' => $title, 'post_type' => $type, 'post_status' => 'publish', 'post_content' => 'x']);
    if ($tags) { wp_set_post_tags($id, $tags); }
    return $id;
};
$fx('claude-code', 'Claude Code', 'ai_tool'); $fx('microsoft-copilot', 'Microsoft Copilot', 'ai_tool');
omochix_suggest_flush();
$cases = [
    ['d6-cc-only', 'Claude Code × Sentry MCP完全ガイド', ['AI開発', 'Claude Code', 'MCP'], ['Claude Code']],
    ['d6-both', 'Grok 4.7正式リリース、GPT・Claudeとの競争へ', ['Claude Code'], ['Claude Code', 'Claude']],
    ['d6-gemini', 'Gemini Intelligenceが新機能とともに拡大展開へ', ['Android'], ['Gemini']],
    ['d6-none', 'NVIDIA cuDSS、一般提供開始', ['CUDA', 'GPU'], []],
    ['d6-studio', '大学のAIエージェントが講座資料を要約', ['Microsoft Copilot Studio'], ['Microsoft Copilot']],   // documented borderline: a product family name
];
$fail = 0;
foreach ($cases as [$slug, $title, $tags, $want]) {
    $got = wp_list_pluck(omochix_article_mentioned_tools($fx($slug, $title, 'post', $tags)), 'title');
    $ok = $got === $want; $fail += $ok ? 0 : 1;
    printf("%s %s => [%s]%s\n", $ok ? 'PASS' : 'FAIL', $title, implode(', ', $got), $ok ? '' : ' (expected ' . implode(', ', $want) . ')');
}
echo $fail ? "$fail failed\n" : "all passed\n"; exit($fail ? 1 : 0);
