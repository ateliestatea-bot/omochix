<?php
/**
 * Design Library demo: Interactive Storytelling. Markup only; rendered by the isolated demo endpoint.
 * A fictional washi workshop ("紙季廊 / SHIKIRO"); people, places and the workshop are fictional, and the
 * paper-making steps are described only in general terms.
 * Without JavaScript (and with reduced motion) every paragraph shows its own finished illustration;
 * the craftsman / paper voice switch works with CSS alone (:has). JavaScript adds the fixed illustration
 * that follows the paragraphs, the contents sheet, the reading progress and position keeping.
 * The 13 illustration states are defined once as <symbol>s and drawn with <use>.
 */
defined('ABSPATH') || exit;
$omx_story = [
    1 => ['id' => 'sk-ch1', 'title' => '冬・楮を刈る', 'steps' => [
        1 => ['冬のはじめ、裏の畑で楮（こうぞ）を刈ります。葉が落ちて枝だけになったころが、刈りどきです。雪の朝は手がかじかみますが、一年の紙はここから始まります。',
              'ぼくはまだ、畑に立つ細い枝です。雪をかぶって、じっと冬を待っています。この枝の皮の内側に、のちに紙になる白い繊維がしまわれています。'],
        2 => ['根元から一本ずつ刈り取り、長さをそろえて束にします。束ねるときは、枝が折れないように力を加減します。束の数を数えると、来年の仕事の量が見えてきます。',
              'ざくっと音がして、ぼくは根元から切り離されました。仲間と並べられ、長さをそろえて縄で束ねられます。畑を離れるのは、少しさびしい気持ちです。'],
        3 => ['束を大きな釜で蒸すと、皮がやわらかくなって、するりとむけるようになります。立ちのぼる湯気のにおいで、蒸し上がりの頃合いがわかります。',
              '釜の中は白い湯気でいっぱいです。体がふっくらとやわらかくなって、外側の皮が少しずつゆるんでいくのがわかります。'],
    ]],
    2 => ['id' => 'sk-ch2', 'title' => '晒す・叩く', 'steps' => [
        4 => ['むいた皮から外側の黒い部分を削り、白い内皮だけを残して、冷たい流れにさらします。水と光にあてると、繊維が少しずつ白くなっていきます。',
              '黒い上着を脱いで、白い内側だけになりました。冷たい水の中で何日か揺られていると、自分の色がだんだん明るくなっていきます。'],
        5 => ['やわらかく煮た繊維を水に広げ、小さなちりや傷を指先でひとつずつ取り除きます。ここを丁寧にするほど、仕上がりの紙が澄んできます。',
              '水の中に広げられて、細かなちりを一粒ずつ取ってもらいます。くすぐったいけれど、これで仕上がりがきれいになるのだそうです。'],
        6 => ['平らな石の上で、繊維を木槌で根気よく叩きます。かたまりがほどけて、綿のようにふわりと広がれば、漉く準備が整います。',
              'とん、とん、と木槌が下りてきます。からまっていた体がほどけて、ふわふわと軽くなりました。もうすぐ水の中で一枚になれそうです。'],
    ]],
    3 => ['id' => 'sk-ch3', 'title' => '漉く', 'steps' => [
        7 => ['漉き舟に水を張り、ほぐした繊維と、植物から取ったねばりのある液「ねり」を入れて、よくかき混ぜます。ねりがあると、繊維が水の中で均等に散らばります。',
              '大きな水槽の中で、ぼくたちは水と、とろりとした「ねり」と一緒にかき混ぜられます。みんなが同じ間隔で、ゆらゆらと浮かびました。'],
        8 => ['簀桁（すけた）を手前から差し入れ、水をすくい上げます。すくう量と角度で、紙の厚みが決まります。毎回、同じ動きを同じ速さで繰り返します。',
              '細い竹で編んだ簀の上に、すくい上げられました。水がすき間から落ちていき、ぼくたちだけが薄く残ります。'],
        9 => ['すくった水を前後に揺らし、繊維を縦にも横にも絡ませます。揺らしては流し、またすくう。何度か重ねて、ちょうどよい厚みにします。',
              '前へ、後ろへ。揺らされるたびに、隣の繊維と手をつなぎ合います。縦にも横にもつながって、薄くても破れにくい一枚になっていきます。'],
        10 => ['漉き上がった紙を簀から外し、台の上に一枚ずつ重ねていきます。ぬれたままでも、ねりのおかげで紙どうしはくっつきません。',
               'ぬれたまま、そっと台の上に寝かされました。上にも下にも、同じ日に生まれた仲間が静かに重なっています。'],
    ]],
    4 => ['id' => 'sk-ch4', 'title' => '干す・届く', 'steps' => [
        11 => ['重ねた紙に一晩かけてゆっくり重しをかけ、水を搾ります。それから一枚ずつはがし、刷毛で板に貼って、日なたに立てかけます。',
               'ゆっくり押されて、体の水がしぼり出されました。板に貼られて日なたに出ると、風が通るたびに少しずつ軽くなります。'],
        12 => ['乾いた紙を光にかざして、厚みのむらや傷がないかを一枚ずつ確かめます。透かすと、繊維の流れが雲のように見えます。',
               '光の前にかざされると、自分の中の繊維の模様がすけて見えました。畑に立っていた枝の名残が、まだここにあります。'],
        13 => ['選んだ紙は、便箋や包み紙になって工房を出ていきます。誰かの手で文字が書かれ、また別の誰かに届く。それを思いながら、次の冬を待ちます。',
               'ぼくは今、一通の手紙になって旅をしています。書かれた言葉を運び終えたら、受け取った人の引き出しで、しばらく休むつもりです。'],
    ]],
];
?>
<div class="sk" data-sk data-ch="0">
    <header class="sk-bar">
        <a class="sk-brand" href="#sk-top"><span class="sk-brand__mark" aria-hidden="true"></span>紙季廊<span class="sk-brand__en">SHIKIRO</span></a>
        <p class="sk-bar__ch" data-sk-chapter>一枚の紙ができるまで</p>
        <button class="sk-bar__toc" type="button" data-sk-toc aria-haspopup="dialog" hidden>目次</button>
        <div class="sk-progress" aria-hidden="true"><i data-sk-progress></i></div>
    </header>

    <main class="sk-main">
        <section class="sk-intro" id="sk-top" aria-labelledby="sk-title">
            <p class="sk-kicker">架空の手漉き和紙の工房の一年</p>
            <h1 id="sk-title">一枚の紙が<br>できるまで</h1>
            <p class="sk-lead">冬の畑の細い枝が、春に一通の手紙になるまで。紙季廊の一年を、職人の目と、紙の目で読む物語です。</p>
            <p class="sk-meta">4 章 · 13 場面 · 読む時間 約 4 分</p>
            <fieldset class="sk-voice">
                <legend>どちらの目で読みますか</legend>
                <div class="sk-voice__opts">
                    <input type="radio" name="sk-voice" id="sk-v-craft" value="craft" checked data-sk-voice><label for="sk-v-craft"><b>職人の目</b><small>工房の「わたし」が語る</small></label>
                    <input type="radio" name="sk-voice" id="sk-v-paper" value="paper" data-sk-voice><label for="sk-v-paper"><b>紙の目</b><small>紙になる「ぼく」が語る</small></label>
                </div>
                <p class="sk-voice__note">読んでいる途中でも切り替えられます。</p>
            </fieldset>
            <nav class="sk-toc" aria-label="目次">
                <h2 class="sk-toc__h">目次</h2>
                <ol>
                    <?php foreach ($omx_story as $omx_n => $omx_ch) : ?>
                        <li><a href="#<?php echo esc_attr($omx_ch['id']); ?>"><span>第<?php echo (int) $omx_n; ?>章</span><?php echo esc_html($omx_ch['title']); ?></a></li>
                    <?php endforeach; ?>
                </ol>
            </nav>
        </section>

        <div class="sk-story">
            <div class="sk-stage" aria-hidden="true" data-sk-stage>
                <svg class="sk-stage__svg" viewBox="0 0 400 300" focusable="false">
                    <circle class="sk-sun" cx="320" cy="70" r="26"/>
                    <path class="sk-mtn sk-mtn--far" d="M-40 190 L40 120 L95 160 L160 95 L235 165 L300 110 L380 170 L460 130 V300 H-40Z"/>
                    <path class="sk-mtn sk-mtn--near" d="M-40 215 L60 175 L130 200 L210 160 L290 205 L370 180 L460 210 V300 H-40Z"/>
                    <?php for ($omx_i = 1; $omx_i <= 13; $omx_i++) : ?>
                        <use class="sk-u" href="#sk-s<?php echo (int) $omx_i; ?>" data-n="<?php echo (int) $omx_i; ?>"/>
                    <?php endfor; ?>
                    <path class="sk-thread" d="M20 286 C120 270 280 300 380 284" pathLength="1"/>
                </svg>
                <p class="sk-stage__cap" data-sk-cap>第1章 冬・楮を刈る</p>
            </div>

            <div class="sk-text">
                <?php foreach ($omx_story as $omx_n => $omx_ch) : ?>
                    <section class="sk-ch sk-ch--<?php echo (int) $omx_n; ?>" id="<?php echo esc_attr($omx_ch['id']); ?>" data-ch="<?php echo (int) $omx_n; ?>" aria-labelledby="<?php echo esc_attr($omx_ch['id']); ?>-h">
                        <h2 class="sk-ch__h" id="<?php echo esc_attr($omx_ch['id']); ?>-h" tabindex="-1"><span>第<?php echo (int) $omx_n; ?>章</span><?php echo esc_html($omx_ch['title']); ?></h2>
                        <?php foreach ($omx_ch['steps'] as $omx_s => $omx_v) : ?>
                            <div class="sk-step" data-step="<?php echo (int) $omx_s; ?>" data-ch="<?php echo (int) $omx_n; ?>">
                                <svg class="sk-fig" viewBox="0 0 400 300" aria-hidden="true" focusable="false"><use href="#sk-s<?php echo (int) $omx_s; ?>"/></svg>
                                <p class="sk-p sk-p--craft"><?php echo esc_html($omx_v[0]); ?></p>
                                <p class="sk-p sk-p--paper"><?php echo esc_html($omx_v[1]); ?></p>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($omx_n < 4) : ?>
                            <p class="sk-next"><a href="#sk-ch<?php echo (int) $omx_n + 1; ?>" data-sk-next>次の章へ<span aria-hidden="true"> ↓</span></a></p>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>
            </div>
        </div>

        <section class="sk-end" id="sk-end" aria-labelledby="sk-end-h">
            <h2 id="sk-end-h" tabindex="-1">紙季廊の一年</h2>
            <ol class="sk-year">
                <li><b>冬</b>楮を刈り、蒸して皮をむく</li>
                <li><b>冬の終わり</b>流れにさらし、ちりを取り、叩く</li>
                <li><b>春のはじめ</b>ねりと混ぜて、一枚ずつ漉く</li>
                <li><b>春</b>搾って干し、光にかざして選ぶ</li>
            </ol>
            <p class="sk-end__note">この物語の工房・人物・地名は架空です。紙すきの工程は一般的な流れを簡単にしたもので、実際の工房によって異なります。</p>
            <button class="sk-visit" type="button" data-sk-visit>工房の見学について</button>
            <p class="sk-visit__msg" data-sk-visit-msg role="status"></p>
        </section>
    </main>

    <div class="sk-sheet" data-sk-sheet role="dialog" aria-modal="true" aria-labelledby="sk-sheet-h" hidden>
        <div class="sk-sheet__box">
            <h2 id="sk-sheet-h">目次</h2>
            <ol>
                <?php foreach ($omx_story as $omx_n => $omx_ch) : ?>
                    <li><button type="button" data-sk-go="<?php echo esc_attr($omx_ch['id']); ?>"><span>第<?php echo (int) $omx_n; ?>章</span><?php echo esc_html($omx_ch['title']); ?></button></li>
                <?php endforeach; ?>
                <li><button type="button" data-sk-go="sk-end"><span>結び</span>紙季廊の一年</button></li>
            </ol>
            <button class="sk-sheet__close" type="button" data-sk-close>閉じる</button>
        </div>
    </div>
    <p class="sk-sr" role="status" aria-live="polite" data-sk-status></p>

    <svg class="sk-defs" aria-hidden="true" focusable="false" width="0" height="0">
        <defs>
            <symbol id="sk-s1" viewBox="0 0 400 300">
                <path class="f-snow" d="M0 228 Q100 206 200 224 T400 218 V300 H0Z"/>
                <g class="l-bark"><path d="M120 226V128M120 168l-14-16M120 150l12-14"/><path d="M160 222V112M160 150l14-14M160 176l-12-12"/><path d="M200 224V124M200 160l-14-16M200 140l12-12"/><path d="M240 222V108M240 146l14-16M240 170l-14-12"/><path d="M280 220V130M280 166l12-14"/></g>
                <g class="f-flake"><circle cx="70" cy="90" r="3"/><circle cx="140" cy="60" r="2.5"/><circle cx="215" cy="84" r="3"/><circle cx="260" cy="48" r="2"/><circle cx="350" cy="120" r="3"/><circle cx="96" cy="150" r="2"/></g>
            </symbol>
            <symbol id="sk-s2" viewBox="0 0 400 300">
                <path class="f-snow" d="M0 228 Q100 206 200 224 T400 218 V300 H0Z"/>
                <g class="l-bark"><path d="M120 226V208M160 222V204M200 224V206M240 222V204M280 220V202"/></g>
                <g transform="rotate(-8 230 250)"><g class="l-bark"><path d="M150 252H320M150 244H316M152 260H318M150 236H312"/></g><rect class="f-rope" x="196" y="230" width="12" height="36" rx="3"/><rect class="f-rope" x="262" y="230" width="12" height="36" rx="3"/></g>
                <path class="l-ink" d="M70 150 q30 -40 70 -20" stroke-width="6"/><path class="l-wood" d="M70 150 l-22 34"/>
            </symbol>
            <symbol id="sk-s3" viewBox="0 0 400 300">
                <path class="f-ground" d="M0 250 H400 V300 H0Z"/>
                <path class="f-indigo" d="M120 150 H280 L268 250 H132Z"/><rect class="f-ink" x="110" y="140" width="180" height="16" rx="5"/>
                <g class="l-bark"><path d="M168 140V96M184 140V90M200 140V98M216 140V88M232 140V96"/></g>
                <g class="l-steam"><path d="M150 120 q-14 -20 0 -40 t0 -40"/><path d="M250 118 q14 -22 0 -42 t0 -38"/><path d="M200 84 q-12 -18 0 -34 t0 -30"/></g>
                <path class="f-fire" d="M150 262 q10 -24 20 0 q10 -28 22 0 q10 -26 20 0 q10 -24 20 0 q10 -22 18 0Z"/>
            </symbol>
            <symbol id="sk-s4" viewBox="0 0 400 300">
                <path class="f-water" d="M0 170 Q100 150 200 172 T400 166 V300 H0Z"/>
                <g class="l-wave"><path d="M30 200 q20 -8 40 0 t40 0"/><path d="M230 230 q20 -8 40 0 t40 0"/><path d="M120 262 q20 -8 40 0 t40 0"/></g>
                <g class="l-fiber"><path d="M80 214 q40 -14 80 0 t80 0"/><path d="M110 236 q40 -12 80 0 t80 0"/><path d="M140 194 q40 -12 80 0 t80 0"/></g>
                <g class="f-stone"><ellipse cx="60" cy="176" rx="26" ry="10"/><ellipse cx="340" cy="186" rx="30" ry="11"/></g>
            </symbol>
            <symbol id="sk-s5" viewBox="0 0 400 300">
                <ellipse class="f-water" cx="200" cy="210" rx="150" ry="58"/><ellipse class="l-ink" cx="200" cy="210" rx="150" ry="58" fill="none"/>
                <g class="l-fiber"><path d="M100 200 q50 -18 100 0 t100 0"/><path d="M110 222 q45 -14 90 0 t90 0"/><path d="M130 184 q40 -12 80 0 t80 0"/></g>
                <g class="f-ink"><circle cx="160" cy="200" r="3"/><circle cx="236" cy="226" r="2.5"/><circle cx="270" cy="190" r="3"/></g>
                <path class="l-ink" d="M300 80 L246 186 M314 86 L254 188" stroke-width="4"/><circle class="f-persimmon" cx="246" cy="190" r="5"/>
            </symbol>
            <symbol id="sk-s6" viewBox="0 0 400 300">
                <rect class="f-stone" x="90" y="210" width="220" height="40" rx="10"/>
                <path class="f-fiberfill" d="M130 210 q20 -26 50 -14 q24 -24 52 -6 q30 -16 46 14Z"/>
                <g transform="rotate(-24 250 120)"><rect class="f-wood" x="196" y="98" width="110" height="38" rx="8"/><rect class="f-wood" x="246" y="136" width="12" height="96" rx="5"/></g>
                <g class="l-ink"><path d="M150 180 l-14 -14M170 172 l-4 -18M240 174 l10 -16"/></g>
            </symbol>
            <symbol id="sk-s7" viewBox="0 0 400 300">
                <path class="f-wood" d="M70 150 H330 L314 262 H86Z"/><path class="f-water" d="M84 166 H316 L304 250 H96Z"/>
                <g class="l-fiber"><path d="M120 196 q30 -12 60 0 t60 0 t60 0"/><path d="M110 222 q30 -12 60 0 t60 0 t60 0"/></g>
                <g class="l-neri"><path d="M200 210 m-34 0 a34 18 0 1 1 68 0 a28 14 0 1 1 -52 2"/></g>
                <path class="l-wood" d="M250 70 L222 200" stroke-width="8"/>
            </symbol>
            <symbol id="sk-s8" viewBox="0 0 400 300">
                <path class="f-wood" d="M70 170 H330 L314 270 H86Z"/><path class="f-water" d="M84 184 H316 L304 258 H96Z"/>
                <rect class="l-indigo" x="110" y="150" width="180" height="70" rx="4" fill="none" stroke-width="7"/>
                <g class="l-screen"><path d="M130 152V218M150 152V218M170 152V218M190 152V218M210 152V218M230 152V218M250 152V218M270 152V218"/></g>
                <g class="l-drop"><path d="M150 232 v10M200 236 v12M250 232 v10"/></g>
            </symbol>
            <symbol id="sk-s9" viewBox="0 0 400 300">
                <path class="f-wood" d="M70 196 H330 L314 278 H86Z"/><path class="f-water" d="M84 208 H316 L304 266 H96Z"/>
                <g transform="rotate(-7 200 140)"><rect class="f-sheetwet" x="104" y="104" width="192" height="76" rx="4"/><rect class="l-indigo" x="104" y="104" width="192" height="76" rx="4" fill="none" stroke-width="7"/>
                <g class="l-wave"><path d="M120 132 q20 -8 40 0 t40 0 t40 0 t40 0"/><path d="M120 156 q20 -8 40 0 t40 0 t40 0 t40 0"/></g></g>
                <g class="l-ink"><path d="M64 140 q-16 14 0 28M336 116 q16 14 0 28"/></g>
            </symbol>
            <symbol id="sk-s10" viewBox="0 0 400 300">
                <rect class="f-wood" x="90" y="236" width="220" height="22" rx="4"/>
                <g class="f-sheetwet"><rect x="104" y="216" width="192" height="20" rx="3"/><rect x="108" y="198" width="192" height="20" rx="3"/><rect x="102" y="180" width="192" height="20" rx="3"/><rect x="106" y="162" width="192" height="20" rx="3"/></g>
                <g class="l-sheetedge"><path d="M104 216 H296M108 198 H300M102 180 H294M106 162 H298"/></g>
                <g class="l-drop"><path d="M140 262 v10M260 262 v10M200 266 v8"/></g>
            </symbol>
            <symbol id="sk-s11" viewBox="0 0 400 300">
                <path class="f-ground" d="M0 262 H400 V300 H0Z"/>
                <g transform="rotate(-10 150 260)"><rect class="f-wood" x="96" y="90" width="90" height="172" rx="4"/><rect class="f-paper" x="106" y="104" width="70" height="140" rx="2"/></g>
                <g transform="rotate(-10 270 260)"><rect class="f-wood" x="216" y="90" width="90" height="172" rx="4"/><rect class="f-paper" x="226" y="104" width="70" height="140" rx="2"/></g>
                <g class="l-wind"><path d="M20 120 q30 -10 60 0M24 150 q24 -8 48 0"/></g>
            </symbol>
            <symbol id="sk-s12" viewBox="0 0 400 300">
                <circle class="f-glow" cx="200" cy="140" r="96"/>
                <rect class="f-paper f-paper--lit" x="120" y="60" width="160" height="200" rx="3"/>
                <g class="l-cloud"><path d="M136 100 q30 -14 60 4 t64 -6"/><path d="M134 140 q36 12 70 -4 t58 6"/><path d="M140 182 q26 -12 56 2 t60 -6"/><path d="M136 222 q34 10 64 -2 t58 4"/></g>
                <g class="l-ink"><path d="M120 260 h-18 M280 260 h18"/></g>
            </symbol>
            <symbol id="sk-s13" viewBox="0 0 400 300">
                <rect class="f-paper" x="96" y="96" width="208" height="132" rx="4"/>
                <path class="l-ink" d="M96 100 L200 172 L304 100" fill="none"/>
                <g class="l-brush"><path d="M130 200 h56M130 214 h36"/></g>
                <circle class="f-persimmon" cx="270" cy="200" r="12"/>
                <path class="l-wind" d="M40 70 q40 -14 80 0 M300 60 q30 -10 60 0"/>
            </symbol>
        </defs>
    </svg>
</div>
