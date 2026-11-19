<?php
/**
 * Design Library demo: Micro Interaction Collection. Markup only; rendered by the isolated demo endpoint.
 * A fictional household & expense app ("HAKARI"). Amounts and items are fictional; nothing typed here is
 * sent or stored. Native controls (checkbox switches, radios, range, inputs) work without JavaScript;
 * JavaScript adds feedback: saved marks, validation, submit states, undo.
 */
defined('ABSPATH') || exit;
?>
<div class="mi" data-mi>
    <header class="mi-top">
        <span class="mi-brand"><span class="mi-brand__mark" aria-hidden="true"><i></i><i></i></span>HAKARI</span>
        <span class="mi-top__note">家計と経費の記録（架空のアプリ）</span>
        <nav class="mi-nav" aria-label="画面">
            <a href="#mi-settings">設定</a><a href="#mi-entry">記録</a><a href="#mi-list">一覧</a>
        </nav>
    </header>

    <main class="mi-grid">
        <section class="mi-card" id="mi-settings" aria-labelledby="mi-settings-h">
            <header class="mi-card__head">
                <p class="mi-step">01 設定を変える</p>
                <h2 id="mi-settings-h">通知と予算</h2>
                <p class="mi-saved" data-mi-saved role="status" aria-live="polite"></p>
            </header>
            <ul class="mi-rows">
                <li class="mi-row">
                    <label for="mi-sw-1"><span class="mi-row__t">支出の通知</span><span class="mi-row__d">記録するたびに知らせる</span></label>
                    <input class="mi-switch" id="mi-sw-1" type="checkbox" role="switch" checked data-mi-setting>
                </li>
                <li class="mi-row">
                    <label for="mi-sw-2"><span class="mi-row__t">週ごとのまとめ</span><span class="mi-row__d">日曜の夜に 1 回</span></label>
                    <input class="mi-switch" id="mi-sw-2" type="checkbox" role="switch" data-mi-setting>
                </li>
                <li class="mi-row is-disabled">
                    <label for="mi-sw-3"><span class="mi-row__t">家族と共有</span><span class="mi-row__d" id="mi-sw-3-why">共有には家族の招待が必要です</span></label>
                    <input class="mi-switch" id="mi-sw-3" type="checkbox" role="switch" disabled aria-describedby="mi-sw-3-why">
                </li>
            </ul>
            <fieldset class="mi-seg">
                <legend>月の始まり</legend>
                <div class="mi-seg__track">
                    <input type="radio" name="mi-start" id="mi-start-1" value="1" checked data-mi-setting><label for="mi-start-1">1 日</label>
                    <input type="radio" name="mi-start" id="mi-start-25" value="25" data-mi-setting><label for="mi-start-25">25 日（給料日）</label>
                    <span class="mi-seg__thumb" aria-hidden="true"></span>
                </div>
            </fieldset>
            <div class="mi-range">
                <label for="mi-alert">予算の何 % で知らせるか</label>
                <div class="mi-range__row">
                    <input type="range" id="mi-alert" min="50" max="100" step="5" value="80" aria-describedby="mi-alert-out" data-mi-setting data-mi-range>
                    <output id="mi-alert-out" for="mi-alert" data-mi-range-out>80%</output>
                </div>
            </div>
        </section>

        <section class="mi-card" id="mi-entry" aria-labelledby="mi-entry-h">
            <header class="mi-card__head">
                <p class="mi-step">02 入力を確かめる</p>
                <h2 id="mi-entry-h">支出を記録</h2>
            </header>
            <div class="mi-form" data-mi-form>
                <div class="mi-field" data-mi-field="amount">
                    <label for="mi-amount">金額（円）</label>
                    <div class="mi-input">
                        <input id="mi-amount" type="text" inputmode="numeric" autocomplete="off" value="1,280" aria-describedby="mi-amount-msg" data-mi-amount>
                        <svg class="mi-ok" viewBox="0 0 20 20" aria-hidden="true"><path pathLength="1" d="M4 10.5l4 4 8-9"/></svg>
                    </div>
                    <p class="mi-msg" id="mi-amount-msg">1 円から 1,000,000 円まで</p>
                </div>
                <div class="mi-field">
                    <label for="mi-cat">分類</label>
                    <select id="mi-cat">
                        <option>食費</option><option selected>本・学び</option><option>交通</option><option>日用品</option>
                    </select>
                </div>
                <div class="mi-field" data-mi-field="memo">
                    <label for="mi-memo">メモ</label>
                    <input id="mi-memo" type="text" autocomplete="off" maxlength="40" value="技術書（見本）" aria-describedby="mi-memo-count" data-mi-memo>
                    <p class="mi-msg mi-count" id="mi-memo-count" data-mi-count>7 / 40 文字</p>
                </div>
                <div class="mi-submit">
                    <button class="mi-btn mi-btn--primary" type="button" data-mi-submit data-state="idle">
                        <span class="mi-btn__spin" aria-hidden="true"></span>
                        <svg class="mi-btn__check" viewBox="0 0 20 20" aria-hidden="true"><path pathLength="1" d="M4 10.5l4 4 8-9"/></svg>
                        <span class="mi-btn__label" data-mi-submit-label>記録する</span>
                    </button>
                    <label class="mi-fail"><input type="checkbox" data-mi-fail> 失敗する場合を試す</label>
                </div>
                <p class="mi-result" data-mi-result role="status" aria-live="polite"></p>
            </div>
            <div class="mi-states" aria-label="ボタンの状態の見本">
                <p class="mi-states__h">ボタンの状態（見本）</p>
                <ul>
                    <li><span class="mi-btn mi-btn--sample">通常</span><small>通常</small></li>
                    <li><span class="mi-btn mi-btn--sample is-hover">通常</span><small>ホバー</small></li>
                    <li><span class="mi-btn mi-btn--sample is-focus">通常</span><small>フォーカス</small></li>
                    <li><span class="mi-btn mi-btn--sample is-active">通常</span><small>押下</small></li>
                    <li><span class="mi-btn mi-btn--sample is-disabled">通常</span><small>無効</small></li>
                    <li><span class="mi-btn mi-btn--sample is-loading"><span class="mi-btn__spin" aria-hidden="true"></span>処理中</span><small>読み込み中</small></li>
                    <li><span class="mi-btn mi-btn--sample is-success">✓ 完了</span><small>成功</small></li>
                    <li><span class="mi-btn mi-btn--sample is-error">! 失敗</span><small>失敗</small></li>
                </ul>
            </div>
        </section>

        <section class="mi-card mi-card--wide" id="mi-list" aria-labelledby="mi-list-h">
            <header class="mi-card__head">
                <p class="mi-step">03 状態を切り替える</p>
                <h2 id="mi-list-h">今月の経費</h2>
                <p class="mi-total">未精算 <strong data-mi-total>¥5,860</strong></p>
            </header>
            <ul class="mi-items" data-mi-items>
                <li class="mi-item" data-amount="1280">
                    <input class="mi-check" type="checkbox" id="mi-it-1" data-mi-paid>
                    <label for="mi-it-1"><span class="mi-item__t">技術書（見本）</span><span class="mi-item__d">本・学び · 11/12</span></label>
                    <span class="mi-item__amt">¥1,280</span>
                    <button class="mi-del" type="button" data-mi-del aria-label="技術書（見本）を削除"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M5 6h10M8 6V4h4v2M7 6l.6 10h4.8L13 6"/></svg></button>
                </li>
                <li class="mi-item" data-amount="2400">
                    <input class="mi-check" type="checkbox" id="mi-it-2" data-mi-paid>
                    <label for="mi-it-2"><span class="mi-item__t">打ち合わせの交通費</span><span class="mi-item__d">交通 · 11/10</span></label>
                    <span class="mi-item__amt">¥2,400</span>
                    <button class="mi-del" type="button" data-mi-del aria-label="打ち合わせの交通費を削除"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M5 6h10M8 6V4h4v2M7 6l.6 10h4.8L13 6"/></svg></button>
                </li>
                <li class="mi-item" data-amount="2180">
                    <input class="mi-check" type="checkbox" id="mi-it-3" data-mi-paid>
                    <label for="mi-it-3"><span class="mi-item__t">文具とプリント</span><span class="mi-item__d">日用品 · 11/08</span></label>
                    <span class="mi-item__amt">¥2,180</span>
                    <button class="mi-del" type="button" data-mi-del aria-label="文具とプリントを削除"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M5 6h10M8 6V4h4v2M7 6l.6 10h4.8L13 6"/></svg></button>
                </li>
            </ul>
            <p class="mi-note">チェックで精算済みにすると、未精算の合計が変わります。削除は 5 秒以内なら元に戻せます。</p>
        </section>
    </main>

    <div class="mi-toast" data-mi-toast role="status" aria-live="polite" hidden>
        <span data-mi-toast-text></span>
        <button type="button" class="mi-toast__undo" data-mi-undo>元に戻す</button>
        <span class="mi-toast__bar" aria-hidden="true" data-mi-toast-bar></span>
    </div>
    <p class="mi-foot">架空のアプリの見本です。入力した内容は送信も保存もされません。</p>
</div>
