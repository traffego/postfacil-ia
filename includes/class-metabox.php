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

        wp_enqueue_style(
            'wpaip-admin',
            WPAIP_PLUGIN_URL . 'admin/css/admin.css',
            [],
            WPAIP_VERSION
        );

        wp_enqueue_script(
            'wpaip-metabox',
            WPAIP_PLUGIN_URL . 'admin/js/metabox.js',
            [ 'jquery' ],
            WPAIP_VERSION,
            true
        );

        // Dados passados ao JS
        wp_localize_script( 'wpaip-metabox', 'wpaipMetabox', [
            'ajax_url'         => admin_url( 'admin-ajax.php' ),
            'nonce'            => WPAIP_Security::create_nonce(),
            'post_id'          => get_the_ID(),
            'is_gutenberg'     => self::is_gutenberg(),
            'default_provider' => WPAIP_Settings::get( 'default_llm', 'openai' ),
            'default_image'    => WPAIP_Settings::get( 'default_image', 'dalle3' ),
            'providers'        => self::get_available_providers(),
            'strings'          => [
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
    }

    // ── Render HTML do Metabox ────────────────────────────────────────────────

    public static function render_metabox( WP_Post $post ): void {
        $providers       = self::get_available_providers();
        $default_llm     = WPAIP_Settings::get( 'default_llm',   'openai' );
        $default_image   = WPAIP_Settings::get( 'default_image', 'dalle3' );

        $llm_models = [
            'openai'    => [ 'gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'o1-mini' ],
            'gemini'    => [ 'gemini-3.5-flash', 'gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-2.0-flash' ],
            'anthropic' => [ 'claude-sonnet-4-5', 'claude-opus-4-5', 'claude-haiku-3-5' ],
            'deepseek'  => [ 'deepseek-chat', 'deepseek-reasoner' ],
        ];
        ?>
        <div id="wpaip-panel-root">

            <?php if ( empty( $providers ) ) : ?>
                <p class="wpaip-notice wpaip-notice--warn">
                    <?php printf(
                        __( 'Nenhuma API key configurada. <a href="%s">Configurar agora.</a>', 'wp-ai-publisher' ),
                        esc_url( admin_url( 'admin.php?page=' . WPAIP_SLUG ) )
                    ); ?>
                </p>
            <?php else : ?>

                <!-- ── Provider & Model ── -->
                <div class="wpaip-field">
                    <label class="wpaip-label" for="wpaip-llm-provider">
                        <?php _e( 'Modelo de Texto', 'wp-ai-publisher' ); ?>
                    </label>
                    <select id="wpaip-llm-provider" class="wpaip-select">
                        <?php foreach ( $providers as $key => $label ) : ?>
                            <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $default_llm ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="wpaip-field" id="wpaip-model-wrapper">
                    <label class="wpaip-label" for="wpaip-llm-model">
                        <?php _e( 'Versão', 'wp-ai-publisher' ); ?>
                    </label>
                    <select id="wpaip-llm-model" class="wpaip-select">
                        <?php foreach ( $llm_models as $prov => $models ) : ?>
                            <?php foreach ( $models as $m ) : ?>
                                <option value="<?php echo esc_attr( $m ); ?>"
                                    data-provider="<?php echo esc_attr( $prov ); ?>"
                                    <?php selected( $m, WPAIP_Settings::get( $prov . '_model' ) ); ?>>
                                    <?php echo esc_html( $m ); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- ── Referências Externas ── -->
                <div class="wpaip-section-title"><?php _e( 'Referências', 'wp-ai-publisher' ); ?></div>

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

                <!-- ── Geração de Texto ── -->
                <div class="wpaip-section-title"><?php _e( 'Texto', 'wp-ai-publisher' ); ?></div>

                <div class="wpaip-field">
                    <label class="wpaip-label" for="wpaip-prompt">
                        <?php _e( 'Tema / Instrução', 'wp-ai-publisher' ); ?>
                    </label>
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
                    <label class="wpaip-label" for="wpaip-image-provider">
                        <?php _e( 'Gerador', 'wp-ai-publisher' ); ?>
                    </label>
                    <select id="wpaip-image-provider" class="wpaip-select">
                        <option value="pollinations" <?php selected( $default_image, 'pollinations' ); ?>>Pollinations AI (Grátis - Sem Chave)</option>
                        <?php if ( ! empty( WPAIP_Settings::get_api_key( 'huggingface' ) ) ) : ?>
                            <option value="huggingface" <?php selected( $default_image, 'huggingface' ); ?>>Hugging Face (Grátis - Com Chave)</option>
                        <?php endif; ?>
                        <?php if ( ! empty( WPAIP_Settings::get_api_key( 'openai' ) ) ) : ?>
                            <option value="dalle3" <?php selected( $default_image, 'dalle3' ); ?>>DALL-E 3 (OpenAI)</option>
                        <?php endif; ?>
                        <?php if ( ! empty( WPAIP_Settings::get_api_key( 'gemini' ) ) ) : ?>
                            <option value="gemini" <?php selected( $default_image, 'gemini' ); ?>>Imagen 4 (Gemini)</option>
                        <?php endif; ?>
                    </select>
                </div>

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
     * Retorna apenas os providers que têm API key configurada.
     */
    private static function get_available_providers(): array {
        $all = [
            'openai'    => 'GPT (OpenAI)',
            'gemini'    => 'Gemini (Google)',
            'anthropic' => 'Claude (Anthropic)',
            'deepseek'  => 'DeepSeek',
        ];

        $available = [];
        foreach ( $all as $key => $label ) {
            if ( ! empty( WPAIP_Settings::get_api_key( $key ) ) ) {
                $available[ $key ] = $label;
            }
        }

        return $available;
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
