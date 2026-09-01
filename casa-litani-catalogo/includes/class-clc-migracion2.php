<?php
if (!defined('ABSPATH')) exit;

/**
 * Segunda reorganización: pasa de 3 niveles (Categoría → Subcategoría → Marca) a 2 niveles
 * (Categoría → Subcategoría), según la planilla nueva que armó el cliente. La marca de cada
 * producto se conserva (se sigue viendo en la ficha y en el buscador), pero deja de ser un
 * nivel de botones — ahora ES la subcategoría en la mayoría de los casos (ej. Celulares →
 * Xiaomi/Motorola/Samsung...), salvo donde el cliente pidió agrupar por tipo (ej. Ordenadores
 * → Tablet, MacBook) o dejó "Varios"/genéricos para lo que no tiene marca propia.
 *
 * No usa la taxonomía de Marca para nada de esto — solo reordena Categoría/Subcategoría.
 * Es idempotente: correrla de nuevo no rompe nada.
 */
class CLC_Migracion2 {

    /** Árbol completo de categorías → subcategorías, tal cual la planilla del cliente. */
    const ARBOL = [
        'Audio' => ['Auriculares', 'Parlantes', 'Multimedia', 'Varios'],
        'Celulares' => ['Xiaomi', 'Motorola', 'Samsung', 'Infinix', 'iPhone', 'Oppo', 'Blackview', 'Doogee', 'Accesorios'],
        'Gaming' => ['PlayStation', 'Xbox', 'Nintendo', 'Consolas'],
        'Hogar' => ['Electrodomésticos', 'Aire Acondicionado', 'Cámara de Vigilancia', 'Televisores', 'Proyectores'],
        'Movilidad' => ['Patineta', 'Moto Eléctrica'],
        'Ordenadores' => ['Lenovo', 'MSI', 'HP', 'MacBook', 'Asus', 'Acer', 'Dell', 'Alienware', 'Samsung', 'Tablet', 'Notebook', 'Accesorios'],
        'Reloj' => ['Garmin', 'Samsung Watch', 'Apple Watch', 'Xiaomi', 'Varios'],
        'Fotografía y Filmación' => ['Cámara Fotográfica', 'Drone', 'Micrófono', 'GPS', 'Ecosonda', 'Varios'],
        'Artículos Varios' => [],
        'Belleza y Cuidado' => ['Planchita', 'Secador', 'Kit Modelador', 'Caballeros', 'Varios'],
    ];

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_post_clc_migracion2_aplicar', [__CLASS__, 'aplicar']);
        add_action('admin_post_clc_migracion2_borrar', [__CLASS__, 'borrar_categoria_vieja']);
    }

    public static function add_menu() {
        add_submenu_page(
            'edit.php?post_type=articulo',
            'Reorganizar categorías (planilla nueva)',
            'Reorganizar (v2)',
            'manage_options',
            'clc-migracion2',
            [__CLASS__, 'render_page']
        );
    }

    /**
     * Devuelve [categoria_nueva, subcategoria_nueva] para un artículo, a partir de su
     * categoría raíz actual, su subcategoría/categoría actual y su marca.
     */
    private static function clasificar($cat_raiz, $subcat_actual, $marca_actual, $titulo) {
        $t = mb_strtolower($titulo);

        // Las impresoras pueden haber quedado enganchadas por error al "Accesorios" compartido
        // de otra categoría (bug de versiones anteriores) — las corregimos sin importar dónde
        // hayan quedado, mirando directo la marca.
        if (in_array($marca_actual, ['Epson', 'Brother'], true)) {
            return ['Ordenadores', 'Accesorios'];
        }

        if ('Ordenadores' === $cat_raiz) {
            // Si ya está en una subcategoría específica de marca/tipo (no genérica), ya está bien
            // clasificado — no hace falta volver a tocarlo. "Notebook" y "Notebooks" NO cuentan acá
            // porque son el cajón genérico donde pueden haber caído tablets/MacBooks por error de
            // una corrida anterior con bug — esos siempre se vuelven a revisar más abajo.
            $subcats_especificas = array_diff(self::ARBOL['Ordenadores'], ['Notebook']);
            if ($subcat_actual && in_array($subcat_actual, $subcats_especificas, true)) {
                return null;
            }

            // A partir de acá reclasificamos desde cero (sirve tanto para artículos crudos que
            // v1 nunca procesó, como para los que quedaron mal repartidos en "Notebook"/"Notebooks"
            // por un bug de una corrida anterior de esta misma herramienta).
            if ('Tablets' === $subcat_actual || 'iPads' === $subcat_actual || 'Varios' === $marca_actual || str_contains($t, 'tablet')) {
                $marca = ('Varios' === $marca_actual) ? (self::extraer_marca($titulo) ?: $marca_actual) : $marca_actual;
                return ['Ordenadores', ('Samsung' === $marca) ? 'Samsung' : 'Tablet'];
            }
            if ('MacBooks' === $subcat_actual || str_contains($t, 'macbook')) {
                return ['Ordenadores', 'MacBook'];
            }
            if (str_starts_with($t, 'ipad') || str_contains($t, ' ipad')) {
                return ['Ordenadores', 'Tablet'];
            }
            $marcas_con_boton = ['Lenovo', 'MSI', 'HP', 'Asus', 'Acer', 'Dell', 'Alienware'];
            return ['Ordenadores', in_array($marca_actual, $marcas_con_boton, true) ? $marca_actual : 'Notebook'];
        }

        if ('Celulares' === $cat_raiz) {
            $mapa = ['MOTOROLA' => 'Motorola', 'Motorola' => 'Motorola'];
            $marca = $mapa[$marca_actual] ?? $marca_actual;
            return ['Celulares', $marca ?: 'Accesorios'];
        }

        if (in_array($cat_raiz, ['Relojes', 'Reloj'], true)) {
            if ('Garmin' === $marca_actual) return ['Reloj', 'Garmin'];
            if ('Apple' === $marca_actual) return ['Reloj', 'Apple Watch'];
            if (in_array($marca_actual, ['Samsung', 'Samsung Watch'], true)) return ['Reloj', 'Samsung Watch'];
            return ['Reloj', 'Varios'];
        }

        if ('Audio' === $cat_raiz) {
            // Relojes Apple Watch mal cargados en Audio (con o sin que v1 los haya procesado) — se mudan.
            if ('Airpods' === $marca_actual && (str_contains($t, 'reloj') || preg_match('/^apple (se|s\d)/', $t))) {
                return ['Reloj', 'Apple Watch'];
            }
            if ('Parlantes' === $subcat_actual || 'JBL Parlantes' === $marca_actual) return ['Audio', 'Parlantes'];
            if ('Auriculares' === $subcat_actual || 'JBL Auriculares' === $marca_actual || 'Airpods' === $marca_actual) return ['Audio', 'Auriculares'];
            if (in_array($subcat_actual, ['Multimedia', 'Varios'], true)) return null;
            return ['Audio', 'Varios'];
        }

        if ('Gaming' === $cat_raiz) {
            // Ya tiene su propia marca como subcategoría (PlayStation/Xbox/Nintendo) — no hace falta tocar.
            if (in_array($subcat_actual, ['PlayStation', 'Xbox', 'Nintendo'], true)) return null;
            // "Consolas" (genérica) revisa siempre la marca/título real, por si quedó mal repartida
            // en una corrida anterior en vez de ir al botón específico que le corresponde.
            if (str_contains($t, 'x box') || str_contains($t, 'xbox') || 'Xbox' === $marca_actual) return ['Gaming', 'Xbox'];
            if (str_contains($t, 'nintendo') || 'Nintendo' === $marca_actual) return ['Gaming', 'Nintendo'];
            if (str_starts_with($t, 'play') || 'PlayStation' === $marca_actual) return ['Gaming', 'PlayStation'];
            return ['Gaming', 'Consolas'];
        }

        if (in_array($cat_raiz, ['Pantallas', 'Hogar', 'Televisores'], true)) {
            if ('Proyectores' === $subcat_actual || str_contains($t, 'proyector')) return ['Hogar', 'Proyectores'];
            if ('Hogar' === $cat_raiz && 'Televisores' === $subcat_actual) return null;
            return ['Hogar', 'Televisores'];
        }

        if ('Movilidad' === $cat_raiz) {
            return ['Movilidad', 'Patineta'];
        }

        if (in_array($cat_raiz, ['Accesorios Varios', 'Artículos Varios'], true)) {
            // Relojes que todavía estén crudos acá (la vieja categoría "Accesorios Varios",
            // de antes de la primera reorganización) también se mudan a Reloj.
            if ('Garmin' === $marca_actual) return ['Reloj', 'Garmin'];
            if ('Samsung Watch' === $marca_actual) return ['Reloj', 'Samsung Watch'];
            // Las impresoras (y a futuro mouse, fundas, etc.) van al botón "Accesorios" de
            // Ordenadores, no a Artículos Varios — el cliente quiere todos los accesorios de PC juntos ahí.
            if ('Impresoras' === $subcat_actual || 'Impresoras' === $marca_actual) {
                return ['Ordenadores', 'Accesorios'];
            }
            return null;
        }

        return null;
    }

    /** Palabras clave para extraer la marca real del título cuando la marca cargada no sirve (ej. "Varios"). */
    private static function extraer_marca($titulo) {
        $mapa = ['SAMSUNG' => 'Samsung', 'XIAOMI' => 'Xiaomi', 'LENOVO' => 'Lenovo', 'CIDEA' => 'C Idea', 'AMAZON' => 'Amazon'];
        $t = mb_strtoupper($titulo);
        foreach ($mapa as $palabra => $marca) {
            if (str_contains($t, $palabra)) {
                return $marca;
            }
        }
        return null;
    }

    private static function analizar() {
        $query = new WP_Query(['post_type' => 'articulo', 'post_status' => 'publish', 'posts_per_page' => -1]);
        $cambios = [];

        foreach ($query->posts as $post) {
            $terminos = wp_get_post_terms($post->ID, 'categoria_producto');
            if (empty($terminos) || is_wp_error($terminos)) {
                continue;
            }
            $actual = $terminos[0];
            $raiz = CLC_Post_Type::categoria_raiz($actual);
            $subcat_actual = ($actual->term_id !== $raiz->term_id) ? $actual->name : null;

            $marcas = wp_get_post_terms($post->ID, 'marca_producto', ['fields' => 'names']);
            $marca_actual = $marcas[0] ?? '';

            $plan = self::clasificar($raiz->name, $subcat_actual, $marca_actual, $post->post_title);
            if (!$plan) {
                continue;
            }
            [$cat_nueva, $subcat_nueva] = $plan;

            if ($raiz->name === $cat_nueva && $subcat_actual === $subcat_nueva) {
                continue;
            }

            $cambios[] = [
                'post_id' => $post->ID,
                'titulo' => $post->post_title,
                'antes' => trim($raiz->name . ' / ' . ($subcat_actual ?: '') . ' / ' . $marca_actual, ' /'),
                'categoria_nueva' => $cat_nueva,
                'subcategoria_nueva' => $subcat_nueva,
            ];
        }

        return $cambios;
    }

    /**
     * Busca (o crea) un término dentro de un PADRE puntual — no alcanza con buscar por nombre
     * solo, porque varias categorías comparten nombres de subcategoría (ej. "Accesorios" existe
     * en Celulares Y en Ordenadores) y WordPress permite nombres repetidos bajo padres distintos.
     * Buscar solo por nombre reutilizaba por error el término de otra categoría.
     */
    private static function obtener_o_crear_termino($nombre, $padre_id = 0) {
        $existentes = get_terms([
            'taxonomy' => 'categoria_producto',
            'name' => $nombre,
            'parent' => $padre_id,
            'hide_empty' => false,
        ]);
        if (!is_wp_error($existentes) && !empty($existentes)) {
            return $existentes[0];
        }
        $insertado = wp_insert_term($nombre, 'categoria_producto', ['parent' => $padre_id]);
        if (is_wp_error($insertado)) {
            // Puede fallar si ya existe un término con ese slug exacto en otro padre; WP
            // igual permite nombres repetidos, así que buscamos de nuevo por si se creó justo antes.
            $reintento = get_terms(['taxonomy' => 'categoria_producto', 'name' => $nombre, 'parent' => $padre_id, 'hide_empty' => false]);
            return (!is_wp_error($reintento) && !empty($reintento)) ? $reintento[0] : null;
        }
        return get_term($insertado['term_id'], 'categoria_producto');
    }

    /** Crea todo el árbol de categorías/subcategorías del cliente, aunque todavía no tengan productos. */
    private static function crear_arbol() {
        foreach (self::ARBOL as $categoria => $subcategorias) {
            $padre = self::obtener_o_crear_termino($categoria, 0);
            foreach ($subcategorias as $sub) {
                self::obtener_o_crear_termino($sub, $padre->term_id);
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
            <h1>Reorganizar categorías — planilla nueva del cliente</h1>

            <?php if (isset($_GET['aplicado'])): ?>
                <div class="notice notice-success"><p><?php echo esc_html(urldecode($_GET['aplicado'])); ?></p></div>
            <?php endif; ?>

            <p>Crea las 10 categorías nuevas (Audio, Celulares, Gaming, Hogar, Movilidad, Ordenadores, Reloj,
            Fotografía y Filmación, Artículos Varios, Belleza y Cuidado) con sus subcategorías, y reclasifica
            los artículos existentes — pasa de 3 niveles (categoría/subcategoría/marca) a 2 (categoría/subcategoría).
            La marca de cada artículo no se toca ni se pierde, solo deja de ser un botón de navegación aparte.</p>

            <?php if (empty($cambios)): ?>
                <div class="notice notice-info"><p>No hay cambios pendientes.</p></div>
            <?php else: ?>
                <p><strong><?php echo count($cambios); ?></strong> artículos van a cambiar de categoría/subcategoría:</p>
                <table class="wp-list-table widefat fixed striped" style="margin-bottom:20px;">
                    <thead><tr><th>Artículo</th><th>Antes</th><th>Después</th></tr></thead>
                    <tbody>
                        <?php foreach ($cambios as $c): ?>
                            <tr>
                                <td><?php echo esc_html($c['titulo']); ?></td>
                                <td><?php echo esc_html($c['antes']); ?></td>
                                <td><strong><?php echo esc_html(implode(' / ', array_filter([$c['categoria_nueva'], $c['subcategoria_nueva']]))); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="clc_migracion2_aplicar">
                    <?php wp_nonce_field('clc_migracion2_aplicar', 'clc_migracion2_nonce'); ?>
                    <button type="submit" class="button button-primary" onclick="return confirm('¿Aplicar estos <?php echo count($cambios); ?> cambios? Se recomienda haber bajado un backup antes (Artículos → Backup).');">Aplicar estos <?php echo count($cambios); ?> cambios</button>
                </form>
            <?php endif; ?>

            <?php $viejas = self::categorias_viejas_candidatas(); ?>
            <?php if (!empty($viejas)): ?>
                <h2 style="margin-top:30px;">Categorías y subcategorías viejas para limpiar</h2>
                <p>Categorías o subcategorías que ya no están en la planilla nueva (quedaron de una reorganización
                anterior — ej. "Notebooks" en plural, duplicada con la "Notebook" nueva). Borrala solo cuando diga
                <strong>0 artículos</strong> — si todavía tiene productos, primero volvé a tocar "Aplicar" arriba
                para terminar de moverlos.</p>
                <table class="wp-list-table widefat fixed striped" style="max-width:700px;">
                    <thead><tr><th>Categoría / Subcategoría</th><th style="width:110px;">Artículos</th><th style="width:120px;"></th></tr></thead>
                    <tbody>
                        <?php foreach ($viejas as $v): ?>
                            <tr>
                                <td><?php echo esc_html($v['ruta']); ?></td>
                                <td><?php echo (int) $v['cantidad']; ?></td>
                                <td>
                                    <?php if (0 === $v['cantidad']): ?>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('¿Borrar la categoría <?php echo esc_js($v['termino']->name); ?>?');">
                                            <input type="hidden" name="action" value="clc_migracion2_borrar">
                                            <input type="hidden" name="term_id" value="<?php echo esc_attr($v['termino']->term_id); ?>">
                                            <?php wp_nonce_field('clc_migracion2_borrar', 'clc_migracion2_borrar_nonce'); ?>
                                            <button type="submit" class="button button-link-delete">Borrar</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color:#999;">tiene productos</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function aplicar() {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        check_admin_referer('clc_migracion2_aplicar', 'clc_migracion2_nonce');
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        self::crear_arbol();
        $cambios = self::analizar();

        // Cache de términos ya resueltos en esta corrida, para no repetir la búsqueda por
        // cada uno de los ~300 artículos (evita quedarse sin tiempo en hostings más lentos).
        $cache_terminos = [];
        $resolver = function ($nombre, $padre_id) use (&$cache_terminos) {
            $clave = $padre_id . '|' . $nombre;
            if (!isset($cache_terminos[$clave])) {
                $cache_terminos[$clave] = self::obtener_o_crear_termino($nombre, $padre_id);
            }
            return $cache_terminos[$clave];
        };

        foreach ($cambios as $c) {
            $padre = $resolver($c['categoria_nueva'], 0);
            $destino = $c['subcategoria_nueva'] ? $resolver($c['subcategoria_nueva'], $padre->term_id) : $padre;
            wp_set_object_terms($c['post_id'], [(int) $destino->term_id], 'categoria_producto');
        }

        if (class_exists('CLC_Cache')) {
            CLC_Cache::limpiar_si_corresponde();
        }

        $mensaje = count($cambios) . ' artículos reclasificados a la estructura nueva.';
        wp_safe_redirect(add_query_arg('aplicado', urlencode($mensaje), admin_url('edit.php?post_type=articulo&page=clc-migracion2')));
        exit;
    }

    private static function contar_directo($term_id) {
        $cantidad = new WP_Query([
            'post_type' => 'articulo',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'tax_query' => [['taxonomy' => 'categoria_producto', 'field' => 'term_id', 'terms' => $term_id, 'include_children' => false]],
        ]);
        return $cantidad->found_posts;
    }

    /**
     * Categorías/subcategorías que ya no están en la planilla nueva — candidatas a borrar una
     * vez vacías. Incluye tanto categorías de primer nivel viejas (ej. "Relojes", "Pantallas")
     * como subcategorías huérfanas de una categoría que sí sigue existiendo (ej. "Notebooks"/
     * "Tablets"/"MacBooks", en plural, hijas de Ordenadores, duplicadas con las nuevas en singular).
     */
    private static function categorias_viejas_candidatas() {
        $nombres_nuevos = array_keys(self::ARBOL);
        $candidatas = [];

        $top = get_terms(['taxonomy' => 'categoria_producto', 'parent' => 0, 'hide_empty' => false]);
        if (is_wp_error($top)) {
            return [];
        }

        foreach ($top as $termino) {
            if (!in_array($termino->name, $nombres_nuevos, true)) {
                // Categoría de primer nivel vieja entera (ej. "Relojes", "Pantallas", "Accesorios Varios").
                $candidatas[] = ['termino' => $termino, 'cantidad' => self::contar_directo($termino->term_id), 'ruta' => $termino->name];
                continue;
            }

            // Es una categoría válida: revisamos sus hijos por subcategorías viejas que no estén
            // en la lista de subcategorías esperada para esa categoría.
            $subcategorias_validas = self::ARBOL[$termino->name];
            $hijos = get_terms(['taxonomy' => 'categoria_producto', 'parent' => $termino->term_id, 'hide_empty' => false]);
            if (is_wp_error($hijos)) {
                continue;
            }
            foreach ($hijos as $hijo) {
                if (!in_array($hijo->name, $subcategorias_validas, true)) {
                    $candidatas[] = ['termino' => $hijo, 'cantidad' => self::contar_directo($hijo->term_id), 'ruta' => $termino->name . ' / ' . $hijo->name];
                }
            }
        }

        return $candidatas;
    }

    public static function borrar_categoria_vieja() {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        check_admin_referer('clc_migracion2_borrar', 'clc_migracion2_borrar_nonce');

        $term_id = (int) ($_POST['term_id'] ?? 0);
        $termino = get_term($term_id, 'categoria_producto');
        if ($termino && !is_wp_error($termino)) {
            wp_delete_term($term_id, 'categoria_producto');
        }

        wp_safe_redirect(add_query_arg('aplicado', urlencode('Categoría vieja eliminada.'), admin_url('edit.php?post_type=articulo&page=clc-migracion2')));
        exit;
    }
}
