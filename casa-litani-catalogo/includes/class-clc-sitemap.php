<?php
if (!defined('ABSPATH')) exit;

/**
 * Sitemap XML propio del catálogo (Google no indexaba bien los productos porque
 * dependía del sitemap genérico del theme). Vive en /catalogo-sitemap.xml e incluye
 * la página de catálogo, cada categoría y cada artículo publicado.
 */
class CLC_Sitemap {

    public static function init() {
        add_action('init', [__CLASS__, 'registrar_regla']);
        add_filter('query_vars', [__CLASS__, 'registrar_query_var']);
        add_action('template_redirect', [__CLASS__, 'imprimir_sitemap']);
        add_filter('robots_txt', [__CLASS__, 'agregar_a_robots'], 10, 2);
    }

    /** Referencia el sitemap del catálogo en /robots.txt para que Google lo encuentre solo. */
    public static function agregar_a_robots($salida, $public) {
        if ('1' !== (string) $public) {
            return $salida;
        }
        return rtrim($salida) . "\n\nSitemap: " . home_url('/catalogo-sitemap.xml') . "\n";
    }

    public static function registrar_regla() {
        add_rewrite_rule('^catalogo-sitemap\.xml$', 'index.php?clc_sitemap=1', 'top');
    }

    public static function registrar_query_var($vars) {
        $vars[] = 'clc_sitemap';
        return $vars;
    }

    public static function imprimir_sitemap() {
        if (!get_query_var('clc_sitemap')) {
            return;
        }

        header('Content-Type: application/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $imprimir = function ($url, $modificado = '') {
            echo '<url><loc>' . esc_url($url) . '</loc>';
            if ($modificado) {
                echo '<lastmod>' . esc_html($modificado) . '</lastmod>';
            }
            echo '</url>' . "\n";
        };

        $imprimir(home_url('/catalogo/'));

        $categorias = get_terms(['taxonomy' => 'categoria_producto', 'hide_empty' => false]);
        if (!is_wp_error($categorias)) {
            foreach ($categorias as $categoria) {
                $imprimir(get_term_link($categoria));
            }
        }

        $articulos = get_posts([
            'post_type' => 'articulo',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ]);
        foreach ($articulos as $articulo) {
            $imprimir(get_permalink($articulo), get_the_modified_date('c', $articulo));
        }

        echo '</urlset>';
        exit;
    }
}
