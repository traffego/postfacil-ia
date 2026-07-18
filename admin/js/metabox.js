/* global wpaipMetabox, tinymce, wp */

// ── Click do modal — delegation no document (não depende de wpaipMetabox) ──
(function ($) {
    $(document).on('click', '#wpaip-floating-trigger', function () {
        $('#wpaip-floating-modal').fadeToggle(200);
    });
    $(document).on('click', '.wpaip-modal-close', function () {
        $('#wpaip-floating-modal').fadeOut(200);
    });
    $(document).on('click', '#wpaip-btn-toggle-refs', function () {
        var $btn     = $(this);
        var $section = $('#wpaip-refs-section');
        $btn.toggleClass('is-open');
        $section.slideToggle(180);
    });

    // ── Botões redondos de parágrafos ──
    $(document).on('click', '.wpaip-para-btn[data-val]', function () {
        var val = parseInt($(this).data('val'), 10);
        $('.wpaip-para-btn').removeClass('is-active');
        $(this).addClass('is-active');
        $('#wpaip-paragraphs').val(val);
        $('#wpaip-btn-draft').data('paragraphs', val);
        var $more = $('#wpaip-para-more');
        if ($more.data('extra')) {
            $more.text('+').removeData('extra').removeClass('is-active');
        }
    });

    $(document).on('click', '#wpaip-para-more', function () {
        var $more   = $(this);
        var current = parseInt($('#wpaip-paragraphs').val(), 10) || 5;
        var next    = Math.min(current + 1, 20);
        $('.wpaip-para-btn').removeClass('is-active');
        $more.addClass('is-active').text(next).data('extra', true);
        $('#wpaip-paragraphs').val(next);
        $('#wpaip-btn-draft').data('paragraphs', next);
    });
    // Move o painel para o modal assim que estiver pronto
    $(function () {
        function tryMove() {
            var $panel = $('#wpaip-panel-root');
            var $modal = $('#wpaip-floating-modal');
            if (!$panel.length || !$modal.length) return false;
            if ($modal.data('moved')) return true;
            $modal.append($panel);
            $panel.closest('.postbox').hide();
            $modal.data('moved', true);
            return true;
        }
        if (!tryMove()) {
            var n = 0, t = setInterval(function () {
                if (tryMove() || ++n > 50) clearInterval(t);
            }, 200);
        }
    });
}(jQuery));

// ── Lógica principal (requer wpaipMetabox) ───────────────────────────────────
(function ($) {
    'use strict';
    if (typeof wpaipMetabox === 'undefined') return;

    const cfg     = wpaipMetabox;
    const isGuten = cfg.is_gutenberg;

    // Bolinha de salvar
    $(function () {
        initSaveDot($('#wpaip-save-dot'));
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
            paragraphs: parseInt($('#wpaip-btn-draft').data('paragraphs') || $('#wpaip-paragraphs').val(), 10) || 5,
            ref_urls:   refUrls,
            ref_texts:  refTexts,
        })
        .done(function (res) {
            if (res.success && res.data.text) {
                insertTextInEditor(res.data.text);
                if (res.data.title) {
                    setPostTitle(res.data.title);
                }
                if (res.data.search_warning) {
                    console.error(res.data.search_warning);
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

    // ── Lógica de Abas do Metabox ─────────────────────────────────────────────

    $(document).on('click', '.wpaip-tab-nav-btn', function() {
        var $btn = $(this);
        var tabId = $btn.data('tab');

        // Alterna botões
        $('.wpaip-tab-nav-btn')
            .removeClass('is-active')
            .css('border-bottom-color', 'transparent')
            .css('color', '#9ca3af');

        $btn
            .addClass('is-active')
            .css('border-bottom-color', '#6366f1')
            .css('color', '#fff');

        // Alterna conteúdo
        $('.wpaip-tab-content').hide();
        $('#wpaip-tab-' + tabId).show();
    });

    // ── Lógica do Drag and Drop (Soltar Imagem do Gemini) ─────────────────────

    var $dropzone = $('#wpaip-gemini-dropzone');
    var $dropStatus = $('#wpaip-gemini-drop-status');

    if ($dropzone.length) {
        // Prevenir comportamento padrão para eventos de arrastar
        $dropzone.on('dragover dragenter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (!$dropzone.hasClass('is-loading')) {
                $dropzone.addClass('is-dragover');
            }
        });

        $dropzone.on('dragleave dragend drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $dropzone.removeClass('is-dragover');
        });

        $dropzone.on('drop', function(e) {
            if ($dropzone.hasClass('is-loading')) return;

            var dt = e.originalEvent.dataTransfer;
            var files = dt.files;
            var imageUrl = '';

            // Tenta pegar a imagem arrastada do chat (via HTML ou URL)
            var html = dt.getData('text/html');
            if (html) {
                // Tenta extrair a URL de uma tag img contida no HTML
                var $temp = $('<div>' + html + '</div>');
                var src = $temp.find('img').attr('src');
                if (src) {
                    imageUrl = src;
                }
            }

            // Se não pegou via HTML, tenta via URL pura
            if (!imageUrl) {
                var rawUrl = dt.getData('text/plain') || dt.getData('URL');
                if (rawUrl && /^https?:\/\/.+/.test(rawUrl)) {
                    imageUrl = rawUrl;
                }
            }

            // Envia para o WordPress
            if (files && files.length > 0) {
                // Caso A: Arquivo físico solto
                uploadDroppedFile(files[0]);
            } else if (imageUrl) {
                // Caso B: Link de imagem solto
                importDroppedUrl(imageUrl);
            } else {
                setStatus($dropStatus, 'error', 'Nenhum formato de imagem reconhecido. Tente clicar, segurar e arrastar a imagem diretamente.');
            }
        });
    }

    function uploadDroppedFile(file) {
        var dropType = $('#wpaip-gemini-drop-type').val() || 'featured';
        var formData = new FormData();
        formData.append('action', 'wpaip_import_dropped_image');
        formData.append('nonce', cfg.nonce);
        formData.append('post_id', cfg.post_id);
        formData.append('type', dropType);
        formData.append('image_file', file);

        $dropzone.addClass('is-loading');
        setStatus($dropStatus, 'loading', cfg.strings.uploading);

        $.ajax({
            url: cfg.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                handleImportSuccess(res);
            },
            error: function() {
                setStatus($dropStatus, 'error', 'Falha ao enviar arquivo.');
            },
            complete: function() {
                $dropzone.removeClass('is-loading');
            }
        });
    }

    function importDroppedUrl(url) {
        var dropType = $('#wpaip-gemini-drop-type').val() || 'featured';
        $dropzone.addClass('is-loading');
        setStatus($dropStatus, 'loading', cfg.strings.uploading);

        $.post(cfg.ajax_url, {
            action: 'wpaip_import_dropped_image',
            nonce: cfg.nonce,
            post_id: cfg.post_id,
            type: dropType,
            image_url: url
        })
        .done(function(res) {
            handleImportSuccess(res);
        })
        .fail(function() {
            setStatus($dropStatus, 'error', 'Falha ao processar URL da imagem.');
        })
        .always(function() {
            $dropzone.removeClass('is-loading');
        });
    }

    function handleImportSuccess(res) {
        if (res.success) {
            setStatus($dropStatus, 'success', 'Imagem importada com sucesso!');
            if (res.data.type === 'featured') {
                // Capa: Mostra preview e atualiza box nativo do WP
                $('#wpaip-featured-img').attr('src', res.data.thumb_url);
                $('#wpaip-featured-preview').show();

                if (!isGuten && typeof wp !== 'undefined' && wp.media) {
                    $('#postimagediv').find('img').attr('src', res.data.thumb_url);
                    $('#_thumbnail_id').val(res.data.attachment_id);
                }
            } else {
                // Inline: insere no post
                insertImageInEditor(res.data.html);
            }
        } else {
            setStatus($dropStatus, 'error', res.data.message || 'Erro desconhecido ao importar.');
        }
    }

}(jQuery));
