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
 * Populate WordPress Quick Edit with the values embedded in the current row.
 */
(function () {
	'use strict';

	if (!window.inlineEditPost || !window.inlineEditPost.edit) return;

	var originalEdit = window.inlineEditPost.edit;
	window.inlineEditPost.edit = function (id) {
		originalEdit.apply(this, arguments);

		var postId = typeof id === 'object' ? window.inlineEditPost.getId(id) : parseInt(id, 10);
		if (!postId) return;

		var rowData = document.querySelector('#post-' + postId + ' .omochix-quick-data');
		var editRow = document.querySelector('#edit-' + postId);
		if (!rowData || !editRow) return;

		var setValue = function (name, value) {
			var field = editRow.querySelector('[name="omochix_quick_meta[' + name + ']"]');
			if (field) field.value = value;
		};

		setValue('pricing_type', rowData.dataset.pricing || 'contact');
		setValue('japanese_support', rowData.dataset.japanese || 'unknown');
		setValue('tool_status', rowData.dataset.status || 'active');
		setValue('rating_overall', rowData.dataset.rating || '');
		setValue('display_order', rowData.dataset.order || '0');

		var featured = editRow.querySelector('[name="omochix_quick_meta[is_featured]"][type="checkbox"]');
		if (featured) featured.checked = rowData.dataset.featured === '1';
	};
}());
