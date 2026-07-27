<?php
/**
 * Script de diagnóstico de licença — POST FÁCIL IA
 * IMPORTANTE: Apagar este arquivo após uso!
 *
 * Como usar:
 * 1. Enviar este arquivo para a raiz do WordPress no Hostinger (mesmo nível que wp-config.php)
 * 2. Acessar via: https://seu-dominio.com/wpaip-debug-license.php
 * 3. Após usar, APAGAR o arquivo do servidor.
 */

// ── Carregar WordPress ────────────────────────────────────────────────────────
// O script está em: wp-content/plugins/postfacil-ia/ → sobe 4 níveis até a raiz
$wp_root = dirname( __DIR__, 3 ); // postfacil-ia → plugins → wp-content → raiz WP
$wp_load = $wp_root . '/wp-load.php';

if ( ! file_exists( $wp_load ) ) {
    http_response_code( 500 );
    die( '<h1>Erro</h1><p>wp-load.php não encontrado em: ' . htmlspecialchars( $wp_load ) . '</p>' );
}

define( 'ABSPATH', $wp_root . '/' );
require_once $wp_load;

// ── Proteção mínima: só admins ─────────────────────────────────────────────────
if ( ! current_user_can( 'manage_options' ) ) {
    http_response_code( 403 );
    die( '<h1>403 Proibido</h1><p>Faça login como administrador WordPress antes de acessar esta página.</p>' );
}

// ── Carregar plugin ────────────────────────────────────────────────────────────
$plugin_file = WP_PLUGIN_DIR . '/postfacil-ia/postfacil-ia.php';
if ( ! file_exists( $plugin_file ) ) {
    die( '<h1>Erro</h1><p>Plugin postfacil-ia não encontrado em: ' . esc_html( $plugin_file ) . '</p>' );
}
require_once $plugin_file;

// ── Ação: Limpar chave de licença ─────────────────────────────────────────────
$action_done = '';
if ( isset( $_POST['action_reset'] ) && check_admin_referer( 'wpaip_debug_license' ) ) {
    $opts = WPAIP_Settings::get_options();
    $opts['license_key'] = '';
    update_option( WPAIP_Settings::OPTION_KEY, $opts );

    // Limpar cache de todos os usuários
    $users = get_users( [ 'fields' => 'ID' ] );
    foreach ( $users as $uid ) {
        delete_user_meta( $uid, 'wpaip_license_access' );
        delete_user_meta( $uid, 'wpaip_license_access_ts' );
    }
    $action_done = 'reset';
}

// ── Ação: Salvar nova chave diretamente ────────────────────────────────────────
if ( isset( $_POST['action_save'] ) && check_admin_referer( 'wpaip_debug_license' ) ) {
    $new_key = trim( sanitize_text_field( $_POST['new_license_key'] ?? '' ) );
    if ( ! empty( $new_key ) ) {
        $opts = WPAIP_Settings::get_options();
        $opts['license_key'] = WPAIP_Security::encrypt( $new_key );
        update_option( WPAIP_Settings::OPTION_KEY, $opts );

        // Limpar cache
        $users = get_users( [ 'fields' => 'ID' ] );
        foreach ( $users as $uid ) {
            delete_user_meta( $uid, 'wpaip_license_access' );
            delete_user_meta( $uid, 'wpaip_license_access_ts' );
        }
        $action_done = 'saved';
    }
}

// ── Ler chave atual ────────────────────────────────────────────────────────────
$enc_key     = WPAIP_Settings::get( 'license_key', '' );
$current_key = trim( WPAIP_Security::decrypt( $enc_key ) );
$server_url  = WPAIP_Settings::get( 'license_server_url', WPAIP_Paywall::DEFAULT_SERVER );

?><!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POST FÁCIL — Debug de Licença</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: system-ui, sans-serif; }
        body { background: #0f172a; color: #f1f5f9; min-height: 100vh; padding: 40px 20px; }
        .container { max-width: 700px; margin: 0 auto; }
        h1 { font-size: 24px; font-weight: 800; margin-bottom: 8px; color: #c4b5fd; }
        .subtitle { color: #94a3b8; font-size: 14px; margin-bottom: 32px; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 24px; margin-bottom: 20px; }
        .card h2 { font-size: 16px; font-weight: 700; margin-bottom: 16px; color: #e2e8f0; }
        .key-display { background: #0f172a; border: 1px solid #475569; border-radius: 8px; padding: 14px 16px; font-family: monospace; font-size: 15px; color: #4ade80; word-break: break-all; margin-bottom: 12px; }
        .key-empty { color: #f87171; }
        .alert { border-radius: 8px; padding: 12px 16px; font-size: 14px; margin-bottom: 20px; }
        .alert-success { background: rgba(74, 222, 128, 0.1); border: 1px solid #4ade80; color: #4ade80; }
        .alert-warning { background: rgba(251, 191, 36, 0.1); border: 1px solid #fbbf24; color: #fbbf24; }
        .alert-danger { background: rgba(248, 113, 113, 0.1); border: 1px solid #f87171; color: #f87171; }
        label { display: block; font-size: 13px; font-weight: 600; color: #94a3b8; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em; }
        input[type="text"] { width: 100%; background: #0f172a; border: 1px solid #475569; border-radius: 8px; padding: 10px 14px; color: #f1f5f9; font-size: 14px; font-family: monospace; margin-bottom: 12px; }
        input[type="text"]:focus { outline: 2px solid #7c3aed; border-color: transparent; }
        .btn { display: inline-block; padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; border: none; transition: 0.2s; }
        .btn-primary { background: #7c3aed; color: #fff; }
        .btn-primary:hover { background: #6d28d9; }
        .btn-danger { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
        .btn-danger:hover { background: rgba(239, 68, 68, 0.3); }
        .row { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        .meta { font-size: 12px; color: #64748b; }
        .security-notice { background: rgba(251, 191, 36, 0.08); border: 1px solid #fbbf24; border-radius: 12px; padding: 16px 20px; margin-bottom: 20px; color: #fbbf24; font-size: 13px; }
        .security-notice strong { display: block; margin-bottom: 4px; font-size: 14px; }
        hr { border: none; border-top: 1px solid #334155; margin: 20px 0; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔑 POST FÁCIL — Debug de Licença</h1>
    <p class="subtitle">Diagnóstico e reset da chave de licença gravada no WordPress.</p>

    <div class="security-notice">
        <strong>⚠️ ATENÇÃO DE SEGURANÇA</strong>
        Apague este arquivo do servidor imediatamente após usar. Ele expõe sua chave de licença para qualquer administrador logado.
    </div>

    <?php if ( $action_done === 'reset' ) : ?>
        <div class="alert alert-success">✓ Chave de licença apagada com sucesso. Agora entre uma chave válida pelo painel do WordPress.</div>
    <?php elseif ( $action_done === 'saved' ) : ?>
        <div class="alert alert-success">✓ Nova chave gravada e cache limpo. Acesse o painel do WordPress para confirmar.</div>
    <?php endif; ?>

    <!-- Chave atual -->
    <div class="card">
        <h2>Chave Atual Gravada no WordPress</h2>
        <?php if ( ! empty( $current_key ) ) : ?>
            <div class="key-display"><?php echo esc_html( $current_key ); ?></div>
            <p class="meta">Servidor de licenças configurado: <?php echo esc_html( $server_url ); ?></p>
            <hr>
            <p style="font-size:13px; color:#94a3b8; margin-bottom: 16px;">
                Copie a chave acima e cadastre-a no painel do servidor de licenças, ou apague e insira uma chave válida abaixo.
            </p>
            <form method="POST">
                <?php wp_nonce_field( 'wpaip_debug_license' ); ?>
                <button type="submit" name="action_reset" class="btn btn-danger"
                    onclick="return confirm('Apagar a chave de licença atual? Você perderá acesso ao plugin até ativar uma nova.')">
                    🗑 Apagar chave atual
                </button>
            </form>
        <?php else : ?>
            <div class="key-display key-empty">(nenhuma chave gravada)</div>
            <p class="meta">Nenhuma chave de licença está configurada no momento.</p>
        <?php endif; ?>
    </div>

    <!-- Gravar nova chave diretamente -->
    <div class="card">
        <h2>Gravar Nova Chave Diretamente</h2>
        <p style="font-size:13px; color:#94a3b8; margin-bottom: 16px;">
            Cole aqui uma chave que <strong>já existe e está ACTIVE</strong> no banco do servidor de licenças. Ela será criptografada e salva.
        </p>
        <form method="POST">
            <?php wp_nonce_field( 'wpaip_debug_license' ); ?>
            <label for="new_license_key">Nova Chave de Licença</label>
            <input type="text" id="new_license_key" name="new_license_key"
                   placeholder="WPAIP-XXXX-XXXX-XXXX" autocomplete="off" spellcheck="false">
            <button type="submit" name="action_save" class="btn btn-primary">💾 Salvar e ativar</button>
        </form>
    </div>

    <!-- Instruções -->
    <div class="card">
        <h2>📋 Como resolver "Chave não encontrada no banco"</h2>
        <ol style="font-size: 14px; color: #94a3b8; line-height: 2; padding-left: 20px;">
            <li>Copie a <strong style="color:#c4b5fd">chave atual</strong> mostrada acima.</li>
            <li>Acesse o painel do servidor de licenças:<br>
                <a href="<?php echo esc_url( rtrim( $server_url, '/' ) . '/index.php' ); ?>" target="_blank"
                   style="color: #7c3aed;"><?php echo esc_html( rtrim( $server_url, '/' ) . '/index.php' ); ?></a>
            </li>
            <li>Crie uma nova licença com essa chave exata (campo "Chave personalizada") e status <strong style="color:#4ade80">ACTIVE</strong>.</li>
            <li>OU: apague a chave atual acima, volte ao WordPress e ative uma chave válida pelo painel.</li>
            <li><strong style="color:#f87171">Apague este arquivo do servidor após concluir.</strong></li>
        </ol>
    </div>

    <p class="meta" style="text-align:center; margin-top:20px;">
        Arquivo: <?php echo esc_html( __FILE__ ); ?>
    </p>
</div>
</body>
</html>
