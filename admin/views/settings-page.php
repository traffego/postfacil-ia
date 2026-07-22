<?php defined( 'ABSPATH' ) || exit;
$opts      = WPAIP_Settings::get_options();
$saved     = isset( $_GET['settings-updated'] );
$providers = [ 'openai', 'gemini', 'anthropic', 'deepseek' ];

// Contagem de chaves configuradas
$configured_keys_count = 0;
$all_providers_keys = [ 'openai', 'gemini', 'anthropic', 'deepseek', 'huggingface', 'poe' ];
foreach ( $all_providers_keys as $pk ) {
    if ( ! empty( WPAIP_Settings::get_api_key( $pk ) ) ) {
        $configured_keys_count++;
    }
}
?>
<div class="wrap wpaip-wrap">

    <h1 class="wpaip-page-title">
        <span class="dashicons dashicons-superhero"></span>
        <?php _e( 'AI Publisher — Configurações', 'wp-ai-publisher' ); ?>
    </h1>

    <?php if ( $saved ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e( 'Configurações salvas com sucesso.', 'wp-ai-publisher' ); ?></p>
        </div>
    <?php endif; ?>

    <?php
    $decrypted_lic_key = WPAIP_Security::decrypt( WPAIP_Settings::get( 'license_key', '' ) );
    $masked_key        = ! empty( $decrypted_lic_key ) ? substr( $decrypted_lic_key, 0, 10 ) . '••••-••••-' . substr( $decrypted_lic_key, -4 ) : 'Sem Licença Ativa';
    $buy_url           = 'https://olive-locust-173119.hostingersite.com/license-server-wp-post/checkout.php';
    $clean_domain      = preg_replace( '/^https?:\/\//i', '', get_site_url() );
    ?>

    <!-- ── Card de Status da Assinatura ── -->
    <div class="wpaip-card" style="border-top: 4px solid #10b981; background: #ffffff; margin-bottom: 24px;">
        <div class="wpaip-card-header" style="background: linear-gradient(135deg, rgba(16,185,129,0.06) 0%, rgba(124,58,237,0.04) 100%); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 20px 24px;">
            <div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span class="dashicons dashicons-shield-alt" style="font-size: 22px; color: #10b981; width:22px; height:22px;"></span>
                    <h2 style="font-size: 1.1rem; margin: 0; font-weight: 700; color: #1e1b4b;"><?php _e( 'Status da Assinatura & Licença', 'wp-ai-publisher' ); ?></h2>
                    <span style="background: rgba(16,185,129,0.15); color: #047857; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 100px; text-transform: uppercase; letter-spacing: 0.05em;">
                        ✓ Licença Ativa
                    </span>
                </div>
                <p class="wpaip-card-desc" style="margin-top: 4px; margin-bottom: 0; color: #64748b;">
                    <?php _e( 'Sua licença está validada e ativa neste domínio.', 'wp-ai-publisher' ); ?>
                </p>
            </div>
            <div>
                <a href="<?php echo esc_url( $buy_url ); ?>" target="_blank" class="button button-primary" style="background: linear-gradient(135deg, #7c3aed 0%, #ec4899 100%); border: none; font-weight: 700; padding: 8px 18px; height: auto; font-size: 13px; border-radius: 8px; box-shadow: 0 4px 12px rgba(124,58,237,0.3); color:#fff; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                    ⚡ Comprar licença para outros domínios
                </a>
            </div>
        </div>
        <div class="wpaip-card-body" style="padding: 20px 24px;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px;">
                    <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; margin-bottom: 4px;">Chave de Licença</div>
                    <div style="font-family: monospace; font-size: 13px; font-weight: 700; color: #334155; word-break: break-all;"><?php echo esc_html( $masked_key ); ?></div>
                </div>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px;">
                    <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; margin-bottom: 4px;">Domínio Autorizado</div>
                    <div style="font-size: 13px; font-weight: 700; color: #0f172a;"><?php echo esc_html( $clean_domain ); ?></div>
                </div>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px;">
                    <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; margin-bottom: 4px;">Status da Validação</div>
                    <div style="font-size: 13px; font-weight: 700; color: #10b981; display:flex; align-items:center; gap:6px;">
                        <span style="display:inline-block; width:8px; height:8px; background:#10b981; border-radius:50%;"></span>
                        Ativa e Válida
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── GRID DE CARDS COMPACTOS INTERATIVOS ── -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 28px;">

        <!-- Card 1: Chaves de API -->
        <div class="wpaip-compact-card" onclick="wpaipOpenSettingsModal('modal-api-keys')">
            <div class="wpaip-compact-icon" style="background: rgba(124,58,237,0.1); color: #7c3aed;">
                <span class="dashicons dashicons-admin-network"></span>
            </div>
            <div style="flex:1;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="font-size:14px; font-weight:700; margin:0; color:#0f172a;"><?php _e( 'Chaves de API', 'wp-ai-publisher' ); ?></h3>
                    <span class="wpaip-badge <?php echo $configured_keys_count > 0 ? 'wpaip-badge--ok' : 'wpaip-badge--empty'; ?>">
                        <?php printf( __( '%d de 6 ativas', 'wp-ai-publisher' ), $configured_keys_count ); ?>
                    </span>
                </div>
                <p style="font-size:12px; color:#64748b; margin:4px 0 0;">OpenAI, Gemini, Claude, DeepSeek, Poe, Hugging Face.</p>
                <div style="font-size:12px; font-weight:700; color:#7c3aed; margin-top:8px; display:flex; align-items:center; gap:4px;">
                    Configurar chaves <span class="dashicons dashicons-arrow-right-alt2" style="font-size:14px; width:14px; height:14px;"></span>
                </div>
            </div>
        </div>

        <!-- Card 2: Modelo de Texto -->
        <div class="wpaip-compact-card" onclick="wpaipOpenSettingsModal('modal-text-model')">
            <div class="wpaip-compact-icon" style="background: rgba(59,130,246,0.1); color: #3b82f6;">
                <span class="dashicons dashicons-editor-bold"></span>
            </div>
            <div style="flex:1;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="font-size:14px; font-weight:700; margin:0; color:#0f172a;"><?php _e( 'Modelo de Texto', 'wp-ai-publisher' ); ?></h3>
                    <span class="wpaip-badge wpaip-badge--ok" style="text-transform:uppercase;">
                        <?php echo esc_html( $opts['default_llm'] ?? 'OpenAI' ); ?>
                    </span>
                </div>
                <p style="font-size:12px; color:#64748b; margin:4px 0 0;">Modelo ativo: <strong><?php echo esc_html( $opts[ $opts['default_llm'] . '_model' ] ?? 'gpt-4o' ); ?></strong></p>
                <div style="font-size:12px; font-weight:700; color:#3b82f6; margin-top:8px; display:flex; align-items:center; gap:4px;">
                    Alterar modelo de texto <span class="dashicons dashicons-arrow-right-alt2" style="font-size:14px; width:14px; height:14px;"></span>
                </div>
            </div>
        </div>

        <!-- Card 3: Modelo de Imagem -->
        <div class="wpaip-compact-card" onclick="wpaipOpenSettingsModal('modal-image-model')">
            <div class="wpaip-compact-icon" style="background: rgba(236,72,153,0.1); color: #ec4899;">
                <span class="dashicons dashicons-format-image"></span>
            </div>
            <div style="flex:1;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="font-size:14px; font-weight:700; margin:0; color:#0f172a;"><?php _e( 'Modelo de Imagem', 'wp-ai-publisher' ); ?></h3>
                    <span class="wpaip-badge wpaip-badge--ok" style="text-transform:uppercase;">
                        <?php echo esc_html( $opts['default_image'] ?? 'Pollinations' ); ?>
                    </span>
                </div>
                <p style="font-size:12px; color:#64748b; margin:4px 0 0;">Gerador: Pollinations, DALL-E 3, Imagen, FLUX, Poe.</p>
                <div style="font-size:12px; font-weight:700; color:#ec4899; margin-top:8px; display:flex; align-items:center; gap:4px;">
                    Configurar gerador de imagem <span class="dashicons dashicons-arrow-right-alt2" style="font-size:14px; width:14px; height:14px;"></span>
                </div>
            </div>
        </div>

        <!-- Card 4: Prompt de Sistema & Estilo -->
        <div class="wpaip-compact-card" onclick="wpaipOpenSettingsModal('modal-system-prompt')">
            <div class="wpaip-compact-icon" style="background: rgba(16,185,129,0.1); color: #10b981;">
                <span class="dashicons dashicons-admin-settings"></span>
            </div>
            <div style="flex:1;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="font-size:14px; font-weight:700; margin:0; color:#0f172a;"><?php _e( 'Prompt de Sistema & Estilo', 'wp-ai-publisher' ); ?></h3>
                    <span class="wpaip-badge wpaip-badge--ok">
                        <?php echo esc_html( ucfirst( $opts['default_journalistic_style'] ?? 'Informativo' ) ); ?>
                    </span>
                </div>
                <p style="font-size:12px; color:#64748b; margin:4px 0 0;">Instruções de tom, idioma e diretrizes de SEO.</p>
                <div style="font-size:12px; font-weight:700; color:#10b981; margin-top:8px; display:flex; align-items:center; gap:4px;">
                    Editar prompt e estilo <span class="dashicons dashicons-arrow-right-alt2" style="font-size:14px; width:14px; height:14px;"></span>
                </div>
            </div>
        </div>

        <!-- Card 5: Tutorial & Ajuda -->
        <div class="wpaip-compact-card" onclick="wpaipOpenSettingsModal('modal-tutorial')">
            <div class="wpaip-compact-icon" style="background: rgba(245,158,11,0.1); color: #f59e0b;">
                <span class="dashicons dashicons-editor-help"></span>
            </div>
            <div style="flex:1;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="font-size:14px; font-weight:700; margin:0; color:#0f172a;"><?php _e( 'Tutorial de API Keys', 'wp-ai-publisher' ); ?></h3>
                    <span class="wpaip-badge" style="background:rgba(245,158,11,0.15); color:#d97706;">Guia Rápido</span>
                </div>
                <p style="font-size:12px; color:#64748b; margin:4px 0 0;">Instruções passo a passo para obter cada chave de API.</p>
                <div style="font-size:12px; font-weight:700; color:#f59e0b; margin-top:8px; display:flex; align-items:center; gap:4px;">
                    Ver instruções de ajuda <span class="dashicons dashicons-arrow-right-alt2" style="font-size:14px; width:14px; height:14px;"></span>
                </div>
            </div>
        </div>

    </div>

    <!-- ── FORMULÁRIO PRINCIPAL COM MODAIS DE DESIGN MODERNO ── -->
    <form method="post" action="options.php" id="wpaip-settings-main-form">
        <?php settings_fields( WPAIP_SLUG . '-settings-group' ); ?>

        <!-- ── MODAL 1: CHAVES DE API ── -->
        <div id="modal-api-keys" class="wpaip-modal-overlay">
            <div class="wpaip-modal-card">
                <button type="button" class="wpaip-modal-close" onclick="wpaipCloseSettingsModal('modal-api-keys')">×</button>
                
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
                    <span class="dashicons dashicons-admin-network" style="font-size:22px; color:#a78bfa;"></span>
                    <h2 style="font-size:18px; font-weight:700; margin:0; color:#f8fafc;"><?php _e( 'Chaves de API', 'wp-ai-publisher' ); ?></h2>
                </div>
                <p style="font-size:13px; color:#94a3b8; margin-bottom:20px; border-bottom:1px solid rgba(255,255,255,0.08); padding-bottom:12px;">
                    <?php _e( 'Credenciais armazenadas com criptografia AES-256. Nunca expostas em texto limpo.', 'wp-ai-publisher' ); ?>
                </p>

                <div class="wpaip-modal-scroll-area">
                    <?php
                    $providers_meta = [
                        'openai'      => [ 'label' => 'OpenAI',     'placeholder' => 'sk-...',       'icon' => '⚡' ],
                        'gemini'      => [ 'label' => 'Google Gemini', 'placeholder' => 'AIza...',    'icon' => '✨' ],
                        'anthropic'   => [ 'label' => 'Anthropic (Claude)',  'placeholder' => 'sk-ant-...',   'icon' => '🧠' ],
                        'deepseek'    => [ 'label' => 'DeepSeek',   'placeholder' => 'sk-...',       'icon' => '🔮' ],
                        'huggingface' => [ 'label' => 'Hugging Face (Imagens)', 'placeholder' => 'hf_...', 'icon' => '🤗' ],
                        'poe'         => [ 'label' => 'Poe.com',    'placeholder' => 'pb-...',       'icon' => '🤖' ],
                    ];

                    foreach ( $providers_meta as $key => $meta ) :
                        $has_key = ! empty( WPAIP_Settings::get_api_key( $key ) );
                    ?>
                    <div class="wpaip-modal-row">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span><?php echo $meta['icon']; ?></span>
                                <strong style="color:#f8fafc; font-size:13px;"><?php echo esc_html( $meta['label'] ); ?></strong>
                            </div>
                            <?php if ( $has_key ) : ?>
                                <span class="wpaip-badge-dark wpaip-badge-dark--ok"><?php _e( 'Configurada', 'wp-ai-publisher' ); ?></span>
                            <?php else : ?>
                                <span class="wpaip-badge-dark wpaip-badge-dark--empty"><?php _e( 'Pendente', 'wp-ai-publisher' ); ?></span>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex; gap:8px;">
                            <input
                                type="password"
                                id="wpaip-key-<?php echo esc_attr( $key ); ?>"
                                name="<?php echo esc_attr( WPAIP_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $key ); ?>_api_key]"
                                class="wpaip-dark-input"
                                style="flex:1;"
                                placeholder="<?php echo esc_attr( $has_key ? '••••••••••••••••' : $meta['placeholder'] ); ?>"
                                autocomplete="new-password"
                            >
                            <button type="button"
                                class="wpaip-dark-btn wpaip-test-btn"
                                data-provider="<?php echo esc_attr( $key ); ?>"
                                data-input="#wpaip-key-<?php echo esc_attr( $key ); ?>"
                            ><?php _e( 'Testar', 'wp-ai-publisher' ); ?></button>
                        </div>
                        <span class="wpaip-test-result" id="wpaip-result-<?php echo esc_attr( $key ); ?>" style="display:block; margin-top:6px; font-size:12px;"></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="wpaip-modal-footer">
                    <button type="button" class="wpaip-dark-btn-sec" onclick="wpaipCloseSettingsModal('modal-api-keys')"><?php _e( 'Fechar', 'wp-ai-publisher' ); ?></button>
                    <button type="submit" class="wpaip-dark-btn-pri"><?php _e( 'Salvar Configurações', 'wp-ai-publisher' ); ?></button>
                </div>
            </div>
        </div>

        <!-- ── MODAL 2: MODELO DE TEXTO ── -->
        <div id="modal-text-model" class="wpaip-modal-overlay">
            <div class="wpaip-modal-card">
                <button type="button" class="wpaip-modal-close" onclick="wpaipCloseSettingsModal('modal-text-model')">×</button>
                
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
                    <span class="dashicons dashicons-editor-bold" style="font-size:22px; color:#60a5fa;"></span>
                    <h2 style="font-size:18px; font-weight:700; margin:0; color:#f8fafc;"><?php _e( 'Modelo de Texto', 'wp-ai-publisher' ); ?></h2>
                </div>
                <p style="font-size:13px; color:#94a3b8; margin-bottom:20px; border-bottom:1px solid rgba(255,255,255,0.08); padding-bottom:12px;">
                    <?php _e( 'Provider e versão usados para gerar textos no editor de posts.', 'wp-ai-publisher' ); ?>
                </p>

                <div class="wpaip-modal-scroll-area">
                    <!-- Provider -->
                    <div style="margin-bottom:16px;">
                        <label for="wpaip-default-llm" style="font-weight:700; color:#c4b5fd; margin-bottom:6px; display:block; font-size:12px; text-transform:uppercase; letter-spacing:0.05em;"><?php _e( 'Provider Padrão', 'wp-ai-publisher' ); ?></label>
                        <select id="wpaip-default-llm" name="<?php echo WPAIP_Settings::OPTION_KEY; ?>[default_llm]" class="wpaip-dark-input" style="width:100%;">
                            <option value="openai"    <?php selected( $opts['default_llm'], 'openai'    ); ?>>GPT (OpenAI)</option>
                            <option value="gemini"    <?php selected( $opts['default_llm'], 'gemini'    ); ?>>Gemini (Google)</option>
                            <option value="anthropic" <?php selected( $opts['default_llm'], 'anthropic' ); ?>>Claude (Anthropic)</option>
                            <option value="deepseek"  <?php selected( $opts['default_llm'], 'deepseek'  ); ?>>DeepSeek</option>
                        </select>
                    </div>

                    <!-- Versão por provider -->
                    <?php
                    $model_opts = [
                        'openai_model'    => [ 'label' => 'OpenAI',  'provider' => 'openai',    'opts' => [ 'gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'o1-mini' ] ],
                        'gemini_model'    => [ 'label' => 'Gemini',  'provider' => 'gemini',    'opts' => [ 'gemini-3.5-flash', 'gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-2.0-flash' ] ],
                        'anthropic_model' => [ 'label' => 'Claude',  'provider' => 'anthropic', 'opts' => [ 'claude-sonnet-4-5', 'claude-opus-4-5', 'claude-haiku-3-5' ] ],
                        'deepseek_model'  => [ 'label' => 'DeepSeek','provider' => 'deepseek',  'opts' => [ 'deepseek-chat', 'deepseek-reasoner' ] ],
                    ];
                    foreach ( $model_opts as $field => $meta ) :
                        $is_active = ( $opts['default_llm'] === $meta['provider'] );
                    ?>
                    <div class="wpaip-model-group" data-provider="<?php echo esc_attr( $meta['provider'] ); ?>" style="<?php echo $is_active ? '' : 'display:none;'; ?> margin-bottom:16px;">
                        <label style="font-weight:700; color:#c4b5fd; margin-bottom:6px; display:block; font-size:12px; text-transform:uppercase; letter-spacing:0.05em;"><?php printf( __( 'Versão do Modelo (%s)', 'wp-ai-publisher' ), esc_html( $meta['label'] ) ); ?></label>
                        <select name="<?php echo WPAIP_Settings::OPTION_KEY; ?>[<?php echo esc_attr( $field ); ?>]" class="wpaip-dark-input" style="width:100%;">
                            <?php foreach ( $meta['opts'] as $m ) : ?>
                                <option value="<?php echo esc_attr( $m ); ?>" <?php selected( $opts[ $field ] ?? '', $m ); ?>>
                                    <?php echo esc_html( $m ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endforeach; ?>

                    <!-- Pesquisa em tempo real via Gemini -->
                    <div style="margin-top: 18px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); padding: 14px; border-radius: 12px;">
                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-weight:700; color:#f8fafc;">
                            <input
                                type="checkbox"
                                name="<?php echo WPAIP_Settings::OPTION_KEY; ?>[enable_gemini_search]"
                                value="1"
                                <?php checked( WPAIP_Settings::get( 'enable_gemini_search', '0' ), '1' ); ?>
                                style="width:18px; height:18px; accent-color:#7c3aed; cursor:pointer;"
                            >
                            <span><?php _e( 'Habilitar pesquisa em tempo real no Google', 'wp-ai-publisher' ); ?></span>
                        </label>
                        <span style="display:block; margin-top:6px; font-size:12px; color:#94a3b8; line-height:1.4;">
                            <?php _e( 'Pesquisa fatos e informações atualizadas na web antes de estruturar e redigir o artigo.', 'wp-ai-publisher' ); ?>
                        </span>
                    </div>
                </div>

                <div class="wpaip-modal-footer">
                    <button type="button" class="wpaip-dark-btn-sec" onclick="wpaipCloseSettingsModal('modal-text-model')"><?php _e( 'Fechar', 'wp-ai-publisher' ); ?></button>
                    <button type="submit" class="wpaip-dark-btn-pri"><?php _e( 'Salvar Configurações', 'wp-ai-publisher' ); ?></button>
                </div>
            </div>
        </div>

        <!-- ── MODAL 3: MODELO DE IMAGEM ── -->
        <div id="modal-image-model" class="wpaip-modal-overlay">
            <div class="wpaip-modal-card">
                <button type="button" class="wpaip-modal-close" onclick="wpaipCloseSettingsModal('modal-image-model')">×</button>
                
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
                    <span class="dashicons dashicons-format-image" style="font-size:22px; color:#f472b6;"></span>
                    <h2 style="font-size:18px; font-weight:700; margin:0; color:#f8fafc;"><?php _e( 'Modelo de Imagem', 'wp-ai-publisher' ); ?></h2>
                </div>
                <p style="font-size:13px; color:#94a3b8; margin-bottom:20px; border-bottom:1px solid rgba(255,255,255,0.08); padding-bottom:12px;">
                    <?php _e( 'Provider e modelo usados para gerar imagens no editor de posts.', 'wp-ai-publisher' ); ?>
                </p>

                <div class="wpaip-modal-scroll-area">
                    <!-- Provider de imagem -->
                    <div style="margin-bottom:16px;">
                        <label for="wpaip-default-image" style="font-weight:700; color:#c4b5fd; margin-bottom:6px; display:block; font-size:12px; text-transform:uppercase; letter-spacing:0.05em;"><?php _e( 'Provider de Imagem', 'wp-ai-publisher' ); ?></label>
                        <select id="wpaip-default-image" name="<?php echo WPAIP_Settings::OPTION_KEY; ?>[default_image]" class="wpaip-dark-input" style="width:100%;">
                            <option value="pollinations" <?php selected( $opts['default_image'], 'pollinations' ); ?>>Pollinations AI (Grátis — Sem Chave)</option>
                            <option value="dalle3"       <?php selected( $opts['default_image'], 'dalle3'       ); ?>>DALL-E 3 (OpenAI)</option>
                            <option value="gemini"       <?php selected( $opts['default_image'], 'gemini'       ); ?>>Imagen 4 (Gemini)</option>
                            <option value="huggingface"  <?php selected( $opts['default_image'], 'huggingface'  ); ?>>Hugging Face (Grátis — Com Chave)</option>
                            <option value="poe"          <?php selected( $opts['default_image'], 'poe'          ); ?>>Poe.com (Com Chave)</option>
                        </select>
                    </div>

                    <!-- Modelo Hugging Face -->
                    <div id="wpaip-hf-model-wrapper" style="<?php echo ( $opts['default_image'] === 'huggingface' ) ? '' : 'display:none;'; ?> margin-bottom:16px;">
                        <label for="wpaip-hf-model" style="font-weight:700; color:#c4b5fd; margin-bottom:6px; display:block; font-size:12px; text-transform:uppercase; letter-spacing:0.05em;"><?php _e( 'Modelo Hugging Face', 'wp-ai-publisher' ); ?></label>
                        <div style="display:flex; gap:8px; align-items:center;">
                            <select
                                id="wpaip-hf-model"
                                name="<?php echo WPAIP_Settings::OPTION_KEY; ?>[huggingface_image_model]"
                                class="wpaip-dark-input"
                                style="flex:1;"
                            >
                                <?php
                                $saved_hf    = $opts['huggingface_image_model'] ?? 'black-forest-labs/FLUX.1-schnell';
                                $defaults_hf = [
                                    'black-forest-labs/FLUX.1-schnell'         => 'FLUX.1-schnell (rápido/grátis)',
                                    'black-forest-labs/FLUX.1-dev'             => 'FLUX.1-dev (melhor qualidade)',
                                    'stabilityai/stable-diffusion-xl-base-1.0' => 'Stable Diffusion XL',
                                    'stabilityai/sdxl-turbo'                   => 'SDXL-Turbo (muito rápido)',
                                    'runwayml/stable-diffusion-v1-5'           => 'Stable Diffusion v1.5',
                                ];
                                foreach ( $defaults_hf as $val => $label ) :
                                ?>
                                    <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $saved_hf, $val ); ?>>
                                        <?php echo esc_html( $label ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" id="wpaip-hf-load-models" class="wpaip-dark-btn">Carregar API</button>
                        </div>
                    </div>

                    <!-- Modelo Poe -->
                    <div id="wpaip-poe-model-wrapper" style="<?php echo ( $opts['default_image'] === 'poe' ) ? '' : 'display:none;'; ?> margin-bottom:16px;">
                        <label for="wpaip-poe-bot-select" style="font-weight:700; color:#c4b5fd; margin-bottom:6px; display:block; font-size:12px; text-transform:uppercase; letter-spacing:0.05em;"><?php _e( 'Bot de Imagem do Poe.com', 'wp-ai-publisher' ); ?></label>
                        <div style="display:flex; gap:8px; align-items:center; margin-bottom:8px;">
                            <select id="wpaip-poe-bot-select" class="wpaip-dark-input" style="flex:1;">
                                <?php
                                $saved_poe = $opts['poe_image_bot'] ?? 'FLUX-schnell';
                                $defaults_poe = [
                                    'FLUX-schnell'      => 'FLUX.1-schnell (Custo baixo)',
                                    'StableDiffusionXL' => 'Stable Diffusion XL (Custo baixíssimo)',
                                    'Playground-v2.5'   => 'Playground v2.5 (Cores vibrantes)',
                                    'DALL-E-3'          => 'DALL-E 3 (Prompts complexos)',
                                    'FLUX-pro'          => 'FLUX.1-pro (Qualidade máxima)',
                                    'FLUX-dev'          => 'FLUX.1-dev (Fotorealismo)',
                                    'Imagen-3'          => 'Imagen 3 (Google)',
                                ];
                                $is_custom = ! array_key_exists( $saved_poe, $defaults_poe );
                                foreach ( $defaults_poe as $val => $label ) :
                                ?>
                                    <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $saved_poe, $val ); ?>>
                                        <?php echo esc_html( $label ); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="custom" <?php selected( $is_custom, true ); ?>><?php _e( 'Outro bot (Digitar handle)...', 'wp-ai-publisher' ); ?></option>
                            </select>
                            <button type="button" id="wpaip-poe-load-models" class="wpaip-dark-btn">Carregar API</button>
                        </div>
                        <input
                            type="text"
                            id="wpaip-poe-bot"
                            name="<?php echo WPAIP_Settings::OPTION_KEY; ?>[poe_image_bot]"
                            class="wpaip-dark-input"
                            value="<?php echo esc_attr( $saved_poe ); ?>"
                            placeholder="Ex: FLUX-schnell"
                            style="<?php echo $is_custom ? 'display:block;' : 'display:none;'; ?> width:100%;"
                        >
                    </div>
                </div>

                <div class="wpaip-modal-footer">
                    <button type="button" class="wpaip-dark-btn-sec" onclick="wpaipCloseSettingsModal('modal-image-model')"><?php _e( 'Fechar', 'wp-ai-publisher' ); ?></button>
                    <button type="submit" class="wpaip-dark-btn-pri"><?php _e( 'Salvar Configurações', 'wp-ai-publisher' ); ?></button>
                </div>
            </div>
        </div>

        <!-- ── MODAL 4: PROMPT DE SISTEMA & ESTILO ── -->
        <div id="modal-system-prompt" class="wpaip-modal-overlay">
            <div class="wpaip-modal-card">
                <button type="button" class="wpaip-modal-close" onclick="wpaipCloseSettingsModal('modal-system-prompt')">×</button>
                
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
                    <span class="dashicons dashicons-admin-settings" style="font-size:22px; color:#34d399;"></span>
                    <h2 style="font-size:18px; font-weight:700; margin:0; color:#f8fafc;"><?php _e( 'Prompt de Sistema Global', 'wp-ai-publisher' ); ?></h2>
                </div>
                <p style="font-size:13px; color:#94a3b8; margin-bottom:20px; border-bottom:1px solid rgba(255,255,255,0.08); padding-bottom:12px;">
                    <?php _e( 'Instrução base enviada a todos os modelos de inteligência artificial.', 'wp-ai-publisher' ); ?>
                </p>

                <div class="wpaip-modal-scroll-area">
                    <div style="margin-bottom:16px;">
                        <label for="wpaip-system-prompt" style="font-weight:700; color:#c4b5fd; margin-bottom:6px; display:block; font-size:12px; text-transform:uppercase; letter-spacing:0.05em;"><?php _e( 'Prompt de Sistema', 'wp-ai-publisher' ); ?></label>
                        <textarea
                            id="wpaip-system-prompt"
                            name="<?php echo WPAIP_Settings::OPTION_KEY; ?>[system_prompt]"
                            class="wpaip-dark-input"
                            rows="5"
                            style="width:100%; font-size:13px; line-height:1.5;"
                        ><?php echo esc_textarea( $opts['system_prompt'] ?? '' ); ?></textarea>
                        <div style="margin-top: 10px; display: flex; align-items: center; gap: 10px;">
                            <button type="button" id="wpaip-btn-improve-prompt" class="wpaip-dark-btn">
                                <?php _e( '✦ Melhorar Prompt via IA', 'wp-ai-publisher' ); ?>
                            </button>
                            <span id="wpaip-improve-prompt-status" style="font-size: 12px; color: #94a3b8; display: none;"></span>
                        </div>
                    </div>

                    <div style="margin-top: 18px;">
                        <label for="wpaip-default-journalistic-style" style="font-weight:700; color:#c4b5fd; margin-bottom:6px; display:block; font-size:12px; text-transform:uppercase; letter-spacing:0.05em;"><?php _e( 'Estilo Jornalístico Padrão', 'wp-ai-publisher' ); ?></label>
                        <select id="wpaip-default-journalistic-style" name="<?php echo WPAIP_Settings::OPTION_KEY; ?>[default_journalistic_style]" class="wpaip-dark-input" style="width:100%;">
                            <option value="default" <?php selected( $opts['default_journalistic_style'] ?? 'default', 'default' ); ?>><?php _e( 'Informativo / Padrão (Fatos diretos e linguagem neutra)', 'wp-ai-publisher' ); ?></option>
                            <option value="investigative" <?php selected( $opts['default_journalistic_style'] ?? 'default', 'investigative' ); ?>><?php _e( 'Investigativo (Profundo e analítico)', 'wp-ai-publisher' ); ?></option>
                            <option value="editorial" <?php selected( $opts['default_journalistic_style'] ?? 'default', 'editorial' ); ?>><?php _e( 'Opinativo / Editorial (Argumentativo e defensor de tese)', 'wp-ai-publisher' ); ?></option>
                            <option value="interview" <?php selected( $opts['default_journalistic_style'] ?? 'default', 'interview' ); ?>><?php _e( 'Entrevista (Perguntas/respostas ou citações)', 'wp-ai-publisher' ); ?></option>
                            <option value="narrative" <?php selected( $opts['default_journalistic_style'] ?? 'default', 'narrative' ); ?>><?php _e( 'Crônica / Narrativo (Storytelling e tom literário)', 'wp-ai-publisher' ); ?></option>
                            <option value="sensationalist" <?php selected( $opts['default_journalistic_style'] ?? 'default', 'sensationalist' ); ?>><?php _e( 'Sensacionalista / Tabloide (Dramático e apelativo)', 'wp-ai-publisher' ); ?></option>
                            <option value="ugauga" <?php selected( $opts['default_journalistic_style'] ?? 'default', 'ugauga' ); ?>><?php _e( 'UGA-UGA Teste (Inserção obrigatória do termo UGA-UGA)', 'wp-ai-publisher' ); ?></option>
                        </select>
                    </div>
                </div>

                <div class="wpaip-modal-footer">
                    <button type="button" class="wpaip-dark-btn-sec" onclick="wpaipCloseSettingsModal('modal-system-prompt')"><?php _e( 'Fechar', 'wp-ai-publisher' ); ?></button>
                    <button type="submit" class="wpaip-dark-btn-pri"><?php _e( 'Salvar Configurações', 'wp-ai-publisher' ); ?></button>
                </div>
            </div>
        </div>

        <!-- ── MODAL 5: TUTORIAL DE API KEYS ── -->
        <div id="modal-tutorial" class="wpaip-modal-overlay">
            <div class="wpaip-modal-card" style="max-width:640px;">
                <button type="button" class="wpaip-modal-close" onclick="wpaipCloseSettingsModal('modal-tutorial')">×</button>
                
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
                    <span class="dashicons dashicons-editor-help" style="font-size:22px; color:#fbbf24;"></span>
                    <h2 style="font-size:18px; font-weight:700; margin:0; color:#f8fafc;"><?php _e( 'Tutorial: Como Obter Suas Chaves de API', 'wp-ai-publisher' ); ?></h2>
                </div>
                <p style="font-size:13px; color:#94a3b8; margin-bottom:20px; border-bottom:1px solid rgba(255,255,255,0.08); padding-bottom:12px;">
                    <?php _e( 'Instruções para cadastrar e obter chaves em cada provedor.', 'wp-ai-publisher' ); ?>
                </p>

                <div class="wpaip-modal-scroll-area">
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:12px;">
                        <div style="padding:14px; border:1px solid rgba(255,255,255,0.08); border-radius:12px; background:rgba(255,255,255,0.03);">
                            <h4 style="margin:0 0 6px; font-size:13px; color:#c4b5fd;">OpenAI (GPT & DALL-E)</h4>
                            <p style="font-size:12px; line-height:1.5; color:#cbd5e1; margin:0;">
                                1. Acesse <a href="https://platform.openai.com/" target="_blank" style="color:#a78bfa;">platform.openai.com</a>.<br>
                                2. Vá em <strong>API Keys > Create secret key</strong>.<br>
                                3. Requer créditos em <strong>Settings > Billing</strong>.
                            </p>
                        </div>
                        <div style="padding:14px; border:1px solid rgba(255,255,255,0.08); border-radius:12px; background:rgba(255,255,255,0.03);">
                            <h4 style="margin:0 0 6px; font-size:13px; color:#c4b5fd;">Google Gemini</h4>
                            <p style="font-size:12px; line-height:1.5; color:#cbd5e1; margin:0;">
                                1. Acesse <a href="https://aistudio.google.com/" target="_blank" style="color:#a78bfa;">aistudio.google.com</a>.<br>
                                2. Clique em <strong>Get API key > Create API Key</strong>.<br>
                                3. Cota gratuita para testes.
                            </p>
                        </div>
                        <div style="padding:14px; border:1px solid rgba(255,255,255,0.08); border-radius:12px; background:rgba(255,255,255,0.03);">
                            <h4 style="margin:0 0 6px; font-size:13px; color:#c4b5fd;">Anthropic (Claude)</h4>
                            <p style="font-size:12px; line-height:1.5; color:#cbd5e1; margin:0;">
                                1. Acesse <a href="https://console.anthropic.com/" target="_blank" style="color:#a78bfa;">console.anthropic.com</a>.<br>
                                2. Vá em <strong>API Keys > Create Key</strong>.<br>
                                3. Requer créditos em <strong>Billing</strong>.
                            </p>
                        </div>
                        <div style="padding:14px; border:1px solid rgba(255,255,255,0.08); border-radius:12px; background:rgba(255,255,255,0.03);">
                            <h4 style="margin:0 0 6px; font-size:13px; color:#c4b5fd;">DeepSeek</h4>
                            <p style="font-size:12px; line-height:1.5; color:#cbd5e1; margin:0;">
                                1. Acesse <a href="https://platform.deepseek.com/" target="_blank" style="color:#a78bfa;">platform.deepseek.com</a>.<br>
                                2. Vá até <strong>API Keys > Create API Key</strong>.
                            </p>
                        </div>
                        <div style="padding:14px; border:1px solid rgba(255,255,255,0.08); border-radius:12px; background:rgba(255,255,255,0.03);">
                            <h4 style="margin:0 0 6px; font-size:13px; color:#c4b5fd;">Hugging Face (Grátis)</h4>
                            <p style="font-size:12px; line-height:1.5; color:#cbd5e1; margin:0;">
                                1. Acesse <a href="https://huggingface.co/settings/tokens" target="_blank" style="color:#a78bfa;">huggingface.co/settings/tokens</a>.<br>
                                2. Crie um token com permissão <strong>Read</strong>.<br>
                                3. Permite gerar imagens grátis.
                            </p>
                        </div>
                        <div style="padding:14px; border:1px solid rgba(255,255,255,0.08); border-radius:12px; background:rgba(255,255,255,0.03);">
                            <h4 style="margin:0 0 6px; font-size:13px; color:#c4b5fd;">Poe.com</h4>
                            <p style="font-size:12px; line-height:1.5; color:#cbd5e1; margin:0;">
                                1. Acesse <a href="https://poe.com/api/keys" target="_blank" style="color:#a78bfa;">poe.com/api/keys</a>.<br>
                                2. Crie seu Token de API do Poe.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="wpaip-modal-footer">
                    <button type="button" class="wpaip-dark-btn-pri" onclick="wpaipCloseSettingsModal('modal-tutorial')"><?php _e( 'Entendido', 'wp-ai-publisher' ); ?></button>
                </div>
            </div>
        </div>

        <div style="margin-top:16px; display:flex; justify-content:flex-end; align-items:center;">
            <?php submit_button( __( 'Salvar Todas as Configurações', 'wp-ai-publisher' ), 'primary button-hero', 'submit', false, [ 'style' => 'background: linear-gradient(135deg, #7c3aed 0%, #ec4899 100%); border:none; font-weight:700; border-radius:10px; padding:10px 24px; box-shadow: 0 4px 20px rgba(124,58,237,0.4);' ] ); ?>
        </div>
    </form>
</div>

<style>
    /* Estilos dos Cards Compactos no Dashboard */
    .wpaip-compact-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 18px 16px;
        display: flex;
        align-items: flex-start;
        gap: 14px;
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
    }
    .wpaip-compact-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 24px rgba(124, 58, 237, 0.12);
        border-color: rgba(124, 58, 237, 0.4);
    }
    .wpaip-compact-icon {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        flex-shrink: 0;
    }

    /* Modais Ultra-Modernos Dark Glass */
    .wpaip-modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 12, 26, 0.78);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        z-index: 100000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.25s ease, visibility 0.25s ease;
    }
    .wpaip-modal-overlay.active {
        opacity: 1;
        visibility: visible;
    }
    .wpaip-modal-card {
        background: #161224;
        border: 1px solid rgba(124, 58, 237, 0.4);
        border-radius: 20px;
        max-width: 560px;
        width: 100%;
        max-height: 88vh;
        display: flex;
        flex-direction: column;
        padding: 28px 24px;
        position: relative;
        box-shadow: 0 25px 60px rgba(0, 0, 0, 0.7), inset 0 1px 0 rgba(255, 255, 255, 0.1);
        transform: scale(0.92) translateY(12px);
        transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
        color: #f8fafc;
    }
    .wpaip-modal-overlay.active .wpaip-modal-card {
        transform: scale(1) translateY(0);
    }
    .wpaip-modal-close {
        position: absolute;
        top: 18px;
        right: 20px;
        background: rgba(255, 255, 255, 0.06);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: rgba(248, 250, 252, 0.6);
        width: 32px;
        height: 32px;
        border-radius: 50%;
        font-size: 18px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }
    .wpaip-modal-close:hover {
        background: rgba(239, 68, 68, 0.25);
        color: #ef4444;
        border-color: rgba(239, 68, 68, 0.4);
    }
    .wpaip-modal-scroll-area {
        overflow-y: auto;
        max-height: 60vh;
        padding-right: 6px;
    }

    /* Elementos Internos dos Modais */
    .wpaip-modal-row {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 12px;
        padding: 14px;
        margin-bottom: 12px;
    }
    .wpaip-dark-input {
        background: #1f1a33 !important;
        border: 1px solid rgba(124, 58, 237, 0.35) !important;
        border-radius: 8px !important;
        color: #ffffff !important;
        padding: 10px 14px !important;
        font-size: 13px !important;
        outline: none !important;
        transition: border-color 0.2s, box-shadow 0.2s !important;
    }
    .wpaip-dark-input:focus {
        border-color: #7c3aed !important;
        box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.3) !important;
    }
    .wpaip-dark-btn {
        background: linear-gradient(135deg, #7c3aed 0%, #9333ea 100%) !important;
        color: #ffffff !important;
        border: none !important;
        font-weight: 700 !important;
        border-radius: 8px !important;
        padding: 8px 16px !important;
        cursor: pointer !important;
        font-size: 13px !important;
        transition: opacity 0.2s !important;
    }
    .wpaip-dark-btn:hover {
        opacity: 0.9 !important;
    }
    .wpaip-dark-btn-pri {
        background: linear-gradient(135deg, #7c3aed 0%, #ec4899 100%);
        color: #ffffff;
        border: none;
        font-weight: 700;
        border-radius: 10px;
        padding: 10px 20px;
        cursor: pointer;
        font-size: 13px;
        box-shadow: 0 4px 15px rgba(124, 58, 237, 0.4);
    }
    .wpaip-dark-btn-sec {
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.12);
        color: #94a3b8;
        font-weight: 600;
        border-radius: 10px;
        padding: 10px 18px;
        cursor: pointer;
        font-size: 13px;
    }
    .wpaip-dark-btn-sec:hover {
        color: #fff;
        background: rgba(255, 255, 255, 0.14);
    }
    .wpaip-modal-footer {
        margin-top: 20px;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
        padding-top: 16px;
    }

    /* Badges nos Modais */
    .wpaip-badge-dark {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        padding: 3px 8px;
        border-radius: 6px;
        letter-spacing: 0.04em;
    }
    .wpaip-badge-dark--ok {
        background: rgba(16, 185, 129, 0.2);
        color: #34d399;
        border: 1px solid rgba(16, 185, 129, 0.4);
    }
    .wpaip-badge-dark--empty {
        background: rgba(245, 158, 11, 0.2);
        color: #fbbf24;
        border: 1px solid rgba(245, 158, 11, 0.4);
    }
</style>

<script>
    function wpaipOpenSettingsModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
        }
    }

    function wpaipCloseSettingsModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
        }
    }

    // Fechar modais ao clicar no fundo escuro ou ao pressionar ESC
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.wpaip-modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.wpaip-modal-overlay.active').forEach(m => m.classList.remove('active'));
            }
        });
    });
</script>
