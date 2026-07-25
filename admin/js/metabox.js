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

    var progressSimulationTimer = null;

    function startProgressOverlay(title, sub, icon) {
        if (progressSimulationTimer) clearInterval(progressSimulationTimer);
        $('#wpaip-dropzone-icon').text(icon || '📝');
        var currentPct = 10;
        setUploadProgress(currentPct, title, sub);

        progressSimulationTimer = setInterval(function () {
            if (currentPct < 90) {
                currentPct += Math.floor(Math.random() * 5) + 3;
                setUploadProgress(Math.min(90, currentPct), title, sub);
            }
        }, 450);
    }

    function stopProgressOverlay(successMsg) {
        if (progressSimulationTimer) clearInterval(progressSimulationTimer);
        setUploadProgress(100, successMsg || '✅ Concluído!', 'Finalizando...');
        setTimeout(function () {
            $('#wpaip-fullscreen-dropzone').fadeOut(250, function () {
                resetDropzoneState();
            });
        }, 350);
    }

    /**
     * Dispara o AJAX de geração com as referências já resolvidas.
     * provider e model são enviados vazios: o PHP usa o padrão das configurações.
     */
    function doGenerate(prompt, mode, refUrls, refTexts, $status) {
        setStatus($status, 'loading', cfg.strings.generating);
        startProgressOverlay('📝 Gerando Artigo com IA...', 'A Inteligência Artificial está escrevendo e formatando o post...', '⚡');

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
                stopProgressOverlay('✅ Artigo gerado e inserido no editor!');
            } else {
                setStatus($status, 'error', cfg.strings.error + (res.data.message || 'Erro desconhecido'));
                stopProgressOverlay('❌ Erro ao gerar artigo.');
            }
        })
        .fail(function () {
            setStatus($status, 'error', cfg.strings.error + 'Falha na requisição.');
            stopProgressOverlay('❌ Falha na conexão ao gerar artigo.');
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
        startProgressOverlay('🔍 Buscando Referências...', 'Lendo o conteúdo dos links informados...', '🌐');

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
        const style    = $('#wpaip-image-style').val() || 'photo';

        // Fallback: usa título do post
        if (!prompt) {
            prompt = $('#title').val()
                  || $('[data-type="core/post-title"] .rich-text').text()
                  || 'Imagem profissional para artigo de blog';
        }

        disableBtns(true);
        setStatus($status, 'loading', cfg.strings.gen_image);
        startProgressOverlay('🎨 Gerando Imagem de Capa com IA...', 'Criando imagem em alta definição...', '🖼️');

        $.post(cfg.ajax_url, {
            action:   'wpaip_generate_featured_image',
            nonce:    cfg.nonce,
            post_id:  cfg.post_id,
            prompt:   prompt,
            style:    style,
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
                stopProgressOverlay('✅ Capa gerada e definida com sucesso!');
            } else {
                setStatus($status, 'error', cfg.strings.error + (res.data.message || ''));
                stopProgressOverlay('❌ Erro ao gerar imagem.');
            }
        })
        .fail(function () {
            setStatus($status, 'error', cfg.strings.error + 'Falha na requisição.');
            stopProgressOverlay('❌ Falha na conexão ao gerar imagem.');
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
        const style   = $('#wpaip-image-style').val() || 'photo';

        if (!prompt) return;

        disableBtns(true);
        setStatus($status, 'loading', cfg.strings.gen_image);

        $.post(cfg.ajax_url, {
            action:   'wpaip_generate_inline_image',
            nonce:    cfg.nonce,
            post_id:  cfg.post_id,
            prompt:   prompt,
            style:    style,
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

    var externalPopup = null;

    // ── Geradores Flutuantes para Sites Oficiais (GPT ⚡ e Nano Banana 🍌) ─────
    $(document).on('click', '.wpaip-popup-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var $btn     = $(this).closest('.wpaip-popup-btn');
        var provider = $btn.attr('data-provider') || 'dalle3';
        var prompt   = $.trim($('#wpaip-image-prompt').val()) || $.trim($('#title').val()) || 'imagem fotorrealista de alta qualidade';
        var style    = $('#wpaip-image-style').val() || 'photo';

        var stylePrefix = 'Crie uma imagem fotorrealista e detalhada de: ';
        if (style === 'cinematic') stylePrefix = 'Crie uma imagem estilo cinematográfico de filme de: ';
        else if (style === 'illustration_3d') stylePrefix = 'Crie uma ilustração 3D moderna de: ';
        else if (style === 'digital_art') stylePrefix = 'Crie uma arte digital conceitual de: ';
        else if (style === 'vector') stylePrefix = 'Crie um vetor minimalista de: ';
        else if (style === 'anime') stylePrefix = 'Crie uma arte estilo anime de: ';
        else if (style === 'vintage') stylePrefix = 'Crie uma imagem estilo retrô vintage de: ';

        var fullPrompt = stylePrefix + prompt;

        // Copia prompt formatado para a área de transferência (Clipboard)
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(fullPrompt);
        }

        var targetUrl = 'https://chatgpt.com/?q=' + encodeURIComponent(fullPrompt);
        if (provider === 'gemini') {
            targetUrl = 'https://gemini.google.com/app';
        }

        var width  = 680;
        var height = 800;
        var left   = screen.width ? (screen.width - width - 50) : 800;
        var top    = 50;

        externalPopup = window.open(targetUrl, 'wpaip_external_' + provider, 'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',resizable=yes,scrollbars=yes');
    });

    // Quando o usuário clica ou interage com a página do WordPress, traz o WordPress para frente
    $(document).on('mousedown focus click', function () {
        if (externalPopup && !externalPopup.closed) {
            try {
                externalPopup.blur();
                window.focus();
            } catch (err) {}
        }
    });

    // Global Callbacks acessíveis pela janela popup (window.opener)
    window.wpaipSetFeaturedFromPopup = function (attachId, thumbUrl) {
        if (thumbUrl) {
            $('#wpaip-featured-img').attr('src', thumbUrl);
            $('#wpaip-featured-preview').slideDown();
        }
        if (attachId && typeof wp !== 'undefined' && wp.media && wp.media.featuredImage) {
            wp.media.featuredImage.set(attachId);
        }
    };

    window.wpaipInsertInlineFromPopup = function (html) {
        insertImageInEditor(html);
    };

    // ── Overlay de Drag & Drop na Tela Inteira ──────────────────────────────
    var $overlay = $('#wpaip-fullscreen-dropzone');
    var dragCounter = 0;
    var isInternalDrag = false;

    $(document).on('dragstart', function () {
        isInternalDrag = true;
    });

    $(document).on('dragend mouseup', function () {
        isInternalDrag = false;
    });

    $(document).on('click', '.wpaip-overlay-close-btn', function () {
        $('#wpaip-fullscreen-dropzone').hide();
        $('#wpaip-drop-choice-modal').fadeOut(150);
        dragCounter = 0;
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            $('#wpaip-fullscreen-dropzone').hide();
            $('#wpaip-drop-choice-modal').fadeOut(150);
            dragCounter = 0;
        }
    });

    $(window).on('dragenter', function (e) {
        if (isInternalDrag) return;

        var dt = e.originalEvent.dataTransfer;
        if (!dt || !dt.types) return;

        var types = Array.from(dt.types);
        if (types.indexOf('Files') !== -1) {
            dragCounter++;
            $overlay.css('display', 'flex');
        }
    });

    $(window).on('dragover', function (e) {
        if (isInternalDrag) return;
        e.preventDefault();
    });

    $(window).on('dragleave', function (e) {
        if (isInternalDrag) return;
        e.preventDefault();
        dragCounter--;
        if (dragCounter <= 0) {
            dragCounter = 0;
            $overlay.hide();
        }
    });

    function focusMain() {
        if (externalPopup && !externalPopup.closed) {
            try {
                externalPopup.blur();
            } catch (e) {}
        }
        try {
            window.focus();
        } catch (e) {}
    }

    $(window).on('drop', function (e) {
        if (isInternalDrag) {
            isInternalDrag = false;
            return;
        }

        e.preventDefault();
        dragCounter = 0;
        $overlay.hide();
        focusMain();

        var dt = e.originalEvent.dataTransfer;
        if (!dt) return;

        if (dt.files && dt.files.length) {
            for (var i = 0; i < dt.files.length; i++) {
                if (dt.files[i].type.indexOf('image') !== -1) {
                    uploadPastedFile(dt.files[i]);
                    break;
                }
            }
        } else if (dt.getData('text/html')) {
            var html = dt.getData('text/html');
            var match = html.match(/src=["'](https?:\/\/[^"']+)["']/i);
            if (match && match[1]) {
                uploadPastedUrl(match[1]);
            }
        } else if (dt.getData('text/uri-list')) {
            var uri = dt.getData('text/uri-list');
            if (uri) {
                uploadPastedUrl(uri);
            }
        }
    });

    // Suporte a Ctrl + V (Paste) em qualquer lugar da tela ou dentro de editores
    function handlePasteEvent(e) {
        var clipboardData = e.clipboardData || (e.originalEvent && e.originalEvent.clipboardData);
        if (!clipboardData) return;

        var items = clipboardData.items;
        if (items) {
            for (var i = 0; i < items.length; i++) {
                if (items[i].type.indexOf('image') !== -1) {
                    var file = items[i].getAsFile();
                    if (file) {
                        e.preventDefault();
                        uploadPastedFile(file);
                        return;
                    }
                }
            }
        }

        var text = clipboardData.getData('text/plain');
        if (text && /^https?:\/\/.+\.(png|jpg|jpeg|webp|gif)(\?.*)?$/i.test($.trim(text))) {
            e.preventDefault();
            uploadPastedUrl($.trim(text));
        }
    }

    $(document).on('paste', handlePasteEvent);

    // Conecta escuta no TinyMCE (Editor Clássico)
    if (typeof tinymce !== 'undefined') {
        tinymce.on('AddEditor', function (e) {
            e.editor.on('init', function () {
                var doc = e.editor.getDoc();
                if (doc) {
                    $(doc).on('paste', handlePasteEvent);
                    $(doc).on('dragenter dragover', function (evt) {
                        if (isInternalDrag) return;
                        evt.preventDefault();
                        var dt = evt.originalEvent ? evt.originalEvent.dataTransfer : null;
                        if (dt && dt.types && Array.from(dt.types).indexOf('Files') !== -1) {
                            $overlay.css('display', 'flex');
                        }
                    });
                    $(doc).on('dragleave drop', function (evt) {
                        if (isInternalDrag) return;
                        evt.preventDefault();
                        if (evt.type === 'drop') {
                            $overlay.hide();
                            var dt = evt.originalEvent ? evt.originalEvent.dataTransfer : null;
                            if (dt && dt.files && dt.files.length) {
                                uploadPastedFile(dt.files[0]);
                            }
                        }
                    });
                }
            });
        });
    }

    var pendingChoiceAttachId = 0;
    var pendingChoiceUrl      = '';

    function showDropChoiceModal(attachId, url) {
        focusMain();
        pendingChoiceAttachId = attachId;
        pendingChoiceUrl      = url;
        $('#wpaip-choice-img-preview').attr('src', url);
        $('#wpaip-drop-choice-modal').css('display', 'flex').hide().fadeIn(200);
    }

    $(document).on('click', '#wpaip-choice-btn-featured', function () {
        focusMain();
        if (pendingChoiceAttachId && pendingChoiceUrl) {
            window.wpaipSetFeaturedFromPopup(pendingChoiceAttachId, pendingChoiceUrl);
            $('#wpaip-drop-choice-modal').fadeOut(200);
            setStatus($('#wpaip-image-status'), 'success', 'Imagem definida como capa com sucesso!');
        }
    });

    $(document).on('click', '#wpaip-choice-btn-inline', function () {
        focusMain();
        if (pendingChoiceUrl) {
            var html = '<img src="' + pendingChoiceUrl + '" class="aligncenter size-large wp-image-' + pendingChoiceAttachId + '" />';
            insertImageInEditor(html);
            $('#wpaip-drop-choice-modal').fadeOut(200);
            setStatus($('#wpaip-image-status'), 'success', 'Imagem inserida na posição do cursor!');
        }
    });

    function setUploadProgress(percent, title, sub) {
        $('#wpaip-dropzone-icon').text('⏳');
        $('#wpaip-dropzone-title').text(title || 'Enviando imagem...');
        $('#wpaip-dropzone-sub').text(sub || 'Aguarde o processamento na biblioteca...');
        $('#wpaip-upload-progress-wrap, #wpaip-upload-progress-text').show();
        $('#wpaip-upload-progress-bar').css('width', Math.min(100, Math.max(0, percent)) + '%');
        $('#wpaip-upload-progress-text').text(Math.round(percent) + '%');
        $('#wpaip-fullscreen-dropzone').css('display', 'flex');
    }

    function resetDropzoneState() {
        $('#wpaip-dropzone-icon').text('📥');
        $('#wpaip-dropzone-title').text('Solte a imagem aqui');
        $('#wpaip-dropzone-sub').text('Envie a imagem para usar como Capa do Post ou inserir no Texto');
        $('#wpaip-upload-progress-wrap, #wpaip-upload-progress-text').hide();
        $('#wpaip-upload-progress-bar').css('width', '0%');
        $('#wpaip-upload-progress-text').text('0%');
    }

    $(document).on('click', '.wpaip-overlay-close-btn', function () {
        $('#wpaip-fullscreen-dropzone').hide();
        $('#wpaip-drop-choice-modal').fadeOut(150);
        resetDropzoneState();
        dragCounter = 0;
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            $('#wpaip-fullscreen-dropzone').hide();
            $('#wpaip-drop-choice-modal').fadeOut(150);
            resetDropzoneState();
            dragCounter = 0;
        }
    });

    function uploadPastedFile(file) {
        var $status = $('#wpaip-image-status');
        setStatus($status, 'loading', 'Enviando imagem...');
        setUploadProgress(15, '⏳ Enviando arquivo...', 'Enviando imagem para a biblioteca de mídia...');

        var formData = new FormData();
        formData.append('action', 'wpaip_upload_pasted_image');
        formData.append('nonce', cfg.nonce);
        formData.append('_ajax_nonce', cfg.nonce);
        formData.append('post_id', cfg.post_id || $('#post_ID').val() || 0);
        formData.append('image_file', file);

        $.ajax({
            url: cfg.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function () {
                var myXhr = $.ajaxSettings.xhr();
                if (myXhr.upload) {
                    myXhr.upload.addEventListener('progress', function (e) {
                        if (e.lengthComputable) {
                            var pct = Math.round((e.loaded / e.total) * 85) + 10;
                            setUploadProgress(pct, '⏳ Enviando arquivo...', 'Upload em andamento...');
                        }
                    }, false);
                }
                return myXhr;
            }
        })
        .done(function (res) {
            setUploadProgress(100, '✅ Envio concluído!', 'Abrindo opções...');
            setTimeout(function () {
                $('#wpaip-fullscreen-dropzone').hide();
                resetDropzoneState();
                if (res.success) {
                    showDropChoiceModal(res.data.attachment_id, res.data.thumb_url);
                    setStatus($status, 'success', 'Imagem enviada com sucesso!');
                } else {
                    setStatus($status, 'error', res.data.message || 'Falha ao enviar imagem.');
                }
            }, 300);
        })
        .fail(function () {
            $('#wpaip-fullscreen-dropzone').hide();
            resetDropzoneState();
            setStatus($status, 'error', 'Erro de conexão ao enviar imagem.');
        });
    }

    function uploadPastedUrl(url) {
        var $status = $('#wpaip-image-status');
        setStatus($status, 'loading', 'Baixando imagem...');
        setUploadProgress(20, '⏳ Baixando imagem externa...', 'Fazendo download da imagem...');

        var pct = 20;
        var progressTimer = setInterval(function () {
            pct += 10;
            if (pct <= 85) {
                setUploadProgress(pct, '⏳ Processando imagem...', 'Aguarde o download e registro no WordPress...');
            }
        }, 250);

        $.post(cfg.ajax_url, {
            action: 'wpaip_upload_pasted_image',
            nonce: cfg.nonce,
            _ajax_nonce: cfg.nonce,
            post_id: cfg.post_id || $('#post_ID').val() || 0,
            image_url: url
        })
        .done(function (res) {
            clearInterval(progressTimer);
            setUploadProgress(100, '✅ Download concluído!', 'Abrindo opções...');
            setTimeout(function () {
                $('#wpaip-fullscreen-dropzone').hide();
                resetDropzoneState();
                if (res.success) {
                    showDropChoiceModal(res.data.attachment_id, res.data.thumb_url);
                    setStatus($status, 'success', 'Imagem capturada com sucesso!');
                } else {
                    setStatus($status, 'error', res.data.message || 'Falha ao capturar imagem.');
                }
            }, 300);
        })
        .fail(function () {
            clearInterval(progressTimer);
            $('#wpaip-fullscreen-dropzone').hide();
            resetDropzoneState();
            setStatus($status, 'error', 'Erro de conexão ao baixar imagem.');
        });
    }

}(jQuery));
