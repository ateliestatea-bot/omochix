/**
 * OmochiX Labs: robots.txt generator UI (core logic in core/robots.js).
 * The output updates as the form changes; the tester parses any robots.txt.
 */
(function (window, document) {
    'use strict';

    var L = window.OmochixLabs;
    var Text = window.OmochixLabsText;
    var Robots = window.OmochixLabsRobots;
    if (!L) return;

    var PRESETS = {
        allow: [],
        wordpress: [{ type: 'disallow', path: '/wp-admin/' }, { type: 'allow', path: '/wp-admin/admin-ajax.php' }],
        block: [{ type: 'disallow', path: '/' }]
    };

    L.tool('robots-txt-generator', function (root) {
        var limits = L.limits();
        var rulesList = L.$('[data-labs-rules]', root);
        var addBtn = L.$('[data-labs-add-rule]', root);
        var sitemaps = L.$('#labs-robots-sitemaps', root);
        var output = L.$('#labs-robots-output', root);
        var warnBox = L.$('[data-labs-warnings]', root);
        var crawlerSelects = L.$all('[data-labs-crawler]', root);
        var useGenerated = L.$('#labs-tester-use-generated', root);
        var customWrap = L.$('[data-labs-tester-custom]', root);
        var customRobots = L.$('#labs-tester-robots', root);
        var agentInput = L.$('#labs-tester-agent', root);
        var pathInput = L.$('#labs-tester-path', root);
        var verdict = L.$('[data-labs-verdict]', root);
        var ruleSeq = 0;
        var timer = null;

        function preset() {
            var checked = L.$('input[name="labs-robots-preset"]:checked', root);
            return checked ? checked.value : 'allow';
        }

        function renumber() {
            L.$all('li', rulesList).forEach(function (li, i) {
                var n = i + 1;
                L.$('[data-rule-type-label]', li).textContent = 'ルール' + n + 'の種類';
                L.$('[data-rule-path-label]', li).textContent = 'ルール' + n + 'のパス';
                L.$('[data-rule-remove]', li).setAttribute('aria-label', 'ルール' + n + 'を削除');
            });
            addBtn.disabled = L.$all('li', rulesList).length >= limits.rules;
        }

        function addRule(type, path, focus) {
            if (L.$all('li', rulesList).length >= limits.rules) return;
            var id = 'labs-rule-' + (++ruleSeq);
            var li = document.createElement('li');
            li.className = 'labs-rule';

            var typeLabel = document.createElement('label');
            typeLabel.className = 'labs-sr-only';
            typeLabel.htmlFor = id + '-type';
            typeLabel.setAttribute('data-rule-type-label', '');
            var select = document.createElement('select');
            select.className = 'labs-select';
            select.id = id + '-type';
            [['disallow', 'Disallow（拒否）'], ['allow', 'Allow（許可）']].forEach(function (opt) {
                var o = document.createElement('option');
                o.value = opt[0];
                o.textContent = opt[1];
                select.appendChild(o);
            });
            select.value = type === 'allow' ? 'allow' : 'disallow';

            var pathLabel = document.createElement('label');
            pathLabel.className = 'labs-sr-only';
            pathLabel.htmlFor = id + '-path';
            pathLabel.setAttribute('data-rule-path-label', '');
            var input = document.createElement('input');
            input.className = 'labs-input labs-code';
            input.id = id + '-path';
            input.maxLength = limits.path;
            input.placeholder = '/private/';
            input.autocomplete = 'off';
            input.spellcheck = false;
            input.setAttribute('autocapitalize', 'off');
            input.value = path || '';

            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'labs-button labs-button--small labs-button--ghost';
            remove.textContent = '削除';
            remove.setAttribute('data-rule-remove', '');
            remove.addEventListener('click', function () {
                var next = li.nextElementSibling || li.previousElementSibling;
                rulesList.removeChild(li);
                renumber();
                update();
                var target = next ? L.$('input', next) : addBtn;
                target.focus();
            });

            li.appendChild(typeLabel);
            li.appendChild(select);
            li.appendChild(pathLabel);
            li.appendChild(input);
            li.appendChild(remove);
            rulesList.appendChild(li);
            renumber();
            if (focus) input.focus();
        }

        function customRules() {
            return L.$all('li', rulesList).map(function (li) {
                return { type: L.$('select', li).value, path: L.$('input', li).value.trim() };
            }).filter(function (r) { return r.path !== ''; });
        }

        function config() {
            var star = PRESETS[preset()].concat(customRules());
            var groups = [{ agents: ['*'], rules: star }];
            var allow = [];
            var deny = [];
            crawlerSelects.forEach(function (sel) {
                if (sel.value === 'allow') allow.push(sel.getAttribute('data-labs-crawler'));
                if (sel.value === 'disallow') deny.push(sel.getAttribute('data-labs-crawler'));
            });
            // A crawler with its own group ignores the "*" group, so "allow" repeats the "*" rules.
            if (allow.length) groups.push({ agents: allow, rules: star.filter(function (r) { return !(r.type === 'disallow' && r.path === '/'); }), comment: 'AIクローラー（許可）' });
            if (deny.length) groups.push({ agents: deny, rules: [{ type: 'disallow', path: '/' }], comment: 'AIクローラー（拒否）' });
            return { groups: groups, sitemaps: sitemaps.value.split(/\r?\n/) };
        }

        function renderWarnings(list) {
            var ul = L.$('ul', warnBox);
            ul.textContent = '';
            list.forEach(function (w) {
                var li = document.createElement('li');
                li.textContent = w.message;
                ul.appendChild(li);
            });
            warnBox.hidden = !list.length;
        }

        function update(fromUser) {
            var built = Robots.build(config());
            output.value = built.text;
            renderWarnings(built.warnings);
            if (fromUser) {
                L.trackRun('generate', Text.byteLength(built.text), true);
            }
            if (useGenerated.checked && !verdict.hidden) test(false);
        }

        function scheduleUpdate() {
            window.clearTimeout(timer);
            timer = window.setTimeout(function () { update(true); }, 150);
        }

        function setVerdict(allowed, message) {
            verdict.textContent = '';
            var strong = document.createElement('strong');
            strong.textContent = allowed ? '✓ 許可' : '✕ 拒否';
            verdict.appendChild(strong);
            verdict.appendChild(document.createTextNode(' ' + message));
            verdict.className = 'labs-verdict ' + (allowed ? 'is-allowed' : 'is-blocked');
            verdict.hidden = false;
        }

        function test(fromUser) {
            L.clearError(root);
            var text = useGenerated.checked ? output.value : customRobots.value;
            var over = L.overLimit(text, limits.robots);
            var agent = agentInput.value.trim();
            var path = pathInput.value.trim();
            var error = over
                || (!/^[A-Za-z][A-Za-z0-9_-]{0,59}$/.test(agent) ? 'クローラー名は英数字・ハイフン・アンダースコアで入力してください（例：GPTBot）。' : '')
                || (!path ? '判定するURLかパスを入力してください。' : '')
                || (path.length > limits.path ? 'URLが長すぎます（' + limits.path + '文字まで）。' : '');
            if (error) {
                L.showError(root, error);
                L.announce(root, error);
                if (fromUser) L.track('labs_tool_error', { action: 'test', error_code: over ? 'too_large' : 'invalid_input' });
                return;
            }
            if (fromUser) L.trackRun('test', Text.byteLength(text));
            var res = Robots.test(Robots.parse(text), agent, path);
            var group = res.agents ? '「User-agent: ' + (res.agents.indexOf(agent.toLowerCase()) !== -1 ? agent : '*') + '」のグループ' : '該当するグループなし';
            var because = res.rule ? '（' + res.rule.line + '行目「' + (res.rule.type === 'allow' ? 'Allow' : 'Disallow') + ': ' + res.rule.path + '」が適用されます）' : '（一致するルールはありません）';
            if (res.path === '/robots.txt') because = '（robots.txt 自体は常に読めます）';
            setVerdict(res.allowed, agent + ' は ' + res.path + ' を' + (res.allowed ? '巡回できます。' : '巡回しません。') + group + because);
            L.announce(root, verdict.textContent);
            if (fromUser) L.track('labs_tool_success', { action: 'test', duration_bucket: 'fast' });
        }

        function reset() {
            L.$('#labs-robots-preset-allow', root).checked = true;
            rulesList.textContent = '';
            renumber();
            crawlerSelects.forEach(function (sel) { sel.value = ''; });
            sitemaps.value = '';
            useGenerated.checked = true;
            customWrap.hidden = true;
            customRobots.value = '';
            agentInput.value = 'Googlebot';
            pathInput.value = '/wp-admin/';
            verdict.hidden = true;
            L.clearError(root);
            update(false);
        }

        root.addEventListener('input', function (event) {
            if (event.target === agentInput || event.target === pathInput || event.target === customRobots) return;
            scheduleUpdate();
        });
        root.addEventListener('change', function (event) {
            if (event.target === useGenerated) {
                customWrap.hidden = useGenerated.checked;
                if (!useGenerated.checked && !customRobots.value) customRobots.value = output.value;
                return;
            }
            if (event.target === agentInput || event.target === pathInput) return;
            scheduleUpdate();
        });
        addBtn.addEventListener('click', function () { addRule('disallow', '', true); update(true); });
        L.$all('[data-labs-bulk]', root).forEach(function (button) {
            button.addEventListener('click', function () {
                var mode = button.getAttribute('data-labs-bulk');
                crawlerSelects.forEach(function (sel) {
                    if (mode === 'none') sel.value = '';
                    else if (mode === 'all') sel.value = 'disallow';
                    else if (sel.getAttribute('data-purpose') === 'training') sel.value = 'disallow';
                });
                update(true);
                L.announce(root, button.textContent + 'に設定しました');
            });
        });
        L.$('[data-labs-run]', root).addEventListener('click', function () { test(true); });
        [agentInput, pathInput].forEach(function (input) {
            input.addEventListener('keydown', function (event) { if (event.key === 'Enter') { event.preventDefault(); test(true); } });
        });
        L.$('[data-labs-copy]', root).addEventListener('click', function () { L.copy(root, output.value, 'robots'); });
        L.$('[data-labs-download]', root).addEventListener('click', function () { L.download(output.value, 'robots.txt', 'text/plain'); });
        L.$('[data-labs-sample]', root).addEventListener('click', function () {
            reset();
            L.$('#labs-robots-preset-wordpress', root).checked = true;
            addRule('disallow', '/?s=');
            addRule('disallow', '/search/');
            crawlerSelects.forEach(function (sel) { if (sel.getAttribute('data-purpose') === 'training') sel.value = 'disallow'; });
            sitemaps.value = 'https://example.com/sitemap.xml';
            agentInput.value = 'GPTBot';
            pathInput.value = 'https://example.com/blog/hello/';
            update(true);
            test(false);
            L.announce(root, L.strings.sample);
        });
        L.$('[data-labs-reset]', root).addEventListener('click', function () {
            reset();
            L.announce(root, L.strings.reset);
        });

        update(false);
    });
}(window, document));
