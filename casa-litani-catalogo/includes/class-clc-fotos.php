<?php
if (!defined('ABSPATH')) exit;

/**
 * Búsqueda y captura automática de fotos por artículo — mismo patrón usado en ratatuin.com.ar
 * para asociar imágenes a cada receta, pero acá aplicado a productos por nombre/modelo.
 *
 * Usa Google Custom Search API (Búsqueda de Imágenes). Requiere API Key + CX configurados
 * en Artículos → Config. Fotos.
 */
class CLC_Fotos {

    public static function init() {
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_box']);
        add_action('wp_ajax_clc_buscar_fotos', [__CLASS__, 'ajax_buscar_fotos']);
        add_action('wp_ajax_clc_asignar_foto', [__CLASS__, 'ajax_asignar_foto']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
    }

    public static function add_meta_box() {
        add_meta_box('clc_buscar_foto', 'Foto del producto (búsqueda automática)', [__CLASS__, 'render_meta_box'], 'articulo', 'side', 'high');
    }

    public static function enqueue_admin_assets($hook) {
        global $post_type;
        if ($post_type !== 'articulo') {
            return;
        }
        wp_enqueue_script('clc-fotos-admin', CLC_URL . 'assets/js/fotos-admin.js', ['jquery'], CLC_VERSION, true);
        wp_localize_script('clc-fotos-admin', 'clcFotos', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('clc_fotos_nonce'),
        ]);
    }

    public static function render_meta_box($post) {
        $configurado = get_option(CLC_Settings::OPTION_API_KEY) && get_option(CLC_Settings::OPTION_CX);
        if (!$configurado) {
            echo '<p>Falta configurar la API de búsqueda. Andá a <a href="' . esc_url(admin_url('edit.php?post_type=articulo&page=clc-config-fotos')) . '">Config. Fotos</a>.</p>';
            return;
        }
        ?>
        <p><button type="button" class="button button-primary" id="clc-btn-buscar-foto" data-post-id="<?php echo esc_attr($post->ID); ?>">Buscar foto</button></p>
        <div id="clc-resultados-fotos" style="display:flex;flex-wrap:wrap;gap:6px;"></div>
        <p class="description">Busca por el título del artículo. Click en una miniatura para usarla como imagen destacada.</p>
        <?php
    }

    /** Devuelve hasta 8 resultados de imagen para el nombre del artículo (o un término custom). */
    public static function ajax_buscar_fotos() {
        check_ajax_referer('clc_fotos_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('No autorizado');
        }

        $query = sanitize_text_field($_POST['query'] ?? '');
        if (empty($query)) {
            wp_send_json_error('Falta el término de búsqueda');
        }

        $api_key = get_option(CLC_Settings::OPTION_API_KEY);
        $cx = get_option(CLC_Settings::OPTION_CX);

        $url = add_query_arg([
            'key' => $api_key,
            'cx' => $cx,
            'q' => $query,
            'searchType' => 'image',
            'num' => 8,
            'safe' => 'active',
        ], 'https://www.googleapis.com/customsearch/v1');

        $respuesta = wp_remote_get($url, ['timeout' => 15]);
        if (is_wp_error($respuesta)) {
            wp_send_json_error($respuesta->get_error_message());
        }

        $cuerpo = json_decode(wp_remote_retrieve_body($respuesta), true);
        if (empty($cuerpo['items'])) {
            wp_send_json_error('Sin resultados');
        }

        $resultados = array_map(function ($item) {
            return [
                'url' => $item['link'] ?? '',
                'miniatura' => $item['image']['thumbnailLink'] ?? ($item['link'] ?? ''),
            ];
        }, $cuerpo['items']);

        wp_send_json_success($resultados);
    }

    /** Descarga la imagen elegida, la sube a la Media Library y la asigna como destacada del artículo. */
    public static function ajax_asignar_foto() {
        check_ajax_referer('clc_fotos_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('No autorizado');
        }

        $post_id = (int) ($_POST['post_id'] ?? 0);
        $url_imagen = esc_url_raw($_POST['url_imagen'] ?? '');
        if (!$post_id || !$url_imagen) {
            wp_send_json_error('Datos incompletos');
        }

        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = download_url($url_imagen);
        if (is_wp_error($tmp)) {
            wp_send_json_error('No se pudo descargar la imagen');
        }

        $archivo = [
            'name' => 'producto-' . $post_id . '-' . time() . '.jpg',
            'tmp_name' => $tmp,
        ];

        $adjunto_id = media_handle_sideload($archivo, $post_id);
        if (is_wp_error($adjunto_id)) {
            @unlink($tmp);
            wp_send_json_error('No se pudo procesar la imagen');
        }

        set_post_thumbnail($post_id, $adjunto_id);
        update_post_meta($post_id, '_clc_estado', 'activo');

        wp_send_json_success(['adjunto_id' => $adjunto_id, 'url' => wp_get_attachment_url($adjunto_id)]);
    }
}
