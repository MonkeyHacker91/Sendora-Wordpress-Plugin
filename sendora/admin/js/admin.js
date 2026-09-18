(function () {
    'use strict';

    var button = document.getElementById('sendora-test-connection');
    var result = document.getElementById('sendora-connection-result');

    if (!button || !result || typeof SendoraAdmin === 'undefined') {
        return;
    }

    button.addEventListener('click', function () {
        var body = new URLSearchParams({
            action: 'sendora_test_connection',
            nonce: SendoraAdmin.nonce
        });

        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        result.className = 'sendora-connection-result';
        result.textContent = 'Testing…';

        fetch(SendoraAdmin.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: body.toString()
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Connection test failed.');
                }

                return response.json();
            })
            .then(function (data) {
                result.textContent = data.message || 'Connection test completed.';
                result.classList.add(data.ok ? 'is-success' : 'is-error');
            })
            .catch(function (error) {
                result.textContent = error.message;
                result.classList.add('is-error');
            })
            .finally(function () {
                button.disabled = false;
                button.removeAttribute('aria-busy');
            });
    });
}());
