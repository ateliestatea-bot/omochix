<?php
/**
 * Design Library demo: Aurora Gradient. Markup only; rendered by the isolated
 * demo endpoint (inc/design-library-demo.php). Opening this file directly
 * prints nothing. The product, company, customers and figures are fictional;
 * the aurora is drawn on a Canvas 2D element, with a CSS gradient behind it.
 */
defined('ABSPATH') || exit;
?>
<div class="au">
    <header class="au-nav">
        <span class="au-mark" aria-label="NAGIWA"><span class="au-mark__dot" aria-hidden="true"></span>NAGIWA</span>
        <nav aria-label="ページ内">
            <a href="#au-features">機能</a>
            <a href="#au-flow">導入の流れ</a>
            <a href="#au-pricing">料金</a>
        </nav>
        <button class="au-btn au-btn--ghost" type="button">ログイン</button>
    </header>

    <section class="au-hero" aria-labelledby="au-title">
        <div class="au-sky" aria-hidden="true">
            <div class="au-static"></div>
            <canvas class="au-canvas" data-au-canvas></canvas>
            <div class="au-horizon"></div>
        </div>
        <div class="au-hero__copy">
            <p class="au-eyebrow">FOCUS WORKSPACE FOR TEAMS</p>
            <h1 class="au-title" id="au-title"><span class="au-line">集中できる時間を、</span><span class="au-line">チームの景色に。</span></h1>
            <p class="au-lead">NAGIWA は、会議・通知・作業時間の流れを一枚にまとめ、チーム全員の「深く考える時間」を守るワークスペースです。</p>
            <div class="au-hero__actions">
                <button class="au-btn au-btn--light" type="button">14日間 無料で試す</button>
                <a class="au-link" href="#au-flow">導入の流れを見る</a>
            </div>
        </div>
        <figure class="au-panel" aria-label="今週の集中時間（サンプル画面）">
            <figcaption class="au-panel__head"><span>今週の集中時間</span><span class="au-badge">+18%</span></figcaption>
            <ol class="au-bars">
                <li style="--h:46%"><span>月</span></li>
                <li style="--h:62%"><span>火</span></li>
                <li style="--h:55%"><span>水</span></li>
                <li style="--h:78%"><span>木</span></li>
                <li style="--h:70%"><span>金</span></li>
            </ol>
            <p class="au-panel__foot">チーム平均 <strong>3.6 時間 / 日</strong></p>
        </figure>
    </section>

    <section class="au-band" aria-label="導入チームの声">
        <p>「通知を減らすツールではなく、時間の見え方が変わるツール。」<span>— 架空の導入チーム 12 社のアンケートより</span></p>
    </section>

    <section class="au-section" id="au-features" aria-labelledby="au-features-h">
        <div class="au-wrap">
            <p class="au-kicker">FEATURES</p>
            <h2 class="au-h2" id="au-features-h">流れを整える、三つのしくみ。</h2>
            <div class="au-rows">
                <article class="au-row" data-au-reveal>
                    <div class="au-row__art au-art au-art--1" aria-hidden="true"><span></span><span></span><span></span></div>
                    <div class="au-row__text">
                        <p class="au-kicker">01 / TIMELINE</p>
                        <h3>予定と集中時間を、同じ帯で見る。</h3>
                        <p>会議の前後に生まれる細切れの時間を可視化。まとまった集中ブロックを自動で提案します。</p>
                    </div>
                </article>
                <article class="au-row au-row--rev" data-au-reveal>
                    <div class="au-row__art au-art au-art--2" aria-hidden="true"><span></span><span></span><span></span></div>
                    <div class="au-row__text">
                        <p class="au-kicker">02 / QUIET HOURS</p>
                        <h3>静かな時間を、チームで合わせる。</h3>
                        <p>メンバーの集中ブロックが重なる時間帯は、通知をまとめて後から届けます。</p>
                    </div>
                </article>
                <article class="au-row" data-au-reveal>
                    <div class="au-row__art au-art au-art--3" aria-hidden="true"><span></span><span></span><span></span></div>
                    <div class="au-row__text">
                        <p class="au-kicker">03 / WEEKLY NOTE</p>
                        <h3>週の終わりに、流れをふり返る。</h3>
                        <p>増えた会議、守れた時間、来週の提案を一枚のノートに。評価ではなく、対話のきっかけに。</p>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="au-section au-section--tint" id="au-flow" aria-labelledby="au-flow-h">
        <div class="au-wrap">
            <p class="au-kicker">HOW IT STARTS</p>
            <h2 class="au-h2" id="au-flow-h">導入は、三つのステップ。</h2>
            <ol class="au-steps">
                <li data-au-reveal><span class="au-steps__n">1</span><h3>カレンダーをつなぐ</h3><p>予定の時間帯だけを読み取り、内容は保存しません。</p></li>
                <li data-au-reveal><span class="au-steps__n">2</span><h3>集中の型を選ぶ</h3><p>朝型・午後型・分散型から、チームに合う型を選びます。</p></li>
                <li data-au-reveal><span class="au-steps__n">3</span><h3>一週間、眺める</h3><p>最初の週は何も変えず、時間の流れを眺めるだけ。</p></li>
            </ol>
        </div>
    </section>

    <section class="au-section" id="au-pricing" aria-labelledby="au-pricing-h">
        <div class="au-wrap">
            <p class="au-kicker">PRICING</p>
            <h2 class="au-h2" id="au-pricing-h">チームの大きさに合わせて。</h2>
            <div class="au-plans">
                <article class="au-plan" data-au-reveal>
                    <h3>Starter</h3>
                    <p class="au-plan__price">¥0<span>／5名まで</span></p>
                    <ul><li>タイムライン</li><li>週次ノート</li></ul>
                </article>
                <article class="au-plan au-plan--main" data-au-reveal>
                    <p class="au-plan__tag">いちばん選ばれています</p>
                    <h3>Team</h3>
                    <p class="au-plan__price">¥900<span>／1名・月</span></p>
                    <ul><li>Starter のすべて</li><li>Quiet Hours</li><li>集中ブロックの自動提案</li></ul>
                </article>
                <article class="au-plan" data-au-reveal>
                    <h3>Organization</h3>
                    <p class="au-plan__price">お見積り</p>
                    <ul><li>Team のすべて</li><li>部門ごとのレポート</li><li>SSO・監査ログ</li></ul>
                </article>
            </div>
        </div>
    </section>

    <section class="au-cta" aria-labelledby="au-cta-h">
        <div class="au-cta__glow" aria-hidden="true"></div>
        <h2 class="au-h2" id="au-cta-h">来週の時間を、少しだけ静かに。</h2>
        <p>クレジットカード不要。14日間、すべての機能を試せます。</p>
        <button class="au-btn au-btn--light" type="button">無料で始める</button>
    </section>

    <footer class="au-foot">
        <p>NAGIWA（架空のサービス）— OmochiX Design Library のデモです。製品・企業・数値はすべて架空で、オーロラは Canvas 2D で描画しています。</p>
    </footer>
</div>
