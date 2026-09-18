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
                throw new Error(result.error || 'Unable to submit the form.');
            }

            form.reset();
            if (status) {
                status.textContent = result.message || 'Thank you. Your message was sent.';
            }
        } catch (error) {
            if (status) {
                status.textContent = error instanceof Error
                    ? error.message
                    : 'Unable to submit the form.';
            }
        } finally {
            form.classList.remove('is-submitting');
            if (button) {
                button.disabled = false;
            }
        }
    });
}());
