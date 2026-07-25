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
        $server_url  = WPAIP_Settings::get( 'license_server_url', WPAIP_Paywall::DEFAULT_SERVER );
        if ( empty( $server_url ) ) {
            $server_url = WPAIP_Paywall::DEFAULT_SERVER;
        }

        if ( empty( $license_key ) ) {
            return [
                'success' => false,
                'url'     => '',
                'message' => __( 'Chave de licença não configurada. Por favor, ative sua licença.', 'wp-ai-publisher' ),
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
        } elseif ( $provider === 'apiframe' ) {
            $api_key = WPAIP_Settings::get_api_key( 'apiframe' );
        } elseif ( $provider === 'pollinations' ) {
            $api_key = WPAIP_Settings::get_api_key( 'pollinations' );
        } elseif ( $provider === 'cloudflare' ) {
            $api_key = WPAIP_Settings::get_api_key( 'cloudflare' );
        }

        if ( empty( $api_key ) && $provider !== 'pollinations' ) {
            return [
                'success' => false,
                'url'     => '',
                'message' => sprintf( __( 'API key para o provedor "%s" não configurada.', 'wp-ai-publisher' ), $provider ),
            ];
        }

        $prompt = WPAIP_Security::prepare_prompt( $prompt, 1000 );

        // ── Mapeamento estrito de Estilos de Imagem ──────────────────────────────────
        $image_style = $options['image_style'] ?? sanitize_text_field( $_POST['style'] ?? 'photo' );
        $style_directives = [
            'photo' => 'STYLE MANDATE: Fotojornalístico / Realista. Authentic real-world photograph, 35mm lens, natural lighting, sharp focus, true-to-life colors, fine textures. ABSOLUTELY NO 3D rendering, NO cartoon, NO CGI, NO digital painting, NO fantasy illustration, NO surreal elements.',
            'cinematic' => 'STYLE MANDATE: Cinematográfico. Cinematic movie screenshot, dramatic volumetric lighting, 8k resolution, anamorphic lens flare, rich color grading, film grain, atmospheric depth.',
            'illustration_3d' => 'STYLE MANDATE: Ilustração 3D. High-end 3D digital illustration, smooth Octane/Redshift render style, soft studio lighting, clean 3D digital model and environment.',
            'digital_art' => 'STYLE MANDATE: Arte Digital / Conceitual. Rich digital concept art, artistic brush strokes, expressive digital painting style, creative composition, vibrant color palette.',
            'vector' => 'STYLE MANDATE: Vetor / Minimalista. Flat vector illustration, clean lines, bold solid colors, modern corporate minimalist tech graphic, isolated background.',
            'anime' => 'STYLE MANDATE: Anime / Manga. High quality Japanese anime manga art style, clean linework, vibrant cel-shading, anime aesthetic.',
            'vintage' => 'STYLE MANDATE: Retrô / Vintage. Vintage retro analog photograph from 1970s/1980s, authentic film grain, warm faded Kodachrome/sepia tones, nostalgic aesthetic.',
        ];
        $selected_style_mandate = $style_directives[ $image_style ] ?? $style_directives['photo'];

        // ── Aprimoramento automático do prompt de imagem via LLM + Busca Web de Referências ──
        $llm_provider = WPAIP_Settings::get( 'default_llm', 'openai' );
        $llm_key      = WPAIP_Settings::get_api_key( $llm_provider );

        if ( ! empty( $llm_key ) ) {
            // Passo 1: Se houver chave do Gemini configurada, realiza busca na web por referências visuais reais
            $web_reference = '';
            $gemini_key    = WPAIP_Settings::get_api_key( 'gemini' );
            if ( ! empty( $gemini_key ) && ( $image_style === 'photo' || $image_style === 'cinematic' ) ) {
                $search_query  = "fotografia real características visuais exatas aparência física de: {$prompt}";
                $web_reference = WPAIP_LLM::fetch_web_context_via_gemini( $search_query );
            }

            // Passo 2: Otimiza o prompt combinando as referências reais da web e o estilo obrigatório
            $enhancer_system = "You are an expert AI prompt engineer specialized in text-to-image models. Convert the user input into a highly detailed descriptive prompt in ENGLISH.\n\n{$selected_style_mandate}\n\nIMPORTANT: Maintain absolute subject accuracy and strictly follow the STYLE MANDATE. Return ONLY the final raw prompt text in English, with no quotes, no markdown, and no explanations.";
            
            $user_content = "Target Subject: {$prompt}";
            if ( ! empty( $web_reference ) ) {
                $user_content .= "\n\nReal-World Reference Facts from Web Search:\n{$web_reference}";
            }

            $llm_res = WPAIP_LLM::generate( $user_content, $llm_provider, [
                'system'     => $enhancer_system,
                'max_tokens' => 350,
            ] );

            if ( $llm_res['success'] && ! empty( $llm_res['text'] ) ) {
                $prompt = trim( $llm_res['text'] );
            }
        } else {
            // Caso LLM não configurado, anexa a diretiva de estilo diretamente no prompt
            $prompt .= ', ' . $selected_style_mandate;
        }

        // Injetar o modelo correto nos options se não vier preenchido ou for depreciado
        if ( empty( $options['model'] ) || strpos( $options['model'], 'imagen' ) !== false ) {
            if ( $provider === 'huggingface' ) {
                $options['model'] = WPAIP_Settings::get( 'huggingface_image_model', 'black-forest-labs/FLUX.1-schnell' );
            } elseif ( $provider === 'poe' ) {
                $options['model'] = WPAIP_Settings::get( 'poe_image_bot', 'FLUX-schnell' );
            } elseif ( $provider === 'gemini' ) {
                $options['model'] = WPAIP_Settings::get( 'gemini_image_model', 'gemini-2.5-flash-image' );
            } elseif ( $provider === 'apiframe' ) {
                $options['model'] = WPAIP_Settings::get( 'apiframe_image_model', 'midjourney' );
            } elseif ( $provider === 'cloudflare' ) {
                $options['model'] = WPAIP_Settings::get( 'cloudflare_image_model', '@cf/black-forest-labs/flux-1-schnell' );
            }
        }

        // Roteamento via Gateway do Servidor de Licenças
        $clean_domain = strtolower( preg_replace( '/^https?:\/\//i', '', get_site_url() ) );
        $clean_domain = explode( '/', $clean_domain )[0];
        $clean_domain = explode( ':', $clean_domain )[0];

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
        add_action( 'wp_ajax_wpaip_image_popup_view',       [ __CLASS__, 'ajax_popup_view'        ] );
    }

    /**
     * Gera imagem de capa e seta como featured image do post.
     */
    public static function ajax_generate_featured(): void {
        WPAIP_Security::check_ajax( 'edit_posts' );

        $post_id  = (int) ( $_POST['post_id']  ?? 0 );
        $prompt   = sanitize_textarea_field( $_POST['prompt']   ?? '' );
        $provider = sanitize_text_field(     $_POST['provider'] ?? '' );
        $style    = sanitize_text_field(     $_POST['style']    ?? 'photo' );

        if ( ! $post_id || empty( $prompt ) ) {
            wp_send_json_error( [ 'message' => 'post_id e prompt são obrigatórios.' ] );
        }

        // Gera imagem
        $result = self::generate( $prompt, $provider, [ 'image_style' => $style ] );
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
        $style    = sanitize_text_field(     $_POST['style']    ?? 'photo' );

        if ( empty( $prompt ) ) {
            wp_send_json_error( [ 'message' => 'Prompt vazio.' ] );
        }

        $result = self::generate( $prompt, $provider, [ 'image_style' => $style ] );
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

    // ── OpenAI Image Models (DALL-E 3, DALL-E 2, GPT Image 2) ──────────────────

    private static function call_dalle3( string $prompt, array $opts ): array {
        return self::call_openai_image( $prompt, $opts );
    }

    private static function call_openai_image( string $prompt, array $opts ): array {
        $api_key = WPAIP_Settings::get_api_key( 'openai' );
        if ( empty( $api_key ) ) {
            return [ 'success' => false, 'url' => '', 'message' => 'API key OpenAI não configurada.' ];
        }

        $model = $opts['model'] ?? WPAIP_Settings::get( 'openai_image_model', 'dall-e-3' );

        $payload = [
            'model'  => $model,
            'prompt' => $prompt,
            'n'      => 1,
        ];

        if ( $model === 'dall-e-2' ) {
            $payload['size'] = $opts['size'] ?? '1024x1024';
        } elseif ( $model === 'gpt-image-2' ) {
            $payload['size']    = $opts['size'] ?? '1024x1024';
            $payload['quality'] = $opts['quality'] ?? 'high';
        } else {
            // dall-e-3
            $payload['size']    = $opts['size'] ?? '1792x1024';
            $payload['quality'] = $opts['quality'] ?? 'standard';
        }

        $response = wp_remote_post( 'https://api.openai.com/v1/images/generations', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( $payload ),
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
        if ( empty( $url ) && ! empty( $data['data'][0]['b64_json'] ) ) {
            $url = 'data:image/png;base64,' . $data['data'][0]['b64_json'];
        }

        if ( empty( $url ) ) {
            return [ 'success' => false, 'url' => '', 'message' => 'URL de imagem não retornada pela OpenAI.' ];
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

    /**
     * Renderiza o HTML da janela flutuante dos geradores GPT e Nano Banana.
     */
    public static function ajax_popup_view(): void {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( __( 'Acesso negado.', 'wp-ai-publisher' ) );
        }

        $provider = sanitize_text_field( $_GET['provider'] ?? 'dalle3' );
        $style    = sanitize_text_field( $_GET['style']    ?? 'photo' );
        $prompt   = sanitize_textarea_field( $_GET['prompt'] ?? '' );
        $post_id  = (int) ( $_GET['post_id'] ?? 0 );

        $is_gpt   = ( $provider === 'dalle3' || $provider === 'openai' );
        $icon     = $is_gpt ? '⚡' : '🍌';
        $title    = $is_gpt ? 'Gerador Flutuante GPT (OpenAI)' : 'Gerador Flutuante Nano Banana (Gemini)';
        $badge_bg = $is_gpt ? '#10a37f' : '#f59e0b';

        header( 'Content-Type: text/html; charset=UTF-8' );
        ?>
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <title><?php echo esc_html( $title ); ?></title>
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                    background: #0f172a;
                    color: #f8fafc;
                    padding: 20px;
                    display: flex;
                    flex-direction: column;
                    min-height: 100vh;
                }
                .header {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    padding-bottom: 15px;
                    border-bottom: 1px solid rgba(255,255,255,0.1);
                    margin-bottom: 20px;
                }
                .header .icon-badge {
                    background: <?php echo $badge_bg; ?>;
                    color: #fff;
                    width: 36px;
                    height: 36px;
                    border-radius: 10px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 20px;
                    font-weight: bold;
                }
                .header h1 { font-size: 16px; font-weight: 700; }
                .field { margin-bottom: 16px; }
                label { display: block; font-size: 11px; font-weight: 700; color: #cbd5e1; text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.05em; }
                textarea, select {
                    width: 100%;
                    background: #1e293b;
                    border: 1px solid #334155;
                    color: #f8fafc;
                    padding: 10px 12px;
                    border-radius: 8px;
                    font-size: 13px;
                    outline: none;
                }
                textarea:focus, select:focus { border-color: #6366f1; }
                .btn {
                    width: 100%;
                    background: linear-gradient(135deg, #6366f1, #4f46e5);
                    color: #fff;
                    border: none;
                    padding: 12px;
                    border-radius: 8px;
                    font-size: 14px;
                    font-weight: 700;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 8px;
                    transition: opacity 0.2s;
                }
                .btn:disabled { opacity: 0.6; cursor: not-allowed; }
                .status {
                    margin-top: 12px;
                    font-size: 13px;
                    padding: 10px;
                    border-radius: 6px;
                    display: none;
                }
                .status.info { background: rgba(99,102,241,0.15); color: #818cf8; border: 1px solid rgba(99,102,241,0.3); }
                .status.error { background: rgba(239,68,68,0.15); color: #f87171; border: 1px solid rgba(239,68,68,0.3); }
                .preview-card {
                    margin-top: 20px;
                    background: #1e293b;
                    border-radius: 12px;
                    padding: 14px;
                    display: none;
                    border: 1px solid #334155;
                }
                .preview-card img { width: 100%; border-radius: 8px; display: block; margin-bottom: 12px; }
                .action-btns { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
                .action-btn {
                    background: #334155;
                    color: #f8fafc;
                    border: none;
                    padding: 10px;
                    border-radius: 6px;
                    font-size: 12px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: background 0.2s;
                }
                .action-btn:hover { background: #475569; }
                .action-btn.primary { background: #10b981; color: #fff; }
                .action-btn.primary:hover { background: #059669; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="icon-badge"><?php echo $icon; ?></div>
                <div>
                    <h1><?php echo esc_html( $title ); ?></h1>
                    <span style="font-size:12px; color:#94a3b8;">Gerador flutuante conectado ao WordPress</span>
                </div>
            </div>

            <div class="field">
                <label>Prompt Visual</label>
                <textarea id="prompt" rows="3" placeholder="Descreva a imagem que deseja gerar..."><?php echo esc_textarea( $prompt ); ?></textarea>
            </div>

            <div class="field">
                <label>Estilo da Imagem</label>
                <select id="style">
                    <option value="photo" <?php selected( $style, 'photo' ); ?>>📷 Fotojornalístico / Realista</option>
                    <option value="cinematic" <?php selected( $style, 'cinematic' ); ?>>🎬 Cinematográfico</option>
                    <option value="illustration_3d" <?php selected( $style, 'illustration_3d' ); ?>>🎨 Ilustração 3D</option>
                    <option value="digital_art" <?php selected( $style, 'digital_art' ); ?>>🖌 Arte Digital</option>
                    <option value="vector" <?php selected( $style, 'vector' ); ?>>✏️ Vetor / Minimalista</option>
                    <option value="anime" <?php selected( $style, 'anime' ); ?>>⛩️ Anime / Manga</option>
                    <option value="vintage" <?php selected( $style, 'vintage' ); ?>>🎞️ Retrô / Vintage</option>
                </select>
            </div>

            <button id="btn-generate" class="btn">
                <span>✨ Gerar Imagem</span>
            </button>

            <div id="status" class="status"></div>

            <div id="preview" class="preview-card">
                <img id="img-result" src="" alt="Resultado">
                <div class="action-btns">
                    <button id="btn-featured" class="action-btn primary">📌 Definir como Capa</button>
                    <button id="btn-inline" class="action-btn">📝 Inserir no Texto</button>
                </div>
            </div>

            <script src="<?php echo esc_url( includes_url( 'js/jquery/jquery.min.js' ) ); ?>"></script>
            <script>
                var ajaxurl = <?php echo json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
                var nonce   = <?php echo json_encode( wp_create_nonce( 'wpaip_ajax_nonce' ) ); ?>;
                var provider = <?php echo json_encode( $provider ); ?>;
                var postId   = <?php echo json_encode( $post_id ); ?>;
                var currentAttachId = 0;
                var currentUrl = '';

                $('#btn-generate').on('click', function() {
                    var pText = $.trim($('#prompt').val());
                    var pStyle = $('#style').val();
                    if (!pText) { alert('Digite um prompt!'); return; }

                    $('#btn-generate').prop('disabled', true).text('⏳ Gerando Imagem...');
                    $('#status').attr('class', 'status info').text('Gerando imagem via ' + provider + '... Aguarde.').show();
                    $('#preview').hide();

                    $.post(ajaxurl, {
                        action: 'wpaip_generate_inline_image',
                        _ajax_nonce: nonce,
                        post_id: postId,
                        prompt: pText,
                        provider: provider,
                        style: pStyle
                    }).done(function(res) {
                        if (res.success) {
                            currentAttachId = res.data.attachment_id;
                            currentUrl = res.data.url;
                            $('#img-result').attr('src', currentUrl);
                            $('#preview').slideDown();
                            $('#status').attr('class', 'status info').text('Imagem gerada com sucesso! Escolha como utilizar abaixo.').show();
                        } else {
                            $('#status').attr('class', 'status error').text(res.data.message || 'Falha ao gerar imagem.').show();
                        }
                    }).fail(function() {
                        $('#status').attr('class', 'status error').text('Erro de conexão ao gerar imagem.').show();
                    }).always(function() {
                        $('#btn-generate').prop('disabled', false).html('<span>✨ Gerar Nova Imagem</span>');
                    });
                });

                $('#btn-featured').on('click', function() {
                    if (window.opener && window.opener.wpaipSetFeaturedFromPopup) {
                        window.opener.wpaipSetFeaturedFromPopup(currentAttachId, currentUrl);
                        window.close();
                    } else {
                        alert('Janela principal do WordPress não foi encontrada.');
                    }
                });

                $('#btn-inline').on('click', function() {
                    if (window.opener && window.opener.wpaipInsertInlineFromPopup) {
                        var html = '<img src="' + currentUrl + '" class="aligncenter size-large wp-image-' + currentAttachId + '" />';
                        window.opener.wpaipInsertInlineFromPopup(html);
                        window.close();
                    } else {
                        alert('Janela principal do WordPress não foi encontrada.');
                    }
                });
            </script>
        </body>
        </html>
        <?php
        exit;
    }
}
