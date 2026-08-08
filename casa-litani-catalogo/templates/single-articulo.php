<?php
/** Ficha de artículo — página completa propia (no depende del theme/Divi). */
$clc_post_id = get_queried_object_id();
CLC_Layout::abrir_pagina(
    get_the_title($clc_post_id),
    wp_strip_all_tags(get_post_field('post_content', $clc_post_id)),
    get_the_post_thumbnail_url($clc_post_id, 'large')
);
?>
    <main class="clc-single-articulo" style="max-width:800px;margin:0 auto;">
        <?php while (have_posts()): the_post(); ?>
            <?php $marcas = get_the_terms(get_the_ID(), 'marca_producto'); ?>
            <?php $categorias = get_the_terms(get_the_ID(), 'categoria_producto'); ?>

            <?php echo do_shortcode('[clc_franja_categorias categoria_actual="' . (($categorias && !is_wp_error($categorias)) ? esc_attr($categorias[0]->slug) : '') . '"]'); ?>

            <h1><?php the_title(); ?></h1>
            <p style="color:var(--dim);">
                <?php if ($categorias && !is_wp_error($categorias)): ?>
                    <?php echo esc_html($categorias[0]->name); ?>
                <?php endif; ?>
                <?php if ($marcas && !is_wp_error($marcas)): ?>
                    &middot; <?php echo esc_html($marcas[0]->name); ?>
                <?php endif; ?>
            </p>

            <?php if (has_post_thumbnail()): ?>
                <?php the_post_thumbnail('large', ['style' => 'width:100%;height:auto;border-radius:var(--r);']); ?>
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

            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:16px;align-items:center;">
                <?php echo do_shortcode('[clc_boton_whatsapp]'); ?>
                <?php echo do_shortcode('[clc_boton_compartir]'); ?>
                <button type="button" class="btn2 btn-ol clc-btn-copiar" data-url="<?php echo esc_url(get_permalink()); ?>" style="border:1px solid var(--border);background:transparent;cursor:pointer;">
                    Copiar link
                </button>
                <span class="clc-copiado-aviso" style="display:none;color:var(--wa-dark);font-size:.85rem;">¡Copiado!</span>
            </div>

            <?php
            $relacionados = new WP_Query([
                'post_type' => 'articulo',
                'posts_per_page' => 4,
                'post__not_in' => [get_the_ID()],
                'tax_query' => ($marcas && !is_wp_error($marcas)) ? [[
                    'taxonomy' => 'marca_producto', 'field' => 'term_id', 'terms' => $marcas[0]->term_id,
                ]] : (($categorias && !is_wp_error($categorias)) ? [[
                    'taxonomy' => 'categoria_producto', 'field' => 'term_id', 'terms' => $categorias[0]->term_id,
                ]] : []),
            ]);
            if ($relacionados->have_posts()):
            ?>
                <h2 style="margin-top:44px;font-size:1.3rem;">También te puede interesar</h2>
                <div class="clc-grid clc-grid-articulos">
                    <?php while ($relacionados->have_posts()): $relacionados->the_post(); ?>
                        <a class="clc-card clc-card-articulo" href="<?php echo esc_url(get_permalink()); ?>">
                            <?php echo get_the_post_thumbnail(get_the_ID(), 'medium'); ?>
                            <span class="clc-card-titulo"><?php the_title(); ?></span>
                        </a>
                    <?php endwhile; wp_reset_postdata(); ?>
                </div>
            <?php endif; ?>
        <?php endwhile; ?>
    </main>
    <script>
    (function () {
        var btn = document.querySelector('.clc-btn-copiar');
        if (!btn) return;
        btn.addEventListener('click', function () {
            var url = btn.getAttribute('data-url');
            var aviso = document.querySelector('.clc-copiado-aviso');
            var hacerAviso = function () {
                if (!aviso) return;
                aviso.style.display = 'inline';
                setTimeout(function () { aviso.style.display = 'none'; }, 2000);
            };
            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(hacerAviso);
            } else {
                var tmp = document.createElement('input');
                tmp.value = url;
                document.body.appendChild(tmp);
                tmp.select();
                document.execCommand('copy');
                document.body.removeChild(tmp);
                hacerAviso();
            }
        });
    })();
    </script>
<?php
CLC_Layout::cerrar_pagina();
