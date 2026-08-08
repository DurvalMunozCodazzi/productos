<?php
/** Página 404 propia — mismo header/footer que el resto del sitio, con buscador del catálogo. */
CLC_Layout::abrir_pagina('Página no encontrada');
?>
    <main style="max-width:700px;margin:0 auto;text-align:center;padding:20px 0;">
        <h1 style="font-size:2.2rem;">Esta página no existe</h1>
        <p style="color:var(--dim);margin-top:12px;">
            El link puede estar roto o el producto que buscás ya no está disponible. Probá buscarlo acá abajo,
            o mirá el catálogo completo.
        </p>
        <div style="margin:24px 0;text-align:left;">
            <?php echo do_shortcode('[clc_filtro_catalogo]'); ?>
        </div>
        <a href="<?php echo esc_url(home_url('/catalogo/')); ?>" class="btn2 btn-ol">Ver catálogo completo</a>
    </main>
<?php
CLC_Layout::cerrar_pagina();
