<?php
if (!defined('ABSPATH')) exit;

/**
 * Importador de Excel (una hoja = una marca, normalmente).
 * Usa CLC_Xlsx_Reader (sin dependencias externas) — no requiere Composer ni configuración
 * previa, funciona apenas se activa el plugin.
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
        ?>
        <div class="wrap">
            <h1>Importar catálogo desde Excel</h1>

            <?php if (isset($_GET['resultado'])): ?>
                <div class="notice notice-success">
                    <p><?php echo esc_html(urldecode($_GET['resultado'])); ?></p>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div class="notice notice-error">
                    <p><?php echo esc_html(urldecode($_GET['error'])); ?></p>
                </div>
            <?php endif; ?>

            <p>Subí el Excel del cliente (una lengüeta por marca/rubro). El sistema crea automáticamente las categorías,
            marcas y artículos que no existan todavía, usando el mapeo hoja→categoría acordado con el cliente.</p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="clc_importar_excel">
                <?php wp_nonce_field('clc_importar_excel', 'clc_importar_nonce'); ?>
                <p><input type="file" name="clc_excel" accept=".xlsx" required></p>
                <p><button type="submit" class="button button-primary">Importar</button></p>
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

        if (empty($_FILES['clc_excel']['tmp_name'])) {
            self::redirigir_error('No se recibió ningún archivo.');
        }
        if (!class_exists('ZipArchive')) {
            self::redirigir_error('Falta la extensión "zip" de PHP en el hosting; hablalo con soporte de Plesk.');
        }

        $lector = CLC_Xlsx_Reader::abrir($_FILES['clc_excel']['tmp_name']);
        if (!$lector) {
            self::redirigir_error('No se pudo leer el archivo. Verificá que sea un .xlsx válido.');
        }

        $mapeo = self::mapeo_default();
        $creados = 0;
        $actualizados = 0;
        $hojas_ignoradas = [];

        foreach ($lector->nombres_de_hoja() as $nombre_hoja) {
            $clave = strtolower(trim($nombre_hoja));
            if (!isset($mapeo[$clave])) {
                $hojas_ignoradas[] = $nombre_hoja;
                continue; // hoja sin mapeo conocido: se ignora, se revisa a mano
            }
            [$nombre_categoria, $marca_config] = $mapeo[$clave];
            $marca_fija = ($marca_config === 'auto') ? ucfirst($nombre_hoja) : $marca_config;

            $categoria_term = self::obtener_o_crear_termino($nombre_categoria, 'categoria_producto');
            $marca_term = self::obtener_o_crear_termino($marca_fija, 'marca_producto');

            $filas = $lector->filas_de_hoja($nombre_hoja);

            foreach ($filas as $fila) {
                if (!self::es_fila_de_producto($fila, $nombre_hoja)) {
                    continue;
                }
                $nombre_articulo = trim((string) $fila[0]);

                // Descripción: el resto de columnas, salvo lo que sea precio (contiene "dolar")
                $partes = [];
                foreach ($fila as $col => $valor) {
                    if ($col === 0) continue;
                    $valor = trim((string) $valor);
                    if ($valor === '' || mb_strlen($valor) < 2 || stripos($valor, 'dolar') !== false) continue;
                    $partes[] = $valor;
                }
                $descripcion = implode(' / ', $partes);

                $resultado = self::crear_o_actualizar_articulo($nombre_articulo, $descripcion, $categoria_term, $marca_term);
                if ($resultado === 'creado') $creados++;
                if ($resultado === 'actualizado') $actualizados++;
            }
        }

        $lector->cerrar();

        $mensaje = "Importación completa: {$creados} artículos nuevos, {$actualizados} actualizados.";
        if (!empty($hojas_ignoradas)) {
            $mensaje .= ' Hojas sin mapear (ignoradas): ' . implode(', ', $hojas_ignoradas) . '.';
        }
        wp_safe_redirect(add_query_arg('resultado', urlencode($mensaje), admin_url('edit.php?post_type=articulo&page=clc-importar')));
        exit;
    }

    private static function redirigir_error($mensaje) {
        wp_safe_redirect(add_query_arg('error', urlencode($mensaje), admin_url('edit.php?post_type=articulo&page=clc-importar')));
        exit;
    }

    /**
     * Decide si una fila es un producto real, filtrando encabezados, títulos,
     * notas al pie ("Obs: ...") y basura de celdas sueltas en otras columnas
     * (ej. "DZ", "x", "10" que quedaron tipeadas fuera de la tabla).
     */
    private static function es_fila_de_producto($fila, $nombre_hoja) {
        // Los productos siempre arrancan en la columna A; todo lo que no
        // empiece ahí es ruido (números/letras sueltas en otra columna).
        if (!array_key_exists(0, $fila)) {
            return false;
        }
        $valor = trim((string) $fila[0]);
        if ($valor === '' || mb_strlen($valor) < 3) {
            return false;
        }
        $bajo = mb_strtolower($valor);

        if (str_starts_with($bajo, 'listado')) return false;
        if (str_starts_with($bajo, 'obs')) return false;
        if (str_contains($bajo, 'cotiza')) return false;
        if ($bajo === mb_strtolower(trim($nombre_hoja))) return false; // ej. "PROYECTOR" solo, sin datos reales

        // Fila de encabezado: alguna celda de la fila es literalmente un nombre de columna,
        // aunque no sea la primera (ej. "RELOJ GARMIN | Monto" en vez de "Modelo | Monto").
        $etiquetas_encabezado = ['modelo', 'marca', 'monto', 'precio', 'ram', 'memoria'];
        foreach ($fila as $celda) {
            if (in_array(mb_strtolower(trim((string) $celda)), $etiquetas_encabezado, true)) {
                return false;
            }
        }

        return true;
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
