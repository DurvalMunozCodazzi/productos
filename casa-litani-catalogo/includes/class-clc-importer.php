<?php
if (!defined('ABSPATH')) exit;

/**
 * Importador de Excel (una hoja = una marca, normalmente).
 * Requiere PhpSpreadsheet vía Composer (vendor/autoload.php en la raíz del plugin).
 * Si no está instalado, la pantalla de importación avisa cómo instalarlo.
 */
class CLC_Importer {

    // Mapeo hoja(lowercase, trim) => [categoria, marca]
    // "marca:auto" = usar el nombre de la hoja como marca.
    // Editable a futuro desde el admin; por ahora queda fijo acá según lo acordado con el cliente.
    public static function mapeo_default() {
        return [
            'honor' => ['Celulares', 'auto'],
            'inifinix' => ['Celulares', 'Infinix'],
            'oppo' => ['Celulares', 'auto'],
            'zte' => ['Celulares', 'auto'],
            'motorola' => ['Celulares', 'auto'],
            'samsung' => ['Celulares', 'auto'],
            'xiaomi' => ['Celulares', 'auto'],
            'iphone' => ['Celulares', 'iPhone'],
            'swap' => ['Celulares', 'iPhone Usados/Swap'],
            'ipad' => ['Ordenadores', 'iPad'],
            'tablet' => ['Ordenadores', 'Varios'],
            'reloj samsung' => ['Accesorios Varios', 'Samsung Watch'],
            'reloj varios' => ['Accesorios Varios', 'Relojes Varios'],
            'garmin' => ['Accesorios Varios', 'Garmin'],
            'auric. varios' => ['Audio', 'Auriculares Varios'],
            'auri. jbl' => ['Audio', 'JBL Auriculares'],
            'jbl' => ['Audio', 'JBL Parlantes'],
            'relojes' => ['Audio', 'Airpods'],
            'plays' => ['Gaming', 'PlayStation'],
            'tele' => ['Pantallas', 'Televisores'],
            'proyector' => ['Pantallas', 'Proyectores'],
            'impresora' => ['Accesorios Varios', 'Impresoras'],
            'freidora' => ['Hogar', 'Freidoras'],
            'aire' => ['Hogar', 'Aires Acondicionados'],
            'patineta' => ['Movilidad', 'Patinetas'],
        ];
    }

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_post_clc_importar_excel', [__CLASS__, 'procesar_importacion']);
    }

    public static function add_menu() {
        add_submenu_page(
            'edit.php?post_type=articulo',
            'Importar desde Excel',
            'Importar Excel',
            'manage_options',
            'clc-importar',
            [__CLASS__, 'render_page']
        );
    }

    public static function render_page() {
        $vendor_ok = file_exists(CLC_PATH . 'vendor/autoload.php');
        ?>
        <div class="wrap">
            <h1>Importar catálogo desde Excel</h1>

            <?php if (!$vendor_ok): ?>
                <div class="notice notice-error">
                    <p><strong>Falta una dependencia.</strong> Este importador necesita la librería PhpSpreadsheet.
                    Desde Plesk, en la carpeta del plugin (<code>wp-content/plugins/casa-litani-catalogo</code>),
                    corré: <code>composer require phpoffice/phpspreadsheet</code>.</p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['resultado'])): ?>
                <div class="notice notice-success">
                    <p><?php echo esc_html(urldecode($_GET['resultado'])); ?></p>
                </div>
            <?php endif; ?>

            <p>Subí el Excel del cliente (una lengüeta por marca/rubro). El sistema crea automáticamente las categorías,
            marcas y artículos que no existan todavía, usando el mapeo hoja→categoría acordado con el cliente.</p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="clc_importar_excel">
                <?php wp_nonce_field('clc_importar_excel', 'clc_importar_nonce'); ?>
                <p><input type="file" name="clc_excel" accept=".xlsx" required></p>
                <p><button type="submit" class="button button-primary" <?php disabled(!$vendor_ok); ?>>Importar</button></p>
            </form>

            <h2>Mapeo de hojas actual</h2>
            <table class="widefat" style="max-width:700px;">
                <thead><tr><th>Hoja</th><th>Categoría</th><th>Marca</th></tr></thead>
                <tbody>
                <?php foreach (self::mapeo_default() as $hoja => $datos): ?>
                    <tr><td><?php echo esc_html($hoja); ?></td><td><?php echo esc_html($datos[0]); ?></td><td><?php echo esc_html($datos[1]); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function procesar_importacion() {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        check_admin_referer('clc_importar_excel', 'clc_importar_nonce');

        if (!file_exists(CLC_PATH . 'vendor/autoload.php')) {
            wp_die('Falta instalar PhpSpreadsheet (composer require phpoffice/phpspreadsheet) en la carpeta del plugin.');
        }
        require_once CLC_PATH . 'vendor/autoload.php';

        if (empty($_FILES['clc_excel']['tmp_name'])) {
            wp_die('No se recibió ningún archivo.');
        }

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load($_FILES['clc_excel']['tmp_name']);
        $mapeo = self::mapeo_default();

        $creados = 0;
        $actualizados = 0;

        foreach ($spreadsheet->getSheetNames() as $nombre_hoja) {
            $clave = strtolower(trim($nombre_hoja));
            if (!isset($mapeo[$clave])) {
                continue; // hoja sin mapeo conocido: se ignora, se revisa a mano
            }
            [$nombre_categoria, $marca_config] = $mapeo[$clave];
            $marca_fija = ($marca_config === 'auto') ? ucfirst($nombre_hoja) : $marca_config;

            $categoria_term = self::obtener_o_crear_termino($nombre_categoria, 'categoria_producto');
            $marca_term = self::obtener_o_crear_termino($marca_fija, 'marca_producto');

            $sheet = $spreadsheet->getSheetByName($nombre_hoja);
            $filas = $sheet->toArray(null, true, true, true);

            $fila_header = self::detectar_fila_header($filas);
            if ($fila_header === null) {
                continue;
            }

            $encabezados = $filas[$fila_header];
            $col_precio = self::detectar_columna_precio($encabezados);

            foreach ($filas as $num_fila => $fila) {
                if ($num_fila <= $fila_header) {
                    continue;
                }
                $celdas = array_values($fila);
                $nombre_articulo = trim((string) reset($celdas));
                if ($nombre_articulo === '') {
                    continue;
                }

                // Arma la descripción con el resto de columnas, salvo la de precio
                $partes = [];
                foreach ($fila as $col => $valor) {
                    if ($col === array_key_first($fila)) continue; // ya es el nombre
                    if ($col === $col_precio) continue; // no publicamos precio
                    $valor = trim((string) $valor);
                    if ($valor !== '') {
                        $partes[] = $valor;
                    }
                }
                $descripcion = implode(' / ', $partes);

                $resultado = self::crear_o_actualizar_articulo($nombre_articulo, $descripcion, $categoria_term, $marca_term);
                if ($resultado === 'creado') $creados++;
                if ($resultado === 'actualizado') $actualizados++;
            }
        }

        $mensaje = "Importación completa: {$creados} artículos nuevos, {$actualizados} actualizados.";
        wp_safe_redirect(add_query_arg('resultado', urlencode($mensaje), admin_url('edit.php?post_type=articulo&page=clc-importar')));
        exit;
    }

    private static function detectar_fila_header($filas) {
        foreach ($filas as $num_fila => $fila) {
            foreach ($fila as $valor) {
                $v = strtolower(trim((string) $valor));
                if (in_array($v, ['modelo', 'marca'], true)) {
                    return $num_fila;
                }
            }
        }
        return null;
    }

    private static function detectar_columna_precio($encabezados) {
        foreach ($encabezados as $col => $valor) {
            $v = strtolower(trim((string) $valor));
            if (in_array($v, ['monto', 'precio'], true)) {
                return $col;
            }
        }
        return null;
    }

    private static function obtener_o_crear_termino($nombre, $taxonomia) {
        $existente = get_term_by('name', $nombre, $taxonomia);
        if ($existente) {
            return $existente;
        }
        $insertado = wp_insert_term($nombre, $taxonomia);
        return get_term($insertado['term_id'], $taxonomia);
    }

    private static function crear_o_actualizar_articulo($nombre, $descripcion, $categoria_term, $marca_term) {
        $existente = get_page_by_title($nombre, OBJECT, 'articulo');

        $datos_post = [
            'post_title' => $nombre,
            'post_content' => $descripcion,
            'post_type' => 'articulo',
            'post_status' => 'publish',
        ];

        if ($existente) {
            $datos_post['ID'] = $existente->ID;
            wp_update_post($datos_post);
            $post_id = $existente->ID;
            $resultado = 'actualizado';
        } else {
            $post_id = wp_insert_post($datos_post);
            $resultado = 'creado';
        }

        wp_set_object_terms($post_id, [(int) $categoria_term->term_id], 'categoria_producto');
        wp_set_object_terms($post_id, [(int) $marca_term->term_id], 'marca_producto');

        if (empty(get_post_meta($post_id, '_clc_estado', true))) {
            update_post_meta($post_id, '_clc_estado', 'activo');
        }

        return $resultado;
    }
}
