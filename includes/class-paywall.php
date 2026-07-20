<?php
/**
 * Controle de acesso ao plugin baseado em licença de uso externa.
 * Bloqueia usuários não-pagantes/sem licença ativa e exibe a página de paywall.
 */
defined( 'ABSPATH' ) || exit;

class WPAIP_Paywall {

    const CACHE_META_KEY = 'wpaip_license_access';
    const CACHE_TIME_KEY = 'wpaip_license_access_ts';

    public static function init(): void {
        add_action( 'admin_init', [ __CLASS__, 'check_access' ] );
    }

    // ── Verificação de acesso ─────────────────────────────────────────────────

    public static function check_access(): void {
        // Só atua nas páginas do plugin
        $screen_id = get_current_screen()?->id ?? '';
        $page      = sanitize_text_field( $_GET['page'] ?? '' );

        $is_plugin_page = (
            str_contains( $page, WPAIP_SLUG ) ||
            str_contains( $screen_id, WPAIP_SLUG )
        );

        if ( ! $is_plugin_page ) {
            return;
        }

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return;
        }

        // 1. Admin sempre isentado (se opção ativa)
        $bypass_admins = WPAIP_Settings::get( 'license_bypass_admins', '1' );
        if ( $bypass_admins && current_user_can( 'manage_options' ) ) {
            return;
        }

        // 2. Licença desabilitada? (sem chave configurada) → libera (modo local/grátis)
        $license_key = WPAIP_Security::decrypt( WPAIP_Settings::get( 'license_key', '' ) );
        if ( empty( $license_key ) ) {
            return;
        }

        // 3. Verificar se a licença é válida no servidor externo
        if ( self::is_license_active( $user_id ) ) {
            return;
        }

        // 4. Bloquear → renderizar paywall e encerrar
        self::render_blocked_page();
        exit;
    }

    // ── Método Principal de Verificação de Licença ────────────────────────────

    public static function is_license_active( int $user_id ): bool {
        $license_key = WPAIP_Security::decrypt( WPAIP_Settings::get( 'license_key', '' ) );
        $server_url  = WPAIP_Settings::get( 'license_server_url', '' );

        if ( empty( $license_key ) || empty( $server_url ) ) {
            return true; // Sem licença configurada = liberado
        }

        // Verificar cache local
        $cache_ts = (int) get_user_meta( $user_id, self::CACHE_TIME_KEY, true );
        $cache_val = get_user_meta( $user_id, self::CACHE_META_KEY, true );
        $hours     = max( 1, (int) WPAIP_Settings::get( 'license_cache_hours', 24 ) );
        $ttl       = $hours * HOUR_IN_SECONDS;

        if ( $cache_ts && ( time() - $cache_ts ) < $ttl && $cache_val !== '' ) {
            return (bool) $cache_val;
        }

        // Consultar servidor de licenças
        $result = self::verify_on_server( $license_key, $server_url );

        // Gravar cache
        update_user_meta( $user_id, self::CACHE_META_KEY, $result ? 1 : 0 );
        update_user_meta( $user_id, self::CACHE_TIME_KEY, time() );

        return $result;
    }

    // ── Chamadas de API ao Servidor de Licenças ───────────────────────────────

    /**
     * Consulta o servidor externo para verificar se a licença é ativa.
     */
    private static function verify_on_server( string $key, string $server_url ): bool {
        $clean_domain = preg_replace( '/^https?:\/\//i', '', get_site_url() );
        $clean_domain = rtrim( $clean_domain, '/' );

        $response = wp_remote_post( rtrim( $server_url, '/' ) . '/api/verify.php', [
            'body'    => [
                'license_key' => $key,
                'domain'      => $clean_domain,
            ],
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            // Em caso de erro de rede, podemos opcionalmente falhar de forma segura (liberar)
            // para não quebrar o site do cliente por instabilidade do nosso servidor de licenças.
            return true;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        return ( $code === 200 && ! empty( $body['success'] ) );
    }

    /**
     * Ativa a licença no servidor externo (chamado via AJAX).
     */
    public static function activate_license( string $key, string $server_url ): array {
        $clean_domain = preg_replace( '/^https?:\/\//i', '', get_site_url() );
        $clean_domain = rtrim( $clean_domain, '/' );

        $response = wp_remote_post( rtrim( $server_url, '/' ) . '/api/activate.php', [
            'body'    => [
                'license_key' => $key,
                'domain'      => $clean_domain,
            ],
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'message' => 'Erro de rede: ' . $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code === 200 && ! empty( $body['success'] ) ) {
            // Limpa o cache do usuário atual para forçar verificação
            self::clear_cache( get_current_user_id() );
            return [ 'success' => true, 'message' => $body['message'] ?? 'Licença ativada com sucesso!' ];
        }

        $msg = $body['message'] ?? ( 'Erro HTTP ' . $code );
        return [ 'success' => false, 'message' => $msg ];
    }

    /**
     * Limpa o cache do usuário.
     */
    public static function clear_cache( int $user_id ): void {
        delete_user_meta( $user_id, self::CACHE_META_KEY );
        delete_user_meta( $user_id, self::CACHE_TIME_KEY );
    }

    // ── Página de bloqueio ────────────────────────────────────────────────────

    public static function render_blocked_page(): void {
        // Redireciona o usuário para o servidor de licenças para pagamento/assinatura
        $payment_link = esc_url( WPAIP_Settings::get( 'license_server_url', '#' ) );
        require_once WPAIP_PLUGIN_DIR . 'admin/views/paywall-page.php';
    }
}
