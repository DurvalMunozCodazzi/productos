<?php
if (!defined('ABSPATH')) exit;

/**
 * Exportar el catálogo completo (sin precios) para compartir fuera de la web:
 * - CSV (se abre directo en Excel) con nombre, categoría, marca y descripción.
 * - Listado imprimible en el navegador (Ctrl+P → Guardar como PDF), agrupado
 *   por categoría/marca, para mandar por WhatsApp sin depender del sitio.
 */
class CLC_Exportar {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_post_clc_exportar_csv', [__CLASS__, 'exportar_csv']);
        add_action('admin_post_clc_exportar_imprimible', [__CLASS__, 'exportar_imprimible']);
    }

    public static function add_menu() {
        add_submenu_page(
            'edit.php?post_type=articulo',
            'Exportar catálogo',
            'Exportar',
            'manage_options',
            'clc-exportar',
            [__CLASS__, 'render_page']
        );
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>Exportar catálogo</h1>
            <p>Sin precios, igual que se ve en la web — para compartir fuera del sitio.</p>

            <h2>Excel / CSV</h2>
            <p>Un archivo .csv con nombre, categoría, marca y descripción de cada artículo. Se abre directo con Excel.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="clc_exportar_csv">
                <?php wp_nonce_field('clc_exportar_csv', 'clc_exportar_csv_nonce'); ?>
                <button type="submit" class="button button-primary">Descargar .csv</button>
            </form>

            <h2 style="margin-top:30px;">Listado para compartir / PDF</h2>
            <p>Abre una página con el catálogo agrupado por categoría y marca, lista para imprimir o guardar como PDF
            (Ctrl+P → "Guardar como PDF") y mandar por WhatsApp.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" target="_blank">
                <input type="hidden" name="action" value="clc_exportar_imprimible">
                <?php wp_nonce_field('clc_exportar_imprimible', 'clc_exportar_imprimible_nonce'); ?>
                <button type="submit" class="button button-primary">Ver listado imprimible</button>
            </form>
        </div>
        <?php
    }

    private static function obtener_articulos_agrupados() {
        $query = new WP_Query([
            'post_type' => 'articulo',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        $agrupado = [];
        foreach ($query->posts as $post) {
            $categorias = wp_get_post_terms($post->ID, 'categoria_producto', ['fields' => 'names']);
            $marcas = wp_get_post_terms($post->ID, 'marca_producto', ['fields' => 'names']);
            $categoria = $categorias[0] ?? 'Sin categoría';
            $marca = $marcas[0] ?? 'Sin marca';

            $agrupado[$categoria][$marca][] = [
                'titulo' => $post->post_title,
                'descripcion' => $post->post_content,
            ];
        }
        ksort($agrupado);
        return $agrupado;
    }

    public static function exportar_csv() {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        check_admin_referer('clc_exportar_csv', 'clc_exportar_csv_nonce');

        $agrupado = self::obtener_articulos_agrupados();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="catalogo-casa-litani-' . gmdate('Y-m-d') . '.csv"');

        $salida = fopen('php://output', 'w');
        fputs($salida, "\xEF\xBB\xBF"); // BOM para que Excel reconozca los acentos
        fputcsv($salida, ['Categoría', 'Marca', 'Artículo', 'Descripción']);

        foreach ($agrupado as $categoria => $marcas) {
            foreach ($marcas as $marca => $articulos) {
                foreach ($articulos as $articulo) {
                    fputcsv($salida, [$categoria, $marca, $articulo['titulo'], $articulo['descripcion']]);
                }
            }
        }

        fclose($salida);
        exit;
    }

    public static function exportar_imprimible() {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        check_admin_referer('clc_exportar_imprimible', 'clc_exportar_imprimible_nonce');

        $agrupado = self::obtener_articulos_agrupados();
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <title>Catálogo Casa Litani</title>
            <style>
                body { font-family: Arial, sans-serif; color: #222; margin: 30px; }
                h1 { color: #f9526b; }
                h2 { border-bottom: 2px solid #f9526b; padding-bottom: 4px; margin-top: 30px; }
                h3 { color: #555; margin-bottom: 6px; }
                ul { margin: 0 0 16px 0; padding-left: 20px; }
                li { margin-bottom: 6px; }
                .desc { color: #777; font-size: 0.85em; }
                @media print { body { margin: 10px; } }
            </style>
        </head>
        <body>
            <h1>Catálogo Casa Litani</h1>
            <p>Generado el <?php echo esc_html(date_i18n('d/m/Y')); ?> — sin precios, consultar disponibilidad por WhatsApp.</p>
            <?php foreach ($agrupado as $categoria => $marcas): ?>
                <h2><?php echo esc_html($categoria); ?></h2>
                <?php foreach ($marcas as $marca => $articulos): ?>
                    <h3><?php echo esc_html($marca); ?></h3>
                    <ul>
                        <?php foreach ($articulos as $articulo): ?>
                            <li>
                                <strong><?php echo esc_html($articulo['titulo']); ?></strong>
                                <?php if (!empty($articulo['descripcion'])): ?>
                                    <div class="desc"><?php echo esc_html($articulo['descripcion']); ?></div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endforeach; ?>
            <?php endforeach; ?>
            <script>window.onload = function(){ window.print(); };</script>
        </body>
        </html>
        <?php
        exit;
    }
}
