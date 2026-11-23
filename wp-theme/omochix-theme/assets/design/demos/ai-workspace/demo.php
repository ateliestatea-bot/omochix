<?php
/**
 * Design Library demo: AI Workspace UI. Markup only; rendered by the isolated demo endpoint.
 * A fictional research app ("TOIMORI"); the city, documents and numbers are fictional.
 * No AI is called and nothing typed here leaves the page: replies are prepared text.
 * The whole conversation is in the markup, so it reads without JavaScript.
 */
defined('ABSPATH') || exit;
?>
<div class="aw" data-aw>
    <header class="aw-top">
        <button class="aw-icon aw-only-tablet" type="button" data-aw-nav aria-controls="aw-side" aria-expanded="false" aria-label="スレッド一覧を開く">
            <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M3 5h14M3 10h14M3 15h14"/></svg>
        </button>
        <span class="aw-brand"><span class="aw-brand__mark" aria-hidden="true"></span>TOIMORI<span class="aw-brand__note">架空の AI リサーチ</span></span>
        <button class="aw-search" type="button" data-aw-open-palette aria-haspopup="dialog">
            <svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="9" cy="9" r="5.5"/><path d="M13 13l4 4"/></svg>
            <span>コマンドを検索</span><kbd aria-hidden="true">⌘K</kbd>
        </button>
        <button class="aw-icon" type="button" data-aw-theme aria-label="ダークテーマに切り替える">
            <svg class="aw-ico-moon" viewBox="0 0 20 20" aria-hidden="true"><path d="M15.5 12.5A6.5 6.5 0 0 1 7.5 4.5a6.5 6.5 0 1 0 8 8z"/></svg>
            <svg class="aw-ico-sun" viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="3.5"/><path d="M10 2v2M10 16v2M2 10h2M16 10h2M4.3 4.3l1.4 1.4M14.3 14.3l1.4 1.4M4.3 15.7l1.4-1.4M14.3 5.7l1.4-1.4"/></svg>
        </button>
        <button class="aw-icon aw-only-wide" type="button" data-aw-panel aria-controls="aw-panel" aria-expanded="true" aria-label="出典パネルを閉じる">
            <svg viewBox="0 0 20 20" aria-hidden="true"><rect x="3" y="4" width="14" height="12" rx="2"/><path d="M12 4v12"/></svg>
        </button>
    </header>

    <nav class="aw-side" id="aw-side" aria-label="スレッド" data-aw-view="threads">
        <button class="aw-new" type="button" data-aw-new>＋ 新しい調査</button>
        <h2 class="aw-side__h">プロジェクト</h2>
        <ul class="aw-list">
            <li><a href="#aw-main" class="is-current" aria-current="page"><span class="aw-dot aw-dot--a" aria-hidden="true"></span>図書館の利用傾向</a></li>
            <li><a href="#aw-main"><span class="aw-dot aw-dot--b" aria-hidden="true"></span>公共施設の開館時間</a></li>
            <li><a href="#aw-main"><span class="aw-dot aw-dot--c" aria-hidden="true"></span>アンケート設計</a></li>
        </ul>
        <h2 class="aw-side__h">最近のスレッド</h2>
        <ul class="aw-list aw-list--threads">
            <li><a href="#aw-main" class="is-current" aria-current="page">来館者が増えた理由<small>今日</small></a></li>
            <li><a href="#aw-main">平日夜の利用者層<small>昨日</small></a></li>
            <li><a href="#aw-main">学習室の混雑<small>3 日前</small></a></li>
        </ul>
        <p class="aw-side__foot">架空のアプリの見本です。AI には接続しておらず、入力内容はどこにも送信されません。</p>
    </nav>

    <main class="aw-main" id="aw-main" data-aw-view="chat">
        <div class="aw-thread__head">
            <p class="aw-crumb">図書館の利用傾向 / スレッド</p>
            <h1>来館者が増えた理由</h1>
            <p class="aw-meta"><span>出典 3 件</span><span>みなと市（架空）</span></p>
        </div>
        <div class="aw-log" data-aw-log aria-live="polite" aria-relevant="additions">
            <div class="aw-msg aw-msg--user">
                <p class="aw-who">あなた</p>
                <p>みなと市立図書館（架空）の来館者が 2025 年に増えた理由を、手元の資料から整理して。</p>
            </div>
            <article class="aw-msg aw-msg--ai" aria-label="TOIMORI の回答">
                <p class="aw-who"><span class="aw-brand__mark" aria-hidden="true"></span>TOIMORI</p>
                <p>資料からは、主な理由が 2 つ読み取れます。</p>
                <p><strong>1. 平日の開館時間を 20 時まで延ばした。</strong>延長後の 4〜12 月は、18 時以降の来館が前年の同じ時期より 41% 多くなっています。<button class="aw-cite" type="button" data-aw-cite="1" aria-label="出典 1：来館者統計 2025">1</button></p>
                <p><strong>2. 学習室を予約制にした。</strong>アンケートでは「席が確実に取れる」を理由に挙げた人が最も多く、回答者の 38% でした。<button class="aw-cite" type="button" data-aw-cite="2" aria-label="出典 2：利用者アンケート">2</button></p>
                <div class="aw-table" role="region" aria-label="時間帯別の来館者（架空）" tabindex="0">
                    <table>
                        <caption>時間帯別の来館者（月平均・架空の数値）</caption>
                        <thead><tr><th scope="col">時間帯</th><th scope="col">2024</th><th scope="col">2025</th><th scope="col">差</th></tr></thead>
                        <tbody>
                            <tr><th scope="row">9〜12 時</th><td>3,120</td><td>3,180</td><td>+2%</td></tr>
                            <tr><th scope="row">12〜18 時</th><td>5,840</td><td>6,010</td><td>+3%</td></tr>
                            <tr><th scope="row">18〜20 時</th><td>1,260</td><td>1,780</td><td class="aw-up">+41%</td></tr>
                        </tbody>
                    </table>
                </div>
                <p>開館時間の変更記録<button class="aw-cite" type="button" data-aw-cite="3" aria-label="出典 3：開館時間の変更記録">3</button>と合わせると、夜の利用が全体の増加の大半を占めています。</p>
                <div class="aw-follow" aria-label="続けて聞く">
                    <button type="button" class="aw-chip" data-aw-suggest="夜の利用者の年代を比べて">夜の利用者の年代を比べて</button>
                    <button type="button" class="aw-chip" data-aw-suggest="予約制の不満点は？">予約制の不満点は？</button>
                </div>
            </article>
        </div>
        <div class="aw-empty" data-aw-empty hidden>
            <p class="aw-empty__h">新しい調査</p>
            <p>資料を選んで質問すると、出典の番号付きで答えます（見本の返答です）。</p>
            <div class="aw-follow">
                <button type="button" class="aw-chip" data-aw-suggest="開館時間の変更点を要約して">開館時間の変更点を要約して</button>
                <button type="button" class="aw-chip" data-aw-suggest="アンケートの自由記述を分類して">アンケートの自由記述を分類して</button>
            </div>
        </div>
        <div class="aw-composer" data-aw-composer>
            <label class="aw-sr" for="aw-input">質問を入力</label>
            <textarea id="aw-input" rows="1" maxlength="400" placeholder="資料について質問する（送信はされません）" data-aw-input></textarea>
            <div class="aw-composer__bar">
                <span class="aw-hint" id="aw-hint">Enter で送る・Shift+Enter で改行</span>
                <button class="aw-send" type="button" data-aw-send aria-describedby="aw-hint">
                    <span data-aw-send-label>送る</span>
                    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11M11 5l5 5-5 5"/></svg>
                </button>
            </div>
        </div>
    </main>

    <aside class="aw-panel" id="aw-panel" aria-label="出典とメモ" data-aw-view="sources">
        <div class="aw-tabs" role="tablist" aria-label="パネルの内容">
            <button role="tab" type="button" id="aw-tab-src" aria-controls="aw-src" aria-selected="true">出典</button>
            <button role="tab" type="button" id="aw-tab-memo" aria-controls="aw-memo" aria-selected="false" tabindex="-1">メモ</button>
        </div>
        <section id="aw-src" role="tabpanel" aria-labelledby="aw-tab-src">
            <ol class="aw-sources">
                <li class="aw-src" id="aw-src-1" tabindex="-1">
                    <p class="aw-src__n">1</p>
                    <p class="aw-src__t">来館者統計 2025（架空）</p>
                    <p class="aw-src__m">表計算 · 12 シート · 更新 2026-01-10</p>
                    <blockquote>18〜20 時の来館は月平均 1,780 人。前年同期は 1,260 人。</blockquote>
                </li>
                <li class="aw-src" id="aw-src-2" tabindex="-1">
                    <p class="aw-src__n">2</p>
                    <p class="aw-src__t">利用者アンケート（架空）</p>
                    <p class="aw-src__m">PDF · 回答 612 件</p>
                    <blockquote>来館が増えた理由：「席が確実に取れる」38%、「夜も開いている」33%。</blockquote>
                </li>
                <li class="aw-src aw-src--error" id="aw-src-3" tabindex="-1" data-aw-src-error>
                    <p class="aw-src__n">3</p>
                    <p class="aw-src__t">開館時間の変更記録（架空）</p>
                    <p class="aw-src__m">文書 · 4 ページ</p>
                    <p class="aw-src__err" data-aw-err-text>プレビューを読み込めませんでした（見本のエラー表示）。</p>
                    <blockquote class="aw-src__ok">2025 年 4 月 1 日から、平日の閉館を 18 時から 20 時に変更。</blockquote>
                    <button class="aw-retry" type="button" data-aw-retry>もう一度読み込む</button>
                </li>
            </ol>
        </section>
        <section id="aw-memo" role="tabpanel" aria-labelledby="aw-tab-memo" hidden>
            <label class="aw-memo__label" for="aw-memo-text">このスレッドのメモ（ページを閉じると消えます）</label>
            <textarea id="aw-memo-text" rows="8">・夜の利用者の年代を次に確認する
・予約制の不満点も拾う</textarea>
        </section>
    </aside>

    <div class="aw-tabbar" role="tablist" aria-label="表示する画面">
        <button role="tab" type="button" data-aw-show="threads" aria-selected="false" tabindex="-1">スレッド</button>
        <button role="tab" type="button" data-aw-show="chat" aria-selected="true">会話</button>
        <button role="tab" type="button" data-aw-show="sources" aria-selected="false" tabindex="-1">出典</button>
    </div>

    <div class="aw-palette" data-aw-palette role="dialog" aria-modal="true" aria-labelledby="aw-palette-h" hidden>
        <div class="aw-palette__box">
            <h2 class="aw-sr" id="aw-palette-h">コマンド</h2>
            <label class="aw-sr" for="aw-palette-q">コマンドを絞り込む</label>
            <input id="aw-palette-q" type="text" autocomplete="off" placeholder="コマンドを入力…" role="combobox" aria-expanded="true" aria-controls="aw-palette-list" aria-autocomplete="list" data-aw-palette-q>
            <ul id="aw-palette-list" role="listbox" aria-label="コマンド" data-aw-palette-list>
                <li role="option" id="aw-cmd-new" data-cmd="new">新しい調査を始める<kbd>N</kbd></li>
                <li role="option" id="aw-cmd-sources" data-cmd="sources">出典を表示する</li>
                <li role="option" id="aw-cmd-memo" data-cmd="memo">メモを開く</li>
                <li role="option" id="aw-cmd-theme" data-cmd="theme">テーマを切り替える</li>
                <li role="option" id="aw-cmd-panel" data-cmd="panel">出典パネルの表示を切り替える</li>
                <li role="option" id="aw-cmd-retry" data-cmd="retry">出典 3 を読み込み直す</li>
            </ul>
            <p class="aw-palette__none" data-aw-palette-none hidden>一致するコマンドはありません</p>
            <p class="aw-palette__help">↑↓ で選ぶ・Enter で実行・Esc で閉じる</p>
        </div>
    </div>
    <p class="aw-sr" role="status" data-aw-status></p>
</div>
