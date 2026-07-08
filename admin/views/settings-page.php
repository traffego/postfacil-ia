<?php defined( 'ABSPATH' ) || exit;
$opts     = WPAIP_Settings::get_options();
$saved    = isset( $_GET['settings-updated'] );
$providers = [ 'openai', 'gemini', 'anthropic', 'deepseek' ];
?>
<div class="wrap wpaip-wrap">

    <h1 class="wpaip-page-title">
        <span class="dashicons dashicons-superhero"></span>
        <?php _e( 'AI Publisher — Configurações', 'wp-ai-publisher' ); ?>
    </h1>

    <?php if ( $saved ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e( '✓ Configurações salvas com sucesso.', 'wp-ai-publisher' ); ?></p>
        </div>
    <?php endif; ?>

    <!-- ── Tutorial/Ajuda para Obter Chaves de API ── -->
    <div class="wpaip-card">
        <div class="wpaip-card-header" style="background: #fafafa; display: flex; justify-content: space-between; align-items: center; cursor: pointer;" onclick="const body = document.getElementById('wpaip-tutorial-body'); body.style.display = body.style.display === 'none' ? 'block' : 'none';">
            <div>
                <h2 style="font-size: 1rem !important; display: flex; align-items: center; gap: 8px;">
                    <span class="dashicons dashicons-editor-help" style="color: #7c6fcd;"></span>
                    <?php _e( 'Tutorial: Como Obter Suas Chaves de API', 'wp-ai-publisher' ); ?>
                </h2>
                <p class="wpaip-card-desc"><?php _e( 'Clique para ver instruções de como conseguir chaves para cada inteligência artificial.', 'wp-ai-publisher' ); ?></p>
            </div>
            <span class="dashicons dashicons-arrow-down-alt2"></span>
        </div>
        <div class="wpaip-card-body" id="wpaip-tutorial-body" style="display: none;">
            <div class="wpaip-grid-2">
                <div style="padding: 12px; border: 1px solid #f0f0f0; border-radius: 6px; background: #fafafa;">
                    <h3 style="margin-top: 0; font-size: 0.9rem; color: #333; display: flex; align-items: center; gap: 6px;">🤖 OpenAI</h3>
                    <p style="font-size: 0.78rem; line-height: 1.4; color: #666; margin-bottom: 0;">
                        1. Acesse <a href="https://platform.openai.com/" target="_blank">platform.openai.com</a>.<br>
                        2. Vá até <strong>API Keys</strong> no menu.<br>
                        3. Clique em <strong>Create new secret key</strong>.<br>
                        4. <em>Aviso:</em> Requer adicionar créditos em <strong>Settings > Billing</strong>.
                    </p>
                </div>
                <div style="padding: 12px; border: 1px solid #f0f0f0; border-radius: 6px; background: #fafafa;">
                    <h3 style="margin-top: 0; font-size: 0.9rem; color: #333; display: flex; align-items: center; gap: 6px;">✨ Google Gemini</h3>
                    <p style="font-size: 0.78rem; line-height: 1.4; color: #666; margin-bottom: 0;">
                        1. Acesse <a href="https://aistudio.google.com/" target="_blank">aistudio.google.com</a>.<br>
                        2. Clique em <strong>Get API key</strong>.<br>
                        3. Clique em <strong>Create API Key</strong>.<br>
                        4. Copie a chave gerada. Tem cota gratuita para testes.
                    </p>
                </div>
                <div style="padding: 12px; border: 1px solid #f0f0f0; border-radius: 6px; background: #fafafa;">
                    <h3 style="margin-top: 0; font-size: 0.9rem; color: #333; display: flex; align-items: center; gap: 6px;">🧠 Anthropic</h3>
                    <p style="font-size: 0.78rem; line-height: 1.4; color: #666; margin-bottom: 0;">
                        1. Acesse <a href="https://console.anthropic.com/" target="_blank">console.anthropic.com</a>.<br>
                        2. Clique na aba <strong>API Keys</strong>.<br>
                        3. Clique em <strong>Create Key</strong>.<br>
                        4. Requer créditos na aba <strong>Billing</strong>.
                    </p>
                </div>
                <div style="padding: 12px; border: 1px solid #f0f0f0; border-radius: 6px; background: #fafafa;">
                    <h3 style="margin-top: 0; font-size: 0.9rem; color: #333; display: flex; align-items: center; gap: 6px;">🔍 DeepSeek</h3>
                    <p style="font-size: 0.78rem; line-height: 1.4; color: #666; margin-bottom: 0;">
                        1. Acesse <a href="https://platform.deepseek.com/" target="_blank">platform.deepseek.com</a>.<br>
                        2. Vá até <strong>API Keys</strong>.<br>
                        3. Clique em <strong>Create API Key</strong>.<br>
                        4. Adicione saldo na conta para chamadas funcionarem.
                    </p>
                </div>
                <div style="padding: 12px; border: 1px solid #f0f0f0; border-radius: 6px; background: #fafafa;">
                    <h3 style="margin-top: 0; font-size: 0.9rem; color: #333; display: flex; align-items: center; gap: 6px;">🤗 Hugging Face</h3>
                    <p style="font-size: 0.78rem; line-height: 1.4; color: #666; margin-bottom: 0;">
                        1. Acesse <a href="https://huggingface.co/settings/tokens" target="_blank">huggingface.co/settings/tokens</a>.<br>
                        2. Crie uma conta grátis (sem cartão).<br>
                        3. Gere um token com permissão <strong>Read</strong>.<br>
                        4. Copie e cole. Permite imagens grátis!
                    </p>
                </div>
            </div>
        </div>
    </div>

    <form method="post" action="options.php">
        <?php settings_fields( WPAIP_SLUG . '-settings-group' ); ?>

        <!-- ── API Keys ── -->
        <div class="wpaip-card">
            <div class="wpaip-card-header">
                <h2><?php _e( 'Chaves de API', 'wp-ai-publisher' ); ?></h2>
                <p class="wpaip-card-desc"><?php _e( 'Credenciais armazenadas com criptografia AES-256. Nunca expostas em plain text.', 'wp-ai-publisher' ); ?></p>
            </div>
            <div class="wpaip-card-body">

                <?php
                $providers_meta = [
                    'openai'      => [ 'label' => 'OpenAI',     'placeholder' => 'sk-...',       'icon' => '🤖' ],
                    'gemini'      => [ 'label' => 'Google Gemini', 'placeholder' => 'AIza...',    'icon' => '✨' ],
                    'anthropic'   => [ 'label' => 'Anthropic',  'placeholder' => 'sk-ant-...',   'icon' => '🧠' ],
                    'deepseek'    => [ 'label' => 'DeepSeek',   'placeholder' => 'sk-...',       'icon' => '🔍' ],
                    'huggingface' => [ 'label' => 'Hugging Face (Imagens Grátis)', 'placeholder' => 'hf_...', 'icon' => '🤗' ],
                ];

                foreach ( $providers_meta as $key => $meta ) :
                    $has_key = ! empty( WPAIP_Settings::get_api_key( $key ) );
                ?>
                <div class="wpaip-api-row">
                    <div class="wpaip-api-label">
                        <span class="wpaip-api-icon"><?php echo $meta['icon']; ?></span>
                        <div>
                            <strong><?php echo esc_html( $meta['label'] ); ?></strong>
                            <?php if ( $has_key ) : ?>
                                <span class="wpaip-badge wpaip-badge--ok"><?php _e( 'Configurada', 'wp-ai-publisher' ); ?></span>
                            <?php else : ?>
                                <span class="wpaip-badge wpaip-badge--empty"><?php _e( 'Não configurada', 'wp-ai-publisher' ); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="wpaip-api-input-group">
                        <input
                            type="password"
                            id="wpaip-key-<?php echo esc_attr( $key ); ?>"
                            name="<?php echo esc_attr( WPAIP_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $key ); ?>_api_key]"
                            class="wpaip-input"
                            placeholder="<?php echo esc_attr( $has_key ? '••••••••••••••••' : $meta['placeholder'] ); ?>"
                            autocomplete="new-password"
                        >
                        <button type="button"
                            class="button wpaip-test-btn"
                            data-provider="<?php echo esc_attr( $key ); ?>"
                            data-input="#wpaip-key-<?php echo esc_attr( $key ); ?>"
                        ><?php _e( 'Testar', 'wp-ai-publisher' ); ?></button>
                        <span class="wpaip-test-result" id="wpaip-result-<?php echo esc_attr( $key ); ?>"></span>
                    </div>
                </div>
                <?php endforeach; ?>

            </div>
        </div>

        <!-- ── Padrões ── -->
        <div class="wpaip-card">
            <div class="wpaip-card-header">
                <h2><?php _e( 'Providers Padrão', 'wp-ai-publisher' ); ?></h2>
            </div>
            <div class="wpaip-card-body wpaip-grid-2">

                <div class="wpaip-field">
                    <label for="wpaip-default-llm"><?php _e( 'Modelo de Texto padrão', 'wp-ai-publisher' ); ?></label>
                    <select id="wpaip-default-llm" name="<?php echo WPAIP_Settings::OPTION_KEY; ?>[default_llm]" class="wpaip-select">
                        <option value="openai"    <?php selected( $opts['default_llm'], 'openai'    ); ?>>GPT (OpenAI)</option>
                        <option value="gemini"    <?php selected( $opts['default_llm'], 'gemini'    ); ?>>Gemini (Google)</option>
                        <option value="anthropic" <?php selected( $opts['default_llm'], 'anthropic' ); ?>>Claude (Anthropic)</option>
                        <option value="deepseek"  <?php selected( $opts['default_llm'], 'deepseek'  ); ?>>DeepSeek</option>
                    </select>
                </div>

                <div class="wpaip-field">
                    <label for="wpaip-default-image"><?php _e( 'Gerador de Imagens padrão', 'wp-ai-publisher' ); ?></label>
                    <select id="wpaip-default-image" name="<?php echo WPAIP_Settings::OPTION_KEY; ?>[default_image]" class="wpaip-select">
                        <option value="pollinations" <?php selected( $opts['default_image'], 'pollinations' ); ?>>Pollinations AI (Grátis)</option>
                        <option value="dalle3" <?php selected( $opts['default_image'], 'dalle3' ); ?>>DALL-E 3 (OpenAI)</option>
                        <option value="gemini" <?php selected( $opts['default_image'], 'gemini' ); ?>>Imagen 4 (Gemini)</option>
                        <option value="huggingface" <?php selected( $opts['default_image'], 'huggingface' ); ?>>Hugging Face</option>
                    </select>
                </div>

            </div>
        </div>

        <!-- ── Modelos ── -->
        <div class="wpaip-card">
            <div class="wpaip-card-header">
                <h2><?php _e( 'Versões dos Modelos', 'wp-ai-publisher' ); ?></h2>
            </div>
            <div class="wpaip-card-body wpaip-grid-2">

                <?php
                $model_opts = [
                    'openai_model'    => [ 'label' => 'OpenAI',     'opts' => [ 'gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'o1-mini' ] ],
                    'gemini_model'    => [ 'label' => 'Gemini',      'opts' => [ 'gemini-3.5-flash', 'gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-2.0-flash' ] ],
                    'anthropic_model' => [ 'label' => 'Claude',      'opts' => [ 'claude-sonnet-4-5', 'claude-opus-4-5', 'claude-haiku-3-5' ] ],
                    'deepseek_model'  => [ 'label' => 'DeepSeek',    'opts' => [ 'deepseek-chat', 'deepseek-reasoner' ] ],
                ];
                foreach ( $model_opts as $field => $meta ) :
                ?>
                <div class="wpaip-field">
                    <label><?php echo esc_html( $meta['label'] ); ?></label>
                    <select name="<?php echo WPAIP_Settings::OPTION_KEY; ?>[<?php echo esc_attr( $field ); ?>]" class="wpaip-select">
                        <?php foreach ( $meta['opts'] as $m ) : ?>
                            <option value="<?php echo esc_attr( $m ); ?>" <?php selected( $opts[ $field ] ?? '', $m ); ?>>
                                <?php echo esc_html( $m ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endforeach; ?>

                <!-- ── Hugging Face Modelo de Imagem ── -->
                <div class="wpaip-field" style="grid-column: 1 / -1;">
                    <label for="wpaip-hf-model">🤗 Hugging Face — Modelo de Imagem</label>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <select
                            id="wpaip-hf-model"
                            name="<?php echo WPAIP_Settings::OPTION_KEY; ?>[huggingface_image_model]"
                            class="wpaip-select"
                        >
                            <?php
                            $saved_hf = $opts['huggingface_image_model'] ?? 'black-forest-labs/FLUX.1-schnell';
                            $defaults_hf = [
                                'black-forest-labs/FLUX.1-schnell' => 'FLUX.1-schnell (rápido/grátis)',
                                'black-forest-labs/FLUX.1-dev'     => 'FLUX.1-dev (melhor qualidade)',
                                'stabilityai/stable-diffusion-xl-base-1.0' => 'Stable Diffusion XL',
                                'stabilityai/sdxl-turbo'           => 'SDXL-Turbo (muito rápido)',
                                'runwayml/stable-diffusion-v1-5'   => 'Stable Diffusion v1.5',
                            ];
                            foreach ( $defaults_hf as $val => $label ) :
                            ?>
                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $saved_hf, $val ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" id="wpaip-hf-load-models" class="button">
                            🔄 Carregar da API
                        </button>
                        <span id="wpaip-hf-models-status" style="font-size:11px; color:#888;"></span>
                    </div>
                    <span class="wpaip-field-hint">
                        Clique em "Carregar da API" para buscar os 30 modelos text-to-image mais populares do Hugging Face (requer chave configurada).
                    </span>
                </div>

            </div>
        </div>

        <!-- ── Prompt de sistema ── -->
        <div class="wpaip-card">
            <div class="wpaip-card-header">
                <h2><?php _e( 'Prompt de Sistema Global', 'wp-ai-publisher' ); ?></h2>
                <p class="wpaip-card-desc"><?php _e( 'Instrução base enviada a todos os modelos. Defina tom, idioma e estilo de escrita.', 'wp-ai-publisher' ); ?></p>
            </div>
            <div class="wpaip-card-body">
                <textarea
                    name="<?php echo WPAIP_Settings::OPTION_KEY; ?>[system_prompt]"
                    class="wpaip-input wpaip-textarea"
                    rows="4"
                ><?php echo esc_textarea( $opts['system_prompt'] ?? '' ); ?></textarea>
            </div>
        </div>

        <?php submit_button( __( 'Salvar Configurações', 'wp-ai-publisher' ) ); ?>
    </form>
</div>
