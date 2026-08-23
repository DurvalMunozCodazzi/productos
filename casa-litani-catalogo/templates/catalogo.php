<?php
/** Página /catalogo/ — página completa propia (no depende del theme/Divi). */
CLC_Layout::abrir_pagina('Catálogo');
?>
    <span style="display:block;color:var(--accent);font-weight:700;font-size:.85rem;text-transform:uppercase;letter-spacing:.08em;margin-bottom:12px">Nuestro catálogo</span>
    <h1 style="font-size:clamp(1.7rem,3vw,2.3rem);font-weight:700;letter-spacing:-.01em;max-width:700px;margin-bottom:16px">Explorá por categoría</h1>
    <p style="color:var(--dim);max-width:600px;margin:0 0 32px;font-size:1.05rem">Elegí una categoría para ver las marcas disponibles. Todo el catálogo es informativo — sin precios online.</p>

    <?php $clc_novedades = do_shortcode('[clc_novedades limite="8"]'); ?>
    <?php if ($clc_novedades): ?>
        <h2 style="font-size:clamp(1.4rem,2.5vw,1.9rem);font-weight:700;letter-spacing:-.01em;margin:0 0 16px">Novedades</h2>
        <?php echo $clc_novedades; ?>
        <div style="margin:40px 0;border-top:1px solid var(--border);"></div>
    <?php endif; ?>

    <?php echo do_shortcode('[clc_categorias]'); ?>

    <h2 id="buscador" style="font-size:clamp(1.4rem,2.5vw,1.9rem);font-weight:700;letter-spacing:-.01em;margin:48px 0 16px;scroll-margin-top:100px;">O buscá directo</h2>
    <?php echo do_shortcode('[clc_filtro_catalogo]'); ?>
<?php
CLC_Layout::cerrar_pagina();
