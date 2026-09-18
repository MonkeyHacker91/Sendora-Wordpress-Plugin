(function () {
    'use strict';

    var pagesBox = document.getElementById('sendora-widget-pages');
    var displayRadios = document.querySelectorAll('[data-sendora-widget-display]');

    function syncWidgetPagesVisibility() {
        if (!pagesBox || !displayRadios.length) {
            return;
        }
        var mode = 'all';
        displayRadios.forEach(function (radio) {
            if (radio.checked) {
                mode = radio.value;
            }
        });
        if (mode === 'specific') {
            pagesBox.removeAttribute('hidden');
        } else {
            pagesBox.setAttribute('hidden', 'hidden');
        }
    }

    displayRadios.forEach(function (radio) {
        radio.addEventListener('change', syncWidgetPagesVisibility);
    });
    syncWidgetPagesVisibility();

    if (typeof SendoraAdmin === 'undefined') {
        return;
    }

    var i18n = SendoraAdmin.i18n || {};

    function postAdmin(action, extra) {
        var body = new URLSearchParams({
            action: action,
            nonce: SendoraAdmin.nonce
        });
        if (extra) {
            Object.keys(extra).forEach(function (key) {
                if (extra[key] !== undefined && extra[key] !== null && extra[key] !== '') {
                    body.set(key, extra[key]);
                }
            });
        }
        return fetch(SendoraAdmin.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: body.toString()
        }).then(function (response) {
            return response.text().then(function (text) {
                var trimmed = (text || '').trim();
                if (trimmed === '-1' || trimmed === '0' || trimmed === '') {
                    throw new Error(i18n.failed || 'Falha na requisição.');
                }
                return JSON.parse(trimmed);
            });
        });
    }

    function bindStatusButton(buttonId, resultId, action, loadingText, extraFn) {
        var button = document.getElementById(buttonId);
        var result = document.getElementById(resultId || 'sendora-connection-result');
        if (!button || !result) {
            return;
        }
        button.addEventListener('click', function () {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            result.className = 'sendora-inline-status';
            result.textContent = loadingText;
            var extra = typeof extraFn === 'function' ? extraFn() : {};
            postAdmin(action, extra)
                .then(function (data) {
                    result.textContent = data.message || i18n.done || 'OK';
                    result.classList.add(data.ok ? 'is-success' : 'is-error');
                    if (action === 'sendora_disconnect' && data.ok) {
                        window.location.reload();
                    }
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
    }

    bindStatusButton(
        'sendora-test-connection',
        'sendora-connection-result',
        'sendora_test_connection',
        i18n.testing || 'Testando…',
        function () {
            var keyInput = document.getElementById('sendora-api-key');
            return keyInput && keyInput.value ? { api_key: keyInput.value } : {};
        }
    );

    var disconnectBtn = document.getElementById('sendora-disconnect');
    if (disconnectBtn) {
        disconnectBtn.addEventListener('click', function () {
            if (!window.confirm(i18n.disconnectConfirm || 'Desconectar?')) {
                return;
            }
            disconnectBtn.disabled = true;
            var result = document.getElementById('sendora-connection-result');
            if (result) {
                result.textContent = i18n.disconnecting || 'Desconectando…';
            }
            postAdmin('sendora_disconnect', {})
                .then(function (data) {
                    if (data.ok) {
                        window.location.reload();
                        return;
                    }
                    if (result) {
                        result.textContent = data.message || i18n.failed;
                        result.classList.add('is-error');
                    }
                })
                .catch(function (error) {
                    if (result) {
                        result.textContent = error.message;
                        result.classList.add('is-error');
                    }
                })
                .finally(function () {
                    disconnectBtn.disabled = false;
                });
        });
    }

    bindStatusButton(
        'sendora-test-event',
        'sendora-connection-result',
        'sendora_test_event',
        i18n.sendingEvent || 'Enviando evento…'
    );
}());
