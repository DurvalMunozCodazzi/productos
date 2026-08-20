<?php
/**
 * Página de categoría/subcategoría (ej. /categoria/ordenadores/, /categoria/notebook/) —
 * página completa propia (no depende del theme/Divi).
 *
 * Lógica de 3 niveles, con la subcategoría opcional:
 * - Si el término actual tiene hijos (subcategorías) y no se pidió marca: se muestran los
 *   botones de subcategoría (ej. Ordenadores → Notebook/Tablet/MacBook/iPad).
 * - Si no tiene hijos (categoría simple como Celulares, o ya se está en una subcategoría
 *   como Notebook) y no se pidió marca: se muestran los botones de marca, igual que antes.
 * - Con ?marca=samsung: se muestra la grilla de artículos.
 */
$categoria = get_queried_object();
$marca_slug = isset($_GET['marca']) ? sanitize_title($_GET['marca']) : '';
$marca_term = $marca_slug ? get_term_by('slug', $marca_slug, 'marca_producto') : null;

$hijos = get_terms([
    'taxonomy' => 'categoria_producto',
    'parent' => $categoria->term_id,
    'hide_empty' => false,
]);
$tiene_subcategorias = !is_wp_error($hijos) && !empty($hijos);

$ancestro = CLC_Post_Type::categoria_raiz($categoria);

CLC_Layout::abrir_pagina($marca_term ? $marca_term->name : $categoria->name);
?>
    <main class="clc-archivo-categoria">

        <nav style="font-size:13px;color:var(--dim);margin-bottom:20px;">
            <a href="<?php echo esc_url(home_url('/catalogo/')); ?>">Catálogo</a>
            <?php if ($categoria->term_id !== $ancestro->term_id): ?>
                &nbsp;/&nbsp;<a href="<?php echo esc_url(get_term_link($ancestro)); ?>"><?php echo esc_html($ancestro->name); ?></a>
            <?php endif; ?>
            <?php if ($marca_term): ?>
                &nbsp;/&nbsp;<a href="<?php echo esc_url(get_term_link($categoria)); ?>"><?php echo esc_html($categoria->name); ?></a>
                &nbsp;/&nbsp;<?php echo esc_html($marca_term->name); ?>
            <?php else: ?>
                &nbsp;/&nbsp;<?php echo esc_html($categoria->name); ?>
            <?php endif; ?>
        </nav>

        <?php echo do_shortcode('[clc_franja_categorias categoria_actual="' . esc_attr($ancestro->slug) . '"]'); ?>

        <?php if ($marca_term): ?>
            <h1><?php echo esc_html($marca_term->name); ?></h1>
            <p style="color:var(--dim);">Modelos disponibles de <?php echo esc_html($marca_term->name); ?> en <?php echo esc_html($categoria->name); ?>.</p>
            <?php echo do_shortcode('[clc_articulos categoria="' . esc_attr($categoria->slug) . '" marca="' . esc_attr($marca_term->slug) . '"]'); ?>
        <?php elseif ($tiene_subcategorias): ?>
            <h1><?php echo esc_html($categoria->name); ?></h1>
            <p style="color:var(--dim);">Elegí un tipo de producto.</p>
            <?php echo do_shortcode('[clc_subcategorias categoria="' . esc_attr($categoria->slug) . '"]'); ?>
        <?php else: ?>
            <h1><?php echo esc_html($categoria->name); ?></h1>
            <p style="color:var(--dim);">Elegí una marca para ver los modelos disponibles.</p>
            <?php echo do_shortcode('[clc_marcas categoria="' . esc_attr($categoria->slug) . '"]'); ?>
        <?php endif; ?>

    </main>
<?php
CLC_Layout::cerrar_pagina();
