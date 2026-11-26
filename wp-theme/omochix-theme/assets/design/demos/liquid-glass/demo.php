<?php
/**
 * Design Library demo: Frosted Glass UI. Markup only; rendered by the isolated
 * demo endpoint (inc/design-library-demo.php). Opening this file directly
 * prints nothing. Product name and copy are fictional.
 */
defined('ABSPATH') || exit;
?>
<div class="lg">
    <div class="lg-bg" aria-hidden="true">
        <span class="lg-blob lg-blob--1"></span>
        <span class="lg-blob lg-blob--2"></span>
        <span class="lg-blob lg-blob--3"></span>
        <span class="lg-blob lg-blob--4"></span>
        <svg class="lg-noise" width="100%" height="100%"><filter id="lg-n"><feTurbulence type="fractalNoise" baseFrequency="0.85" numOctaves="2" stitchTiles="stitch"/><feColorMatrix values="0 0 0 0 1  0 0 0 0 1  0 0 0 0 1  0 0 0 0.55 0"/></filter><rect width="100%" height="100%" filter="url(#lg-n)"/></svg>
    </div>
    <div class="lg-spot" aria-hidden="true"></div>

    <header class="lg-nav glass">
        <span class="lg-logo"><span class="lg-logo__mark" aria-hidden="true"></span>Quillmere</span>
        <nav aria-label="ページ内"><a href="#lg-features">機能</a><a href="#lg-flow">使い方</a><a href="#lg-plans">プラン</a></nav>
        <button class="lg-btn lg-btn--small" type="button">無料で試す</button>
    </header>

    <main>
        <section class="lg-hero">
            <div class="lg-hero__text">
                <p class="lg-eyebrow">AI WORKSPACE · 架空の製品</p>
                <h1><span class="lg-ph">考えごとを、</span><br><span class="lg-ph">光のように</span><span class="lg-ph">軽く。</span></h1>
                <p class="lg-lead">メモ、資料、会議の記録をひとつの画面に。Quillmere は散らばった考えを集めて、次にやることを静かに差し出します。</p>
                <div class="lg-cta">
                    <button class="lg-btn lg-btn--primary" type="button">はじめる</button>
                    <button class="lg-btn" type="button">2 分で分かる紹介</button>
                </div>
            </div>
            <div class="lg-hero__panel glass lg-float" role="img" aria-label="アプリ画面の見本：会議メモから作業が3件提案されている">
                <div class="lg-panel__bar"><span></span><span></span><span></span></div>
                <p class="lg-panel__q">今週の会議メモから、やることをまとめて</p>
                <ul class="lg-panel__list">
                    <li><b>資料の初稿</b><span>木曜まで</span></li>
                    <li><b>見積もりの確認</b><span>担当：佐藤</span></li>
                    <li><b>次回の議題案</b><span>3 件</span></li>
                </ul>
                <p class="lg-panel__chip"><svg viewBox="0 0 10 10" width="0.8em" height="0.8em" aria-hidden="true" focusable="false" style="vertical-align:-0.05em"><path d="M5 0C5.5 3 7 4.5 10 5C7 5.5 5.5 7 5 10C4.5 7 3 5.5 0 5C3 4.5 4.5 3 5 0Z" fill="currentColor"/></svg> 提案を 3 件作成しました</p>
            </div>
        </section>

        <section class="lg-tier" id="lg-features" aria-labelledby="lg-features-h">
            <h2 id="lg-features-h">集める、整える、渡す。</h2>
            <div class="lg-grid lg-grid--3">
                <article class="glass lg-card lg-float" style="--d:0s"><span class="lg-icon" aria-hidden="true">◎</span><h3>ひとつの場所に</h3><p>メールの一文も、写真の板書も。取り込んだ瞬間に内容ごとに並びます。</p></article>
                <article class="glass lg-card lg-float" style="--d:-2s"><span class="lg-icon" aria-hidden="true">◇</span><h3>要点だけを</h3><p>長い記録は三行の要約に。元の文章へはいつでも戻れます。</p></article>
                <article class="glass lg-card lg-float" style="--d:-4s"><span class="lg-icon" aria-hidden="true">△</span><h3>次の人へ</h3><p>共有したい部分だけを選んで、読みやすい形で渡せます。</p></article>
            </div>
        </section>

        <section class="lg-tier" id="lg-flow" aria-labelledby="lg-flow-h">
            <h2 id="lg-flow-h">使い方は 3 ステップ</h2>
            <ol class="lg-grid lg-grid--3 lg-steps">
                <li class="glass lg-card"><span class="lg-step">01</span><h3>つなぐ</h3><p>使っている保存場所を選ぶだけ。</p></li>
                <li class="glass lg-card"><span class="lg-step">02</span><h3>たずねる</h3><p>ふだんの言葉で、知りたいことを書きます。</p></li>
                <li class="glass lg-card"><span class="lg-step">03</span><h3>受け取る</h3><p>根拠つきの答えと、次の一手が届きます。</p></li>
            </ol>
        </section>

        <section class="lg-tier" id="lg-plans" aria-labelledby="lg-plans-h">
            <h2 id="lg-plans-h">プラン</h2>
            <div class="lg-grid lg-grid--2">
                <article class="glass lg-card lg-plan"><h3>Personal</h3><p class="lg-price">見本の価格</p><ul><li>ノート 100 件まで</li><li>要約と検索</li></ul><button class="lg-btn" type="button">選ぶ</button></article>
                <article class="glass lg-card lg-plan lg-plan--hi"><h3>Team</h3><p class="lg-price">見本の価格</p><ul><li>ノート無制限</li><li>共有と権限</li><li>まとめの自動作成</li></ul><button class="lg-btn lg-btn--primary" type="button">選ぶ</button></article>
            </div>
        </section>
    </main>

    <footer class="lg-foot">これは OmochiX デザインライブラリーの見本です。製品名・内容・価格はすべて架空です。</footer>
</div>
