<?php
if (!defined('ABSPATH')) exit;

class CLC_Shortcodes {

    public static function init() {
        add_shortcode('clc_categorias', [__CLASS__, 'shortcode_categorias']);
        add_shortcode('clc_marcas', [__CLASS__, 'shortcode_marcas']);
        add_shortcode('clc_articulos', [__CLASS__, 'shortcode_articulos']);
        add_shortcode('clc_boton_whatsapp', [__CLASS__, 'shortcode_boton_whatsapp']);
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
        return '<a class="clc-btn-whatsapp" href="' . esc_url($link) . '" target="_blank" rel="noopener">Consultar precio por WhatsApp</a>';
    }
}
