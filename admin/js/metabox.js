/* global wpaipMetabox, tinymce, wp */
(function ($) {
    'use strict';

    const cfg       = wpaipMetabox;
    const isGuten   = cfg.is_gutenberg;

    // ── Modal Flutuante ───────────────────────────────────────────────────────
    $(function () {
        const $panel = $('#wpaip-panel-root');
        if (!$panel.length) return;

        const $trigger = $('<button type="button" id="wpaip-floating-trigger" title="AI Publisher"><span class="dashicons dashicons-superhero"></span></button>');
        const $modal = $('<div id="wpaip-floating-modal" class="wpaip-dark-theme" style="display:none;"></div>');
        const $header = $('<div class="wpaip-modal-header"><h3>🤖 AI Publisher</h3><button type="button" class="wpaip-modal-close">&times;</button></div>');
        
        $modal.append($header).append($panel);
        $('body').append($trigger).append($modal);

        // Oculta metabox original
        $('#wpaip-panel').hide();
        $panel.closest('.postbox').hide();

        $trigger.on('click', function () {
            $modal.fadeToggle(200);
        });

        $header.find('.wpaip-modal-close').on('click', function () {
            $modal.fadeOut(200);
        });
    });

    // ── Utilitários ────────────────────────────────────────────────────────────

    function setStatus($el, type, msg) {
        $el.attr('class', 'wpaip-status ' + type).text(msg).show();
    }

    function disableBtns(state) {
        $('.wpaip-btn').prop('disabled', state);
    }

    function getSelectedText() {
        if (isGuten) {
            // Gutenberg: usa seleção nativa do browser dentro do iframe
            try {
                const editorFrame = document.querySelector('iframe[name="editor-canvas"]');
                const doc = editorFrame ? editorFrame.contentDocument : document;
                return doc.getSelection ? doc.getSelection().toString() : '';
            } catch (e) {
                return '';
            }
        }
        // Editor Clássico
        if (typeof tinymce !== 'undefined' && tinymce.activeEditor) {
            return tinymce.activeEditor.selection.getContent({ format: 'text' });
        }
        return '';
    }

    function insertTextInEditor(text) {
        if (isGuten) {
            // Gutenberg: insere um parágrafo via dispatch
            if (typeof wp !== 'undefined' && wp.data) {
                const { createBlock } = wp.blocks;
                const { insertBlocks } = wp.data.dispatch('core/block-editor');
                const block = createBlock('core/html', { content: text });
                insertBlocks(block);
            }
        } else {
            // Editor Clássico (TinyMCE)
            if (typeof tinymce !== 'undefined' && tinymce.activeEditor) {
                tinymce.activeEditor.execCommand('mceInsertContent', false, text);
            } else {
                // Fallback: textarea
                const ta = document.getElementById('content');
                if (ta) ta.value += '\n\n' + text;
            }
        }
    }

    function insertImageInEditor(html) {
        if (isGuten) {
            if (typeof wp !== 'undefined' && wp.data) {
                const { createBlock } = wp.blocks;
                const { insertBlocks } = wp.data.dispatch('core/block-editor');
                const block = createBlock('core/html', { content: html });
                insertBlocks(block);
            }
        } else {
            if (typeof tinymce !== 'undefined' && tinymce.activeEditor) {
                tinymce.activeEditor.execCommand('mceInsertContent', false, html);
            }
        }
    }

    // ── Filtro de modelos por provider ─────────────────────────────────────────

    function filterModels(provider) {
        $('#wpaip-llm-model option').each(function () {
            const show = $(this).data('provider') === provider;
            $(this).toggle(show);
            if (show) $(this).prop('selected', true);
        });
    }

    $('#wpaip-llm-provider').on('change', function () {
        filterModels($(this).val());
    });

    // Inicializa
    filterModels($('#wpaip-llm-provider').val());

    // ── Geração de Texto ───────────────────────────────────────────────────────

    $('.wpaip-btn[data-mode]').on('click', function () {
        const mode     = $(this).data('mode');
        const $status  = $('#wpaip-text-status');
        let prompt     = $.trim($('#wpaip-prompt').val());

        // Para expand/summarize, pega texto selecionado se prompt vazio
        if (!prompt && (mode === 'expand' || mode === 'summarize')) {
            prompt = getSelectedText();
        }

        if (!prompt) {
            setStatus($status, 'error', cfg.strings.prompt_empty);
            return;
        }

        disableBtns(true);
        setStatus($status, 'loading', cfg.strings.generating);

        $.post(cfg.ajax_url, {
            action:   'wpaip_generate_text',
            nonce:    cfg.nonce,
            prompt:   prompt,
            provider: $('#wpaip-llm-provider').val(),
            model:    $('#wpaip-llm-model').val(),
            mode:     mode,
        })
        .done(function (res) {
            if (res.success && res.data.text) {
                insertTextInEditor(res.data.text);
                setStatus($status, 'success', cfg.strings.success);
            } else {
                setStatus($status, 'error', cfg.strings.error + (res.data.message || 'Erro desconhecido'));
            }
        })
        .fail(function () {
            setStatus($status, 'error', cfg.strings.error + 'Falha na requisição.');
        })
        .always(function () {
            disableBtns(false);
        });
    });

    // ── Imagem de Capa ─────────────────────────────────────────────────────────

    $('#wpaip-btn-featured').on('click', function () {
        const $status  = $('#wpaip-image-status');
        let prompt     = $.trim($('#wpaip-image-prompt').val());

        // Fallback: usa título do post
        if (!prompt) {
            prompt = $('#title').val()
                  || $('[data-type="core/post-title"] .rich-text').text()
                  || 'Imagem profissional para artigo de blog';
        }

        disableBtns(true);
        setStatus($status, 'loading', cfg.strings.gen_image);

        $.post(cfg.ajax_url, {
            action:   'wpaip_generate_featured_image',
            nonce:    cfg.nonce,
            post_id:  cfg.post_id,
            prompt:   prompt,
            provider: $('#wpaip-image-provider').val(),
        })
        .done(function (res) {
            if (res.success) {
                // Mostra preview
                $('#wpaip-featured-img').attr('src', res.data.thumb_url);
                $('#wpaip-featured-preview').show();

                // Atualiza o box de featured image do WordPress (editor clássico)
                if (!isGuten && typeof wp !== 'undefined' && wp.media) {
                    // Força refresh do thumbnail nativo
                    $('#postimagediv').find('img').attr('src', res.data.thumb_url);
                    $('#_thumbnail_id').val(res.data.attachment_id);
                }

                setStatus($status, 'success', cfg.strings.success);
            } else {
                setStatus($status, 'error', cfg.strings.error + (res.data.message || ''));
            }
        })
        .fail(function () {
            setStatus($status, 'error', cfg.strings.error + 'Falha na requisição.');
        })
        .always(function () {
            disableBtns(false);
        });
    });

    // ── Imagem Inline ──────────────────────────────────────────────────────────

    $('#wpaip-btn-inline').on('click', function () {
        const $status = $('#wpaip-image-status');
        const prompt  = window.prompt(cfg.strings.image_prompt);

        if (!prompt) return;

        disableBtns(true);
        setStatus($status, 'loading', cfg.strings.gen_image);

        $.post(cfg.ajax_url, {
            action:   'wpaip_generate_inline_image',
            nonce:    cfg.nonce,
            post_id:  cfg.post_id,
            prompt:   prompt,
            provider: $('#wpaip-image-provider').val(),
        })
        .done(function (res) {
            if (res.success) {
                insertImageInEditor(res.data.html);
                setStatus($status, 'success', cfg.strings.success);
            } else {
                setStatus($status, 'error', cfg.strings.error + (res.data.message || ''));
            }
        })
        .fail(function () {
            setStatus($status, 'error', cfg.strings.error + 'Falha na requisição.');
        })
        .always(function () {
            disableBtns(false);
        });
    });

}(jQuery));
