/* global wpaipMetabox, tinymce, wp */
(function ($) {
    'use strict';

    const cfg       = wpaipMetabox;
    const isGuten   = cfg.is_gutenberg;

    // ── Modal Flutuante Dark ──────────────────────────────────────────────────
    $(function () {
        const $panel = $('#wpaip-panel-root');
        if (!$panel.length) return;

        const $trigger    = $('<button type="button" id="wpaip-floating-trigger" title="POST F\u00c1CIL I.A."><span class="dashicons dashicons-superhero"></span></button>');
        const $modal      = $('<div id="wpaip-floating-modal" class="wpaip-dark-theme" style="display:none;"></div>');
        const $header     = $('<div class="wpaip-modal-header"></div>');
        const $titleGroup = $('<div class="wpaip-modal-title-group"></div>');
        const $saveDot    = $('<button type="button" id="wpaip-save-dot" class="wpaip-save-dot wpaip-save-dot--saved" title="Salvar post"><svg viewBox="0 0 16 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M2 1a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4.5L10.5 1H2zm8.5 0v3.5H13L10.5 1zM5 9h6v4H5V9zm1 1v2h4v-2H6z"/></svg></button>');
        const $title      = $('<h3>POST F\u00c1CIL I.A.</h3>');
        const $closeBtn   = $('<button type="button" class="wpaip-modal-close">&times;</button>');

        $titleGroup.append($saveDot).append($title);
        $header.append($titleGroup).append($closeBtn);
        $modal.append($header).append($panel);
        $('body').append($trigger).append($modal);

        // Oculta o postbox vazio (painel está no modal)
        $panel.closest('.postbox').hide();

        $trigger.on('click', function () {
            $modal.fadeToggle(200);
        });

        $closeBtn.on('click', function () {
            $modal.fadeOut(200);
        });

        initSaveDot($saveDot);
    });

    function initSaveDot($dot) {
        function setSaved(saved) {
            $dot
                .removeClass('wpaip-save-dot--saved wpaip-save-dot--unsaved')
                .addClass(saved ? 'wpaip-save-dot--saved' : 'wpaip-save-dot--unsaved')
                .attr('title', saved ? 'Post salvo' : 'Salvar post (alterações não salvas)');
        }

        // Estado inicial
        setSaved(true);

        if (isGuten && typeof wp !== 'undefined' && wp.data) {
            // Gutenberg: assina o store para detectar mudanças
            wp.data.subscribe(function () {
                try {
                    const editorSelect = wp.data.select('core/editor');
                    if (!editorSelect) return;

                    const isDirty   = typeof editorSelect.isEditedPostDirty === 'function' ? editorSelect.isEditedPostDirty() : false;
                    const isSaving  = typeof editorSelect.isSavingPost === 'function' ? editorSelect.isSavingPost() : false;

                    if (isSaving) {
                        $dot.addClass('wpaip-save-dot--saving');
                    } else {
                        $dot.removeClass('wpaip-save-dot--saving');
                        setSaved(!isDirty);
                    }
                } catch (err) {
                    // Evita quebrar se o editor ainda não estiver totalmente carregado
                }
            });
        } else {
            // Editor Clássico: monitora alterações no formulário do post
            var dirty = false;
            $(document).on('input change', '#title, #content, #excerpt', function () {
                if (!dirty) { 
                    dirty = true; 
                    setSaved(false); 
                }
            });
            // Quando salva, volta para verde
            $('#post').on('submit', function () {
                dirty = false;
                setSaved(true);
            });
        }

        // ── Clique na bolinha salva o post ─────────────────────────────────────
        $dot.on('click', function () {
            if ($dot.hasClass('wpaip-save-dot--saving')) return;
            $dot.addClass('wpaip-save-dot--saving');

            if (isGuten && typeof wp !== 'undefined' && wp.data) {
                try {
                    const editorDispatch = wp.data.dispatch('core/editor');
                    if (editorDispatch && typeof editorDispatch.savePost === 'function') {
                        editorDispatch.savePost()
                            .catch(function () { setSaved(false); })
                            .finally(function () { $dot.removeClass('wpaip-save-dot--saving'); });
                    } else {
                        $dot.removeClass('wpaip-save-dot--saving');
                    }
                } catch (e) {
                    $dot.removeClass('wpaip-save-dot--saving');
                }
            } else {
                var $save = $('#save-post');
                var $pub  = $('#publish');
                if ($save.length)      { $save.trigger('click'); }
                else if ($pub.length)  { $pub.trigger('click');  }
                setTimeout(function () {
                    $dot.removeClass('wpaip-save-dot--saving');
                    setSaved(true);
                }, 1800);
            }
        });
    }

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

    function setPostTitle(title) {
        if (isGuten) {
            // Gutenberg: atualiza via store
            if (typeof wp !== 'undefined' && wp.data) {
                wp.data.dispatch('core/editor').editPost({ title: title });
            }
        } else {
            // Editor Clássico: campo #title
            const $titleField = $('#title');
            if ($titleField.length) {
                $titleField.val(title).trigger('change');
                // Força atualização do slug preview
                if (typeof wp !== 'undefined' && wp.title) {
                    wp.title.set(title);
                }
            }
        }
    }

    // ── Referências Externas ───────────────────────────────────────────────────

    var references = []; // { url, text }

    function isValidUrl(str) {
        try { return /^https?:\/\/.+/.test(str); } catch (e) { return false; }
    }

    function renderRefList() {
        var $list = $('#wpaip-ref-list');
        $list.empty();
        if (!references.length) return;

        references.forEach(function (ref, idx) {
            var loaded   = ref.text ? ' wpaip-ref--loaded' : '';
            var icon     = ref.text ? '✓' : '○';
            var title    = ref.url.replace(/^https?:\/\//, '').substring(0, 42);
            var $item = $(
                '<li class="wpaip-ref-item' + loaded + '" data-idx="' + idx + '">' +
                    '<span class="wpaip-ref-icon">' + icon + '</span>' +
                    '<span class="wpaip-ref-url" title="' + ref.url + '">' + title + '</span>' +
                    '<button type="button" class="wpaip-ref-remove" title="Remover">×</button>' +
                '</li>'
            );
            $list.append($item);
        });
    }

    $('#wpaip-btn-ref-add').on('click', function () {
        var url = $.trim($('#wpaip-ref-input').val());
        var $st = $('#wpaip-ref-status');

        if (!isValidUrl(url)) {
            setStatus($st, 'error', cfg.strings.ref_invalid);
            return;
        }
        if (references.some(function (r) { return r.url === url; })) {
            setStatus($st, 'error', cfg.strings.ref_duplicate);
            return;
        }

        references.push({ url: url, text: '' });
        renderRefList();
        $('#wpaip-ref-input').val('');
        $st.hide();
    });

    // Adicionar com Enter
    $('#wpaip-ref-input').on('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); $('#wpaip-btn-ref-add').trigger('click'); }
    });

    // Remover item
    $('#wpaip-ref-list').on('click', '.wpaip-ref-remove', function () {
        var idx = parseInt($(this).closest('.wpaip-ref-item').data('idx'), 10);
        references.splice(idx, 1);
        renderRefList();
    });

    // ── Geração de Texto ───────────────────────────────────────────────────────

    /**
     * Dispara o AJAX de geração com as referências já resolvidas.
     * provider e model são enviados vazios: o PHP usa o padrão das configurações.
     */
    function doGenerate(prompt, mode, refUrls, refTexts, $status) {
        setStatus($status, 'loading', cfg.strings.generating);

        $.post(cfg.ajax_url, {
            action:     'wpaip_generate_text',
            nonce:      cfg.nonce,
            prompt:     prompt,
            provider:   '',
            model:      '',
            mode:       mode,
            paragraphs: parseInt($('#wpaip-paragraphs').val(), 10) || 5,
            ref_urls:   refUrls,
            ref_texts:  refTexts,
        })
        .done(function (res) {
            if (res.success && res.data.text) {
                insertTextInEditor(res.data.text);
                if (res.data.title) {
                    setPostTitle(res.data.title);
                }
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
    }

    $('.wpaip-btn[data-mode]').on('click', function () {
        var mode    = $(this).data('mode');
        var $status = $('#wpaip-text-status');
        var prompt  = $.trim($('#wpaip-prompt').val());

        // Para expand/summarize, pega texto selecionado se prompt vazio
        if (!prompt && (mode === 'expand' || mode === 'summarize')) {
            prompt = getSelectedText();
        }

        if (!prompt) {
            setStatus($status, 'error', cfg.strings.prompt_empty);
            return;
        }

        disableBtns(true);

        var refUrls = references.map(function (r) { return r.url; });

        // Sem referências: gera direto
        if (!refUrls.length) {
            doGenerate(prompt, mode, [], [], $status);
            return;
        }

        // Com referências: busca conteúdo e sempre gera depois (mesmo se fetch falhar)
        var $refSt = $('#wpaip-ref-status');
        setStatus($refSt, 'loading', cfg.strings.ref_fetching);

        $.post(cfg.ajax_url, {
            action: 'wpaip_fetch_references',
            nonce:  cfg.nonce,
            urls:   refUrls,
        })
        .always(function (res) {
            var refTexts = refUrls.map(function () { return ''; });

            // Tenta ler o resultado mesmo se o status HTTP foi erro
            var data = (res && res.success && res.data && res.data.references) ? res.data.references : null;

            if (data) {
                data.forEach(function (r, idx) {
                    if (references[idx]) {
                        references[idx].text = r.text || '';
                    }
                    refTexts[idx] = r.text || '';
                });
                renderRefList();
                setStatus($refSt, 'success', cfg.strings.ref_fetch_ok);
            } else {
                setStatus($refSt, 'error', cfg.strings.ref_fetch_fail);
            }

            // Gera o texto com as refs (mesmo sem conteúdo extraído, URLs são enviadas)
            doGenerate(prompt, mode, refUrls, refTexts, $status);
        });
    });

    // ── Imagem de Capa ─────────────────────────────────────────────────────────
    // provider enviado vazio: PHP usa default_image das configurações

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
            provider: '',
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
    // provider enviado vazio: PHP usa default_image das configurações

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
            provider: '',
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

    });

}(jQuery));
