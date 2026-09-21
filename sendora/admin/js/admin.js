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

    document.querySelectorAll('[data-sendora-woo-card]').forEach(function (card) {
        var toggle = card.querySelector('[data-sendora-woo-toggle]');
        var body = card.querySelector('[data-sendora-woo-body]');
        var state = card.querySelector('[data-sendora-woo-state]');
        var template = card.querySelector('[data-sendora-woo-template]');
        if (!toggle || !body) {
            return;
        }

        function sync() {
            var on = !!toggle.checked;
            card.classList.toggle('is-enabled', on);
            if (on) {
                body.removeAttribute('hidden');
            } else {
                body.setAttribute('hidden', 'hidden');
            }
            if (state) {
                state.textContent = on ? 'Ativo' : 'Desativado';
            }
            if (template) {
                // Message is optional when CRM (funnel/stage or tags) is configured.
                template.removeAttribute('required');
            }
        }

        toggle.addEventListener('change', sync);
        sync();

        var funnel = card.querySelector('[data-sendora-woo-funnel]');
        var stage = card.querySelector('[data-sendora-woo-stage]');
        if (funnel && stage) {
            funnel.addEventListener('change', function () {
                filterStages(stage, funnel.value, stage.getAttribute('data-selected') || stage.value);
            });
            filterStages(stage, funnel.value, stage.getAttribute('data-selected') || stage.value);
        }
    });

    function filterStages(select, funnelId, selectedId) {
        var stages = (SendoraAdmin && SendoraAdmin.stages) ? SendoraAdmin.stages : [];
        var placeholder = (i18n.selectStage || 'Selecione um estágio');
        var html = '<option value="">' + placeholder + '</option>';
        stages.forEach(function (row) {
            if (funnelId && row.funnel_id && row.funnel_id !== funnelId) {
                return;
            }
            if (!funnelId) {
                return;
            }
            var sel = selectedId && selectedId === row.id ? ' selected' : '';
            html += '<option value="' + row.id + '"' + sel + ' data-funnel="' + (row.funnel_id || '') + '">'
                + (row.label || row.id) + '</option>';
        });
        select.innerHTML = html;
        if (selectedId) {
            select.setAttribute('data-selected', selectedId);
        }
    }
    document.querySelectorAll('[data-sendora-woo-card]').forEach(function (card) {
        var provider = card.querySelector('[data-sendora-provider]');
        var instance = card.querySelector('[data-sendora-instance]');
        if (provider) {
            provider.addEventListener('change', function () {
                refreshChannelCard(card, true);
            });
        }
        if (instance) {
            instance.addEventListener('change', function () {
                if (provider && provider.value === 'meta') {
                    loadMetaTemplatesForCard(card);
                }
            });
        }
    });

    function refreshChannelCard(card, resetTemplate) {
        var provider = card.querySelector('[data-sendora-provider]');
        var instance = card.querySelector('[data-sendora-instance]');
        var templateSelect = card.querySelector('[data-sendora-woo-template]');
        var help = card.querySelector('[data-sendora-instance-help]');
        var metaOnly = card.querySelectorAll('[data-sendora-meta-only]');
        var isMeta = provider && provider.value === 'meta';
        var instances = isMeta ? (SendoraAdmin.wabas || []) : (SendoraAdmin.connections || []);
        var selectedInstance = instance ? instance.value : '';
        var required = templateSelect && templateSelect.hasAttribute('required');

        metaOnly.forEach(function (el) {
            if (isMeta) {
                el.removeAttribute('hidden');
            } else {
                el.setAttribute('hidden', 'hidden');
            }
        });

        if (help) {
            help.textContent = isMeta
                ? (i18n.instanceHelpMeta || '')
                : (i18n.instanceHelpEvolution || '');
        }

        var channelHelp = card.querySelector('[data-sendora-channel-help]');
        if (channelHelp) {
            channelHelp.textContent = isMeta
                ? (i18n.channelHelpMeta || '')
                : (i18n.channelHelpEvolution || '');
        }

        if (instance) {
            var instHtml = '<option value="">' + (i18n.defaultInstance || 'Padrão da conta') + '</option>';
            instances.forEach(function (row) {
                var sel = selectedInstance && selectedInstance === row.id ? ' selected' : '';
                instHtml += '<option value="' + escapeAttr(row.id) + '"' + sel + '>'
                    + escapeHtml(row.name || row.id) + '</option>';
            });
            instance.innerHTML = instHtml;
        }

        if (!templateSelect) {
            return;
        }

        if (isMeta) {
            loadMetaTemplatesForCard(card, resetTemplate);
            return;
        }

        fillTemplateSelect(
            templateSelect,
            SendoraAdmin.templates || [],
            resetTemplate ? '' : templateSelect.value,
            required
        );
    }

    function loadMetaTemplatesForCard(card, resetTemplate) {
        var templateSelect = card.querySelector('[data-sendora-woo-template]');
        var instance = card.querySelector('[data-sendora-instance]');
        var language = card.querySelector('[data-sendora-template-language]');
        if (!templateSelect) {
            return;
        }
        var required = templateSelect.hasAttribute('required');
        var previous = resetTemplate ? '' : templateSelect.value;
        templateSelect.innerHTML = '<option value="">'
            + (i18n.loadingTemplates || 'Carregando…') + '</option>';

        postAdmin('sendora_list_meta_templates', {
            instance_id: instance ? instance.value : ''
        })
            .then(function (data) {
                var list = (data && data.templates) ? data.templates : (SendoraAdmin.metaTemplates || []);
                fillTemplateSelect(templateSelect, list, previous, required);
                if (language && templateSelect.selectedOptions[0]) {
                    var lang = templateSelect.selectedOptions[0].getAttribute('data-language');
                    if (lang) {
                        language.value = lang;
                    }
                }
            })
            .catch(function () {
                fillTemplateSelect(
                    templateSelect,
                    SendoraAdmin.metaTemplates || [],
                    previous,
                    required
                );
            });
    }

    function fillTemplateSelect(select, list, selectedId, required) {
        var placeholder = required
            ? (i18n.selectMessage || 'Selecione uma mensagem')
            : (i18n.noneMessage || 'Nenhuma (só CRM)');
        var html = '<option value="">' + placeholder + '</option>';
        list.forEach(function (row) {
            var sel = selectedId && selectedId === row.id ? ' selected' : '';
            var langAttr = row.language ? ' data-language="' + escapeAttr(row.language) + '"' : '';
            html += '<option value="' + escapeAttr(row.id) + '"' + sel + langAttr + '>'
                + escapeHtml(row.name || row.id) + '</option>';
        });
        select.innerHTML = html;
    }

    function escapeAttr(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;');
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    document.querySelectorAll('[data-sendora-test-send]').forEach(function (button) {
        button.addEventListener('click', function () {
            var card = button.closest('[data-sendora-woo-card]');
            var result = card
                ? card.querySelector('[data-sendora-test-result]')
                : null;
            var templateSelect = card
                ? card.querySelector('[data-sendora-woo-template]')
                : null;
            var providerSelect = card
                ? card.querySelector('[data-sendora-provider]')
                : null;
            var instanceSelect = card
                ? card.querySelector('[data-sendora-instance]')
                : null;
            var languageInput = card
                ? card.querySelector('[data-sendora-template-language]')
                : null;
            var bodyVarsInput = card
                ? card.querySelector('[data-sendora-body-vars]')
                : null;
            var phoneInput = card
                ? card.querySelector('[data-sendora-test-phone]')
                : null;
            var templateId = templateSelect ? templateSelect.value : '';
            var testPhone = phoneInput ? phoneInput.value.trim() : '';

            if (!testPhone) {
                if (result) {
                    result.hidden = false;
                    result.className = 'sendora-inline-status is-error';
                    result.textContent = i18n.testNeedPhone
                        || 'Informe o telefone de teste (pode ser o seu).';
                }
                if (phoneInput) {
                    phoneInput.focus();
                }
                return;
            }

            button.disabled = true;
            if (result) {
                result.hidden = false;
                result.className = 'sendora-inline-status';
                result.textContent = i18n.sendingTest || 'Enviando teste…';
            }

            postAdmin('sendora_test_send', {
                source: button.getAttribute('data-source') || '',
                event: button.getAttribute('data-event') || '',
                form_id: button.getAttribute('data-form-id') || '',
                template_id: templateId,
                test_phone: testPhone,
                provider: providerSelect ? providerSelect.value : '',
                instance_id: instanceSelect ? instanceSelect.value : '',
                template_language: languageInput ? languageInput.value : '',
                body_vars: bodyVarsInput ? bodyVarsInput.value : ''
            })
                .then(function (data) {
                    if (result) {
                        result.textContent = data.message || (data.ok ? 'OK' : (i18n.failed || 'Falha'));
                        result.classList.add(data.ok ? 'is-success' : 'is-error');
                    }
                })
                .catch(function (error) {
                    if (result) {
                        result.textContent = error.message;
                        result.classList.add('is-error');
                    }
                })
                .finally(function () {
                    button.disabled = false;
                });
        });
    });

    // --- Onboarding wizard (plugin admin only) ---
    (function initOnboarding() {
        var wizard = document.getElementById('sendora-onboard-wizard');

        function onboardPost(onboardAction, extra) {
            var payload = Object.assign({ onboard_action: onboardAction }, extra || {});
            return postAdmin('sendora_onboarding', payload);
        }

        function finishForever(action) {
            if (wizard) {
                wizard.setAttribute('data-finishing', '1');
            }
            return onboardPost(action || 'dismiss')
                .then(function (data) {
                    if (!data || !data.ok) {
                        throw new Error((data && data.message) || 'Falha ao finalizar.');
                    }
                    if (wizard) {
                        wizard.remove();
                    }
                    document.body.classList.remove('sendora-onboard-open');
                    window.location.reload();
                })
                .catch(function (error) {
                    if (wizard) {
                        wizard.removeAttribute('data-finishing');
                    }
                    window.alert(error.message || 'Não foi possível finalizar o guia. Tente de novo.');
                });
        }

        function setWizardStep(step) {
            if (!wizard || wizard.getAttribute('data-finishing') === '1') {
                return;
            }
            step = Math.max(1, Math.min(4, step));
            wizard.setAttribute('data-step', String(step));
            wizard.querySelectorAll('[data-onboard-step]').forEach(function (el) {
                var n = parseInt(el.getAttribute('data-onboard-step'), 10);
                if (n === step) {
                    el.removeAttribute('hidden');
                } else {
                    el.setAttribute('hidden', 'hidden');
                }
            });
            wizard.querySelectorAll('.sendora-onboard__dots li').forEach(function (li, idx) {
                li.classList.remove('is-active', 'is-done');
                if (idx + 1 === step) {
                    li.classList.add('is-active');
                } else if (idx + 1 < step) {
                    li.classList.add('is-done');
                }
            });
            var prev = wizard.querySelector('[data-sendora-onboard-prev]');
            var next = wizard.querySelector('[data-sendora-onboard-next]');
            if (prev) {
                if (step <= 1) {
                    prev.setAttribute('hidden', 'hidden');
                } else {
                    prev.removeAttribute('hidden');
                }
            }
            if (next) {
                next.textContent = step >= 4
                    ? (i18n.onboardFinish || 'Concluir')
                    : (i18n.onboardNext || 'Próximo');
            }
            onboardPost('set_step', { wizard_step: step });
        }

        document.querySelectorAll('[data-sendora-onboard-action]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var act = btn.getAttribute('data-sendora-onboard-action');
                if (!act) {
                    return;
                }
                btn.disabled = true;
                if (act === 'dismiss' || act === 'complete') {
                    finishForever(act).finally(function () {
                        btn.disabled = false;
                    });
                    return;
                }
                var extra = {};
                if (act === 'channels_seen' && wizard) {
                    extra.wizard_step = 3;
                }
                onboardPost(act, extra)
                    .then(function (data) {
                        if (act === 'channels_seen' && wizard) {
                            setWizardStep(3);
                        }
                    })
                    .catch(function () { /* ignore */ })
                    .finally(function () {
                        btn.disabled = false;
                    });
            });
        });

        if (!wizard) {
            return;
        }

        document.body.classList.add('sendora-onboard-open');

        wizard.querySelectorAll('[data-sendora-onboard-dismiss]').forEach(function (el) {
            el.addEventListener('click', function () {
                finishForever('dismiss');
            });
        });

        var prevBtn = wizard.querySelector('[data-sendora-onboard-prev]');
        var nextBtn = wizard.querySelector('[data-sendora-onboard-next]');
        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                var step = parseInt(wizard.getAttribute('data-step') || '1', 10);
                setWizardStep(step - 1);
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                var step = parseInt(wizard.getAttribute('data-step') || '1', 10);
                if (step >= 4) {
                    finishForever('complete');
                    return;
                }
                if (step === 2) {
                    onboardPost('channels_seen', { wizard_step: 3 }).then(function () {
                        setWizardStep(3);
                    });
                    return;
                }
                setWizardStep(step + 1);
            });
        }
    }());
}());
