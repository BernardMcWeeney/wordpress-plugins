(function () {
	'use strict';

	function getPreviewUrl(attachment) {
		var sizes = attachment && attachment.sizes ? attachment.sizes : {};

		if (sizes.large && sizes.large.url) {
			return sizes.large.url;
		}

		if (sizes.medium && sizes.medium.url) {
			return sizes.medium.url;
		}

		return attachment && attachment.url ? attachment.url : '';
	}

	function setFieldPreview(field, url) {
		var image = field.querySelector('[data-greenberry-media-preview]');
		var placeholder = field.querySelector('[data-greenberry-media-placeholder]');
		var clearButton = field.querySelector('[data-greenberry-media-clear]');
		var previewBox = field.querySelector('.greenberry-media-field__preview');

		if (image) {
			image.src = url || '';
			image.hidden = !url;
		}

		if (placeholder) {
			placeholder.hidden = !!url;
		}

		if (clearButton) {
			clearButton.disabled = !url;
		}

		if (previewBox) {
			previewBox.classList.toggle('is-empty', !url);
		}
	}

	function setLoginPreview(field, url) {
		var role = field.getAttribute('data-greenberry-media-role');
		var preview = document.querySelector('[data-greenberry-admin-login-preview]');

		if (!preview) {
			return;
		}

		if ('background' === role) {
			preview.style.backgroundImage = url ? 'url("' + url + '")' : '';
			return;
		}

		if ('logo' === role) {
			var logo = document.querySelector('[data-greenberry-admin-login-preview-logo]');
			var logoText = document.querySelector('[data-greenberry-admin-login-preview-logo-text]');
			var defaultLogo = logo ? logo.getAttribute('data-default-src') : '';
			var nextUrl = url || defaultLogo || '';

			if (logo) {
				logo.src = nextUrl;
				logo.hidden = !nextUrl;
			}

			if (logoText) {
				logoText.hidden = !!nextUrl;
			}
		}
	}

	function bindMediaField(field) {
		var input = field.querySelector('[data-greenberry-media-id]');
		var chooseButton = field.querySelector('[data-greenberry-media-choose]');
		var clearButton = field.querySelector('[data-greenberry-media-clear]');
		var strings = window.greenberryAdminLogin || {};
		var frame;

		if (!input || !chooseButton || !window.wp || !wp.media) {
			return;
		}

		chooseButton.addEventListener('click', function (event) {
			event.preventDefault();

			if (!frame) {
				frame = wp.media({
					title: strings.chooseTitle || 'Choose an image',
					button: {
						text: strings.chooseText || 'Use this image'
					},
					multiple: false,
					library: {
						type: 'image'
					}
				});

				frame.on('select', function () {
					var attachment = frame.state().get('selection').first().toJSON();
					var url = getPreviewUrl(attachment);

					input.value = attachment.id || '';
					setFieldPreview(field, url);
					setLoginPreview(field, url);
				});
			}

			frame.open();
		});

		if (clearButton) {
			clearButton.addEventListener('click', function (event) {
				event.preventDefault();

				input.value = '';
				setFieldPreview(field, '');
				setLoginPreview(field, '');
			});
		}
	}

	function bindMessagePreview() {
		var input = document.querySelector('[data-greenberry-admin-login-message-input]');
		var preview = document.querySelector('[data-greenberry-admin-login-preview-message]');

		if (!input || !preview) {
			return;
		}

		input.addEventListener('input', function () {
			var siteName = input.getAttribute('data-site-name') || '';
			var message = input.value.replace(/\{site_name\}/g, siteName).trim();

			preview.textContent = message;
			preview.hidden = !message;
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		var fields = document.querySelectorAll('.greenberry-media-field');

		Array.prototype.forEach.call(fields, bindMediaField);
		bindMessagePreview();
	});
}());
