<?php
/**
 * Metabox lateral unificado para Editor Clássico e Gutenberg.
 * Registra painel de IA no editor de posts/páginas.
 */
defined( 'ABSPATH' ) || exit;

class WPAIP_Metabox {

    public static function init(): void {
        add_action( 'add_meta_boxes',        [ __CLASS__, 'register_metabox'       ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets'         ] );
        add_action( 'admin_footer',          [ __CLASS__, 'render_global_overlays' ] );

        // AJAX handlers
        WPAIP_LLM::register_ajax();
        WPAIP_Image::register_ajax();
    }

    // ── Registro do Metabox ───────────────────────────────────────────────────

    public static function register_metabox(): void {
        $post_types = apply_filters( 'wpaip_post_types', [ 'post', 'page' ] );

        foreach ( $post_types as $pt ) {
            add_meta_box(
                'wpaip-panel',
                __( 'POST FÁCIL I.A.', 'wp-ai-publisher' ),
                [ __CLASS__, 'render_metabox' ],
                $pt,
                'side',
                'high'
            );
        }
    }

    // ── Assets ────────────────────────────────────────────────────────────────

    public static function enqueue_assets( string $hook ): void {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
            return;
        }

        $css_file = WPAIP_PLUGIN_DIR . 'admin/css/admin.css';
        $js_file  = WPAIP_PLUGIN_DIR . 'admin/js/metabox.js';
        $css_ver  = file_exists( $css_file ) ? filemtime( $css_file ) : time();
        $js_ver   = file_exists( $js_file )  ? filemtime( $js_file )  : time();

        wp_enqueue_style(
            'font-awesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
            [],
            '6.5.1'
        );

        wp_enqueue_style(
            'wpaip-admin',
            WPAIP_PLUGIN_URL . 'admin/css/admin.css',
            [ 'font-awesome' ],
            $css_ver
        );

        wp_enqueue_script(
            'wpaip-metabox',
            WPAIP_PLUGIN_URL . 'admin/js/metabox.js',
            [ 'jquery' ],
            $js_ver,
            true
        );

        // Dados passados ao JS
        wp_localize_script( 'wpaip-metabox', 'wpaipMetabox', [
            'ajax_url'     => admin_url( 'admin-ajax.php' ),
            'nonce'        => WPAIP_Security::create_nonce(),
            'post_id'      => get_the_ID(),
            'is_gutenberg' => self::is_gutenberg(),
            'strings'      => [
                'generating'     => __( 'Gerando…', 'wp-ai-publisher' ),
                'gen_image'      => __( 'Gerando imagem…', 'wp-ai-publisher' ),
                'uploading'      => __( 'Enviando para biblioteca…', 'wp-ai-publisher' ),
                'success'        => __( 'Pronto!', 'wp-ai-publisher' ),
                'error'          => __( 'Erro: ', 'wp-ai-publisher' ),
                'prompt_empty'   => __( 'Digite um tema ou selecione texto no editor.', 'wp-ai-publisher' ),
                'image_prompt'   => __( 'Descreva a imagem que deseja gerar:', 'wp-ai-publisher' ),
                'ref_invalid'    => __( 'URL inválida.', 'wp-ai-publisher' ),
                'ref_duplicate'  => __( 'URL já adicionada.', 'wp-ai-publisher' ),
                'ref_fetching'   => __( 'Buscando referências…', 'wp-ai-publisher' ),
                'ref_fetch_ok'   => __( 'Referências carregadas!', 'wp-ai-publisher' ),
                'ref_fetch_fail' => __( 'Falha ao buscar referências.', 'wp-ai-publisher' ),
            ],
        ] );

        // Injeta o trigger fixo e o modal via footer (não depende de JS para existir no DOM)
        add_action( 'admin_footer', [ __CLASS__, 'render_modal_shell' ] );
    }

    public static function render_modal_shell(): void {
        ?>
        <button type="button" id="wpaip-floating-trigger" title="<?php esc_attr_e( 'POST FÁCIL I.A.', 'wp-ai-publisher' ); ?>">
            <span class="dashicons dashicons-superhero"></span>
        </button>
        <div id="wpaip-floating-modal" class="wpaip-dark-theme" style="display:none;">
            <div class="wpaip-modal-header">
                <div class="wpaip-modal-title-group">
                    <button type="button" id="wpaip-save-dot" class="wpaip-save-dot wpaip-save-dot--saved" title="<?php esc_attr_e( 'Salvar post', 'wp-ai-publisher' ); ?>">
                        <svg viewBox="0 0 16 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                            <path d="M2 1a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4.5L10.5 1H2zm8.5 0v3.5H13L10.5 1zM5 9h6v4H5V9zm1 1v2h4v-2H6z"/>
                        </svg>
                    </button>
                    <h3>POST FÁCIL I.A.</h3>
                </div>
                <button type="button" class="wpaip-modal-close">&times;</button>
            </div>
            <!-- painel será movido aqui pelo JS -->
        </div>
        <?php
    }

    // ── Render HTML do Metabox ────────────────────────────────────────────────

    public static function render_metabox( WP_Post $post ): void {
        $has_providers = self::has_any_provider();
        ?>
        <div id="wpaip-panel-root">

            <?php if ( ! $has_providers ) : ?>
                <p class="wpaip-notice wpaip-notice--warn">
                    <?php printf(
                        __( 'Nenhuma API key configurada. <a href="%s">Configurar agora.</a>', 'wp-ai-publisher' ),
                        esc_url( admin_url( 'admin.php?page=' . WPAIP_SLUG ) )
                    ); ?>
                </p>
            <?php else : ?>

                <!-- CARD 1: GERADOR DE CONTEÚDO (ARTIGO) -->
                <div class="wpaip-card-box">
                    <div class="wpaip-card-box-header">
                        <span class="wpaip-card-icon">📝</span>
                        <strong class="wpaip-card-title"><?php _e( 'Gerar Artigo', 'wp-ai-publisher' ); ?></strong>
                    </div>

                    <div class="wpaip-field" style="margin-bottom:8px;">
                        <div class="wpaip-prompt-header">
                            <label class="wpaip-label" for="wpaip-prompt"><?php _e( 'Tema ou Instruções', 'wp-ai-publisher' ); ?></label>
                            <div class="wpaip-para-btns">
                                <span class="wpaip-para-label"><?php _e( 'Parágrafos', 'wp-ai-publisher' ); ?></span>
                                <button type="button" class="wpaip-para-btn" data-val="1">1</button>
                                <button type="button" class="wpaip-para-btn" data-val="2">2</button>
                                <button type="button" class="wpaip-para-btn" data-val="3">3</button>
                                <button type="button" class="wpaip-para-btn" data-val="4">4</button>
                                <button type="button" class="wpaip-para-btn is-active" data-val="5">5</button>
                                <button type="button" id="wpaip-para-more" class="wpaip-para-btn wpaip-para-btn--more" title="<?php esc_attr_e( 'Mais parágrafos', 'wp-ai-publisher' ); ?>">+</button>
                            </div>
                            <input type="hidden" id="wpaip-paragraphs" value="5">
                        </div>

                        <div class="wpaip-prompt-wrap">
                            <textarea id="wpaip-prompt" class="wpaip-textarea" rows="3"
                                placeholder="<?php esc_attr_e( 'Ex: 5 dicas essenciais de SEO para e-commerce', 'wp-ai-publisher' ); ?>"></textarea>
                            <div class="wpaip-prompt-actions">
                                <button type="button" id="wpaip-btn-draft" class="wpaip-btn wpaip-btn--primary" data-mode="draft">
                                    <?php _e( '✦ Gerar Artigo', 'wp-ai-publisher' ); ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Referências Externas (Sanfonado dentro do Card de Artigo) -->
                    <div style="margin-top:10px;">
                        <button type="button" id="wpaip-btn-toggle-refs" class="wpaip-btn-toggle-refs">
                            <span class="wpaip-toggle-icon">+</span>
                            <?php _e( 'Usar matérias externas (URLs)', 'wp-ai-publisher' ); ?>
                        </button>

                        <div id="wpaip-refs-section" style="display:none; margin-top:8px;">
                            <div class="wpaip-field">
                                <label class="wpaip-label" for="wpaip-ref-input">
                                    <?php _e( 'Adicionar URL de referência', 'wp-ai-publisher' ); ?>
                                </label>
                                <div class="wpaip-ref-input-row">
                                    <input type="url" id="wpaip-ref-input" class="wpaip-input"
                                        placeholder="<?php esc_attr_e( 'https://exemplo.com/artigo', 'wp-ai-publisher' ); ?>" />
                                    <button type="button" id="wpaip-btn-ref-add" class="wpaip-btn wpaip-btn--secondary wpaip-btn--icon" title="<?php esc_attr_e( 'Adicionar', 'wp-ai-publisher' ); ?>">+</button>
                                </div>
                            </div>

                            <ul id="wpaip-ref-list" class="wpaip-ref-list"></ul>
                            <div id="wpaip-ref-status" class="wpaip-status" style="display:none;"></div>
                        </div>
                    </div>

                    <div id="wpaip-text-status" class="wpaip-status" style="display:none;"></div>
                </div>

                <!-- CARD 2: GERADOR DE IMAGENS -->
                <div class="wpaip-card-box" style="margin-top:14px;">
                    <div class="wpaip-card-box-header" style="justify-content:space-between;">
                        <div style="display:flex; align-items:center; gap:6px;">
                            <span class="wpaip-card-icon">🎨</span>
                            <strong class="wpaip-card-title"><?php _e( 'Imagem & Capa', 'wp-ai-publisher' ); ?></strong>
                        </div>
                        <div style="display:flex; gap:6px; align-items:center;">
                            <button type="button" class="wpaip-popup-btn" data-provider="dalle3" title="<?php esc_attr_e( 'Abrir ChatGPT (GPT-4o)', 'wp-ai-publisher' ); ?>" style="background:#ffffff; border:1px solid #cbd5e1; border-radius:5px; padding:3px 7px; font-size:10px; font-weight:700; color:#334155; cursor:pointer; box-shadow:0 1px 2px rgba(0,0,0,0.05); transition:all 0.15s;">
                                <?php _e( 'Chat GPT', 'wp-ai-publisher' ); ?>
                            </button>
                            <button type="button" class="wpaip-popup-btn" data-provider="gemini" title="<?php esc_attr_e( 'Abrir Google Gemini (Nano Banana)', 'wp-ai-publisher' ); ?>" style="background:#ffffff; border:1px solid #cbd5e1; border-radius:5px; padding:3px 7px; font-size:10px; font-weight:700; color:#334155; cursor:pointer; box-shadow:0 1px 2px rgba(0,0,0,0.05); transition:all 0.15s;">
                                <?php _e( 'Nano banana', 'wp-ai-publisher' ); ?>
                            </button>
                        </div>
                    </div>

                    <div class="wpaip-field" style="margin-bottom:10px;">
                        <label class="wpaip-label" for="wpaip-image-style">
                            <?php _e( 'Estilo da Imagem', 'wp-ai-publisher' ); ?>
                        </label>
                        <select id="wpaip-image-style" class="wpaip-select">
                            <option value="photo" selected><?php _e( '📷 Fotojornalístico / Realista', 'wp-ai-publisher' ); ?></option>
                            <option value="cinematic"><?php _e( '🎬 Cinematográfico', 'wp-ai-publisher' ); ?></option>
                            <option value="illustration_3d"><?php _e( '🎨 Ilustração 3D', 'wp-ai-publisher' ); ?></option>
                            <option value="digital_art"><?php _e( '🖌 Arte Digital / Conceitual', 'wp-ai-publisher' ); ?></option>
                            <option value="vector"><?php _e( '✏️ Vetor / Minimalista', 'wp-ai-publisher' ); ?></option>
                            <option value="anime"><?php _e( '⛩️ Anime / Manga', 'wp-ai-publisher' ); ?></option>
                            <option value="vintage"><?php _e( '🎞️ Retrô / Vintage', 'wp-ai-publisher' ); ?></option>
                        </select>
                    </div>

                    <div class="wpaip-field" style="margin-bottom:10px;">
                        <label class="wpaip-label" for="wpaip-image-prompt">
                            <?php _e( 'Prompt Visual', 'wp-ai-publisher' ); ?>
                        </label>
                        <textarea id="wpaip-image-prompt" class="wpaip-textarea" rows="2"
                            placeholder="<?php esc_attr_e( 'Deixe vazio para usar o título do post', 'wp-ai-publisher' ); ?>"></textarea>
                    </div>

                    <div id="wpaip-featured-preview" style="display:none; margin-bottom: 10px;">
                        <img id="wpaip-featured-img" src="" alt="" style="width:100%; border-radius:6px; border:1px solid #475569;" />
                    </div>

                    <div class="wpaip-btn-group" style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                        <button type="button" id="wpaip-btn-featured" class="wpaip-btn wpaip-btn--primary" style="justify-content:center;">
                            <?php _e( '🖼 Gerar Capa', 'wp-ai-publisher' ); ?>
                        </button>
                        <button type="button" id="wpaip-btn-inline" class="wpaip-btn wpaip-btn--secondary" style="justify-content:center;">
                            <?php _e( '+ Inserir no Texto', 'wp-ai-publisher' ); ?>
                        </button>
                    </div>

                    <div id="wpaip-image-status" class="wpaip-status" style="display:none;"></div>
                </div>

            <?php endif; ?>

        </div>
        <?php
    }

    /**
     * Renderiza o Overlay de Drag & Drop e o Modal de Escolha no footer de todas as páginas de edição de posts.
     */
    public static function render_global_overlays(): void {
        $screen = get_current_screen();
        if ( ! $screen || ! in_array( $screen->base, [ 'post' ], true ) ) {
            return;
        }
        ?>
        <!-- Overlay de Arrastar e Soltar na Tela Inteira (Fullscreen Dropzone) -->
        <div id="wpaip-fullscreen-dropzone" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(15,23,42,0.88); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); z-index:999999; justify-content:center; align-items:center; flex-direction:column; color:#f8fafc; border:4px dashed #6366f1; pointer-events:auto;">
            <button type="button" class="wpaip-overlay-close-btn" style="position:absolute; top:24px; right:28px; background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2); color:#fff; font-size:28px; width:44px; height:44px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all 0.2s;">×</button>
            <div style="text-align:center; padding:28px; max-width:480px; width:90%; background:rgba(30,41,59,0.85); border-radius:20px; border:1px solid rgba(255,255,255,0.1); box-shadow:0 25px 50px -12px rgba(0,0,0,0.5); position:relative;">
                <span id="wpaip-dropzone-icon" style="font-size:56px; display:block; margin-bottom:12px;">📥</span>
                <h2 id="wpaip-dropzone-title" style="font-size:22px; font-weight:800; margin-bottom:8px; color:#fff;"><?php _e( 'Solte a imagem aqui', 'wp-ai-publisher' ); ?></h2>
                <p id="wpaip-dropzone-sub" style="font-size:13px; color:#c4b5fd; line-height:1.4;"><?php _e( 'Envie a imagem para usar como Capa do Post ou inserir no Texto', 'wp-ai-publisher' ); ?></p>
                
                <div id="wpaip-upload-progress-wrap" style="display:none; width:100%; max-width:340px; margin:16px auto 0; background:rgba(255,255,255,0.1); border-radius:10px; height:8px; overflow:hidden;">
                    <div id="wpaip-upload-progress-bar" style="width:0%; height:100%; background:linear-gradient(90deg, #6366f1, #10b981); transition:width 0.2s ease;"></div>
                </div>
                <div id="wpaip-upload-progress-text" style="display:none; font-size:12px; color:#94a3b8; margin-top:6px; font-weight:600;">0%</div>
            </div>
        </div>

        <!-- Modal de Escolha da Destinação da Imagem -->
        <div id="wpaip-drop-choice-modal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(15,23,42,0.85); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); z-index:999999; justify-content:center; align-items:center;">
            <div style="background:#1e293b; border:1px solid #334155; border-radius:16px; padding:24px; max-width:420px; width:90%; text-align:center; box-shadow:0 25px 50px -12px rgba(0,0,0,0.5); color:#f8fafc; position:relative;">
                <button type="button" class="wpaip-overlay-close-btn" style="position:absolute; top:12px; right:14px; background:none; border:none; color:#94a3b8; font-size:24px; cursor:pointer;">×</button>
                <h3 style="font-size:18px; font-weight:700; margin-bottom:6px; color:#fff;"><?php _e( 'Onde deseja utilizar a imagem?', 'wp-ai-publisher' ); ?></h3>
                <p style="font-size:13px; color:#94a3b8; margin-bottom:16px;"><?php _e( 'Escolha se a imagem enviada será a capa do post ou inserida no corpo do texto na posição do cursor.', 'wp-ai-publisher' ); ?></p>

                <div style="margin-bottom:16px;">
                    <img id="wpaip-choice-img-preview" src="" style="max-height:160px; width:auto; border-radius:8px; border:1px solid #475569; display:inline-block;" />
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <button type="button" id="wpaip-choice-btn-featured" style="background:#10b981; color:#fff; border:none; padding:12px; border-radius:8px; font-weight:700; font-size:13px; cursor:pointer;">
                        📌 <?php _e( 'Como Capa', 'wp-ai-publisher' ); ?>
                    </button>
                    <button type="button" id="wpaip-choice-btn-inline" style="background:#6366f1; color:#fff; border:none; padding:12px; border-radius:8px; font-weight:700; font-size:13px; cursor:pointer;">
                        📝 <?php _e( 'Inserir no Texto', 'wp-ai-publisher' ); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Verifica se existe ao menos um provider de texto ou imagem configurado.
     */
    private static function has_any_provider(): bool {
        $text_providers = [ 'openai', 'gemini', 'anthropic', 'deepseek' ];
        foreach ( $text_providers as $p ) {
            if ( ! empty( WPAIP_Settings::get_api_key( $p ) ) ) {
                return true;
            }
        }
        // Pollinations não requer chave — sempre disponível
        return true;
    }

    /**
     * Detecta se o post está sendo editado no Gutenberg.
     */
    private static function is_gutenberg(): bool {
        if ( ! function_exists( 'use_block_editor_for_post' ) ) {
            return false;
        }
        global $post;
        return $post instanceof WP_Post && use_block_editor_for_post( $post );
    }
}
