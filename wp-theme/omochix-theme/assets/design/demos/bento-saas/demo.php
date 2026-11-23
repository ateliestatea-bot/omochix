<?php
/**
 * Design Library demo: Bento SaaS. Markup only; rendered by the isolated demo
 * endpoint. The product, numbers, customers and prices are fictional.
 * Numbers are written in their final value so they read without JS.
 */
defined('ABSPATH') || exit;
?>
<div class="bn">
    <header class="bn-nav">
        <span class="bn-logo"><span class="bn-logo__grid" aria-hidden="true"><i></i><i></i><i></i><i></i></span>Grivio</span>
        <nav aria-label="ページ内"><a href="#bn-grid">機能</a><a href="#bn-price">料金</a></nav>
        <button class="bn-btn bn-btn--primary" type="button">無料で始める</button>
    </header>

    <main>
        <section class="bn-hero">
            <p class="bn-pill">架空の SaaS · 数値はすべて見本です</p>
            <h1>チームの数字を、<br>ひと目で。</h1>
            <p class="bn-lead">散らばったタスクと進み具合を、区画ごとのボードにまとめます。会議の前に開くだけで、話すべきことが分かります。</p>
        </section>

        <section class="bn-grid" id="bn-grid" aria-label="機能の概要">
            <article class="bn-tile bn-tile--chart">
                <header><h2>週ごとの完了数</h2><span class="bn-badge">+24%</span></header>
                <svg class="bn-line" viewBox="0 0 320 140" role="img" aria-label="折れ線グラフ：8 週間で完了数が増えている（架空の数値）">
                    <g class="bn-gridlines"><line x1="0" y1="35" x2="320" y2="35"/><line x1="0" y1="70" x2="320" y2="70"/><line x1="0" y1="105" x2="320" y2="105"/></g>
                    <path class="bn-area" d="M0 120 L45 108 L90 112 L135 86 L180 90 L225 60 L270 52 L320 30 L320 140 L0 140 Z"/>
                    <path class="bn-path" d="M0 120 L45 108 L90 112 L135 86 L180 90 L225 60 L270 52 L320 30" pathLength="1"/>
                </svg>
            </article>
            <article class="bn-tile bn-tile--kpi">
                <h2>平均の処理時間</h2>
                <p class="bn-num"><span data-count="38" data-prefix="−">−38</span><small>%</small></p>
                <p class="bn-sub">導入前との比較（見本）</p>
            </article>
            <article class="bn-tile bn-tile--kpi bn-tile--accent">
                <h2>自動化した作業</h2>
                <p class="bn-num"><span data-count="1240">1,240</span><small>件 / 月</small></p>
                <p class="bn-sub">ルールは 12 個</p>
            </article>
            <article class="bn-tile bn-tile--bars">
                <h2>担当ごとの残り</h2>
                <ul class="bn-bars" aria-label="棒グラフ：担当ごとの残りタスク（架空）">
                    <li style="--v:.82"><span>企画</span><b>18</b></li>
                    <li style="--v:.55"><span>制作</span><b>12</b></li>
                    <li style="--v:.36"><span>確認</span><b>8</b></li>
                    <li style="--v:.18"><span>公開</span><b>4</b></li>
                </ul>
            </article>
            <article class="bn-tile bn-tile--board">
                <h2>今日のボード</h2>
                <div class="bn-board">
                    <div><p class="bn-col">未着手</p><p class="bn-task">見出し案を 3 つ</p><p class="bn-task">素材の確認</p></div>
                    <div><p class="bn-col">進行中</p><p class="bn-task bn-task--hi">料金ページの文言</p></div>
                    <div><p class="bn-col">完了</p><p class="bn-task bn-task--done">議事録の共有</p></div>
                </div>
            </article>
            <article class="bn-tile bn-tile--apps">
                <h2>つながる道具</h2>
                <ul class="bn-apps" aria-label="連携の例（汎用アイコン）">
                    <li title="カレンダー"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M3 10h18M8 3v4M16 3v4"/></svg></li>
                    <li title="メール"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/></svg></li>
                    <li title="チャット"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v11H9l-5 4z"/></svg></li>
                    <li title="ファイル"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4"/></svg></li>
                    <li title="表計算"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M3 15h18M9 4v16"/></svg></li>
                    <li title="通知"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 16V11a6 6 0 1 1 12 0v5l2 2H4z"/><path d="M10 20h4"/></svg></li>
                </ul>
            </article>
            <article class="bn-tile bn-tile--uptime">
                <h2>稼働率</h2>
                <p class="bn-num"><span data-count="99.9" data-decimals="1">99.9</span><small>%</small></p>
                <div class="bn-ticks" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i class="is-warn"></i><i></i><i></i><i></i><i></i></div>
            </article>
            <article class="bn-tile bn-tile--quote">
                <p>「朝のミーティングが 15 分短くなりました。」</p>
                <p class="bn-who">— 架空の制作会社のディレクター</p>
            </article>
        </section>

        <section class="bn-price" id="bn-price" aria-labelledby="bn-price-h">
            <h2 id="bn-price-h">料金</h2>
            <p class="bn-note">すべて見本の価格（架空）です。</p>
            <div class="bn-plans">
                <article class="bn-plan"><h3>Free</h3><p class="bn-amount">¥0</p><ul><li>ボード 3 つ</li><li>メンバー 5 人まで</li></ul><button class="bn-btn" type="button">始める</button></article>
                <article class="bn-plan bn-plan--hi"><p class="bn-rec">おすすめ</p><h3>Team</h3><p class="bn-amount">¥1,200<small> / 人・月</small></p><ul><li>ボード無制限</li><li>自動化ルール</li><li>週次レポート</li></ul><button class="bn-btn bn-btn--primary" type="button">14 日間試す</button></article>
                <article class="bn-plan"><h3>Business</h3><p class="bn-amount">要相談</p><ul><li>権限の詳細設定</li><li>監査ログ</li></ul><button class="bn-btn" type="button">問い合わせる</button></article>
            </div>
        </section>
    </main>
    <footer class="bn-foot">これは OmochiX デザインライブラリーの見本です。製品名・数値・価格・発言はすべて架空です。</footer>
</div>
