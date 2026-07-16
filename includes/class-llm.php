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
        // Nota: o prompt já vem sanitizado pelo ajax_generate_text; não re-sanitizar
        // para evitar que wp_strip_all_tags destrua conteúdo de referências.

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
        add_action( 'wp_ajax_wpaip_generate_text',    [ __CLASS__, 'ajax_generate_text'    ] );
        add_action( 'wp_ajax_wpaip_fetch_references', [ __CLASS__, 'ajax_fetch_references' ] );
    }

    // ── AJAX: Fetch References ─────────────────────────────────────────────────

    public static function ajax_fetch_references(): void {
        WPAIP_Security::check_ajax( 'edit_posts' );

        $urls = isset( $_POST['urls'] ) && is_array( $_POST['urls'] )
            ? array_map( 'esc_url_raw', $_POST['urls'] )
            : [];

        if ( empty( $urls ) ) {
            wp_send_json_error( [ 'message' => 'Nenhuma URL enviada.' ] );
        }

        $results = [];
        foreach ( $urls as $url ) {
            if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
                continue;
            }

            // Método primário: strpos local (rápido, sem dependência externa)
            $text = self::fetch_url_local( $url );

            // Fallback: Jina AI Reader (para SPAs com JS pesado)
            if ( mb_strlen( $text ) < 200 ) {
                $text = self::fetch_url_jina( $url );
            }

            // Limita a 6000 chars por referência
            if ( mb_strlen( $text ) > 6000 ) {
                $text = mb_substr( $text, 0, 6000 ) . '…';
            }

            $results[] = [
                'url'     => $url,
                'success' => ! empty( $text ),
                'text'    => $text,
            ];
        }

        wp_send_json_success( [ 'references' => $results ] );
    }

    /**
     * Método primário: cURL com UA Chrome + strpos para extração do body.
     * Usa strpos em vez de regex para evitar pcre.backtrack_limit em HTMLs grandes (G1 tem 1.4 MB).
     * Velocidade: ~0.1s. Sem dependência externa.
     */
    private static function fetch_url_local( string $url ): string {
        if ( ! function_exists( 'curl_init' ) ) {
            return '';
        }

        $cookie_file = sys_get_temp_dir() . '/wpaip_ref_' . md5( $url ) . '.txt';

        $ch = curl_init( $url );
        curl_setopt_array( $ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING       => '', // aceita gzip/deflate automaticamente
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
            CURLOPT_REFERER        => 'https://www.google.com.br/',
            CURLOPT_COOKIEFILE     => $cookie_file,
            CURLOPT_COOKIEJAR      => $cookie_file,
            CURLOPT_HTTPHEADER     => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
                'Cache-Control: no-cache',
                'Pragma: no-cache',
            ],
        ] );

        $html = curl_exec( $ch );
        $code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        curl_close( $ch );

        if ( file_exists( $cookie_file ) ) {
            @unlink( $cookie_file );
        }

        if ( ! $html || $code !== 200 ) {
            return '';
        }

        // Remove scripts, estilos, SVG, nav, header, footer antes de qualquer extração
        $html = preg_replace( '/<(script|style|svg|noscript|nav|header|footer|aside)[^>]*>.*?<\/\1>/si', '', $html );

        // Usa strpos para extrair <body> — evita pcre.backtrack_limit em HTMLs de 1MB+
        $body_start = stripos( $html, '<body' );
        $text       = '';

        if ( $body_start !== false ) {
            $tag_end   = strpos( $html, '>', $body_start );
            $body_end  = strripos( $html, '</body>' );
            $body_html = ( $body_end !== false && $body_end > $tag_end )
                ? substr( $html, $tag_end + 1, $body_end - $tag_end - 1 )
                : substr( $html, $tag_end + 1 );

            $text = strip_tags( $body_html );
        } else {
            // Sem <body>: usa HTML completo
            $text = strip_tags( $html );
        }

        $text = preg_replace( '/\s{2,}/', ' ', $text );
        return trim( $text );
    }

    /**
     * Fallback: Jina AI Reader (r.jina.ai) — usado apenas quando fetch local retorna < 200 chars.
     * Útil para SPAs que renderizam conteúdo 100% via JavaScript.
     */
    private static function fetch_url_jina( string $url ): string {
        if ( ! function_exists( 'curl_init' ) ) {
            return '';
        }

        $ch = curl_init( 'https://r.jina.ai/' . $url );
        curl_setopt_array( $ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0',
            CURLOPT_HTTPHEADER     => [
                'Accept: text/plain',
                'X-Return-Format: text',
            ],
        ] );

        $text = curl_exec( $ch );
        $code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        curl_close( $ch );

        if ( ! $text || $code !== 200 ) {
            return '';
        }

        $text  = trim( $text );
        $lines = explode( "\n", $text );

        // Remove cabeçalho/menu duplicado do topo (Jina às vezes repete navegação)
        $start = 0;
        foreach ( $lines as $i => $line ) {
            if ( mb_strlen( trim( $line ) ) > 80 ) {
                $start = max( 0, $i - 3 );
                break;
            }
        }

        if ( $start > 0 ) {
            $text = implode( "\n", array_slice( $lines, $start ) );
        }

        return trim( $text );
    }

    public static function ajax_generate_text(): void {
        WPAIP_Security::check_ajax( 'edit_posts' );

        $prompt    = sanitize_textarea_field( $_POST['prompt']     ?? '' );
        $provider  = sanitize_text_field(     $_POST['provider']   ?? '' );
        $model     = sanitize_text_field(     $_POST['model']      ?? '' );
        $mode      = sanitize_text_field(     $_POST['mode']       ?? 'draft' );
        $paragraphs = max( 1, min( 50, (int) ( $_POST['paragraphs'] ?? 5 ) ) );

        // URLs sempre enviadas (mesmo quando fetch server-side falhou)
        $ref_urls  = isset( $_POST['ref_urls'] ) && is_array( $_POST['ref_urls'] )
            ? array_map( 'esc_url_raw', $_POST['ref_urls'] )
            : [];

        // Conteúdo extraído: não usar sanitize_textarea_field aqui pois
        // wp_strip_all_tags poderia destruir texto com < ou > do conteúdo web
        $ref_texts = isset( $_POST['ref_texts'] ) && is_array( $_POST['ref_texts'] )
            ? array_map( 'wp_kses_no_null', $_POST['ref_texts'] )
            : [];

        if ( empty( $prompt ) ) {
            wp_send_json_error( [ 'message' => 'Prompt vazio.' ] );
        }

        // Sanitiza apenas o input do usuário (não o prompt final)
        $prompt = WPAIP_Security::sanitize_prompt( $prompt );
        $prompt = mb_substr( $prompt, 0, 2000 ); // limita input do usuário

        // Monta prompt baseado no modo
        $final_prompt = self::build_prompt( $prompt, $mode, $ref_urls, $ref_texts, $paragraphs );

        // Só passa 'model' se vier preenchido; caso vazio, cada call_* usa o valor das configurações
        $options = [];
        if ( ! empty( $model ) ) {
            $options['model'] = $model;
        }
        $result = self::generate( $final_prompt, $provider, $options );

        if ( ! $result['success'] ) {
            wp_send_json_error( [ 'message' => $result['message'] ] );
        }

        // Modo draft: tenta extrair título do H1 gerado
        $title = '';
        $text  = $result['text'];

        if ( $mode === 'draft' ) {
            // Extrai e remove o primeiro H1 para usar como título do post
            if ( preg_match( '/<h1[^>]*>(.*?)<\/h1>/is', $text, $m ) ) {
                $title = wp_strip_all_tags( $m[1] );
                $text  = preg_replace( '/<h1[^>]*>.*?<\/h1>/is', '', $text, 1 );
                $text  = trim( $text );
            }
        }

        wp_send_json_success( [ 'text' => $text, 'title' => $title ] );
    }

    /**
     * Extrai title e content do JSON retornado pelo modelo no modo draft.
     * Tenta múltiplas estratégias para ser resiliente a respostas malformadas.
     *
     * @param string $raw   Resposta bruta do modelo.
     * @param string $title Título extraído (passado por referência).
     * @return string       HTML do conteúdo extraído (ou o texto bruto se tudo falhar).
     */
    private static function extract_draft_content( string $raw, string &$title ): string {
        // ── Passo 1: remove code fences  ```json ... ``` ────────────────────────
        $clean = trim( $raw );
        $clean = preg_replace( '/^```(?:json)?\s*/i', '', $clean );
        $clean = preg_replace( '/\s*```\s*$/i',       '', $clean );
        $clean = trim( $clean );

        // ── Passo 2: json_decode direto ─────────────────────────────────────────
        $decoded = json_decode( $clean, true );

        // ── Passo 3: busca o primeiro bloco { ... } caso haja texto extra ───────
        if ( ! is_array( $decoded ) ) {
            $start = strpos( $clean, '{' );
            $end   = strrpos( $clean, '}' );
            if ( $start !== false && $end !== false && $end > $start ) {
                $decoded = json_decode( substr( $clean, $start, $end - $start + 1 ), true );
            }
        }

        // ── Passo 4: json_decode funcionou → extrai campos ──────────────────────
        if ( is_array( $decoded ) && ! empty( $decoded['content'] ) ) {
            $title = sanitize_text_field( $decoded['title'] ?? '' );
            return $decoded['content'];
        }

        // ── Passo 5: regex como último recurso (JSON com newlines literais) ─────
        if ( preg_match( '/"content"\s*:\s*"((?:[^"\\\\]|\\\\.)*)/s', $clean, $m ) ) {
            if ( preg_match( '/"title"\s*:\s*"([^"\\\\]*)"/s', $clean, $mt ) ) {
                $title = sanitize_text_field( $mt[1] );
            }
            $content = json_decode( '"' . $m[1] . '"' );
            return $content ?: $m[1];
        }

        // ── Passo 6: retorna o texto bruto se tudo falhar ────────────────────────
        return $raw;
    }

    // ── Prompt builder ────────────────────────────────────────────────────────

    /**
     * @param string   $input     Tema / instrução do usuário.
     * @param string   $mode      draft | expand | summarize
     * @param string[] $ref_urls  Lista de URLs de referência (sempre presente).
     * @param string[] $ref_texts Conteúdo extraído de cada URL (pode estar vazio).
     */
    private static function build_prompt( string $input, string $mode, array $ref_urls = [], array $ref_texts = [], int $paragraphs = 5 ): string {
        // Monta bloco de contexto com referências
        $ref_block = '';
        if ( ! empty( $ref_urls ) ) {
            $ref_block = "\n\n---\nREFERÊNCIAS (use como base principal do conteúdo):\n";

            foreach ( $ref_urls as $i => $url ) {
                $n     = $i + 1;
                $text  = $ref_texts[ $i ] ?? '';

                $ref_block .= "\n[Referência {$n}] URL: {$url}\n";

                if ( ! empty( $text ) ) {
                    // Conteúdo extraído com sucesso
                    $ref_block .= "Conteúdo extraído desta página:\n{$text}\n";
                } else {
                    // Site bloqueou fetch server-side — pede ao modelo usar seu conhecimento
                    $ref_block .= "Não foi possível extrair o conteúdo desta página automaticamente. "
                               . "Use seu conhecimento sobre este URL e seu conteúdo para embasar o artigo.\n";
                }
            }

            $ref_block .= "---\n";
        }

        switch ( $mode ) {
            case 'expand':
                return "Expanda o seguinte conteúdo em um artigo de blog completo e bem estruturado. "
                     . "Desenvolva cada ponto com profundidade, use linguagem envolvente e acessível, "
                     . "adicione exemplos práticos quando útil. "
                     . "O artigo deve ter exatamente {$paragraphs} parágrafos de conteúdo (além dos sub-títulos). "
                     . "Retorne apenas o HTML do artigo (use H2 e H3 para sub-títulos, parágrafos, listas quando pertinente), sem comentários extra:{$ref_block}\n\n{$input}";
            case 'summarize':
                return "Resuma o seguinte texto de forma clara e concisa. Retorne apenas o resumo:{$ref_block}\n\n{$input}";
            case 'draft':
            default:
                $h2_count = max( 1, (int) ceil( $paragraphs / 2 ) );
                return "Crie um artigo de blog longo, completo e muito bem estruturado sobre o seguinte tema. "
                     . "O artigo deve ter: título em H1, introdução envolvente, exatamente {$h2_count} seções H2 "
                     . "(cada seção com pelo menos 2 parágrafos ricos), totalizando aproximadamente {$paragraphs} parágrafos no corpo do texto, e uma conclusão. "
                     . 'Use linguagem clara, acessível e otimizada para SEO. '
                     . 'Retorne SOMENTE o HTML do artigo (sem markdown, sem ```html, sem texto extra fora do HTML). '
                     . "Tema:\n\n{$input}{$ref_block}";
        }
    }

    // ── OpenAI ────────────────────────────────────────────────────────────────

    private static function call_openai( string $key, string $prompt, string $system, array $opts ): array {
        $model      = ( ! empty( $opts['model'] ) ) ? $opts['model'] : WPAIP_Settings::get( 'openai_model', 'gpt-4o' );
        $max_tokens = (int) ( $opts['max_tokens'] ?? 6000 );

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
        $model = ( ! empty( $opts['model'] ) ) ? $opts['model'] : WPAIP_Settings::get( 'gemini_model', 'gemini-2.0-flash' );
        $url   = "https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key={$key}";

        // Combina instrução de sistema com o prompt para evitar problemas de compatibilidade de campos no JSON do v1
        $combined_prompt = "Instruções do Sistema:\n{$system}\n\nTarefa:\n{$prompt}";

        $body = wp_json_encode( [
            'contents'           => [ [ 'parts' => [ [ 'text' => $combined_prompt ] ] ] ],
            'generationConfig'   => [ 'maxOutputTokens' => $opts['max_tokens'] ?? 8000 ],
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
        $model      = ( ! empty( $opts['model'] ) ) ? $opts['model'] : WPAIP_Settings::get( 'anthropic_model', 'claude-sonnet-4-5' );
        $max_tokens = (int) ( $opts['max_tokens'] ?? 6000 );

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
        $model      = ( ! empty( $opts['model'] ) ) ? $opts['model'] : WPAIP_Settings::get( 'deepseek_model', 'deepseek-chat' );
        $max_tokens = (int) ( $opts['max_tokens'] ?? 6000 );

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
