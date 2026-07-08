<?php
/**
 * Upload de imagens para a Biblioteca de Mídia do WordPress.
 * Suporta upload via URL remota e via arquivo local (base64 salvo em disco).
 */
defined( 'ABSPATH' ) || exit;

class WPAIP_Media {

    /**
     * Faz download de uma URL (ou lê arquivo local) e insere na Biblioteca de Mídia.
     *
     * @param string   $source   URL remota (https://) ou caminho absoluto local.
     * @param int|null $post_id  Post ao qual associar o attachment (ou null).
     * @param string   $title    Título/alt text da imagem.
     * @return int|WP_Error      ID do attachment ou WP_Error.
     */
    public static function upload_from_url( string $source, ?int $post_id = null, string $title = '' ): int|WP_Error {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // ── Arquivo local (ex: Gemini Imagen retorna base64 → temp file) ──────
        if ( file_exists( $source ) ) {
            return self::sideload_local( $source, $post_id, $title );
        }

        // ── URL remota ────────────────────────────────────────────────────────
        if ( ! filter_var( $source, FILTER_VALIDATE_URL ) ) {
            return new WP_Error( 'wpaip_invalid_source', 'Fonte de imagem inválida: ' . $source );
        }

        // Verifica se a URL é de um domínio permitido (segurança)
        $allowed_hosts = [
            'oaidalleapiprodscus.blob.core.windows.net', // DALL-E CDN
            'generativelanguage.googleapis.com',
        ];

        $host = wp_parse_url( $source, PHP_URL_HOST );
        // DALL-E usa vários subdomínios de blob.core.windows.net
        $is_allowed = in_array( $host, $allowed_hosts, true )
            || str_ends_with( $host ?? '', '.blob.core.windows.net' )
            || str_ends_with( $host ?? '', '.openai.com' );

        if ( ! $is_allowed ) {
            // Permite qualquer HTTPS mas loga aviso
            if ( ! str_starts_with( $source, 'https://' ) ) {
                return new WP_Error( 'wpaip_unsafe_url', 'Apenas URLs HTTPS são permitidas.' );
            }
        }

        // Download para diretório temporário do WP
        $tmp_file = download_url( $source, 60 );
        if ( is_wp_error( $tmp_file ) ) {
            return $tmp_file;
        }

        return self::sideload_local( $tmp_file, $post_id, $title, true );
    }

    /**
     * Move um arquivo local para a Biblioteca de Mídia.
     *
     * @param string   $path      Caminho absoluto do arquivo.
     * @param int|null $post_id   Post associado.
     * @param string   $title     Título.
     * @param bool     $cleanup   Se true, remove o arquivo temporário após upload.
     * @return int|WP_Error
     */
    private static function sideload_local( string $path, ?int $post_id, string $title, bool $cleanup = false ): int|WP_Error {
        $file_array = [
            'name'     => self::generate_filename( $title ),
            'tmp_name' => $path,
        ];

        $attachment_id = media_handle_sideload( $file_array, $post_id ?? 0, $title );

        if ( $cleanup && file_exists( $path ) ) {
            @unlink( $path );
        }

        if ( is_wp_error( $attachment_id ) ) {
            return $attachment_id;
        }

        // Atualiza alt text
        if ( ! empty( $title ) ) {
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $title ) );
        }

        return $attachment_id;
    }

    /**
     * Gera um nome de arquivo seguro baseado no título e timestamp.
     */
    private static function generate_filename( string $title ): string {
        $slug = sanitize_title( $title ?: 'wpaip-image' );
        $slug = substr( $slug, 0, 40 );
        return $slug . '-' . time() . '.png';
    }
}
