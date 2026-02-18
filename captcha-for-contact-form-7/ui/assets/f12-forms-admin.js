/**
 * Slide-In panel logic for Override settings (Extended + Forms pages).
 *
 * Handles open/close, field enable/disable, and AJAX save via REST API.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var backdrop = document.querySelector('.f12-slide-in-backdrop');
        var slideIn = document.querySelector('.f12-slide-in');
        var toast = document.querySelector('.f12-slide-in-toast');

        if (!backdrop || !slideIn) {
            return;
        }

        var header = slideIn.querySelector('.f12-slide-in-header h3');
        var body = slideIn.querySelector('.f12-slide-in-body');
        var closeBtn = slideIn.querySelector('.f12-slide-in-close');
        var cancelBtn = slideIn.querySelector('.f12-slide-in-cancel');
        var saveBtn = slideIn.querySelector('.f12-slide-in-save');

        var activePanel = null;

        // Bind configure buttons
        document.querySelectorAll('.f12-configure-btn').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var panelId = btn.getAttribute('data-panel');
                if (panelId) {
                    open(panelId);
                }
            });
        });

        // Bind close events
        if (closeBtn) closeBtn.addEventListener('click', close);
        if (cancelBtn) cancelBtn.addEventListener('click', close);
        backdrop.addEventListener('click', close);

        // Bind save
        if (saveBtn) saveBtn.addEventListener('click', save);

        /**
         * Open the slide-in panel for a given panel ID.
         */
        function open(panelId) {
            var panel = document.getElementById(panelId);
            if (!panel) {
                return;
            }

            activePanel = panel;

            // Set title
            header.textContent = panel.getAttribute('data-panel-title') || '';

            // Clone panel content into body
            body.innerHTML = panel.innerHTML;

            // Enable all disabled fields in the body
            body.querySelectorAll('[disabled]').forEach(function (el) {
                el.removeAttribute('disabled');
            });

            // Show
            backdrop.classList.add('f12-slide-in-visible');
            slideIn.classList.add('f12-slide-in-visible');
        }

        /**
         * Close the slide-in panel.
         */
        function close() {
            backdrop.classList.remove('f12-slide-in-visible');
            slideIn.classList.remove('f12-slide-in-visible');
            body.innerHTML = '';
            activePanel = null;
        }

        /**
         * Sync current field values from the slide-in body back to the hidden panel.
         *
         * innerHTML serialization does not reflect JS-changed select values or
         * input values, so we must update the DOM attributes explicitly.
         */
        function syncToHiddenPanel() {
            if (!activePanel) return;

            // Sync select elements: update selected attribute on options
            body.querySelectorAll('select[data-override-key]').forEach(function (sel) {
                var key = sel.getAttribute('data-override-key');
                var panelSel = activePanel.querySelector('select[data-override-key="' + key + '"]');
                if (!panelSel) return;
                Array.from(panelSel.options).forEach(function (opt) {
                    if (opt.value === sel.value) {
                        opt.setAttribute('selected', 'selected');
                    } else {
                        opt.removeAttribute('selected');
                    }
                });
            });

            // Sync input elements: update value attribute
            body.querySelectorAll('input[data-override-key]').forEach(function (inp) {
                var key = inp.getAttribute('data-override-key');
                var panelInp = activePanel.querySelector('input[data-override-key="' + key + '"]');
                if (panelInp) {
                    panelInp.setAttribute('value', inp.value);
                }
            });

            // Sync enabled checkbox
            var bodyCheckbox = body.querySelector('[data-override-enabled]');
            var panelCheckbox = activePanel.querySelector('[data-override-enabled]');
            if (bodyCheckbox && panelCheckbox) {
                if (bodyCheckbox.checked) {
                    panelCheckbox.setAttribute('checked', 'checked');
                } else {
                    panelCheckbox.removeAttribute('checked');
                }
            }
        }

        /**
         * Save overrides via REST API.
         */
        function save() {
            if (!activePanel) {
                return;
            }

            var type = activePanel.getAttribute('data-panel-type') || 'integration';
            var integrationId = activePanel.getAttribute('data-integration-id') || '';
            var formId = activePanel.getAttribute('data-form-id') || '';

            // Collect enabled toggle
            var enabledCheckbox = body.querySelector('[data-override-enabled]');
            var enabled = enabledCheckbox ? enabledCheckbox.checked : false;

            // Collect all override fields
            var overrides = {};
            body.querySelectorAll('[data-override-key]').forEach(function (field) {
                var key = field.getAttribute('data-override-key');
                var value = field.value;
                overrides[key] = value;
            });

            // Build request body
            var payload = {
                type: type,
                integration_id: integrationId,
                enabled: enabled,
                overrides: overrides
            };

            if (type === 'form' && formId) {
                payload.form_id = formId;
            }

            // Disable save button during request
            saveBtn.disabled = true;
            saveBtn.textContent = f12FormsAdmin.saving || 'Saving...';

            fetch(f12FormsAdmin.restUrl + 'overrides/save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': f12FormsAdmin.restNonce
                },
                body: JSON.stringify(payload)
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    return response.json();
                })
                .then(function (data) {
                    if (data.status === 'success') {
                        showToast(f12FormsAdmin.msgSuccess || 'Settings saved.', 'success');
                        updateBadge(activePanel, enabled, overrides);
                        syncToHiddenPanel();
                        close();
                    } else {
                        showToast(f12FormsAdmin.msgError || 'Error saving settings.', 'error');
                    }
                })
                .catch(function () {
                    showToast(f12FormsAdmin.msgError || 'Error saving settings.', 'error');
                })
                .finally(function () {
                    saveBtn.disabled = false;
                    saveBtn.textContent = f12FormsAdmin.saveLabel || 'Save';
                });
        }

        /**
         * Update the badge on the page after a successful save.
         */
        function updateBadge(panel, enabled, overrides) {
            if (!panel) return;

            var panelId = panel.id;
            // Find the configure button that references this panel
            var btn = document.querySelector('[data-panel="' + panelId + '"]');
            if (!btn) return;

            var row = btn.closest('.toggle-item-wrapper, .f12-forms-integration-row, tr');
            if (!row) return;

            var badge = row.querySelector('.f12-forms-badge');
            if (!badge) return;

            // Count non-inherit overrides
            var count = 0;
            if (enabled) {
                for (var key in overrides) {
                    if (overrides.hasOwnProperty(key) && overrides[key] !== '__inherit__' && overrides[key] !== '') {
                        count++;
                    }
                }
            }

            if (enabled && count > 0) {
                badge.className = 'f12-forms-badge f12-forms-badge--active';
                badge.textContent = count + (count === 1 ? ' Override' : ' Overrides');
            } else {
                badge.className = 'f12-forms-badge f12-forms-badge--global';
                badge.textContent = f12FormsAdmin.badgeGlobal || 'Global Settings';
            }
        }

        /**
         * Show a toast notification.
         */
        function showToast(message, type) {
            if (!toast) return;

            toast.textContent = message;
            toast.className = 'f12-slide-in-toast';
            toast.classList.add(type === 'success' ? 'f12-toast-success' : 'f12-toast-error');

            setTimeout(function () {
                toast.className = 'f12-slide-in-toast';
            }, 3000);
        }
    });
})();
