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
            <p><?php _e( 'Configurações salvas com sucesso.', 'wp-ai-publisher' ); ?></p>
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
                    <h3 style="margin-top: 0; font-size: 0.9rem; color: #333; display: flex; align-items: center; gap: 6px;">OpenAI</h3>
                    <p style="font-size: 0.78rem; line-height: 1.4; color: #666; margin-bottom: 0;">
                        1. Acesse <a href="https://platform.openai.com/" target="_blank">platform.openai.com</a>.<br>
                        2. Vá até <strong>API Keys</strong> no menu.<br>
                        3. Clique em <strong>Create new secret key</strong>.<br>
                        4. <em>Aviso:</em> Requer adicionar créditos em <strong>Settings > Billing</strong>.
                    </p>
                </div>
                <div style="padding: 12px; border: 1px solid #f0f0f0; border-radius: 6px; background: #fafafa;">
                    <h3 style="margin-top: 0; font-size: 0.9rem; color: #333; display: flex; align-items: center; gap: 6px;">Google Gemini</h3>
                    <p style="font-size: 0.78rem; line-height: 1.4; color: #666; margin-bottom: 0;">
                        1. Acesse <a href="https://aistudio.google.com/" target="_blank">aistudio.google.com</a>.<br>
                        2. Clique em <strong>Get API key</strong>.<br>
                        3. Clique em <strong>Create API Key</strong>.<br>
                        4. Copie a chave gerada. Tem cota gratuita para testes.
                    </p>
                </div>
                <div style="padding: 12px; border: 1px solid #f0f0f0; border-radius: 6px; background: #fafafa;">
                    <h3 style="margin-top: 0; font-size: 0.9rem; color: #333; display: flex; align-items: center; gap: 6px;">Anthropic</h3>
                    <p style="font-size: 0.78rem; line-height: 1.4; color: #666; margin-bottom: 0;">
                        1. Acesse <a href="https://console.anthropic.com/" target="_blank">console.anthropic.com</a>.<br>
                        2. Clique na aba <strong>API Keys</strong>.<br>
                        3. Clique em <strong>Create Key</strong>.<br>
                        4. Requer créditos na aba <strong>Billing</strong>.
                    </p>
                </div>
                <div style="padding: 12px; border: 1px solid #f0f0f0; border-radius: 6px; background: #fafafa;">
                    <h3 style="margin-top: 0; font-size: 0.9rem; color: #333; display: flex; align-items: center; gap: 6px;">DeepSeek</h3>
                    <p style="font-size: 0.78rem; line-height: 1.4; color: #666; margin-bottom: 0;">
                        1. Acesse <a href="https://platform.deepseek.com/" target="_blank">platform.deepseek.com</a>.<br>
                        2. Vá até <strong>API Keys</strong>.<br>
                        3. Clique em <strong>Create API Key</strong>.<br>
                        4. Adicione saldo na conta para chamadas funcionarem.
                    </p>
                </div>
                <div style="padding: 12px; border: 1px solid #f0f0f0; border-radius: 6px; background: #fafafa;">
                    <h3 style="margin-top: 0; font-size: 0.9rem; color: #333; display: flex; align-items: center; gap: 6px;">Hugging Face</h3>
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
                    'openai'      => [ 'label' => 'OpenAI',     'placeholder' => 'sk-...',       'icon' => '' ],
                    'gemini'      => [ 'label' => 'Google Gemini', 'placeholder' => 'AIza...',    'icon' => '' ],
                    'anthropic'   => [ 'label' => 'Anthropic',  'placeholder' => 'sk-ant-...',   'icon' => '' ],
                    'deepseek'    => [ 'label' => 'DeepSeek',   'placeholder' => 'sk-...',       'icon' => '' ],
                    'huggingface' => [ 'label' => 'Hugging Face (Imagens Grátis)', 'placeholder' => 'hf_...', 'icon' => '' ],
                ];

                foreach ( $providers_meta as $key => $meta ) :
                    $has_key = ! empty( WPAIP_Settings::get_api_key( $key ) );
                ?>
                <div class="wpaip-api-row">
                    <div class="wpaip-api-label">
                        <?php if ( ! empty( $meta['icon'] ) ) : ?><span class="wpaip-api-icon"><?php echo $meta['icon']; ?></span><?php endif; ?>
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

        <!-- ── Modelo de Texto ── -->
        <div class="wpaip-card">
            <div class="wpaip-card-header">
                <h2><?php _e( 'Modelo de Texto', 'wp-ai-publisher' ); ?></h2>
                <p class="wpaip-card-desc"><?php _e( 'Provider e versão usados para gerar texto no editor de posts.', 'wp-ai-publisher' ); ?></p>
            </div>
            <div class="wpaip-card-body wpaip-grid-2">

                <!-- Provider -->
                <div class="wpaip-field">
                    <label for="wpaip-default-llm"><?php _e( 'Provider', 'wp-ai-publisher' ); ?></label>
                    <select id="wpaip-default-llm" name="<?php echo WPAIP_Settings::OPTION_KEY; ?>[default_llm]" class="wpaip-select">
                        <option value="openai"    <?php selected( $opts['default_llm'], 'openai'    ); ?>>GPT (OpenAI)</option>
                        <option value="gemini"    <?php selected( $opts['default_llm'], 'gemini'    ); ?>>Gemini (Google)</option>
                        <option value="anthropic" <?php selected( $opts['default_llm'], 'anthropic' ); ?>>Claude (Anthropic)</option>
                        <option value="deepseek"  <?php selected( $opts['default_llm'], 'deepseek'  ); ?>>DeepSeek</option>
                    </select>
                </div>

                <!-- Versão por provider (ocultas dinamicamente via JS) -->
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
                <div class="wpaip-field wpaip-model-group" data-provider="<?php echo esc_attr( $meta['provider'] ); ?>" style="<?php echo $is_active ? '' : 'display:none;'; ?>">
                    <label><?php printf( __( 'Versão (%s)', 'wp-ai-publisher' ), esc_html( $meta['label'] ) ); ?></label>
                    <select name="<?php echo WPAIP_Settings::OPTION_KEY; ?>[<?php echo esc_attr( $field ); ?>]" class="wpaip-select">
                        <?php foreach ( $meta['opts'] as $m ) : ?>
                            <option value="<?php echo esc_attr( $m ); ?>" <?php selected( $opts[ $field ] ?? '', $m ); ?>>
                                <?php echo esc_html( $m ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endforeach; ?>

            </div>
        </div>

        <!-- ── Modelo de Imagem ── -->
        <div class="wpaip-card">
            <div class="wpaip-card-header">
                <h2><?php _e( 'Modelo de Imagem', 'wp-ai-publisher' ); ?></h2>
                <p class="wpaip-card-desc"><?php _e( 'Provider e modelo usados para gerar imagens no editor de posts.', 'wp-ai-publisher' ); ?></p>
            </div>
            <div class="wpaip-card-body wpaip-grid-2">

                <!-- Provider de imagem -->
                <div class="wpaip-field">
                    <label for="wpaip-default-image"><?php _e( 'Provider', 'wp-ai-publisher' ); ?></label>
                    <select id="wpaip-default-image" name="<?php echo WPAIP_Settings::OPTION_KEY; ?>[default_image]" class="wpaip-select">
                        <option value="pollinations" <?php selected( $opts['default_image'], 'pollinations' ); ?>>Pollinations AI (Grátis — Sem Chave)</option>
                        <option value="dalle3"       <?php selected( $opts['default_image'], 'dalle3'       ); ?>>DALL-E 3 (OpenAI)</option>
                        <option value="gemini"       <?php selected( $opts['default_image'], 'gemini'       ); ?>>Imagen 4 (Gemini)</option>
                        <option value="huggingface"  <?php selected( $opts['default_image'], 'huggingface'  ); ?>>Hugging Face (Grátis — Com Chave)</option>
                    </select>
                </div>

                <!-- Modelo Hugging Face (visível só quando HF selecionado) -->
                <div class="wpaip-field" id="wpaip-hf-model-wrapper" style="<?php echo ( $opts['default_image'] === 'huggingface' ) ? '' : 'display:none;'; ?>">
                    <label for="wpaip-hf-model"><?php _e( 'Modelo Hugging Face', 'wp-ai-publisher' ); ?></label>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <select
                            id="wpaip-hf-model"
                            name="<?php echo WPAIP_Settings::OPTION_KEY; ?>[huggingface_image_model]"
                            class="wpaip-select"
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
                        <button type="button" id="wpaip-hf-load-models" class="button">Carregar da API</button>
                        <span id="wpaip-hf-models-status" style="font-size:11px; color:#888;"></span>
                    </div>
                    <span class="wpaip-field-hint">
                        <?php _e( 'Clique em "Carregar da API" para buscar os 30 modelos text-to-image mais populares do Hugging Face (requer chave configurada).', 'wp-ai-publisher' ); ?>
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
                <div class="wpaip-field">
                    <label for="wpaip-system-prompt"><?php _e( 'Prompt de Sistema', 'wp-ai-publisher' ); ?></label>
                    <textarea
                        id="wpaip-system-prompt"
                        name="<?php echo WPAIP_Settings::OPTION_KEY; ?>[system_prompt]"
                        class="wpaip-input wpaip-textarea"
                        rows="4"
                    ><?php echo esc_textarea( $opts['system_prompt'] ?? '' ); ?></textarea>
                    <div style="margin-top: 8px; display: flex; align-items: center; gap: 10px;">
                        <button type="button" id="wpaip-btn-improve-prompt" class="button button-secondary">
                            <?php _e( '✦ Melhorar Prompt', 'wp-ai-publisher' ); ?>
                        </button>
                        <span id="wpaip-improve-prompt-status" style="font-size: 11px; color: #888; display: none;"></span>
                    </div>
                </div>

                <div class="wpaip-field" style="margin-top: 15px;">
                    <label for="wpaip-default-journalistic-style"><?php _e( 'Estilo Jornalístico Padrão', 'wp-ai-publisher' ); ?></label>
                    <select id="wpaip-default-journalistic-style" name="<?php echo WPAIP_Settings::OPTION_KEY; ?>[default_journalistic_style]" class="wpaip-select">
                        <option value="default" <?php selected( $opts['default_journalistic_style'] ?? 'default', 'default' ); ?>><?php _e( 'Informativo / Padrão (Fatos diretos e linguagem neutra)', 'wp-ai-publisher' ); ?></option>
                        <option value="investigative" <?php selected( $opts['default_journalistic_style'] ?? 'default', 'investigative' ); ?>><?php _e( 'Investigativo (Profundo e analítico)', 'wp-ai-publisher' ); ?></option>
                        <option value="editorial" <?php selected( $opts['default_journalistic_style'] ?? 'default', 'editorial' ); ?>><?php _e( 'Opinativo / Editorial (Argumentativo e defensor de tese)', 'wp-ai-publisher' ); ?></option>
                        <option value="interview" <?php selected( $opts['default_journalistic_style'] ?? 'default', 'interview' ); ?>><?php _e( 'Entrevista (Estrutura de perguntas/respostas ou abundante em citações)', 'wp-ai-publisher' ); ?></option>
                        <option value="narrative" <?php selected( $opts['default_journalistic_style'] ?? 'default', 'narrative' ); ?>><?php _e( 'Crônica / Narrativo (Storytelling, tom literário e reflexivo)', 'wp-ai-publisher' ); ?></option>
                        <option value="sensationalist" <?php selected( $opts['default_journalistic_style'] ?? 'default', 'sensationalist' ); ?>><?php _e( 'Sensacionalista / Tabloide (Dramático, ganchos de curiosidade e forte apelo emocional)', 'wp-ai-publisher' ); ?></option>
                        <option value="ugauga" <?php selected( $opts['default_journalistic_style'] ?? 'default', 'ugauga' ); ?>><?php _e( 'UGA-UGA Teste (Inserção obrigatória do termo UGA-UGA várias vezes)', 'wp-ai-publisher' ); ?></option>
                    </select>
                    <span class="wpaip-field-hint"><?php _e( 'O estilo selecionado influenciará a estrutura, tom de voz e abordagem do conteúdo gerado pela IA.', 'wp-ai-publisher' ); ?></span>
                </div>
            </div>
        </div>

        <!-- ── Asaas — Controle de Acesso ── -->
        <div class="wpaip-card" style="border-top: 3px solid #7c3aed;">
            <div class="wpaip-card-header" style="background: linear-gradient(135deg, rgba(124,58,237,.06) 0%, rgba(236,72,153,.04) 100%);">
                <h2 style="display:flex; align-items:center; gap:8px;">
                    <span class="dashicons dashicons-lock" style="color:#7c3aed;"></span>
                    <?php _e( 'Asaas — Controle de Acesso', 'wp-ai-publisher' ); ?>
                </h2>
                <p class="wpaip-card-desc">
                    <?php _e( 'Restrinja o uso do plugin a usuários com assinatura ou cobrança ativa no Asaas. Deixe a chave em branco para desabilitar o paywall.', 'wp-ai-publisher' ); ?>
                </p>
            </div>
            <div class="wpaip-card-body">

                <!-- Chave API -->
                <div class="wpaip-api-row">
                    <div class="wpaip-api-label">
                        <span class="wpaip-api-icon"><span class="dashicons dashicons-cart" style="font-size:18px;"></span></span>
                        <div>
                            <strong><?php _e( 'Chave de API Asaas', 'wp-ai-publisher' ); ?></strong>
                            <?php $has_asaas = ! empty( WPAIP_Security::decrypt( WPAIP_Settings::get( 'asaas_api_key' ) ) ); ?>
                            <?php if ( $has_asaas ) : ?>
                                <span class="wpaip-badge wpaip-badge--ok"><?php _e( 'Configurada', 'wp-ai-publisher' ); ?></span>
                            <?php else : ?>
                                <span class="wpaip-badge wpaip-badge--empty"><?php _e( 'Não configurada', 'wp-ai-publisher' ); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="wpaip-api-input-group">
                        <input
                            type="password"
                            id="wpaip-asaas-key"
                            name="<?php echo WPAIP_Settings::OPTION_KEY; ?>[asaas_api_key]"
                            class="wpaip-input"
                            placeholder="<?php echo esc_attr( $has_asaas ? '••••••••••••••••' : '$aact_...' ); ?>"
                            autocomplete="new-password"
                        >
                        <button type="button" id="wpaip-asaas-test-btn" class="button">
                            <?php _e( 'Testar', 'wp-ai-publisher' ); ?>
                        </button>
                        <span class="wpaip-test-result" id="wpaip-asaas-test-result"></span>
                    </div>
                </div>

                <div class="wpaip-grid-2" style="margin-top:20px;">

                    <!-- Ambiente -->
                    <div class="wpaip-field">
                        <label for="wpaip-asaas-env"><?php _e( 'Ambiente', 'wp-ai-publisher' ); ?></label>
                        <select id="wpaip-asaas-env" name="<?php echo WPAIP_Settings::OPTION_KEY; ?>[asaas_environment]" class="wpaip-select">
                            <option value="sandbox"    <?php selected( WPAIP_Settings::get( 'asaas_environment', 'sandbox' ), 'sandbox'    ); ?>>Sandbox (testes)</option>
                            <option value="production" <?php selected( WPAIP_Settings::get( 'asaas_environment', 'sandbox' ), 'production' ); ?>>Produção</option>
                        </select>
                        <span class="wpaip-field-hint">Use Sandbox para testes e Produção para o site real.</span>
                    </div>

                    <!-- Cache -->
                    <div class="wpaip-field">
                        <label for="wpaip-asaas-cache"><?php _e( 'Cache de verificação (horas)', 'wp-ai-publisher' ); ?></label>
                        <input
                            type="number"
                            id="wpaip-asaas-cache"
                            name="<?php echo WPAIP_Settings::OPTION_KEY; ?>[asaas_cache_hours]"
                            class="wpaip-input"
                            value="<?php echo esc_attr( WPAIP_Settings::get( 'asaas_cache_hours', 24 ) ); ?>"
                            min="1" max="168" step="1"
                        >
                        <span class="wpaip-field-hint">Evita chamadas excessivas à API. Padrão: 24h.</span>
                    </div>

                    <!-- Link de pagamento -->
                    <div class="wpaip-field" style="grid-column: 1 / -1;">
                        <label for="wpaip-asaas-link"><?php _e( 'Link de assinatura (redirecionamento)', 'wp-ai-publisher' ); ?></label>
                        <input
                            type="url"
                            id="wpaip-asaas-link"
                            name="<?php echo WPAIP_Settings::OPTION_KEY; ?>[asaas_payment_link]"
                            class="wpaip-input"
                            value="<?php echo esc_url( WPAIP_Settings::get( 'asaas_payment_link', '' ) ); ?>"
                            placeholder="https://www.asaas.com/c/seu-link"
                        >
                        <span class="wpaip-field-hint">URL exibida no botão "Assinar agora" da página de bloqueio.</span>
                    </div>

                    <!-- Bypass admins -->
                    <div class="wpaip-field" style="grid-column: 1 / -1;">
                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                            <input
                                type="checkbox"
                                name="<?php echo WPAIP_Settings::OPTION_KEY; ?>[asaas_bypass_admins]"
                                value="1"
                                <?php checked( WPAIP_Settings::get( 'asaas_bypass_admins', '1' ), '1' ); ?>
                                style="width:18px; height:18px; accent-color:#7c3aed; cursor:pointer;"
                            >
                            <span><?php _e( 'Isentar administradores da verificação (recomendado)', 'wp-ai-publisher' ); ?></span>
                        </label>
                        <span class="wpaip-field-hint" style="margin-left:28px;">Usuários com permissão <code>manage_options</code> nunca serão bloqueados.</span>
                    </div>

                </div>

                <!-- Limpar cache manual -->
                <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid rgba(0,0,0,.06); display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                    <button type="button" id="wpaip-asaas-clear-cache" class="button">
                        <?php _e( 'Limpar meu cache Asaas', 'wp-ai-publisher' ); ?>
                    </button>
                    <span id="wpaip-asaas-cache-result" style="font-size:12px; color:#666;"></span>
                    <span style="font-size:12px; color:#999;"><?php _e( 'Força re-verificação do seu status de pagamento na próxima visita.', 'wp-ai-publisher' ); ?></span>
                </div>

            </div>
        </div>

        <?php submit_button( __( 'Salvar Configurações', 'wp-ai-publisher' ) ); ?>
    </form>
</div>
