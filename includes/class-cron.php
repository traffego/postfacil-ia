<?php
/**
 * Agendamento automático de posts via WP-Cron.
 * Cria posts completos (texto + imagem + publicação) de forma autônoma.
 */
defined( 'ABSPATH' ) || exit;

class WPAIP_Cron {

    const OPTION_SCHEDULES = 'wpaip_cron_schedules';
    const OPTION_LOGS      = 'wpaip_cron_logs';
    const HOOK_PREFIX      = 'wpaip_run_schedule_';
    const MAX_LOGS         = 50;

    public static function init(): void {
        add_action( 'admin_post_wpaip_save_schedule',   [ __CLASS__, 'handle_save_schedule'   ] );
        add_action( 'admin_post_wpaip_delete_schedule', [ __CLASS__, 'handle_delete_schedule' ] );
        add_action( 'admin_post_wpaip_toggle_schedule', [ __CLASS__, 'handle_toggle_schedule' ] );

        // Registra hooks dinâmicos para cada schedule salvo
        foreach ( self::get_schedules() as $schedule ) {
            $hook = self::HOOK_PREFIX . $schedule['id'];
            add_action( $hook, function() use ( $schedule ) {
                WPAIP_Cron::run_schedule( $schedule['id'] );
            } );
        }
    }

    // ── Ciclo de vida do plugin ───────────────────────────────────────────────

    public static function on_activate(): void {
        // Reagenda todos os schedules ativos ao reativar o plugin
        foreach ( self::get_schedules() as $schedule ) {
            if ( ! empty( $schedule['active'] ) ) {
                self::schedule_event( $schedule );
            }
        }
    }

    public static function on_deactivate(): void {
        foreach ( self::get_schedules() as $schedule ) {
            self::unschedule_event( $schedule['id'] );
        }
    }

    // ── CRUD de Schedules ─────────────────────────────────────────────────────

    public static function get_schedules(): array {
        return (array) get_option( self::OPTION_SCHEDULES, [] );
    }

    public static function get_schedule( string $id ): ?array {
        foreach ( self::get_schedules() as $s ) {
            if ( $s['id'] === $id ) {
                return $s;
            }
        }
        return null;
    }

    private static function save_schedules( array $schedules ): void {
        update_option( self::OPTION_SCHEDULES, $schedules );
    }

    public static function handle_save_schedule(): void {
        check_admin_referer( 'wpaip_save_schedule' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Sem permissão.' );
        }

        $id = sanitize_key( $_POST['schedule_id'] ?? '' ) ?: uniqid( 'wpaip_' );

        $schedule = [
            'id'           => $id,
            'label'        => sanitize_text_field( $_POST['label']        ?? 'Agendamento' ),
            'topic'        => sanitize_textarea_field( $_POST['topic']    ?? '' ),
            'category'     => (int) ( $_POST['category']                  ?? 0 ),
            'llm_provider' => sanitize_text_field( $_POST['llm_provider'] ?? 'openai' ),
            'llm_model'    => sanitize_text_field( $_POST['llm_model']    ?? 'gpt-4o' ),
            'img_provider' => sanitize_text_field( $_POST['img_provider'] ?? 'dalle3' ),
            'frequency'    => sanitize_text_field( $_POST['frequency']    ?? 'daily' ),
            'post_status'  => sanitize_text_field( $_POST['post_status']  ?? 'publish' ),
            'active'       => true,
            'created_at'   => current_time( 'mysql' ),
        ];

        // Salva/atualiza
        $schedules = self::get_schedules();
        $found     = false;
        foreach ( $schedules as &$s ) {
            if ( $s['id'] === $id ) {
                $schedule['created_at'] = $s['created_at']; // preserva data original
                $s    = $schedule;
                $found = true;
                break;
            }
        }
        if ( ! $found ) {
            $schedules[] = $schedule;
        }
        self::save_schedules( $schedules );

        // Reagenda evento WP-Cron
        self::unschedule_event( $id );
        self::schedule_event( $schedule );

        wp_redirect( admin_url( 'admin.php?page=' . WPAIP_SLUG . '-cron&saved=1' ) );
        exit;
    }

    public static function handle_delete_schedule(): void {
        check_admin_referer( 'wpaip_delete_schedule' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Sem permissão.' );
        }

        $id        = sanitize_key( $_POST['schedule_id'] ?? '' );
        $schedules = array_filter( self::get_schedules(), fn( $s ) => $s['id'] !== $id );
        self::save_schedules( array_values( $schedules ) );
        self::unschedule_event( $id );

        wp_redirect( admin_url( 'admin.php?page=' . WPAIP_SLUG . '-cron&deleted=1' ) );
        exit;
    }

    public static function handle_toggle_schedule(): void {
        check_admin_referer( 'wpaip_toggle_schedule' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Sem permissão.' );
        }

        $id        = sanitize_key( $_POST['schedule_id'] ?? '' );
        $schedules = self::get_schedules();

        foreach ( $schedules as &$s ) {
            if ( $s['id'] === $id ) {
                $s['active'] = ! $s['active'];
                if ( $s['active'] ) {
                    self::schedule_event( $s );
                } else {
                    self::unschedule_event( $id );
                }
                break;
            }
        }

        self::save_schedules( $schedules );
        wp_redirect( admin_url( 'admin.php?page=' . WPAIP_SLUG . '-cron' ) );
        exit;
    }

    // ── WP-Cron ───────────────────────────────────────────────────────────────

    private static function schedule_event( array $schedule ): void {
        $hook = self::HOOK_PREFIX . $schedule['id'];

        if ( ! wp_next_scheduled( $hook ) ) {
            wp_schedule_event( time(), $schedule['frequency'], $hook );
        }
    }

    private static function unschedule_event( string $id ): void {
        $hook      = self::HOOK_PREFIX . $id;
        $timestamp = wp_next_scheduled( $hook );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, $hook );
        }
    }

    // ── Execução do Schedule ──────────────────────────────────────────────────

    public static function run_schedule( string $id ): void {
        $schedule = self::get_schedule( $id );
        if ( ! $schedule || empty( $schedule['active'] ) ) {
            return;
        }

        $start = microtime( true );

        self::log( $id, 'info', 'Iniciando geração de post para schedule: ' . $schedule['label'] );

        try {
            // 1. Gera título
            $title_result = WPAIP_LLM::generate(
                'Crie um título criativo e otimizado para SEO sobre o tema: ' . $schedule['topic'] . '. Retorne apenas o título, sem aspas ou pontuação extra.',
                $schedule['llm_provider'],
                [ 'model' => $schedule['llm_model'], 'max_tokens' => 100 ]
            );

            if ( ! $title_result['success'] ) {
                throw new RuntimeException( 'Falha ao gerar título: ' . $title_result['message'] );
            }

            $title = wp_strip_all_tags( $title_result['text'] );
            $title = trim( $title, '"\'.' );
            self::log( $id, 'info', 'Título gerado: ' . $title );

            // 2. Gera conteúdo do post
            $content_result = WPAIP_LLM::generate(
                $title,
                $schedule['llm_provider'],
                [ 'model' => $schedule['llm_model'], 'max_tokens' => 2500 ]
            );

            if ( ! $content_result['success'] ) {
                throw new RuntimeException( 'Falha ao gerar conteúdo: ' . $content_result['message'] );
            }

            $content = $content_result['text'];
            self::log( $id, 'info', 'Conteúdo gerado: ' . strlen( $content ) . ' caracteres.' );

            // 3. Cria rascunho do post
            $post_id = wp_insert_post( [
                'post_title'    => $title,
                'post_content'  => $content,
                'post_status'   => 'draft',
                'post_category' => $schedule['category'] ? [ $schedule['category'] ] : [],
                'post_author'   => 1,
            ] );

            if ( is_wp_error( $post_id ) ) {
                throw new RuntimeException( 'Falha ao criar post: ' . $post_id->get_error_message() );
            }

            self::log( $id, 'info', 'Post criado com ID: ' . $post_id );

            // 4. Gera e seta imagem de capa
            $img_prompt = 'Imagem profissional para artigo de blog sobre: ' . $title . '. Estilo fotográfico moderno, sem texto.';
            $img_result = WPAIP_Image::generate( $img_prompt, $schedule['img_provider'] );

            if ( $img_result['success'] ) {
                $attachment_id = WPAIP_Media::upload_from_url(
                    $img_result['url'],
                    $post_id,
                    $title
                );

                if ( ! is_wp_error( $attachment_id ) ) {
                    set_post_thumbnail( $post_id, $attachment_id );
                    self::log( $id, 'info', 'Imagem de capa definida (attachment #' . $attachment_id . ').' );
                } else {
                    self::log( $id, 'warn', 'Falha no upload da imagem: ' . $attachment_id->get_error_message() );
                }
            } else {
                self::log( $id, 'warn', 'Falha ao gerar imagem: ' . $img_result['message'] );
            }

            // 5. Publica o post
            wp_update_post( [
                'ID'          => $post_id,
                'post_status' => $schedule['post_status'],
            ] );

            $elapsed = round( microtime( true ) - $start, 2 );
            self::log( $id, 'success', "Post #{$post_id} publicado com sucesso em {$elapsed}s." );

        } catch ( Throwable $e ) {
            self::log( $id, 'error', $e->getMessage() );
        }
    }

    // ── Logs ──────────────────────────────────────────────────────────────────

    public static function get_logs(): array {
        return (array) get_option( self::OPTION_LOGS, [] );
    }

    private static function log( string $schedule_id, string $level, string $message ): void {
        $logs = self::get_logs();

        array_unshift( $logs, [
            'schedule_id' => $schedule_id,
            'level'       => $level,
            'message'     => $message,
            'time'        => current_time( 'mysql' ),
        ] );

        // Limita tamanho do log
        $logs = array_slice( $logs, 0, self::MAX_LOGS );

        update_option( self::OPTION_LOGS, $logs );
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public static function render_cron_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        require_once WPAIP_PLUGIN_DIR . 'admin/views/cron-page.php';
    }
}
