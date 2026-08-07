<?php
/**
 * Página de categoría (ej. /categoria/celulares/) — página completa propia (no depende del theme/Divi).
 * Sin parámetro ?marca= → muestra las marcas de la categoría.
 * Con ?marca=samsung → muestra los artículos de esa marca dentro de la categoría.
 */
$categoria = get_queried_object();
$marca_slug = isset($_GET['marca']) ? sanitize_title($_GET['marca']) : '';
$marca_term = $marca_slug ? get_term_by('slug', $marca_slug, 'marca_producto') : null;

CLC_Layout::abrir_pagina($marca_term ? $marca_term->name : $categoria->name);
?>
    <main class="clc-archivo-categoria">

        <nav style="font-size:13px;color:var(--dim);margin-bottom:20px;">
            <a href="<?php echo esc_url(home_url('/catalogo/')); ?>">Catálogo</a>
            <?php if ($marca_term): ?>
                &nbsp;/&nbsp;<a href="<?php echo esc_url(get_term_link($categoria)); ?>"><?php echo esc_html($categoria->name); ?></a>
                &nbsp;/&nbsp;<?php echo esc_html($marca_term->name); ?>
            <?php else: ?>
                &nbsp;/&nbsp;<?php echo esc_html($categoria->name); ?>
            <?php endif; ?>
        </nav>

        <?php echo do_shortcode('[clc_franja_categorias categoria_actual="' . esc_attr($categoria->slug) . '"]'); ?>

        <?php if ($marca_term): ?>
            <h1><?php echo esc_html($marca_term->name); ?></h1>
            <p style="color:var(--dim);">Modelos disponibles de <?php echo esc_html($marca_term->name); ?> en <?php echo esc_html($categoria->name); ?>.</p>
            <?php echo do_shortcode('[clc_articulos categoria="' . esc_attr($categoria->slug) . '" marca="' . esc_attr($marca_term->slug) . '"]'); ?>
        <?php else: ?>
            <h1><?php echo esc_html($categoria->name); ?></h1>
            <p style="color:var(--dim);">Elegí una marca para ver los modelos disponibles.</p>
            <?php echo do_shortcode('[clc_marcas categoria="' . esc_attr($categoria->slug) . '"]'); ?>
        <?php endif; ?>

    </main>
<?php
CLC_Layout::cerrar_pagina();
