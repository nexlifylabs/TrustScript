/**
 * TrustScript Reviews and form Settings Page JS
 * @version 1.0.0
 * @since 1.0.0
 */

(function($) {
	'use strict';

	function createStatusBadge(message, type) {
		const span = document.createElement('span');
		span.className = 'trustscript-badge trustscript-badge-' + type;
		span.textContent = message;
		return span;
	}

	function initCategorySearch() {
		const $searchInput = $('#trustscript-category-search');
		if (!$searchInput.length) return;

		$searchInput.on('keyup', function() {
			const searchTerm = this.value.toLowerCase().trim();
			const $categories = $('.trustscript-product-categories-list');
			const $parentLabels = $categories.find('.trustscript-parent-category');
			let visibleCount = 0;

			$parentLabels.each(function() {
				const $parentLabel = $(this);
				const $checkbox = $parentLabel.find('.trustscript-category-checkbox');
				const categoryName = $checkbox.attr('data-category-name') || '';
				const $subcategories = $parentLabel.next('.trustscript-subcategories');
				let hasVisibleChild = false;

				const parentMatches = categoryName.includes(searchTerm);

				if ($subcategories.length) {
					$subcategories.find('.trustscript-category-checkbox').each(function() {
						const $child = $(this);
						const childName = $child.attr('data-category-name') || '';
						const childMatches = childName.includes(searchTerm);
						const $childLabel = $child.closest('label');

						if (childMatches) {
							$childLabel.removeClass('hidden');
							hasVisibleChild = true;
							visibleCount++;
						} else {
							$childLabel.addClass('hidden');
						}
					});

					if (parentMatches || hasVisibleChild) {
						$parentLabel.removeClass('hidden');
						if (searchTerm !== '') {
							$subcategories.addClass('visible').removeClass('hidden');
							$parentLabel.find('.trustscript-expand-toggle').text('▼');
						} else {
							$subcategories.removeClass('visible');
							$parentLabel.find('.trustscript-expand-toggle').text('▶');
						}
						if (parentMatches) visibleCount++;
					} else {
						$parentLabel.addClass('hidden');
						$subcategories.addClass('hidden');
					}
				} else {
					if (parentMatches) {
						$parentLabel.removeClass('hidden');
						visibleCount++;
					} else {
						$parentLabel.addClass('hidden');
					}
				}
			});

			const countText = searchTerm === '' 
				? TrustscriptAdmin.i18n.allCategories
				: (visibleCount === 1 ? TrustscriptAdmin.i18n.oneCategory : visibleCount + ' ' + TrustscriptAdmin.i18n.nCategories.replace('%d', '').trim());
			$('#trustscript-category-count').text(countText);
		});
	}

	function initCategoryBulkActions() {
		$('#trustscript-select-all-categories').on('click', function(e) {
			e.preventDefault();
			$('#trustscript-category-search').val('').trigger('keyup');
			$('.trustscript-product-categories-list').find('.trustscript-category-checkbox:visible').prop('checked', true);
		});

		$('#trustscript-deselect-all-categories').on('click', function(e) {
			e.preventDefault();
			$('#trustscript-category-search').val('').trigger('keyup');
			$('.trustscript-product-categories-list').find('.trustscript-category-checkbox:visible').prop('checked', false);
		});
	}

	function initCategoryToggle() {
		$(document).on('click', '.trustscript-expand-toggle', function(e) {
			e.preventDefault();
			e.stopPropagation();
			
			const $toggle = $(this);
			const $parentLabel = $toggle.closest('.trustscript-parent-category');
			const $subcats = $parentLabel.next('.trustscript-subcategories');

			if ($subcats.length) {
				const isVisible = $subcats.hasClass('visible');
				
				if (isVisible) {
					$subcats.removeClass('visible');
					$toggle.text('▶');
				} else {
					$subcats.addClass('visible');
					$toggle.text('▼');
				}
			}
		});
	}

	function initCategoryParentChildLogic() {
		$(document).on('change', '.trustscript-parent-checkbox', function() {
			const $checkbox = $(this);
			const isChecked = $checkbox.is(':checked');
			const categoryId = $checkbox.val();
			const $categoryLabel = $checkbox.closest('.trustscript-parent-category');
			const $immediateSubcats = $categoryLabel.next('.trustscript-subcategories');

			if (isChecked && $immediateSubcats.length) {
				$immediateSubcats.find('.trustscript-parent-checkbox').prop('checked', false);
				$immediateSubcats.addClass('visible');
				$categoryLabel.find('.trustscript-expand-toggle').text('▼');
			}

			if (isChecked) {
				$checkbox.closest('.trustscript-subcategories').prevAll('.trustscript-parent-category').each(function() {
					const $ancestor = $(this);
					const $ancestorCheckbox = $ancestor.find('.trustscript-parent-checkbox').first();
					if ($ancestorCheckbox.length && $ancestorCheckbox.is(':checked')) {
						$ancestorCheckbox.prop('checked', false);
					}
				});
			}
		});
	}

	function initReviews() {
		bindTriggerStatusChange();
		bindSaveSettings();
		bindSaveReviewFormSettings();
		bindPlaceholderInsertion();
		bindEmailPreview();
		bindEmailReset();
		bindSyncOrders();
		bindDelayToggle();
		bindInternationalToggle();
		bindServiceSettings();
		bindServiceToggle();
		bindTabButton();
		bindOptionalDataSettings();
		bindModerationSettings();
		updateDelayDescription();

		initCategorySearch();
		initCategoryBulkActions();
		initCategoryToggle();
		initCategoryParentChildLogic();
	}

	function updateDelayDescription() {
		const triggerStatus = $('#trustscript_review_trigger_status').val();
		const $desc = $('#delay-description');
		
		if (triggerStatus === 'delivered') {
			$desc.text(TrustscriptAdmin.i18n.delayDelivered);
		} else {
			$desc.text(TrustscriptAdmin.i18n.delayCompleted);
		}
	}

	function bindTriggerStatusChange() {
		$('#trustscript_review_trigger_status').on('change', updateDelayDescription);
	}

	function bindDelayToggle() {
		$('#trustscript_review_delay_hours').on('change', function() {
			$('#trustscript-custom-delay-wrapper').toggle($(this).val() === 'custom');
		});

		$('#trustscript_international_delay_hours').on('change', function() {
			$('#trustscript-custom-intl-delay-wrapper').toggle($(this).val() === 'custom');
		});
	}

	function bindInternationalToggle() {
		$('#trustscript_enable_international_handling').on('change', function() {
			if ($(this).is(':checked')) {
				$('#trustscript-international-delay-section').slideDown(300);
			} else {
				$('#trustscript-international-delay-section').slideUp(300);
			}
		});
	}

	function bindSaveReviewFormSettings() {
		$('#trustscript-save-review-form').on('click', function() {
			const $btn = $(this);
			const $status = $('#trustscript-review-form-save-status');
			
			$btn.prop('disabled', true).text(TrustscriptAdmin.i18n.saving);
			$status.empty();

			if (typeof tinyMCE !== 'undefined') {
				tinyMCE.triggerSave();
			}

			const data = {
				action:                           'trustscript_save_review_settings',
				nonce:                            $('#trustscript_review_form_nonce').val() || TrustscriptAdmin.save_review_nonce,
				trustscript_review_page_id:       $('#trustscript_review_page_id').val(),
				trustscript_simple_email_subject: $('#trustscript_simple_email_subject').val(),
				trustscript_simple_email_body:    $('#trustscript_simple_email_body').val()
			};

			$.post(TrustscriptAdmin.ajax_url, data, function(response) {
				$btn.prop('disabled', false).text(TrustscriptAdmin.i18n.saveButton);
				
				if (response.success) {
					$status.empty().append(createStatusBadge(TrustscriptAdmin.i18n.saveSuccess, 'success'));
					setTimeout(function() { $status.empty(); }, 3000);
				} else {
					var message = (response.data && response.data.message) ? response.data.message : TrustscriptAdmin.i18n.syncFailed;
					$status.empty().append(createStatusBadge(message, 'danger'));
				}
			}).fail(function() {
				$btn.prop('disabled', false).text(TrustscriptAdmin.i18n.saveButton);
				$status.empty().append(createStatusBadge(TrustscriptAdmin.i18n.networkError, 'danger'));
			});
		});
	}

	function bindPlaceholderInsertion() {
		var editorId = 'trustscript_simple_email_body';

		$(document).on('click', '.trustscript-insert-placeholder', function() {
			var placeholder = $(this).data('placeholder');
			if (!placeholder) { return; }

			var editor = (typeof tinyMCE !== 'undefined') ? tinyMCE.get(editorId) : null;

			if (editor && !editor.isHidden()) {
				editor.insertContent(placeholder);
				editor.focus();
			} else {
				var el = document.getElementById(editorId);
				if (!el) { return; }
				var start = el.selectionStart;
				var end   = el.selectionEnd;
				el.value  = el.value.substring(0, start) + placeholder + el.value.substring(end);
				el.selectionStart = el.selectionEnd = start + placeholder.length;
				el.focus();
			}
		});
	}

	function bindEmailPreview() {
		$('#trustscript-preview-email-btn').on('click', function() {
			var $btn    = $(this);
			var $status = $('#trustscript-preview-email-status');

			if (typeof tinyMCE !== 'undefined') {
				tinyMCE.triggerSave();
			}

			var subject = $('#trustscript_simple_email_subject').val();
			var body    = $('#trustscript_simple_email_body').val();

			$btn.prop('disabled', true).text('Generating…');
			$status.text('');

			$.post(TrustscriptAdmin.ajax_url, {
				action:  'trustscript_preview_simple_email',
				nonce:   TrustscriptAdmin.nonce,
				subject: subject,
				body:    body
			}, function(res) {
				$btn.prop('disabled', false).text('&#128065; Preview Email');
				if (res.success && res.data) {
					$('#trustscript-preview-subject').text(res.data.subject);
					$('#trustscript-preview-body').html(res.data.body);
					$('#trustscript-email-preview-row').show();
					$status.text('');
					$('html,body').animate({ scrollTop: $('#trustscript-email-preview-row').offset().top - 30 }, 300);
				} else {
					var msg = (res.data && res.data.message) ? res.data.message : 'Preview failed.';
					$status.text(msg);
				}
			}).fail(function() {
				$btn.prop('disabled', false).text('&#128065; Preview Email');
				$status.text('Network error — please try again.');
			});
		});

		$(document).on('click', '#trustscript-preview-close', function() {
			$('#trustscript-email-preview-row').hide();
		});
	}

	function bindEmailReset() {
		$('#trustscript-reset-email-btn').on('click', function() {
			if ( ! confirm( TrustscriptAdmin.i18n ? (TrustscriptAdmin.i18n.resetConfirm || 'Are you sure you want to reset the email template? Custom changes will be lost.') : 'Are you sure you want to reset the email template? Custom changes will be lost.' ) ) {
				return;
			}
			if (typeof tsDefaultEmailData !== 'undefined') {
				$('#trustscript_simple_email_subject').val(tsDefaultEmailData.subject);
				$('#trustscript_simple_email_body').val(tsDefaultEmailData.body);
				
				if (typeof tinyMCE !== 'undefined') {
					var editor = tinyMCE.get('trustscript_simple_email_body');
					if (editor && !editor.isHidden()) {
						editor.setContent(tsDefaultEmailData.body);
					}
				}
			}
		});
	}

	function bindSaveSettings() {
		$('#trustscript-save-review-settings').on('click', function() {
			const $btn = $(this);
			const $status = $('#trustscript-review-save-status');
			
			$btn.prop('disabled', true).text(TrustscriptAdmin.i18n.saving);
			$status.empty();

			const categories = [];
			$('input[name="trustscript_review_categories[]"]:checked').each(function() {
				categories.push($(this).val());
			});

			const memberpress_memberships = [];
			$('input[name="trustscript_memberpress_memberships[]"]:checked').each(function() {
				memberpress_memberships.push($(this).val());
			});

			const keywords = [];
			$('input[name="trustscript_keywords[]"]:checked').each(function() {
				keywords.push($(this).val());
			});

			let delay_hours = $('#trustscript_review_delay_hours').val();
			if (delay_hours === 'custom') {
				const customValue = $('#trustscript_custom_delay_value').val() || '0';
				const customUnit = $('#trustscript_custom_delay_unit').val() || 'days';
				delay_hours = customUnit === 'hours' ? customValue : (parseInt(customValue, 10) * 24);
			}

			let intl_delay_hours = $('#trustscript_international_delay_hours').val();
			if (intl_delay_hours === 'custom') {
				const customIntlValue = $('#trustscript_custom_intl_delay_value').val() || '0';
				const customIntlUnit = $('#trustscript_custom_intl_delay_unit').val() || 'days';
				intl_delay_hours = customIntlUnit === 'hours' ? customIntlValue : (parseInt(customIntlValue, 10) * 24);
			}

			if (typeof tinyMCE !== 'undefined') {
				tinyMCE.triggerSave();
			}

			const data = {
				action:                               'trustscript_save_review_settings',
				nonce:                                TrustscriptAdmin.save_review_nonce,
				simple_review_enabled:                $('#trustscript_simple_review_enabled').is(':checked')          ? '1' : '0',
				api_review_collection_enabled:        $('#trustscript_api_review_collection_enabled').is(':checked')  ? '1' : '0',
				enabled:                              $('#trustscript_reviews_enabled').is(':checked')                 ? '1' : '0',
				categories:                           categories,
				memberpress_memberships:              memberpress_memberships,
				trustscript_review_keywords:          keywords,
				memberpress_delay_days:               $('#trustscript_memberpress_delay_days').val(),
				auto_publish:                         $('#trustscript_auto_publish').is(':checked')                    ? '1' : '0',
				enable_voting:                        $('#trustscript_enable_voting').is(':checked')                  ? '1' : '0',
				delay_hours:                          delay_hours,
				trigger_status:                       $('#trustscript_review_trigger_status').val(),
				auto_sync_enabled:                    $('#trustscript_auto_sync_enabled').is(':checked')              ? '1' : '0',
				auto_sync_time:                       $('#trustscript_auto_sync_time').val(),
				auto_sync_lookback:                   $('#trustscript_auto_sync_lookback').val(),
				trustscript_woocommerce_min_value:    $('#trustscript_woocommerce_min_value').val(),
				trustscript_woocommerce_exclude_free: $('#trustscript_woocommerce_exclude_free').is(':checked')       ? '1' : '0',
				enable_international_handling:        $('#trustscript_enable_international_handling').is(':checked')  ? 'true' : 'false',
				international_delay_hours:            intl_delay_hours
			};

			$.post(TrustscriptAdmin.ajax_url, data, function(response) {
				$btn.prop('disabled', false).text(TrustscriptAdmin.i18n.saveButton);
				
				if (response.success) {
					$status.empty().append(createStatusBadge(TrustscriptAdmin.i18n.saveSuccess, 'success'));
					setTimeout(function() { $status.empty(); }, 3000);
				} else {
					var message = (response.data && response.data.message) ? response.data.message : TrustscriptAdmin.i18n.syncFailed;
					$status.empty().append(createStatusBadge(message, 'danger'));
				}
			}).fail(function() {
				$btn.prop('disabled', false).text(TrustscriptAdmin.i18n.saveButton);
				$status.empty().append(createStatusBadge(TrustscriptAdmin.i18n.networkError, 'danger'));
			});
		});
	}

	function bindSyncOrders() {
		$('#trustscript-sync-orders').on('click', function() {
			const $btn = $(this);
			const $status = $('#trustscript-sync-status');
			const $results = $('#trustscript-sync-results');
			const $count = $('#sync-count');
			
			if (!$btn.data('syncConfirmed')) {
				$status.html(
					'<div style="padding:12px;background:#fff3cd;border:1px solid #ffc107;border-radius:4px;margin-bottom:12px;">' +
					'<p style="margin:0 0 8px 0;font-weight:600;">' + TrustscriptAdmin.i18n.syncConfirm + '</p>' +
					'<button type="button" class="sync-confirm-yes button button-primary" style="margin-right:6px;">Yes, Sync</button>' +
					'<button type="button" class="sync-confirm-no button">Cancel</button>' +
					'</div>'
				).show();
				
				$status.find('.sync-confirm-yes').on('click', function() {
					$btn.data('syncConfirmed', true);
					$btn.click();
				});
				
				$status.find('.sync-confirm-no').on('click', function() {
					$btn.data('syncConfirmed', false);
					$status.empty();
				});
				
				return;
			}
			
			$btn.data('syncConfirmed', false);
			
			$btn.prop('disabled', true).empty();
			const spinIcon = document.createElement('span');
			spinIcon.className = 'dashicons dashicons-update trustscript-spin';
			$btn.append(spinIcon).append(document.createTextNode(' ' + TrustscriptAdmin.i18n.syncing));
			$status.empty();
			$results.hide();

			const data = {
				action: 'trustscript_sync_orders',
				nonce: TrustscriptAdmin.nonce,
				days: $('#trustscript-sync-days').val()
			};

			$.post(TrustscriptAdmin.ajax_url, data, function(response) {
				$btn.prop('disabled', false).empty();
				const icon = document.createElement('span');
				icon.className = 'dashicons dashicons-update';
				$btn.append(icon).append(document.createTextNode(' ' + TrustscriptAdmin.i18n.syncButton));
				
				if (response.success) {
					const processed = response.data.processed || 0;
					const reviewsPublished = response.data.reviews_published || 0;
					const ordersSynced = response.data.orders_synced || 0;
					const ordersSkipped = response.data.orders_skipped || 0;
					
					$count.text(processed);
					$results.fadeIn();
					
					let fullMessage = response.data.message;
					
					if (reviewsPublished > 0 || ordersSynced > 0 || ordersSkipped > 0) {
						fullMessage += '\n\n📊 ' + TrustscriptAdmin.i18n.syncBreakdown;
						if (reviewsPublished > 0) fullMessage += '\n✅ ' + reviewsPublished + ' ' + TrustscriptAdmin.i18n.syncReviewsPublished;
						if (ordersSynced > 0) fullMessage += '\n📤 ' + ordersSynced + ' ' + TrustscriptAdmin.i18n.syncOrdersSent;
						if (ordersSkipped > 0) fullMessage += '\n⏭️ ' + ordersSkipped + ' ' + TrustscriptAdmin.i18n.syncOrdersSkipped;
					}
					
					const statusDiv = createStatusBadge(fullMessage, 'success');
					if (reviewsPublished > 0 || ordersSynced > 0 || ordersSkipped > 0) {
						statusDiv.style.whiteSpace = 'pre-wrap';
						statusDiv.style.lineHeight = '1.8';
					}
					$status.empty().append(statusDiv);
					setTimeout(function() { $status.empty(); }, 8000);
				} else {
					var message = (response.data && response.data.message) ? response.data.message : TrustscriptAdmin.i18n.syncFailed;
					$status.empty().append(createStatusBadge(message, 'danger'));
				}
			}).fail(function() {
				$btn.prop('disabled', false).empty();
				const icon = document.createElement('span');
				icon.className = 'dashicons dashicons-update';
				$btn.append(icon).append(document.createTextNode(' ' + TrustscriptAdmin.i18n.syncButton));
				$status.empty().append(createStatusBadge(TrustscriptAdmin.i18n.networkError, 'danger'));
			});
		});
	}

	function bindServiceSettings() {
		$('#trustscript-save-service-settings').on('click', function(e) {
			e.preventDefault();
			
			const $button = $(this);
			const $message = $('#trustscript-service-save-status');
			const originalText = $button.text().trim();
			
			const formData = {
				action: 'trustscript_save_service_settings',
				nonce: TrustscriptAdmin.nonce
			};
			
			$('.trustscript-service-toggle').each(function() {
				const serviceId = $(this).data('service-id');
				formData['trustscript_enable_service_' + serviceId] = $(this).is(':checked') ? '1' : '0';
			});
			
			$('.trustscript-service-trigger').each(function() {
				const serviceId = $(this).data('service-id');
				formData['trustscript_trigger_status_' + serviceId] = $(this).val();
			});
			
			$button.prop('disabled', true).text(TrustscriptAdmin.i18n.saving || 'Saving...');
			$message.empty();
			
			$.post(TrustscriptAdmin.ajax_url, formData)
				.done(function(response) {
					if (response.success) {
						$message.append(createStatusBadge(response.data.message || TrustscriptAdmin.i18n.saveSuccess, 'success'));
					} else {
						$message.append(createStatusBadge(response.data.message || TrustscriptAdmin.i18n.saveFailed, 'danger'));
					}
				})
				.fail(function(xhr) {
					const errorMsg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : TrustscriptAdmin.i18n.networkError;
					$message.append(createStatusBadge(errorMsg, 'danger'));
				})
				.always(function() {
					$button.prop('disabled', false).text(originalText);
					
					setTimeout(function() {
						$message.fadeOut(300, function() {
							$(this).empty().show();
						});
					}, 5000);
				});
		});
	}

	function bindServiceToggle() {
		$('.trustscript-service-toggle').on('change', function() {
			const $card = $(this).closest('.trustscript-service-card');
			const $body = $card.find('.trustscript-service-body');
			const isEnabled = $(this).is(':checked');
			
			if (isEnabled) {
				$card.removeClass('inactive').addClass('active');
				$body.css({'opacity': '1', 'pointer-events': 'auto'});
			} else {
				$card.removeClass('active').addClass('inactive');
				$body.css({'opacity': '0.5', 'pointer-events': 'none'});
			}
		});
	}

	function bindTabButton() {
		$('.trustscript-tab-button').on('click', function() {
			const $button = $(this);
			const serviceId = $button.data('service');
			
			$('.trustscript-tab-button').removeClass('active');
			$button.addClass('active');
			
			$('.trustscript-tab-panel').removeClass('active');
			$('#trustscript-service-' + serviceId).addClass('active');
		});
	}

	function bindOptionalDataSettings() {
		$('#trustscript-save-optional-data-settings').on('click', function(e) {
			e.preventDefault();
			
			const $button = $(this);
			const $message = $('#trustscript-optional-data-save-status');
			const originalText = $button.text().trim();
			
			const formData = {
				action: 'trustscript_save_optional_data_settings',
				nonce: TrustscriptAdmin.nonce,
				trustscript_include_product_names: $('#trustscript-include-product-names').is(':checked') ? '1' : '0',
				trustscript_include_order_dates: $('#trustscript-include-order-dates').is(':checked') ? '1' : '0'
			};
			
			$button.prop('disabled', true).text(TrustscriptAdmin.i18n.saving || 'Saving...');
			$message.empty();
			
			$.post(TrustscriptAdmin.ajax_url, formData)
				.done(function(response) {
					if (response.success) {
						$message.append(createStatusBadge(response.data.message || TrustscriptAdmin.i18n.saveSuccess, 'success'));
					} else {
						$message.append(createStatusBadge(response.data.message || TrustscriptAdmin.i18n.saveFailed, 'danger'));
					}
				})
				.fail(function(xhr) {
					const errorMsg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : TrustscriptAdmin.i18n.networkError;
					$message.append(createStatusBadge(errorMsg, 'danger'));
				})
				.always(function() {
					$button.prop('disabled', false).text(originalText);
					
					setTimeout(function() {
						$message.fadeOut(300, function() {
							$(this).empty().show();
						});
					}, 5000);
				});
		});
	}

	function bindModerationSettings() {
		$('#trustscript-save-moderation-settings').on('click', function(e) {
			e.preventDefault();
			
			const $button = $(this);
			const $message = $('#trustscript-moderation-save-status');
			const originalText = $button.text().trim();
			
			const formData = {
				action: 'trustscript_save_moderation_settings',
				nonce: TrustscriptAdmin.nonce || TrustscriptAdmin.save_review_nonce,
				trustscript_review_blocked_words: $('#trustscript_review_blocked_words').val()
			};
			
			$button.prop('disabled', true).text(TrustscriptAdmin.i18n.saving || 'Saving...');
			$message.empty();
			
			$.post(TrustscriptAdmin.ajax_url, formData)
				.done(function(response) {
					if (response.success) {
						$message.append(createStatusBadge(response.data.message || TrustscriptAdmin.i18n.saveSuccess || 'Saved successfully', 'success'));
					} else {
						$message.append(createStatusBadge(response.data.message || TrustscriptAdmin.i18n.saveFailed || 'Failed to save', 'danger'));
					}
				})
				.fail(function(xhr) {
					const errorMsg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : (TrustscriptAdmin.i18n.networkError || 'Network error');
					$message.append(createStatusBadge(errorMsg, 'danger'));
				})
				.always(function() {
					$button.prop('disabled', false).text(originalText);
					
					setTimeout(function() {
						$message.fadeOut(300, function() {
							$(this).empty().show();
						});
					}, 5000);
				});
		});
	}

	$(document).ready(function() {
		initReviews();
	});

})(jQuery);
