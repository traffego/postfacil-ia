<?php
/**
 * Metabox lateral unificado para Editor Clássico e Gutenberg.
 * Registra painel de IA no editor de posts/páginas.
 */
defined( 'ABSPATH' ) || exit;

class WPAIP_Metabox {

    public static function init(): void {
        add_action( 'add_meta_boxes',        [ __CLASS__, 'register_metabox'  ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets'    ] );

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
            'wpaip-admin',
            WPAIP_PLUGIN_URL . 'admin/css/admin.css',
            [],
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

                <!-- ── Referências Externas ── -->
                <button type="button" id="wpaip-btn-toggle-refs" class="wpaip-btn-toggle-refs">
                    <span class="wpaip-toggle-icon">+</span>
                    <?php _e( 'Usar matérias externas', 'wp-ai-publisher' ); ?>
                </button>

                <div id="wpaip-refs-section" style="display:none;">
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

                <!-- ── Geração de Texto ── -->
                <div class="wpaip-field">
                    <div class="wpaip-prompt-header">
                        <label class="wpaip-label" for="wpaip-prompt">PROMPT</label>
                        <div class="wpaip-para-btns">
                            <button type="button" class="wpaip-para-btn" data-val="1">1</button>
                            <button type="button" class="wpaip-para-btn" data-val="2">2</button>
                            <button type="button" class="wpaip-para-btn" data-val="3">3</button>
                            <button type="button" class="wpaip-para-btn" data-val="4">4</button>
                            <button type="button" class="wpaip-para-btn is-active" data-val="5">5</button>
                            <button type="button" id="wpaip-para-more" class="wpaip-para-btn wpaip-para-btn--more" title="<?php esc_attr_e( 'Mais parágrafos', 'wp-ai-publisher' ); ?>">+</button>
                        </div>
                        <input type="hidden" id="wpaip-paragraphs" value="5">
                    </div>
                    <textarea id="wpaip-prompt" class="wpaip-textarea" rows="3"
                        placeholder="<?php esc_attr_e( 'Ex: 5 dicas de SEO para e-commerce', 'wp-ai-publisher' ); ?>"></textarea>
                </div>

                <div class="wpaip-btn-group">
                    <button type="button" id="wpaip-btn-draft" class="wpaip-btn wpaip-btn--primary" data-mode="draft">
                        <?php _e( '✦ Gerar Rascunho', 'wp-ai-publisher' ); ?>
                    </button>
                    <button type="button" id="wpaip-btn-expand" class="wpaip-btn wpaip-btn--secondary" data-mode="expand">
                        <?php _e( 'Expandir', 'wp-ai-publisher' ); ?>
                    </button>
                    <button type="button" id="wpaip-btn-summarize" class="wpaip-btn wpaip-btn--secondary" data-mode="summarize">
                        <?php _e( 'Resumir', 'wp-ai-publisher' ); ?>
                    </button>
                </div>

                <div id="wpaip-text-status" class="wpaip-status" style="display:none;"></div>

                <!-- ── Imagem Destacada ── -->
                <div class="wpaip-section-title"><?php _e( 'Imagem de Capa', 'wp-ai-publisher' ); ?></div>

                <div class="wpaip-field">
                    <label class="wpaip-label" for="wpaip-image-prompt">
                        <?php _e( 'Prompt visual', 'wp-ai-publisher' ); ?>
                    </label>
                    <textarea id="wpaip-image-prompt" class="wpaip-textarea" rows="2"
                        placeholder="<?php esc_attr_e( 'Deixe vazio para usar o título do post', 'wp-ai-publisher' ); ?>"></textarea>
                </div>

                <div id="wpaip-featured-preview" style="display:none; margin-bottom: 8px;">
                    <img id="wpaip-featured-img" src="" alt="" style="width:100%; border-radius:4px;" />
                </div>

                <div class="wpaip-btn-group">
                    <button type="button" id="wpaip-btn-featured" class="wpaip-btn wpaip-btn--primary">
                        <?php _e( '🖼 Gerar Capa', 'wp-ai-publisher' ); ?>
                    </button>
                    <button type="button" id="wpaip-btn-inline" class="wpaip-btn wpaip-btn--secondary">
                        <?php _e( '+ Inserir no texto', 'wp-ai-publisher' ); ?>
                    </button>
                </div>

                <div id="wpaip-image-status" class="wpaip-status" style="display:none;"></div>

            <?php endif; ?>

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
