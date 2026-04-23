/**
 * Admin Table Editing - JavaScript
 * 
 * Handles inline editing for membership management and client creation
 * Best Practices 2025: ES6+, Async/Await, Error Handling
 * 
 * @package MembershipValidator
 * @since 1.0.0
 */

(function($) {
    'use strict';
    
    // Configuration from wp_localize_script
    const config = window.ocAdminData || {
        ajaxUrl: '/wp-admin/admin-ajax.php',
        nonce: ''
    };
    
    /**
     * 🎯 Helper: Scroll smooth la un element cu offset de la top
     */
    function scrollToElement($element, offsetTop = 15) {
        if ($element.length) {
            $('html, body').animate({
                scrollTop: $element.offset().top - offsetTop
            }, 400);
        }
    }

    function syncInfoButtonState(userId, isExpanded) {
        const $button = $(`.oc-admin-card[data-user-id="${userId}"]`).find('.oc-btn-info').first();
        if (!$button.length) {
            return;
        }

        $button.toggleClass('is-active', Boolean(isExpanded));
        $button.attr('aria-expanded', isExpanded ? 'true' : 'false');
    }

    function syncAddSubscriptionButtonState(userId, isExpanded) {
        const $button = $(`.oc-btn-add-subscription[data-user-id="${userId}"]`).first();
        if (!$button.length) {
            return;
        }

        $button.toggleClass('oc-btn-warning', Boolean(isExpanded));
        $button.toggleClass('oc-btn-success', !isExpanded);
        $button.attr('aria-expanded', isExpanded ? 'true' : 'false');
    }

    function closeVisibleRenewForms(exceptUserId = null) {
        $('.oc-renew-container:visible').each(function() {
            const $container = $(this);
            const containerId = ($container.attr('id') || '').replace('oc-renew-container-', '');

            if (exceptUserId !== null && containerId === exceptUserId.toString()) {
                return;
            }

            $container.stop(true, true).slideUp(200);
            if (containerId !== '') {
                syncAddSubscriptionButtonState(containerId, false);
            }
        });
    }

    function closeAddClientForm() {
        const $container = $('#oc-add-client-form-container');
        if ($container.is(':visible')) {
            $container.stop(true, true).slideUp(200);
        }

        $('#oc-toggle-add-client-form')
            .removeClass('is-active')
            .attr('aria-expanded', 'false');
    }

    window.ocAdminCloseAddClientForm = closeAddClientForm;
    window.ocAdminCloseVisibleRenewForms = closeVisibleRenewForms;
    
    /**
     * 🎯 Helper: Închide toate cardurile expandate (pentru accordion behavior)
     */
    function closeAllExpandedCards(exceptUserId = null) {
        $('.oc-card-details:visible').each(function() {
            const $details = $(this);
            const cardUserId = $details.attr('id').replace('card-details-', '');
            
            // Skip cardul curent (dacă e specificat)
            if (exceptUserId && cardUserId === exceptUserId.toString()) {
                return;
            }
            
            // Închide cardul
            if ($details.is(':visible')) {
                $details.stop(true, true).slideUp(200);
                $details.find('.oc-renew-container:visible').hide();
                syncInfoButtonState(cardUserId, false);
                syncAddSubscriptionButtonState(cardUserId, false);
            }
        });
    }
    
    /**
     * 🎯 Previne scroll automat la bottom la refresh
     */
    if ('scrollRestoration' in history) {
        history.scrollRestoration = 'manual';
    }
    window.scrollTo(0, 0);
    
    /**
     * Initialize on document ready
     * 🔒 PROTECȚIE: Previne inițializare dublă dacă script-ul se încarcă de 2 ori
     */
    $(document).ready(function() {
        // Check dacă a fost deja inițializat
        if (window.ocAdminTableInitialized) {
            return; // Skip inițializarea dublă
        }
        window.ocAdminTableInitialized = true;
        
        initEditButtons();
        initProtectedActiveEditButton();
        initSaveButtons();
        initCancelButtons();
        initCreateOrderButtons();
        initCancelMembershipButtons(); // ← NOU: Buton anulare abonament
        initValidationHistoryButtons();
        initPackageDropdowns();
        initAddCourseButton();
        initNewClientForm();
        initSearchAutoReset();
        initHistoryToggleButton();
        initManualActivation(); // ← NOU: Activare manuală abonamente pending
        initExpiredHistory(); // ← NOU: Toggle istoric abonamente expirate
        initWpDateTextPickers();
        applyWpLocaleToDateInputs();
        initAutoExpirationCalculation(); // ← NOU: Calcul automat data expirare (+28 zile)
        $('#oc-toggle-add-client-form').attr('aria-expanded', 'false');
    });

    function applyWpLocaleToDateInputs($scope = $(document)) {
        const locale = (config.locale || '').toString().replace('_', '-').trim();
        if (!locale) {
            return;
        }

        $scope.find('input[type="date"]').attr('lang', locale);
    }

    function parseDisplayDateToIso(rawValue) {
        const value = (rawValue || '').toString().trim();
        if (value === '') {
            return '';
        }

        if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
            return value;
        }

        const format = (config.dateFormat || 'd/m/Y').toString();
        const tokens = [];
        let escaped = false;

        for (const ch of format) {
            if (escaped) {
                escaped = false;
                continue;
            }
            if (ch === '\\') {
                escaped = true;
                continue;
            }
            if (['d', 'j', 'm', 'n', 'Y', 'y'].includes(ch)) {
                tokens.push(ch);
            }
        }

        const numbers = value.match(/\d+/g) || [];
        if (tokens.length === 0 || numbers.length !== tokens.length) {
            return null;
        }

        let day = null;
        let month = null;
        let year = null;

        for (let i = 0; i < tokens.length; i++) {
            const token = tokens[i];
            const number = parseInt(numbers[i], 10);
            if (Number.isNaN(number)) {
                return null;
            }

            if (token === 'd' || token === 'j') {
                day = number;
            } else if (token === 'm' || token === 'n') {
                month = number;
            } else if (token === 'Y') {
                year = number;
            } else if (token === 'y') {
                year = number >= 70 ? 1900 + number : 2000 + number;
            }
        }

        if (!day || !month || !year) {
            return null;
        }

        const date = new Date(year, month - 1, day);
        if (
            date.getFullYear() !== year ||
            date.getMonth() !== month - 1 ||
            date.getDate() !== day
        ) {
            return null;
        }

        return `${year.toString().padStart(4, '0')}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
    }

    function getIsoDateFieldValue($field) {
        if (!$field || !$field.length) {
            return '';
        }

        const raw = ($field.val() || '').toString().trim();
        if (raw === '') {
            return '';
        }

        return parseDisplayDateToIso(raw);
    }

    function initWpDateTextPickers() {
        function buildHiddenPicker($anchorInput) {
            const $anchor = $anchorInput && $anchorInput.length ? $anchorInput : $();
            const offset = $anchor.length ? $anchor.offset() : null;
            const width = $anchor.length ? $anchor.outerWidth() : 1;
            const height = $anchor.length ? $anchor.outerHeight() : 1;
            const scrollTop = $(window).scrollTop() || 0;
            const scrollLeft = $(window).scrollLeft() || 0;
            const viewportWidth = $(window).width() || window.innerWidth || 0;
            const viewportHeight = $(window).height() || window.innerHeight || 0;
            const popupEstimatedWidth = 340;
            const popupEstimatedHeight = 360;

            let top = offset ? offset.top : 0;
            let left = offset ? offset.left : 0;

            const anchorTopInViewport = top - scrollTop;
            const anchorLeftInViewport = left - scrollLeft;
            const spaceRight = viewportWidth - anchorLeftInViewport;
            const spaceBottom = viewportHeight - anchorTopInViewport;

            if (spaceRight < popupEstimatedWidth) {
                left -= (popupEstimatedWidth - spaceRight + 8);
            }

            if (spaceBottom < popupEstimatedHeight) {
                top -= (popupEstimatedHeight - spaceBottom + 8);
            }

            const minLeft = Math.max(scrollLeft + 4, 0);
            const minTop = Math.max(scrollTop + 4, 0);
            left = Math.max(left, minLeft);
            top = Math.max(top, minTop);

            const $picker = $('<input type="date" class="oc-hidden-native-datepicker">').css({
                position: 'absolute',
                top: `${top}px`,
                left: `${left}px`,
                width: `${Math.max(width || 1, 1)}px`,
                height: `${Math.max(height || 1, 1)}px`,
                opacity: 0.01,
                pointerEvents: 'none',
                zIndex: 999999
            }).appendTo(document.body);

            applyWpLocaleToDateInputs($picker);
            return $picker;
        }

        // Marchez explicit interacțiunea directă pe input (mouse/touch).
        // Click-ul sintetizat prin <label for="..."> nu setează acest flag.
        $(document).on('mousedown touchstart', 'input.oc-wp-date-input', function() {
            $(this).data('ocDirectDateInputClick', true);
        });

        $(document).on('click', 'input.oc-wp-date-input', function(e) {
            const $activeInput = $(this);
            const isDirectInputClick = $activeInput.data('ocDirectDateInputClick') === true;
            $activeInput.removeData('ocDirectDateInputClick');

            if (!isDirectInputClick) {
                return;
            }

            e.preventDefault();

            // Curăță orice picker temporar rămas (ex: blur neanunțat în unele browsere)
            $('.oc-hidden-native-datepicker').remove();

            const $picker = buildHiddenPicker($activeInput);
            const iso = getIsoDateFieldValue($activeInput);
            let cleaned = false;

            function cleanup() {
                if (cleaned) {
                    return;
                }
                cleaned = true;
                $picker.off('change');
                $(document).off('mousedown.ocTempDatePicker touchstart.ocTempDatePicker');
                $picker.remove();
            }

            $picker.on('change', function() {
                const selectedIso = (this.value || '').toString();
                if (selectedIso) {
                    $activeInput.val(formatDateForDisplay(selectedIso)).trigger('change');
                }
                cleanup();
            });

            // Închide DOAR la următorul click explicit în afara câmpului (nu la hover/mouse move)
            setTimeout(function() {
                $(document).on('mousedown.ocTempDatePicker touchstart.ocTempDatePicker', function(event) {
                    const $target = $(event.target);
                    if ($target.closest('input.oc-wp-date-input').length) {
                        return;
                    }
                    cleanup();
                });
            }, 0);

            $picker.val(iso && iso !== null ? iso : '');

            const input = $picker[0];
            try {
                input.focus({ preventScroll: true });
                if (typeof input.showPicker === 'function') {
                    input.showPicker();
                } else {
                    input.click();
                }
            } catch (err) {
                input.click();
            }
        });
    }
    
    /**
     * Initialize Edit and "Abonament Nou" buttons
     */
    function getCardMembershipStatus($card) {
        const raw = String($card.closest('.oc-admin-card').data('status') || '').trim().toLowerCase();
        return raw;
    }

    function hasActiveMembershipSensitiveUnlock($card) {
        const unlockUntil = parseInt($card.attr('data-active-edit-unlocked-until') || '0', 10);
        if (!Number.isFinite(unlockUntil) || unlockUntil <= 0) {
            return false;
        }
        return Math.floor(Date.now() / 1000) < unlockUntil;
    }

    function setActiveMembershipSensitiveUnlock($card, unlockedUntilEpoch) {
        const ts = parseInt(unlockedUntilEpoch || '0', 10);
        if (Number.isFinite(ts) && ts > 0) {
            $card.attr('data-active-edit-unlocked-until', String(ts));
        }
    }

    function applyActiveMembershipSensitiveLockState($card) {
        const isActive = getCardMembershipStatus($card) === 'active';
        if (!isActive) {
            return;
        }

        const unlocked = hasActiveMembershipSensitiveUnlock($card);
        const $sensitivePackageFields = $card.find('.oc-package-date-field, .oc-package-meta-field, input[name="no_expiry"]');
        const $courseSessionInputs = $card.find('.oc-course-session-input');

        if (!unlocked) {
            $sensitivePackageFields
                .prop('disabled', true)
                .removeClass('oc-field-editable')
                .addClass('oc-field-readonly');

            $courseSessionInputs.each(function() {
                const $inp = $(this);
                $inp.prop('disabled', true)
                    .removeClass('oc-field-editable')
                    .addClass('oc-field-readonly')
                    .hide();
                $inp.siblings('.oc-stat-display').show();
            });
        } else {
            $sensitivePackageFields
                .prop('disabled', false)
                .removeClass('oc-field-readonly')
                .addClass('oc-field-editable');

            $courseSessionInputs.each(function() {
                const $inp = $(this);
                if (!$inp.attr('data-original-value')) {
                    $inp.attr('data-original-value', $inp.val());
                }
                $inp.prop('disabled', false)
                    .removeClass('oc-field-readonly')
                    .addClass('oc-field-editable')
                    .show();
                $inp.siblings('.oc-stat-display').hide();
            });
        }
    }

    function askActiveMembershipPin() {
        return new Promise((resolve) => {
            const modalHtml = `
                <div class="oc-inline-confirm-backdrop" id="oc-active-pin-modal">
                    <div class="oc-inline-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="oc-active-pin-title">
                        <button type="button" class="oc-inline-confirm-close" data-action="cancel-pin" aria-label="Închide dialogul">
                            <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                        </button>
                        <div class="oc-inline-confirm-badge" aria-hidden="true">
                            <span class="dashicons dashicons-lock"></span>
                        </div>
                        <div class="oc-inline-confirm-header">
                            <div class="oc-inline-confirm-kicker">Siguranță</div>
                            <h3 id="oc-active-pin-title">Editeaza abonament activ</h3>
                        </div>
                        <div class="oc-inline-confirm-body">
                            <p>Introdu PIN-ul pentru a debloca temporar campurile sensibile.</p>
                            <input type="password" class="oc-inline-confirm-pin-input" id="oc-active-pin-input" autocomplete="off" placeholder="PIN" />
                            <div class="oc-inline-confirm-error" id="oc-active-pin-error" style="display:none;"></div>
                        </div>
                        <div class="oc-inline-confirm-actions">
                            <button type="button" class="button button-primary" data-action="confirm-pin">Deblocheaza</button>
                            <button type="button" class="button" data-action="cancel-pin">Anuleaza</button>
                        </div>
                    </div>
                </div>`;

            const $modal = $(modalHtml);
            $('body').append($modal);

            const $input = $modal.find('#oc-active-pin-input');
            const $error = $modal.find('#oc-active-pin-error');

            const closeWith = (value) => {
                $modal.remove();
                resolve(value);
            };

            $modal.on('click', '[data-action="cancel-pin"]', function() {
                closeWith('');
            });

            $modal.on('click', function(evt) {
                if (evt.target === $modal.get(0)) {
                    closeWith('');
                }
            });

            $modal.on('click', '[data-action="confirm-pin"]', function() {
                const pin = String($input.val() || '').trim();
                if (!pin) {
                    $error.text('PIN-ul este obligatoriu.').show();
                    return;
                }
                closeWith(pin);
            });

            $input.on('keydown', function(evt) {
                if (evt.key === 'Enter') {
                    evt.preventDefault();
                    $modal.find('[data-action="confirm-pin"]').trigger('click');
                }
            });

            setTimeout(() => $input.trigger('focus'), 0);
        });
    }

    function initProtectedActiveEditButton() {
        $(document).on('click', '.oc-edit-active-membership-btn', async function(e) {
            e.preventDefault();
            e.stopPropagation();

            const $btn = $(this);
            const $card = $btn.closest('.oc-card-details');
            if (!$card.length) {
                return;
            }

            const userId = String($btn.data('user-id') || '').trim();
            if (!userId) {
                showNotification('❌ Nu am putut identifica utilizatorul pentru deblocare.', 'error');
                return;
            }

            const pin = await askActiveMembershipPin();
            if (!pin) {
                return;
            }

            $btn.prop('disabled', true);
            try {
                const response = await $.post(config.ajaxUrl, {
                    action: 'oc_verify_active_membership_edit_pin',
                    nonce: config.nonce,
                    target_user_id: userId,
                    pin
                });

                if (!response || !response.success) {
                    const msg = response?.data?.message || 'PIN invalid.';
                    showNotification('❌ ' + msg, 'error');
                    return;
                }

                const unlockedUntil = parseInt(response?.data?.unlocked_until || '0', 10);
                setActiveMembershipSensitiveUnlock($card, unlockedUntil);

                const isInEditMode = $card.find('.oc-btn-save-member:visible').length > 0;
                if (!isInEditMode) {
                    $card.find('.oc-edit-btn').first().trigger('click');
                } else {
                    applyActiveMembershipSensitiveLockState($card);
                }

                showNotification('✅ Câmpurile sensibile au fost deblocate temporar.', 'success');
            } catch (err) {
                showNotification('❌ Eroare la validarea PIN-ului.', 'error');
            } finally {
                $btn.prop('disabled', false);
            }
        });
    }

    function initEditButtons() {
        // Buton "Edit" - activează editarea câmpurilor normale (EXCLUDE cursuri + ședințe)
        $(document).on('click', '.oc-edit-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const userId = $(this).data('user-id');
            const $card = $(this).closest('.oc-card-details');
            if (!$card.length) {
                return;
            }
            
            // Editabile doar câmpurile principale marcate explicit în card (nu formularul de reînnoire)
            const $inputs = $card.find('[data-field-type]')
                .not('.oc-field-always-readonly')
                .not('.oc-course-session-input')
                .not('.oc-renew-container [data-field-type]');

            // Checkbox-uri editabile explicit (în principal no_expiry pe pachet)
            const $checkboxes = $card.find('input[name="no_expiry"], .oc-preserve-expiry-checkbox');

            const $editBtn = $(this);
            const $saveBtn = $card.find('.oc-btn-save-member');
            const $cancelBtn = $card.find('.oc-btn-cancel-edit');
            
            
            // Salvează valorile originale DOAR pentru câmpuri editabile
            $inputs.add($checkboxes).each(function() {
                const $input = $(this);
                if (!$input.attr('data-original-value')) {
                    if ($input.is(':checkbox')) {
                        $input.attr('data-original-value', $input.is(':checked') ? '1' : '0');
                    } else {
                        $input.attr('data-original-value', $input.val());
                    }
                }
            });
            
            // Activează editarea
            $inputs.prop('disabled', false).removeClass('oc-field-readonly').addClass('oc-field-editable');
            $checkboxes.prop('disabled', false).removeClass('oc-field-readonly').addClass('oc-field-editable');

            const $packageDateScopes = $card.find('.oc-package-date-fields');
            if ($packageDateScopes.length) {
                $packageDateScopes.each(function() {
                    syncNoExpiryState($(this));
                });
            } else {
                syncNoExpiryState($card);
            }

            // Activează inputurile de ședințe per curs: afișează inputs, ascunde valorile statice
            $card.find('.oc-course-session-input').each(function() {
                const $inp = $(this);
                if (!$inp.attr('data-original-value')) {
                    $inp.attr('data-original-value', $inp.val());
                }
                $inp.prop('disabled', false).removeClass('oc-field-readonly').addClass('oc-field-editable').show();
                $inp.siblings('.oc-stat-display').hide();
            });

            applyActiveMembershipSensitiveLockState($card);

            $editBtn.hide();
            $saveBtn.show();
            $cancelBtn.show();
            
            // ❌ Notificare eliminată - doar activează editarea fără mesaj
            
            // 🎯 Scroll automat la formular expandat (cu offset 15px)
            scrollToElement($card, 15);
        });

        // Pending cards: opening details enables edit mode automatically.
        $(document).on('click', '.oc-btn-info', function() {
            const $summary = $(this).closest('.oc-card-summary');
            const $adminCard = $summary.closest('.oc-admin-card');
            if (!$adminCard.length) {
                return;
            }

            const status = String($adminCard.data('status') || '').trim().toLowerCase();
            if (status !== 'pending') {
                return;
            }

            const userId = String($adminCard.data('user-id') || '').trim();
            if (!userId) {
                return;
            }

            setTimeout(function() {
                const $cardDetails = $(`#card-details-${userId}`);
                if (!$cardDetails.length || !$cardDetails.is(':visible')) {
                    return;
                }
                const $saveBtn = $cardDetails.find('.oc-btn-save-member').first();
                if ($saveBtn.is(':visible')) {
                    return;
                }
                const $editBtn = $cardDetails.find('.oc-edit-btn').first();
                if ($editBtn.length) {
                    $editBtn.trigger('click');
                }
            }, 140);
        });

        $(document).on('change', 'input[name="no_expiry"]', function() {
            const $scope = $(this).closest('.oc-package-date-fields, .oc-package-section, .oc-card-details, .oc-renew-form, #oc-new-client-form');
            syncNoExpiryState($scope);

            // Dacă revine pe "cu expirare", recalculează automat din data achiziționării
            if (!$(this).is(':checked')) {
                const $createdAt = $scope.find('input[name="created_at"]').first();
                if ($createdAt.length) {
                    $createdAt.trigger('change');
                }
            }
        });

        $(document).on('input change', 'input[name="package_product_price"].oc-package-meta-field, select[name="package_payment_method"].oc-package-meta-field', function() {
            const $packageSection = $(this).closest('.oc-package-section');
            if (!$packageSection.length) {
                return;
            }

            syncGatewayPackageUi($packageSection);
        });
        
        // Buton "Abonament Nou" - afișează formular adăugare abonament
        $(document).on('click', '.oc-btn-add-subscription', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const userId = $(this).data('user-id');
            const $renewContainer = $(`#oc-renew-container-${userId}`);
            const $btn = $(this);

            closeAddClientForm();
            closeVisibleRenewForms(userId);
            
            
            // Toggle formular
            if ($renewContainer.is(':visible')) {
                $renewContainer.stop(true, true).slideUp(300);
                syncAddSubscriptionButtonState(userId, false);
                // ❌ Notificare eliminată - doar ascunde formularul fără mesaj
            } else {
                makeRenewFormEditable($renewContainer);
                $renewContainer.stop(true, true).slideDown(300, function() {
                    // 🎯 Scroll automat la formular după deschidere (cu offset 15px)
                    scrollToElement($renewContainer, 15);
                });
                syncAddSubscriptionButtonState(userId, true);
                // ❌ Notificare eliminată - doar afișează formularul fără mesaj
            }
        });
        
        // Buton anulare în formular
        $(document).on('click', '.oc-btn-cancel-renew', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            
            // Metodă 1: Caută prin closest form
            let $form = $(this).closest('.oc-renew-form');
            let userId = $form.data('user-id');
            
            
            // Metodă 2 (BACKUP): Caută prin parents() în loc de closest() pentru container
            if (!userId) {
                const $container = $(this).parents('.oc-renew-container');
                const containerId = $container.attr('id'); // ex: "oc-renew-container-43"
                
                
                if (containerId) {
                    userId = containerId.replace('oc-renew-container-', '');
                }
            }
            
            // Metodă 3 (LAST RESORT): Caută ID în atributul butonului de "Abonament Nou" vizibil
            if (!userId) {
                const $parentCard = $(this).closest('.oc-admin-card-expanded');
                const $addBtn = $parentCard.find('.oc-btn-add-subscription');
                userId = $addBtn.data('user-id');
            }
            
            
            if (!userId) {
                console.error('[Cancel Renew] ERROR: Could not determine user ID!');
                showNotification('❌ Eroare: Nu am putut determina ID-ul utilizatorului', 'error');
                return;
            }
            
            const $renewContainer = $(`#oc-renew-container-${userId}`);
            
            const $addBtn = $(`.oc-btn-add-subscription[data-user-id="${userId}"]`);
            
            if ($renewContainer.length > 0) {
                $renewContainer.slideUp(300);
                $addBtn.removeClass('oc-btn-warning').addClass('oc-btn-success');
                
                // Reset formular
                $form = $renewContainer.find('.oc-renew-form');
                if ($form[0]) {
                    $form[0].reset();
                }
                $form.find('.oc-renew-courses-container').hide();
                $form.find('.oc-renew-course-selections').html('');
                
                showNotification('↺ Formular anulat', 'info');
            } else {
                console.error('[Cancel Renew] ERROR: Container not found! ID:', userId);
                showNotification('❌ Eroare: Nu am găsit formularul de anulat', 'error');
            }
        });

        function askGatewayReplaceConfirmationModal(responseData) {
            return new Promise((resolve) => {
                const pendingCount = parseInt(responseData?.pending_count || 0, 10) || 0;
                const activeCount = parseInt(responseData?.active_count || 0, 10) || 0;
                const existingCount = parseInt(responseData?.existing_count || 0, 10) || 0;
                const replaceLabel = String(responseData?.replace_label || 'abonamente active/pending existente').trim();

                const details = [
                    `Total de inlocuit: ${existingCount}`,
                    `Active: ${activeCount}`,
                    `Pending: ${pendingCount}`
                ].join(' | ');

                const modalHtml = `
                    <div class="oc-inline-confirm-backdrop" id="oc-renew-replace-confirm-modal">
                        <div class="oc-inline-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="oc-renew-replace-confirm-title">
                            <button type="button" class="oc-inline-confirm-close" data-choice="cancel" aria-label="Inchide dialogul">
                                <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                            </button>
                            <div class="oc-inline-confirm-badge oc-inline-confirm-badge-accent" aria-hidden="true">
                                <span class="dashicons dashicons-warning"></span>
                            </div>
                            <div class="oc-inline-confirm-header">
                                <div class="oc-inline-confirm-kicker">Confirmare necesara</div>
                                <h3 id="oc-renew-replace-confirm-title">Inlocuiesti abonamentele existente?</h3>
                            </div>
                            <div class="oc-inline-confirm-body">
                                <p>Au fost detectate ${replaceLabel}. Continuarea va inlocui aceste abonamente cu abonamentul nou.</p>
                                <p><strong>${details}</strong></p>
                            </div>
                            <div class="oc-inline-confirm-actions">
                                <button type="button" class="button button-primary" data-choice="confirm">DA, continua</button>
                                <button type="button" class="button" data-choice="cancel">NU, anuleaza</button>
                            </div>
                        </div>
                    </div>`;

                const $modal = $(modalHtml);
                $('body').append($modal);

                const closeWith = (value) => {
                    $modal.remove();
                    resolve(value);
                };

                $modal.on('click', '[data-choice="confirm"]', function() {
                    closeWith(true);
                });

                $modal.on('click', '[data-choice="cancel"]', function() {
                    closeWith(false);
                });

                $modal.on('click', function(evt) {
                    if (evt.target === $modal.get(0)) {
                        closeWith(false);
                    }
                });
            });
        }

        $(document).on('click', '.oc-btn-submit-renew', async function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            
            const $btn = $(this);
            
            // Metodă 1: Caută form prin closest
            let $form = $btn.closest('.oc-renew-form');
            
            // Metodă 2 (BACKUP): Caută form prin parents() în loc de closest()
            if ($form.length === 0) {
                $form = $btn.parents('.oc-renew-form');
            }
            
            // Metodă 3 (LAST RESORT): Caută în container-ul renew
            if ($form.length === 0) {
                const $renewContainer = $btn.closest('.oc-renew-subscription-form');
                $form = $renewContainer.find('.oc-renew-form');
            }
            
            const userId = $form.data('user-id');
            const $packageSelect = $form.find('[name="package_id"]');

            const selectedPackageId = parseInt($packageSelect.val(), 10);
            if (isNaN(selectedPackageId) || selectedPackageId <= 0) {
                $packageSelect.addClass('oc-field-error-highlight').trigger('focus');
                showNotification('❌ Selectează un abonament înainte de adăugare', 'error');
                return;
            }
            
            
            // Colectează cursurile selectate
            const selectedCourses = [];
            $form.find('input[name="course_selections[]"]:checked, input.oc-pool-radio-input:checked').each(function() {
                selectedCourses.push(parseInt($(this).val()));
            });

            // Validare per-grup (suport dual mode cu 2 carduri separate)
            const groupValidationError = validatePoolGroupSelections($form);
            if (groupValidationError) {
                console.error('[Renew Submit] Group validation failed:', groupValidationError);
                showNotification(groupValidationError, 'error');
                return;
            }
            
            // Pregătește date (EXACT ca la creare client nou!)
            // IMPORTANT: păstrează user_id textual pentru guest_* și folosește real_user_id când există
            let normalizedUserId = userId;
            let normalizedRealUserId = 0;
            
            // Obține ID-ul real din card
            const $cardDetails = $form.closest('.oc-card-details');
            const $card = $cardDetails.prev('.oc-admin-card');
            const realUserId = $card.data('real-user-id');
            
            if (realUserId !== undefined && realUserId !== null && realUserId !== '') {
                const parsedRealUserId = parseInt(realUserId, 10);
                if (!isNaN(parsedRealUserId) && parsedRealUserId > 0) {
                    normalizedRealUserId = parsedRealUserId;
                    normalizedUserId = parsedRealUserId;
                }
            } else if (typeof userId === 'string') {
                const trimmedUserId = userId.trim();
                if (trimmedUserId !== '') {
                    normalizedUserId = trimmedUserId;
                }
            } else {
                const parsedUserId = parseInt(userId, 10);
                normalizedUserId = isNaN(parsedUserId) ? 0 : parsedUserId;
            }
            
            const productPriceEditableRaw = $form.find('[name="product_price"]').first().val();
            const productPriceRaw = parseFloat(String(productPriceEditableRaw || '0').replace(',', '.'));
            const productPrice = Number.isFinite(productPriceRaw) ? Math.max(0, productPriceRaw) : 0;

            const formData = {
                user_id: normalizedUserId,
                real_user_id: normalizedRealUserId,
                package_id: selectedPackageId,
                course_selections: selectedCourses,
                activation_date: getIsoDateFieldValue($form.find('[name="activation_date"]')),
                expiration_date: getIsoDateFieldValue($form.find('[name="expiration_date"]')),
                payment_status: $form.find('[name="payment_status"]').val(),
                payment_method: $form.find('[name="payment_method"]').val(),
                product_price: productPrice,
                send_email: $form.find('[name="send_email"]').is(':checked') ? 1 : 0
            };

            if (formData.activation_date === null) {
                showNotification('❌ Data "Activ de la" este invalidă pentru formatul setat în WordPress.', 'error');
                return;
            }

            if (formData.expiration_date === null) {
                showNotification('❌ Data "Expiră la" este invalidă pentru formatul setat în WordPress.', 'error');
                return;
            }
            
            
            const defaultSubmitLabel = '✅ Creează Abonament Nou + Comandă WooCommerce';
            $btn.prop('disabled', true).html('⏳ Se creează comanda...');

            async function submitRenewRequest(confirmReplaceExisting) {
                const payloadData = {
                    ...formData,
                    confirm_gateway_replace_existing: confirmReplaceExisting ? 1 : 0
                };

                const response = await $.post(config.ajaxUrl, {
                    action: 'oc_renew_subscription',
                    nonce: config.nonce,
                    data: payloadData
                });

                return response;
            }

            try {
                let response = await submitRenewRequest(false);

                if (!response || !response.success) {
                    const requiresConfirmation = Boolean(response?.data?.requires_confirmation);

                    if (requiresConfirmation) {
                        const confirmed = await askGatewayReplaceConfirmationModal(response.data || {});

                        if (confirmed) {
                            response = await submitRenewRequest(true);
                        } else {
                            showNotification('ℹ️ Reînnoirea a fost anulată.', 'info');
                            $btn.prop('disabled', false).html(defaultSubmitLabel);
                            return;
                        }
                    }
                }

                if (response && response.success) {
                    showNotification('✅ ' + response.data.message, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    const errorMessage = response?.data?.message || 'Nu s-a putut crea abonamentul.';
                    showNotification('❌ ' + errorMessage, 'error');
                    $btn.prop('disabled', false).html(defaultSubmitLabel);
                }
            } catch (err) {
                showNotification('❌ Eroare de comunicare cu serverul', 'error');
                $btn.prop('disabled', false).html(defaultSubmitLabel);
            }
        });
        
        // Dropdown cascade pentru formular reînnoire (IDENTIC cu creare client nou!)
        $(document).on('change', '.oc-renew-package-select', function() {
            const packageId = $(this).val();
            const $select = $(this);
            const $renewContainer = $(this).closest('.oc-renew-form');
            const $coursesContainer = $renewContainer.find('.oc-renew-courses-container');
            const $coursesGrid = $renewContainer.find('.oc-renew-course-selections');

            $select.removeClass('oc-field-error-highlight');
            applyPackageMetaToForm($renewContainer, $select);
            
            
            if (!packageId) {
                $coursesContainer.hide();
                $coursesGrid.removeClass('oc-has-pool-groups');
                return;
            }
            
            if ($coursesGrid.length === 0) {
                console.error('[Renew Package] ERROR: Grid element not found!');
                return;
            }
            
            $coursesGrid.html('<p style="text-align: center; padding: 20px;">⏳ Se încarcă cursurile...</p>');
            $coursesContainer.show();
            
            
            // FOLOSEȘTE ACEEAȘI funcție AJAX ca la creare client nou!
            $.post(config.ajaxUrl, {
                action: 'oc_get_package_courses',
                nonce: config.nonce,
                package_id: packageId
            }, function(response) {
                
                if (response.success && response.data.html) {
                    $coursesGrid.html(response.data.html);
                    const userId = $renewContainer.data('user-id');
                    applyLoadedCoursesLayout($coursesGrid, userId || 'renew');
                    // ❌ Notificare eliminată - încarcă cursurile fără mesaj
                } else {
                    console.error('[Renew Package] No HTML in response or error');
                    $coursesGrid.removeClass('oc-has-pool-groups');
                    $coursesGrid.html('<p style="color: #d63638;">❌ Nu s-au găsit cursuri pentru acest pachet.</p>');
                }
            }).fail(function(jqXHR, textStatus, errorThrown) {
                console.error('[Renew Package] AJAX Failed:', textStatus, errorThrown);
                $coursesGrid.removeClass('oc-has-pool-groups');
                $coursesGrid.html('<p style="color: #d63638;">❌ Eroare la încărcarea cursurilor.</p>');
            });
        });
    }

    function normalizeGatewayPaymentMethod(value) {
        return String(value || '')
            .trim()
            .toLowerCase()
            .replace(/[\s_-]+/g, '');
    }

    function isGatewayUnlimitedMethod(value) {
        const normalized = normalizeGatewayPaymentMethod(value);
        return normalized === '7card'
            || normalized === 'oc7card'
            || normalized === 'esx'
            || normalized === 'ocesx'
            || normalized.indexOf('7card') !== -1
            || normalized.indexOf('esx') !== -1;
    }

    function syncGatewayPackageUi($packageSection) {
        if (!$packageSection || !$packageSection.length) {
            return;
        }

        const $priceField = $packageSection.find('input[name="package_product_price"].oc-package-meta-field').first();
        const $paymentField = $packageSection.find('select[name="package_payment_method"].oc-package-meta-field').first();
        const $noExpiryField = $packageSection.find('input[name="no_expiry"].oc-package-date-field').first();

        if (!$priceField.length || !$paymentField.length) {
            return;
        }

        const priceValue = parseFloat(String($priceField.val() || '0').replace(',', '.'));
        const normalizedPrice = Number.isFinite(priceValue) ? priceValue : 0;
        const paymentMethod = String($paymentField.val() || '');

        const isGatewayMethod = isGatewayUnlimitedMethod(paymentMethod);
        if (!isGatewayMethod) {
            return;
        }

        const shouldBeUnlimited = isGatewayMethod && normalizedPrice <= 0;

        $packageSection.find('.oc-course-entry').each(function() {
            const $courseEntry = $(this);
            const $allocated = $courseEntry.find('.oc-course-session-input[data-field-name="sessions_allocated"]').first();
            const $remaining = $courseEntry.find('.oc-course-session-input[data-field-name="remaining_sessions"]').first();
            const $used = $courseEntry.find('.oc-course-session-input[data-field-name="used_sessions"]').first();

            if (!$allocated.length || !$remaining.length) {
                return;
            }

            const defaultAllocated = Math.max(0, parseInt($courseEntry.attr('data-default-allocated'), 10) || 0);
            const unlimitedValue = Math.max(0, parseInt($courseEntry.attr('data-unlimited-value'), 10) || 0);
            const usedValue = Math.max(0, parseInt($used.val(), 10) || 0);

            if (shouldBeUnlimited) {
                $allocated.val(unlimitedValue);
                $remaining.val(Math.max(0, unlimitedValue - usedValue));
            } else {
                $allocated.val(defaultAllocated);
                $remaining.val(Math.max(0, defaultAllocated - usedValue));
            }
        });

        if ($noExpiryField.length) {
            const shouldCheckNoExpiry = shouldBeUnlimited;
            if ($noExpiryField.is(':checked') !== shouldCheckNoExpiry) {
                $noExpiryField.prop('checked', shouldCheckNoExpiry).trigger('change');
            }
        }
    }

    function syncNoExpiryState($card) {
        if (!$card || !$card.length) {
            return;
        }

        const $noExpiry = $card.find('input[name="no_expiry"]');
        const $expiration = $card.find('input[name="expiration_date"]');

        if (!$noExpiry.length || !$expiration.length) {
            return;
        }

        if ($noExpiry.is(':checked')) {
            $expiration.val('');
            $expiration.prop('disabled', true).addClass('oc-field-readonly');
        } else {
            $expiration.prop('disabled', false).removeClass('oc-field-readonly');
        }
    }
    
    /**
     * Initialize Create Order buttons (pentru guest users)
     */
    function initCreateOrderButtons() {
        $(document).on('click', '.oc-btn-create-order', function() {
            const $btn = $(this);
            const membershipId = $btn.data('membership-id');
            
            if (!membershipId) {
                showNotification('❌ Membership ID lipsă', 'error');
                return;
            }
            
            if (!confirm('Creezi o comandă WooCommerce nouă pentru acest guest user?')) {
                return;
            }
            
            $btn.prop('disabled', true).html('⏳ Se creează comanda...');
            
            $.post(config.ajaxUrl, {
                action: 'oc_create_woo_order_guest',
                nonce: config.nonce,
                membership_id: membershipId
            }, function(response) {
                if (response.success) {
                    showNotification('✅ ' + response.data.message, 'success');
                    if (response.data.order_url) {
                        showNotification('🔗 <a href="' + response.data.order_url + '" target="_blank">Vezi comanda #' + response.data.order_id + '</a>', 'info');
                    }
                    setTimeout(function() { location.reload(); }, 2000);
                } else {
                    showNotification('❌ ' + response.data.message, 'error');
                    $btn.prop('disabled', false).html('🛒 Creează Comandă WooCommerce');
                }
            }).fail(function() {
                showNotification('❌ Eroare de comunicare cu serverul', 'error');
                $btn.prop('disabled', false).html('🛒 Creează Comandă WooCommerce');
            });
        });
    }
    
    /**
     * Initialize Cancel Membership buttons - ANULARE ABONAMENT
     */
    function initCancelMembershipButtons() {
        $(document).on('click', '.oc-btn-cancel-membership', function() {
            const $btn = $(this);
            const orderId = $btn.data('order-id');
            
            if (!orderId) {
                showNotification('❌ Order ID lipsă', 'error');
                return;
            }
            
            // Confirmație dublă pentru a preveni anulări accidentale
            if (!confirm('⚠️ ATENȚIE!\n\nAnularea pachetului va:\n• Anula comanda WooCommerce (#' + orderId + ')\n• Marca TOATE cursurile din acest pachet ca EXPIRATE\n\nAceastă acțiune NU poate fi anulată!\n\nContinuați?')) {
                return;
            }
            
            $btn.prop('disabled', true).html('⏳ Se anulează...');
            
            $.post(config.ajaxUrl, {
                action: 'oc_cancel_membership',
                nonce: config.nonce,
                order_id: orderId
            }, function(response) {
                if (response.success) {
                    showNotification('✅ ' + response.data.message, 'success');
                    
                    // 🎯 ANIMAȚIE FEEDBACK VIZUAL INSTANT
                    const $packageCard = $('.oc-package-section[data-order-id="' + orderId + '"]');
                    
                    if ($packageCard.length) {
                        // Animație fade-out pentru card-ul mare
                        $packageCard.css({
                            'opacity': '0',
                            'transform': 'translateY(-20px)',
                            'pointer-events': 'none'
                        });
                        
                        setTimeout(function() {
                            $packageCard.slideUp(400, function() {
                                $(this).remove();
                            });
                        }, 300);
                    }
                    
                    // 🔄 RELOAD pagină după 1 secundă pentru sincronizare completă
                    // Card-ul sumar din listă trebuie recalculat cu datele actualizate
                    setTimeout(function() {
                        location.reload();
                    }, 1000)
                } else {
                    showNotification('❌ ' + response.data.message, 'error');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Anulează Acest Pachet');
                }
            }).fail(function() {
                showNotification('❌ Eroare de comunicare cu serverul', 'error');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Anulează Acest Pachet');
            });
        });
    }

    /**
     * Initialize Validation History buttons - istoric validări per pachet
     */
    function initValidationHistoryButtons() {
        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function renderHistoryRows($panel, events) {
            const $list = $panel.find('.oc-validation-history-list').first();
            if (!$list.length) return;

            if (!Array.isArray(events) || events.length === 0) {
                if ($list.children().length === 0) {
                    $list.html('<div class="oc-validation-history-empty">Nu există evenimente înregistrate.</div>');
                }
                return;
            }

            let html = '';
            events.forEach(function(eventRow) {
                const eventType = String(eventRow.event_type || 'scan_valid');
                const eventLabel = escapeHtml(eventRow.event_label || 'Scan valid');
                const course = escapeHtml(eventRow.course || 'N/A');
                const validatedAt = escapeHtml(eventRow.validated_at_display || 'N/A');
                const errorMsg = escapeHtml(eventRow.error || '');

                html += '<div class="oc-validation-history-item">';
                html += '<div class="oc-validation-history-main">';
                html += '<span class="oc-validation-history-badge oc-history-' + eventType + '">' + eventLabel + '</span>';
                html += '<span class="oc-validation-history-course">' + course + '</span>';
                html += '</div>';
                html += '<div class="oc-validation-history-meta">' + validatedAt + '</div>';
                if (errorMsg) {
                    html += '<div class="oc-validation-history-error">' + errorMsg + '</div>';
                }
                html += '</div>';
            });

            $list.append(html);
        }

        function loadHistoryPage($panel, page) {
            const membershipId = parseInt($panel.data('membership-id'), 10) || 0;
            const orderId = parseInt($panel.data('order-id'), 10) || 0;
            const $loadMore = $panel.find('.oc-btn-history-load-more').first();
            const $list = $panel.find('.oc-validation-history-list').first();

            if (!membershipId && !orderId) {
                showNotification('❌ Nu există membership/order pentru istoric.', 'error');
                return;
            }

            if (!$list.length) {
                return;
            }

            if (page === 1) {
                $list.html('<div class="oc-validation-history-loading">Se încarcă istoricul...</div>');
            }

            $loadMore.prop('disabled', true).text('Se încarcă...');

            $.post(config.ajaxUrl, {
                action: 'oc_get_membership_validation_history',
                nonce: config.nonce,
                membership_id: membershipId,
                order_id: orderId,
                page: page,
                per_page: 20
            }).done(function(response) {
                if (!response || !response.success) {
                    const message = (response && response.data && response.data.message) ? response.data.message : 'Nu s-a putut încărca istoricul.';
                    showNotification('❌ ' + message, 'error');
                    if (page === 1) {
                        $list.html('<div class="oc-validation-history-empty">Nu s-a putut încărca istoricul.</div>');
                    }
                    return;
                }

                if (page === 1) {
                    $list.empty();
                }

                const events = (response.data && Array.isArray(response.data.events)) ? response.data.events : [];
                renderHistoryRows($panel, events);

                const hasMore = !!(response.data && response.data.has_more);
                if (hasMore) {
                    $loadMore.show().attr('data-page', String(page + 1));
                } else {
                    $loadMore.hide();
                }
            }).fail(function() {
                showNotification('❌ Eroare de comunicare la încărcarea istoricului.', 'error');
                if (page === 1) {
                    $list.html('<div class="oc-validation-history-empty">Nu s-a putut încărca istoricul.</div>');
                }
            }).always(function() {
                $loadMore.prop('disabled', false).text('Încarcă mai mult');
            });
        }

        $(document).on('click', '.oc-btn-validation-history', function() {
            const $btn = $(this);
            const $package = $btn.closest('.oc-package-section');
            const $panel = $package.find('.oc-validation-history-panel').first();

            if (!$panel.length) {
                showNotification('❌ Panoul de istoric nu este disponibil.', 'error');
                return;
            }

            const isVisible = $panel.is(':visible');
            if (isVisible) {
                $panel.slideUp(180);
                return;
            }

            $panel.slideDown(180);
            if (String($panel.attr('data-history-preloaded') || '') === '1') {
                return;
            }
            $panel.find('.oc-btn-history-load-more').attr('data-page', '1').hide();
            loadHistoryPage($panel, 1);
        });

        $(document).on('click', '.oc-btn-history-load-more', function() {
            const $btn = $(this);
            const $panel = $btn.closest('.oc-validation-history-panel');
            const nextPage = parseInt($btn.attr('data-page') || '2', 10) || 2;
            loadHistoryPage($panel, nextPage);
        });
    }
    
    /**
     * Initialize Save buttons
     */
    function initSaveButtons() {
        $(document).on('click', '.oc-btn-save-member', function() {
            const $btn = $(this);
            const $card = $btn.closest('.oc-card-details');
            const userId = $btn.data('user-id');
            const membershipId = $btn.data('membership-id');
            const orderId = $btn.data('order-id');
            
            $btn.prop('disabled', true).html('⏳ Se salvează...');
            
            // Collect form data (exclude course session inputs — handled separately)
            const formData = {};
            $card.find('.oc-field-editable').not('.oc-course-session-input').not('.oc-package-date-field').not('.oc-package-meta-field').each(function() {
                const $field = $(this);
                const fieldName = $field.attr('name');
                
                if ($field.is(':checkbox')) {
                    formData[fieldName] = $field.is(':checked') ? 1 : 0;
                } else {
                    formData[fieldName] = $field.val();
                }
            });

            // Colectează editările individuale per curs grupate pe membership_id
            const csGroups = {};
            $card.find('.oc-course-session-input.oc-field-editable').each(function() {
                const $inp = $(this);
                const mid = String($inp.data('membership-id'));
                const fieldName = $inp.data('field-name');
                if (!csGroups[mid]) {
                    csGroups[mid] = { id: parseInt(mid, 10) };
                }
                csGroups[mid][fieldName] = parseInt($inp.val(), 10) || 0;
            });
            const courseSessionUpdates = Object.values(csGroups);

            const packageDateUpdates = [];
            const packageMetaUpdates = [];
            let packageDatesInvalid = false;
            let packageMetaInvalid = false;

            $card.find('.oc-package-section').each(function() {
                if (packageDatesInvalid || packageMetaInvalid) {
                    return;
                }

                const $package = $(this);
                const orderIdRaw = parseInt($package.data('order-id'), 10);
                const membershipIdRaw = parseInt($package.find('.oc-package-date-fields').data('membership-id'), 10);
                const $createdField = $package.find('input[name="created_at"].oc-package-date-field').first();
                const $expirationField = $package.find('input[name="expiration_date"].oc-package-date-field').first();
                const $noExpiryField = $package.find('input[name="no_expiry"].oc-package-date-field').first();

                if (!$createdField.length && !$expirationField.length && !$noExpiryField.length) {
                    return;
                }

                const createdIso = getIsoDateFieldValue($createdField);
                if (createdIso === null) {
                    packageDatesInvalid = true;
                    showFieldErrorForEdit($card, 'Format invalid pentru data achiziționării în unul dintre abonamente.');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Salvează');
                    $createdField.focus();
                    return;
                }

                const noExpiry = $noExpiryField.length ? ($noExpiryField.is(':checked') ? 1 : 0) : 0;
                let expirationIso = '';

                if (noExpiry !== 1) {
                    expirationIso = getIsoDateFieldValue($expirationField);
                    if (expirationIso === null) {
                        packageDatesInvalid = true;
                        showFieldErrorForEdit($card, 'Format invalid pentru data de expirare în unul dintre abonamente.');
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Salvează');
                        $expirationField.focus();
                        return;
                    }
                }

                packageDateUpdates.push({
                    order_id: Number.isFinite(orderIdRaw) ? orderIdRaw : 0,
                    membership_id: Number.isFinite(membershipIdRaw) ? membershipIdRaw : 0,
                    created_at: createdIso,
                    expiration_date: noExpiry === 1 ? '' : expirationIso,
                    no_expiry: noExpiry
                });

                const $packagePriceField = $package.find('input[name="package_product_price"].oc-package-meta-field').first();
                const $packagePaymentField = $package.find('select[name="package_payment_method"].oc-package-meta-field').first();
                const $packagePaymentStatusField = $package.find('select[name="package_payment_status"].oc-package-meta-field').first();
                const $packageObservationsField = $package.find('textarea[name="package_observations"].oc-package-meta-field').first();

                if ($packagePriceField.length || $packagePaymentField.length || $packagePaymentStatusField.length || $packageObservationsField.length) {
                    const packagePriceRaw = $packagePriceField.length ? parseFloat($packagePriceField.val()) : 0;
                    if ($packagePriceField.length && (!Number.isFinite(packagePriceRaw) || packagePriceRaw < 0)) {
                        packageMetaInvalid = true;
                        showFieldErrorForEdit($card, 'Preț invalid pentru unul dintre abonamente.');
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Salvează');
                        $packagePriceField.focus();
                        return;
                    }

                    packageMetaUpdates.push({
                        order_id: Number.isFinite(orderIdRaw) ? orderIdRaw : 0,
                        membership_id: Number.isFinite(membershipIdRaw) ? membershipIdRaw : 0,
                        product_price: $packagePriceField.length ? packagePriceRaw : null,
                        payment_method: $packagePaymentField.length ? String($packagePaymentField.val() || '').trim() : '',
                        payment_status: $packagePaymentStatusField.length ? String($packagePaymentStatusField.val() || '').trim() : '',
                        observations: $packageObservationsField.length ? String($packageObservationsField.val() || '').trim() : ''
                    });
                }
            });

            if (packageDatesInvalid) {
                return;
            }

            if (packageMetaInvalid) {
                return;
            }

            const $expirationField = $card.find('input[name="expiration_date"]')
                .not('.oc-package-date-field')
                .filter(function() {
                    return $(this).closest('.oc-renew-container').length === 0;
                })
                .first();
            const $noExpiryField = $card.find('input[name="no_expiry"]')
                .not('.oc-package-date-field')
                .filter(function() {
                    return $(this).closest('.oc-renew-container').length === 0;
                })
                .first();
            if ($noExpiryField.length) {
                formData.no_expiry = $noExpiryField.is(':checked') ? 1 : 0;
            }
            if ($expirationField.length) {
                const expirationIso = getIsoDateFieldValue($expirationField);

                if (formData.no_expiry !== 1 && expirationIso === null) {
                    showFieldErrorForEdit($card, 'Format invalid pentru data de expirare.');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Salvează');
                    $expirationField.focus();
                    return;
                }

                formData.expiration_date = formData.no_expiry === 1 ? '' : expirationIso;
            }
            
            // AJAX Save
            $.post(config.ajaxUrl, {
                action: 'oc_save_member_data',
                nonce: config.nonce,
                user_id: userId,
                membership_id: membershipId,
                order_id: orderId,
                data: formData,
                course_sessions: JSON.stringify(courseSessionUpdates),
                package_date_updates: JSON.stringify(packageDateUpdates),
                package_meta_updates: JSON.stringify(packageMetaUpdates)
            })
            .done(function(response) {
                if (response.success) {
                    showNotification('✅ ' + response.data.message, 'success');
                    
                    // Update original values
                    $card.find('.oc-field-editable').each(function() {
                        if ($(this).is(':checkbox')) {
                            $(this).attr('data-original-value', $(this).is(':checked') ? '1' : '0');
                        } else {
                            $(this).attr('data-original-value', $(this).val());
                        }
                    });
                    
                    // Disable edit mode
                    resetEditMode($card);
                    
                    // Reload page after delay
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    // Afișează eroarea sub câmpul corespunzător
                    showFieldErrorForEdit($card, response.data.message);
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Salvează');
                }
            })
            .fail(function() {
                showNotification('❌ Eroare de conexiune', 'error');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Salvează');
            });
        });
    }
    
    /**
     * Initialize Cancel buttons
     */
    function initCancelButtons() {
        $(document).on('click', '.oc-btn-cancel-edit', function() {
            const userId = $(this).data('user-id');
            let $card = $(this).closest('.oc-card-details');
            if (!$card.length && userId !== undefined) {
                $card = $(`#card-details-${userId}`);
            }
            if (!$card.length) {
                return;
            }
            
            // Restore original values
            $card.find('.oc-field-editable').each(function() {
                const originalValue = $(this).attr('data-original-value');
                if ($(this).is(':checkbox')) {
                    $(this).prop('checked', originalValue == '1');
                } else {
                    $(this).val(originalValue);
                }
            });
            
            resetEditMode($card);
            // ❌ Notificare eliminată - doar anulează editarea fără mesaj
        });
    }
    
    /**
     * Reset edit mode for a card
     */
    function resetEditMode($card) {
        const $inputs = $card.find('[data-field-type], input[name="no_expiry"], .oc-preserve-expiry-checkbox')
            .not('.oc-renew-container [data-field-type]')
            .not('.oc-renew-container input')
            .not('.oc-renew-container select');
        const $editBtn = $card.find('.oc-edit-btn');
        const $saveBtn = $card.find('.oc-btn-save-member');
        const $cancelBtn = $card.find('.oc-btn-cancel-edit');
        
        
        $inputs.prop('disabled', true);
        $inputs.removeClass('oc-field-editable').addClass('oc-field-readonly');
        $editBtn.html('<span class="dashicons dashicons-edit"></span> Edit').removeClass('oc-btn-warning oc-btn-danger').addClass('oc-btn-secondary').show(); // ← ADĂUGAT .show()
        $saveBtn.hide();
        $cancelBtn.hide();

        // Restaurează inputurile de ședințe per curs: ascunde inputs, reafișează valorile statice
        $card.find('.oc-course-session-input').each(function() {
            const $inp = $(this);
            const orig = $inp.attr('data-original-value');
            if (orig !== undefined) {
                $inp.val(orig);
            }
            $inp.prop('disabled', true).removeClass('oc-field-editable').addClass('oc-field-readonly').hide();
            $inp.siblings('.oc-stat-display').show();
        });
        
        // Ascunde butonul "Adaugă Curs" și formularul dacă există
        $card.find('.oc-add-course-btn-container').hide();
        $card.find('.oc-add-course-form-container').remove();

        applyActiveMembershipSensitiveLockState($card);
    }

    /**
     * Makes radio input names unique per form context to prevent cross-form interference.
     * PHP generates static names like "course_group_pool1"; this appends a context suffix.
     *
     * @param {jQuery} $container  The injected HTML container.
     * @param {string} contextId   A unique string (e.g. user ID or 'new').
     */
    function uniquifyRadioNames($container, contextId) {
        const nameMap = {};
        $container.find('input[type="radio"].oc-pool-radio-input').each(function() {
            const origName = $(this).attr('name');
            if (!nameMap[origName]) {
                nameMap[origName] = origName + '_ctx_' + contextId;
            }
            $(this).attr('name', nameMap[origName]);
        });
    }

    /**
     * Applies common post-load transformations for injected course markup.
     *
     * @param {jQuery} $container
     * @param {string} contextId
     */
    function applyLoadedCoursesLayout($container, contextId) {
        uniquifyRadioNames($container, contextId);
        cleanupOrphanPoolRadios($container);

        const hasGroups = $container.find('.oc-course-pool-group').length > 0;
        $container.toggleClass('oc-has-pool-groups', hasGroups);
    }

    /**
     * Applies selected package metadata (price + duration) to target form fields.
     *
     * @param {jQuery} $scope
     * @param {jQuery} $select
     */
    function applyPackageMetaToForm($scope, $select) {
        const $selected = $select.find(':selected');

        const rawPrice = $selected.data('price');
        const parsedPrice = parseFloat(String(rawPrice ?? 0).replace(',', '.'));
        const price = Number.isFinite(parsedPrice) ? parsedPrice : 0;

        const durationDays = Math.max(1, parseInt($selected.data('duration-days') || 28, 10) || 28);

        // New client: editable price
        $scope.find('#new-client-price, input[name="product_price"]').val(price.toFixed(2));

        // Renew: editable price
        $scope.find('.oc-renew-price-input, input[name="product_price"]').val(price.toFixed(2));

        // Keep package duration attached to expiration input for auto-calculation
        const $expiration = $scope.find('input[name="expiration_date"]');
        $expiration.attr('data-duration-days', durationDays);

        // Recalculate expiration from current activation date, if available
        const $activation = $scope.find('input[name="activation_date"]');
        if ($activation.length) {
            $activation.trigger('change');
        }
    }

    /**
     * Removes cloned/misplaced pool radios outside the expected label wrapper.
     * Only removes duplicates that have an equivalent wrapped radio in the same group.
     *
     * @param {jQuery} $container
     */
    function cleanupOrphanPoolRadios($container) {
        const $groups = $container.find('.oc-course-pool-group');
        if ($groups.length === 0) {
            return;
        }

        const wrappedKeys = new Set();
        $groups.find('label.oc-course-radio-label input.oc-pool-radio-input').each(function() {
            const $radio = $(this);
            const key = String($radio.attr('name') || '') + '::' + String($radio.val() || '');
            wrappedKeys.add(key);
        });

        $groups.find('input.oc-pool-radio-input').each(function() {
            const $radio = $(this);
            if ($radio.closest('label.oc-course-radio-label').length > 0) {
                return;
            }

            const key = String($radio.attr('name') || '') + '::' + String($radio.val() || '');
            if (wrappedKeys.has(key)) {
                $radio.remove();
            }
        });
    }

    /**
     * Validates per-pool-group min/max selection constraints for dual-mode packages.
     * Returns an error message string if invalid, or null if valid.
     *
     * @param {jQuery} $container  The form or container holding .oc-course-pool-group elements.
     * @returns {string|null}
     */
    function validatePoolGroupSelections($container) {
        const $groups = $container.find('.oc-course-pool-group');
        if ($groups.length === 0) {
            // Legacy fallback
            const total = $container.find('input[name="course_selections[]"]:checked, input.oc-pool-radio-input:checked').length;
            if (total === 0) return '❌ Selectează cel puțin un curs';
            return null;
        }

        let error = null;
        $groups.each(function() {
            if (error) return;
            const $grp   = $(this);
            const label   = $grp.data('label') || 'Grup';
            const minSel  = parseInt($grp.data('min-selections') || 1, 10);
            const maxSel  = parseInt($grp.data('max-selections') || 0, 10);
            const uiStyle = $grp.data('ui-style') || 'checkboxes';

            let selected = 0;
            if (uiStyle === 'slots') {
                selected = $grp.find('input[type="radio"]:checked').length;
            } else {
                selected = $grp.find('input[name="course_selections[]"]:checked').length;
            }

            if (selected < minSel) {
                const minText = minSel === 1 ? '1 curs' : minSel + ' cursuri';
                error = '❌ Selectează cel puțin ' + minText + ' din "' + label + '"';
            } else if (maxSel > 0 && selected > maxSel) {
                const maxText = maxSel === 1 ? '1 curs' : maxSel + ' cursuri';
                error = '❌ Poți selecta maxim ' + maxText + ' din "' + label + '"';
            }
        });
        return error;
    }

    function makeRenewFormEditable($renewContainer) {
        if (!$renewContainer || !$renewContainer.length) {
            return;
        }

        const $renewInputs = $renewContainer.find('input, select, textarea');
        $renewInputs.prop('disabled', false);
        $renewInputs.removeClass('oc-field-readonly');
        $renewInputs.removeClass('oc-field-editable');
    }
    
    /**
     * Initialize package dropdowns (cascade)
     */
    function initPackageDropdowns() {
        // Dropdown cascade for editing existing member
        $(document).on('change', '.oc-package-select', function() {
            const packageId = $(this).val();
            const userId = $(this).data('user-id');
            const $courseSelect = $(`#course-${userId}`);
            
            if (!packageId) return;
            
            $courseSelect.html('<option value="">⏳ Se încarcă...</option>').prop('disabled', true);
            
            $.post(config.ajaxUrl, {
                action: 'oc_get_package_courses',
                nonce: config.nonce,
                package_id: packageId
            })
            .done(function(response) {
                if (response.success && response.data.variations) {
                    let options = '<option value="">-- Selectează curs --</option>';
                    response.data.variations.forEach(function(v) {
                        const courseName = v.attributes.attribute_pa_curs || 'Curs #' + v.variation_id;
                        options += '<option value="' + v.variation_id + '">' + courseName + '</option>';
                    });
                    $courseSelect.html(options).prop('disabled', false);
                }
            })
            .fail(function() {
                $courseSelect.html('<option value="">Eroare</option>');
            });
        });
    }
    
    /**
     * Initialize Add Course button
     */
    function initAddCourseButton() {
        $(document).on('click', '.oc-btn-add-course', function() {
            const userId = $(this).data('user-id');
            const $btn = $(this);
            const $card = $(`#card-details-${userId}`);
            
            // Verifică dacă formularul există deja
            if ($card.find('.oc-add-course-form-container').length > 0) {
                // ❌ Notificare eliminată - doar ignoră dubla deschidere
                return;
            }
            
            // Creează formular inline pentru selectare curs
            const $form = $('<div class="oc-add-course-form-container" style="margin-top: 10px; padding: 15px; background: #fff; border: 2px dashed #0073aa; border-radius: 4px;"></div>');
            
            $form.html(`
                <h4 style="margin-top: 0;">➕ Adaugă Curs Nou</h4>
                <div style="margin-bottom: 10px;">
                    <label>Selectează curs: <span style="color: red;">*</span></label>
                    <select class="oc-new-course-select" style="width: 100%; padding: 8px;">
                        <option value="">⏳ Se încarcă cursurile disponibile...</option>
                    </select>
                </div>
                <div class="oc-sessions-info" style="margin-bottom: 10px; padding: 10px; background: #e7f3ff; border: 1px solid #b8daff; border-radius: 4px; display: none;">
                    <label style="font-weight: 600; color: #004085;">📊 Ședințe alocate automat:</label>
                    <div style="font-size: 18px; font-weight: bold; color: #0056b3; margin-top: 5px;">
                        <span class="oc-sessions-display">-</span> ședințe
                    </div>
                    <small style="color: #666; font-style: italic;">Numărul de ședințe este setat automat în funcție de cursul selectat.</small>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="button button-primary oc-confirm-add-course" data-user-id="${userId}" disabled>
                        ✅ Confirmă Adăugare
                    </button>
                    <button type="button" class="button oc-cancel-add-course">
                        ✕ Anulează
                    </button>
                </div>
            `);
            
            $btn.closest('.oc-add-course-btn-container').after($form);
            
            // Încarcă cursurile disponibile
            loadAvailableCourses($form.find('.oc-new-course-select'));
            
        });
        
        // Handler pentru anulare
        $(document).on('click', '.oc-cancel-add-course', function() {
            $(this).closest('.oc-add-course-form-container').remove();
            // ❌ Notificare eliminată - doar închide formularul fără mesaj
        });
        
        // Handler pentru confirmare adăugare - AJAX REAL
        $(document).on('click', '.oc-confirm-add-course', function() {
            const userId = $(this).data('user-id');
            const $form = $(this).closest('.oc-add-course-form-container');
            const $select = $form.find('.oc-new-course-select');
            const $btn = $(this);
            const variationId = $select.val();
            const sessions = $select.find(':selected').data('sessions');
            const courseName = $select.find(':selected').text();
            
            if (!variationId) {
                showNotification('❌ Selectează un curs', 'error');
                return;
            }
            
            if (!sessions || sessions <= 0) {
                showNotification('❌ Cursul selectat nu are ședințe mapate', 'error');
                return;
            }
            
            // Disable button și arată loading
            $btn.prop('disabled', true).html('⏳ Se adaugă cursul...');
            
            
            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'oc_add_supplementary_course',
                    nonce: config.nonce,
                    user_id: userId,
                    variation_id: variationId,
                    sessions: sessions
                },
                success: function(response) {
                    if (response.success) {
                        showNotification('✅ ' + response.data.message, 'success');
                        
                        
                        // Închide formularul
                        $form.remove();
                        
                        // Reîncarcă pagina după 1.5s pentru a afișa noul curs
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        showNotification('❌ ' + (response.data?.message || 'Eroare necunoscută'), 'error');
                        $btn.prop('disabled', false).html('✅ Confirmă Adăugare');
                        console.error('[Add Course] Error:', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    showNotification('❌ Eroare de comunicare cu serverul', 'error');
                    $btn.prop('disabled', false).html('✅ Confirmă Adăugare');
                    console.error('[Add Course] AJAX error:', {xhr, status, error});
                }
            });
        });
    }
    
    /**
     * Load available courses for adding
     * Încarcă TOATE cursurile Pool disponibile + mapare ședințe
     */
    function loadAvailableCourses($select) {
        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'oc_get_all_pool_courses',
                nonce: config.nonce
            },
            success: function(response) {
                if (response.success && response.data.courses) {
                    $select.html('<option value="">-- Selectează un curs --</option>');
                    
                    response.data.courses.forEach(function(course) {
                        $select.append(
                            `<option value="${course.variation_id}" data-sessions="${course.sessions}">
                                ${course.course_name} (${course.sessions} ședințe)
                            </option>`
                        );
                    });
                    
                    // Handler pentru schimbare curs - afișează ședințe automat
                    $select.off('change').on('change', function() {
                        const $selected = $(this).find(':selected');
                        const sessions = $selected.data('sessions');
                        const $form = $(this).closest('.oc-add-course-form-container');
                        const $sessionsInfo = $form.find('.oc-sessions-info');
                        const $confirmBtn = $form.find('.oc-confirm-add-course');
                        
                        if (sessions && sessions > 0) {
                            $sessionsInfo.find('.oc-sessions-display').text(sessions);
                            $sessionsInfo.slideDown(200);
                            $confirmBtn.prop('disabled', false);
                        } else {
                            $sessionsInfo.slideUp(200);
                            $confirmBtn.prop('disabled', true);
                        }
                    });
                    
                } else {
                    $select.html('<option value="">❌ Nu s-au găsit cursuri disponibile</option>');
                    console.error('[Load Courses] Error:', response.data?.message || 'Unknown error');
                }
            },
            error: function(xhr, status, error) {
                $select.html('<option value="">❌ Eroare încărcare cursuri</option>');
                console.error('[Load Courses] AJAX error:', error);
            }
        });
    }
    
    /**
     * Initialize New Client Form
     */
    function initNewClientForm() {
        function askActivationChoice() {
            return new Promise((resolve) => {
                const modalHtml = `
                    <div class="oc-inline-confirm-backdrop" id="oc-activation-choice-modal">
                        <div class="oc-inline-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="oc-activation-choice-title">
                            <button type="button" class="oc-inline-confirm-close" data-choice="activate-later" aria-label="Închide dialogul">
                                <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                            </button>
                            <div class="oc-inline-confirm-badge oc-inline-confirm-badge-accent" aria-hidden="true">
                                <span class="dashicons dashicons-controls-play"></span>
                            </div>
                            <div class="oc-inline-confirm-header">
                                <div class="oc-inline-confirm-kicker">Activare</div>
                                <h3 id="oc-activation-choice-title">Vrei sa activezi abonamentul acum?</h3>
                            </div>
                            <div class="oc-inline-confirm-body">
                                <p>Poti activa abonamentul imediat dupa creare sau il poti lasa in pending pentru activare ulterioara.</p>
                            </div>
                            <div class="oc-inline-confirm-actions">
                                <button type="button" class="button button-primary" data-choice="activate-now">DA, activez acum</button>
                                <button type="button" class="button" data-choice="activate-later">NU activez mai tarziu</button>
                            </div>
                        </div>
                    </div>`;

                const $modal = $(modalHtml);
                $('body').append($modal);

                $modal.on('click', function(evt) {
                    if (evt.target === $modal.get(0)) {
                        $modal.remove();
                        resolve(false);
                    }
                });

                $modal.on('click', '[data-choice]', function() {
                    const choice = $(this).data('choice');
                    $modal.remove();
                    resolve(choice === 'activate-now');
                });
            });
        }

        async function activateOrderPackageNow(orderId, activationDate, expirationDate) {
            const preserveNoExpiry = !expirationDate;
            const payload = {
                action: 'oc_activate_membership_manual',
                nonce: config.nonce,
                order_id: parseInt(orderId, 10) || 0,
                activation_date: activationDate,
                preserved_expiration_date: preserveNoExpiry ? '' : expirationDate,
                preserve_no_expiry: preserveNoExpiry ? 1 : 0
            };

            const response = await $.post(config.ajaxUrl, payload);
            if (!response || !response.success) {
                throw new Error(response?.data?.message || 'Activarea automata a esuat.');
            }
            return response;
        }

        // Toggle form visibility
        $(document).on('click', '#oc-toggle-add-client-form', function(e) {
            e.preventDefault();

            const $container = $('#oc-add-client-form-container');
            const isOpening = !$container.is(':visible');

            if (!isOpening) {
                closeAddClientForm();
                return;
            }

            closeAllExpandedCards();
            closeVisibleRenewForms();

            $container.stop(true, true).slideDown(200, function() {
                scrollToElement($container, 15);
            });

            $(this)
                .addClass('is-active')
                .attr('aria-expanded', 'true');
        });

        $(document).on('click', '.oc-btn-close-form', function(e) {
            e.preventDefault();
            closeAddClientForm();
        });
        
        // Dropdown cascade for new client
        $('#new-client-package').on('change', function() {
            const packageId = $(this).val();
            const $select = $(this);
            const $form = $('#oc-new-client-form');
            const $container = $('#oc-new-courses-container');
            const $selections = $('#oc-new-course-selections');
            const $expirationInput = $form.find('input[name="expiration_date"]');

            $select.removeClass('oc-field-error-highlight');
            // Pachet nou => revenim la modul auto pentru expirare până la o editare manuală nouă.
            $expirationInput.removeData('ocManualExpiryOverride');
            applyPackageMetaToForm($form, $select);
            
            if (!packageId) {
                $container.hide();
                return;
            }
            
            $selections.html('<p>⏳ Se încarcă...</p>');
            $container.show();
            
            $.post(config.ajaxUrl, {
                action: 'oc_get_package_courses',
                nonce: config.nonce,
                package_id: packageId
            })
            .done(function(response) {
                if (response.success) {
                    $selections.html(response.data.html);
                    applyLoadedCoursesLayout($selections, 'new');
                } else {
                    $selections.html('<p style="color: red;">❌ ' + response.data.message + '</p>');
                }
            })
            .fail(function() {
                $selections.html('<p style="color: red;">❌ Eroare de conexiune</p>');
            });
        });
        
        // Max-selections enforcement for checkbox groups (radio handles this natively)
        $(document).on('change', '.oc-course-pool-group input[name="course_selections[]"]', function() {
            const $checkbox = $(this);
            if (!$checkbox.is(':checked')) return;
            const $grp = $checkbox.closest('.oc-course-pool-group');
            const maxSel = parseInt($grp.data('max-selections') || 0, 10);
            if (maxSel > 0) {
                const checked = $grp.find('input[name="course_selections[]"]:checked');
                if (checked.length > maxSel) {
                    // Already at max — block this new selection
                    $checkbox.prop('checked', false);
                }
            }
        });

        // Submit new client form
        $('#oc-new-client-form').on('submit', async function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $btn = $form.find('[type="submit"]');
            const productPriceEditableRaw = $form.find('[name="product_price"]').first().val();
            const productPriceRaw = parseFloat(String(productPriceEditableRaw || '0').replace(',', '.'));
            const productPrice = Number.isFinite(productPriceRaw) ? Math.max(0, productPriceRaw) : 0;
            const activationDate = getIsoDateFieldValue($form.find('[name="activation_date"]'));
            const expirationDate = getIsoDateFieldValue($form.find('[name="expiration_date"]'));
            const $packageSelect = $form.find('[name="package_id"]');
            const selectedPackageId = parseInt($packageSelect.val(), 10);

            if (isNaN(selectedPackageId) || selectedPackageId <= 0) {
                $packageSelect.addClass('oc-field-error-highlight').trigger('focus');
                showNotification('❌ Selectează un abonament înainte de creare', 'error');
                return;
            }
            
            // Collect course selections (checkboxes + radio buttons)
            const selectedCourses = [];
            $form.find('input[name="course_selections[]"]:checked, input.oc-pool-radio-input:checked').each(function() {
                selectedCourses.push($(this).val());
            });

            // Validare per-grup (suport dual mode cu 2 carduri separate)
            const groupValidationError = validatePoolGroupSelections($form);
            if (groupValidationError) {
                showNotification(groupValidationError, 'error');
                return;
            }

            if (activationDate === null) {
                showNotification('❌ Data "Activ de la" este invalidă pentru formatul setat în WordPress.', 'error');
                return;
            }

            if (expirationDate === null) {
                showNotification('❌ Data "Expiră la" este invalidă pentru formatul setat în WordPress.', 'error');
                return;
            }

            const activateNowChoice = await askActivationChoice();
            
            $btn.prop('disabled', true).html('⏳ Se creează...');
            
            // AJAX Create Client
            $.post(config.ajaxUrl, {
                action: 'oc_create_new_client',
                nonce: config.nonce,
            data: {
                first_name: $form.find('[name="first_name"]').val(),
                last_name: $form.find('[name="last_name"]').val(),
                email: $form.find('[name="email"]').val(),
                phone: $form.find('[name="phone"]').val(),
                password: $form.find('[name="password"]').val(),
                package_id: selectedPackageId,
                course_selections: selectedCourses,
                payment_status: $form.find('[name="payment_status"]').val(),
                payment_method: $form.find('[name="payment_method"]').val(),
                product_price: productPrice,
                observations: $form.find('[name="observations"]').val(),
                send_email: $form.find('[name="send_email"]').is(':checked') ? 1 : 0,
                activation_date: activationDate,
                expiration_date: expirationDate,
                billing_first_name: $form.find('[name="billing_first_name"]').val(),
                billing_last_name: $form.find('[name="billing_last_name"]').val(),
                billing_address_1: $form.find('[name="billing_address_1"]').val(),
                billing_city: $form.find('[name="billing_city"]').val(),
                billing_state: $form.find('[name="billing_state"]').val(),
                billing_postcode: $form.find('[name="billing_postcode"]').val(),
                billing_country: $form.find('[name="billing_country"]').val(),
                activate_now: activateNowChoice ? 1 : 0
            }
            })
            .done(async function(response) {
                if (response.success) {
                    if (activateNowChoice && response.data?.order_id) {
                        try {
                            await activateOrderPackageNow(response.data.order_id, activationDate, expirationDate || '');
                        } catch (activationError) {
                            showNotification('⚠️ Clientul a fost creat, dar activarea automata a esuat: ' + activationError.message, 'error');
                        }
                    }

                    // Schimbă butonul la SUCCESS
                    $btn.prop('disabled', true).html('✅ Succes! Reîncarcă...');
                    
                    // Afișează notificare
                    const activationMessage = activateNowChoice
                        ? ' Abonamentul a fost activat acum.'
                        : ' Abonamentul a ramas in pending.';
                    showNotification('✅ ' + response.data.message + activationMessage, 'success');
                    
                    // Reset formular
                    $form[0].reset();
                    $('#oc-new-courses-container').hide();
                    
                    // Auto-reload după 1 secundă pentru UX mai bun
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    // Afișează eroarea sub câmpul corespunzător
                    showFieldError($form, response.data.message);
                    $btn.prop('disabled', false).html('✅ Creează Client + Comandă');
                }
            })
            .fail(function() {
                showNotification('❌ Eroare de conexiune', 'error');
                $btn.prop('disabled', false).html('✅ Creează Client + Comandă');
            });
        });

        // Dacă admin-ul editează manual expirarea, păstrăm valoarea și nu o mai suprascriem automat.
        $(document).on('input change', '#oc-new-client-form input[name="expiration_date"]', function() {
            const $field = $(this);
            if ($field.data('ocAutoExpiryUpdate')) {
                return;
            }
            $field.data('ocManualExpiryOverride', true);
        });

    }

    /**
     * Auto-reset search when input is cleared
     */
    function initSearchAutoReset() {
        $(document).on('input', '.oc-search-form .oc-search-input', function() {
            const $input = $(this);
            if ($input.val().trim() !== '') {
                return;
            }

            const $form = $input.closest('.oc-search-form');
            const resetUrl = $form.data('reset-url');
            if (resetUrl) {
                window.location.href = resetUrl;
            }
        });
    }
    
    /**
     * Show notification
     */
    /**
     * 🎨 SISTEM NOTIFICĂRI PREMIUM - Overlay full-page + mesaj centrat perfect
     * FUNCȚIE GLOBALĂ - Disponibilă în tot pluginul
     * 
     * @param {string} message - Mesajul de afișat
     * @param {string} type - Tipul: 'success', 'error', 'info', 'warning'
     * @param {number} duration - Durata în ms (default: 5000)
     */
    window.showNotification = function(message, type, duration) {
        type = type || 'info';
        // ⚡ Durate reduse: success 2s, altele 3s (în loc de 5s)
        if (!duration) {
            duration = (type === 'success') ? 2000 : 3000;
        }
        
        // 1. Creează overlay full-page
        const $overlay = $('<div class="oc-notification-overlay"></div>').css({
            'position': 'fixed',
            'inset': 0,
            'width': '100%',
            'height': '100%',
            'background': 'rgba(15, 23, 42, 0.64)',
            'z-index': 999998,
            'display': 'flex',
            'align-items': 'center',
            'justify-content': 'center',
            'padding': '16px',
            'box-sizing': 'border-box',
            'backdrop-filter': 'blur(2px)',
            'animation': 'fadeIn 0.3s ease-out'
        });
        
        // 2. Determină stilurile pentru fiecare tip
        let bgColor, borderColor, icon;
        switch(type) {
            case 'success':
                bgColor = '#d4edda';
                borderColor = '#28a745';
                icon = '✅';
                break;
            case 'error':
                bgColor = '#f8d7da';
                borderColor = '#dc3545';
                icon = '❌';
                break;
            case 'warning':
                bgColor = '#fff3cd';
                borderColor = '#ffc107';
                icon = '⚠️';
                break;
            default: // info
                bgColor = '#d1ecf1';
                borderColor = '#17a2b8';
                icon = 'ℹ️';
        }
        
        // 3. Creează mesajul centrat
        const $message = $('<div class="oc-notification-message"></div>').css({
            'background': '#ffffff',
            'border': '2px solid ' + borderColor,
            'border-radius': '14px',
            'padding': '24px 20px',
            'width': 'min(560px, 100%)',
            'max-width': '100%',
            'max-height': '88vh',
            'overflow-y': 'auto',
            'box-shadow': '0 24px 64px rgba(15,23,42,0.3)',
            'text-align': 'center',
            'box-sizing': 'border-box',
            'animation': 'slideIn 0.3s ease-out'
        }).html(
            '<div style="font-size: 44px; margin-bottom: 10px; line-height:1;">' + icon + '</div>' +
            '<div style="font-size: 16px; font-weight: 600; color: #1f2937; line-height: 1.5; word-break: break-word;">' + message + '</div>' +
            '<div style="margin-top: 14px; font-size: 12px; color: #6b7280;">Click oriunde pentru a închide</div>'
        );
        
        // 4. Adaugă CSS animations (dacă nu există deja)
        if (!$('#oc-notification-animations').length) {
            $('head').append(`
                <style id="oc-notification-animations">
                    @keyframes fadeIn {
                        from { opacity: 0; }
                        to { opacity: 1; }
                    }
                    @keyframes slideIn {
                        from { 
                            opacity: 0;
                            transform: translateY(-30px) scale(0.95);
                        }
                        to { 
                            opacity: 1;
                            transform: translateY(0) scale(1);
                        }
                    }
                </style>
            `);
        }
        
        // 5. Append la body
        $overlay.append($message);
        $('body').append($overlay);
        
        // 6. Auto-remove după duration
        const removeNotification = function() {
            $overlay.fadeOut(300, function() {
                $(this).remove();
            });
        };
        
        const autoRemoveTimer = setTimeout(removeNotification, duration);
        
        // 7. Click pe overlay sau mesaj = închide
        $overlay.on('click', function() {
            clearTimeout(autoRemoveTimer);
            removeNotification();
        });
        
        // Prevent click on message from closing (optional - dar acum vrem să închidă oricum)
        // $message.on('click', function(e) { e.stopPropagation(); });
    };
    
    // Alias local pentru compatibilitate
    const showNotification = window.showNotification;
    
    /**
     * Show field-specific error message for edit mode
     */
    function showFieldErrorForEdit($card, errorMessage) {
        // Șterge erorile anterioare
        $card.find('.oc-field-error').remove();
        
        // Determină câmpul afectat pe baza mesajului de eroare
        let $targetField = null;
        
        if (errorMessage.includes('Email')) {
            $targetField = $card.find('[name="email"]');
        } else if (errorMessage.includes('Telefon')) {
            $targetField = $card.find('[name="phone"]');
        } else if (errorMessage.includes('Nume')) {
            $targetField = $card.find('[name="display_name"]');
        } else {
            // Eroare generală - afișează la începutul card-ului
            $targetField = $card.find('.oc-card-header');
        }
        
        if ($targetField && $targetField.length) {
            // Adaugă mesajul de eroare sub câmp
            const $errorDiv = $('<div class="oc-field-error" style="color: #d63638; font-size: 12px; margin-top: 5px; padding: 5px; background: #fcf0f1; border: 1px solid #d63638; border-radius: 3px;">' + errorMessage + '</div>');
            $targetField.after($errorDiv);
            
            // Highlight câmpul cu eroare
            $targetField.addClass('oc-field-error-highlight');
            
            // Scroll la câmpul cu eroare
            $('html, body').animate({
                scrollTop: $targetField.offset().top - 100
            }, 500);
            
            // Elimină highlight după 5 secunde
            setTimeout(function() {
                $targetField.removeClass('oc-field-error-highlight');
            }, 5000);
        } else {
            // Fallback la notificare normală
            showNotification('❌ ' + errorMessage, 'error');
        }
    }
    
    /**
     * Show field-specific error message for new client form
     */
    function showFieldError($form, errorMessage) {
        // Șterge erorile anterioare
        $form.find('.oc-field-error').remove();
        
        // Determină câmpul afectat pe baza mesajului de eroare
        let $targetField = null;
        
        if (errorMessage.includes('Email')) {
            $targetField = $form.find('[name="email"]');
        } else if (errorMessage.includes('Telefon')) {
            $targetField = $form.find('[name="phone"]');
        } else if (errorMessage.includes('Nume')) {
            $targetField = $form.find('[name="display_name"]');
        } else {
            // Eroare generală - afișează la începutul formularului
            $targetField = $form.find('.oc-form-header');
        }
        
        if ($targetField && $targetField.length) {
            // Adaugă mesajul de eroare sub câmp
            const $errorDiv = $('<div class="oc-field-error" style="color: #d63638; font-size: 12px; margin-top: 5px; padding: 5px; background: #fcf0f1; border: 1px solid #d63638; border-radius: 3px;">' + errorMessage + '</div>');
            $targetField.after($errorDiv);
            
            // Highlight câmpul cu eroare
            $targetField.addClass('oc-field-error-highlight');
            
            // Scroll la câmpul cu eroare
            $('html, body').animate({
                scrollTop: $targetField.offset().top - 100
            }, 500);
            
            // Elimină highlight după 5 secunde
            setTimeout(function() {
                $targetField.removeClass('oc-field-error-highlight');
            }, 5000);
        } else {
            // Fallback la notificare normală
            showNotification('❌ ' + errorMessage, 'error');
        }
    }
    
    /**
     * Initialize History Toggle Button
     * Expandează/ascunde istoricul cursurilor expirate
     */
    function initHistoryToggleButton() {
        $(document).on('click', '.oc-btn-toggle-history', function() {
            const $btn = $(this);
            const $historyList = $btn.siblings('.oc-history-courses-list');
            const $arrow = $btn.find('.dashicons-arrow-down-alt2, .dashicons-arrow-up-alt2');
            
            // Toggle visibility
            $historyList.slideToggle(300, function() {
                // Schimbă săgeata și textul
                if ($historyList.is(':visible')) {
                    $arrow.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
                    $btn.html($btn.html().replace('Vezi Istoric', 'Ascunde Istoric'));
                } else {
                    $arrow.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
                    $btn.html($btn.html().replace('Ascunde Istoric', 'Vezi Istoric'));
                }
            });
        });
        
    }
    
    /**
     * 📜 EXPIRED HISTORY: Inițializează funcționalitatea de toggle pentru istoric abonamente expirate
     * Se afișează în cardul expandat pentru TOȚI utilizatorii
     */
    function initExpiredHistory() {
        $(document).on('click', '.oc-expired-history-header', function() {
            const $header = $(this);
            const $content = $header.siblings('.oc-expired-history-content');
            const $icon = $header.find('.oc-toggle-icon');
            
            // Toggle conținut cu animație
            $content.slideToggle(300, function() {
                // Animație icon și schimbare background
                if ($content.is(':visible')) {
                    $icon.css('transform', 'rotate(180deg)');
                    $header.css('background', '#e9ecef');
                } else {
                    $icon.css('transform', 'rotate(0deg)');
                    $header.css('background', '#f8f9fa');
                }
            });
        });
        
    }
    
    /**
     * 📅 AUTO EXPIRATION CALCULATION: Calculează automat data expirare (+28 zile) când se schimbă data activare
     */
    function initAutoExpirationCalculation() {
        function recalculateExpirationFromSource($sourceDateInput, forceOverride = false) {
            const sourceDateIso = getIsoDateFieldValue($sourceDateInput);
            if (!sourceDateIso) return;

            const isPackageCreatedAt = ($sourceDateInput.attr('name') || '') === 'created_at'
                && $sourceDateInput.closest('.oc-package-section').length > 0;

            let $scope;
            if (isPackageCreatedAt) {
                $scope = $sourceDateInput.closest('.oc-package-section');
            } else {
                $scope = $sourceDateInput.closest('.oc-card-details, .oc-renew-form, form, .oc-new-client-form, .oc-admin-form');
            }

            const $expirationInput = $scope.find('input[name="expiration_date"]').first();
            const $noExpiry = $scope.find('input[name="no_expiry"]').first();
            const $preserveExpiry = $scope.find('.oc-preserve-expiry-checkbox').first();
            const isNewClientScope = $scope.is('#oc-new-client-form') || $scope.find('#oc-new-client-form').length > 0;

            if (!$expirationInput.length) {
                return;
            }

            if ($noExpiry.length && $noExpiry.is(':checked')) {
                $expirationInput.val('');
                return;
            }

            // Backward compatibility: where preserve-expiry still exists, forceOverride should bypass it.
            if (!forceOverride && $preserveExpiry.length && $preserveExpiry.is(':checked')) {
                return;
            }

            if (!forceOverride && isNewClientScope && $expirationInput.data('ocManualExpiryOverride')) {
                return;
            }

            const durationDays = Math.max(1, parseInt($expirationInput.data('duration-days') || 28, 10) || 28);

            const startDate = new Date(sourceDateIso);
            startDate.setDate(startDate.getDate() + durationDays);

            const year = startDate.getFullYear();
            const month = String(startDate.getMonth() + 1).padStart(2, '0');
            const day = String(startDate.getDate()).padStart(2, '0');
            const expirationDate = `${year}-${month}-${day}`;

            if (($expirationInput.attr('type') || '').toLowerCase() === 'date') {
                $expirationInput.data('ocAutoExpiryUpdate', true);
                $expirationInput.val(expirationDate);
            } else {
                $expirationInput.data('ocAutoExpiryUpdate', true);
                $expirationInput.val(formatDateForDisplay(expirationDate));
            }

            $expirationInput.trigger('change');
            $expirationInput.removeData('ocAutoExpiryUpdate');
        }

        // Recalculează la schimbarea datei de activare (formulare noi/renew)
        // și la schimbarea datei de achiziționare (edit card existent)
        $(document).on('change', 'input[name="activation_date"], input[name="created_at"]', function() {
            recalculateExpirationFromSource($(this), false);
        });

        // Pentru pachete pending: orice click pe data achiziționării recalculează imediat expirarea.
        $(document).on('click', '.oc-package-section.oc-package-pending input[name="created_at"].oc-package-date-field', function() {
            recalculateExpirationFromSource($(this), true);
        });
        
    }
    
    /**
     * 🔒 MANUAL ACTIVATION: Inițializează funcționalitatea de activare manuală
     */
    function initManualActivation() {
        async function savePendingPackageEditsBeforeActivation($button, orderId) {
            const $card = $button.closest('.oc-card-details');
            if (!$card.length) {
                return;
            }

            const $saveBtn = $card.find('.oc-btn-save-member').first();
            const userId = $saveBtn.data('user-id');
            const membershipId = parseInt($saveBtn.data('membership-id'), 10) || 0;
            const $package = $card.find(`.oc-package-section[data-order-id="${orderId}"]`).first();
            if (!$package.length || !userId) {
                return;
            }

            const packageDateUpdates = [];
            const packageMetaUpdates = [];
            const sessionMap = new Map();

            const $dateScope = $package.find('.oc-package-date-fields').first();
            if ($dateScope.length) {
                const packageMembershipId = parseInt($dateScope.data('membership-id'), 10) || membershipId;
                const createdAt = getIsoDateFieldValue($dateScope.find('input[name="created_at"]').first());
                if (createdAt === null || !createdAt) {
                    throw new Error('Data achiziționării este invalidă.');
                }

                const noExpiry = $dateScope.find('input[name="no_expiry"]').first().is(':checked') ? 1 : 0;
                let expirationDate = '';
                if (!noExpiry) {
                    const parsedExpiry = getIsoDateFieldValue($dateScope.find('input[name="expiration_date"]').first());
                    if (parsedExpiry === null) {
                        throw new Error('Data expirării este invalidă.');
                    }
                    expirationDate = parsedExpiry || '';
                }

                packageDateUpdates.push({
                    order_id: parseInt(orderId, 10) || 0,
                    membership_id: packageMembershipId,
                    created_at: createdAt,
                    expiration_date: noExpiry ? '' : expirationDate,
                    no_expiry: noExpiry
                });

                const priceRaw = parseFloat(String($package.find('input[name="package_product_price"]').first().val() || '0').replace(',', '.'));
                if (Number.isFinite(priceRaw) && priceRaw >= 0) {
                    packageMetaUpdates.push({
                        order_id: parseInt(orderId, 10) || 0,
                        membership_id: packageMembershipId,
                        product_price: priceRaw,
                        payment_method: String($package.find('select[name="package_payment_method"]').first().val() || '').trim(),
                        payment_status: String($package.find('select[name="package_payment_status"]').first().val() || '').trim(),
                        observations: String($package.find('textarea[name="package_observations"]').first().val() || '').trim()
                    });
                }
            }

            $package.find('.oc-course-session-input').each(function() {
                const $input = $(this);
                const rowMembershipId = parseInt($input.data('membership-id'), 10) || 0;
                const fieldName = String($input.data('field-name') || '').trim();
                if (!rowMembershipId || !fieldName) {
                    return;
                }
                const rawValue = parseInt($input.val(), 10);
                if (!Number.isFinite(rawValue) || rawValue < 0) {
                    return;
                }
                if (!sessionMap.has(rowMembershipId)) {
                    sessionMap.set(rowMembershipId, { id: rowMembershipId });
                }
                sessionMap.get(rowMembershipId)[fieldName] = rawValue;
            });

            const courseSessions = Array.from(sessionMap.values());

            const response = await $.post(config.ajaxUrl, {
                action: 'oc_save_member_data',
                nonce: config.nonce,
                user_id: userId,
                membership_id: membershipId,
                order_id: orderId,
                data: {},
                course_sessions: JSON.stringify(courseSessions),
                package_date_updates: JSON.stringify(packageDateUpdates),
                package_meta_updates: JSON.stringify(packageMetaUpdates)
            });

            if (!response || !response.success) {
                throw new Error(response?.data?.message || 'Nu s-au putut salva modificările înainte de activare.');
            }
        }

        // Handler buton "Activează Abonamentul"
        $(document).on('click', '.oc-btn-activate-membership', async function() {
            const $button = $(this);
            const orderId = $button.data('order-id');
            
            if (!orderId) {
                alert('❌ Eroare: Order ID lipsă.');
                return;
            }
            
            const $packageCard = $(`.oc-package-section[data-order-id="${orderId}"]`).first();
            const $createdAtInput = $packageCard.find('input[name="created_at"].oc-package-date-field').first();
            const $packageExpiryInput = $packageCard.find('input[name="expiration_date"].oc-package-date-field').first();
            const $packageNoExpiry = $packageCard.find('input[name="no_expiry"].oc-package-date-field').first();

            if (!$packageCard.length) {
                alert('❌ Nu am găsit cardul de pachet pentru activare.');
                return;
            }

            const activationDate = getIsoDateFieldValue($createdAtInput);
            if (activationDate === null || !activationDate) {
                alert('❌ Data achiziționării este invalidă. Corectează data înainte de activare.');
                $createdAtInput.focus();
                return;
            }

            const preserveNoExpiry = $packageNoExpiry.length ? $packageNoExpiry.is(':checked') : false;
            let preservedExpirationDate = '';

            if (!preserveNoExpiry && $packageExpiryInput.length) {
                const parsedPreservedExpiration = getIsoDateFieldValue($packageExpiryInput);
                if (parsedPreservedExpiration === null) {
                    alert('❌ Data expirării editată este invalidă. Corectează data înainte de activare.');
                    $packageExpiryInput.focus();
                    return;
                }
                preservedExpirationDate = parsedPreservedExpiration || '';
            }

            const modeHint = preserveNoExpiry
                ? '\n\n⏳ Expirare: fără dată de expirare.'
                : `\n\n🗓 Expirare: ${formatDateForDisplay(preservedExpirationDate) || 'recalculare automată din data activării.'}`;
            
            if (!confirm(`✅ Activezi pachetul cu data de start ${formatDateForDisplay(activationDate)}?${modeHint}`)) {
                return;
            }
            
            // Disable buton și arată loading
            $button.prop('disabled', true).html('⏳ Activare...');
            
            try {
                await savePendingPackageEditsBeforeActivation($button, orderId);

                const response = await $.ajax({
                    url: config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'oc_activate_membership_manual',
                        nonce: config.nonce,
                        order_id: orderId,
                        activation_date: activationDate,
                        preserved_expiration_date: preservedExpirationDate,
                        preserve_no_expiry: preserveNoExpiry ? 1 : 0
                    }
                });
                
                if (response.success) {
                    // Succes - arată mesaj
                    alert('✅ Pachet activat cu succes!\n\nPagina se va reîncărca pentru a afișa modificările.');
                    
                    // Refresh pagina pentru a vedea card-ul actualizat
                    location.reload();
                } else {
                    alert('❌ Eroare: ' + (response.data.message || 'Nu s-a putut activa pachetul.'));
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-controls-play"></span> Activează Abonamentul');
                }
            } catch (error) {
                console.error('[Manual Activation] Error:', error);
                alert('❌ Eroare de conexiune. Te rog reîncearcă.');
                $button.prop('disabled', false).html('<span class="dashicons dashicons-controls-play"></span> Activează Abonamentul');
            }
        });
        
    }
    
    /**
     * Helper: Formatează data pe baza formatului WordPress (doar pentru afișare).
     */
    function formatDateForDisplay(dateString) {
        if (!dateString) return '';

        const date = new Date(`${dateString}T00:00:00`);
        if (Number.isNaN(date.getTime())) {
            return dateString;
        }

        const locale = (config.locale || document.documentElement.lang || 'en-US').replace('_', '-');
        const wpFormat = (config.dateFormat || 'Y-m-d').toString();

        const day = date.getDate();
        const month = date.getMonth() + 1;
        const year = date.getFullYear();

        const tokenMap = {
            d: String(day).padStart(2, '0'),
            j: String(day),
            m: String(month).padStart(2, '0'),
            n: String(month),
            Y: String(year),
            y: String(year).slice(-2),
            F: date.toLocaleString(locale, { month: 'long' }),
            M: date.toLocaleString(locale, { month: 'short' }),
            l: date.toLocaleString(locale, { weekday: 'long' }),
            D: date.toLocaleString(locale, { weekday: 'short' })
        };

        let formatted = '';
        let escaped = false;

        for (const ch of wpFormat) {
            if (escaped) {
                formatted += ch;
                escaped = false;
                continue;
            }

            if (ch === '\\') {
                escaped = true;
                continue;
            }

            formatted += tokenMap[ch] ?? ch;
        }

        return formatted;
    }
    
})(jQuery);

