<?php
/**
 * Geração de imagens via APIs de IA.
 * Suporta: DALL-E 3 (OpenAI), Gemini Imagen, Stable Diffusion (via API compatible).
 */
defined( 'ABSPATH' ) || exit;

class WPAIP_Image {

    /**
     * Gera uma imagem e retorna a URL temporária.
     *
     * @param string $prompt    Descrição da imagem (já sanitizada).
     * @param string $provider  'dalle3' | 'gemini' (padrão do settings)
     * @param array  $options   [ 'size' => '1792x1024', 'quality' => 'hd' ]
     * @return array { success: bool, url: string, message: string }
     */
    public static function generate( string $prompt, string $provider = '', array $options = [] ): array {
        if ( empty( $provider ) ) {
            $provider = WPAIP_Settings::get( 'default_image', 'pollinations' );
        }

        $license_key = WPAIP_Security::decrypt( WPAIP_Settings::get( 'license_key', '' ) );
        $server_url  = WPAIP_Settings::get( 'license_server_url', '' );

        if ( empty( $license_key ) || empty( $server_url ) ) {
            return [
                'success' => false,
                'url'     => '',
                'message' => __( 'Chave de licença ou URL do servidor de licenças não configurada.', 'wp-ai-publisher' ),
            ];
        }

        // Recuperar chave do provedor (se necessário)
        $api_key = '';
        if ( $provider === 'dalle3' ) {
            $api_key = WPAIP_Settings::get_api_key( 'openai' );
        } elseif ( $provider === 'gemini' ) {
            $api_key = WPAIP_Settings::get_api_key( 'gemini' );
        } elseif ( $provider === 'huggingface' ) {
            $api_key = WPAIP_Settings::get_api_key( 'huggingface' );
        } elseif ( $provider === 'poe' ) {
            $api_key = WPAIP_Settings::get_api_key( 'poe' );
        }

        if ( empty( $api_key ) && $provider !== 'pollinations' ) {
            return [
                'success' => false,
                'url'     => '',
                'message' => sprintf( __( 'API key para o provedor "%s" não configurada.', 'wp-ai-publisher' ), $provider ),
            ];
        }

        $prompt = WPAIP_Security::prepare_prompt( $prompt, 1000 );

        // Injetar o modelo correto nos options se não vier preenchido
        if ( empty( $options['model'] ) ) {
            if ( $provider === 'huggingface' ) {
                $options['model'] = WPAIP_Settings::get( 'huggingface_image_model', 'black-forest-labs/FLUX.1-schnell' );
            } elseif ( $provider === 'poe' ) {
                $options['model'] = WPAIP_Settings::get( 'poe_image_bot', 'FLUX-schnell' );
            }
        }

        // Roteamento via Gateway do Servidor de Licenças
        $clean_domain = preg_replace( '/^https?:\/\//i', '', get_site_url() );
        $clean_domain = rtrim( $clean_domain, '/' );

        $response = wp_remote_post( rtrim( $server_url, '/' ) . '/api/generate.php', [
            'body'    => [
                'license_key' => $license_key,
                'domain'      => $clean_domain,
                'action'      => 'image',
                'provider'    => $provider,
                'api_key'     => $api_key,
                'prompt'      => $prompt,
                'options'     => wp_json_encode( $options ),
            ],
            'timeout' => 90,
        ] );

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'url'     => '',
                'message' => 'Erro de conexão com o servidor de licenças: ' . $response->get_error_message(),
            ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 || empty( $body ) ) {
            $msg = $body['message'] ?? ( 'Erro HTTP ' . $code );
            return [ 'success' => false, 'url' => '', 'message' => 'Gateway: ' . $msg ];
        }

        if ( empty( $body['success'] ) ) {
            return [ 'success' => false, 'url' => '', 'message' => $body['message'] ?? 'Erro desconhecido no gateway.' ];
        }

        // Se o gateway retornou a imagem em base64 (como Gemini ou Hugging Face)
        if ( ! empty( $body['base64'] ) ) {
            $tmp = sys_get_temp_dir() . '/wpaip_gw_' . uniqid() . '.png';
            file_put_contents( $tmp, base64_decode( $body['base64'] ) );
            return [ 'success' => true, 'url' => $tmp, 'is_local' => true, 'message' => '' ];
        }

        // Se retornou uma URL direta (como DALL-E 3, Pollinations ou Poe)
        return [
            'success' => true,
            'url'     => $body['url'] ?? '',
            'message' => '',
        ];
    }

    // ── AJAX Handlers ─────────────────────────────────────────────────────────

    public static function register_ajax(): void {
        add_action( 'wp_ajax_wpaip_generate_featured_image', [ __CLASS__, 'ajax_generate_featured' ] );
        add_action( 'wp_ajax_wpaip_generate_inline_image',   [ __CLASS__, 'ajax_generate_inline'   ] );
    }

    /**
     * Gera imagem de capa e seta como featured image do post.
     */
    public static function ajax_generate_featured(): void {
        WPAIP_Security::check_ajax( 'edit_posts' );

        $post_id  = (int) ( $_POST['post_id']  ?? 0 );
        $prompt   = sanitize_textarea_field( $_POST['prompt']   ?? '' );
        $provider = sanitize_text_field(     $_POST['provider'] ?? '' );

        if ( ! $post_id || empty( $prompt ) ) {
            wp_send_json_error( [ 'message' => 'post_id e prompt são obrigatórios.' ] );
        }

        // Gera imagem
        $result = self::generate( $prompt, $provider );
        if ( ! $result['success'] ) {
            wp_send_json_error( [ 'message' => $result['message'] ] );
        }

        // Faz upload para biblioteca WP e seta como featured image
        $attachment_id = WPAIP_Media::upload_from_url( $result['url'], $post_id, $prompt );
        if ( is_wp_error( $attachment_id ) ) {
            wp_send_json_error( [ 'message' => $attachment_id->get_error_message() ] );
        }

        set_post_thumbnail( $post_id, $attachment_id );

        $thumb_url = wp_get_attachment_image_url( $attachment_id, 'medium' );

        wp_send_json_success( [
            'attachment_id' => $attachment_id,
            'thumb_url'     => $thumb_url,
            'message'       => 'Imagem de capa definida com sucesso.',
        ] );
    }

    /**
     * Gera imagem ilustrativa para inserir no corpo do post.
     */
    public static function ajax_generate_inline(): void {
        WPAIP_Security::check_ajax( 'edit_posts' );

        $post_id  = (int) ( $_POST['post_id']  ?? 0 );
        $prompt   = sanitize_textarea_field( $_POST['prompt']   ?? '' );
        $provider = sanitize_text_field(     $_POST['provider'] ?? '' );

        if ( empty( $prompt ) ) {
            wp_send_json_error( [ 'message' => 'Prompt vazio.' ] );
        }

        $result = self::generate( $prompt, $provider );
        if ( ! $result['success'] ) {
            wp_send_json_error( [ 'message' => $result['message'] ] );
        }

        $attachment_id = WPAIP_Media::upload_from_url( $result['url'], $post_id ?: null, $prompt );
        if ( is_wp_error( $attachment_id ) ) {
            wp_send_json_error( [ 'message' => $attachment_id->get_error_message() ] );
        }

        $full_url = wp_get_attachment_image_url( $attachment_id, 'large' );

        wp_send_json_success( [
            'attachment_id' => $attachment_id,
            'url'           => $full_url,
            'html'          => sprintf(
                '<img src="%s" alt="%s" class="aligncenter size-large wp-image-%d" />',
                esc_url( $full_url ),
                esc_attr( $prompt ),
                $attachment_id
            ),
        ] );
    }

    // ── DALL-E 3 ──────────────────────────────────────────────────────────────

    private static function call_dalle3( string $prompt, array $opts ): array {
        $api_key = WPAIP_Settings::get_api_key( 'openai' );
        if ( empty( $api_key ) ) {
            return [ 'success' => false, 'url' => '', 'message' => 'API key OpenAI não configurada.' ];
        }

        $body = wp_json_encode( [
            'model'   => 'dall-e-3',
            'prompt'  => $prompt,
            'n'       => 1,
            'size'    => $opts['size']    ?? '1792x1024',
            'quality' => $opts['quality'] ?? 'standard',
        ] );

        $response = wp_remote_post( 'https://api.openai.com/v1/images/generations', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => $body,
            'timeout' => 90,
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'url' => '', 'message' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $msg = $data['error']['message'] ?? ( 'Erro HTTP ' . $code );
            return [ 'success' => false, 'url' => '', 'message' => $msg ];
        }

        $url = $data['data'][0]['url'] ?? '';
        if ( empty( $url ) ) {
            return [ 'success' => false, 'url' => '', 'message' => 'URL de imagem não retornada pelo DALL-E.' ];
        }

        return [ 'success' => true, 'url' => $url, 'message' => '' ];
    }

    // ── Gemini Imagen ─────────────────────────────────────────────────────────

    private static function call_gemini_imagen( string $prompt, array $opts ): array {
        $api_key = WPAIP_Settings::get_api_key( 'gemini' );
        if ( empty( $api_key ) ) {
            return [ 'success' => false, 'url' => '', 'message' => 'API key Gemini não configurada.' ];
        }

        $model = $opts['model'] ?? 'imagen-4.0-generate-001';
        $url   = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:predict?key={$api_key}";

        $body = wp_json_encode( [
            'instances'  => [ [ 'prompt' => $prompt ] ],
            'parameters' => [
                'sampleCount'  => 1,
                'aspectRatio'  => $opts['aspect_ratio'] ?? '16:9',
            ],
        ] );

        $response = wp_remote_post( $url, [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => $body,
            'timeout' => 90,
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'url' => '', 'message' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $msg = $data['error']['message'] ?? ( 'Erro HTTP ' . $code );
            return [ 'success' => false, 'url' => '', 'message' => $msg ];
        }

        // Gemini retorna base64; salvar como arquivo temporário
        $b64 = $data['predictions'][0]['bytesBase64Encoded'] ?? '';
        if ( empty( $b64 ) ) {
            return [ 'success' => false, 'url' => '', 'message' => 'Imagem não retornada pelo Gemini Imagen.' ];
        }

        // Salvar em temp e retornar caminho como "url" (WPAIP_Media sabe lidar)
        $tmp  = sys_get_temp_dir() . '/wpaip_gemini_' . uniqid() . '.png';
        file_put_contents( $tmp, base64_decode( $b64 ) );

        return [ 'success' => true, 'url' => $tmp, 'is_local' => true, 'message' => '' ];
    }

    private static function call_pollinations( string $prompt, array $opts ): array {
        $width  = $opts['width'] ?? 1024;
        $height = $opts['height'] ?? 576;
        $url    = 'https://image.pollinations.ai/prompt/' . urlencode( $prompt ) . "?width={$width}&height={$height}&nologo=true&private=true";

        return [ 'success' => true, 'url' => $url, 'message' => '' ];
    }

    private static function call_huggingface( string $prompt, array $opts ): array {
        $api_key = WPAIP_Settings::get_api_key( 'huggingface' );
        if ( empty( $api_key ) ) {
            return [ 'success' => false, 'url' => '', 'message' => 'API key Hugging Face não configurada.' ];
        }

        // Modelo configurado pelo usuário nas configurações do plugin (padrão: FLUX.1-schnell)
        $model = $opts['model'] ?? WPAIP_Settings::get( 'huggingface_image_model', 'black-forest-labs/FLUX.1-schnell' );
        $url   = "https://router.huggingface.co/hf-inference/models/{$model}";

        $body = wp_json_encode( [ 'inputs' => $prompt ] );

        $response = wp_remote_post( $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => $body,
            'timeout' => 90,
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'url' => '', 'message' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body_response = wp_remote_retrieve_body( $response );

        if ( $code !== 200 ) {
            $json = json_decode( $body_response, true );
            $msg  = $json['error'] ?? $json['error']['message'] ?? ( 'Erro HTTP ' . $code );
            // Se o modelo estiver carregando (comum no Hugging Face free tier)
            if ( isset( $json['estimated_time'] ) ) {
                $msg = sprintf( 'O modelo está carregando nos servidores do Hugging Face. Tempo estimado: %d segundos. Tente novamente em breve.', (int) $json['estimated_time'] );
            }
            return [ 'success' => false, 'url' => '', 'message' => $msg ];
        }

        if ( empty( $body_response ) ) {
            return [ 'success' => false, 'url' => '', 'message' => 'Imagem vazia retornada pelo Hugging Face.' ];
        }

        // Hugging Face retorna os bytes da imagem diretamente. Salvar como arquivo temporário local
        $tmp  = sys_get_temp_dir() . '/wpaip_hf_' . uniqid() . '.png';
        file_put_contents( $tmp, $body_response );

        return [ 'success' => true, 'url' => $tmp, 'is_local' => true, 'message' => '' ];
    }

    /**
     * Gera uma imagem via API compatível com OpenAI do Poe.com.
     *
     * @param string $prompt
     * @param array  $opts
     * @return array
     */
    private static function call_poe( string $prompt, array $opts ): array {
        $api_key = WPAIP_Settings::get_api_key( 'poe' );
        if ( empty( $api_key ) ) {
            return [ 'success' => false, 'url' => '', 'message' => 'API key Poe.com não configurada.' ];
        }

        $model = $opts['model'] ?? WPAIP_Settings::get( 'poe_image_bot', 'FLUX-schnell' );
        $url   = 'https://api.poe.com/v1/chat/completions';

        $body = wp_json_encode( [
            'model'    => $model,
            'messages' => [
                [ 'role' => 'user', 'content' => $prompt ]
            ],
            'stream'   => false
        ] );

        $response = wp_remote_post( $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => $body,
            'timeout' => 90,
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'url' => '', 'message' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body_response = wp_remote_retrieve_body( $response );
        $data = json_decode( $body_response, true );

        if ( $code !== 200 ) {
            $msg = $data['error']['message'] ?? $data['error'] ?? ( 'Erro HTTP ' . $code );
            return [ 'success' => false, 'url' => '', 'message' => $msg ];
        }

        $content = $data['choices'][0]['message']['content'] ?? '';
        if ( empty( $content ) ) {
            return [ 'success' => false, 'url' => '', 'message' => 'Resposta vazia do Poe.' ];
        }

        // Tenta extrair a URL da imagem no formato Markdown: ![image](https://...)
        if ( preg_match( '/!\[.*?\]\((https?:\/\/[^\s\)]+)\)/i', $content, $matches ) ) {
            $image_url = $matches[1];
        } else {
            // Fallback: tenta capturar qualquer link que pareça uma URL de imagem
            if ( preg_match( '/(https?:\/\/[^\s\)]+\.(?:png|jpg|jpeg|webp)(?:\?[^\s\)]*)?)/i', $content, $matches ) ) {
                $image_url = $matches[1];
            } else {
                // Fallback final: tenta capturar qualquer URL iniciada por http/https
                if ( preg_match( '/(https?:\/\/[^\s\)]+)/i', $content, $matches ) ) {
                    $image_url = $matches[1];
                } else {
                    return [ 'success' => false, 'url' => '', 'message' => 'Não foi possível extrair a URL da imagem da resposta do Poe: ' . esc_html( $content ) ];
                }
            }
        }

        $image_url = trim( $image_url, '()"\' ' );

        return [ 'success' => true, 'url' => $image_url, 'message' => '' ];
    }
}
