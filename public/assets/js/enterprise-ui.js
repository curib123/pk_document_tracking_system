/* Native MVC browser enhancements. Never performs AJAX, fetch, or REST calls. */
(function (window, document) {
    'use strict';

    if (!window.jQuery) {
        // HTML links, native selects and server forms remain usable offline.
        return;
    }

    const $ = window.jQuery;

    $(function () {
        // CSS icon fallbacks work even when icon CDNs are blocked.
        function chooseIcons() {
            if (!document.fonts || !document.fonts.check) {
                return;
            }
            $('body').removeClass('pk-icons-fa pk-icons-bi');
            if (document.fonts.check('900 14px "Font Awesome 6 Free"')) {
                $('body').addClass('pk-icons-fa');
            } else if (document.fonts.check('14px "bootstrap-icons"')) {
                $('body').addClass('pk-icons-bi');
            }
        }

        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(chooseIcons);
        }

        // Native <details> keeps mobile navigation keyboard- and offline-friendly.
        $('.pk-mobile-drawer nav a').on('click', function () {
            $(this).closest('.pk-mobile-drawer').prop('open', false);
        });

        $('[data-pk-close-mobile]').on('click', function () {
            $(this).closest('.pk-mobile-drawer').prop('open', false);
        });

        $(document).on('keydown', function (event) {
            if (event.key === 'Escape') {
                $('.pk-mobile-drawer').prop('open', false);
                closeSelects();
            }
        });

        $(document).on('click', function (event) {
            $('.pk-mobile-drawer[open]').each(function () {
                if (!this.contains(event.target)) {
                    $(this).prop('open', false);
                }
            });

            if (!$(event.target).closest('.pk-select-shell').length) {
                closeSelects();
            }
        });

        const activeSelects = [];

        function closeSelects(except) {
            activeSelects.forEach(function (instance) {
                if (instance !== except && instance.open) {
                    instance.close();
                }
            });
        }

        // One reusable progressive-enhancement for every native <select>.
        // Click: looks like a dropdown, switches into a searchable list.
        $('select[data-pk-searchable]').each(function () {
            const native = $(this);
            const wrapper = native.closest('.pk-select-shell');
            if (!wrapper.length || native.prop('disabled')) return;

            const label = native.attr('data-label') || native.attr('name') || 'Option';
            const originalOptions = native.find('option').map(function () {
                return { value: this.value, label: $(this).text().trim(), disabled: this.disabled };
            }).get();

            const trigger = $('<button type="button" class="pk-select-trigger"></button>')
                .attr('aria-haspopup', 'listbox')
                .attr('aria-expanded', 'false')
                .attr('aria-label', 'Choose ' + label);
            const chosenText = $('<span class="text-truncate"></span>');
            trigger.append(chosenText, $('<span class="pk-chevron" aria-hidden="true">⌄</span>'));

            const menu = $('<div class="pk-select-menu" hidden></div>');
            const search = $('<input type="search" class="form-control form-control-sm" autocomplete="off">')
                .attr('aria-label', 'Search ' + label)
                .attr('placeholder', 'Search ' + label.toLowerCase())
                .attr('name', native.attr('data-lookup-name') || '')
                .prop('disabled', !native.attr('data-lookup-name'));
            const list = $('<div class="pk-option-list" role="listbox"></div>');
            const count = $('<div class="pk-option-hint" aria-live="polite"></div>');
            menu.append(search, list, count);

            const moreAction = native.attr('data-lookup-action');
            if (moreAction) {
                const server = $('<div class="pk-search-all"></div>');
                const submit = $('<button type="submit" class="btn btn-outline-primary btn-sm w-100">Search full list</button>')
                    .attr('name', 'lookup_field')
                    .val(native.attr('name'))
                    .attr('formaction', moreAction)
                    .attr('formmethod', 'post')
                    .attr('formnovalidate', 'formnovalidate');
                server.append(submit);
                menu.append(server);
            }

            wrapper.append(trigger, menu);
            // The visible trigger owns validation once the native select is visually hidden.
            const required = native.prop('required');
            if (required) native.prop('required', false);
            native.addClass('visually-hidden').attr({ 'tabindex': '-1', 'aria-hidden': 'true' });
            wrapper.find('[data-pk-lookup-fallback]').prop('hidden', true)
                .find('input, button').prop('disabled', true);

            function showValue() {
                const selected = native.find('option:selected');
                chosenText.text(selected.length && selected.val() ? selected.text().trim() : 'Select ' + label.toLowerCase());
                trigger.toggleClass('text-body-secondary', !selected.val());
            }

            function filteredOptions(query) {
                const term = query.toLocaleLowerCase();
                const matches = originalOptions.filter(function (item) {
                    return !item.disabled && item.label.toLocaleLowerCase().includes(term);
                });
                list.empty();
                if (!matches.length) {
                    list.append($('<p class="pk-option-empty mb-0"></p>').text('No matching options. Search the full list below.'));
                }
                matches.forEach(function (item) {
                    const option = $('<button type="button" class="pk-option" role="option"></button>')
                        .text(item.label)
                        .attr('aria-selected', String(native.val() === item.value))
                        .on('click', function () {
                            native.val(item.value).trigger('change');
                            instance.close();
                            trigger.trigger('focus');
                        });
                    list.append(option);
                });
                count.text(matches.length + ' available option' + (matches.length === 1 ? '' : 's'));
            }

            const instance = {
                open: false,
                close: function () {
                    this.open = false;
                    menu.prop('hidden', true);
                    trigger.attr('aria-expanded', 'false');
                    wrapper.removeClass('is-open');
                    search.val('');
                },
                show: function () {
                    closeSelects(this);
                    this.open = true;
                    wrapper.addClass('is-open');
                    menu.prop('hidden', false);
                    trigger.attr('aria-expanded', 'true');
                    filteredOptions(search.val());
                    search.trigger('focus');
                }
            };
            activeSelects.push(instance);

            trigger.on('click', function () {
                if (instance.open) {
                    instance.close();
                } else {
                    instance.show();
                }
            });
            native.on('change', function () {
                trigger.removeClass('border-danger');
                showValue();
            });

            native.closest('form').on('submit', function (event) {
                const submitter = event.originalEvent && event.originalEvent.submitter;
                if (submitter && submitter.formNoValidate) return;
                if (event.isDefaultPrevented()) return;
                if (required && !native.val()) {
                    event.preventDefault();
                    trigger.addClass('border-danger');
                    instance.show();
                    count.text('Choose ' + label.toLowerCase() + ' to continue.');
                }
            });
            search.on('input', function () {
                filteredOptions($(this).val());
            });
            search.on('keydown', function (event) {
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    list.find('.pk-option').first().trigger('focus');
                } else if (event.key === 'Enter') {
                    // Enter selects the first matching result, never submits the parent form.
                    event.preventDefault();
                    list.find('.pk-option').first().trigger('click');
                }
            });
            list.on('keydown', '.pk-option', function (event) {
                const options = list.find('.pk-option');
                const index = options.index(this);
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    options.eq(Math.min(options.length - 1, index + 1)).trigger('focus');
                } else if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    if (index === 0) search.trigger('focus');
                    else options.eq(index - 1).trigger('focus');
                }
            });

            showValue();
            if (native.attr('data-pk-autofocus') === 'true') {
                const activateSearch = function () {
                    const initialSearch = native.attr('data-pk-search-term') || '';
                    instance.show();
                    search.val(initialSearch);
                    filteredOptions(initialSearch);
                };

                const ownerModal = native.closest('.modal');
                if (ownerModal.length) {
                    ownerModal.one('shown.bs.modal', activateSearch);
                } else {
                    activateSearch();
                }
            }
        });

        // Modal route with one shared design: JS opens Bootstrap modal; otherwise
        // CSS keeps its form visible as a normal page.
        if (window.bootstrap && window.bootstrap.Modal) {
            $('body').addClass('pk-modal-ready');
            $('.pk-page-modal[data-pk-auto-open="true"]').each(function () {
                window.bootstrap.Modal.getOrCreateInstance(this, {
                    backdrop: 'static',
                    keyboard: true
                }).show();
            });
        }

        // Password visibility is optional convenience; HTML form still works without JS.
        $('[data-pk-password-toggle]').on('click', function () {
            const control = document.getElementById($(this).attr('data-pk-password-toggle'));
            if (!control) return;
            const nowVisible = control.type === 'password';
            control.type = nowVisible ? 'text' : 'password';
            $(this).text(nowVisible ? 'Hide' : 'Show')
                .attr('aria-pressed', String(nowVisible))
                .attr('aria-label', (nowVisible ? 'Hide' : 'Show') + ' ' + (control.name || 'password'));
        });

        // Dismiss mobile menu via click on the background only.
        $('.pk-mobile-drawer').on('click', function (event) {
            if (event.target === this) $(this).prop('open', false);
        });
    });
})(window, document);
