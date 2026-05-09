(function($){
    'use strict';
    
    function showApiKeyError(message, title) {
        title = title || 'API Key Error';
        const $container = $('#trustscript-api-key-inline-error');
        
        if ($container.length) {
            $container.html(
                '<div class="trustscript-api-key-error">' +
                    '<span class="trustscript-api-key-error-icon">⚠️</span>' +
                    '<div class="trustscript-api-key-error-body">' +
                        '<strong>' + $('<span>').text(title).html() + '</strong>' +
                        '<p>' + $('<span>').text(message).html() + '</p>' +
                    '</div>' +
                    '<button type="button" class="trustscript-api-key-error-dismiss" aria-label="Dismiss">&times;</button>' +
                '</div>'
            ).show();
        } else {
            showNotification(message, 'error');
        }
    }

    function showNotification(message, type) {
        type = type || 'error';
        
        $('.trustscript-notification').remove();
        
        const $notification = $('<div></div>')
            .addClass('trustscript-notification trustscript-notification-' + type)
            .text(message)
            .appendTo('body');
        
        const dismissTimeout = setTimeout(function() {
            $notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
        
        $notification.on('click', function() {
            clearTimeout(dismissTimeout);
            $(this).fadeOut(300, function() {
                $(this).remove();
            });
        });
    }

    function getErrorMessage(xhr, fallback) {
        fallback = fallback || TrustscriptAdmin.i18n.unknownError || 'Unknown error';
        if (!xhr || !xhr.responseJSON || !xhr.responseJSON.data) {
            return fallback;
        }
        return xhr.responseJSON.data.message || fallback;
    }




    $(document).ready(function(){


        $('.trustscript-api-form').on('submit', function(e){
            const $apiKeyInput = $('#trustscript_api_key');
            
            if ($apiKeyInput.length && $apiKeyInput.attr('required') !== undefined) {
                const keyValue = $.trim($apiKeyInput.val());
                
                if (!keyValue) {
                    e.preventDefault();
                    e.stopPropagation();
                    $apiKeyInput.addClass('trustscript-input-error').trigger('focus');
                    showApiKeyError(
                        TrustscriptAdmin.i18n.apiKeyRequired,
                        TrustscriptAdmin.i18n.apiKeyRequiredTitle
                    );
                    setTimeout(function() {
                        $apiKeyInput.removeClass('trustscript-input-error');
                    }, 3000);
                    return false;
                }
                
                if (!keyValue.match(/^TSK-[A-F0-9-]+$/i)) {
                    e.preventDefault();
                    e.stopPropagation();
                    $apiKeyInput.addClass('trustscript-input-error').trigger('focus');
                    showApiKeyError(
                        TrustscriptAdmin.i18n.invalidFormat,
                        TrustscriptAdmin.i18n.invalidFormatTitle
                    );
                    setTimeout(function() {
                        $apiKeyInput.removeClass('trustscript-input-error');
                    }, 3000);
                    return false;
                }
            }
        });

        $(document).on('click', '.trustscript-api-key-error-dismiss', function() {
            $(this).closest('.trustscript-api-key-error').fadeOut(200, function() {
                $(this).remove();
            });
            $('#trustscript-api-key-inline-error').hide();
        });

        $(document).on('click', '.trustscript-dismissible-notice .notice-dismiss', function() {
            var notice = $(this).closest('.trustscript-dismissible-notice').data('notice');
            if (!notice) return;
            jQuery.post(TrustscriptAdmin.ajax_url, {
                action: 'trustscript_dismiss_notice',
                notice: notice,
                nonce: TrustscriptAdmin.nonce,
            });
        });

        $(document).on('input', '#trustscript_api_key', function() {
            $(this).removeClass('trustscript-input-error');
            $('#trustscript-api-key-inline-error').hide().empty();
            $('.trustscript-api-key-error').not('#trustscript-api-key-inline-error .trustscript-api-key-error').fadeOut(200);
        });

        function scrollToApiKeyMessage() {
            var target = document.querySelector('.trustscript-api-key-error')
                        || document.querySelector('.trustscript-api-key-success')
                        || document.querySelector('.trustscript-api-key-warning')
                        || document.getElementById('trustscript-api-form');
            if (target) {
                setTimeout(function() {
                    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 120);
            }
        }

        scrollToApiKeyMessage();

        const $apiKeyContainer = $('[data-api-key-container]');

        $(document).on('click', '[data-action="edit-api-key"]', function(e){
            e.preventDefault();
            const $container = $('[data-api-key-container]');
            const $input = $('<input>').attr({
                type: 'password',
                name: 'trustscript_api_key',
                id: 'trustscript_api_key_input',
                class: 'regular-text',
                placeholder: TrustscriptAdmin.i18n.pasteApiKeyPlaceholder
            });
            const $cancelBtn = $('<button>').attr({
                type: 'button',
                class: 'button',
                'data-action': 'cancel-edit-api-key'
            }).css('margin-left', '6px').text(TrustscriptAdmin.i18n.cancel);
            $container.empty().append($input, $cancelBtn);
            $input.trigger('focus');
        });

        $(document).on('click', '[data-action="cancel-edit-api-key"]', function(e){
            e.preventDefault();
            const $container = $('[data-api-key-container]');
            const $masked = $('<span>').attr('id', 'trustscript-api-key-masked').text('**************');
            const $editBtn = $('<button>').attr({
                type: 'button',
                id: 'trustscript-edit-api-key',
                class: 'button trustscript-edit-button',
                'data-action': 'edit-api-key'
            }).text(TrustscriptAdmin.i18n.edit);
            $container.empty().append($masked, ' ', $editBtn);
        });

        $(document).on('click', '[data-action="delete-api-key"]', function(e){
            e.preventDefault();
            const $modal = $('[data-modal="delete-api-key"]');
            $modal.fadeIn(200).css('display', 'flex').data('open', true);
        });

        $(document).on('click', '[data-action="close-modal"], [data-action="cancel-modal"]', function(e){
            e.preventDefault();
            const $modal = $('[data-modal="delete-api-key"]');
            $modal.fadeOut(200).data('open', false);
        });

        $(document).on('click', '[data-modal="delete-api-key"]', function(e){
            if (e.target === this) {
                $(this).fadeOut(200).data('open', false);
            }
        });

        $(document).on('click', '[data-action="confirm-delete"]', function(e){
            e.preventDefault();
            const $btn = $(this);
            const $modal = $('[data-modal="delete-api-key"]');
            
            $btn.prop('disabled', true).data('original-text', $btn.text()).text(TrustscriptAdmin.i18n.deleting);
            $btn.data('deleting', true);

            $.post(TrustscriptAdmin.ajax_url, {
                action: 'trustscript_delete_api_key',
                nonce: TrustscriptAdmin.nonce
            }).done(function(res){
                $modal.fadeOut(200).data('open', false);
                if (res.success) {
                    location.reload();
                } else {
                    showNotification(res.data && res.data.message ? res.data.message : TrustscriptAdmin.i18n.failedToDelete, 'error');
                }
            }).fail(function(xhr){
                $modal.fadeOut(200).data('open', false);
                const msg = getErrorMessage(xhr, TrustscriptAdmin.i18n.failedToDelete);
                showNotification(msg, 'error');
            }).always(function(){
                $btn.prop('disabled', false).text($btn.data('original-text')).data('deleting', false);
            });
        });

        function fetchUsage() {
            const $loading = $('#trustscript-usage-loading');
            const $container = $('#trustscript-usage');
            const $error = $('#trustscript-usage-error');
            $loading.show(); $container.hide(); $error.hide();

            $.post(TrustscriptAdmin.ajax_url, {
                action: 'trustscript_fetch_usage',
                nonce: TrustscriptAdmin.nonce
            }).done(function(res){
                $loading.hide();
                if (res.success && res.data) {
                    const data = res.data;
                    $('#trustscript-plan').text((data.plan || 'free').toUpperCase());
                    $('#trustscript-used').text(data.rewritesThisMonth || 0);
                    $('#trustscript-limit').text(data.monthlyLimit || 0);
                    const percent = data.monthlyLimit ? Math.min(100, Math.round((data.rewritesThisMonth / data.monthlyLimit) * 100)) : 0;
                    $('#trustscript-progress').css('width', percent + '%');
                    $container.show();
                } else {
                    $error.text((res.data && res.data.message) ? res.data.message : 'Failed to fetch usage');
                    $error.show();
                }
            }).fail(function(xhr){
                $loading.hide();
                $error.text(getErrorMessage(xhr, TrustscriptAdmin.i18n.networkError)).show();
            });
        }

        fetchUsage();

        $('#trustscript-refresh-usage').on('click', function(e){
            e.preventDefault();
            fetchUsage();
        });


        $(document)
            .off('click.trustscriptFaq', '.trustscript-faq-question')
            .on('click.trustscriptFaq', '.trustscript-faq-question', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            const $button = $(this);
            const $answer = $button.next('.trustscript-faq-answer');
            const $icon = $button.find('.trustscript-faq-icon').first();
            const isExpanded = $answer.is(':visible') || $button.attr('aria-expanded') === 'true';

            if (isExpanded) {
                $button.attr('aria-expanded', 'false');
                $answer.stop(true, true).slideUp(300);
                if ($icon.length) $icon.text('+');
            } else {
                $('.trustscript-faq-question').not($button).each(function() {
                    $(this).attr('aria-expanded', 'false');
                    $(this).next('.trustscript-faq-answer').stop(true, true).slideUp(300);
                    const $otherIcon = $(this).find('.trustscript-faq-icon').first();
                    if ($otherIcon.length) $otherIcon.text('+');
                });

                $button.attr('aria-expanded', 'true');
                $answer.stop(true, true).slideDown(300);
                if ($icon.length) $icon.text('−');
            }
        });




        const $consentCheckbox = $('#trustscript-consent-checkbox');
        const $consentCheckboxLabel = $('.trustscript-consent-checkbox');
        const $saveButton = $('#submit, .trustscript-connect-btn');
        const $consentLink = $('.trustscript-consent-link');
        const $consentModal = $('#trustscript-consent-modal');
        const $consentAgree = $('#trustscript-consent-agree');
        const $consentDecline = $('#trustscript-consent-decline');

        function syncSaveButton() {
            if ($consentCheckbox.length && !$consentCheckbox.is(':checked')) {
                $saveButton.prop('disabled', true).css('opacity', '0.5').attr('title', 'Please accept the data sharing terms first');
            } else {
                $saveButton.prop('disabled', false).css('opacity', '1').removeAttr('title');
            }
        }

        syncSaveButton();

        $consentCheckbox.on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $consentModal.fadeIn(200).css('display', 'flex');
            return false;
        });

        $consentCheckboxLabel.on('click', function(e) {
            if ($(e.target).closest('.trustscript-consent-link').length) {
                return true;
            }
            e.preventDefault();
            e.stopPropagation();
            $consentModal.fadeIn(200).css('display', 'flex');
            return false;
        });

        $(document).on('submit', '.trustscript-api-form', function(e) {
            if ($consentCheckbox.length && !$consentCheckbox.is(':checked')) {
                e.preventDefault();
                e.stopImmediatePropagation();
                $consentModal.fadeIn(200).css('display', 'flex');
            }
        });

        $consentLink.on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $consentModal.fadeIn(200).css('display', 'flex');
        });

        $consentDecline.on('click', function() {
            $consentModal.fadeOut(200);
        });

        $consentAgree.on('click', function() {
            $consentCheckbox.prop('checked', true);
            syncSaveButton();
            $consentModal.fadeOut(200);
        });

        $consentCheckbox.on('change', function() {
            syncSaveButton();
        });

        $consentModal.on('click', function(e) {
            if (e.target === this) {
                $(this).fadeOut(200);
            }
        });

        $(document).on('click', '[data-action="retry-queue-item"]', function(){
            const $btn = $(this);
            const id = $btn.data('id');
            $btn.prop('disabled', true).text(TrustscriptAdmin.i18n.saving);
            
            $.post(TrustscriptAdmin.ajax_url, {
                action: 'trustscript_queue_retry',
                id: id,
                nonce: $btn.data('nonce')
            }).done(function(res){
                if (res.success) {
                    $('#trustscript-queue-row-' + id).fadeOut(300, function(){ $(this).remove(); });
                } else {
                    showNotification((res.data && res.data.message) ? res.data.message : TrustscriptAdmin.i18n.retryFailed, 'error');
                    $btn.prop('disabled', false).text(TrustscriptAdmin.i18n.retry);
                }
            }).fail(function(xhr){
                showNotification(getErrorMessage(xhr, TrustscriptAdmin.i18n.retryFailed), 'error');
                $btn.prop('disabled', false).text(TrustscriptAdmin.i18n.retry);
            });
        });

        $(document).on('click', '[data-action="clear-queue-item"]', function(){
            const $btn = $(this);
            const id = $btn.data('id');
            if (!window.confirm((TrustscriptAdmin.i18n && TrustscriptAdmin.i18n.confirmClear) ? TrustscriptAdmin.i18n.confirmClear : 'Are you sure? This will remove the item from the queue.')) {
                return;
            }
            $btn.prop('disabled', true).text(TrustscriptAdmin.i18n.clearing);
            
            $.post(TrustscriptAdmin.ajax_url, {
                action: 'trustscript_queue_clear',
                id: id,
                nonce: $btn.data('nonce')
            }).done(function(res){
                if (res.success) {
                    $('#trustscript-queue-row-' + id).fadeOut(300, function(){ $(this).remove(); });
                } else {
                    showNotification((res.data && res.data.message) ? res.data.message : TrustscriptAdmin.i18n.clearFailed, 'error');
                    $btn.prop('disabled', false).text(TrustscriptAdmin.i18n.clear);
                }
            }).fail(function(xhr){
                showNotification(getErrorMessage(xhr, TrustscriptAdmin.i18n.clearFailed), 'error');
                $btn.prop('disabled', false).text(TrustscriptAdmin.i18n.clear);
            });
        });

        $(document).on('click', '.trustscript-scroll-to', function(e){
            e.preventDefault();
            const target = $(this).attr('href');
            if (target && $(target).length) {
                $('html,body').animate({ scrollTop: $(target).offset().top - 40 }, 380);
            }
        });

        
        $(document).on('click', '#trustscript-queue-process-all', function(e){
            e.preventDefault();
            const $btn = $(this);
            const $summary = $('.trustscript-queue-summary');
            
            $btn.prop('disabled', true).text(TrustscriptAdmin.i18n.queuedForProcessing);
            
            $summary.after(
                $('<div></div>').attr('id', 'trustscript-processing-msg').addClass('trustscript-processing-msg').html(
                    '⏳ ' + TrustscriptAdmin.i18n.processingBackground
                )
            );
            
            $.post(TrustscriptAdmin.ajax_url, {
                action: 'trustscript_queue_process_all',
                nonce:  $btn.data('nonce')
            }).done(function(res){
                if (res.success) {
                    location.reload();
                } else {
                    $('#trustscript-processing-msg').remove();
                    showNotification(res.data && res.data.message ? res.data.message : TrustscriptAdmin.i18n.processQueueFailed, 'error');
                    $btn.prop('disabled', false).text(TrustscriptAdmin.i18n.processQueueNow);
                }
            }).fail(function(xhr){
                $('#trustscript-processing-msg').remove();
                showNotification(getErrorMessage(xhr, TrustscriptAdmin.i18n.processQueueFailed), 'error');
                $btn.prop('disabled', false).text(TrustscriptAdmin.i18n.processQueueNow);
            });
        });

        $(document).on('click', '.trustscript-queue-retry', function(e){
            e.preventDefault();
            const $btn = $(this);
            const id   = $btn.data('id');
            $btn.prop('disabled', true).text(TrustscriptAdmin.i18n.retrying);
            $.post(TrustscriptAdmin.ajax_url, {
                action: 'trustscript_queue_retry',
                id:     id,
                nonce:  $btn.data('nonce')
            }).done(function(res){
                if (res.success) {
                    const $row = $('#trustscript-queue-row-' + id);
                    if (res.data && res.data.removed) {
                        $row.fadeOut(300, function(){ $(this).remove(); });
                    } else {
                        location.reload();
                    }
                } else {
                    showNotification(res.data && res.data.message ? res.data.message : TrustscriptAdmin.i18n.retryFailed, 'error');
                    $btn.prop('disabled', false).text(TrustscriptAdmin.i18n.retry);
                }
            }).fail(function(xhr){
                showNotification(getErrorMessage(xhr, TrustscriptAdmin.i18n.retryFailed), 'error');
                $btn.prop('disabled', false).text(TrustscriptAdmin.i18n.retry);
            });
        });

        $(document).on('click', '.trustscript-queue-clear', function(e){
            e.preventDefault();
            const $btn  = $(this);
            const id    = $btn.data('id');
            const order = $btn.data('order');
            const message = ((TrustscriptAdmin.i18n && TrustscriptAdmin.i18n.confirmClearQueue) ? TrustscriptAdmin.i18n.confirmClearQueue : 'Remove order #' + order + ' from the queue? This will not cancel the order, but the review request will NOT be retried.');
            if (!window.confirm(message)) {
                return;
            }
            $btn.prop('disabled', true).text(TrustscriptAdmin.i18n.clearing);
            $.post(TrustscriptAdmin.ajax_url, {
                action: 'trustscript_queue_clear',
                id:     id,
                nonce:  $btn.data('nonce')
            }).done(function(res){
                if (res.success) {
                    $('#trustscript-queue-row-' + id).fadeOut(300, function(){ $(this).remove(); });
                } else {
                    showNotification(res.data && res.data.message ? res.data.message : TrustscriptAdmin.i18n.clearFailed, 'error');
                    $btn.prop('disabled', false).text(TrustscriptAdmin.i18n.clear);
                }
            }).fail(function(xhr){
                showNotification(getErrorMessage(xhr, TrustscriptAdmin.i18n.clearFailed), 'error');
                $btn.prop('disabled', false).text(TrustscriptAdmin.i18n.clear);
            });
        });

        $('#trustscript-uninstall-form').on('submit', function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $btn = $('#trustscript-uninstall-save-btn');
            var $checkbox = $('#trustscript_delete_data_on_uninstall');
            
            $btn.prop('disabled', true).text(TrustscriptAdmin.i18n.saving);
            
            $.ajax({
                url: TrustscriptAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'trustscript_save_uninstall_preference',
                    delete_data: $checkbox.is(':checked') ? '1' : '0',
                    nonce: TrustscriptAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotification(response.data.message, 'success');
                    } else {
                        showNotification((response.data && response.data.message) ? response.data.message : TrustscriptAdmin.i18n.savePreferenceFailed, 'error');
                    }
                },
                error: function(xhr) {
                    showNotification(getErrorMessage(xhr, TrustscriptAdmin.i18n.networkError), 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text(TrustscriptAdmin.i18n.savePreference);
                }
            });
        });

        // Trust Strip Settings
        $('#trustscript-trust-strip-form').on('submit', function(e) {
            e.preventDefault();
            var $form = $(this);
            var $btn = $('#trustscript-trust-strip-save-btn');
            var $spinner = $form.find('.spinner');
            var $message = $('.trustscript-trust-strip-message');

            $btn.prop('disabled', true);
            $spinner.addClass('is-active');
            $message.text('');

            $.ajax({
                url: TrustscriptAdmin.ajax_url,
                method: 'POST',
                data: {
                    action: 'trustscript_save_trust_strip_settings',
                    nonce: TrustscriptAdmin.nonce,
                    enable_trust_strip: $('#trustscript_enable_trust_strip').is(':checked') ? 'true' : 'false'
                },
                success: function(resp) {
                    if (resp.success) {
                        $message.text(resp.data.message).css('color', 'green');
                    } else {
                        $message.text(resp.data.message || 'Error saving settings').css('color', 'red');
                    }
                },
                error: function() {
                    $message.text(TrustscriptAdmin.i18n.networkError).css('color', 'red');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    $spinner.removeClass('is-active');
                    setTimeout(function() { $message.text(''); }, 3000);
                }
            });
        });

        $( document ).on( 'input', '.trustscript-country-search-input', function () {
            var q     = $( this ).val().toLowerCase();
            var $list = $( this ).closest( '.trustscript-country-picker-container' ).find( '.trustscript-country-grid label' );
            $list.each( function () {
                var name = $( this ).text().toLowerCase();
                $( this ).toggle( name.indexOf( q ) !== -1 );
            } );
        } );

        $( document ).on( 'click', '.trustscript-country-select-all', function () {
            var $wrap = $( this ).closest( '.trustscript-country-picker-container' );
            $wrap.find( 'input[type=checkbox]:not(:disabled)' ).prop( 'checked', true );
        } );
        $( document ).on( 'click', '.trustscript-country-deselect-all', function () {
            var $wrap = $( this ).closest( '.trustscript-country-picker-container' );
            $wrap.find( 'input[type=checkbox]:not(:disabled)' ).prop( 'checked', false );
        } );

        $( '#trustscript-privacy-form' ).on( 'submit', function ( e ) {
            e.preventDefault();

            var $btn = $( '#trustscript-privacy-save-btn' );
            var $msg = $( '#trustscript-privacy-save-msg' );
            
            var textSaving = (TrustscriptAdmin && TrustscriptAdmin.i18n && TrustscriptAdmin.i18n.saving) ? TrustscriptAdmin.i18n.saving : 'Saving…';
            var textSaveForm = (TrustscriptAdmin && TrustscriptAdmin.i18n && TrustscriptAdmin.i18n.saveChanges) ? TrustscriptAdmin.i18n.saveChanges : 'Save Changes';
            var textNetworkErr = (TrustscriptAdmin && TrustscriptAdmin.i18n && TrustscriptAdmin.i18n.networkError) ? TrustscriptAdmin.i18n.networkError : 'Network error. Please try again.';
            var textSaveFailed = (TrustscriptAdmin && TrustscriptAdmin.i18n && TrustscriptAdmin.i18n.saveFailed) ? TrustscriptAdmin.i18n.saveFailed : 'Save failed. Please try again.';

            $btn.prop( 'disabled', true ).text( textSaving );
            $msg.hide();

            var data = $( this ).serialize();
            data += '&action=trustscript_save_privacy_settings';

            $.post( TrustscriptAdmin ? TrustscriptAdmin.ajax_url : ajaxurl, data, function ( res ) {
                $btn.prop( 'disabled', false ).text( textSaveForm );
                if ( res.success ) {
                    $msg.css( 'color', '#2ecc71' )
                        .text( '✓ ' + res.data.message )
                        .fadeIn();
                } else {
                    $msg.css( 'color', '#e74c3c' )
                        .text( '✗ ' + ( (res.data && res.data.message) ? res.data.message : textSaveFailed ) )
                        .fadeIn();
                }
                setTimeout( function () { $msg.fadeOut(); }, 4000 );
            } ).fail( function () {
                $btn.prop( 'disabled', false ).text( textSaveForm );
                $msg.css( 'color', '#e74c3c' )
                    .text( textNetworkErr )
                    .fadeIn();
            } );
        } );

        const $verificationModalForm = $('#trustscript-verification-modal-form');
        const $verificationModalSaveBtn = $('#trustscript-verification-modal-save-btn');
        const $verificationModalMessage = $('.trustscript-verification-modal-message');
        const $verificationModalSpinner = $('#trustscript-verification-modal-form .spinner');

        if ($verificationModalForm.length) {
            $verificationModalForm.on('submit', function(e) {
                e.preventDefault();

                const isEnabled = $('#trustscript_enable_verification_modal').is(':checked');
                $verificationModalSpinner.addClass('is-active');
                $verificationModalSaveBtn.prop('disabled', true);
                $verificationModalMessage.html('').removeClass('notice-success notice-error');

                $.ajax({
                    url: TrustscriptAdmin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'trustscript_save_verification_modal_settings',
                        nonce: TrustscriptAdmin.nonce,
                        enable_verification_modal: isEnabled
                    },
                    success: function(response) {
                        if (response.success) {
                            $verificationModalMessage
                                .html('<span style="color: #46b450;">✓ ' + response.data.message + '</span>')
                                .addClass('notice-success');
                        } else {
                            $verificationModalMessage
                                .html('<span style="color: #dc3232;">✗ ' + (response.data.message || 'Failed to save') + '</span>')
                                .addClass('notice-error');
                        }
                    },
                    error: function() {
                        $verificationModalMessage
                            .html('<span style="color: #dc3232;">✗ ' + TrustscriptAdmin.i18n.saveFailed + '</span>')
                            .addClass('notice-error');
                    },
                    complete: function() {
                        $verificationModalSpinner.removeClass('is-active');
                        $verificationModalSaveBtn.prop('disabled', false);

                        setTimeout(function() {
                            $verificationModalMessage.fadeOut(300, function() {
                                $(this).html('').show();
                            });
                        }, 3000);
                    }
                });
            });
        }

    });

})(jQuery);