/**
 * TrustScript — Simple Review Form (frontend)
 *
 * @since 1.0.0
 */

(function () {
	'use strict';

	var cfg = window.trustscript || {};
	var REST = (cfg.rest_url || '/wp-json/').replace(/\/$/, '');
	var NONCE = cfg.wp_rest_nonce || '';
	var MAX_PHOTOS = 3;
	var MAX_SIZE = 5 * 1024 * 1024;
	var ALLOWED = ['image/jpeg', 'image/png', 'image/webp'];
	var tStr = window.trustscriptStrings || {};
	var STAR_LABELS = [
		'',
		tStr.star_hover_1 || 'Not what I expected',
		tStr.star_hover_2 || 'Could be better',
		tStr.star_hover_3 || 'It’s okay overall',
		tStr.star_hover_4 || 'I’m really happy with it',
		tStr.star_hover_5 || 'I absolutely love it'
	];

	var selectedRating = 0;
	var photoFiles = [];

	function qs(selector, parent) {
		return (parent || document).querySelector(selector);
	}

	function qsa(selector, parent) {
		return (parent || document).querySelectorAll(selector);
	}

	function showError(id, msg) {
		var el = document.getElementById(id);
		if (el) {
			el.textContent = msg || '';
		}
	}

	function clearErrors() {
		qsa('.trustscript-field-error').forEach(function (e) {
			e.textContent = '';
		});
	}

	function escapeHtml(text) {
		var div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	function showToast(msg, type) {
		var t = document.createElement('div');
		t.className = 'trustscript-toast trustscript-toast-' + type;
		var icon = type === 'success'
			? '<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M3 9l4 4 8-8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
			: '<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="9" r="7" stroke="currentColor" stroke-width="2"/><path d="M9 5v5M9 13h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
		t.innerHTML = icon + msg;
		document.body.appendChild(t);
		setTimeout(function () {
			t.style.transition = 'opacity .3s';
			t.style.opacity = '0';
		}, 3500);
		setTimeout(function () {
			if (t.parentNode) {
				t.parentNode.removeChild(t);
			}
		}, 4000);
	}

	function loadConfigFromElement() {
		var form = qs('.trustscript-review-form[data-config]');
		if (form && form.getAttribute('data-config')) {
			try {
				var parsed = JSON.parse(form.getAttribute('data-config'));
				cfg = Object.assign({}, window.trustscript || {}, parsed);
				REST = (cfg.rest_url || '/wp-json/').replace(/\/$/, '');
				NONCE = cfg.wp_rest_nonce || '';
			} catch (e) {
				// Ignore invalid JSON.
			}
		}
	}

	function init() {
		loadConfigFromElement();

		var isStandalone = cfg.standalone === true;

		if (isStandalone) {
			initMultiProductSelector();
			initStarRating();
			initSteps();
			initPhotoUpload();
			initCharCount();
			initFormSubmit();
			return;
		}

		var btn = qs('#trustscript-write-review-btn');
		var modal = qs('#trustscript-review-modal');
		if (!btn || !modal) {
			return;
		}

		btn.addEventListener('click', function (e) {
			if (btn.getAttribute('data-already-reviewed')) {
				e.preventDefault();
				showToast('You have already reviewed this product.', 'error');
				return;
			}
			resetForm();
			modal.style.display = 'flex';
			document.body.style.overflow = 'hidden';
		});

		document.addEventListener('click', function (e) {
			if (e.target && (e.target.classList.contains('trustscript-edit-review-btn') || e.target.closest('.trustscript-edit-review-btn'))) {
				var editBtn = e.target.classList.contains('trustscript-edit-review-btn') ? e.target : e.target.closest('.trustscript-edit-review-btn');
				e.preventDefault();

				var commentId = editBtn.getAttribute('data-comment-id');
				var rating = editBtn.getAttribute('data-rating');
				var text = editBtn.getAttribute('data-text');

				resetForm();

				var form = qs('#trustscript-review-form');
				if (form) {
					var cidInput = form.querySelector('input[name="comment_id"]');
					if (!cidInput) {
						cidInput = document.createElement('input');
						cidInput.type = 'hidden';
						cidInput.name = 'comment_id';
						form.appendChild(cidInput);
					}
					cidInput.value = commentId;
				}

				if (rating) {
					selectedRating = parseInt(rating, 10);
					var ratingInput = qs('#trustscript-rating-input');
					if (ratingInput) {
						ratingInput.value = selectedRating;
					}
					var btns = qsa('.trustscript-star-btn');
					btns.forEach(function (b) {
						var bRating = parseInt(b.getAttribute('data-rating'), 10);
						if (bRating <= selectedRating) {
							b.classList.add('active');
						} else {
							b.classList.remove('active');
						}
					});
					var label = qs('#trustscript-star-label');
					if (label) {
						label.textContent = STAR_LABELS[selectedRating] || '';
					}
				}

				if (text) {
					var textarea = qs('#trustscript-review-text');
					if (textarea) {
						textarea.value = text;
						textarea.dispatchEvent(new Event('input'));
					}
				}

				modal.style.display = 'flex';
				document.body.style.overflow = 'hidden';
			}
		});

		if (new URLSearchParams(window.location.search).get('write_review') === '1') {
			setTimeout(function () {
				btn.click();
			}, 500);
		}

		var closeBtn = qs('#trustscript-review-modal-close');
		if (closeBtn) {
			closeBtn.addEventListener('click', closeModal);
		}
		modal.addEventListener('click', function (e) {
			if (e.target === modal) {
				closeModal();
			}
		});
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && modal.style.display !== 'none') {
				closeModal();
			}
		});

		initStarRating();
		initSteps();
		initPhotoUpload();
		initCharCount();
		initFormSubmit();
	}

	function closeModal() {
		var modal = qs('#trustscript-review-modal');
		if (modal) {
			modal.style.display = 'none';
			document.body.style.overflow = '';
		}
	}

	function initMultiProductSelector() {
		var buttons = qsa('.trustscript-product-item button');
		var selector = qs('#trustscript-product-selector');
		var formWrap = qs('#trustscript-inline-form-wrap');
		var pidInput = formWrap ? formWrap.querySelector('[name="product_id"]') : null;

		qsa('.trustscript-product-item').forEach(function (item) {
			item.addEventListener('mouseenter', function () {
				item.classList.add('is-hovered');
			});
			item.addEventListener('mouseleave', function () {
				item.classList.remove('is-hovered');
			});
		});

		buttons.forEach(function (btn) {
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				var pid = btn.dataset.productId;
				var pname = btn.dataset.productName;
				var pimage = btn.dataset.productImage;

				cfg.product_id = parseInt(pid, 10);
				if (pidInput) {
					pidInput.value = pid;
				}

				var titleEl = qs('#trustscript-hero-title');
				var imgEl = qs('#trustscript-hero-img');
				var phEl = qs('#trustscript-hero-placeholder');

				if (titleEl) {
					titleEl.textContent = pname;
				}
				if (imgEl && pimage) {
					imgEl.src = pimage;
					imgEl.style.display = 'block';
					if (phEl) {
						phEl.style.display = 'none';
					}
				} else if (imgEl) {
					imgEl.style.display = 'none';
					if (phEl) {
						phEl.style.display = 'block';
					}
				}

				if (selector) {
					selector.style.display = 'none';
				}
				if (formWrap) {
					formWrap.style.display = 'block';
				}
				var card = document.querySelector('.trustscript-review-form');
				if (card) {
					card.scrollIntoView({ behavior: 'smooth', block: 'start' });
				}
			});
		});
	}

	function initStarRating() {
		var btns = qsa('.trustscript-star-btn');
		var label = qs('#trustscript-star-label');

		btns.forEach(function (btn) {
			btn.addEventListener('mouseenter', function () {
				var r = parseInt(btn.getAttribute('data-rating'), 10);
				btns.forEach(function (b) {
					var bRating = parseInt(b.getAttribute('data-rating'), 10);
					if (bRating <= r) {
						b.classList.add('hover');
					} else {
						b.classList.remove('hover');
					}
				});
				if (label) {
					label.textContent = STAR_LABELS[r] || '';
				}
			});

			btn.addEventListener('mouseleave', function () {
				btns.forEach(function (b) {
					b.classList.remove('hover');
				});
				if (label) {
					label.textContent = selectedRating ? STAR_LABELS[selectedRating] : 'Tap a star to rate';
				}
			});

			btn.addEventListener('touchstart', function () {
				var r = parseInt(btn.getAttribute('data-rating'), 10);
				btns.forEach(function (b) {
					var bRating = parseInt(b.getAttribute('data-rating'), 10);
					if (bRating <= r) {
						b.classList.add('hover');
					} else {
						b.classList.remove('hover');
					}
				});
				if (label) {
					label.textContent = STAR_LABELS[r] || '';
				}
			}, { passive: true });

			btn.addEventListener('touchend', function () {
				btns.forEach(function (b) {
					b.classList.remove('hover');
				});
			}, { passive: true });

			btn.addEventListener('click', function (e) {
				e.preventDefault();
				selectedRating = parseInt(btn.getAttribute('data-rating'), 10);
				var input = qs('#trustscript-rating-input');
				if (input) {
					input.value = selectedRating;
				}
				btns.forEach(function (b) {
					var bRating = parseInt(b.getAttribute('data-rating'), 10);
					if (bRating <= selectedRating) {
						b.classList.add('active');
					} else {
						b.classList.remove('active');
					}
				});
				if (label) {
					label.textContent = STAR_LABELS[selectedRating];
				}
				showError('trustscript-rating-error', '');
			});
		});
	}

	function initSteps() {
		var nextBtn = qs('#trustscript-next-btn') || qs('#trustscript-step-next');
		var backBtn = qs('#trustscript-back-btn') || qs('#trustscript-step-back');
		var skipBtn = qs('#trustscript-skip-btn');

		if (nextBtn) {
			nextBtn.addEventListener('click', function (e) {
				e.preventDefault();
				if (!validateStep1()) {
					return;
				}
				goToStep(2);
			});
		}

		if (backBtn) {
			backBtn.addEventListener('click', function () {
				goToStep(1);
			});
		}

		if (skipBtn) {
			skipBtn.addEventListener('click', function () {
				var form = qs('#trustscript-review-form');
				if (!form) {
					return;
				}
				if (typeof form.requestSubmit === 'function') {
					form.requestSubmit();
				} else {
					form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
				}
			});
		}
	}

	function goToStep(stepNum) {
		var s1 = qs('#trustscript-panel-1') || qs('#trustscript-step-1');
		var s2 = qs('#trustscript-panel-2') || qs('#trustscript-step-2');

		if (stepNum === 2) {
			if (s1) { s1.style.display = 'none'; }
			if (s2) { s2.style.display = 'block'; }
			updateStepIndicator(2);
		} else {
			if (s2) { s2.style.display = 'none'; }
			if (s1) { s1.style.display = 'block'; }
			updateStepIndicator(1);
		}
	}

	function updateStepIndicator(step) {
		var dots = qsa('.trustscript-step-dot');
		var line = qs('.trustscript-step-line');

		dots.forEach(function (d) {
			if (step === 1) {
				if (d.dataset.step === '1') {
					d.classList.add('trustscript-step-active');
				} else {
					d.classList.remove('trustscript-step-active');
				}
				d.classList.remove('trustscript-step-done');
			} else {
				if (d.dataset.step === '2') {
					d.classList.add('trustscript-step-active');
				} else {
					d.classList.remove('trustscript-step-active');
				}
				if (d.dataset.step === '1') {
					d.classList.add('trustscript-step-done');
				}
			}
		});

		if (line) {
			if (step === 2) {
				line.classList.add('trustscript-step-line-active');
			} else {
				line.classList.remove('trustscript-step-line-active');
			}
		}
	}

	function validateStep1() {
		clearErrors();
		var valid = true;

		if (selectedRating < 1) {
			showError('trustscript-rating-error', trustscriptStrings.error_rating);
			valid = false;
		}

		var textarea = qs('#trustscript-review-text');
		if (!textarea || textarea.value.trim().length < 10) {
			showError('trustscript-text-error', trustscriptStrings.error_text_short);
			valid = false;
		}

		var nameEl = qs('#trustscript-reviewer-name') || qs('#trustscript-name');
		if (nameEl && nameEl.type !== 'hidden' && !nameEl.value.trim()) {
			showError('trustscript-name-error', trustscriptStrings.error_name);
			valid = false;
		}

		var emailEl = qs('#trustscript-reviewer-email') || qs('#trustscript-email');
		if (emailEl && emailEl.type !== 'hidden' && (!emailEl.value.trim() || emailEl.value.indexOf('@') === -1)) {
			showError('trustscript-email-error', trustscriptStrings.error_email);
			valid = false;
		}

		return valid;
	}

	function initPhotoUpload() {
		var zone = qs('#trustscript-upload-zone');
		var input = qs('#trustscript-photo-input');
		if (!zone || !input) {
			return;
		}

		['dragenter', 'dragover'].forEach(function (ev) {
			zone.addEventListener(ev, function (e) {
				e.preventDefault();
				zone.classList.add('trustscript-dragover', 'dragover');
			});
		});

		['dragleave', 'drop'].forEach(function (ev) {
			zone.addEventListener(ev, function (e) {
				e.preventDefault();
				zone.classList.remove('trustscript-dragover', 'dragover');
			});
		});

		zone.addEventListener('drop', function (e) {
			var files = Array.from(e.dataTransfer.files);
			addPhotos(files);
		});

		input.addEventListener('change', function () {
			addPhotos(Array.from(input.files));
			input.value = '';
		});

		zone.addEventListener('click', function (e) {
			if (e.target === input) {
				return;
			}
			input.click();
		});
	}

	function addPhotos(files) {
		var filtered = files.filter(function (f) {
			return ALLOWED.indexOf(f.type) !== -1 && f.size <= MAX_SIZE;
		});

		var remaining = MAX_PHOTOS - photoFiles.length;
		if (remaining <= 0) {
			showError('trustscript-photo-error', 'Maximum ' + MAX_PHOTOS + ' photos allowed.');
			return;
		}

		var toAdd = filtered.slice(0, remaining);
		photoFiles = photoFiles.concat(toAdd);
		showError('trustscript-photo-error', '');
		renderPreviews();
	}

	function renderPreviews() {
		var container = qs('#trustscript-upload-previews') || qs('#trustscript-previews');
		if (!container) {
			return;
		}
		container.innerHTML = '';

		photoFiles.forEach(function (file, idx) {
			var thumb = document.createElement('div');
			thumb.className = 'trustscript-preview-thumb';

			var img = document.createElement('img');
			img.src = URL.createObjectURL(file);
			img.alt = '';
			thumb.appendChild(img);

			var rmBtn = document.createElement('button');
			rmBtn.type = 'button';
			rmBtn.className = 'trustscript-preview-remove';
			rmBtn.innerHTML = '&times;';
			rmBtn.addEventListener('click', function (e) {
				e.preventDefault();
				photoFiles.splice(idx, 1);
				renderPreviews();
			});
			thumb.appendChild(rmBtn);
			container.appendChild(thumb);
		});

		var placeholder = qs('#trustscript-upload-placeholder');
		if (placeholder) {
			placeholder.style.display = photoFiles.length >= MAX_PHOTOS ? 'none' : '';
		}

		var note = qs('#trustscript-photo-note');
		if (note) {
			note.textContent = photoFiles.length > 0
				? photoFiles.length + ' / ' + MAX_PHOTOS + ' photo' + (photoFiles.length > 1 ? 's' : '') + ' selected'
				: 'Up to 3 photos';
		}
	}

	function initCharCount() {
		var textarea = qs('#trustscript-review-text');
		var counter = qs('#trustscript-char-count') || qs('#trustscript-char-current');
		if (!textarea || !counter) {
			return;
		}
		textarea.addEventListener('input', function () {
			counter.textContent = textarea.value.length.toString();
		});
	}

	function showConfirmationPanel(remainingProducts) {
		var form = qs('#trustscript-review-form');
		var confirmPanel = qs('#trustscript-confirmation');
		var productsList = qs('#trustscript-remaining-products-list');

		if (!confirmPanel || !productsList) {
			return;
		}

		if (form) {
			form.style.display = 'none';
		}
		var stepsEl = qs('.trustscript-review-modal-steps');
		if (stepsEl) {
			stepsEl.style.display = 'none';
		}

		productsList.innerHTML = '';
		remainingProducts.forEach(function (product) {
			var productCard = document.createElement('div');
			productCard.className = 'trustscript-remaining-product-item';

			var image_url = product.imageUrl || '';
			var product_name = product.productName || product.name || '';
			var product_id = product.productId || product.id || '';

			var html = '<div class="trustscript-remaining-product-content">';

			if (image_url) {
				html += '<img src="' + escapeHtml(image_url) + '" alt="" class="trustscript-remaining-product-img">';
			}

			html += '<div class="trustscript-remaining-product-info">';
			html += '<div class="trustscript-remaining-product-name">' + escapeHtml(product_name) + '</div>';
			html += '<button type="button" class="trustscript-btn-secondary trustscript-write-review-remaining" data-product-id="' + escapeHtml(product_id) + '" data-product-name="' + escapeHtml(product_name) + '" data-product-image="' + escapeHtml(image_url) + '">';
			html += 'Write Review';
			html += '</button>';
			html += '</div>';
			html += '</div>';

			productCard.innerHTML = html;
			productsList.appendChild(productCard);
		});

		confirmPanel.style.display = 'block';

		qsa('.trustscript-write-review-remaining').forEach(function (btn) {
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				var pid = btn.getAttribute('data-product-id');
				var pname = btn.getAttribute('data-product-name');
				var pimage = btn.getAttribute('data-product-image');

				cfg.product_id = parseInt(pid, 10);

				var form = qs('#trustscript-review-form');
				var pidInput = form ? form.querySelector('[name="product_id"]') : null;
				if (pidInput) {
					pidInput.value = pid;
				}

				var titleEl = qs('#trustscript-hero-title');
				var imgEl = qs('#trustscript-hero-img');
				var phEl = qs('#trustscript-hero-placeholder');

				if (titleEl) {
					titleEl.textContent = pname;
				}

				if (imgEl && pimage) {
					imgEl.src = pimage;
					imgEl.style.display = 'block';
					if (phEl) { phEl.style.display = 'none'; }
				} else if (imgEl) {
					imgEl.style.display = 'none';
					if (phEl) { phEl.style.display = 'block'; }
				}

				if (confirmPanel) {
					confirmPanel.style.display = 'none';
				}
				if (form) {
					form.style.display = 'block';
				}
				var stepsEl = qs('.trustscript-review-modal-steps');
				if (stepsEl) {
					stepsEl.style.display = '';
				}

				resetForm();

				var card = document.querySelector('.trustscript-review-form');
				if (card) {
					card.scrollIntoView({ behavior: 'smooth', block: 'start' });
				}
			});
		});
	}

	function initFormSubmit() {
		var form = qs('#trustscript-review-form');
		if (!form) {
			return;
		}

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			clearErrors();

			var submitBtn = qs('#trustscript-submit-review') || qs('#trustscript-submit-btn');
			if (!submitBtn) {
				return;
			}
			var btnText = submitBtn.querySelector('.trustscript-btn-text') || qs('#trustscript-submit-text');
			var btnLoad = submitBtn.querySelector('.trustscript-btn-loading') || qs('#trustscript-submit-loading');

			submitBtn.disabled = true;
			if (btnText) { btnText.style.display = 'none'; }
			if (btnLoad) { btnLoad.style.display = 'inline-flex'; }

			var fd = new FormData();
			fd.append('product_id', cfg.product_id || '');
			if (cfg.review_token) {
				fd.append('review_token', cfg.review_token);
			}
			fd.append('rating', selectedRating);

			var textarea = qs('#trustscript-review-text');
			fd.append('review_text', textarea ? textarea.value : '');

			var cidInput = form.querySelector('input[name="comment_id"]');
			if (cidInput && cidInput.value) {
				fd.append('comment_id', cidInput.value);
			}

			var nameEl = qs('#trustscript-reviewer-name') || qs('#trustscript-name');
			if (nameEl) { fd.append('reviewer_name', nameEl.value); }

			var emailEl = qs('#trustscript-reviewer-email') || qs('#trustscript-email');
			if (emailEl) { fd.append('reviewer_email', emailEl.value); }

			var hp = form.querySelector('[name="trustscript_hp"]');
			if (hp) { fd.append('trustscript_hp', hp.value); }

			photoFiles.forEach(function (f) {
				fd.append('photos[]', f);
			});

			fetch(REST + '/trustscript/v1/submit-simple-review', {
				method: 'POST',
				headers: { 'X-WP-Nonce': NONCE },
				body: fd,
			})
				.then(function (resp) {
					return resp.json().then(function (data) {
						return { resp: resp, data: data };
					});
				})
				.then(function (result) {
					var resp = result.resp;
					var data = result.data;

					if (!resp.ok || !data.success) {
						var msg = (data.message) || (data.data && data.data.message) || 'Something went wrong.';
						showToast(msg, 'error');
						submitBtn.disabled = false;
						if (btnText) { btnText.style.display = ''; }
						if (btnLoad) { btnLoad.style.display = 'none'; }
						return;
					}

					showToast(data.message || 'Your review was submitted!', 'success');

					var submittedEvent = new CustomEvent('trustscript:submitted', { bubbles: true });
					form.dispatchEvent(submittedEvent);

					var nextProducts = data.next_products || [];
					if (cfg.standalone && nextProducts.length > 0) {
						showConfirmationPanel(nextProducts);
					} else {
						var successPanel = qs('#trustscript-success');
						if (cfg.standalone && successPanel) {
							form.style.display = 'none';
							successPanel.style.display = 'block';
							var stepsEl = qs('.trustscript-review-modal-steps');
							if (stepsEl) {
								stepsEl.style.display = 'none';
							}
						}

						if (!cfg.standalone) {
							closeModal();
						}

						if (data.approved && data.html) {
							var list = qs('#trustscript-reviews-list');
							if (list) {
								var wrapper = document.createElement('div');
								wrapper.className = 'trustscript-review-card-wrapper';
								wrapper.innerHTML = data.html;
								list.insertBefore(wrapper, list.firstChild);
							}
						}

						resetForm();
					}
				})
				.catch(function () {
					showToast('Network error. Please try again.', 'error');
					submitBtn.disabled = false;
					if (btnText) { btnText.style.display = ''; }
					if (btnLoad) { btnLoad.style.display = 'none'; }
				});
		});
	}

	function resetForm() {
		selectedRating = 0;
		photoFiles = [];

		var form = qs('#trustscript-review-form');
		if (form) {
			form.reset();
		}

		qsa('.trustscript-star-btn').forEach(function (b) {
			b.classList.remove('trustscript-star-active', 'active');
		});

		var ratingInput = qs('#trustscript-rating-input');
		if (ratingInput) {
			ratingInput.value = '';
		}

		var charCounter = qs('#trustscript-char-current') || qs('#trustscript-char-count');
		if (charCounter) {
			charCounter.textContent = '0';
		}

		var starLabel = qs('#trustscript-star-label');
		if (starLabel) {
			starLabel.textContent = 'Tap a star to rate';
		}

		renderPreviews();

		goToStep(1);

		var btn = qs('#trustscript-submit-review') || qs('#trustscript-submit-btn');
		if (btn) {
			btn.disabled = false;
			var t = btn.querySelector('.trustscript-btn-text') || qs('#trustscript-submit-text');
			var l = btn.querySelector('.trustscript-btn-loading') || qs('#trustscript-submit-loading');
			if (t) { t.style.display = ''; }
			if (l) { l.style.display = 'none'; }
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();