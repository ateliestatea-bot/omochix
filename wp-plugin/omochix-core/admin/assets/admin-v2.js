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
	 * Live substring filter for relation picker <select multiple> options.
	 * Purely a display filter; it never changes which options are selected.
	 */
	document.querySelectorAll('[data-relation-picker]').forEach(function (picker) {
		var input = picker.querySelector('[data-relation-filter]');
		var select = picker.querySelector('select');
		if (!input || !select) return;

		input.addEventListener('input', function () {
			var query = input.value.trim().toLowerCase();
			Array.prototype.forEach.call(select.options, function (option) {
				option.hidden = query !== '' && option.text.toLowerCase().indexOf(query) === -1;
			});
		});
	});
}());
