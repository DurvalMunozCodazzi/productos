<?php
/**
 * Template de ficha de artículo. WordPress lo usa automáticamente si se copia
 * a la raíz del theme activo, o el plugin lo sirve vía template_include (ver includes si se agrega esa clase).
 */
get_header();
?>

<main class="clc-single-articulo" style="max-width:800px;margin:40px auto;padding:0 16px;">
    <?php echo do_shortcode('[clc_boton_inicio]'); ?>
    <?php while (have_posts()): the_post(); ?>
        <?php $marcas = get_the_terms(get_the_ID(), 'marca_producto'); ?>
        <?php $categorias = get_the_terms(get_the_ID(), 'categoria_producto'); ?>

        <?php echo do_shortcode('[clc_franja_categorias categoria_actual="' . (($categorias && !is_wp_error($categorias)) ? esc_attr($categorias[0]->slug) : '') . '"]'); ?>

        <h1><?php the_title(); ?></h1>
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
            <?php
            $fotografo = get_post_meta(get_the_ID(), '_clc_pexels_fotografo', true);
            $fotografo_url = get_post_meta(get_the_ID(), '_clc_pexels_fotografo_url', true);
            if ($fotografo):
            ?>
                <p style="font-size:11px;color:#999;margin-top:4px;">
                    Foto: <a href="<?php echo esc_url($fotografo_url ?: 'https://www.pexels.com'); ?>" target="_blank" rel="noopener"><?php echo esc_html($fotografo); ?></a> en Pexels
                </p>
            <?php endif; ?>
        <?php endif; ?>

        <div class="clc-descripcion" style="margin-top:20px;">
            <?php the_content(); ?>
        </div>

        <?php echo do_shortcode('[clc_boton_whatsapp]'); ?>
    <?php endwhile; ?>
    <?php echo do_shortcode('[clc_credito]'); ?>
</main>

<?php get_footer(); ?>
