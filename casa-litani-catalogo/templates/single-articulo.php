<?php
/**
 * Template de ficha de artículo. WordPress lo usa automáticamente si se copia
 * a la raíz del theme activo, o el plugin lo sirve vía template_include (ver includes si se agrega esa clase).
 */
get_header();
?>

<main class="clc-single-articulo" style="max-width:800px;margin:40px auto;padding:0 16px;">
    <?php while (have_posts()): the_post(); ?>
        <h1><?php the_title(); ?></h1>

        <?php $marcas = get_the_terms(get_the_ID(), 'marca_producto'); ?>
        <?php $categorias = get_the_terms(get_the_ID(), 'categoria_producto'); ?>
        <p style="color:#666;">
            <?php if ($categorias && !is_wp_error($categorias)): ?>
                <?php echo esc_html($categorias[0]->name); ?>
            <?php endif; ?>
            <?php if ($marcas && !is_wp_error($marcas)): ?>
                &middot; <?php echo esc_html($marcas[0]->name); ?>
            <?php endif; ?>
        </p>

        <?php if (has_post_thumbnail()): ?>
            <?php the_post_thumbnail('large', ['style' => 'width:100%;border-radius:10px;']); ?>
        <?php endif; ?>

        <div class="clc-descripcion" style="margin-top:20px;">
            <?php the_content(); ?>
        </div>

        <?php echo do_shortcode('[clc_boton_whatsapp]'); ?>
    <?php endwhile; ?>
</main>

<?php get_footer(); ?>
