(function () {
    'use strict';

    function onlyDigits(value) {
        return String(value || '').replace(/\D+/g, '');
    }

    function applyMask(value, mask) {
        var digits = onlyDigits(value);
        if (!mask) {
            return digits;
        }
        var out = '';
        var di = 0;
        for (var i = 0; i < mask.length && di < digits.length; i++) {
            if (mask.charAt(i) === '#') {
                out += digits.charAt(di);
                di += 1;
            } else {
                out += mask.charAt(i);
            }
        }
        return out;
    }

    function bindPhoneMask(input) {
        if (!(input instanceof HTMLInputElement) || input.dataset.sendoraMaskBound === '1') {
            return;
        }
        input.dataset.sendoraMaskBound = '1';
        var mask = input.getAttribute('data-sendora-phone-mask') || '';

        input.addEventListener('input', function () {
            var start = input.selectionStart;
            var before = input.value;
            input.value = applyMask(input.value, mask);
            if (typeof start === 'number' && document.activeElement === input) {
                var delta = input.value.length - before.length;
                var pos = Math.max(0, start + delta);
                try {
                    input.setSelectionRange(pos, pos);
                } catch (e) {
                    // Ignore unsupported selection on some browsers.
                }
            }
        });
    }

    function initForms(root) {
        (root || document).querySelectorAll('input[data-sendora-phone-mask]').forEach(bindPhoneMask);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initForms(document);
        });
    } else {
        initForms(document);
    }

    document.addEventListener('submit', async function (event) {
        var form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.classList.contains('sendora-form')) {
            return;
        }

        event.preventDefault();

        var button = form.querySelector('button[type="submit"]');
        var status = form.querySelector('.sendora-form__status');
        var phoneInput = form.querySelector('input[name="phone"]');
        var i18n = (typeof SendoraForm !== 'undefined' && SendoraForm.i18n) ? SendoraForm.i18n : {};
        form.classList.add('is-submitting');

        if (button) {
            button.disabled = true;
        }

        if (status) {
            status.textContent = '';
            status.classList.remove('is-success', 'is-error');
        }

        // Submit national digits; server adds DDI.
        if (phoneInput) {
            phoneInput.value = onlyDigits(phoneInput.value);
        }

        try {
            var response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin'
            });
            var result = await response.json();

            if (!response.ok || !result.ok) {
                throw new Error(result.error || i18n.error || 'Não foi possível enviar o formulário.');
            }

            form.reset();
            initForms(form);
            if (status) {
                status.textContent = result.message || i18n.success || 'Obrigado. Sua mensagem foi enviada.';
                status.classList.add('is-success');
            }
        } catch (error) {
            if (status) {
                status.textContent = error instanceof Error
                    ? error.message
                    : (i18n.error || 'Não foi possível enviar o formulário.');
                status.classList.add('is-error');
            }
            if (phoneInput) {
                var mask = phoneInput.getAttribute('data-sendora-phone-mask') || '';
                phoneInput.value = applyMask(phoneInput.value, mask);
            }
        } finally {
            form.classList.remove('is-submitting');
            if (button) {
                button.disabled = false;
            }
        }
    });
}());
