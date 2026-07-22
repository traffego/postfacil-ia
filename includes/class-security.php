<?php
/**
 * Segurança: sanitização, nonces e proteção contra prompt injection.
 */
defined( 'ABSPATH' ) || exit;

class WPAIP_Security {

    /**
     * Gera ou verifica nonce para ações AJAX do plugin.
     */
    public static function nonce_action( string $suffix = 'action' ): string {
        return WPAIP_SLUG . '-' . $suffix;
    }

    public static function create_nonce( string $suffix = 'action' ): string {
        return wp_create_nonce( self::nonce_action( $suffix ) );
    }

    public static function verify_nonce( string $nonce, string $suffix = 'action' ): bool {
        return (bool) wp_verify_nonce( $nonce, self::nonce_action( $suffix ) );
    }

    /**
     * Verifica nonce AJAX e capability. Morre com erro JSON se falhar.
     */
    public static function check_ajax( string $capability = 'edit_posts', string $suffix = 'action' ): void {
        $nonce = sanitize_text_field( $_POST['nonce'] ?? '' );
        if ( ! self::verify_nonce( $nonce, $suffix ) || ! current_user_can( $capability ) ) {
            wp_send_json_error( [ 'message' => __( 'Ação não autorizada.', 'wp-ai-publisher' ) ], 403 );
        }
    }

    /**
     * Sanitiza texto de usuário para uso como prompt.
     * Remove injeções comuns: "ignore all previous instructions", etc.
     */
    public static function sanitize_prompt( string $input ): string {
        $input = wp_strip_all_tags( $input );
        $input = sanitize_textarea_field( $input );

        // Padrões de prompt injection mais comuns
        $injection_patterns = [
            '/ignore\s+(all\s+)?previous\s+instructions?/i',
            '/forget\s+(all\s+)?previous\s+instructions?/i',
            '/you\s+are\s+now\s+(a|an)\s+/i',
            '/act\s+as\s+if\s+you\s+(are|were)\s+/i',
            '/disregard\s+(your|all)\s+/i',
            '/system\s*:\s*/i',
            '/<\|.*?\|>/i',  // tokens especiais como <|endoftext|>
        ];

        foreach ( $injection_patterns as $pattern ) {
            $input = preg_replace( $pattern, '[REDACTED]', $input );
        }

        return trim( $input );
    }

    /**
     * Sanitiza e limita tamanho de um prompt para APIs externas.
     */
    public static function prepare_prompt( string $raw, int $max_chars = 2000 ): string {
        $clean = self::sanitize_prompt( $raw );
        return mb_substr( $clean, 0, $max_chars );
    }

    /**
     * Criptografa uma string (API key) antes de salvar no banco.
     */
    public static function encrypt( string $value ): string {
        if ( empty( $value ) ) {
            return '';
        }

        $key    = self::get_encryption_key();
        $iv     = openssl_random_pseudo_bytes( 16 );
        $cipher = openssl_encrypt( $value, 'AES-256-CBC', $key, 0, $iv );

        return base64_encode( $iv . $cipher );
    }

    /**
     * Descriptografa uma API key ou chave de licença salva no banco.
     */
    public static function decrypt( string $encrypted ): string {
        if ( empty( $encrypted ) ) {
            return '';
        }

        // Se for uma chave de licença em texto limpo (ex: WPAIP-... ou DEV-...)
        if ( strpos( $encrypted, 'WPAIP-' ) === 0 || strpos( $encrypted, 'DEV-' ) === 0 ) {
            return trim( $encrypted );
        }

        $key  = self::get_encryption_key();
        $data = base64_decode( $encrypted, true );
        if ( false === $data || strlen( $data ) <= 16 ) {
            return trim( $encrypted );
        }

        $iv   = substr( $data, 0, 16 );
        $text = substr( $data, 16 );

        $result = openssl_decrypt( $text, 'AES-256-CBC', $key, 0, $iv );

        if ( false === $result || empty( $result ) ) {
            return trim( $encrypted );
        }

        return trim( $result );
    }

    /**
     * Chave de criptografia derivada de AUTH_KEY do wp-config.php.
     * Garante que a chave é específica por instalação WordPress.
     */
    private static function get_encryption_key(): string {
        $salt = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'wpaip-fallback-key-change-in-production';
        return substr( hash( 'sha256', $salt . WPAIP_SLUG ), 0, 32 );
    }
}
