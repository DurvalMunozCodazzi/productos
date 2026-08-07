<?php
if (!defined('ABSPATH')) exit;

class CLC_Shortcodes {

    public static function init() {
        add_shortcode('clc_categorias', [__CLASS__, 'shortcode_categorias']);
        add_shortcode('clc_marcas', [__CLASS__, 'shortcode_marcas']);
        add_shortcode('clc_articulos', [__CLASS__, 'shortcode_articulos']);
        add_shortcode('clc_boton_whatsapp', [__CLASS__, 'shortcode_boton_whatsapp']);
        add_shortcode('clc_franja_categorias', [__CLASS__, 'shortcode_franja_categorias']);
        add_shortcode('clc_credito', [__CLASS__, 'shortcode_credito']);
        add_shortcode('clc_boton_inicio', [__CLASS__, 'shortcode_boton_inicio']);
    }

    /** [clc_boton_inicio] — link "Inicio" para volver a la landing, mismo estilo/colores que sus otras páginas */
    public static function shortcode_boton_inicio() {
        return '<div style="margin-bottom:14px;">'
            . '<a href="' . esc_url(home_url('/')) . '" style="color:#71585d;font-size:.9rem;font-weight:600;text-decoration:none;">'
            . '&larr; Inicio'
            . '</a></div>';
    }

    /** [clc_credito] — misma línea de autoría que en el pie de la landing, con el logo de Web Sobre Ruedas */
    public static function shortcode_credito() {
        $logo = CLC_URL . 'assets/img/wsr-logo.png';
        return '<div style="width:100%;margin-top:20px;padding-top:20px;border-top:1px solid #f0dbe0;text-align:center;">'
            . '<a href="https://websobreruedas.com" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:8px;color:#71585d;font-size:.78rem;line-height:1;">'
            . '<img src="' . esc_url($logo) . '" alt="Web Sobre Ruedas" style="width:18px;height:18px;object-fit:contain;flex-shrink:0;display:block;">'
            . 'Hosting y Desarrollo Web: Web Sobre Ruedas by Durval Muñoz Codazzi'
            . '</a></div>';
    }

    /**
     * [clc_franja_categorias categoria_actual="celulares"]
     * Franja fija de accesos rápidos a categorías. Se usa en todas las pantallas del catálogo
     * (buscador, marca, artículo) para no perder nunca el acceso al resto de las categorías.
     */
    public static function shortcode_franja_categorias($atts) {
        $atts = shortcode_atts(['categoria_actual' => ''], $atts);
        $terms = get_terms(['taxonomy' => 'categoria_producto', 'hide_empty' => false]);
        if (empty($terms) || is_wp_error($terms)) {
            return '';
        }
        ob_start();
        echo '<div class="clc-cat-strip">';
        foreach ($terms as $term) {
            $activo = ($term->slug === $atts['categoria_actual']) ? ' clc-cat-chip-activo' : '';
            echo '<a class="clc-cat-chip' . $activo . '" href="' . esc_url(get_term_link($term)) . '">';
            echo esc_html($term->name);
            echo '</a>';
        }
        echo '</div>';
        return ob_get_clean();
    }

    /** [clc_categorias] — grilla de categorías (nivel 1 de navegación) */
    public static function shortcode_categorias() {
        $terms = get_terms(['taxonomy' => 'categoria_producto', 'hide_empty' => false]);
        if (empty($terms) || is_wp_error($terms)) {
            return '<p>No hay categorías cargadas todavía.</p>';
        }
        ob_start();
        echo '<div class="clc-grid clc-grid-categorias">';
        foreach ($terms as $term) {
            $img = get_term_meta($term->term_id, 'clc_imagen', true);
            $link = get_term_link($term);
            echo '<a class="clc-card" href="' . esc_url($link) . '">';
            if ($img) {
                echo '<img src="' . esc_url($img) . '" alt="' . esc_attr($term->name) . '">';
            }
            echo '<span class="clc-card-titulo">' . esc_html($term->name) . '</span>';
            echo '</a>';
        }
        echo '</div>';
        return ob_get_clean();
    }

    /** [clc_marcas categoria="celulares"] — grilla de marcas dentro de una categoría */
    public static function shortcode_marcas($atts) {
        $atts = shortcode_atts(['categoria' => ''], $atts);
        if (empty($atts['categoria'])) {
            return '';
        }

        // Traemos las marcas que efectivamente tienen artículos en esta categoría
        $articulos = get_posts([
            'post_type' => 'articulo',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'tax_query' => [[
                'taxonomy' => 'categoria_producto',
                'field' => 'slug',
                'terms' => $atts['categoria'],
            ]],
        ]);

        if (empty($articulos)) {
            return '<p>No hay marcas cargadas en esta categoría todavía.</p>';
        }

        $marca_ids = [];
        foreach ($articulos as $post_id) {
            $marcas = wp_get_post_terms($post_id, 'marca_producto');
            foreach ($marcas as $m) {
                $marca_ids[$m->term_id] = $m;
            }
        }

        ob_start();
        echo '<div class="clc-grid clc-grid-marcas">';
        foreach ($marca_ids as $marca) {
            $link = add_query_arg('marca', $marca->slug, get_term_link($atts['categoria'], 'categoria_producto'));
            echo '<a class="clc-card clc-card-marca" href="' . esc_url($link) . '">';
            echo '<span class="clc-card-titulo">' . esc_html($marca->name) . '</span>';
            echo '</a>';
        }
        echo '</div>';
        return ob_get_clean();
    }

    /** [clc_articulos categoria="celulares" marca="samsung"] — grilla de artículos filtrados */
    public static function shortcode_articulos($atts) {
        $atts = shortcode_atts(['categoria' => '', 'marca' => ''], $atts);

        $tax_query = ['relation' => 'AND'];
        if (!empty($atts['categoria'])) {
            $tax_query[] = ['taxonomy' => 'categoria_producto', 'field' => 'slug', 'terms' => $atts['categoria']];
        }
        if (!empty($atts['marca'])) {
            $tax_query[] = ['taxonomy' => 'marca_producto', 'field' => 'slug', 'terms' => $atts['marca']];
        }

        $query = new WP_Query([
            'post_type' => 'articulo',
            'posts_per_page' => 60,
            'meta_query' => [['key' => '_clc_estado', 'value' => 'discontinuado', 'compare' => '!=']],
            'tax_query' => $tax_query,
        ]);

        if (!$query->have_posts()) {
            return '<p>No hay artículos cargados con este filtro todavía.</p>';
        }

        ob_start();
        echo '<div class="clc-grid clc-grid-articulos">';
        while ($query->have_posts()) {
            $query->the_post();
            echo '<a class="clc-card clc-card-articulo" href="' . esc_url(get_permalink()) . '">';
            echo get_the_post_thumbnail(get_the_ID(), 'medium');
            echo '<span class="clc-card-titulo">' . esc_html(get_the_title()) . '</span>';
            echo '</a>';
        }
        echo '</div>';
        wp_reset_postdata();
        return ob_get_clean();
    }

    /** [clc_boton_whatsapp] — usado dentro del template de ficha de artículo (single-articulo.php) */
    public static function shortcode_boton_whatsapp($atts) {
        $atts = shortcode_atts(['post_id' => get_the_ID()], $atts);
        $link = CLC_Whatsapp::link_consulta($atts['post_id']);
        if (empty($link)) {
            return '';
        }
        return '<a class="clc-btn-whatsapp" href="' . esc_url($link) . '" target="_blank" rel="noopener">Consultar</a>';
    }
}
