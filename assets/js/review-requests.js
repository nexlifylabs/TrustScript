/**
 * TrustScript Review Requests Page
 * @version 1.0.0
 * @since 1.0.0
 */

(function ($) {
	'use strict';

	var currentPage = 1;
	var isLoading = false;
	var i18n = TrustscriptAdmin.i18n || {};

	var STATUS_CFG = {
		pending: { label: i18n.rrStatusPending || 'Pending', bg: '#eff6ff', fg: '#3b82f6' },
		scheduled: { label: i18n.rrStatusScheduled || 'Scheduled', bg: '#fffbeb', fg: '#f59e0b' },
		sent: { label: i18n.rrStatusSent || 'Sent', bg: '#ecfdf5', fg: '#10b981' },
		published: { label: i18n.rrStatusPublished || 'Published', bg: '#d1fae5', fg: '#065f46' },
		'opt-out': { label: i18n.rrStatusOptOut || 'Opt-Out', bg: '#faf5ff', fg: '#8b5cf6' },
		already_published: { label: i18n.rrStatusAlreadyReviewed || 'Already Reviewed', bg: '#f3f4f6', fg: '#6b7280' },
		ineligible: { label: i18n.rrStatusIneligible || 'Ineligible', bg: '#f3f4f6', fg: '#9ca3af' },
		cancelled: { label: i18n.rrStatusCancelled || 'Cancelled', bg: '#fef2f2', fg: '#dc2626' },
	};

	var CONSENT_CFG = {
		'Not Required': { label: i18n.rrConsentNA || 'Not Required', bg: '#f3f4f6', fg: '#6b7280', tooltip: i18n.rrTooltipConsentNA || 'Consent not required for this country' },
		'N/A': { label: i18n.rrConsentNA || 'N/A', bg: '#f3f4f6', fg: '#6b7280', tooltip: i18n.rrTooltipConsentNA || 'Consent not required for this country' },
		'Not Given': { label: i18n.rrConsentNotGiven || 'Not Given', bg: '#fef2f2', fg: '#dc2626', tooltip: i18n.rrTooltipConsentNotGiven || 'Customer did not check the consent checkbox' },
		'Confirmed': { label: i18n.rrConsentConfirmed || 'Confirmed', bg: '#d1fae5', fg: '#065f46', tooltip: i18n.rrTooltipConsentConfirmed || 'Customer confirmed consent (double opt-in link clicked)' },
		'Waiting': { label: i18n.rrConsentWaiting || 'Waiting', bg: '#fef3c7', fg: '#b45309', tooltip: i18n.rrTooltipConsentWaiting || 'Awaiting customer confirmation of double opt-in' },
		'Expired': { label: i18n.rrConsentExpired || 'Expired', bg: '#fecaca', fg: '#991b1b', tooltip: i18n.rrTooltipConsentExpired || 'Double opt-in confirmation link expired (7 days)' },
		'Pending': { label: i18n.rrConsentPending || 'Pending', bg: '#eff6ff', fg: '#3b82f6', tooltip: i18n.rrTooltipConsentPending || 'Consent pending' },
	};

	window.tsEscapeHtml = window.tsEscapeHtml || function (text) {
		var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
		return (text || '').toString().replace(/[&<>"']/g, function (m) { return map[m]; });
	};

	function loadPage(page) {
		if (isLoading) { return; }
		isLoading = true;
		currentPage = page;

		$('#review-requests-loading').show();
		$('#review-requests-list').hide();
		$('#review-requests-empty').hide();

		$.post(
			TrustscriptAdmin.ajax_url,
			{
				action: 'trustscript_fetch_review_requests',
				nonce: TrustscriptAdmin.nonce,
				page: page,
				search: $('#rr-search').val().trim(),
				status: $('#rr-status-filter').val(),
				date_range: $('#rr-date-filter').val(),
			},
			function (response) {
				isLoading = false;
				$('#review-requests-loading').hide();

				if (!response.success || !response.data) {
					showError(i18n.rrErrLoadOrders || 'Failed to load orders');
					return;
				}

				var data = response.data;
				renderStats(data.stats || {});

				if (!data.orders || !data.orders.length) {
					$('#review-requests-empty').show();
					return;
				}

				var start = (data.page - 1) * data.perPage + 1;
				var end = Math.min(data.page * data.perPage, data.total);
				var showingText = i18n.rrTxtShowing || 'Showing';
				var ofText = i18n.rrTxtOf || 'of';
				var entriesText = i18n.rrTxtEntries || 'entries';
				$('#rr-results-info').html(
					showingText + ' <strong>' + start + '–' + end + '</strong> ' + ofText + ' <strong>' + data.total + '</strong> ' + entriesText
				);

				renderTable(data.orders, data.canSendSimple, data.canSendViaApi);
				renderPagination(data.page, data.pages);

				$('#review-requests-list').show();
			}
		).fail(function () {
			isLoading = false;
			$('#review-requests-loading').hide();
			showError(i18n.rrErrNetwork || 'Network error. Please check your connection and try again.');
		});
	}

	function showError(msg) {
		$('#review-requests-empty')
			.html('<div style="text-align:center;padding:40px 20px;"><div style="font-size:13px;color:#d32f2f;">' + window.tsEscapeHtml(msg) + '</div></div>')
			.show();
	}

	function renderStats(stats) {
		$('#rr-stat-total').text(stats.total != null ? stats.total : 0);
		$('#rr-stat-pending').text(stats.pending != null ? stats.pending : 0);
		$('#rr-stat-scheduled').text(stats.scheduled != null ? stats.scheduled : 0);
		$('#rr-stat-sent').text(stats.sent != null ? stats.sent : 0);
		$('#rr-stat-published').text(stats.published != null ? stats.published : 0);
		$('#rr-stat-optout').text(stats['opt-out'] != null ? stats['opt-out'] : 0);
		$('#rr-stat-already-published').text(stats.already_published != null ? stats.already_published : 0);
		$('#rr-stat-ineligible').text(stats.ineligible != null ? stats.ineligible : 0);
		$('#rr-stat-cancelled').text(stats.cancelled != null ? stats.cancelled : 0);
	}

	function renderTable(orders, canSendSimple, canSendViaApi) {
		var html = '';

		orders.forEach(function (order) {
			var isAlreadyPublished = (order.status === 'already_published');
			var isOptOut = (order.status === 'opt-out');
			var isPublished = (order.status === 'published');
			var isSent = (order.status === 'sent');
			var isPending = (order.status === 'pending');
			var isScheduled = (order.status === 'scheduled');
			var isCancelled = (order.status === 'cancelled');
			var isIneligible = (order.status === 'ineligible');

			var cfg = STATUS_CFG[order.status] || { bg: '#f3f4f6', fg: '#6b7280' };
			var statusLabel = STATUS_CFG[order.status] ? STATUS_CFG[order.status].label : (order.status.charAt(0).toUpperCase() + order.status.slice(1));

			var productHtml = order.productUrl
				? '<a href="' + window.tsEscapeHtml(order.productUrl) + '" target="_blank" style="color:#2563eb;text-decoration:none;font-weight:500;">' + window.tsEscapeHtml(order.productName) + '</a>'
				: '<span style="font-weight:500;">' + window.tsEscapeHtml(order.productName) + '</span>';

			// Consent status badge.
			var consentCfg = CONSENT_CFG[order.displayConsentStatus] || { label: window.tsEscapeHtml(order.displayConsentStatus), bg: '#f3f4f6', fg: '#6b7280' };
			var consentTooltip = consentCfg.tooltip ? ' title="' + consentCfg.tooltip + '" style="cursor:help;"' : '';

			// Human-readable ineligibility reason for tooltip.
			var INELIGIBLE_REASON_LABELS = {
				category_filter: i18n.rrReasonCategory || 'Excluded by category filter',
				free_product: i18n.rrReasonFree || 'Free product — excluded by settings',
				min_value: i18n.rrReasonMinVal || 'Item value below minimum threshold',
				fully_refunded: i18n.rrReasonRefunded || 'Fully refunded',
			};

			// Tooltip for informational-only statuses.
			var badgeTooltip = '';
			if (isAlreadyPublished) { badgeTooltip = ' title="' + window.tsEscapeHtml(i18n.rrTooltipAlreadyReviewed || 'Customer has already reviewed this product') + '" style="cursor:help;"'; }
			if (isOptOut) { badgeTooltip = ' title="' + window.tsEscapeHtml(i18n.rrTooltipOptOut || 'Customer opted out of review requests') + '"'; }
			if (isCancelled) { badgeTooltip = ' title="' + window.tsEscapeHtml(i18n.rrTooltipCancelled || 'This item was refunded or the order was cancelled') + '"'; }
			if (isIneligible && order.ineligibleReason) {
				var reasonLabel = INELIGIBLE_REASON_LABELS[order.ineligibleReason] || order.ineligibleReason;
				badgeTooltip = ' title="' + window.tsEscapeHtml(reasonLabel) + '" style="cursor:help;"';
			}

			// Use the raw numeric order ID (not the display order number) for AJAX calls.
			// rawOrderId is always the true DB id; orderId is the display string (e.g. "#1024").
			var rawOrderId = order.rawOrderId || order.orderId.replace('#', '');

			// Action column.
			var actionHtml;
			var canSendEmail = canSendSimple && isPending && !order.emailSent &&
				(order.displayConsentStatus === 'Confirmed' || order.displayConsentStatus === 'N/A' || order.displayConsentStatus === 'Not Required');
			var canSendApiAction = canSendViaApi && isPending && !order.emailSent &&
				(order.displayConsentStatus === 'Confirmed' || order.displayConsentStatus === 'N/A' || order.displayConsentStatus === 'Not Required');
			var disabledBtnText = canSendViaApi ? (i18n.rrBtnSendApi || 'Send via API') : (i18n.rrBtnSendEmail || 'Send Email');

			if (isAlreadyPublished) {
				// Customer already reviewed this product
				actionHtml = '<button type="button" class="button button-small" disabled title="' + window.tsEscapeHtml(i18n.rrTooltipAlreadyReviewed || 'Customer already reviewed this product') + '" style="cursor:not-allowed;opacity:0.5;">' + window.tsEscapeHtml(i18n.rrBtnAlreadyReviewed || 'Already Reviewed') + '</button>';
			} else if (isOptOut || isCancelled || isIneligible) {
				actionHtml = '<span style="color:#ddd;">—</span>';
			} else if (isPublished && order.productUrl) {
				actionHtml = '<a href="' + window.tsEscapeHtml(order.productUrl) + '" target="_blank" class="trustscript-action-link" title="' + window.tsEscapeHtml(i18n.rrTooltipViewReview || 'View review on product page') + '">' + window.tsEscapeHtml(i18n.rrBtnView || 'View') + '</a>';
			} else if (canSendEmail) {
				var btnId = 'trustscript-send-' + rawOrderId;
				actionHtml =
					'<button type="button" id="' + btnId + '" class="button button-small trustscript-send-simple-email-btn" ' +
					'data-order-id="' + window.tsEscapeHtml(String(rawOrderId)) + '">' + window.tsEscapeHtml(i18n.rrBtnSendEmail || 'Send Email') + '</button>';
			} else if (canSendApiAction) {
				var btnId = 'trustscript-send-api-' + rawOrderId;
				actionHtml =
					'<button type="button" id="' + btnId + '" class="button button-small trustscript-send-api-btn" ' +
					'title="' + window.tsEscapeHtml(i18n.rrTooltipSendApi || 'Order will be sent to TrustScript Server for generating review request.') + '" ' +
					'data-order-id="' + window.tsEscapeHtml(String(rawOrderId)) + '">' + window.tsEscapeHtml(i18n.rrBtnSendApi || 'Send via API') + '</button>';
			} else if (isScheduled && !order.emailSent) {
				actionHtml = '<button type="button" class="button button-small" disabled title="' + window.tsEscapeHtml(i18n.rrTooltipEmailScheduled || 'Email scheduled for sending') + '" style="cursor:not-allowed;opacity:0.5;">' + disabledBtnText + '</button>';
			} else if ((canSendSimple || canSendViaApi) && isPending && !order.emailSent && order.displayConsentStatus === 'Waiting') {
				actionHtml = '<button type="button" class="button button-small" disabled title="' + window.tsEscapeHtml(i18n.rrTooltipConsentWaiting || 'Awaiting customer confirmation of double opt-in') + '" style="cursor:not-allowed;opacity:0.5;">' + disabledBtnText + '</button>';
			} else if ((canSendSimple || canSendViaApi) && isPending && !order.emailSent && order.displayConsentStatus === 'Expired') {
				actionHtml = '<button type="button" class="button button-small" disabled title="' + window.tsEscapeHtml(i18n.rrTooltipConsentExpired || 'Double opt-in confirmation link expired') + '" style="cursor:not-allowed;opacity:0.5;">' + disabledBtnText + '</button>';
			} else if ((canSendSimple || canSendViaApi) && isPending && !order.emailSent && order.displayConsentStatus === 'Declined') {
				actionHtml = '<button type="button" class="button button-small" disabled title="' + window.tsEscapeHtml(i18n.rrTooltipCustomerDeclined || 'Customer declined consent') + '" style="cursor:not-allowed;opacity:0.5;">' + disabledBtnText + '</button>';
			} else {
				actionHtml = '<span style="color:#ddd;">—</span>';
			}

			html += '<tr style="border-bottom:1px solid #f3f4f6;">';
			html += '<td style="padding:12px;"><a href="' + window.tsEscapeHtml(order.orderAdminUrl) + '" style="color:#2563eb;text-decoration:none;font-weight:500;">' + window.tsEscapeHtml(order.orderId) + '</a></td>';
			html += '<td style="padding:12px;color:#666;">' + window.tsEscapeHtml(order.customerName) + '</td>';
			html += '<td style="padding:12px;">' + productHtml + '</td>';
			html += '<td style="padding:12px;color:#999;font-size:12px;">' + window.tsEscapeHtml(order.orderDate) + '</td>';
			html += '<td style="padding:12px;"><span style="background:' + consentCfg.bg + ';color:' + consentCfg.fg + ';padding:4px 8px;border-radius:4px;font-size:11px;font-weight:600;display:inline-block;"' + consentTooltip + '>' + window.tsEscapeHtml(consentCfg.label) + '</span></td>';
			html += '<td style="padding:12px;"><span style="background:' + cfg.bg + ';color:' + cfg.fg + ';padding:4px 8px;border-radius:4px;font-size:11px;font-weight:600;display:inline-block;"' + badgeTooltip + '>' + window.tsEscapeHtml(statusLabel) + '</span></td>';
			html += '<td style="padding:12px;text-align:center;">' + actionHtml + '</td>';
			html += '</tr>';
		});

		$('#review-requests-tbody').html(html);
	}

	function renderPagination(currentPg, totalPages) {
		if (totalPages <= 1) {
			$('#rr-pagination').hide();
			return;
		}

		$('#rr-pagination').show();
		var pageText = i18n.rrTxtPage || 'Page';
		var ofText = i18n.rrTxtOf || 'of';
		$('#rr-pagination-info').text(pageText + ' ' + currentPg + ' ' + ofText + ' ' + totalPages);

		var html = '';
		var maxBtns = 5;
		var startPg = Math.max(1, currentPg - Math.floor(maxBtns / 2));
		var endPg = Math.min(totalPages, startPg + maxBtns - 1);
		if (endPg - startPg < maxBtns - 1) { startPg = Math.max(1, endPg - maxBtns + 1); }

		var prevText = i18n.rrBtnPrevious || '← Previous';
		var nextText = i18n.rrBtnNext || 'Next →';

		if (currentPg > 1) { html += btn(currentPg - 1, prevText, false); }
		if (startPg > 1) { html += btn(1, '1', false); if (startPg > 2) { html += '<span style="padding:0 8px;color:#ddd;">…</span>'; } }

		for (var i = startPg; i <= endPg; i++) { html += btn(i, i, i === currentPg); }

		if (endPg < totalPages) { if (endPg < totalPages - 1) { html += '<span style="padding:0 8px;color:#ddd;">…</span>'; } html += btn(totalPages, totalPages, false); }
		if (currentPg < totalPages) { html += btn(currentPg + 1, nextText, false); }

		$('#rr-pagination-buttons').html(html);
		$(document).off('click.rrpag').on('click.rrpag', '.trustscript-page-btn', function () {
			var pg = parseInt($(this).data('page'), 10);
			loadPage(pg);
			$('html, body').animate({ scrollTop: $('#review-requests-list').offset().top - 20 }, 300);
		});
	}

	function btn(page, label, isActive) {
		var style = isActive ? 'background:#2563eb;color:#fff;border:1px solid #2563eb;' : 'background:#fff;color:#1a1a1a;border:1px solid #e5e7eb;';
		var ariaAttr = isActive ? ' aria-current="page"' : '';
		return '<button class="trustscript-page-btn" data-page="' + page + '" style="' + style + 'padding:6px 12px;border-radius:4px;font-size:12px;font-weight:500;cursor:pointer;"' + ariaAttr + '>' + label + '</button> ';
	}

	function resetAndLoad() { currentPage = 1; loadPage(1); }

	$(document).ready(function () {
		loadPage(1);

		$('#rr-apply-filters').on('click', resetAndLoad);
		$('#rr-search').on('keypress', function (e) { if (e.which === 13) { resetAndLoad(); } });
		$('#rr-status-filter, #rr-date-filter').on('change', resetAndLoad);

		$('#refresh-review-requests').on('click', function () {
			var $btn = $(this);
			var orig = $btn.text();
			$btn.prop('disabled', true).text(i18n.rrTxtSending || 'Refreshing...');
			loadPage(currentPage);
			setTimeout(function () { $btn.prop('disabled', false).text(orig); }, 1500);
		});

		$(document).on('click', '.trustscript-send-simple-email-btn', function () {
			var $btn = $(this);
			var orderId = $btn.data('order-id');
			var orig = $btn.text();

			$btn.prop('disabled', true).text(i18n.rrTxtSending || 'Sending...');

			$.post(TrustscriptAdmin.ajax_url, {
				action: 'trustscript_send_simple_review_email',
				nonce: TrustscriptAdmin.nonce,
				order_id: orderId
			}).done(function (res) {
				if (res.success) {
					$btn.text(i18n.rrTxtSent || 'Sent!').addClass('button-primary');
					setTimeout(function () { loadPage(currentPage); }, 1000);
				} else {
					alert(res.data && res.data.message ? res.data.message : (i18n.rrErrSendEmail || 'Failed to send email.'));
					$btn.prop('disabled', false).text(orig);
				}
			}).fail(function () {
				alert(i18n.rrErrNetworkTryAgain || 'Network error. Please try again.');
				$btn.prop('disabled', false).text(orig);
			});
		});

		$(document).on('click', '.trustscript-send-api-btn', function () {
			var $btn = $(this);
			var orderId = $btn.data('order-id');
			var orig = $btn.text();

			$btn.prop('disabled', true).text(i18n.rrTxtSending || 'Sending...');

			$.post(TrustscriptAdmin.ajax_url, {
				action: 'trustscript_send_via_api',
				nonce: TrustscriptAdmin.nonce,
				order_id: orderId
			}).done(function (res) {
				if (res.success) {
					$btn.text(i18n.rrTxtSent || 'Sent!').addClass('button-primary');
					setTimeout(function () { loadPage(currentPage); }, 1000);
				} else {
					alert(res.data && res.data.message ? res.data.message : (i18n.rrErrSendApi || 'Failed to send via API.'));
					$btn.prop('disabled', false).text(orig);
				}
			}).fail(function () {
				alert(i18n.rrErrNetworkTryAgain || 'Network error. Please try again.');
				$btn.prop('disabled', false).text(orig);
			});
		});
	});

})(jQuery);