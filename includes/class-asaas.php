<?php
/**
 * Integração com a API Asaas.
 * Verifica se o usuário logado possui assinatura ativa ou cobrança confirmada.
 */
defined( 'ABSPATH' ) || exit;

class WPAIP_Asaas {

    const CACHE_META_KEY = 'wpaip_asaas_access';
    const CACHE_TIME_KEY = 'wpaip_asaas_access_ts';

    // ── Config ───────────────────────────────────────────────────────────────

    private static function base_url(): string {
        $env = WPAIP_Settings::get( 'asaas_environment', 'sandbox' );
        return $env === 'production'
            ? 'https://api.asaas.com'
            : 'https://sandbox.asaas.com/api';
    }

    private static function api_key(): string {
        return WPAIP_Security::decrypt( WPAIP_Settings::get( 'asaas_api_key', '' ) );
    }

    private static function cache_hours(): int {
        return max( 1, (int) WPAIP_Settings::get( 'asaas_cache_hours', 24 ) );
    }

    // ── HTTP Helper ───────────────────────────────────────────────────────────

    private static function get( string $path ): array|false {
        $key      = self::api_key();
        $response = wp_remote_get( self::base_url() . $path, [
            'headers' => [
                'access_token' => $key,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 10,
        ] );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 || ! is_array( $body ) ) {
            return false;
        }

        return $body;
    }

    // ── Customer ──────────────────────────────────────────────────────────────

    /**
     * Busca cliente pelo e-mail. Retorna o ID do primeiro resultado ou null.
     */
    public static function get_customer_id_by_email( string $email ): ?string {
        $data = self::get( '/v3/customers?email=' . rawurlencode( $email ) . '&limit=1' );
        if ( ! $data || empty( $data['data'][0]['id'] ) ) {
            return null;
        }
        return (string) $data['data'][0]['id'];
    }

    // ── Assinatura ────────────────────────────────────────────────────────────

    /**
     * Verifica se o cliente tem assinatura ACTIVE.
     */
    public static function has_active_subscription( string $customer_id ): bool {
        $data = self::get( '/v3/subscriptions?customer=' . rawurlencode( $customer_id ) . '&status=ACTIVE&limit=1' );
        return ! empty( $data['data'][0]['id'] );
    }

    // ── Cobrança ──────────────────────────────────────────────────────────────

    /**
     * Verifica se o cliente tem cobrança CONFIRMED ou RECEIVED.
     */
    public static function has_confirmed_payment( string $customer_id ): bool {
        $data = self::get( '/v3/payments?customer=' . rawurlencode( $customer_id ) . '&status=CONFIRMED,RECEIVED&limit=1' );
        return ! empty( $data['data'][0]['id'] );
    }

    // ── Método principal ──────────────────────────────────────────────────────

    /**
     * Verifica (com cache em user_meta) se o usuário WP atual é pagante.
     *
     * @param int $user_id  ID do usuário WordPress.
     * @return bool
     */
    public static function is_paying_user( int $user_id ): bool {
        // Verificar cache
        $cache_ts = (int) get_user_meta( $user_id, self::CACHE_TIME_KEY, true );
        $ttl      = self::cache_hours() * HOUR_IN_SECONDS;

        if ( $cache_ts && ( time() - $cache_ts ) < $ttl ) {
            return (bool) get_user_meta( $user_id, self::CACHE_META_KEY, true );
        }

        // Sem cache válido → consultar API
        $user  = get_userdata( $user_id );
        $email = $user ? $user->user_email : '';

        $result = false;

        if ( ! empty( $email ) ) {
            $customer_id = self::get_customer_id_by_email( $email );
            if ( $customer_id ) {
                $result = self::has_active_subscription( $customer_id )
                       || self::has_confirmed_payment( $customer_id );
            }
        }

        // Gravar cache
        update_user_meta( $user_id, self::CACHE_META_KEY, $result ? 1 : 0 );
        update_user_meta( $user_id, self::CACHE_TIME_KEY, time() );

        return $result;
    }

    /**
     * Limpa o cache de acesso de um usuário (força re-verificação na próxima requisição).
     */
    public static function clear_cache( int $user_id ): void {
        delete_user_meta( $user_id, self::CACHE_META_KEY );
        delete_user_meta( $user_id, self::CACHE_TIME_KEY );
    }

    // ── Teste de conexão ──────────────────────────────────────────────────────

    /**
     * Testa se a API key consegue se autenticar (GET /v3/customers?limit=1).
     * Retorna array ['success' => bool, 'message' => string].
     */
    public static function test_connection(): array {
        if ( empty( self::api_key() ) ) {
            return [ 'success' => false, 'message' => 'Chave Asaas não configurada.' ];
        }

        $data = self::get( '/v3/customers?limit=1' );

        if ( $data !== false ) {
            return [ 'success' => true, 'message' => 'Conexão com Asaas OK.' ];
        }

        return [ 'success' => false, 'message' => 'Falha ao conectar na API Asaas. Verifique a chave e o ambiente.' ];
    }
}
