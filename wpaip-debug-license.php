<?php
/**
 * Script de diagnóstico de licença — POST FÁCIL IA
 * APAGAR DO SERVIDOR APÓS USAR!
 *
 * URL de acesso:
 * https://olive-locust-173119.hostingersite.com/wp-content/plugins/postfacil-ia/wpaip-debug-license.php
 */

// Carregar WordPress — sem redefinir ABSPATH
require_once dirname( __FILE__, 4 ) . '/wp-load.php';

// Só admins podem acessar
if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
    auth_redirect();
    exit;
}

// Verificar se as classes do plugin estão disponíveis
if ( ! class_exists( 'WPAIP_Settings' ) || ! class_exists( 'WPAIP_Security' ) ) {
    wp_die( 'Plugin POST FÁCIL não está ativo. Ative-o no painel do WordPress primeiro.' );
}

// ── Ação: Apagar chave de licença ──────────────────────────────────────────────
$msg  = '';
$type = '';

if ( isset( $_POST['action_reset'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'wpaip_debug' ) ) {
    $opts                = WPAIP_Settings::get_options();
    $opts['license_key'] = '';
    update_option( WPAIP_Settings::OPTION_KEY, $opts );
    foreach ( get_users( [ 'fields' => 'ID' ] ) as $uid ) {
        delete_user_meta( $uid, 'wpaip_license_access' );
        delete_user_meta( $uid, 'wpaip_license_access_ts' );
    }
    $msg  = 'Chave apagada. Entre uma nova chave válida abaixo.';
    $type = 'success';
}

// ── Ação: Salvar nova chave ────────────────────────────────────────────────────
if ( isset( $_POST['action_save'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'wpaip_debug' ) ) {
    $new_key = trim( sanitize_text_field( $_POST['new_key'] ?? '' ) );
    if ( ! empty( $new_key ) ) {
        $opts                = WPAIP_Settings::get_options();
        $opts['license_key'] = WPAIP_Security::encrypt( $new_key );
        update_option( WPAIP_Settings::OPTION_KEY, $opts );
        foreach ( get_users( [ 'fields' => 'ID' ] ) as $uid ) {
            delete_user_meta( $uid, 'wpaip_license_access' );
            delete_user_meta( $uid, 'wpaip_license_access_ts' );
        }
        $msg  = 'Nova chave salva com sucesso: ' . esc_html( $new_key );
        $type = 'success';
    } else {
        $msg  = 'Digite uma chave válida.';
        $type = 'error';
    }
}

// ── Ler chave atual ────────────────────────────────────────────────────────────
$enc_key     = WPAIP_Settings::get( 'license_key', '' );
$current_key = trim( WPAIP_Security::decrypt( $enc_key ) );
$server_url  = WPAIP_Settings::get( 'license_server_url', 'https://olive-locust-173119.hostingersite.com/license-server-wp-post/' );

?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Debug Licença — POST FÁCIL</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;font-family:system-ui,sans-serif}
body{background:#0f172a;color:#f1f5f9;min-height:100vh;padding:40px 20px}
.wrap{max-width:680px;margin:0 auto}
h1{font-size:22px;font-weight:800;color:#c4b5fd;margin-bottom:6px}
.sub{color:#64748b;font-size:13px;margin-bottom:28px}
.card{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:22px;margin-bottom:18px}
h2{font-size:15px;font-weight:700;color:#e2e8f0;margin-bottom:14px}
.key{background:#0f172a;border:1px solid #475569;border-radius:8px;padding:13px 15px;font-family:monospace;font-size:15px;color:#4ade80;word-break:break-all;margin-bottom:10px}
.key.empty{color:#f87171}
.alert{border-radius:8px;padding:11px 15px;font-size:14px;margin-bottom:16px}
.alert.success{background:rgba(74,222,128,.1);border:1px solid #4ade80;color:#4ade80}
.alert.error{background:rgba(248,113,113,.1);border:1px solid #f87171;color:#f87171}
.warn{background:rgba(251,191,36,.08);border:1px solid #fbbf24;border-radius:10px;padding:14px 18px;margin-bottom:18px;color:#fbbf24;font-size:13px}
label{display:block;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;margin-bottom:7px}
input[type=text]{width:100%;background:#0f172a;border:1px solid #475569;border-radius:8px;padding:10px 13px;color:#f1f5f9;font-size:14px;font-family:monospace;margin-bottom:12px}
.btn{display:inline-block;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;border:none;transition:.15s}
.btn-primary{background:#7c3aed;color:#fff}
.btn-primary:hover{background:#6d28d9}
.btn-danger{background:rgba(239,68,68,.15);color:#f87171;border:1px solid rgba(239,68,68,.3)}
.btn-danger:hover{background:rgba(239,68,68,.3)}
hr{border:none;border-top:1px solid #334155;margin:16px 0}
ol{padding-left:18px;font-size:13px;color:#94a3b8;line-height:2}
a{color:#7c3aed}
</style>
</head>
<body>
<div class="wrap">
<h1>🔑 Debug de Licença — POST FÁCIL</h1>
<p class="sub">Diagnóstico da chave gravada no WordPress.</p>

<div class="warn">⚠️ <strong>Apague este arquivo do servidor após usar.</strong> Ele expõe sua chave de licença.</div>

<?php if ( $msg ) : ?>
<div class="alert <?php echo $type; ?>"><?php echo $msg; ?></div>
<?php endif; ?>

<!-- Chave atual -->
<div class="card">
    <h2>Chave Atual no WordPress</h2>
    <?php if ( $current_key ) : ?>
        <div class="key"><?php echo esc_html( $current_key ); ?></div>
        <p style="font-size:12px;color:#64748b;margin-bottom:14px">Servidor: <?php echo esc_html( $server_url ); ?></p>
        <hr>
        <p style="font-size:13px;color:#94a3b8;margin-bottom:14px">Copie a chave acima e cadastre-a no painel do servidor de licenças com status <strong style="color:#4ade80">ACTIVE</strong>. Ou apague e insira uma chave válida.</p>
        <form method="POST">
            <?php echo wp_nonce_field( 'wpaip_debug', '_wpnonce', true, false ); ?>
            <button type="submit" name="action_reset" class="btn btn-danger"
                onclick="return confirm('Apagar a chave atual? Você perderá acesso até ativar uma nova.')">
                🗑 Apagar chave atual
            </button>
        </form>
    <?php else : ?>
        <div class="key empty">(sem chave gravada)</div>
    <?php endif; ?>
</div>

<!-- Gravar nova chave -->
<div class="card">
    <h2>Gravar Nova Chave</h2>
    <p style="font-size:13px;color:#94a3b8;margin-bottom:14px">Cole uma chave que <strong>já existe como ACTIVE</strong> no banco do servidor de licenças.</p>
    <form method="POST">
        <?php echo wp_nonce_field( 'wpaip_debug', '_wpnonce', true, false ); ?>
        <label for="nk">Nova Chave de Licença</label>
        <input type="text" id="nk" name="new_key" placeholder="WPAIP-XXXX-XXXX-XXXX" autocomplete="off" spellcheck="false">
        <button type="submit" name="action_save" class="btn btn-primary">💾 Salvar</button>
    </form>
</div>

<!-- Instruções -->
<div class="card">
    <h2>📋 Passos para resolver</h2>
    <ol>
        <li>Copiar a chave atual acima</li>
        <li>Acessar o painel de licenças:<br>
            <a href="<?php echo esc_url( rtrim( $server_url, '/' ) . '/index.php' ); ?>" target="_blank">
                <?php echo esc_html( rtrim( $server_url, '/' ) . '/index.php' ); ?>
            </a>
        </li>
        <li>Criar licença com essa chave exata + status <strong style="color:#4ade80">ACTIVE</strong></li>
        <li><strong style="color:#f87171">Apagar este arquivo do servidor após concluir</strong></li>
    </ol>
</div>
</div>
</body>
</html>
