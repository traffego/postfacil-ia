<?php
/**
 * Painel de configurações do plugin no WordPress Admin.
 * Gerencia chaves de API para todos os provedores LLM e imagem.
 */
defined( 'ABSPATH' ) || exit;

class WPAIP_Settings {

    const OPTION_KEY = 'wpaip_settings';

    public static function init(): void {
        add_action( 'admin_menu',    [ __CLASS__, 'register_menu'    ] );
        add_action( 'admin_init',    [ __CLASS__, 'register_fields'  ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );

        // AJAX: testar conexão de API key
        add_action( 'wp_ajax_wpaip_test_api_key',   [ __CLASS__, 'ajax_test_api_key'   ] );
        // AJAX: listar modelos do Hugging Face
        add_action( 'wp_ajax_wpaip_list_hf_models', [ __CLASS__, 'ajax_list_hf_models' ] );
        // AJAX: listar modelos do Poe.com
        add_action( 'wp_ajax_wpaip_list_poe_bots',   [ __CLASS__, 'ajax_list_poe_bots'   ] );
        // AJAX: ativar/testar licença
        add_action( 'wp_ajax_wpaip_activate_license',   [ __CLASS__, 'ajax_activate_license'   ] );
        // AJAX: limpar cache da licença do usuário atual
        add_action( 'wp_ajax_wpaip_clear_license_cache', [ __CLASS__, 'ajax_clear_license_cache' ] );
        // AJAX: melhorar prompt de sistema global
        add_action( 'wp_ajax_wpaip_improve_prompt', [ 'WPAIP_LLM', 'ajax_improve_prompt' ] );
    }

    // ── Menu Admin ────────────────────────────────────────────────────────────

    public static function register_menu(): void {
        add_menu_page(
            __( 'AI Publisher', 'wp-ai-publisher' ),
            __( 'AI Publisher', 'wp-ai-publisher' ),
            'manage_options',
            WPAIP_SLUG,
            [ __CLASS__, 'render_settings_page' ],
            'dashicons-superhero',
            30
        );

        add_submenu_page(
            WPAIP_SLUG,
            __( 'Configurações', 'wp-ai-publisher' ),
            __( 'Configurações', 'wp-ai-publisher' ),
            'manage_options',
            WPAIP_SLUG,
            [ __CLASS__, 'render_settings_page' ]
        );

        add_submenu_page(
            WPAIP_SLUG,
            __( 'Agendamentos', 'wp-ai-publisher' ),
            __( 'Agendamentos', 'wp-ai-publisher' ),
            'manage_options',
            WPAIP_SLUG . '-cron',
            [ 'WPAIP_Cron', 'render_cron_page' ]
        );
    }

    // ── Register Settings ─────────────────────────────────────────────────────

    public static function register_fields(): void {
        register_setting(
            WPAIP_SLUG . '-settings-group',
            self::OPTION_KEY,
            [ 'sanitize_callback' => [ __CLASS__, 'sanitize_options' ] ]
        );
    }

    public static function sanitize_options( array $input ): array {
        $clean = self::get_defaults();
        $saved = self::get_options();

        // LLM providers
        foreach ( [ 'openai', 'gemini', 'anthropic', 'deepseek', 'huggingface', 'poe' ] as $provider ) {
            $key = $provider . '_api_key';
            if ( ! empty( $input[ $key ] ) ) {
                $raw = sanitize_text_field( $input[ $key ] );
                // Se mudou (não é placeholder mascarado), re-criptografa
                if ( $raw !== str_repeat( '*', strlen( $raw ) ) && $raw !== '••••••••••••••••' ) {
                    $clean[ $key ] = WPAIP_Security::encrypt( $raw );
                } else {
                    $clean[ $key ] = $saved[ $key ] ?? '';
                }
            } else {
                $clean[ $key ] = $saved[ $key ] ?? '';
            }
        }

        // Chave de Licença — preserva sempre o que foi salvo pelo activate_license (nunca re-encripta via form)
        $clean['license_key'] = $saved['license_key'] ?? '';

        // URL do Servidor de Licenças — preserva URL existente, usa DEFAULT_SERVER se estiver vazia
        $url_input = esc_url_raw( $input['license_server_url'] ?? '' );
        $clean['license_server_url'] = ! empty( $url_input ) ? $url_input : ( $saved['license_server_url'] ?? WPAIP_Paywall::DEFAULT_SERVER );
        $clean['license_cache_hours'] = max( 1, (int) ( $input['license_cache_hours'] ?? 24 ) );

        // Provider padrão para texto e imagem
        $clean['default_llm']   = sanitize_text_field( $input['default_llm']   ?? 'openai' );
        $clean['default_image'] = sanitize_text_field( $input['default_image'] ?? 'pollinations' );

        // Modelo padrão por provider
        $clean['openai_model']          = sanitize_text_field( $input['openai_model']          ?? 'gpt-4o' );
        $clean['gemini_model']          = sanitize_text_field( $input['gemini_model']          ?? 'gemini-3.5-flash' );
        $clean['anthropic_model']       = sanitize_text_field( $input['anthropic_model']       ?? 'claude-sonnet-4-5' );
        $clean['deepseek_model']        = sanitize_text_field( $input['deepseek_model']        ?? 'deepseek-chat' );
        $clean['openai_image_model']    = sanitize_text_field( $input['openai_image_model']    ?? 'dall-e-3' );
        $clean['huggingface_image_model'] = sanitize_text_field( $input['huggingface_image_model'] ?? 'black-forest-labs/FLUX.1-schnell' );
        $clean['poe_image_bot']         = sanitize_text_field( $input['poe_image_bot']         ?? 'FLUX-schnell' );

        // Prompt de sistema global
        $clean['system_prompt'] = sanitize_textarea_field( $input['system_prompt'] ?? '' );

        // Estilo jornalístico padrão
        $clean['default_journalistic_style'] = sanitize_text_field( $input['default_journalistic_style'] ?? 'default' );

        // Pesquisa em tempo real via Gemini
        $clean['enable_gemini_search'] = ! empty( $input['enable_gemini_search'] ) ? '1' : '0';

        return $clean;
    }

    // ── Get / Set helpers ─────────────────────────────────────────────────────

    public static function get_options(): array {
        return (array) get_option( self::OPTION_KEY, self::get_defaults() );
    }

    public static function get( string $key, $fallback = '' ) {
        $opts = self::get_options();
        return $opts[ $key ] ?? $fallback;
    }

    /**
     * Retorna API key descriptografada para uso interno.
     */
    public static function get_api_key( string $provider ): string {
        $encrypted = self::get( $provider . '_api_key', '' );
        return WPAIP_Security::decrypt( $encrypted );
    }

    public static function get_defaults(): array {
        return [
            'openai_api_key'          => '',
            'gemini_api_key'          => '',
            'anthropic_api_key'       => '',
            'deepseek_api_key'        => '',
            'huggingface_api_key'     => '',
            'poe_api_key'             => '',
            'default_llm'             => 'openai',
            'default_image'           => 'pollinations',
            'openai_model'            => 'gpt-4o',
            'gemini_model'            => 'gemini-3.5-flash',
            'anthropic_model'         => 'claude-sonnet-4-5',
            'deepseek_model'          => 'deepseek-chat',
            'openai_image_model'      => 'dall-e-3',
            'huggingface_image_model' => 'black-forest-labs/FLUX.1-schnell',
            'poe_image_bot'           => 'FLUX-schnell',
            'system_prompt'           => 'Você é um redator especialista em SEO e marketing de conteúdo. Escreva em português do Brasil com linguagem clara, objetiva e envolvente.',
            'default_journalistic_style' => 'default',
            'enable_gemini_search'       => '0',
            // Licenciamento Externo
            'license_key'         => '',
            'license_server_url'  => 'https://olive-locust-173119.hostingersite.com/license-server-wp-post/',
            'license_cache_hours' => 24,
        ];
    }

    // ── Assets ────────────────────────────────────────────────────────────────

    public static function enqueue_assets( string $hook ): void {
        // Carrega apenas nas páginas do plugin
        if ( strpos( $hook, WPAIP_SLUG ) === false ) {
            return;
        }

        $css_file = WPAIP_PLUGIN_DIR . 'admin/css/admin.css';
        $js_file  = WPAIP_PLUGIN_DIR . 'admin/js/settings.js';
        $css_ver  = file_exists( $css_file ) ? filemtime( $css_file ) : time();
        $js_ver   = file_exists( $js_file )  ? filemtime( $js_file )  : time();

        wp_enqueue_style(
            'wpaip-admin',
            WPAIP_PLUGIN_URL . 'admin/css/admin.css',
            [],
            $css_ver
        );

        wp_enqueue_script(
            'wpaip-settings',
            WPAIP_PLUGIN_URL . 'admin/js/settings.js',
            [ 'jquery' ],
            $js_ver,
            true
        );

        wp_localize_script( 'wpaip-settings', 'wpaipSettings', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => WPAIP_Security::create_nonce( 'settings' ),
            'strings'  => [
                'testing'       => __( 'Testando…', 'wp-ai-publisher' ),
                'success'       => __( 'Conexão OK', 'wp-ai-publisher' ),
                'fail'          => __( 'Falha na conexão', 'wp-ai-publisher' ),
                'loading_models'=> __( 'Buscando modelos…', 'wp-ai-publisher' ),
                'models_error'  => __( 'Erro ao buscar modelos. Configure a chave do HF primeiro.', 'wp-ai-publisher' ),
            ],
        ] );
    }

    // ── AJAX: Listar Modelos do Hugging Face ────────────────────────────────

    public static function ajax_list_hf_models(): void {
        WPAIP_Security::check_ajax( 'manage_options', 'settings' );

        $api_key = self::get_api_key( 'huggingface' );
        if ( empty( $api_key ) ) {
            wp_send_json_error( [ 'message' => 'Configure a chave do Hugging Face primeiro.' ] );
        }

        $url = 'https://huggingface.co/api/models?pipeline_tag=text-to-image&sort=likes&direction=-1&limit=30&filter=text-to-image';
        $response = wp_remote_get( $url, [
            'headers' => [ 'Authorization' => 'Bearer ' . $api_key ],
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => $response->get_error_message() ] );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( $code !== 200 || ! is_array( $data ) ) {
            wp_send_json_error( [ 'message' => 'Erro HTTP ' . $code ] );
        }

        $models = array_map( fn( $m ) => [
            'id'    => $m['id'] ?? '',
            'likes' => $m['likes'] ?? 0,
        ], $data );

        wp_send_json_success( [ 'models' => $models ] );
    }

    // ── AJAX: Listar Modelos do Poe.com ─────────────────────────────────────

    public static function ajax_list_poe_bots(): void {
        WPAIP_Security::check_ajax( 'manage_options', 'settings' );

        $api_key = self::get_api_key( 'poe' );
        if ( empty( $api_key ) ) {
            wp_send_json_error( [ 'message' => 'Configure a chave do Poe.com primeiro.' ] );
        }

        $url = 'https://api.poe.com/v1/models';
        $response = wp_remote_get( $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => $response->get_error_message() ] );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( $code !== 200 || ! is_array( $data ) || ! isset( $data['data'] ) ) {
            $msg = $data['error']['message'] ?? $data['error'] ?? ( 'Erro HTTP ' . $code );
            wp_send_json_error( [ 'message' => $msg ] );
        }

        $bots = array_map( fn( $m ) => [
            'id' => $m['id'] ?? '',
        ], $data['data'] );

        wp_send_json_success( [ 'bots' => $bots ] );
    }

    // ── AJAX: Ativar/Testar Licença ───────────────────────────────────────────

    public static function ajax_activate_license(): void {
        WPAIP_Security::check_ajax( 'manage_options', 'settings' );

        $license_key = sanitize_text_field( $_POST['license_key'] ?? '' );
        $server_url  = esc_url_raw( $_POST['license_server_url'] ?? '' );

        if ( empty( $license_key ) || empty( $server_url ) ) {
            wp_send_json_error( [ 'message' => 'Chave de licença e URL do servidor são obrigatórios.' ] );
        }

        $result = WPAIP_Paywall::activate_license( $license_key, $server_url );

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    // ── AJAX: Limpar cache Licença ────────────────────────────────────────────

    public static function ajax_clear_license_cache(): void {
        WPAIP_Security::check_ajax( 'manage_options', 'settings' );
        $user_id = (int) ( $_POST['user_id'] ?? get_current_user_id() );
        WPAIP_Paywall::clear_cache( $user_id );
        wp_send_json_success( [ 'message' => 'Cache de licença limpo para o usuário #' . $user_id ] );
    }

    // ── AJAX: Testar API Key ──────────────────────────────────────────────────

    public static function ajax_test_api_key(): void {
        WPAIP_Security::check_ajax( 'manage_options', 'settings' );

        $provider = sanitize_text_field( $_POST['provider'] ?? '' );
        $api_key  = sanitize_text_field( $_POST['api_key']  ?? '' );

        // Se o input de chave vier vazio ou com o placeholder de bolinhas, 
        // e nós já temos uma chave salva no banco, testamos a chave salva!
        if ( empty( $api_key ) || $api_key === '••••••••••••••••' || $api_key === str_repeat( '*', strlen( $api_key ) ) ) {
            $api_key = self::get_api_key( $provider );
        }

        if ( empty( $provider ) || empty( $api_key ) ) {
            wp_send_json_error( [ 'message' => 'Provider e API key são obrigatórios.' ] );
        }

        $result = self::test_connection( $provider, $api_key );

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    private static function test_connection( string $provider, string $api_key ): array {
        $endpoints = [
            'openai'    => [
                'url'     => 'https://api.openai.com/v1/models',
                'headers' => [ 'Authorization' => 'Bearer ' . $api_key ],
            ],
            'poe'       => [
                'url'     => 'https://api.poe.com/v1/models',
                'headers' => [ 'Authorization' => 'Bearer ' . $api_key ],
            ],
            'gemini'    => [
                'url'     => 'https://generativelanguage.googleapis.com/v1beta/models?key=' . $api_key,
                'headers' => [],
            ],
            'anthropic' => [
                'url'     => 'https://api.anthropic.com/v1/models',
                'headers' => [
                    'x-api-key'         => $api_key,
                    'anthropic-version' => '2023-06-01',
                ],
            ],
            'deepseek'  => [
                'url'     => 'https://api.deepseek.com/models',
                'headers' => [ 'Authorization' => 'Bearer ' . $api_key ],
            ],
            'huggingface' => [
                'url'     => 'https://huggingface.co/api/whoami-v2',
                'headers' => [ 'Authorization' => 'Bearer ' . $api_key ],
            ],
        ];

        if ( ! isset( $endpoints[ $provider ] ) ) {
            return [ 'success' => false, 'message' => 'Provider desconhecido: ' . $provider ];
        }

        $ep       = $endpoints[ $provider ];
        $response = wp_remote_get( $ep['url'], [
            'headers' => $ep['headers'],
            'timeout' => 10,
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'message' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );

        if ( $code === 200 ) {
            return [ 'success' => true, 'message' => 'Conexão OK — provider: ' . $provider ];
        }

        $body = wp_remote_retrieve_body( $response );
        $json = json_decode( $body, true );
        $msg  = $json['error']['message'] ?? $json['error'] ?? ( 'HTTP ' . $code );

        return [ 'success' => false, 'message' => $msg ];
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public static function render_settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        require_once WPAIP_PLUGIN_DIR . 'admin/views/settings-page.php';
    }
}
