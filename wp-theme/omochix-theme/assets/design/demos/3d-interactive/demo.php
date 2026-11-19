<?php
/**
 * Design Library demo: 3D Interactive (plain WebGL, no library). Markup only;
 * rendered by the isolated demo endpoint. The product is fictional.
 * $omx_demo['poster'] is the item's still image, shown without WebGL / JS.
 */
defined('ABSPATH') || exit;
$omx_poster = isset($omx_demo['poster']) ? (string) $omx_demo['poster'] : '';
?>
<div class="kd">
    <header class="kd-nav">
        <span class="kd-logo">KORE<span>-01</span></span>
        <span class="kd-tag">架空の製品</span>
    </header>
    <main>
        <section class="kd-hero">
            <div class="kd-stage">
                <canvas class="kd-canvas" tabindex="0" role="img" aria-label="軌道モジュール KORE-01 の 3D モデル。ドラッグまたは矢印キーで回転できます。"></canvas>
                <div class="kd-fallback">
                    <?php if ('' !== $omx_poster) : ?><img src="<?php echo htmlspecialchars($omx_poster, ENT_QUOTES, 'UTF-8'); ?>" width="1280" height="800" alt="KORE-01 の静止画：中心の多面体を 2 本のリングと 6 個の小さな立方体が囲んでいる"><?php endif; ?>
                    <p>この環境では 3D 表示を使えないため、静止画を表示しています。</p>
                </div>
                <p class="kd-hint" aria-hidden="true">ドラッグで回転</p>
                <p class="kd-label" aria-live="polite"></p>
            </div>
            <div class="kd-copy">
                <p class="kd-eyebrow">ORBIT MODULE</p>
                <h1>手のひらに、<br>小さな軌道を。</h1>
                <p class="kd-lead">中心のコアと 2 本のリング、6 つのサテライトで組み上げる卓上モジュール。部品にポインターを重ねると、その役割が分かります。</p>
                <p class="kd-scroll" aria-hidden="true">スクロールでカメラが近づきます ↓</p>
            </div>
        </section>

        <section class="kd-features" aria-labelledby="kd-f-h">
            <h2 id="kd-f-h">5 つの部品</h2>
            <p class="kd-note">カードにポインターやフォーカスを当てると、3D モデルの該当部分が光ります。横にスクロールできます。</p>
            <ul class="kd-rail" tabindex="0" aria-label="部品の一覧（横スクロール）">
                <li class="kd-card" data-part="0" tabindex="0"><span class="kd-dot" style="--c:#9d8cff"></span><h3>コア</h3><p>20 面体の芯。全体の重心を受け持ちます。</p></li>
                <li class="kd-card" data-part="1" tabindex="0"><span class="kd-dot" style="--c:#5eead4"></span><h3>内側のリング</h3><p>傾いた軌道で、回転の向きを示します。</p></li>
                <li class="kd-card" data-part="2" tabindex="0"><span class="kd-dot" style="--c:#f9a8d4"></span><h3>外側のリング</h3><p>細い輪が、内側と逆に回ります。</p></li>
                <li class="kd-card" data-part="3" tabindex="0"><span class="kd-dot" style="--c:#fbbf24"></span><h3>サテライト</h3><p>6 個の立方体が等間隔で周回します。</p></li>
                <li class="kd-card" data-part="4" tabindex="0"><span class="kd-dot" style="--c:#93c5fd"></span><h3>フレーム</h3><p>外周の線が、置き場所の目安になります。</p></li>
            </ul>
        </section>
    </main>
    <footer class="kd-foot">これは OmochiX デザインライブラリーの見本です。製品名・内容はすべて架空です。3D モデルはプログラムで生成しています。</footer>
</div>
