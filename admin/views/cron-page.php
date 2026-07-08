<?php defined( 'ABSPATH' ) || exit;
$schedules  = WPAIP_Cron::get_schedules();
$logs       = WPAIP_Cron::get_logs();
$saved      = isset( $_GET['saved'] );
$deleted    = isset( $_GET['deleted'] );
$categories = get_categories( [ 'hide_empty' => false ] );

$frequencies = [
    'hourly'     => __( 'A cada hora',    'wp-ai-publisher' ),
    'twicedaily' => __( '2x por dia',     'wp-ai-publisher' ),
    'daily'      => __( 'Diariamente',    'wp-ai-publisher' ),
    'weekly'     => __( 'Semanalmente',   'wp-ai-publisher' ),
];
?>
<div class="wrap wpaip-wrap">

    <h1 class="wpaip-page-title">
        <span class="dashicons dashicons-clock"></span>
        <?php _e( 'AI Publisher — Agendamentos', 'wp-ai-publisher' ); ?>
    </h1>

    <?php if ( $saved )   : ?><div class="notice notice-success is-dismissible"><p>✓ Agendamento salvo.</p></div><?php endif; ?>
    <?php if ( $deleted ) : ?><div class="notice notice-info is-dismissible"><p>Agendamento removido.</p></div><?php endif; ?>

    <div class="wpaip-cron-layout">

        <!-- ── Formulário novo agendamento ── -->
        <div class="wpaip-card wpaip-cron-form">
            <div class="wpaip-card-header">
                <h2><?php _e( 'Novo Agendamento', 'wp-ai-publisher' ); ?></h2>
            </div>
            <div class="wpaip-card-body">
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'wpaip_save_schedule' ); ?>
                    <input type="hidden" name="action" value="wpaip_save_schedule">

                    <div class="wpaip-field">
                        <label><?php _e( 'Nome do agendamento', 'wp-ai-publisher' ); ?></label>
                        <input type="text" name="label" class="wpaip-input" placeholder="Ex: Posts de Marketing" required>
                    </div>

                    <div class="wpaip-field">
                        <label><?php _e( 'Tema / Tópico base', 'wp-ai-publisher' ); ?></label>
                        <textarea name="topic" class="wpaip-input wpaip-textarea" rows="3"
                            placeholder="<?php esc_attr_e( 'Ex: Dicas de marketing digital para pequenas empresas', 'wp-ai-publisher' ); ?>" required></textarea>
                        <span class="wpaip-field-hint"><?php _e( 'O modelo vai criar títulos e conteúdos variados sobre este tema.', 'wp-ai-publisher' ); ?></span>
                    </div>

                    <div class="wpaip-grid-2">
                        <div class="wpaip-field">
                            <label><?php _e( 'Frequência', 'wp-ai-publisher' ); ?></label>
                            <select name="frequency" class="wpaip-select">
                                <?php foreach ( $frequencies as $val => $label ) : ?>
                                    <option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="wpaip-field">
                            <label><?php _e( 'Categoria', 'wp-ai-publisher' ); ?></label>
                            <select name="category" class="wpaip-select">
                                <option value="0"><?php _e( '— Sem categoria —', 'wp-ai-publisher' ); ?></option>
                                <?php foreach ( $categories as $cat ) : ?>
                                    <option value="<?php echo esc_attr( $cat->term_id ); ?>"><?php echo esc_html( $cat->name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="wpaip-field">
                            <label><?php _e( 'Modelo de Texto', 'wp-ai-publisher' ); ?></label>
                            <select name="llm_provider" class="wpaip-select">
                                <option value="openai">GPT (OpenAI)</option>
                                <option value="gemini">Gemini (Google)</option>
                                <option value="anthropic">Claude (Anthropic)</option>
                                <option value="deepseek">DeepSeek</option>
                            </select>
                        </div>

                        <div class="wpaip-field">
                            <label><?php _e( 'Gerador de Imagem', 'wp-ai-publisher' ); ?></label>
                            <select name="img_provider" class="wpaip-select">
                                <option value="pollinations">Pollinations AI (Grátis)</option>
                                <option value="dalle3">DALL-E 3 (OpenAI)</option>
                                <option value="gemini">Imagen 4 (Gemini)</option>
                                <option value="huggingface">Hugging Face</option>
                            </select>
                        </div>

                        <div class="wpaip-field">
                            <label><?php _e( 'Status do post gerado', 'wp-ai-publisher' ); ?></label>
                            <select name="post_status" class="wpaip-select">
                                <option value="publish"><?php _e( 'Publicar automaticamente', 'wp-ai-publisher' ); ?></option>
                                <option value="draft"><?php _e( 'Salvar como rascunho', 'wp-ai-publisher' ); ?></option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="button button-primary wpaip-btn-submit">
                        <?php _e( '+ Criar Agendamento', 'wp-ai-publisher' ); ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- ── Lista de agendamentos ── -->
        <div>
            <div class="wpaip-card">
                <div class="wpaip-card-header">
                    <h2><?php _e( 'Agendamentos Ativos', 'wp-ai-publisher' ); ?></h2>
                </div>
                <div class="wpaip-card-body" style="padding: 0;">

                    <?php if ( empty( $schedules ) ) : ?>
                        <p style="padding: 1.25rem; color: #888;"><?php _e( 'Nenhum agendamento criado ainda.', 'wp-ai-publisher' ); ?></p>
                    <?php else : ?>
                        <table class="wp-list-table widefat fixed striped wpaip-table">
                            <thead>
                                <tr>
                                    <th><?php _e( 'Nome', 'wp-ai-publisher' ); ?></th>
                                    <th><?php _e( 'Tema', 'wp-ai-publisher' ); ?></th>
                                    <th><?php _e( 'Frequência', 'wp-ai-publisher' ); ?></th>
                                    <th><?php _e( 'Status', 'wp-ai-publisher' ); ?></th>
                                    <th><?php _e( 'Ações', 'wp-ai-publisher' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $schedules as $s ) :
                                    $next = wp_next_scheduled( 'wpaip_run_schedule_' . $s['id'] );
                                ?>
                                <tr>
                                    <td><strong><?php echo esc_html( $s['label'] ); ?></strong></td>
                                    <td style="max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?php echo esc_html( $s['topic'] ); ?></td>
                                    <td><?php echo esc_html( $frequencies[ $s['frequency'] ] ?? $s['frequency'] ); ?></td>
                                    <td>
                                        <?php if ( $s['active'] ) : ?>
                                            <span class="wpaip-badge wpaip-badge--ok">✓ Ativo</span>
                                            <?php if ( $next ) : ?>
                                                <br><small style="color:#888;"><?php echo __( 'Próximo: ', 'wp-ai-publisher' ) . human_time_diff( time(), $next ); ?></small>
                                            <?php endif; ?>
                                        <?php else : ?>
                                            <span class="wpaip-badge wpaip-badge--empty">Pausado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <!-- Toggle -->
                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
                                            <?php wp_nonce_field( 'wpaip_toggle_schedule' ); ?>
                                            <input type="hidden" name="action" value="wpaip_toggle_schedule">
                                            <input type="hidden" name="schedule_id" value="<?php echo esc_attr( $s['id'] ); ?>">
                                            <button type="submit" class="button button-small">
                                                <?php echo $s['active'] ? __( 'Pausar', 'wp-ai-publisher' ) : __( 'Ativar', 'wp-ai-publisher' ); ?>
                                            </button>
                                        </form>
                                        <!-- Delete -->
                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;" onsubmit="return confirm('Remover este agendamento?')">
                                            <?php wp_nonce_field( 'wpaip_delete_schedule' ); ?>
                                            <input type="hidden" name="action" value="wpaip_delete_schedule">
                                            <input type="hidden" name="schedule_id" value="<?php echo esc_attr( $s['id'] ); ?>">
                                            <button type="submit" class="button button-small button-link-delete">
                                                <?php _e( 'Remover', 'wp-ai-publisher' ); ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                </div>
            </div>

            <!-- ── Log de execuções ── -->
            <?php if ( ! empty( $logs ) ) : ?>
            <div class="wpaip-card" style="margin-top: 1.5rem;">
                <div class="wpaip-card-header">
                    <h2><?php _e( 'Log de Execuções', 'wp-ai-publisher' ); ?></h2>
                </div>
                <div class="wpaip-card-body" style="padding: 0; max-height: 320px; overflow-y: auto;">
                    <table class="wp-list-table widefat fixed wpaip-table">
                        <thead>
                            <tr>
                                <th style="width:140px;"><?php _e( 'Data/Hora', 'wp-ai-publisher' ); ?></th>
                                <th style="width:80px;"><?php _e( 'Nível', 'wp-ai-publisher' ); ?></th>
                                <th><?php _e( 'Mensagem', 'wp-ai-publisher' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $logs as $log ) :
                                $level_colors = [
                                    'success' => '#34d399',
                                    'info'    => '#60a5fa',
                                    'warn'    => '#fbbf24',
                                    'error'   => '#f87171',
                                ];
                                $color = $level_colors[ $log['level'] ] ?? '#888';
                            ?>
                            <tr>
                                <td style="font-size:11px; color:#888;"><?php echo esc_html( $log['time'] ); ?></td>
                                <td><span style="color:<?php echo $color; ?>; font-weight:600; font-size:11px; text-transform:uppercase;"><?php echo esc_html( $log['level'] ); ?></span></td>
                                <td style="font-size:12px;"><?php echo esc_html( $log['message'] ); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- .wpaip-cron-layout -->
</div>
