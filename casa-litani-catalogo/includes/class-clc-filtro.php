<?php
if (!defined('ABSPATH')) exit;

/**
 * Barra de filtros en vivo: Tipo de producto (Categoría) + Marca + Buscador de texto.
 * Se apoya en la REST API de WordPress (sin recargar página).
 */
class CLC_Filtro {

    public static function init() {
        add_shortcode('clc_filtro_catalogo', [__CLASS__, 'shortcode']);
        add_action('rest_api_init', [__CLASS__, 'registrar_campos_rest']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue']);
    }

    public static function registrar_campos_rest() {
        register_rest_field('articulo', 'clc_datos', [
            'get_callback' => function ($post) {
                $post_id = $post['id'];
                $categorias = wp_get_post_terms($post_id, 'categoria_producto', ['fields' => 'names']);
                $marcas = wp_get_post_terms($post_id, 'marca_producto', ['fields' => 'names']);
                return [
                    'imagen' => get_the_post_thumbnail_url($post_id, 'medium') ?: '',
                    'categoria' => $categorias[0] ?? '',
                    'marca' => $marcas[0] ?? '',
                    'sku' => get_post_meta($post_id, '_clc_sku', true),
                    'permalink' => get_permalink($post_id),
                ];
            },
            'schema' => null,
        ]);
    }

    public static function enqueue() {
        wp_enqueue_script('clc-filtro', CLC_URL . 'assets/js/filtro.js', [], CLC_VERSION, true);
        wp_localize_script('clc-filtro', 'clcFiltro', [
            'restUrl' => esc_url_raw(rest_url('wp/v2/')),
        ]);
    }

    public static function shortcode() {
        ob_start();
        ?>
        <div class="clc-filtro-barra">
            <select id="clc-filtro-categoria">
                <option value="">Tipo de producto: Todos</option>
            </select>
            <select id="clc-filtro-marca">
                <option value="">Marca: Todas</option>
            </select>
            <input type="text" id="clc-filtro-buscar" placeholder="Buscar por nombre o modelo…">
        </div>
        <div id="clc-filtro-resultados" class="clc-grid clc-grid-articulos"></div>
        <p id="clc-filtro-estado" class="clc-filtro-estado"></p>
        <?php
        return ob_get_clean();
    }
}
