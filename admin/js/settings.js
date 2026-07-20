/* global wpaipSettings, jQuery */
(function ($) {
    'use strict';

    const cfg = wpaipSettings;

    // ── Teste de API Key ───────────────────────────────────────────────────────

    $('.wpaip-test-btn').on('click', function () {
        const $btn      = $(this);
        const provider  = $btn.data('provider');
        const inputSel  = $btn.data('input');
        const $input    = $(inputSel);
        const $result   = $('#wpaip-result-' + provider);
        const api_key   = $.trim($input.val());
        const hasSaved  = $btn.closest('.wpaip-api-row').find('.wpaip-badge--ok').length > 0;

        if (!api_key && !hasSaved) {
            $result.attr('class', 'wpaip-test-result fail').text('Digite uma API key para testar.');
            return;
        }

        $btn.prop('disabled', true).text(cfg.strings.testing);
        $result.attr('class', 'wpaip-test-result').text('');

        $.post(cfg.ajax_url, {
            action:   'wpaip_test_api_key',
            nonce:    cfg.nonce,
            provider: provider,
            api_key:  api_key,
        })
        .done(function (res) {
            if (res.success) {
                $result.attr('class', 'wpaip-test-result ok').text(cfg.strings.success);
            } else {
                $result.attr('class', 'wpaip-test-result fail').text(cfg.strings.fail + ': ' + (res.data.message || ''));
            }
        })
        .fail(function () {
            $result.attr('class', 'wpaip-test-result fail').text(cfg.strings.fail);
        })
        .always(function () {
            $btn.prop('disabled', false).text('Testar');
        });
    });

    // ── Toggle visibilidade da senha ───────────────────────────────────────────

    $('input[type="password"].wpaip-input').each(function () {
        const $input = $(this);
        const $toggle = $('<button type="button" class="button" style="margin-left:4px; padding: 4px 8px; font-size:11px;">Mostrar</button>');
        $input.after($toggle);
        $toggle.on('click', function () {
            const type = $input.attr('type') === 'password' ? 'text' : 'password';
            $input.attr('type', type);
            $toggle.text(type === 'password' ? 'Mostrar' : 'Ocultar');
        });
    });

    // ── Buscar Modelos Hugging Face ───────────────────────────────────────────

    $('#wpaip-hf-load-models').on('click', function () {
        const $btn = $(this);
        const $select = $('#wpaip-hf-model');
        const $status = $('#wpaip-hf-models-status');
        const savedVal = $select.val();

        $btn.prop('disabled', true);
        $status.css('color', '#888').text(cfg.strings.loading_models);

        $.post(cfg.ajax_url, {
            action: 'wpaip_list_hf_models',
            nonce: cfg.nonce
        })
        .done(function (res) {
            if (res.success && res.data.models) {
                $select.empty();
                res.data.models.forEach(function (m) {
                    if (m.id) {
                        const likesStr = m.likes ? ' (' + m.likes + ' likes)' : '';
                        const isSelected = m.id === savedVal ? ' selected' : '';
                        $select.append('<option value="' + m.id + '"' + isSelected + '>' + m.id + likesStr + '</option>');
                    }
                });
                $status.css('color', 'green').text('Modelos carregados');
            } else {
                $status.css('color', 'red').text(res.data.message || cfg.strings.models_error);
            }
        })
        .fail(function () {
            $status.css('color', 'red').text(cfg.strings.models_error);
        })
        .always(function () {
            $btn.prop('disabled', false);
        });
    });

    // ── Buscar Modelos Poe.com ────────────────────────────────────────────────

    $('#wpaip-poe-load-models').on('click', function () {
        const $btn = $(this);
        const $select = $('#wpaip-poe-bot-select');
        const $status = $('#wpaip-poe-models-status');
        const $input = $('#wpaip-poe-bot');
        const savedVal = $input.val();

        $btn.prop('disabled', true);
        $status.css('color', '#888').text(cfg.strings.loading_models);

        $.post(cfg.ajax_url, {
            action: 'wpaip_list_poe_bots',
            nonce: cfg.nonce
        })
        .done(function (res) {
            if (res.success && res.data.bots) {
                $select.empty();
                let foundSaved = false;
                res.data.bots.forEach(function (b) {
                    if (b.id) {
                        const isSelected = b.id === savedVal ? ' selected' : '';
                        if (b.id === savedVal) foundSaved = true;
                        $select.append('<option value="' + b.id + '"' + isSelected + '>' + b.id + '</option>');
                    }
                });
                // Readiciona a opção customizada no final
                const customSelected = (!savedVal || !foundSaved) ? ' selected' : '';
                $select.append('<option value="custom"' + customSelected + '>Outro bot (Digitar handle)...</option>');
                
                $status.css('color', 'green').text('Modelos carregados');
                $select.trigger('change');
            } else {
                $status.css('color', 'red').text(res.data.message || 'Erro ao carregar bots. Verifique a chave do Poe.');
            }
        })
        .fail(function () {
            $status.css('color', 'red').text('Erro de rede.');
        })
        .always(function () {
            $btn.prop('disabled', false);
        });
    });

    // ── Controle do Select de Bots do Poe ─────────────────────────────────────

    $('#wpaip-poe-bot-select').on('change', function () {
        const val = $(this).val();
        const $input = $('#wpaip-poe-bot');
        if (val === 'custom') {
            $input.show();
        } else {
            $input.val(val).hide();
        }
    });

    // ── Versão de texto dinâmica por provider ─────────────────────────────────

    function updateTextModelVisibility(provider) {
        $('.wpaip-model-group').hide();
        $('.wpaip-model-group[data-provider="' + provider + '"]').show();
    }

    $('#wpaip-default-llm').on('change', function () {
        updateTextModelVisibility($(this).val());
    });

    // Init
    updateTextModelVisibility($('#wpaip-default-llm').val());

    // ── Modelos de imagem visíveis condicionalmente (Hugging Face / Poe) ─────

    function updateImageModelVisibility(provider) {
        if (provider === 'huggingface') {
            $('#wpaip-hf-model-wrapper').show();
            $('#wpaip-poe-model-wrapper').hide();
        } else if (provider === 'poe') {
            $('#wpaip-hf-model-wrapper').hide();
            $('#wpaip-poe-model-wrapper').show();
        } else {
            $('#wpaip-hf-model-wrapper').hide();
            $('#wpaip-poe-model-wrapper').hide();
        }
    }

    $('#wpaip-default-image').on('change', function () {
        updateImageModelVisibility($(this).val());
    });

    // Init
    updateImageModelVisibility($('#wpaip-default-image').val());

    // ── Ativar/Testar Licença ──────────────────────────────────────────────────

    $('#wpaip-license-test-btn').on('click', function () {
        const $btn    = $(this);
        const $result = $('#wpaip-license-test-result');
        const hasSaved = $('.wpaip-badge--ok', $btn.closest('.wpaip-api-row')).length > 0;
        const licenseKey = $.trim($('#wpaip-license-key').val());
        const serverUrl  = $.trim($('#wpaip-license-server-url').val());

        if (!licenseKey && !hasSaved) {
            $result.attr('class', 'wpaip-test-result fail').text('Configure a chave de licença primeiro.');
            return;
        }
        if (!serverUrl) {
            $result.attr('class', 'wpaip-test-result fail').text('Preencha a URL do servidor de licenças.');
            return;
        }

        $btn.prop('disabled', true).text(cfg.strings.testing);
        $result.attr('class', 'wpaip-test-result').text('');

        $.post(cfg.ajax_url, {
            action:             'wpaip_activate_license',
            nonce:              cfg.nonce,
            license_key:        licenseKey,
            license_server_url: serverUrl,
        })
        .done(function (res) {
            if (res.success) {
                $result.attr('class', 'wpaip-test-result ok').text(res.data.message || 'Ativada com sucesso!');
                // Atualizar o badge de status na tela
                const $badge = $btn.closest('.wpaip-api-row').find('.wpaip-badge');
                $badge.attr('class', 'wpaip-badge wpaip-badge--ok').text('Ativada');
            } else {
                $result.attr('class', 'wpaip-test-result fail').text(res.data.message || 'Falha na ativação.');
            }
        })
        .fail(function (xhr) {
            let errorMsg = 'Erro de rede ou servidor.';
            if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                errorMsg = xhr.responseJSON.data.message;
            }
            $result.attr('class', 'wpaip-test-result fail').text('Falha: ' + errorMsg);
        })
        .always(function () {
            $btn.prop('disabled', false).text('Ativar Licença');
        });
    });

    // ── Limpar cache Licença ──────────────────────────────────────────────────

    $('#wpaip-license-clear-cache').on('click', function () {
        const $btn    = $(this);
        const $result = $('#wpaip-license-cache-result');

        $btn.prop('disabled', true).text('Limpando…');
        $result.css('color', '#888').text('');

        $.post(cfg.ajax_url, {
            action: 'wpaip_clear_license_cache',
            nonce:  cfg.nonce,
        })
        .done(function (res) {
            if (res.success) {
                $result.css('color', 'green').text(res.data.message || 'Cache limpo!');
            } else {
                $result.css('color', 'red').text(res.data.message || 'Erro.');
            }
        })
        .fail(function () {
            $result.css('color', 'red').text('Falha na requisição.');
        })
        .always(function () {
            $btn.prop('disabled', false).text('Limpar meu cache de licença');
        });
    });

    // ── Melhorar Prompt de Sistema ─────────────────────────────────────────────

    $('#wpaip-btn-improve-prompt').on('click', function () {
        const $btn    = $(this);
        const $status = $('#wpaip-improve-prompt-status');
        const $prompt = $('#wpaip-system-prompt');
        const promptVal = $.trim($prompt.val());

        if (!promptVal) {
            $status.css('color', 'red').text('Escreva um prompt inicial para que possamos melhorá-lo.').show();
            return;
        }

        const provider = $('#wpaip-default-llm').val();
        const model = $('.wpaip-model-group[data-provider="' + provider + '"] select').val();

        $btn.prop('disabled', true).text('Melhorando…');
        $status.css('color', '#888').text('Melhorando prompt com ' + provider + '...').show();

        $.post(cfg.ajax_url, {
            action:        'wpaip_improve_prompt',
            nonce:         cfg.nonce,
            system_prompt: promptVal,
            provider:      provider,
            model:         model
        })
        .done(function (res) {
            if (res.success && res.data.prompt) {
                $prompt.val(res.data.prompt);
                $status.css('color', 'green').text('Prompt melhorado com sucesso!');
            } else {
                $status.css('color', 'red').text('Erro: ' + (res.data.message || 'Erro desconhecido.'));
            }
        })
        .fail(function () {
            $status.css('color', 'red').text('Falha na requisição.');
        })
        .always(function () {
            $btn.prop('disabled', false).text('✦ Melhorar Prompt');
            setTimeout(function () { $status.fadeOut(300); }, 5000);
        });
    });

}(jQuery));
