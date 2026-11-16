<?php
/**
 * Design Library demo: Minimal Editorial. Markup only; rendered by the
 * isolated demo endpoint. The magazine, articles and people are fictional.
 */
defined('ABSPATH') || exit;
?>
<div class="ed">
    <header class="ed-mast">
        <span class="ed-mast__title">YOHAKU</span>
        <span class="ed-mast__sub">季刊 余白 — 架空の雑誌</span>
        <span class="ed-mast__issue">No. 07 / 秋</span>
    </header>
    <div class="ed-rule" aria-hidden="true"></div>

    <main>
        <section class="ed-hero ed-grid">
            <p class="ed-kicker">特集　暮らしと文字</p>
            <h1 class="ed-title" aria-label="余白は、読む人の呼吸になる。">
                <span class="ed-line" aria-hidden="true">余白は、</span>
                <span class="ed-line" aria-hidden="true">読む人の</span>
                <span class="ed-line" aria-hidden="true">呼吸になる。</span>
            </h1>
            <p class="ed-byline">文　架空の編集部<br>図版　幾何学のスケッチ</p>
            <figure class="ed-figure" aria-hidden="true">
                <svg viewBox="0 0 240 240">
                    <circle class="ed-draw" cx="120" cy="120" r="92" pathLength="1"/>
                    <circle class="ed-draw" cx="120" cy="120" r="46" pathLength="1"/>
                    <line class="ed-draw" x1="10" y1="120" x2="230" y2="120" pathLength="1"/>
                    <line class="ed-draw" x1="120" y1="10" x2="120" y2="230" pathLength="1"/>
                    <rect class="ed-draw ed-accent-stroke" x="148" y="44" width="48" height="48" pathLength="1"/>
                </svg>
            </figure>
        </section>

        <section class="ed-article ed-grid" aria-labelledby="ed-a1">
            <aside class="ed-side">
                <p>01</p>
                <p>エッセイ</p>
                <p>読了 4 分</p>
            </aside>
            <div class="ed-body">
                <h2 id="ed-a1">一行の長さを決める</h2>
                <p class="ed-drop">紙面をつくるとき、最初に決めるのは文字の大きさではなく一行の長さです。目が行の終わりから次の行の頭へ戻るとき、距離が長すぎると迷い、短すぎると落ち着きません。この見本では、ほぼ四十字で折り返すように段の幅を決めています。</p>
                <p>余白は空いている場所ではなく、内容と内容のあいだに置かれた合図です。見出しの上を広く、下を狭くすると、見出しは次の段落の一部として読まれます。罫線は少なく、細く。色は一色だけを、強調したい一語のために残しておきます。</p>
                <p>画面でも同じことが言えます。スクロールに合わせて細い線が引かれると、読む人は自分がどこまで来たのかを、数字を見ずに知ることができます。</p>
            </div>
            <blockquote class="ed-quote"><p>色は一色、強調したい一語のために。</p></blockquote>
        </section>
        <div class="ed-rule" aria-hidden="true"></div>

        <section class="ed-pager" aria-labelledby="ed-pager-h">
            <div class="ed-pager__head">
                <h2 id="ed-pager-h">今号のほかの記事</h2>
                <div class="ed-pager__nav">
                    <button type="button" class="ed-btn" data-ed-prev aria-label="前の記事">←</button>
                    <span class="ed-pager__count" aria-live="polite"><span data-ed-index>1</span> / 3</span>
                    <button type="button" class="ed-btn" data-ed-next aria-label="次の記事">→</button>
                </div>
            </div>
            <div class="ed-pages">
                <article class="ed-page is-current" aria-label="記事 1 / 3">
                    <p class="ed-page__no">02</p><h3>余白のある台所</h3>
                    <p>道具を減らした台所で、使う順番に器を並べると、手が先に動くようになりました。読者から届いた三つの工夫を紹介します。</p>
                </article>
                <article class="ed-page" aria-label="記事 2 / 3">
                    <p class="ed-page__no">03</p><h3>縦に読む、横に読む</h3>
                    <p>同じ文章を縦組みと横組みで並べると、読む速さが少し変わります。見出しだけを縦にする、という中間の選び方もあります。</p>
                </article>
                <article class="ed-page" aria-label="記事 3 / 3">
                    <p class="ed-page__no">04</p><h3>一枚の紙から</h3>
                    <p>折り目を一本入れるだけで、紙は表紙と本文に分かれます。小さな冊子をつくるための、いちばん短い手順です。</p>
                </article>
            </div>
        </section>
    </main>

    <footer class="ed-foot">これは OmochiX デザインライブラリーの見本です。雑誌名・記事・人物はすべて架空です。</footer>
</div>
