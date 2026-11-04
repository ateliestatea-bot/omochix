/**
 * OmochiX Labs: token / API cost calculator UI (core/tokens.js, core/cost.js).
 *
 * The price rows come from the server (omochixLabs.pricing) and are filtered
 * again with the browser's date (Cost.active), so a cached page never shows
 * an expired or stale price. Every number on screen is an estimate.
 */
(function (window, document) {
    'use strict';

    var L = window.OmochixLabs;
    var Text = window.OmochixLabsText;
    var Cost = window.OmochixLabsCost;
    if (!L) return;

    var SAMPLE_TEXT = 'あなたはカスタマーサポートの担当者です。次の問い合わせ内容を読み、丁寧な日本語で3文以内の返信文を作成してください。\n\n問い合わせ：注文した商品がまだ届きません。注文番号は 20261104-0012 です。いつ頃届きますか？';
    var DEFAULTS = { input: 2000, output: 800, cached: 0, requests: 100, days: 30, rate: '' };
    /** Shown only in review mode (an administrator outside production): the server sends unapproved rows nowhere else. */
    var STATES = { pending: '承認待ち', held: '保留' };

    L.tool('token-cost-calculator', function (root) {
        var limits = L.limits();
        var pricing = L.config.pricing || { models: [], providers: {}, max_age_days: 90 };
        var models = Cost.active(pricing.models, Text.today(), pricing.max_age_days || 90);
        var byId = {};
        models.forEach(function (m) { byId[m.id] = m; });

        var text = L.$('#labs-cost-text', root);
        var fields = {
            input: L.$('#labs-cost-input', root),
            output: L.$('#labs-cost-output', root),
            cached: L.$('#labs-cost-cached', root),
            requests: L.$('#labs-cost-requests', root),
            days: L.$('#labs-cost-days', root),
            rate: L.$('#labs-cost-rate', root)
        };
        var modelsBox = L.$('[data-labs-models]', root);
        var noModels = L.$('[data-labs-no-models]', root);
        var rowsBody = L.$('[data-labs-cost-rows]', root);
        var notes = L.$('[data-labs-cost-notes]', root);
        var periodHead = L.$('[data-labs-period-head]', root);
        var out = { chars: L.$('[data-labs-chars]', root), tokens: L.$('[data-labs-tokens]', root), range: L.$('[data-labs-range]', root) };
        var estimate = null;
        var estTimer = null;
        var calcTimer = null;
        var lastRows = [];

        /** Rebuild the model checkboxes from the rows valid today, keeping the server's ticks. */
        function renderModels() {
            var checked = {};
            L.$all('[data-labs-model]', modelsBox).forEach(function (box) { checked[box.value] = box.checked; });
            modelsBox.textContent = '';
            Object.keys(pricing.providers || {}).forEach(function (provider) {
                var rows = models.filter(function (m) { return m.provider === provider; });
                if (!rows.length) return;
                var group = document.createElement('div');
                group.className = 'labs-models__group';
                var name = document.createElement('p');
                name.className = 'labs-models__provider';
                name.textContent = pricing.providers[provider];
                group.appendChild(name);
                rows.forEach(function (m) {
                    var label = document.createElement('label');
                    label.className = 'labs-check';
                    var box = document.createElement('input');
                    box.type = 'checkbox';
                    box.value = m.id;
                    box.setAttribute('data-labs-model', '');
                    box.checked = !!checked[m.id];
                    label.appendChild(box);
                    label.appendChild(document.createTextNode(' ' + m.label));
                    if (STATES[m.approval]) {
                        var tag = document.createElement('span');
                        tag.className = 'labs-tag labs-tag--state';
                        tag.textContent = STATES[m.approval];
                        label.appendChild(document.createTextNode(' '));
                        label.appendChild(tag);
                    }
                    group.appendChild(label);
                });
                modelsBox.appendChild(group);
            });
            noModels.hidden = models.length > 0;
            if (models.length && !L.$all('[data-labs-model]:checked', modelsBox).length) {
                var first = L.$('[data-labs-model]', modelsBox);
                if (first) first.checked = true;
            }
        }

        function readInt(input, max) {
            var raw = input.value.trim();
            if (raw === '') return 0;
            var n = Number(raw);
            if (!isFinite(n) || n < 0 || n > max || Math.floor(n) !== n) {
                input.setAttribute('aria-invalid', 'true');
                return null;
            }
            input.removeAttribute('aria-invalid');
            return n;
        }

        function readRate() {
            var raw = fields.rate.value.trim();
            if (raw === '') { fields.rate.removeAttribute('aria-invalid'); return 0; }
            var n = Number(raw);
            if (!isFinite(n) || n <= 0 || n > 10000) { fields.rate.setAttribute('aria-invalid', 'true'); return null; }
            fields.rate.removeAttribute('aria-invalid');
            return n;
        }

        function cell(tr, tag, value, sub) {
            var node = document.createElement(tag);
            if (tag === 'th') node.scope = 'row';
            node.textContent = value;
            if (sub) {
                var span = document.createElement('span');
                span.className = 'labs-table__sub';
                span.textContent = sub;
                node.appendChild(span);
            }
            tr.appendChild(node);
            return node;
        }

        function calculate(fromUser) {
            var usage = {
                input: readInt(fields.input, 1e9),
                output: readInt(fields.output, 1e9),
                cached: readInt(fields.cached, 1e9),
                requestsPerDay: readInt(fields.requests, 1e7),
                days: readInt(fields.days, 3660)
            };
            var rate = readRate();
            rowsBody.textContent = '';
            lastRows = [];
            var invalid = Object.keys(usage).some(function (k) { return usage[k] === null; }) || rate === null;
            if (invalid) {
                L.showError(root, '数値の欄には0以上の整数を入れてください（為替は0より大きい数）。赤枠の欄を確認してください。');
                notes.textContent = '';
                if (fromUser) L.track('labs_tool_error', { action: 'calculate', error_code: 'invalid_input' });
                return;
            }
            L.clearError(root);
            periodHead.textContent = '期間合計（' + usage.days + '日）';
            var selected = L.$all('[data-labs-model]:checked', modelsBox).map(function (b) { return byId[b.value]; }).filter(Boolean);
            var flags = {};
            selected.forEach(function (model) {
                var r = Cost.compute(model, usage);
                r.notes.forEach(function (n) { flags[n] = true; });
                var tr = document.createElement('tr');
                cell(tr, 'th', model.label, pricing.providers[model.provider] || '');
                [r.perRequest, r.perDay, r.total].forEach(function (v) {
                    cell(tr, 'td', Cost.formatUSD(v), rate ? '約' + Cost.formatJPY(v, rate) : '');
                });
                var priceCell = cell(tr, 'td', '入力 ' + Cost.formatPrice(r.prices.input) + ' / 出力 ' + Cost.formatPrice(r.prices.output), 'キャッシュ ' + Cost.formatPrice(r.prices.cached) + (r.tier === 'long' ? '（長いコンテキストの単価）' : ''));
                (model.caveats || []).forEach(function (code) {
                    var label = (pricing.caveat_labels || {})[code];
                    if (!label) return;
                    var note = document.createElement('span');
                    note.className = 'labs-table__sub labs-table__caveat';
                    note.textContent = '※' + label;
                    priceCell.appendChild(note);
                });
                if (STATES[model.approval]) {
                    flags.unapproved = true;
                    var state = document.createElement('span');
                    state.className = 'labs-tag labs-tag--state';
                    state.textContent = STATES[model.approval];
                    tr.firstChild.appendChild(state);
                }
                var src = cell(tr, 'td', '');
                var a = document.createElement('a');
                a.href = model.source_url;
                a.rel = 'noopener noreferrer external';
                a.textContent = '公式ページ';
                src.appendChild(a);
                var sub = document.createElement('span');
                sub.className = 'labs-table__sub';
                sub.textContent = model.verified_at + (model.valid_until ? ' / ' + model.valid_until + 'までの価格' : '');
                src.appendChild(sub);
                rowsBody.appendChild(tr);
                lastRows.push([model.label, Cost.formatUSD(r.perRequest), Cost.formatUSD(r.perDay), Cost.formatUSD(r.total), model.verified_at]);
            });
            var lines = ['金額はすべて推定値です（標準ティアの料金。キャッシュ書き込みの料金は含みません）。'];
            if (flags.long_context) lines.push('入力が長いため、一部のモデルは長いコンテキストの単価で計算しています。');
            if (flags.cached_not_listed) lines.push('キャッシュ単価が公開されていないモデルは、キャッシュ分も通常の入力単価で計算しています。');
            if (flags.unapproved) lines.push('「承認待ち」「保留」の価格は確認用で、本番のページには表示されません。');
            if (!selected.length && models.length) lines.push('比べるモデルにチェックを入れてください。');
            notes.textContent = lines.join(' ');
            if (fromUser && selected.length) {
                L.trackRun('calculate', 0, true);
                L.track('labs_tool_success', { action: 'calculate', duration_bucket: 'fast' });
            }
        }

        function scheduleCalc() {
            window.clearTimeout(calcTimer);
            calcTimer = window.setTimeout(function () { calculate(true); }, 200);
        }

        function showEstimate(est) {
            estimate = est;
            out.chars.textContent = est.chars.toLocaleString('ja-JP');
            out.tokens.textContent = '約' + est.tokens.toLocaleString('ja-JP');
            out.range.textContent = est.chars ? est.low.toLocaleString('ja-JP') + '〜' + est.high.toLocaleString('ja-JP') : '—';
        }

        function runEstimate(fromUser) {
            var value = text.value;
            var over = L.overLimit(value, limits.input);
            if (over) {
                L.showError(root, over);
                if (fromUser) L.track('labs_tool_error', { action: 'estimate', error_code: 'too_large' });
                return;
            }
            var bytes = Text.byteLength(value);
            if (fromUser && value) L.trackRun('estimate', bytes, true);
            L.run('tokens.estimate', { text: value }, bytes > limits.worker).then(function (est) {
                showEstimate(est);
            }, function (err) {
                L.showError(root, L.errorMessage(err));
                if (fromUser) L.track('labs_tool_error', { action: 'estimate', error_code: L.errorCode(err) });
            });
        }

        function setDefaults() {
            fields.input.value = DEFAULTS.input;
            fields.output.value = DEFAULTS.output;
            fields.cached.value = DEFAULTS.cached;
            fields.requests.value = DEFAULTS.requests;
            fields.days.value = DEFAULTS.days;
            fields.rate.value = DEFAULTS.rate;
            Object.keys(fields).forEach(function (k) { fields[k].removeAttribute('aria-invalid'); });
        }

        text.addEventListener('input', function () {
            window.clearTimeout(estTimer);
            estTimer = window.setTimeout(function () { runEstimate(true); }, 250);
        });
        Object.keys(fields).forEach(function (k) { fields[k].addEventListener('input', scheduleCalc); });
        modelsBox.addEventListener('change', scheduleCalc);
        L.$all('[data-labs-use]', root).forEach(function (button) {
            button.addEventListener('click', function () {
                if (!estimate || !estimate.chars) { L.announce(root, '先に文章を入力してください'); text.focus(); return; }
                var target = button.getAttribute('data-labs-use') === 'output' ? fields.output : fields.input;
                target.value = estimate.tokens;
                calculate(true);
                L.announce(root, (target === fields.output ? '出力' : '入力') + 'トークンを' + estimate.tokens + 'にしました');
            });
        });
        L.$('[data-labs-copy]', root).addEventListener('click', function () {
            if (!lastRows.length) return;
            var head = ['モデル', '1回(USD)', '1日(USD)', '期間合計(USD)', '価格確認日'].join('\t');
            var body = lastRows.map(function (r) { return r.join('\t'); }).join('\n');
            L.copy(root, head + '\n' + body + '\n※推定値です。実際の請求額とは異なる場合があります。', 'summary');
        });
        L.$('[data-labs-sample]', root).addEventListener('click', function () {
            text.value = SAMPLE_TEXT;
            runEstimate(false);
            fields.input.value = 3000;
            fields.output.value = 1000;
            fields.cached.value = 0;
            fields.requests.value = 200;
            fields.days.value = 30;
            fields.rate.value = 150;
            calculate(true);
            L.announce(root, L.strings.sample);
        });
        L.$('[data-labs-reset]', root).addEventListener('click', function () {
            text.value = '';
            showEstimate({ chars: 0, tokens: 0, low: 0, high: 0 });
            setDefaults();
            calculate(false);
            L.clearError(root);
            L.announce(root, L.strings.reset);
            text.focus();
        });

        renderModels();
        calculate(false);
    });
}(window, document));
