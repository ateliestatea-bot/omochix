/**
 * Native WordPress Media Library integration for the tool logo field.
 */
(function () {
	'use strict';

	var selectButton = document.querySelector('[data-select-logo]');
	var removeButton = document.querySelector('[data-remove-logo]');
	var logoInput = document.querySelector('#omochix-tool-logo');
	var preview = document.querySelector('[data-logo-preview]');
	var labels = window.omochixCoreAdmin || {};
	var mediaFrame;

	if (!selectButton || !removeButton || !logoInput || !preview || !window.wp || !wp.media) return;

	selectButton.addEventListener('click', function () {
		if (!mediaFrame) {
			mediaFrame = wp.media({
				title: labels.mediaTitle || 'Select tool logo',
				button: { text: labels.mediaButton || 'Use as tool logo' },
				library: { type: 'image' },
				multiple: false
			});

			mediaFrame.on('select', function () {
				var attachment = mediaFrame.state().get('selection').first().toJSON();
				var imageUrl = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
				logoInput.value = attachment.id;
				preview.innerHTML = '';
				var image = document.createElement('img');
				image.src = imageUrl;
				image.alt = '';
				image.width = 96;
				image.height = 96;
				preview.appendChild(image);
				removeButton.disabled = false;
			});
		}

		mediaFrame.open();
	});

	removeButton.addEventListener('click', function () {
		logoInput.value = '';
		preview.innerHTML = '';
		removeButton.disabled = true;
	});
}());

/**
 * Populate WordPress Quick Edit with the values embedded in the current row,
 * and refuse to send an invalid rating or display order.
 */
(function () {
	'use strict';

	/*
	 * Quick Edit rating / display order check. A number input that holds text
	 * such as "abc" or "1e" reports value "" (validity.badInput), so without
	 * this it would be sent as a blank rating and delete the stored one.
	 * A truly blank rating still means "remove the rating".
	 */
	var isValidQuickValue = function (name, value, badInput) {
		if (badInput) return false;
		value = String(value).trim();
		if (name === 'rating_overall') {
			if (value === '') return true;
			var rating = Number(value);
			return isFinite(rating) && rating >= 1 && rating <= 5;
		}
		if (name === 'display_order') return /^\d+$/.test(value);
		return true;
	};
	window.omochixQuickEditValueIsValid = isValidQuickValue;

	if (!window.inlineEditPost || !window.inlineEditPost.edit) return;

	var clearQuickErrors = function (editRow) {
		Array.prototype.forEach.call(editRow.querySelectorAll('.omochix-quick-error'), function (node) {
			node.parentNode.removeChild(node);
		});
		Array.prototype.forEach.call(editRow.querySelectorAll('[aria-invalid="true"]'), function (field) {
			field.removeAttribute('aria-invalid');
		});
	};

	// Both the Update button and Enter call inlineEditPost.save(); returning
	// false here stops the AJAX request so nothing is changed.
	var originalSave = window.inlineEditPost.save;
	window.inlineEditPost.save = function (id) {
		var postId = typeof id === 'object' ? window.inlineEditPost.getId(id) : parseInt(id, 10);
		var editRow = postId ? document.querySelector('#edit-' + postId) : null;
		if (editRow) {
			clearQuickErrors(editRow);
			var firstInvalid = null;
			['rating_overall', 'display_order'].forEach(function (name) {
				var field = editRow.querySelector('[name="omochix_quick_meta[' + name + ']"]');
				if (!field || isValidQuickValue(name, field.value, field.validity && field.validity.badInput)) return;
				var message = document.createElement('p');
				message.className = 'notice notice-error inline omochix-quick-error';
				message.setAttribute('role', 'alert');
				message.textContent = field.getAttribute('data-omochix-error') || 'Invalid value.';
				field.setAttribute('aria-invalid', 'true');
				field.closest('label').insertAdjacentElement('afterend', message);
				if (!firstInvalid) firstInvalid = field;
			});
			if (firstInvalid) {
				firstInvalid.focus();
				return false;
			}
			// Clearing a rating the tool had when Quick Edit opened deletes it: ask first.
			var ratingField = editRow.querySelector('[name="omochix_quick_meta[rating_overall]"]');
			if (ratingField && String(ratingField.value).trim() === '' && ratingField.getAttribute('data-omochix-initial') &&
				!window.confirm(ratingField.getAttribute('data-omochix-confirm') || 'Remove the current rating?')) {
				return false;
			}
		}
		return originalSave.apply(this, arguments);
	};

	var originalEdit = window.inlineEditPost.edit;
	window.inlineEditPost.edit = function (id) {
		originalEdit.apply(this, arguments);

		var postId = typeof id === 'object' ? window.inlineEditPost.getId(id) : parseInt(id, 10);
		if (!postId) return;

		var rowData = document.querySelector('#post-' + postId + ' .omochix-quick-data');
		var editRow = document.querySelector('#edit-' + postId);
		if (!rowData || !editRow) return;
		clearQuickErrors(editRow);

		var setValue = function (name, value) {
			var field = editRow.querySelector('[name="omochix_quick_meta[' + name + ']"]');
			if (field) field.value = value;
		};

		setValue('pricing_type', rowData.dataset.pricing || 'contact');
		setValue('japanese_support', rowData.dataset.japanese || 'unknown');
		setValue('tool_status', rowData.dataset.status || 'active');
		setValue('rating_overall', rowData.dataset.rating || '');
		setValue('display_order', rowData.dataset.order || '0');
		var ratingField = editRow.querySelector('[name="omochix_quick_meta[rating_overall]"]');
		if (ratingField) ratingField.setAttribute('data-omochix-initial', rowData.dataset.rating || '');

		var featured = editRow.querySelector('[name="omochix_quick_meta[is_featured]"][type="checkbox"]');
		if (featured) featured.checked = rowData.dataset.featured === '1';
	};
}());
