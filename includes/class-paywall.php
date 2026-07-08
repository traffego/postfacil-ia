<?php
/**
 * Controle de acesso ao plugin baseado em status de pagamento no Asaas.
 * Bloqueia usuários não-pagantes e exibe página de paywall.
 */
defined( 'ABSPATH' ) || exit;

class WPAIP_Paywall {

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
        $bypass_admins = WPAIP_Settings::get( 'asaas_bypass_admins', '1' );
        if ( $bypass_admins && current_user_can( 'manage_options' ) ) {
            return;
        }

        // 2. Asaas desabilitado? (sem chave) → libera
        $api_key = WPAIP_Security::decrypt( WPAIP_Settings::get( 'asaas_api_key', '' ) );
        if ( empty( $api_key ) ) {
            return;
        }

        // 3. Verificar se é pagante
        if ( WPAIP_Asaas::is_paying_user( $user_id ) ) {
            return;
        }

        // 4. Bloquear → renderizar paywall e encerrar
        self::render_blocked_page();
        exit;
    }

    // ── Página de bloqueio ────────────────────────────────────────────────────

    public static function render_blocked_page(): void {
        $payment_link = esc_url( WPAIP_Settings::get( 'asaas_payment_link', '#' ) );
        require_once WPAIP_PLUGIN_DIR . 'admin/views/paywall-page.php';
    }
}
