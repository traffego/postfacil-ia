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

        // Injeta contexto de data/hora atual para evitar anacronismos temporais na escrita do modelo
        $current_date = date_i18n( 'd \d\e F \d\e Y' );
        $current_time = date_i18n( 'H:i' );
        $temporal_instruction = "Data e hora de referência atual: {$current_date} às {$current_time}. Escreva o conteúdo ciente desta data atual e utilize os tempos verbais corretos (tempo passado para o que já aconteceu e futuro apenas para eventos posteriores a essa data).";
        $system = $temporal_instruction . "\n\n" . $system;

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

        // Pesquisa automática em segundo plano via Gemini (se habilitado)
        $is_search_enabled = WPAIP_Settings::get( 'enable_gemini_search', '0' ) === '1';
        if ( $is_search_enabled ) {
            $web_context = self::fetch_web_context_via_gemini( $prompt );
            if ( ! empty( $web_context ) ) {
                $ref_urls[]  = 'google-search-gemini';
                $ref_texts[] = $web_context;
            }
        }

        // Resgata o estilo jornalístico global configurado
        $journalistic_style = WPAIP_Settings::get( 'default_journalistic_style', 'default' );

        // Monta prompt baseado no modo e estilo jornalístico
        $final_prompt = self::build_prompt( $prompt, $mode, $ref_urls, $ref_texts, $paragraphs, $journalistic_style );

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
            if ( preg_match( '/<h1[^>]*>(.*?)<\/h1>/is', $text, $m ) ) {
                $title = wp_strip_all_tags( $m[1] );
                $text  = preg_replace( '/<h1[^>]*>.*?<\/h1>/is', '', $text, 1 );
                $text  = trim( $text );
            }
        }

        wp_send_json_success( [ 'text' => $text, 'title' => $title ] );
    }

    public static function ajax_improve_prompt(): void {
        WPAIP_Security::check_ajax( 'manage_options', 'settings' );

        $system_prompt = sanitize_textarea_field( $_POST['system_prompt'] ?? '' );
        $provider      = sanitize_text_field( $_POST['provider'] ?? '' );
        $model         = sanitize_text_field( $_POST['model'] ?? '' );

        if ( empty( $system_prompt ) ) {
            wp_send_json_error( [ 'message' => __( 'O prompt de sistema inicial está vazio.', 'wp-ai-publisher' ) ] );
        }

        $meta_prompt = "Melhore o seguinte prompt de sistema para redação de artigos de blog em português. "
                     . "Seu objetivo é enriquecer as instruções de comportamento da IA para que os artigos gerados sejam altamente envolventes, fluídos, bem estruturados e otimizados para SEO. "
                     . "Retorne EXCLUSIVAMENTE o texto puro do novo prompt de sistema melhorado (em português), sem explicações, sem aspas e sem blocos de código markdown (como ```):\n\n"
                     . $system_prompt;

        $options = [];
        if ( ! empty( $model ) ) {
            $options['model'] = $model;
        }
        $options['system'] = 'Você é um engenheiro de prompt de inteligência artificial de elite especialista em criação de conteúdo.';
        $options['max_tokens'] = 2000;

        $result = self::generate( $meta_prompt, $provider, $options );

        if ( ! $result['success'] ) {
            wp_send_json_error( [ 'message' => $result['message'] ] );
        }

        $improved_prompt = trim( $result['text'] );
        $improved_prompt = preg_replace( '/^```[a-zA-Z]*\s*/i', '', $improved_prompt );
        $improved_prompt = preg_replace( '/\s*```$/i', '', $improved_prompt );
        $improved_prompt = trim( $improved_prompt );

        wp_send_json_success( [ 'prompt' => $improved_prompt ] );
    }

    public static function fetch_web_context_via_gemini( string $user_prompt ): string {
        $api_key = WPAIP_Settings::get_api_key( 'gemini' );
        if ( empty( $api_key ) ) {
            return '';
        }

        $model = WPAIP_Settings::get( 'gemini_model', 'gemini-2.0-flash' );

        $search_prompt = "Pesquise na internet informações e fatos recentes, reais e atualizados sobre o seguinte tema: \"{$user_prompt}\". Resuma os pontos mais importantes, notícias reais, datas e acontecimentos levantados pela pesquisa em tópicos explicativos factuais para servir de base e referência fidedigna para a criação de um artigo. Retorne apenas os fatos consolidados encontrados na busca, sem introduções de IA, sem conclusões e sem blocos de código markdown (como ```).";

        $options = [
            'model'      => $model,
            'max_tokens' => 2000,
            'tools'      => [
                [ 'googleSearch' => [] ]
            ]
        ];

        $system = "Você é um pesquisador auxiliar de internet de alta precisão. Use a busca do Google para obter e reportar informações reais e consolidadas sobre o tema de forma direta, sem inventar fatos.";

        $result = self::call_gemini( $api_key, $search_prompt, $system, $options );

        if ( $result['success'] && ! empty( $result['text'] ) ) {
            return trim( $result['text'] );
        } else {
            error_log( 'WP AI Publisher — Falha na busca em tempo real via Gemini: ' . ( $result['message'] ?? 'Resposta vazia' ) );
        }

        return '';
    }

    /**
     * Mantém apenas os primeiros $max parágrafos <p> do HTML,
     * preservando headings (H2, H3) e listas que os acompanham.
     */
    private static function truncate_to_paragraphs( string $html, int $max ): string {
        if ( $max <= 0 ) return $html;

        // Divide o HTML em blocos de nível raiz usando DOMDocument
        $doc = new DOMDocument( '1.0', 'UTF-8' );
        libxml_use_internal_errors( true );
        $doc->loadHTML( '<?xml encoding="UTF-8"><div id="__wrap">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
        libxml_clear_errors();

        $wrap  = $doc->getElementById( '__wrap' );
        if ( ! $wrap ) return $html;

        $kept  = 0;
        $nodes = iterator_to_array( $wrap->childNodes );
        $keep  = [];

        foreach ( $nodes as $node ) {
            if ( $node->nodeType !== XML_ELEMENT_NODE ) {
                $keep[] = $node;
                continue;
            }
            $tag = strtolower( $node->nodeName );

            if ( $tag === 'p' ) {
                if ( $kept >= $max ) break; // para aqui
                $keep[] = $node;
                $kept++;
            } else {
                // headings e listas: inclui só se ainda não atingiu o limite
                if ( $kept < $max ) {
                    $keep[] = $node;
                }
            }
        }

        // Reconstrói o HTML só com os nós mantidos
        $out = '';
        foreach ( $keep as $node ) {
            $out .= $doc->saveHTML( $node );
        }

        return $out ?: $html;
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

    private static function build_prompt( string $input, string $mode, array $ref_urls = [], array $ref_texts = [], int $paragraphs = 5, string $journalistic_style = 'default' ): string {
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

        // Mapeia instruções de estilo jornalístico
        $style_instruction = '';
        switch ( $journalistic_style ) {
            case 'investigative':
                $style_instruction = 'Adote um estilo jornalístico investigativo: aprofunde-se nos fatos, traga detalhes minuciosos, evidencie contradições, organize o texto como se estivesse revelando segredos ou cruzando fontes de informação.';
                break;
            case 'editorial':
                $style_instruction = 'Adote um estilo jornalístico de opinião/editorial: apresente uma tese clara logo no início, argumente fortemente usando lógica e fatos, e assuma uma postura crítica ou analítica sobre o tema.';
                break;
            case 'interview':
                $style_instruction = 'Adote um formato jornalístico de entrevista: estruture partes do texto usando perguntas e respostas (Q&A) ou dê grande destaque a aspas e citações hipotéticas de especialistas sobre o tema.';
                break;
            case 'narrative':
                $style_instruction = 'Adote um estilo de crônica ou jornalismo literário (narrativo): use storytelling, tom pessoal, linguagem fluida, descrições ricas de cenas/contextos e reflexões profundas.';
                break;
            case 'sensationalist':
                $style_instruction = 'Adote um estilo de tabloide/sensacionalista: use ganchos de curiosidade dramáticos, chamadas instigantes, termos fortes e focados na emoção e impacto imediato do leitor.';
                break;
            case 'ugauga':
                $style_instruction = 'Adote um tom humorístico e místico primitivo: você DEVE obrigatoriamente inserir a palavra literal "UGA-UGA" (em caixa alta) no meio do texto diversas vezes (pelo menos 5 vezes ao longo do texto), de forma visível e destacada em diferentes parágrafos.';
                break;
            case 'default':
            default:
                $style_instruction = 'Adote um estilo jornalístico informativo clássico: seja imparcial, direto, comece com os fatos principais e explique-os de forma simples e direta.';
                break;
        }

        switch ( $mode ) {
            case 'expand':
                return "Expanda o seguinte conteúdo em um artigo de blog completo e bem estruturado. "
                     . "ESTILO DE ESCRITA: {$style_instruction} "
                     . "Desenvolva cada ponto com profundidade, use linguagem envolvente e acessível, "
                     . "adicione exemplos práticos quando útil. "
                     . "O artigo deve ter exatamente {$paragraphs} parágrafos de conteúdo (além dos sub-títulos). "
                     . "Retorne apenas o HTML do artigo (use H2 e H3 para sub-títulos, parágrafos, listas quando pertinente), sem comentários extra:{$ref_block}\n\n{$input}";
            case 'summarize':
                return "Resuma o seguinte texto de forma clara e concisa. Retorne apenas o resumo:{$ref_block}\n\n{$input}";
            case 'draft':
            default:
                // Mapeia contagem para descritor semântico de tamanho
                if ( $paragraphs <= 1 ) {
                    $size_desc = 'curtíssimo: apenas 1 parágrafo de introdução, direto ao ponto, sem seções ou subtítulos';
                } elseif ( $paragraphs <= 2 ) {
                    $size_desc = 'curto: introdução e conclusão, 2 parágrafos no total, sem subtítulos';
                } elseif ( $paragraphs <= 4 ) {
                    $size_desc = "médio: {$paragraphs} parágrafos bem desenvolvidos, 1 ou 2 subtítulos H2";
                } elseif ( $paragraphs <= 7 ) {
                    $size_desc = "completo: {$paragraphs} parágrafos, estrutura clara com 2-3 seções H2, introdução e conclusão";
                } else {
                    $size_desc = "longo e detalhado: aproximadamente {$paragraphs} parágrafos, múltiplas seções H2 e H3, exemplos práticos, introdução e conclusão robusta";
                }

                return "Crie um artigo de blog sobre o tema abaixo. "
                     . "ESTILO DE ESCRITA OBRIGATÓRIO: {$style_instruction} "
                     . "TAMANHO OBRIGATÓRIO: {$size_desc}. "
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
        $url   = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}";

        // Combina instrução de sistema com o prompt para evitar problemas de compatibilidade de campos no JSON do v1
        $combined_prompt = "Instruções do Sistema:\n{$system}\n\nTarefa:\n{$prompt}";

        $body_data = [
            'contents'           => [ [ 'parts' => [ [ 'text' => $combined_prompt ] ] ] ],
            'generationConfig'   => [ 'maxOutputTokens' => $opts['max_tokens'] ?? 8000 ],
        ];

        if ( ! empty( $opts['tools'] ) ) {
            $body_data['tools'] = $opts['tools'];
        }

        $body = wp_json_encode( $body_data );

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
