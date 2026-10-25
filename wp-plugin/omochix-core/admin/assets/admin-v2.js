/**
 * AI Tools v2: repeatable "updates" / "changelog" rows, and a client-side
 * filter for the relation picker <select multiple> boxes.
 *
 * No build step, no dependency: plain DOM APIs only, scoped to the ai_tool
 * edit screen (see omochix_core_admin_assets()).
 */
(function () {
	'use strict';

	/**
	 * Repeatable rows for "product_updates" / "changelog".
	 *
	 * Each repeater tracks its own monotonically increasing index in a data
	 * attribute so a removed-then-re-added row never reuses an index still
	 * present in the DOM (which would silently overwrite that row's POST
	 * data with the new row's values).
	 */
	document.querySelectorAll('[data-repeater]').forEach(function (repeater) {
		var rows = repeater.querySelector('[data-repeater-rows]');
		var template = repeater.querySelector('[data-repeater-template]');
		var addButton = repeater.querySelector('[data-repeater-add]');
		if (!rows || !template || !addButton) return;

		var existingRows = rows.querySelectorAll('[data-repeater-row]');
		var nextIndex = existingRows.length;

		function bindRemove(row) {
			var removeButton = row.querySelector('[data-repeater-remove]');
			if (!removeButton) return;
			removeButton.addEventListener('click', function () {
				row.remove();
			});
		}

		existingRows.forEach(bindRemove);

		addButton.addEventListener('click', function () {
			var html = template.innerHTML.split('__INDEX__').join(String(nextIndex));
			nextIndex += 1;

			var wrapper = document.createElement('div');
			wrapper.innerHTML = html.trim();
			var newRow = wrapper.firstElementChild;
			if (!newRow) return;

			rows.appendChild(newRow);
			bindRemove(newRow);
		});
	});

	/**
	 * Relation pickers: substring filter (display only, never changes what is
	 * selected) plus a live "currently selected" chip list and count. The
	 * chips exist because the filter hides non-matching <option> elements,
	 * which previously made an already-selected item invisible while
	 * searching — easy to forget it was still selected, or to lose track of
	 * how many items were chosen.
	 */
	document.querySelectorAll('[data-relation-picker]').forEach(function (picker) {
		var input = picker.querySelector('[data-relation-filter]');
		var select = picker.querySelector('select');
		var chips = picker.querySelector('[data-relation-chips]');
		var count = picker.querySelector('[data-relation-count]');
		if (!select) return;

		function renderChips() {
			var selected = Array.prototype.filter.call(select.options, function (option) {
				return option.selected;
			});

			if (count) {
				count.textContent = selected.length + '件選択中';
			}
			if (!chips) return;

			chips.innerHTML = '';
			chips.hidden = selected.length === 0;

			selected.forEach(function (option) {
				var chip = document.createElement('span');
				chip.className = 'omochix-relation-chip';

				var label = document.createElement('span');
				label.textContent = option.text;
				chip.appendChild(label);

				var removeButton = document.createElement('button');
				removeButton.type = 'button';
				removeButton.className = 'omochix-relation-chip__remove';
				removeButton.setAttribute('aria-label', option.text + ' を選択解除');
				removeButton.textContent = '×';
				removeButton.addEventListener('click', function () {
					option.selected = false;
					renderChips();
				});
				chip.appendChild(removeButton);

				chips.appendChild(chip);
			});
		}

		select.addEventListener('change', renderChips);
		renderChips();

		if (input) {
			input.addEventListener('input', function () {
				var query = input.value.trim().toLowerCase();
				Array.prototype.forEach.call(select.options, function (option) {
					option.hidden = query !== '' && option.text.toLowerCase().indexOf(query) === -1;
				});
			});
		}
	});
}());

/**
 * Practice list: populate Quick Edit (difficulty / minutes / verified date)
 * from the hidden .omochix-practice-quick-data span in the row.
 */
(function () {
	'use strict';
	if (!window.inlineEditPost || !window.inlineEditPost.edit) return;
	var originalEdit = window.inlineEditPost.edit;
	window.inlineEditPost.edit = function (id) {
		originalEdit.apply(this, arguments);
		var postId = typeof id === 'object' ? window.inlineEditPost.getId(id) : parseInt(id, 10);
		if (!postId) return;
		var rowData = document.querySelector('#post-' + postId + ' .omochix-practice-quick-data');
		var editRow = document.querySelector('#edit-' + postId);
		if (!rowData || !editRow) return;
		var setValue = function (name, value) {
			var field = editRow.querySelector('[name="omochix_practice_quick[' + name + ']"]');
			if (field) field.value = value;
		};
		setValue('practice_difficulty', rowData.dataset.level || 'beginner');
		setValue('practice_minutes', rowData.dataset.minutes && rowData.dataset.minutes !== '0' ? rowData.dataset.minutes : '');
		setValue('practice_verified_at', rowData.dataset.verified || '');
	};
}());

/**
 * Practice JSON fields (Phase 6A-S5): 整形 / 検証 buttons and a live status
 * for the rows textareas. The server still keeps the stored value when a
 * field cannot be parsed; this only tells the editor before saving.
 */
(function () {
	'use strict';
	var strings = (window.omochixCorePractice && window.omochixCorePractice.strings) || {};
	var T = function (key, fallback) { return strings[key] || fallback; };

	function parseRows(text) {
		var trimmed = text.trim();
		if (trimmed === '') return { ok: true, empty: true, value: [] };
		try {
			var value = JSON.parse(trimmed);
			if (!Array.isArray(value)) return { ok: false, message: T('notArray', 'JSON の配列（[ … ]）ではありません') };
			for (var i = 0; i < value.length; i++) {
				if (!value[i] || typeof value[i] !== 'object' || Array.isArray(value[i])) {
					return { ok: false, message: T('rowNotObject', '行 ' + (i + 1) + ' がオブジェクト（{ … }）ではありません').replace('%d', String(i + 1)) };
				}
			}
			return { ok: true, value: value, count: value.length };
		} catch (error) {
			return { ok: false, message: String(error.message || error) };
		}
	}

	function report(tools, textarea, result) {
		var status = tools.querySelector('[data-json-status]');
		textarea.classList.toggle('is-invalid', !result.ok);
		if (!status) return;
		status.className = 'omochix-json-status ' + (result.ok ? 'omochix-json-status--ok' : 'omochix-json-status--error');
		if (!result.ok) {
			status.textContent = T('invalid', '読めません：') + result.message;
		} else if (result.empty) {
			status.textContent = T('empty', '空（保存すると項目を消します）');
		} else {
			status.textContent = T('valid', '%d 件の行').replace('%d', String(result.count));
		}
	}

	document.querySelectorAll('[data-json-tools]').forEach(function (tools) {
		var textarea = document.getElementById(tools.getAttribute('data-json-tools'));
		if (!textarea) return;
		var validate = function () { report(tools, textarea, parseRows(textarea.value)); };
		var formatBtn = tools.querySelector('[data-json-format]');
		var validateBtn = tools.querySelector('[data-json-validate]');
		if (validateBtn) validateBtn.addEventListener('click', validate);
		if (formatBtn) formatBtn.addEventListener('click', function () {
			var result = parseRows(textarea.value);
			if (result.ok && !result.empty) textarea.value = JSON.stringify(result.value, null, 2);
			report(tools, textarea, result);
		});
		textarea.addEventListener('change', validate);
	});

	// Before saving, point at the first unparsable field (the server keeps its stored value).
	var form = document.getElementById('post');
	if (form) form.addEventListener('submit', function () {
		var first = null;
		document.querySelectorAll('[data-json-tools]').forEach(function (tools) {
			var textarea = document.getElementById(tools.getAttribute('data-json-tools'));
			if (!textarea) return;
			var result = parseRows(textarea.value);
			report(tools, textarea, result);
			if (!result.ok && !first) first = textarea;
		});
		if (first) first.scrollIntoView({ block: 'center' });
	});
}());
