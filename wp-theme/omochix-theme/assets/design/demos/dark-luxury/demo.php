<?php
/**
 * Design Library demo: Dark Luxury. Markup only; rendered by the isolated
 * demo endpoint (inc/design-library-demo.php). Opening this file directly
 * prints nothing. The studio, projects, places and people are fictional;
 * every image is inline SVG drawn for this demo.
 */
defined('ABSPATH') || exit;
?>
<div class="dk">
    <header class="dk-nav">
        <span class="dk-mark" aria-label="SUMIKAGE ARCHITECTS">SUMIKAGE<span class="dk-mark__sub">ARCHITECTS</span></span>
        <nav aria-label="ページ内">
            <a href="#dk-philosophy">思想</a>
            <a href="#dk-works">作品</a>
            <a href="#dk-residence">住まい</a>
            <a href="#dk-contact">見学</a>
        </nav>
        <button class="dk-btn dk-btn--line" type="button">見学のご予約</button>
    </header>

    <main>
        <section class="dk-hero" aria-labelledby="dk-hero-h">
            <div class="dk-hero__layers" aria-hidden="true">
                <svg class="dk-layer dk-layer--back" viewBox="0 0 1600 900" preserveAspectRatio="xMidYMax slice">
                    <defs>
                        <linearGradient id="dk-sky" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#08080a"/><stop offset=".65" stop-color="#131315"/><stop offset="1" stop-color="#1c1b1d"/></linearGradient>
                        <linearGradient id="dk-arch" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#1a1a1d"/><stop offset="1" stop-color="#101012"/></linearGradient>
                    </defs>
                    <rect width="1600" height="900" fill="url(#dk-sky)"/>
                    <g fill="url(#dk-arch)" stroke="#3a3326" stroke-width="1">
                        <path d="M60 900V520a90 90 0 0 1 180 0V900Z"/><path d="M300 900V470a90 90 0 0 1 180 0V900Z"/>
                        <path d="M540 900V430a90 90 0 0 1 180 0V900Z"/><path d="M880 900V430a90 90 0 0 1 180 0V900Z"/>
                        <path d="M1120 900V470a90 90 0 0 1 180 0V900Z"/><path d="M1360 900V520a90 90 0 0 1 180 0V900Z"/>
                    </g>
                </svg>
                <svg class="dk-layer dk-layer--mid" viewBox="0 0 1600 900" preserveAspectRatio="xMidYMax slice">
                    <defs>
                        <linearGradient id="dk-col" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#0c0c0e"/><stop offset=".45" stop-color="#2a2925"/><stop offset=".55" stop-color="#2f2c26"/><stop offset="1" stop-color="#0c0c0e"/></linearGradient>
                        <linearGradient id="dk-gap" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#c9ab6e" stop-opacity="0"/><stop offset=".55" stop-color="#c9ab6e" stop-opacity=".18"/><stop offset="1" stop-color="#c9ab6e" stop-opacity="0"/></linearGradient>
                    </defs>
                    <rect x="0" y="180" width="1600" height="26" fill="#0e0e10"/>
                    <rect x="0" y="206" width="1600" height="1" fill="#6b5b3a" opacity=".55"/>
                    <g fill="url(#dk-col)">
                        <rect x="150" y="206" width="70" height="694"/><rect x="390" y="206" width="70" height="694"/>
                        <rect x="630" y="206" width="70" height="694"/><rect x="900" y="206" width="70" height="694"/>
                        <rect x="1140" y="206" width="70" height="694"/><rect x="1380" y="206" width="70" height="694"/>
                    </g>
                    <g fill="url(#dk-gap)"><rect x="700" y="206" width="200" height="694"/></g>
                </svg>
                <div class="dk-slit"></div>
                <svg class="dk-layer dk-layer--front" viewBox="0 0 1600 900" preserveAspectRatio="xMidYMax slice">
                    <path d="M0 900V760h420v-40h220v-40h320v40h220v40h420V900Z" fill="#070708"/>
                    <path d="M0 760h420v-40h220v-40h320v40h220v40h420" fill="none" stroke="#8a7448" stroke-width="1.2"/>
                </svg>
            </div>
            <div class="dk-hero__copy">
                <p class="dk-kicker"><span class="dk-kicker__en">ARCHITECTURE &amp; RESIDENCES</span><span class="dk-kicker__sep"> — </span><span class="dk-kicker__ja">架空の建築ブランド</span></p>
                <h1 id="dk-hero-h" class="dk-title" aria-label="静けさを、設計する。">
                    <span class="dk-line" aria-hidden="true"><span>静けさを、</span></span>
                    <span class="dk-line" aria-hidden="true"><span>設計する。</span></span>
                </h1>
                <p class="dk-lead">光の入り方と、壁の厚み。住む人の一日に合わせて、空間の「間」を一つずつ決めていきます。</p>
            </div>
            <p class="dk-scrollhint" aria-hidden="true"><span></span>SCROLL</p>
        </section>

        <section class="dk-philosophy" id="dk-philosophy" aria-labelledby="dk-ph-h">
            <div class="dk-wrap">
                <p class="dk-label" data-dk-reveal>PHILOSOPHY</p>
                <h2 id="dk-ph-h" class="dk-h2" data-dk-reveal>三つの素材で、建てる。</h2>
                <ol class="dk-tenets">
                    <li data-dk-reveal><span class="dk-num">01</span><h3>光</h3><i class="dk-rule" aria-hidden="true"></i><p>朝の光は東の細い窓から、夕方の光は深い庇の下から。季節ごとの太陽の高さを図面に描き込みます。</p></li>
                    <li data-dk-reveal><span class="dk-num">02</span><h3>素材</h3><i class="dk-rule" aria-hidden="true"></i><p>黒い左官壁、焼いた杉、真鍮の金物。手に触れる場所ほど、時間とともに表情が深くなるものを選びます。</p></li>
                    <li data-dk-reveal><span class="dk-num">03</span><h3>余白</h3><i class="dk-rule" aria-hidden="true"></i><p>何も置かない一間を、必ず一つ。家具ではなく、影と静けさのための場所です。</p></li>
                </ol>
            </div>
        </section>

        <section class="dk-works" id="dk-works" aria-labelledby="dk-works-h" data-dk-works>
            <div class="dk-works__sticky">
                <div class="dk-works__head dk-wrap">
                    <p class="dk-label">SELECTED WORKS</p>
                    <h2 id="dk-works-h" class="dk-h2">作品</h2>
                    <p class="dk-works__count" aria-hidden="true"><span data-dk-index>01</span> / 04</p>
                </div>
                <ul class="dk-track" data-dk-track>
                    <li class="dk-card" tabindex="0" aria-labelledby="dk-w1">
                        <figure>
                            <svg viewBox="0 0 400 500" aria-hidden="true">
                                <defs><linearGradient id="dk-w1-s" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#0d0d0f"/><stop offset="1" stop-color="#1f1d1b"/></linearGradient><linearGradient id="dk-w1-l" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#e6cf9a"/><stop offset="1" stop-color="#9c7f47"/></linearGradient></defs>
                                <rect width="400" height="500" fill="url(#dk-w1-s)"/>
                                <path d="M0 330 L120 300 L180 340 L240 330 L240 500 L0 500Z" fill="#0a0a0b"/>
                                <rect x="70" y="250" width="290" height="46" fill="#18181a"/>
                                <rect x="90" y="266" width="240" height="10" fill="url(#dk-w1-l)" opacity=".85"/>
                                <rect x="70" y="296" width="290" height="1" fill="#8a7448"/>
                                <path d="M240 330 L400 330 L400 500 L240 500Z" fill="#111113"/>
                                <path d="M240 380 H400" stroke="#3b3a3c" stroke-width="1"/><path d="M250 410 H400" stroke="#2c2b2d" stroke-width="1"/>
                            </svg>
                            <figcaption><span class="dk-card__n">01</span><h3 id="dk-w1">崖の家</h3><p>海沿いの丘 ／ 2025</p></figcaption>
                        </figure>
                    </li>
                    <li class="dk-card" tabindex="0" aria-labelledby="dk-w2">
                        <figure>
                            <svg viewBox="0 0 400 500" aria-hidden="true">
                                <defs><radialGradient id="dk-w2-g" cx=".5" cy=".55" r=".5"><stop offset="0" stop-color="#3b3222"/><stop offset="1" stop-color="#0d0d0f"/></radialGradient></defs>
                                <rect width="400" height="500" fill="url(#dk-w2-g)"/>
                                <path d="M0 180 L140 230 L140 430 L0 470Z" fill="#141416"/><path d="M400 180 L260 230 L260 430 L400 470Z" fill="#17171a"/>
                                <path d="M140 230 H260 V430 H140Z" fill="#0b0b0c"/>
                                <circle cx="200" cy="330" r="46" fill="#1d1c18"/><rect x="198" y="360" width="4" height="70" fill="#1d1c18"/>
                                <path d="M140 430 H260" stroke="#c9ab6e" stroke-width="1"/>
                                <rect x="40" y="300" width="6" height="90" fill="#c9ab6e" opacity=".6"/><rect x="354" y="300" width="6" height="90" fill="#c9ab6e" opacity=".45"/>
                            </svg>
                            <figcaption><span class="dk-card__n">02</span><h3 id="dk-w2">中庭の家</h3><p>古い街区 ／ 2024</p></figcaption>
                        </figure>
                    </li>
                    <li class="dk-card" tabindex="0" aria-labelledby="dk-w3">
                        <figure>
                            <svg viewBox="0 0 400 500" aria-hidden="true">
                                <defs><linearGradient id="dk-w3-s" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#121214"/><stop offset="1" stop-color="#08080a"/></linearGradient></defs>
                                <rect width="400" height="500" fill="url(#dk-w3-s)"/>
                                <rect x="150" y="60" width="100" height="440" fill="#18181b"/>
                                <g fill="#c9ab6e"><rect x="162" y="110" width="76" height="3" opacity=".7"/><rect x="162" y="170" width="76" height="3" opacity=".45"/><rect x="162" y="230" width="76" height="3" opacity=".8"/><rect x="162" y="290" width="76" height="3" opacity=".35"/><rect x="162" y="350" width="76" height="3" opacity=".6"/><rect x="162" y="410" width="76" height="3" opacity=".5"/></g>
                                <g fill="#0b0b0c"><rect x="150" y="60" width="8" height="440"/><rect x="242" y="60" width="8" height="440"/></g>
                                <path d="M0 470 H400" stroke="#2e2c29" stroke-width="1"/>
                            </svg>
                            <figcaption><span class="dk-card__n">03</span><h3 id="dk-w3">塔の住まい</h3><p>森の縁 ／ 2023</p></figcaption>
                        </figure>
                    </li>
                    <li class="dk-card" tabindex="0" aria-labelledby="dk-w4">
                        <figure>
                            <svg viewBox="0 0 400 500" aria-hidden="true">
                                <defs><linearGradient id="dk-w4-w" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#1a1917"/><stop offset="1" stop-color="#09090a"/></linearGradient></defs>
                                <rect width="400" height="500" fill="#0c0c0e"/>
                                <rect x="40" y="200" width="320" height="10" fill="#1d1c1f"/><rect x="40" y="210" width="320" height="1" fill="#c9ab6e"/>
                                <g fill="#151517"><rect x="70" y="211" width="6" height="110"/><rect x="190" y="211" width="6" height="110"/><rect x="324" y="211" width="6" height="110"/></g>
                                <rect x="110" y="250" width="180" height="70" fill="#2a2416" opacity=".9"/>
                                <rect y="321" width="400" height="179" fill="url(#dk-w4-w)"/>
                                <rect x="110" y="330" width="180" height="40" fill="#2a2416" opacity=".35"/>
                                <path d="M0 321 H400" stroke="#3a3428" stroke-width="1"/>
                            </svg>
                            <figcaption><span class="dk-card__n">04</span><h3 id="dk-w4">水辺の東屋</h3><p>湖畔 ／ 2022</p></figcaption>
                        </figure>
                    </li>
                </ul>
            </div>
        </section>

        <section class="dk-residence" id="dk-residence" aria-labelledby="dk-res-h">
            <div class="dk-wrap dk-residence__grid">
                <figure class="dk-interior" data-dk-depth>
                    <svg viewBox="0 0 800 560" aria-hidden="true">
                        <defs>
                            <linearGradient id="dk-in-f" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#17161a"/><stop offset="1" stop-color="#0b0b0c"/></linearGradient>
                            <linearGradient id="dk-in-l" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#e3c98e" stop-opacity=".55"/><stop offset="1" stop-color="#e3c98e" stop-opacity="0"/></linearGradient>
                        </defs>
                        <rect width="800" height="560" fill="#0d0d0f"/>
                        <path d="M0 0 L250 120 L250 420 L0 560Z" fill="#141416"/><path d="M800 0 L550 120 L550 420 L800 560Z" fill="#121214"/>
                        <path d="M250 420 L550 420 L800 560 L0 560Z" fill="url(#dk-in-f)"/>
                        <rect x="250" y="120" width="300" height="300" fill="#0a0a0b"/>
                        <rect x="380" y="140" width="40" height="260" fill="#d7bd84" opacity=".8"/>
                        <path d="M380 400 L420 400 L640 560 L250 560Z" fill="url(#dk-in-l)"/>
                        <rect x="300" y="360" width="200" height="22" fill="#1e1d20"/><rect x="300" y="382" width="200" height="1" fill="#8a7448"/>
                    </svg>
                    <figcaption>リビングの一間。正午の光が床に一本の線を引く。</figcaption>
                </figure>
                <div class="dk-residence__text">
                    <p class="dk-label" data-dk-reveal>RESIDENCE</p>
                    <h2 id="dk-res-h" class="dk-h2" data-dk-reveal>住まいの仕様</h2>
                    <table class="dk-spec" data-dk-reveal>
                        <caption class="dk-sr">住まいの主な仕様（見本）</caption>
                        <tbody>
                            <tr><th scope="row">構造</th><td>鉄筋コンクリート造・一部木造</td></tr>
                            <tr><th scope="row">外装</th><td>黒漆喰、焼杉板張り</td></tr>
                            <tr><th scope="row">開口</th><td>真鍮枠の地窓、天窓</td></tr>
                            <tr><th scope="row">庭</th><td>苔と砂利、夜間の足元照明</td></tr>
                            <tr><th scope="row">見学</th><td>完全予約制・一日二組まで</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="dk-contact" id="dk-contact" aria-labelledby="dk-ct-h">
            <div class="dk-wrap">
                <p class="dk-label" data-dk-reveal>VISIT</p>
                <h2 id="dk-ct-h" class="dk-h2 dk-h2--center" data-dk-reveal><span class="dk-ph">静けさを、</span><span class="dk-ph">体で確かめる。</span></h2>
                <p class="dk-contact__lead" data-dk-reveal>完成した住まいを、季節ごとに一組ずつご案内しています。</p>
                <div class="dk-contact__actions" data-dk-reveal>
                    <button class="dk-btn dk-btn--gold" type="button">見学を予約する</button>
                    <a class="dk-link" href="#dk-works">作品をもう一度見る</a>
                </div>
            </div>
        </section>
    </main>
    <footer class="dk-foot">これは OmochiX デザインライブラリーの見本です。事務所名・作品・地名・仕様はすべて架空で、図版は SVG で描いたものです。</footer>
</div>
