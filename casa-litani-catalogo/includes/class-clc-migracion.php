<?php
if (!defined('ABSPATH')) exit;

/**
 * Migración única: crea "Relojes" como categoría nueva de primer nivel (hermana de
 * Celulares/Audio/Gaming/etc.) y las subcategorías de Ordenadores (Notebooks/Tablets/
 * MacBooks/iPads), Accesorios Varios (Impresoras), Audio (Parlantes/Auriculares), Gaming
 * (Consolas) y Pantallas (Televisores/Proyectores) — y reclasifica los artículos existentes
 * según reglas fijas, corrigiendo de paso marcas mal puestas (ej. "JBL Parlantes" →
 * subcategoría Parlantes + marca JBL) y artículos en la categoría equivocada (ej. relojes
 * Apple Watch que estaban cargados en Audio con marca "Airpods").
 *
 * Es idempotente: correrla de nuevo sobre artículos ya migrados no les cambia nada.
 */
class CLC_Migracion {

    /** Categorías nuevas de primer nivel (sin subcategoría, van directo a marca — como Celulares). */
    const CATEGORIAS_NUEVAS = ['Relojes'];

    /** Estructura de subcategorías a crear, por categoría padre. */
    const SUBCATEGORIAS = [
        'Ordenadores' => ['Notebooks', 'Tablets', 'MacBooks', 'iPads'],
        'Accesorios Varios' => ['Impresoras'],
        'Audio' => ['Parlantes', 'Auriculares'],
        'Gaming' => ['Consolas'],
        'Pantallas' => ['Televisores', 'Proyectores'],
    ];

    /** Palabras clave para extraer la marca real del título cuando la marca cargada no sirve. */
    const MARCAS_POR_PALABRA = [
        'SAMSUNG' => 'Samsung', 'XIAOMI' => 'Xiaomi', 'LENOVO' => 'Lenovo', 'CIDEA' => 'C Idea',
        'AMAZON' => 'Amazon', 'BROTHER' => 'Brother', 'EPSON' => 'Epson', 'JVC' => 'JVC',
        'TCL' => 'TCL', 'MOTOROLA' => 'Motorola', 'IKEDA' => 'Ikeda', 'DAEWO' => 'Daewoo',
        'ECOPOWER' => 'Ecopower', 'NOBLEX' => 'Noblex', 'VIZZION' => 'Vizzion', 'CARRIER' => 'Carrier',
        'MIDEA' => 'Midea', 'CONLUX' => 'Conlux', 'CLIMAX' => 'Climax', 'SPEED' => 'Speed',
    ];

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_post_clc_migracion_aplicar', [__CLASS__, 'aplicar']);
    }

    public static function add_menu() {
        add_submenu_page(
            'edit.php?post_type=articulo',
            'Reorganizar categorías',
            'Reorganizar categorías',
            'manage_options',
            'clc-migracion',
            [__CLASS__, 'render_page']
        );
    }

    /** Extrae la marca real de un título usando el mapa de palabras clave, o null si no encuentra ninguna. */
    private static function extraer_marca($titulo) {
        $t = mb_strtoupper($titulo);
        foreach (self::MARCAS_POR_PALABRA as $palabra => $marca) {
            if (str_contains($t, $palabra)) {
                return $marca;
            }
        }
        return null;
    }

    /**
     * Devuelve el plan de cambios para un artículo: [categoria_destino, subcategoria_destino, marca_destino]
     * o null si esta categoría no tiene reglas de migración (queda como está).
     */
    private static function clasificar($categoria_actual, $marca_actual, $titulo) {
        $t = mb_strtolower($titulo);

        if ('Ordenadores' === $categoria_actual) {
            if ('Varios' === $marca_actual || str_contains($t, 'tablet')) {
                $marca = ('Varios' === $marca_actual) ? (self::extraer_marca($titulo) ?: $marca_actual) : $marca_actual;
                return ['Ordenadores', 'Tablets', $marca];
            }
            if (str_contains($t, 'macbook')) {
                return ['Ordenadores', 'MacBooks', 'Apple'];
            }
            if (str_starts_with($t, 'ipad') || str_contains($t, ' ipad')) {
                return ['Ordenadores', 'iPads', 'Apple'];
            }
            return ['Ordenadores', 'Notebooks', $marca_actual];
        }

        if ('Audio' === $categoria_actual) {
            // Relojes Apple Watch mal cargados en Audio con marca "Airpods" -> se mudan a la
            // categoría nueva "Relojes" (de primer nivel, sin subcategoría, como Celulares).
            if ('Airpods' === $marca_actual && (str_contains($t, 'reloj') || preg_match('/^apple (se|s\d)/', $t))) {
                return ['Relojes', null, 'Apple'];
            }
            if ('JBL Parlantes' === $marca_actual) {
                return ['Audio', 'Parlantes', 'JBL'];
            }
            if ('JBL Auriculares' === $marca_actual) {
                return ['Audio', 'Auriculares', 'JBL'];
            }
            if ('Airpods' === $marca_actual) {
                return ['Audio', 'Auriculares', 'Apple'];
            }
            return null;
        }

        if ('Accesorios Varios' === $categoria_actual) {
            // Los relojes se mudan a su propia categoría "Relojes" (primer nivel, sin subcategoría).
            if ('Garmin' === $marca_actual) {
                return ['Relojes', null, 'Garmin'];
            }
            if ('Samsung Watch' === $marca_actual) {
                return ['Relojes', null, 'Samsung'];
            }
            if ('Impresoras' === $marca_actual) {
                return ['Accesorios Varios', 'Impresoras', self::extraer_marca($titulo) ?: $marca_actual];
            }
            return null;
        }

        if ('Gaming' === $categoria_actual) {
            if (str_starts_with($t, 'play')) {
                return ['Gaming', 'Consolas', 'PlayStation'];
            }
            if (str_contains($t, 'x box') || str_contains($t, 'xbox')) {
                return ['Gaming', 'Consolas', 'Xbox'];
            }
            if (str_contains($t, 'nintendo')) {
                return ['Gaming', 'Consolas', 'Nintendo'];
            }
            return ['Gaming', 'Consolas', $marca_actual];
        }

        if ('Pantallas' === $categoria_actual) {
            $subcat = str_contains($t, 'proyector') ? 'Proyectores' : 'Televisores';
            $marca = self::extraer_marca($titulo) ?: $marca_actual;
            return ['Pantallas', $subcat, $marca];
        }

        return null;
    }

    /** Recorre todos los artículos y arma la lista de cambios propuestos (sin aplicar nada). */
    private static function analizar() {
        $query = new WP_Query(['post_type' => 'articulo', 'post_status' => 'publish', 'posts_per_page' => -1]);
        $cambios = [];

        foreach ($query->posts as $post) {
            $categorias = wp_get_post_terms($post->ID, 'categoria_producto', ['fields' => 'names']);
            $marcas = wp_get_post_terms($post->ID, 'marca_producto', ['fields' => 'names']);
            $categoria_actual = $categorias[0] ?? '';
            $marca_actual = $marcas[0] ?? '';

            $plan = self::clasificar($categoria_actual, $marca_actual, $post->post_title);
            if (!$plan) {
                continue;
            }
            [$cat_destino, $subcat_destino, $marca_destino] = $plan;
            $termino_efectivo = $subcat_destino ?: $cat_destino;

            // Si ya está exactamente así, no hay nada que migrar.
            if ($categoria_actual === $termino_efectivo && $marca_actual === $marca_destino) {
                continue;
            }

            $cambios[] = [
                'post_id' => $post->ID,
                'titulo' => $post->post_title,
                'categoria_actual' => $categoria_actual,
                'marca_actual' => $marca_actual,
                'categoria_destino' => $cat_destino,
                'subcategoria_destino' => $subcat_destino,
                'marca_destino' => $marca_destino,
            ];
        }

        return $cambios;
    }

    private static function obtener_o_crear_termino($nombre, $taxonomia, $padre_id = 0) {
        $existente = get_term_by('name', $nombre, $taxonomia);
        if ($existente && (int) $existente->parent === (int) $padre_id) {
            return $existente;
        }
        // Si existe con otro padre (raro) o no existe, lo creamos/reubicamos.
        if ($existente) {
            return $existente;
        }
        $insertado = wp_insert_term($nombre, $taxonomia, ['parent' => $padre_id]);
        if (is_wp_error($insertado)) {
            return get_term_by('name', $nombre, $taxonomia);
        }
        return get_term($insertado['term_id'], $taxonomia);
    }

    /** Crea (si hace falta) las categorías nuevas de primer nivel y todas las subcategorías definidas en SUBCATEGORIAS. */
    private static function crear_subcategorias() {
        foreach (self::CATEGORIAS_NUEVAS as $nombre) {
            self::obtener_o_crear_termino($nombre, 'categoria_producto', 0);
        }
        foreach (self::SUBCATEGORIAS as $categoria_padre => $hijos) {
            $padre = get_term_by('name', $categoria_padre, 'categoria_producto');
            if (!$padre) {
                continue;
            }
            foreach ($hijos as $hijo) {
                self::obtener_o_crear_termino($hijo, 'categoria_producto', $padre->term_id);
            }
        }
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $cambios = self::analizar();
        ?>
        <div class="wrap">
            <h1>Reorganizar categorías, subcategorías y marcas</h1>

            <?php if (isset($_GET['aplicado'])): ?>
                <div class="notice notice-success"><p><?php echo esc_html(urldecode($_GET['aplicado'])); ?></p></div>
            <?php endif; ?>

            <p>Crea las subcategorías de Ordenadores, Accesorios Varios, Audio, Gaming y Pantallas, y reclasifica
            los artículos existentes (corrige también marcas mal puestas y artículos en la categoría equivocada,
            como los relojes Apple Watch que hoy están en Audio). Se puede correr más de una vez sin problema:
            lo que ya está bien clasificado no se toca.</p>

            <?php if (empty($cambios)): ?>
                <div class="notice notice-info"><p>No hay cambios pendientes — todo ya está clasificado según las reglas actuales.</p></div>
            <?php else: ?>
                <p><strong><?php echo count($cambios); ?></strong> artículos van a cambiar de categoría/subcategoría/marca:</p>
                <table class="wp-list-table widefat fixed striped" style="margin-bottom:20px;">
                    <thead>
                        <tr>
                            <th>Artículo</th>
                            <th>Antes</th>
                            <th>Después</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cambios as $c): ?>
                            <tr>
                                <td><?php echo esc_html($c['titulo']); ?></td>
                                <td><?php echo esc_html($c['categoria_actual'] . ' / ' . $c['marca_actual']); ?></td>
                                <td><strong><?php echo esc_html(implode(' / ', array_filter([$c['categoria_destino'], $c['subcategoria_destino'], $c['marca_destino']]))); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="clc_migracion_aplicar">
                    <?php wp_nonce_field('clc_migracion_aplicar', 'clc_migracion_nonce'); ?>
                    <button type="submit" class="button button-primary" onclick="return confirm('¿Aplicar estos <?php echo count($cambios); ?> cambios? Se recomienda haber bajado un backup antes (Artículos → Backup).');">Aplicar estos <?php echo count($cambios); ?> cambios</button>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function aplicar() {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        check_admin_referer('clc_migracion_aplicar', 'clc_migracion_nonce');

        self::crear_subcategorias();
        $cambios = self::analizar();

        foreach ($cambios as $c) {
            if ($c['subcategoria_destino']) {
                $categoria_padre = get_term_by('name', $c['categoria_destino'], 'categoria_producto');
                $padre_id = $categoria_padre ? $categoria_padre->term_id : 0;
                $categoria_term = self::obtener_o_crear_termino($c['subcategoria_destino'], 'categoria_producto', $padre_id);
            } else {
                // Categoría de primer nivel, sin subcategoría (ej. Relojes) — se asigna directo.
                $categoria_term = self::obtener_o_crear_termino($c['categoria_destino'], 'categoria_producto', 0);
            }
            $marca_term = self::obtener_o_crear_termino($c['marca_destino'], 'marca_producto');

            wp_set_object_terms($c['post_id'], [(int) $categoria_term->term_id], 'categoria_producto');
            wp_set_object_terms($c['post_id'], [(int) $marca_term->term_id], 'marca_producto');
        }

        if (class_exists('CLC_Cache')) {
            CLC_Cache::limpiar_si_corresponde();
        }

        $mensaje = count($cambios) . ' artículos reclasificados correctamente.';
        wp_safe_redirect(add_query_arg('aplicado', urlencode($mensaje), admin_url('edit.php?post_type=articulo&page=clc-migracion')));
        exit;
    }
}
