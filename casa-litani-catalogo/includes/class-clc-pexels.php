<?php
if (!defined('ABSPATH')) exit;

/**
 * Fotos automáticas de artículos vía la API gratuita de Pexels — mismo mecanismo
 * usado en ratatuin.com.ar para las recetas: tandas por WP-Cron (para no pasarse
 * del límite de 200 pedidos/hora de Pexels) + carga manual/masiva desde el admin.
 * Guarda la foto como imagen destacada del Artículo y el crédito del fotógrafo
 * (obligatorio por los términos de uso de Pexels) en post meta.
 */
class CLC_Pexels {

    const POR_TANDA_CRON = 120;
    const POR_TANDA_MANUAL = 15;
    const POR_TANDA_AUTO = 9;

    public static function init() {
        add_action('clc_pexels_tanda_horaria', [__CLASS__, 'procesar_tanda']);
        add_action('wp', [__CLASS__, 'activar_cron']);
    }

    public static function activar_cron() {
        if (!wp_next_scheduled('clc_pexels_tanda_horaria')) {
            wp_schedule_event(time(), 'hourly', 'clc_pexels_tanda_horaria');
        }
    }

    public static function desactivar_cron() {
        wp_clear_scheduled_hook('clc_pexels_tanda_horaria');
    }

    /** Busca 1 foto en Pexels para un término (usa la primera coincidencia). */
    public static function buscar($query, $api_key, &$error = null) {
        $url = add_query_arg([
            'query' => rawurlencode($query),
            'per_page' => 1,
            'orientation' => 'landscape',
        ], 'https://api.pexels.com/v1/search');

        $respuesta = wp_remote_get($url, [
            'headers' => ['Authorization' => $api_key],
            'timeout' => 15,
        ]);

        if (is_wp_error($respuesta)) {
            $error = 'Error de conexión: ' . $respuesta->get_error_message();
            return null;
        }
        $codigo = wp_remote_retrieve_response_code($respuesta);
        if (200 !== $codigo) {
            $error = (401 === $codigo || 403 === $codigo)
                ? 'La API key de Pexels fue rechazada (código ' . $codigo . ').'
                : (429 === $codigo ? 'Se alcanzó el límite de pedidos por hora de Pexels.' : 'Pexels devolvió un error (código ' . $codigo . ').');
            return null;
        }

        $cuerpo = json_decode(wp_remote_retrieve_body($respuesta), true);
        if (empty($cuerpo['photos'][0])) {
            return null;
        }

        $foto = $cuerpo['photos'][0];
        return [
            'url' => $foto['src']['large'] ?? $foto['src']['medium'] ?? '',
            'fotografo' => $foto['photographer'] ?? '',
            'fotografo_url' => $foto['photographer_url'] ?? '',
        ];
    }

    /** Descarga la imagen y la deja cargada en la Media Library. */
    public static function sideload($url, $descripcion) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $temp = download_url($url);
        if (is_wp_error($temp)) {
            return null;
        }

        $archivo = [
            'name' => sanitize_file_name($descripcion . '.jpg'),
            'tmp_name' => $temp,
        ];

        $id = media_handle_sideload($archivo, 0, $descripcion);
        if (is_wp_error($id)) {
            @unlink($temp);
            return null;
        }
        return (int) $id;
    }

    /** Término de búsqueda: nombre del artículo + marca, para que Pexels traiga algo relevante. */
    private static function termino_busqueda($post_id) {
        $nombre = get_the_title($post_id);
        $marcas = wp_get_post_terms($post_id, 'marca_producto', ['fields' => 'names']);
        $marca = $marcas[0] ?? '';
        return trim($nombre . ' ' . $marca . ' producto');
    }

    /** Procesa una tanda de artículos sin foto. Devuelve un resumen para mostrar en el admin. */
    public static function procesar_tanda($limite = self::POR_TANDA_CRON) {
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        $resumen = ['ok' => false, 'error' => '', 'revisadas' => 0, 'cargadas' => 0, 'sin_match' => 0];

        $api_key = CLC_Settings::get_api_key();
        if ('' === $api_key) {
            $resumen['error'] = 'No hay API key de Pexels cargada en Config. Fotos.';
            update_option('clc_pexels_ultimo_resultado', $resumen);
            return $resumen;
        }

        $query = new WP_Query([
            'post_type' => 'articulo',
            'posts_per_page' => $limite,
            'post_status' => 'publish',
            'meta_query' => [['key' => '_thumbnail_id', 'compare' => 'NOT EXISTS']],
            'fields' => 'ids',
        ]);

        foreach ($query->posts as $post_id) {
            $resumen['revisadas']++;
            $error_busqueda = null;
            $encontrada = self::buscar(self::termino_busqueda($post_id), $api_key, $error_busqueda);

            if (!$encontrada || empty($encontrada['url'])) {
                $resumen['sin_match']++;
                if ($error_busqueda && '' === $resumen['error']) {
                    $resumen['error'] = $error_busqueda;
                }
                if ($error_busqueda && (strpos($error_busqueda, 'rechazada') !== false || strpos($error_busqueda, 'límite') !== false)) {
                    break;
                }
                continue;
            }

            $adjunto_id = self::sideload($encontrada['url'], get_the_title($post_id));
            if (!$adjunto_id) {
                $resumen['sin_match']++;
                continue;
            }

            set_post_thumbnail($post_id, $adjunto_id);
            update_post_meta($post_id, '_clc_pexels_fotografo', $encontrada['fotografo']);
            update_post_meta($post_id, '_clc_pexels_fotografo_url', $encontrada['fotografo_url']);
            update_post_meta($post_id, '_clc_estado', 'activo');
            $resumen['cargadas']++;
        }

        $resumen['ok'] = true;
        if (0 === $resumen['revisadas']) {
            $resumen['error'] = 'Todos los artículos ya tienen foto.';
        }
        update_option('clc_pexels_ultimo_resultado', $resumen);
        return $resumen;
    }
}
