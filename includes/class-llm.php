<?php
/**
 * Abstração para chamadas a provedores LLM.
 * Suporta: OpenAI, Google Gemini, Anthropic Claude, DeepSeek.
 */
defined( 'ABSPATH' ) || exit;

class WPAIP_LLM {

    /**
     * Gera texto usando o provider configurado.
     *
     * @param string $prompt  Prompt do usuário (já sanitizado).
     * @param string $provider 'openai' | 'gemini' | 'anthropic' | 'deepseek'
     * @param array  $options  [ 'model' => '', 'max_tokens' => 1500, 'system' => '' ]
     * @return array { success: bool, text: string, message: string }
     */
    public static function generate( string $prompt, string $provider = '', array $options = [] ): array {
        if ( empty( $provider ) ) {
            $provider = WPAIP_Settings::get( 'default_llm', 'openai' );
        }

        $api_key = WPAIP_Settings::get_api_key( $provider );
        if ( empty( $api_key ) ) {
            return [
                'success' => false,
                'text'    => '',
                'message' => sprintf( __( 'API key para "%s" não configurada.', 'wp-ai-publisher' ), $provider ),
            ];
        }

        $system = $options['system'] ?? WPAIP_Settings::get( 'system_prompt' );
        $prompt = WPAIP_Security::prepare_prompt( $prompt, 4000 );

        switch ( $provider ) {
            case 'openai':
                return self::call_openai( $api_key, $prompt, $system, $options );
            case 'gemini':
                return self::call_gemini( $api_key, $prompt, $system, $options );
            case 'anthropic':
                return self::call_anthropic( $api_key, $prompt, $system, $options );
            case 'deepseek':
                return self::call_deepseek( $api_key, $prompt, $system, $options );
            default:
                return [ 'success' => false, 'text' => '', 'message' => 'Provider desconhecido: ' . $provider ];
        }
    }

    // ── AJAX Handler ──────────────────────────────────────────────────────────

    public static function register_ajax(): void {
        add_action( 'wp_ajax_wpaip_generate_text', [ __CLASS__, 'ajax_generate_text' ] );
    }

    public static function ajax_generate_text(): void {
        WPAIP_Security::check_ajax( 'edit_posts' );

        $prompt   = sanitize_textarea_field( $_POST['prompt']   ?? '' );
        $provider = sanitize_text_field(     $_POST['provider'] ?? '' );
        $model    = sanitize_text_field(     $_POST['model']    ?? '' );
        $mode     = sanitize_text_field(     $_POST['mode']     ?? 'draft' ); // draft | expand | summarize

        if ( empty( $prompt ) ) {
            wp_send_json_error( [ 'message' => 'Prompt vazio.' ] );
        }

        // Monta prompt baseado no modo
        $final_prompt = self::build_prompt( $prompt, $mode );

        $result = self::generate( $final_prompt, $provider, [ 'model' => $model ] );

        if ( ! $result['success'] ) {
            wp_send_json_error( [ 'message' => $result['message'] ] );
        }

        // Modo draft: tenta extrair título do JSON retornado
        $title = '';
        $text  = $result['text'];

        if ( $mode === 'draft' ) {
            // Remove possível bloco de código markdown: ```json ... ```
            $clean = preg_replace( '/^```(?:json)?\s*/i', '', trim( $text ) );
            $clean = preg_replace( '/\s*```$/i', '', $clean );
            $decoded = json_decode( $clean, true );

            if ( is_array( $decoded ) && isset( $decoded['content'] ) ) {
                $title = sanitize_text_field( $decoded['title'] ?? '' );
                $text  = $decoded['content'];
            }
        }

        wp_send_json_success( [ 'text' => $text, 'title' => $title ] );
    }

    // ── Prompt builder ────────────────────────────────────────────────────────

    private static function build_prompt( string $input, string $mode ): string {
        switch ( $mode ) {
            case 'expand':
                return "Expanda o seguinte trecho, mantendo o estilo e tom. Retorne apenas o texto expandido, sem comentários:\n\n{$input}";
            case 'summarize':
                return "Resuma o seguinte texto de forma clara e concisa. Retorne apenas o resumo:\n\n{$input}";
            case 'draft':
            default:
                return 'Crie um artigo de blog completo e bem estruturado sobre o seguinte tema. '
                     . 'Use subtítulos H2 e H3, parágrafos envolventes e linguagem acessível. '
                     . 'Retorne SOMENTE um objeto JSON válido (sem markdown, sem texto extra) com duas chaves: '
                     . '"title" (string com o título do artigo) e "content" (string com o artigo em HTML semântico). '
                     . "Tema:\n\n{$input}";
        }
    }

    // ── OpenAI ────────────────────────────────────────────────────────────────

    private static function call_openai( string $key, string $prompt, string $system, array $opts ): array {
        $model      = $opts['model'] ?? WPAIP_Settings::get( 'openai_model', 'gpt-4o' );
        $max_tokens = (int) ( $opts['max_tokens'] ?? 2000 );

        $body = wp_json_encode( [
            'model'      => $model,
            'max_tokens' => $max_tokens,
            'messages'   => [
                [ 'role' => 'system', 'content' => $system ],
                [ 'role' => 'user',   'content' => $prompt ],
            ],
        ] );

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => $body,
            'timeout' => 120,
        ] );

        return self::parse_response( $response, function ( array $data ): string {
            return $data['choices'][0]['message']['content'] ?? '';
        } );
    }

    // ── Google Gemini ─────────────────────────────────────────────────────────

    private static function call_gemini( string $key, string $prompt, string $system, array $opts ): array {
        $model = $opts['model'] ?? WPAIP_Settings::get( 'gemini_model', 'gemini-2.0-flash' );
        $url   = "https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key={$key}";

        // Combina instrução de sistema com o prompt para evitar problemas de compatibilidade de campos no JSON do v1
        $combined_prompt = "Instruções do Sistema:\n{$system}\n\nTarefa:\n{$prompt}";

        $body = wp_json_encode( [
            'contents'           => [ [ 'parts' => [ [ 'text' => $combined_prompt ] ] ] ],
            'generationConfig'   => [ 'maxOutputTokens' => $opts['max_tokens'] ?? 2000 ],
        ] );

        $response = wp_remote_post( $url, [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => $body,
            'timeout' => 120,
        ] );

        return self::parse_response( $response, function ( array $data ): string {
            return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        } );
    }

    // ── Anthropic Claude ──────────────────────────────────────────────────────

    private static function call_anthropic( string $key, string $prompt, string $system, array $opts ): array {
        $model      = $opts['model'] ?? WPAIP_Settings::get( 'anthropic_model', 'claude-sonnet-4-5' );
        $max_tokens = (int) ( $opts['max_tokens'] ?? 2000 );

        $body = wp_json_encode( [
            'model'      => $model,
            'max_tokens' => $max_tokens,
            'system'     => $system,
            'messages'   => [
                [ 'role' => 'user', 'content' => $prompt ],
            ],
        ] );

        $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
            'headers' => [
                'x-api-key'         => $key,
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ],
            'body'    => $body,
            'timeout' => 120,
        ] );

        return self::parse_response( $response, function ( array $data ): string {
            return $data['content'][0]['text'] ?? '';
        } );
    }

    // ── DeepSeek ──────────────────────────────────────────────────────────────

    private static function call_deepseek( string $key, string $prompt, string $system, array $opts ): array {
        $model      = $opts['model'] ?? WPAIP_Settings::get( 'deepseek_model', 'deepseek-chat' );
        $max_tokens = (int) ( $opts['max_tokens'] ?? 2000 );

        $body = wp_json_encode( [
            'model'      => $model,
            'max_tokens' => $max_tokens,
            'messages'   => [
                [ 'role' => 'system', 'content' => $system ],
                [ 'role' => 'user',   'content' => $prompt ],
            ],
        ] );

        $response = wp_remote_post( 'https://api.deepseek.com/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => $body,
            'timeout' => 120,
        ] );

        return self::parse_response( $response, function ( array $data ): string {
            return $data['choices'][0]['message']['content'] ?? '';
        } );
    }

    // ── Utilitário: parse de resposta HTTP ────────────────────────────────────

    private static function parse_response( $response, callable $extractor ): array {
        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'text' => '', 'message' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( $code !== 200 ) {
            $msg = $data['error']['message'] ?? ( 'Erro HTTP ' . $code );
            return [ 'success' => false, 'text' => '', 'message' => $msg ];
        }

        $text = call_user_func( $extractor, $data );

        if ( empty( $text ) ) {
            return [ 'success' => false, 'text' => '', 'message' => 'Resposta vazia do modelo.' ];
        }

        return [ 'success' => true, 'text' => $text, 'message' => '' ];
    }
}
