/**
 * OmochiX Labs: text diff UI (core logic in core/diff.js, run in the Worker).
 * Rows are built with DOM nodes only; changes are marked with +/− and
 * screen-reader text as well as colour.
 */
(function (window, document) {
    'use strict';

    var L = window.OmochixLabs;
    var Text = window.OmochixLabsText;
    if (!L) return;

    var MAX_ROWS = 5000;
    var SAMPLE_OLD = 'OmochiX Labs は無料のツール集です。\nJSON整形・テキスト差分・robots.txt生成を公開しています。\n入力はブラウザの中で処理します。\nfunction greet(name) {\n  return "Hello, " + name;\n}';
    var SAMPLE_NEW = 'OmochiX Labs は無料のオンラインツール集です。\nJSON整形・テキスト差分・robots.txt生成・API料金計算を公開しています。\n入力はブラウザの中で処理します。\nfunction greet(name, greeting) {\n  return greeting + ", " + name;\n}\n// 新しい行';
    var LABEL = { insert: '追加', delete: '削除', equal: '変更なし' };
    var MARK = { insert: '+', delete: '−', equal: ' ' };

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    /** Line content, with changed words wrapped in <ins>/<del>. */
    function content(row) {
        var cell = el('td', 'labs-diff__code');
        if (row.segments) {
            row.segments.forEach(function (seg) {
                if (seg.t === 'equal') cell.appendChild(document.createTextNode(seg.s));
                else cell.appendChild(el(seg.t === 'insert' ? 'ins' : 'del', '', seg.s));
            });
        } else {
            cell.appendChild(document.createTextNode(row.text === '' ? ' ' : row.text));
        }
        return cell;
    }

    function marker(type) {
        var cell = el('td', 'labs-diff__mark is-' + type);
        cell.appendChild(el('span', '', MARK[type])).setAttribute('aria-hidden', 'true');
        if (type !== 'equal') cell.appendChild(el('span', 'labs-sr-only', LABEL[type]));
        return cell;
    }

    function head(labels) {
        var thead = el('thead', 'labs-sr-only');
        var tr = el('tr');
        labels.forEach(function (label) { var th = el('th', '', label); th.scope = 'col'; tr.appendChild(th); });
        thead.appendChild(tr);
        return thead;
    }

    function unifiedTable(rows) {
        var table = el('table', 'labs-diff__table');
        table.appendChild(head(['変更前の行', '変更後の行', '種類', '内容']));
        var body = el('tbody');
        rows.slice(0, MAX_ROWS).forEach(function (row) {
            var tr = el('tr', 'labs-diff__row is-' + row.type);
            tr.appendChild(el('td', 'labs-diff__no', row.oldNo ? String(row.oldNo) : ''));
            tr.appendChild(el('td', 'labs-diff__no', row.newNo ? String(row.newNo) : ''));
            tr.appendChild(marker(row.type));
            tr.appendChild(content(row));
            body.appendChild(tr);
        });
        table.appendChild(body);
        return table;
    }

    /** Side by side: equal rows on both sides, a change block's deletions left and insertions right. */
    function splitTable(rows) {
        var table = el('table', 'labs-diff__table labs-diff__table--split');
        table.appendChild(head(['変更前の行', '種類', '変更前', '変更後の行', '種類', '変更後']));
        var body = el('tbody');
        var shown = 0;
        var i = 0;
        function side(tr, row) {
            if (!row) {
                tr.appendChild(el('td', 'labs-diff__no'));
                tr.appendChild(el('td', 'labs-diff__mark'));
                tr.appendChild(el('td', 'labs-diff__code is-empty'));
                return;
            }
            var no = row.type === 'insert' ? row.newNo : row.oldNo;
            tr.appendChild(el('td', 'labs-diff__no', String(no)));
            tr.appendChild(marker(row.type));
            var cell = content(row);
            cell.classList.add('is-' + row.type);
            tr.appendChild(cell);
        }
        while (i < rows.length && shown < MAX_ROWS) {
            if (rows[i].type === 'equal') {
                var tr = el('tr', 'labs-diff__row is-equal');
                side(tr, rows[i]);
                side(tr, { type: 'equal', oldNo: rows[i].newNo, text: rows[i].newText });
                body.appendChild(tr);
                shown++;
                i++;
                continue;
            }
            var dels = [];
            var ins = [];
            while (i < rows.length && rows[i].type !== 'equal') {
                (rows[i].type === 'delete' ? dels : ins).push(rows[i]);
                i++;
            }
            for (var k = 0; k < Math.max(dels.length, ins.length) && shown < MAX_ROWS; k++, shown++) {
                var row = el('tr', 'labs-diff__row is-change');
                side(row, dels[k]);
                side(row, ins[k]);
                body.appendChild(row);
            }
        }
        table.appendChild(body);
        return table;
    }

    L.tool('text-diff', function (root) {
        var oldInput = L.$('#labs-diff-old', root);
        var newInput = L.$('#labs-diff-new', root);
        var ws = L.$('#labs-diff-ws', root);
        var cs = L.$('#labs-diff-case', root);
        var result = L.$('[data-labs-result]', root);
        var summary = L.$('[data-labs-summary]', root);
        var output = L.$('[data-labs-output]', root);
        var copyBtn = L.$('[data-labs-copy]', root);
        var limits = L.limits();
        var last = null;

        function view() {
            var checked = L.$('input[name="labs-diff-view"]:checked', root);
            return checked ? checked.value : 'unified';
        }

        function render() {
            output.textContent = '';
            if (!last) { result.hidden = true; return; }
            var s = last.stats;
            var parts = [];
            if (s.same) parts.push('違いはありません。');
            else parts.push('追加 ' + s.added + '行・削除 ' + s.removed + '行（変更なし ' + s.unchanged + '行）');
            if (last.lineEndings.old !== last.lineEndings.new && last.lineEndings.old !== 'none' && last.lineEndings.new !== 'none') {
                parts.push('改行コードが違います（変更前：' + last.lineEndings.old + '、変更後：' + last.lineEndings.new + '）。改行コードの違いは無視して比べました。');
            }
            if (last.approximate) parts.push('違いが多いため、変更部分をまとめて削除・追加として表示しています。');
            if (last.rows.length > MAX_ROWS) parts.push('表示は最初の' + MAX_ROWS + '行までです。全体は「diff形式でコピー」で取り出せます。');
            summary.textContent = parts.join(' ');
            if (!s.same) output.appendChild(view() === 'split' ? splitTable(last.rows) : unifiedTable(last.rows));
            output.hidden = s.same;
            result.hidden = false;
        }

        function fail(message, code) {
            L.showError(root, message);
            L.announce(root, message);
            L.track('labs_tool_error', { action: 'compare', error_code: code });
        }

        function lineCount(text) { return (text.match(/\n/g) || []).length + 1; }

        function exec() {
            L.clearError(root);
            var a = oldInput.value;
            var b = newInput.value;
            if (!a && !b) { fail('比べるテキストを「変更前」と「変更後」に入力してください。', 'invalid_input'); oldInput.focus(); return; }
            var over = L.overLimit(a, limits.input) || L.overLimit(b, limits.input);
            if (over) { fail(over, 'too_large'); return; }
            if (lineCount(a) > limits.lines || lineCount(b) > limits.lines) { fail('行数が多すぎます（片側' + limits.lines + '行まで）。範囲を絞ってお試しください。', 'too_large'); return; }
            var started = Date.now();
            L.trackRun('compare', Math.max(Text.byteLength(a), Text.byteLength(b)));
            L.setBusy(root, true);
            L.run('diff.lines', { oldText: a, newText: b, ignoreWhitespace: ws.checked, ignoreCase: cs.checked, oldName: '変更前', newName: '変更後' }, true).then(function (res) {
                L.setBusy(root, false);
                last = res;
                render();
                L.announce(root, summary.textContent);
                L.track('labs_tool_success', { action: 'compare', duration_bucket: Text.durationBucket(Date.now() - started) });
            }, function (err) {
                L.setBusy(root, false);
                fail(L.errorMessage(err), L.errorCode(err));
            });
        }

        L.$('[data-labs-run]', root).addEventListener('click', exec);
        L.bindRunShortcut(root, exec);
        L.$all('input[name="labs-diff-view"]', root).forEach(function (radio) { radio.addEventListener('change', render); });
        [ws, cs].forEach(function (box) { box.addEventListener('change', function () { if (last) exec(); }); });
        L.$('[data-labs-sample]', root).addEventListener('click', function () {
            oldInput.value = SAMPLE_OLD;
            newInput.value = SAMPLE_NEW;
            L.announce(root, L.strings.sample);
            exec();
        });
        L.$('[data-labs-swap]', root).addEventListener('click', function () {
            var t = oldInput.value;
            oldInput.value = newInput.value;
            newInput.value = t;
            if (last) exec();
            else L.announce(root, '左右を入れ替えました');
        });
        L.$('[data-labs-reset]', root).addEventListener('click', function () {
            oldInput.value = '';
            newInput.value = '';
            last = null;
            render();
            L.clearError(root);
            L.announce(root, L.strings.reset);
            oldInput.focus();
        });
        copyBtn.addEventListener('click', function () { if (last) L.copy(root, last.unified, 'diff'); });
    });
}(window, document));
