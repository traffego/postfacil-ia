<?php
/**
 * Página de bloqueio exibida a usuários sem acesso (não-pagantes).
 */
defined( 'ABSPATH' ) || exit;
// $payment_link já está disponível via WPAIP_Paywall::render_blocked_page()
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php _e( 'Acesso Restrito — AI Publisher', 'wp-ai-publisher' ); ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', sans-serif;
            background: #0f0c1a;
            overflow: hidden;
            position: relative;
        }

        /* Animated gradient background */
        body::before {
            content: '';
            position: fixed;
            inset: -50%;
            background: radial-gradient(ellipse at 20% 50%, rgba(124, 58, 237, 0.25) 0%, transparent 55%),
                        radial-gradient(ellipse at 80% 20%, rgba(59, 130, 246, 0.2) 0%, transparent 50%),
                        radial-gradient(ellipse at 60% 80%, rgba(236, 72, 153, 0.15) 0%, transparent 50%);
            animation: bgDrift 12s ease-in-out infinite alternate;
            z-index: 0;
        }

        @keyframes bgDrift {
            0%   { transform: translate(0, 0) scale(1); }
            50%  { transform: translate(-3%, 2%) scale(1.05); }
            100% { transform: translate(3%, -2%) scale(1.02); }
        }

        /* Floating orbs */
        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(60px);
            opacity: 0.35;
            animation: float linear infinite;
            z-index: 0;
            pointer-events: none;
        }
        .orb-1 { width: 300px; height: 300px; background: #7c3aed; top: 10%; left: 5%;  animation-duration: 18s; }
        .orb-2 { width: 200px; height: 200px; background: #3b82f6; top: 60%; right: 8%; animation-duration: 22s; animation-delay: -6s; }
        .orb-3 { width: 250px; height: 250px; background: #ec4899; bottom: 5%; left: 40%; animation-duration: 15s; animation-delay: -3s; }

        @keyframes float {
            0%   { transform: translateY(0) translateX(0); }
            25%  { transform: translateY(-30px) translateX(15px); }
            50%  { transform: translateY(-15px) translateX(30px); }
            75%  { transform: translateY(20px) translateX(-10px); }
            100% { transform: translateY(0) translateX(0); }
        }

        /* Card principal */
        .paywall-card {
            position: relative;
            z-index: 10;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border-radius: 24px;
            padding: 56px 48px;
            max-width: 520px;
            width: 90%;
            text-align: center;
            box-shadow:
                0 0 0 1px rgba(124, 58, 237, 0.15),
                0 32px 64px rgba(0, 0, 0, 0.5),
                inset 0 1px 0 rgba(255, 255, 255, 0.08);
            animation: cardIn 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) both;
        }

        @keyframes cardIn {
            from { opacity: 0; transform: translateY(30px) scale(0.95); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* Ícone cadeado */
        .lock-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #7c3aed, #ec4899);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 28px;
            font-size: 36px;
            box-shadow: 0 0 40px rgba(124, 58, 237, 0.5);
            animation: pulse 3s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 40px rgba(124, 58, 237, 0.5); }
            50%       { box-shadow: 0 0 60px rgba(236, 72, 153, 0.7), 0 0 80px rgba(124, 58, 237, 0.3); }
        }

        /* Badge */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(124, 58, 237, 0.15);
            border: 1px solid rgba(124, 58, 237, 0.35);
            color: #c4b5fd;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 4px 12px;
            border-radius: 100px;
            margin-bottom: 20px;
        }

        /* Título */
        .paywall-title {
            font-size: 28px;
            font-weight: 800;
            color: #f8fafc;
            line-height: 1.2;
            margin-bottom: 12px;
            background: linear-gradient(135deg, #f8fafc 0%, #c4b5fd 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .paywall-subtitle {
            font-size: 15px;
            color: rgba(248, 250, 252, 0.55);
            line-height: 1.6;
            margin-bottom: 36px;
        }

        /* Features list */
        .features {
            list-style: none;
            margin-bottom: 36px;
            text-align: left;
        }

        .features li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            color: rgba(248, 250, 252, 0.75);
            font-size: 14px;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .features li:last-child { border-bottom: none; }

        .features li .check {
            flex-shrink: 0;
            width: 20px;
            height: 20px;
            background: linear-gradient(135deg, #7c3aed, #ec4899);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            margin-top: 1px;
        }

        /* Botão CTA */
        .cta-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 16px 32px;
            background: linear-gradient(135deg, #7c3aed 0%, #ec4899 100%);
            color: #fff;
            font-size: 16px;
            font-weight: 700;
            border-radius: 12px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s;
            box-shadow: 0 8px 30px rgba(124, 58, 237, 0.45);
            letter-spacing: 0.01em;
        }

        .cta-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 40px rgba(124, 58, 237, 0.6);
            opacity: 0.95;
            color: #fff;
            text-decoration: none;
        }

        .cta-btn:active { transform: translateY(0); }

        /* Link voltar */
        .back-link {
            display: block;
            margin-top: 20px;
            font-size: 13px;
            color: rgba(248, 250, 252, 0.4);
            text-decoration: none;
            transition: color 0.2s;
        }

        .back-link:hover {
            color: rgba(248, 250, 252, 0.75);
            text-decoration: none;
        }

        /* User info */
        .user-info {
            margin-bottom: 24px;
            padding: 10px 16px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.07);
            border-radius: 10px;
            font-size: 13px;
            color: rgba(248, 250, 252, 0.5);
        }

        .user-info strong { color: rgba(248, 250, 252, 0.85); }
    </style>
</head>
<body>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <div class="paywall-card">

        <div class="lock-icon">🔒</div>

        <div class="badge">
            ✦ Acesso Premium
        </div>

        <h1 class="paywall-title">Conteúdo exclusivo<br>para assinantes</h1>

        <p class="paywall-subtitle">
            O <strong style="color: #c4b5fd;">AI Publisher</strong> é uma ferramenta premium.
            Ative sua assinatura para criar e agendar posts com IA ilimitada.
        </p>

        <?php
        $current_user = wp_get_current_user();
        if ( $current_user->ID ) :
        ?>
        <div class="user-info">
            Logado como <strong><?php echo esc_html( $current_user->user_email ); ?></strong>
        </div>
        <?php endif; ?>

        <ul class="features">
            <li>
                <span class="check">✓</span>
                Geração de posts completos com texto e imagens via IA
            </li>
            <li>
                <span class="check">✓</span>
                Agendamento automático e recorrente de publicações
            </li>
            <li>
                <span class="check">✓</span>
                Suporte a OpenAI, Gemini, Claude e DeepSeek
            </li>
            <li>
                <span class="check">✓</span>
                Imagens geradas por FLUX, DALL-E e Imagen 4
            </li>
        </ul>

        <?php if ( $payment_link && $payment_link !== '#' ) : ?>
            <a href="<?php echo $payment_link; ?>" class="cta-btn" target="_blank">
                ⚡ Assinar agora e liberar acesso
            </a>
        <?php else : ?>
            <a href="#" class="cta-btn" style="opacity:.5; cursor:not-allowed;" onclick="return false;">
                🔒 Assinatura não disponível no momento
            </a>
        <?php endif; ?>

        <a href="<?php echo esc_url( admin_url() ); ?>" class="back-link">
            ← Voltar ao painel
        </a>

    </div>
</body>
</html>
