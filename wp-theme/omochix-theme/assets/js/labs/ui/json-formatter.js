/**
 * OmochiX Labs: JSON formatter UI (core logic in core/json.js).
 */
(function (window, document) {
    'use strict';

    var L = window.OmochixLabs;
    var Text = window.OmochixLabsText;
    var Json = window.OmochixLabsJson;
    if (!L) return;

    var SAMPLE = '{"site":"OmochiX","section":"Labs","updated":"2026-11-04","tools":[{"slug":"json-formatter","name":"JSON整形・検証","free":true,"tags":["json","整形"]},{"slug":"text-diff","name":"テキスト差分比較","free":true,"tags":[]}],"order_id":12345678901234567890,"price":null}';
    var ROOT_TYPES = { object: 'オブジェクト', array: '配列', string: '文字列', number: '数値', literal: 'true / false / null' };

    L.tool('json-formatter', function (root) {
        var input = L.$('#labs-json-input', root);
        var output = L.$('#labs-json-output', root);
        var indentSel = L.$('#labs-json-indent', root);
        var sortBox = L.$('#labs-json-sort', root);
        var stats = L.$('[data-labs-stats]', root);
        var warnBox = L.$('[data-labs-warnings]', root);
        var copyBtn = L.$('[data-labs-copy]', root);
        var saveBtn = L.$('[data-labs-download]', root);
        var limits = L.limits();
        var lastAction = 'format';

        function setOutput(text) {
            output.value = text;
            copyBtn.disabled = !text;
            saveBtn.disabled = !text;
        }

        function item(dl, label, value) {
            var wrap = document.createElement('div');
            var dt = document.createElement('dt');
            var dd = document.createElement('dd');
            dt.textContent = label;
            dd.textContent = value;
            wrap.appendChild(dt);
            wrap.appendChild(dd);
            dl.appendChild(wrap);
        }

        function renderStats(s, bytes) {
            stats.textContent = '';
            if (!s) { stats.hidden = true; return; }
            item(stats, '全体の種類', ROOT_TYPES[s.root] || s.root);
            item(stats, 'オブジェクト', s.objects + '個');
            item(stats, '配列', s.arrays + '個');
            item(stats, '最大の深さ', s.maxDepth + '段');
            item(stats, '結果のサイズ', Text.formatBytes(bytes));
            stats.hidden = false;
        }

        function renderWarnings(list) {
            var ul = L.$('ul', warnBox);
            ul.textContent = '';
            (list || []).forEach(function (w) {
                var li = document.createElement('li');
                li.textContent = w.line + '行目 ' + w.column + '文字目：' + w.message;
                ul.appendChild(li);
            });
            warnBox.hidden = !(list && list.length);
        }

        function fail(message, code, detail, action) {
            L.showError(root, message, detail);
            L.announce(root, message);
            L.track('labs_tool_error', { action: action, error_code: code });
        }

        function exec(action) {
            lastAction = action;
            var text = input.value;
            L.clearError(root);
            if (!text.trim()) {
                setOutput('');
                renderStats(null);
                renderWarnings([]);
                fail('JSONを入力してください。', 'invalid_input', null, action);
                input.focus();
                return;
            }
            var over = L.overLimit(text, limits.input);
            if (over) { fail(over, 'too_large', null, action); return; }
            var bytes = Text.byteLength(text);
            var indent = action === 'minify' ? 0 : (indentSel.value === 'tab' ? '\t' : parseInt(indentSel.value, 10));
            var started = Date.now();
            L.trackRun(action, bytes);
            L.setBusy(root, true);
            L.run('json.format', { text: text, indent: indent, sortKeys: sortBox.checked }, bytes > limits.worker).then(function (res) {
                L.setBusy(root, false);
                renderWarnings(res.warnings);
                if (!res.ok) {
                    setOutput('');
                    renderStats(null);
                    var ex = Json.excerpt(text, res.error.line, res.error.column);
                    fail(res.error.line + '行目 ' + res.error.column + '文字目：' + res.error.message, 'invalid_input', ex.text + '\n' + ex.caret, action);
                    return;
                }
                setOutput(res.output);
                renderStats(res.stats, Text.byteLength(res.output));
                var done = action === 'minify' ? '圧縮しました。' : '整形しました。';
                L.announce(root, done + (res.warnings.length ? '注意が' + res.warnings.length + '件あります。' : ''));
                L.track('labs_tool_success', { action: action, duration_bucket: Text.durationBucket(Date.now() - started) });
            }, function (err) {
                L.setBusy(root, false);
                fail(L.errorMessage(err), L.errorCode(err), null, action);
            });
        }

        L.$all('[data-labs-run]', root).forEach(function (button) {
            button.addEventListener('click', function () { exec(button.getAttribute('data-action')); });
        });
        L.bindRunShortcut(root, function () { exec('format'); });

        L.$('[data-labs-sample]', root).addEventListener('click', function () {
            input.value = SAMPLE;
            L.announce(root, L.strings.sample);
            exec('format');
        });
        L.$('[data-labs-reset]', root).addEventListener('click', function () {
            input.value = '';
            setOutput('');
            renderStats(null);
            renderWarnings([]);
            L.clearError(root);
            L.announce(root, L.strings.reset);
            input.focus();
        });
        copyBtn.addEventListener('click', function () { L.copy(root, output.value, 'output'); });
        saveBtn.addEventListener('click', function () {
            if (!output.value) return;
            L.download(output.value, lastAction === 'minify' ? 'minified.json' : 'formatted.json', 'application/json');
        });
        [indentSel, sortBox].forEach(function (control) {
            control.addEventListener('change', function () { if (output.value) exec(lastAction === 'minify' ? 'minify' : 'format'); });
        });
        setOutput('');
    });
}(window, document));
