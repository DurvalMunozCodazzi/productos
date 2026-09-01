<?php
/**
 * Página de categoría/subcategoría (ej. /categoria/ordenadores/, /categoria/notebook/) —
 * página completa propia (no depende del theme/Divi).
 *
 * 2 niveles de navegación por botón: Categoría → Subcategoría → productos.
 * - Si el término actual tiene hijos (subcategorías): se muestran los botones de subcategoría.
 * - Si no tiene hijos (ya se está en una subcategoría, o la categoría no tiene ninguna): se
 *   muestra directo la grilla de productos. La marca de cada producto se sigue viendo en su
 *   ficha y en el buscador, pero ya no es un paso de navegación aparte.
 */
$categoria = get_queried_object();

$hijos = get_terms([
    'taxonomy' => 'categoria_producto',
    'parent' => $categoria->term_id,
    'hide_empty' => false,
]);
$tiene_subcategorias = !is_wp_error($hijos) && !empty($hijos);

$ancestro = CLC_Post_Type::categoria_raiz($categoria);

CLC_Layout::abrir_pagina($categoria->name);
?>
    <main class="clc-archivo-categoria">

        <nav style="font-size:13px;color:var(--dim);margin-bottom:20px;">
            <a href="<?php echo esc_url(home_url('/catalogo/')); ?>">Catálogo</a>
            <?php if ($categoria->term_id !== $ancestro->term_id): ?>
                &nbsp;/&nbsp;<a href="<?php echo esc_url(get_term_link($ancestro)); ?>"><?php echo esc_html($ancestro->name); ?></a>
            <?php endif; ?>
            &nbsp;/&nbsp;<?php echo esc_html($categoria->name); ?>
        </nav>

        <?php echo do_shortcode('[clc_franja_categorias categoria_actual="' . esc_attr($ancestro->slug) . '"]'); ?>

        <?php if ($tiene_subcategorias): ?>
            <h1><?php echo esc_html($categoria->name); ?></h1>
            <p style="color:var(--dim);">Elegí un tipo de producto.</p>
            <?php echo do_shortcode('[clc_subcategorias categoria="' . esc_attr($categoria->slug) . '"]'); ?>
        <?php else: ?>
            <h1><?php echo esc_html($categoria->name); ?></h1>
            <p style="color:var(--dim);">Modelos disponibles.</p>
            <?php echo do_shortcode('[clc_articulos categoria="' . esc_attr($categoria->slug) . '"]'); ?>
        <?php endif; ?>

    </main>
<?php
CLC_Layout::cerrar_pagina();
