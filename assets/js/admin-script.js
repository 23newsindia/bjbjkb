/**
 * Wild Dragon SEO - Admin Dashboard JavaScript
 * Enhanced interactions and user experience
 */

jQuery(document).ready(function($) {
    'use strict';

    // Initialize dashboard enhancements
    initDashboardEnhancements();
    initFormValidation();
    initTooltips();
    initAnimations();

    /**
     * Initialize dashboard enhancements
     */
    function initDashboardEnhancements() {
        // Add loading states to form submissions
        $('form.wdseo-form').on('submit', function() {
            const $form = $(this);
            const $submitBtn = $form.find('input[type="submit"]');
            
            $submitBtn.prop('disabled', true);
            $submitBtn.val('Saving...');
            $form.addClass('wdseo-loading');
        });

        // Auto-save indication
        let saveTimeout;
        $('form.wdseo-form input, form.wdseo-form select, form.wdseo-form textarea').on('change', function() {
            clearTimeout(saveTimeout);
            
            // Show unsaved changes indicator
            if (!$('.wdseo-unsaved-notice').length) {
                $('<div class="wdseo-notice wdseo-notice-warning wdseo-unsaved-notice">' +
                  '<strong>⚠️ Unsaved Changes</strong> - Don\'t forget to save your settings!' +
                  '</div>').insertAfter('.nav-tab-wrapper');
            }
        });

        // Remove unsaved notice on form submit
        $('form.wdseo-form').on('submit', function() {
            $('.wdseo-unsaved-notice').remove();
        });

        // Character counter for textareas
        $('textarea[name*="description"]').each(function() {
            addCharacterCounter($(this));
        });

        // Enhanced checkbox interactions
        $('input[type="checkbox"]').on('change', function() {
            const $this = $(this);
            const $label = $this.closest('label');
            
            if ($this.is(':checked')) {
                $label.addClass('wdseo-checked');
            } else {
                $label.removeClass('wdseo-checked');
            }
        });

        // Initialize checked state
        $('input[type="checkbox"]:checked').closest('label').addClass('wdseo-checked');
    }

    /**
     * Add character counter to textarea
     */
    function addCharacterCounter($textarea) {
        const maxLength = 160; // Standard meta description length
        const $counter = $('<div class="wdseo-char-counter"></div>');
        
        $textarea.after($counter);
        
        function updateCounter() {
            const length = $textarea.val().length;
            const remaining = maxLength - length;
            
            let status = 'good';
            if (length > maxLength) status = 'over';
            else if (length > maxLength * 0.9) status = 'warning';
            
            $counter.html(`
                <span class="wdseo-char-count wdseo-char-${status}">
                    ${length} characters
                    ${remaining < 0 ? `(${Math.abs(remaining)} over limit)` : `(${remaining} remaining)`}
                </span>
            `);
        }
        
        $textarea.on('input keyup', updateCounter);
        updateCounter();
    }

    /**
     * Initialize form validation
     */
    function initFormValidation() {
        // Twitter handle validation
        $('input[name="wdseo_twitter_site_handle"]').on('input', function() {
            const $input = $(this);
            const value = $input.val();
            
            if (value && !value.startsWith('@')) {
                $input.val('@' + value);
            }
            
            // Validate Twitter handle format
            const isValid = /^@[A-Za-z0-9_]{1,15}$/.test(value);
            
            if (value && !isValid) {
                $input.addClass('wdseo-invalid');
                if (!$input.next('.wdseo-validation-error').length) {
                    $input.after('<div class="wdseo-validation-error">Please enter a valid Twitter handle (e.g., @username)</div>');
                }
            } else {
                $input.removeClass('wdseo-invalid');
                $input.next('.wdseo-validation-error').remove();
            }
        });

        // URL pattern validation
        $('textarea[name="wdseo_robots_blocked_urls"]').on('input', function() {
            const $textarea = $(this);
            const lines = $textarea.val().split('\n');
            let hasErrors = false;
            
            lines.forEach(function(line, index) {
                line = line.trim();
                if (line && !line.startsWith('/')) {
                    hasErrors = true;
                }
            });
            
            if (hasErrors) {
                $textarea.addClass('wdseo-invalid');
                if (!$textarea.next('.wdseo-validation-error').length) {
                    $textarea.after('<div class="wdseo-validation-error">URL patterns should start with / (e.g., /admin/*)</div>');
                }
            } else {
                $textarea.removeClass('wdseo-invalid');
                $textarea.next('.wdseo-validation-error').remove();
            }
        });
    }

    /**
     * Initialize tooltips
     */
    function initTooltips() {
        // Add tooltips to feature icons
        $('.wdseo-feature-icon').each(function() {
            const $icon = $(this);
            const $row = $icon.closest('tr');
            const description = $row.find('.description').text();
            
            if (description) {
                $icon.attr('title', description);
            }
        });

        // Add tooltips to status indicators
        $('.wdseo-status-enabled').attr('title', 'This feature is active and working');
        $('.wdseo-status-disabled').attr('title', 'This feature is currently disabled');
        $('.wdseo-status-warning').attr('title', 'This feature needs attention');
    }

    /**
     * Initialize animations
     */
    function initAnimations() {
        // Animate form sections on tab change
        $('.nav-tab').on('click', function(e) {
            const $form = $('.wdseo-form');
            
            $form.css('opacity', '0.7');
            
            setTimeout(function() {
                $form.css('opacity', '1');
            }, 150);
        });

        // Animate success messages
        $(document).on('DOMNodeInserted', '.notice-success', function() {
            $(this).hide().fadeIn(300);
        });

        // Smooth scroll to validation errors
        $(document).on('click', '.wdseo-validation-error', function() {
            const $error = $(this);
            const $input = $error.prev('input, textarea, select');
            
            $('html, body').animate({
                scrollTop: $input.offset().top - 100
            }, 300);
            
            $input.focus();
        });
    }

    /**
     * Add dynamic help text
     */
    function addDynamicHelp() {
        // Robots meta help
        $('select[name*="robots"]').on('change', function() {
            const $select = $(this);
            const value = $select.val();
            let helpText = '';
            
            switch(value) {
                case 'index,follow':
                    helpText = '✅ Search engines will index this content and follow links';
                    break;
                case 'noindex,nofollow':
                    helpText = '🚫 Search engines will not index this content or follow links';
                    break;
                case 'index,nofollow':
                    helpText = '📄 Search engines will index this content but not follow links';
                    break;
                case 'noindex,follow':
                    helpText = '🔗 Search engines will not index this content but will follow links';
                    break;
            }
            
            $select.next('.wdseo-dynamic-help').remove();
            if (helpText) {
                $select.after(`<div class="wdseo-dynamic-help">${helpText}</div>`);
            }
        });
    }

    // Initialize dynamic help
    addDynamicHelp();

    /**
     * Tab persistence
     */
    function initTabPersistence() {
        // Save current tab to localStorage
        $('.nav-tab').on('click', function() {
            const tabId = $(this).attr('href').split('tab=')[1];
            localStorage.setItem('wdseo_current_tab', tabId);
        });

        // Restore tab on page load
        const savedTab = localStorage.getItem('wdseo_current_tab');
        if (savedTab && !window.location.href.includes('tab=')) {
            const newUrl = window.location.href + '&tab=' + savedTab;
            window.history.replaceState({}, '', newUrl);
        }
    }

    initTabPersistence();

    /**
     * Quick actions
     */
    function initQuickActions() {
        // Quick enable/disable toggles
        $('.wdseo-quick-toggle').on('click', function(e) {
            e.preventDefault();
            
            const $toggle = $(this);
            const $checkbox = $toggle.data('target');
            
            $($checkbox).prop('checked', !$($checkbox).is(':checked')).trigger('change');
            
            // Update toggle text
            const isChecked = $($checkbox).is(':checked');
            $toggle.text(isChecked ? 'Disable' : 'Enable');
            $toggle.toggleClass('wdseo-enabled', isChecked);
        });
    }

    initQuickActions();
});

// Add custom CSS for JavaScript enhancements
const customCSS = `
<style>
.wdseo-char-counter {
    margin-top: 8px;
    font-size: 12px;
}

.wdseo-char-count.wdseo-char-good {
    color: var(--wdseo-success);
}

.wdseo-char-count.wdseo-char-warning {
    color: var(--wdseo-warning);
}

.wdseo-char-count.wdseo-char-over {
    color: var(--wdseo-danger);
    font-weight: 600;
}

.wdseo-validation-error {
    color: var(--wdseo-danger);
    font-size: 12px;
    margin-top: 4px;
    padding: 8px 12px;
    background: rgba(239, 68, 68, 0.1);
    border-radius: 4px;
    border-left: 3px solid var(--wdseo-danger);
}

.wdseo-invalid {
    border-color: var(--wdseo-danger) !important;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
}

.wdseo-dynamic-help {
    margin-top: 8px;
    padding: 8px 12px;
    background: var(--wdseo-gray-50);
    border-radius: 4px;
    font-size: 12px;
    border-left: 3px solid var(--wdseo-primary);
}

.wdseo-checked {
    background: rgba(99, 102, 241, 0.05);
    border-radius: 4px;
    padding: 4px 8px;
    margin: -4px -8px;
}

.wdseo-quick-toggle {
    font-size: 11px;
    padding: 4px 8px;
    border-radius: 12px;
    background: var(--wdseo-gray-200);
    color: var(--wdseo-gray-700);
    text-decoration: none;
    margin-left: 8px;
    transition: var(--wdseo-transition);
}

.wdseo-quick-toggle:hover {
    background: var(--wdseo-gray-300);
}

.wdseo-quick-toggle.wdseo-enabled {
    background: var(--wdseo-success);
    color: white;
}
</style>
`;

document.head.insertAdjacentHTML('beforeend', customCSS);