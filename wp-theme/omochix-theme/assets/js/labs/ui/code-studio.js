/**
 * OmochiX Labs: Code Studio UI (HTML/CSS, Phase 1).
 *
 * Security (docs/labs/CODE-STUDIO-DESIGN.md §5):
 *  - The learner's code is read from textarea.value and only ever reaches
 *    this page's DOM through textContent. It is never innerHTML here.
 *  - The preview is <iframe sandbox=""> (no scripts, opaque origin, no
 *    forms / popups / top navigation) showing a srcdoc that DOMPurify and
 *    core/codestudio.js built, with an enforced "default-src 'none'" policy.
 *  - Checks and the glossary read an inert DOMParser document and text.
 *  - Nothing is sent or stored: no fetch, no storage, no cookies.
 */
(function (window, document) {
    'use strict';

    var L = window.OmochixLabs;
    var S = window.OmochixLabsStudio;
    var D = window.OmochixLabsDiff;
    var Text = window.OmochixLabsText;
    if (!L || !S) return;

    var DEBOUNCE_MS = 300;
    var MAX_DIFF_ROWS = 400;
    var MARK = { insert: '+', delete: '−', equal: ' ' };
    var MARK_LABEL = { insert: '追加', delete: '削除' };
    var REMOVED = {
        markup: '「<」などの記号', escape: '「\\」を使った書き方', 'import': '@import', 'at-rule': '@font-face などの規則',
        url: '外部の URL（url(…)）', 'function': '画像を読み込む関数', legacy: '古いスクリプト用の書き方', unstable: '読み取れない CSS（全体）',
        too_deep: '入れ子が深すぎる CSS（style 属性）'
    };

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    /** A preview frame. sandbox="" is set before any content and never relaxed. */
    function frame(title) {
        var f = document.createElement('iframe');
        f.setAttribute('sandbox', '');
        f.setAttribute('referrerpolicy', 'no-referrer');
        f.setAttribute('title', title);
        f.className = 'labs-studio__frame';
        return f;
    }

    L.tool('code-studio', function (root) {
        var studio = (L.config && L.config.studio) || {};
        var lessons = studio.lessons || [];
        var glossary = studio.glossary || { tags: {}, properties: {} };
        var purify = window.DOMPurify;
        var limits = L.limits();
        var htmlInput = L.$('#labs-studio-html', root);
        var cssInput = L.$('#labs-studio-css', root);
        var select = L.$('#labs-studio-lesson', root);
        var stage = L.$('[data-studio-stage]', root);
        var notes = L.$('[data-studio-notes]', root);
        var sizeNote = L.$('[data-studio-size]', root);
        var compare = L.$('[data-studio-compare]', root);
        var tasks = L.$('[data-studio-tasks]', root);
        var progress = L.$('[data-studio-progress]', root);
        var glossaryList = L.$('[data-studio-glossary]', root);
        var runButton = L.$('[data-labs-run]', root);

        if (!lessons.length || !purify || typeof purify.sanitize !== 'function' || !('srcdoc' in document.createElement('iframe')) || !('sandbox' in document.createElement('iframe'))) {
            // No isolated preview is possible: show nothing rather than an unsafe fallback.
            [htmlInput, cssInput, select, runButton].forEach(function (c) { if (c) c.disabled = true; });
            L.showError(root, 'お使いのブラウザでは、安全なプレビューを表示できません。新しいブラウザでお試しください。');
            return;
        }

        // style="…" attributes get the same CSS rules as the CSS editor (this page loads DOMPurify only for this tool).
        // src may only be a small inline bitmap (DOMPurify alone lets any data: URL through on <img>).
        purify.addHook('uponSanitizeAttribute', function (node, data) {
            if (!data) return;
            if (data.attrName === 'style') {
                var styleCss = S.sanitizeCss(data.attrValue);
                data.attrValue = styleCss.css;
                if (styleCss.removed.indexOf('too_deep') !== -1) deepStyle = true;     // review F1: dropped; build() says why
            }
            else if (data.attrName === 'src' && !S.isImageUri(data.attrValue)) data.keepAttr = false;
        });

        var lesson = lessons[0];
        var timer = null;
        var deepStyle = false;       // set by the hook above when a style="" was too deeply nested to keep
        var current = null;          // last successful build: { html (sanitised), css, doc (inert), parsed }
        var preview = frame('プレビュー');
        var beforeFrame = null;
        var afterFrame = null;
        stage.appendChild(preview);

        /* ------------------------------------------------------------ build */

        /**
         * Sanitise the inputs and build the preview document.
         * @return {{error: ?string, code: ?string, html: string, css: string, srcdoc: string, doc: Document, parsed: object, removed: string[]}}
         */
        function build(html, css) {
            var over = S.overLimit({ html: html, css: css }, limits);
            if (over) {
                return { error: (over === 'html' ? 'HTML' : 'CSS') + ' が大きすぎます（上限 ' + Text.formatBytes(limits[over]) + '）。短くしてお試しください。', code: 'too_large' };
            }
            // Review N2: very deep nesting can stop the browser's own CSS parser. Refuse it before anything is drawn and say
            // why; the last good preview stays. (sanitizeCss() also drops such CSS, for style="" and the saved file.)
            if (S.nestingDepth(css) > S.LIMITS.depth) {
                return { error: 'CSS のかっこ（「(」「[」「{」）の入れ子が深すぎます（上限 ' + S.LIMITS.depth + ' 段）。入れ子を減らしてお試しください。', code: 'too_deep' };
            }
            deepStyle = false;
            var safe = String(purify.sanitize(html, S.purifyConfig()));
            // DOMPurify always lists the wrapper elements of its own parse (body); those are not the learner's.
            var removedHtml = (purify.removed || []).some(function (r) { return !(r && r.element && /^(?:BODY|HTML|HEAD)$/.test(r.element.nodeName)); });
            var doc = new window.DOMParser().parseFromString('<!doctype html><html><body>' + safe + '</body></html>', 'text/html');
            if (doc.body.getElementsByTagName('*').length > (limits.nodes || S.LIMITS.nodes)) {
                return { error: '要素が多すぎます（上限 ' + (limits.nodes || S.LIMITS.nodes) + '個）。HTML を短くしてお試しください。', code: 'too_large' };
            }
            var cleaned = S.sanitizeCss(css);
            // Review F1: sanitizeCss() checks the depth again on what it would hand to the browser (taking text out can
            // make CSS deeper). Refuse that too, with the same reason, instead of showing a preview without the CSS.
            if (cleaned.removed.indexOf('too_deep') !== -1) {
                return { error: 'CSS のかっこ（「(」「[」「{」）の入れ子が深すぎます（上限 ' + S.LIMITS.depth + ' 段）。入れ子を減らしてお試しください。', code: 'too_deep' };
            }
            var removed = cleaned.removed.map(function (code) { return REMOVED[code] || code; });
            if (removedHtml) removed.unshift('使えないタグ・属性・URL');
            if (deepStyle) removed.push(REMOVED.too_deep);
            return {
                error: null, code: null, html: safe, css: cleaned.css, removed: removed, doc: doc,
                parsed: S.parseCss(cleaned.css),
                srcdoc: S.buildDocument({ html: safe, css: cleaned.css })
            };
        }

        function showSize() {
            sizeNote.textContent = 'HTML ' + Text.formatBytes(S.byteLength(htmlInput.value)) + '・CSS ' + Text.formatBytes(S.byteLength(cssInput.value)) + '（それぞれ ' + Text.formatBytes(limits.html || S.LIMITS.html) + ' まで）';
        }

        function render() {
            timer = null;
            showSize();
            var built = build(htmlInput.value, cssInput.value);
            if (built.error) {
                // Keep the last good preview; say why it did not update.
                L.showError(root, built.error);
                return false;
            }
            L.clearError(root);
            current = built;
            preview.srcdoc = built.srcdoc;
            if (built.removed.length) {
                notes.textContent = '安全のため、次のものを取り除いて表示しています：' + built.removed.join('、') + '。';
                notes.hidden = false;
            } else {
                notes.hidden = true;
                notes.textContent = '';
            }
            renderGlossary();
            if (compare.open) renderCompare();
            return true;
        }

        function schedule() {
            if (timer) window.clearTimeout(timer);
            timer = window.setTimeout(render, DEBOUNCE_MS);
        }

        /* --------------------------------------------------------- glossary */

        function renderGlossary() {
            glossaryList.textContent = '';
            var count = 0;
            function add(term, entry) {
                var dt = el('dt');
                dt.appendChild(el('code', '', term));
                dt.appendChild(document.createTextNode(' ' + entry.label));
                glossaryList.appendChild(dt);
                glossaryList.appendChild(el('dd', '', entry.text));
                count++;
            }
            S.htmlTags(htmlInput.value).forEach(function (tag) {
                if (Object.prototype.hasOwnProperty.call(glossary.tags, tag)) add('<' + tag + '>', glossary.tags[tag]);
            });
            S.cssProperties(S.parseCss(cssInput.value)).forEach(function (prop) {
                if (Object.prototype.hasOwnProperty.call(glossary.properties, prop)) add(prop, glossary.properties[prop]);
            });
            if (!count) glossaryList.appendChild(el('dd', '', 'コードを書くと、使っているタグとプロパティの説明がここに並びます。'));
        }

        /* ---------------------------------------------------------- compare */

        function diffTable(label, before, after) {
            var wrap = el('div', 'labs-studio__difffile');
            wrap.appendChild(el('h5', '', label));
            var res = D.lines(before, after, { oldName: '最初', newName: '今' });
            if (res.stats.same) {
                wrap.appendChild(el('p', 'labs-hint', '変更はありません。'));
                return wrap;
            }
            wrap.appendChild(el('p', 'labs-hint', '追加 ' + res.stats.added + '行・削除 ' + res.stats.removed + '行'));
            var box = el('div', 'labs-diff');
            var table = el('table', 'labs-diff__table');
            var thead = el('thead', 'labs-sr-only');
            var hr = el('tr');
            ['種類', '内容'].forEach(function (t) { var th = el('th', '', t); th.scope = 'col'; hr.appendChild(th); });
            thead.appendChild(hr);
            table.appendChild(thead);
            var body = el('tbody');
            res.rows.slice(0, MAX_DIFF_ROWS).forEach(function (row) {
                var tr = el('tr', 'labs-diff__row is-' + row.type);
                var mark = el('td', 'labs-diff__mark is-' + row.type);
                mark.appendChild(el('span', '', MARK[row.type])).setAttribute('aria-hidden', 'true');
                if (MARK_LABEL[row.type]) mark.appendChild(el('span', 'labs-sr-only', MARK_LABEL[row.type]));
                tr.appendChild(mark);
                tr.appendChild(el('td', 'labs-diff__code', row.text === '' ? ' ' : row.text));
                body.appendChild(tr);
            });
            table.appendChild(body);
            box.appendChild(table);
            wrap.appendChild(box);
            return wrap;
        }

        function renderCompare() {
            var beforeBox = L.$('[data-studio-before]', root);
            var afterBox = L.$('[data-studio-after]', root);
            var diffBox = L.$('[data-studio-diff]', root);
            if (!beforeFrame) {
                beforeFrame = frame('最初の表示');
                afterFrame = frame('今の表示');
                beforeBox.appendChild(beforeFrame);
                afterBox.appendChild(afterFrame);
            }
            var first = build(lesson.files.html, lesson.files.css);
            if (!first.error) beforeFrame.srcdoc = first.srcdoc;
            if (current) afterFrame.srcdoc = current.srcdoc;
            diffBox.textContent = '';
            diffBox.appendChild(diffTable('HTML', lesson.files.html, htmlInput.value));
            diffBox.appendChild(diffTable('CSS', lesson.files.css, cssInput.value));
        }

        /* -------------------------------------------------------- exercises */

        function renderLesson() {
            L.$('[data-studio-title]', root).textContent = lesson.title;
            L.$('[data-studio-summary]', root).textContent = lesson.summary;
            var steps = L.$('[data-studio-steps]', root);
            steps.textContent = '';
            lesson.steps.forEach(function (s) { steps.appendChild(el('li', '', s)); });
            tasks.textContent = '';
            lesson.exercises.forEach(function (ex) {
                var li = el('li', 'labs-studio__task');
                li.setAttribute('data-task', ex.id);
                li.setAttribute('data-state', 'todo');
                li.appendChild(el('span', 'labs-studio__state', '未'));
                var body = el('div', 'labs-studio__taskbody');
                body.appendChild(el('p', 'labs-studio__prompt', ex.prompt));
                if (ex.hint) {
                    var d = el('details', 'labs-studio__hint');
                    d.appendChild(el('summary', '', 'ヒントを見る'));
                    d.appendChild(el('p', '', ex.hint));
                    body.appendChild(d);
                }
                li.appendChild(body);
                tasks.appendChild(li);
            });
            progress.textContent = '';
            var learn = L.$('[data-studio-learn]', root);
            var link = L.$('[data-studio-learn-link]', root);
            // The URL comes from the server (a published Learn page of this site), never from the query string.
            if (lesson.learn && lesson.learn.url) { link.href = lesson.learn.url; learn.hidden = false; }
            else { link.removeAttribute('href'); learn.hidden = true; }
        }

        function check() {
            if (timer) window.clearTimeout(timer);
            var started = Date.now();
            L.trackRun('validate', S.byteLength(htmlInput.value) + S.byteLength(cssInput.value));
            if (!render() || !current) {
                L.track('labs_tool_error', { action: 'validate', error_code: 'too_large' });
                return;
            }
            var done = 0;
            lesson.exercises.forEach(function (ex) {
                var ok = S.runChecks(current.doc, current.parsed, ex.checks).every(Boolean);
                var li = L.$('[data-task="' + ex.id + '"]', tasks);
                if (!li) return;
                li.setAttribute('data-state', ok ? 'done' : 'todo');
                L.$('.labs-studio__state', li).textContent = ok ? '済' : '未';
                if (ok) done++;
            });
            var total = lesson.exercises.length;
            var message = done === total ? '課題を' + total + '件すべて達成しました。' : total + '件のうち' + done + '件を達成しました。ヒントを見ながら、残りに挑戦しましょう。';
            progress.textContent = message;
            L.announce(root, message);
            L.track('labs_tool_success', { action: 'validate', duration_bucket: Text.durationBucket(Date.now() - started) });
        }

        /* ----------------------------------------------------------- lesson */

        function dirty() { return htmlInput.value !== lesson.files.html || cssInput.value !== lesson.files.css; }

        function load(next) {
            lesson = next;
            select.value = lesson.slug;
            htmlInput.value = lesson.files.html;
            cssInput.value = lesson.files.css;
            renderLesson();
            render();
        }

        function bySlug(slug) {
            for (var i = 0; i < lessons.length; i++) if (lessons[i].slug === slug) return lessons[i];
            return null;
        }

        function exported() {
            var built = build(htmlInput.value, cssInput.value);
            if (built.error) { L.showError(root, built.error); return null; }
            return S.buildExport({ html: built.html, css: built.css }, lesson.title);
        }

        /* ----------------------------------------------------------- events */

        htmlInput.addEventListener('input', schedule);
        cssInput.addEventListener('input', schedule);
        runButton.addEventListener('click', check);
        L.bindRunShortcut(root, check);

        select.addEventListener('change', function () {
            var next = bySlug(select.value);
            if (!next) { select.value = lesson.slug; return; }
            if (dirty() && !window.confirm('書いたコードは消えます。教材を切り替えますか？')) { select.value = lesson.slug; return; }
            load(next);
            L.announce(root, '教材「' + next.title + '」を開きました。');
        });

        L.$all('input[name="labs-studio-width"]', root).forEach(function (radio) {
            radio.addEventListener('change', function () { if (radio.checked) stage.setAttribute('data-width', radio.value); });
        });

        compare.addEventListener('toggle', function () { if (compare.open) renderCompare(); });

        L.$('[data-labs-reset]', root).addEventListener('click', function () {
            if (dirty() && !window.confirm('書いたコードを消して、教材の最初の状態に戻しますか？')) return;
            load(lesson);
            L.announce(root, '教材の最初の状態に戻しました。');
            L.track('labs_tool_run', { action: 'reset', size_bucket: 'xs' });
            htmlInput.focus();
        });

        L.$('[data-labs-copy]', root).addEventListener('click', function () {
            var text = exported();
            if (text) L.copy(root, text, 'output');
        });

        L.$('[data-labs-download]', root).addEventListener('click', function () {
            var text = exported();
            if (!text) return;
            L.download(text, 'code-studio-' + lesson.slug + '.html', 'text/html');
            L.announce(root, 'HTML ファイルとして保存しました。');
            L.trackRun('download', S.byteLength(text));
        });

        // ?lesson=<slug>: only a slug that exists in the lesson data is used; the value is never written to the page.
        var wanted = null;
        try { wanted = new window.URLSearchParams(window.location.search).get('lesson'); } catch (e) { wanted = null; }
        load((wanted && bySlug(wanted)) || lessons[0]);
    });
}(window, document));
