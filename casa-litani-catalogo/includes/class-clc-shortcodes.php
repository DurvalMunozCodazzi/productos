<?php
if (!defined('ABSPATH')) exit;

class CLC_Shortcodes {

    public static function init() {
        add_shortcode('clc_categorias', [__CLASS__, 'shortcode_categorias']);
        add_shortcode('clc_marcas', [__CLASS__, 'shortcode_marcas']);
        add_shortcode('clc_articulos', [__CLASS__, 'shortcode_articulos']);
        add_shortcode('clc_boton_whatsapp', [__CLASS__, 'shortcode_boton_whatsapp']);
        add_shortcode('clc_boton_compartir', [__CLASS__, 'shortcode_boton_compartir']);
        add_shortcode('clc_franja_categorias', [__CLASS__, 'shortcode_franja_categorias']);
        add_shortcode('clc_credito', [__CLASS__, 'shortcode_credito']);
        add_shortcode('clc_novedades', [__CLASS__, 'shortcode_novedades']);
        add_shortcode('clc_subcategorias', [__CLASS__, 'shortcode_subcategorias']);
    }

    /** [clc_subcategorias categoria="ordenadores"] — grilla de subcategorías (nivel 2) de una categoría, con conteo. */
    public static function shortcode_subcategorias($atts) {
        $atts = shortcode_atts(['categoria' => ''], $atts);
        if (empty($atts['categoria'])) {
            return '';
        }
        $padre = get_term_by('slug', $atts['categoria'], 'categoria_producto');
        if (!$padre) {
            return '';
        }
        $hijos = get_terms(['taxonomy' => 'categoria_producto', 'parent' => $padre->term_id, 'hide_empty' => false]);
        if (empty($hijos) || is_wp_error($hijos)) {
            return '';
        }

        ob_start();
        echo '<div class="clc-grid clc-grid-categorias">';
        foreach ($hijos as $hijo) {
            $cantidad = new WP_Query([
                'post_type' => 'articulo',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'tax_query' => [['taxonomy' => 'categoria_producto', 'field' => 'term_id', 'terms' => $hijo->term_id]],
            ]);
            echo '<a class="clc-card clc-card-marca" href="' . esc_url(get_term_link($hijo)) . '">';
            echo '<span class="clc-card-titulo">' . esc_html($hijo->name) . '</span>';
            echo '<span style="display:block;font-size:11.5px;color:var(--dim,#999);margin-top:4px;">' . (int) $cantidad->found_posts . ' modelos</span>';
            echo '</a>';
        }
        echo '</div>';
        return ob_get_clean();
    }

    /** [clc_novedades limite="8"] — últimos artículos cargados, para destacar lo nuevo sin tocar nada a mano. */
    public static function shortcode_novedades($atts) {
        $atts = shortcode_atts(['limite' => 8], $atts);

        $query = new WP_Query([
            'post_type' => 'articulo',
            'posts_per_page' => (int) $atts['limite'],
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => [['key' => '_clc_estado', 'value' => 'discontinuado', 'compare' => '!=']],
        ]);

        if (!$query->have_posts()) {
            return '';
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
        $terms = get_terms(['taxonomy' => 'categoria_producto', 'hide_empty' => false, 'parent' => 0]);
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
        $terms = get_terms(['taxonomy' => 'categoria_producto', 'hide_empty' => false, 'parent' => 0]);
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

    /**
     * [clc_boton_compartir] — para que el cliente le reenvíe el producto a un comprador
     * (no va a un número fijo, abre el selector de contactos del celular vía Web Share API,
     * o WhatsApp genérico si el navegador no soporta compartir nativo).
     */
    public static function shortcode_boton_compartir($atts) {
        $atts = shortcode_atts(['post_id' => get_the_ID()], $atts);
        $post_id = (int) $atts['post_id'];
        $nombre = get_the_title($post_id);
        $url = get_permalink($post_id);
        $texto = 'Mirá este producto en Casa Litani: "' . $nombre . '"';
        $link_generico = 'https://wa.me/?text=' . rawurlencode($texto . ' - ' . $url);

        ob_start();
        ?>
        <a class="clc-btn-compartir btn2 btn-ol" href="<?php echo esc_url($link_generico); ?>" target="_blank" rel="noopener"
           data-compartir-texto="<?php echo esc_attr($texto); ?>" data-compartir-url="<?php echo esc_url($url); ?>">
            Compartir
        </a>
        <script>
        (function () {
            var btns = document.querySelectorAll('.clc-btn-compartir[data-compartir-url="<?php echo esc_js($url); ?>"]');
            btns.forEach(function (btn) {
                if (!navigator.share) return;
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    navigator.share({
                        title: 'Casa Litani',
                        text: btn.getAttribute('data-compartir-texto'),
                        url: btn.getAttribute('data-compartir-url'),
                    }).catch(function () {});
                });
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
