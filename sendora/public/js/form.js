(function () {
    'use strict';

    document.addEventListener('submit', async function (event) {
        var form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.classList.contains('sendora-form')) {
            return;
        }

        event.preventDefault();

        var button = form.querySelector('button[type="submit"]');
        var status = form.querySelector('.sendora-form__status');
        var i18n = (typeof SendoraForm !== 'undefined' && SendoraForm.i18n) ? SendoraForm.i18n : {};
        form.classList.add('is-submitting');

        if (button) {
            button.disabled = true;
        }

        if (status) {
            status.textContent = '';
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
            if (status) {
                status.textContent = result.message || i18n.success || 'Obrigado. Sua mensagem foi enviada.';
            }
        } catch (error) {
            if (status) {
                status.textContent = error instanceof Error
                    ? error.message
                    : (i18n.error || 'Não foi possível enviar o formulário.');
            }
        } finally {
            form.classList.remove('is-submitting');
            if (button) {
                button.disabled = false;
            }
        }
    });
}());
