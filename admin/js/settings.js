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
            $result.attr('class', 'wpaip-test-result fail').text('⚠ Digite uma API key para testar.');
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
        const $toggle = $('<button type="button" class="button" style="margin-left:4px; padding: 4px 8px; font-size:11px;">👁</button>');
        $input.after($toggle);
        $toggle.on('click', function () {
            const type = $input.attr('type') === 'password' ? 'text' : 'password';
            $input.attr('type', type);
            $toggle.text(type === 'password' ? '👁' : '🙈');
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
                $status.css('color', 'green').text('✓ Modelos carregados');
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

}(jQuery));
