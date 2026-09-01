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
        'Artículos Varios' => ['Impresoras'],
        'Belleza y Cuidado' => ['Planchita', 'Secador', 'Kit Modelador', 'Caballeros', 'Varios'],
    ];

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_post_clc_migracion2_aplicar', [__CLASS__, 'aplicar']);
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

        if ('Ordenadores' === $cat_raiz) {
            if ('MacBooks' === $subcat_actual) {
                return ['Ordenadores', 'MacBook'];
            }
            if ('iPads' === $subcat_actual) {
                return ['Ordenadores', 'Tablet'];
            }
            if ('Tablets' === $subcat_actual) {
                return ['Ordenadores', ('Samsung' === $marca_actual) ? 'Samsung' : 'Tablet'];
            }
            if ('Notebooks' === $subcat_actual) {
                $marcas_con_boton = ['Lenovo', 'MSI', 'HP', 'Asus', 'Acer', 'Dell', 'Alienware'];
                return ['Ordenadores', in_array($marca_actual, $marcas_con_boton, true) ? $marca_actual : 'Notebook'];
            }
            return ['Ordenadores', 'Notebook'];
        }

        if ('Celulares' === $cat_raiz) {
            $mapa = ['MOTOROLA' => 'Motorola', 'Motorola' => 'Motorola'];
            $marca = $mapa[$marca_actual] ?? $marca_actual;
            return ['Celulares', $marca ?: 'Accesorios'];
        }

        if ('Relojes' === $cat_raiz) {
            if ('Garmin' === $marca_actual) return ['Reloj', 'Garmin'];
            if ('Apple' === $marca_actual) return ['Reloj', 'Apple Watch'];
            if ('Samsung' === $marca_actual) return ['Reloj', 'Samsung Watch'];
            return ['Reloj', 'Varios'];
        }

        if ('Audio' === $cat_raiz) {
            if ('Parlantes' === $subcat_actual) return ['Audio', 'Parlantes'];
            if ('Auriculares' === $subcat_actual) return ['Audio', 'Auriculares'];
            return ['Audio', 'Varios'];
        }

        if ('Gaming' === $cat_raiz) {
            if ('PlayStation' === $marca_actual) return ['Gaming', 'PlayStation'];
            if ('Xbox' === $marca_actual) return ['Gaming', 'Xbox'];
            if ('Nintendo' === $marca_actual) return ['Gaming', 'Nintendo'];
            return ['Gaming', 'Consolas'];
        }

        if ('Pantallas' === $cat_raiz) {
            if ('Proyectores' === $subcat_actual) return ['Hogar', 'Proyectores'];
            return ['Hogar', 'Televisores'];
        }

        if ('Movilidad' === $cat_raiz) {
            return ['Movilidad', 'Patineta'];
        }

        if ('Accesorios Varios' === $cat_raiz) {
            if ('Impresoras' === $subcat_actual) {
                return ['Artículos Varios', 'Impresoras'];
            }
            return null;
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

    private static function obtener_o_crear_termino($nombre, $padre_id = 0) {
        $existente = get_term_by('name', $nombre, 'categoria_producto');
        if ($existente) {
            return $existente;
        }
        $insertado = wp_insert_term($nombre, 'categoria_producto', ['parent' => $padre_id]);
        if (is_wp_error($insertado)) {
            return get_term_by('name', $nombre, 'categoria_producto');
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
                                <td><strong><?php echo esc_html($c['categoria_nueva'] . ' / ' . $c['subcategoria_nueva']); ?></strong></td>
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
        </div>
        <?php
    }

    public static function aplicar() {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        check_admin_referer('clc_migracion2_aplicar', 'clc_migracion2_nonce');

        self::crear_arbol();
        $cambios = self::analizar();

        foreach ($cambios as $c) {
            $padre = self::obtener_o_crear_termino($c['categoria_nueva'], 0);
            $subcat = self::obtener_o_crear_termino($c['subcategoria_nueva'], $padre->term_id);
            wp_set_object_terms($c['post_id'], [(int) $subcat->term_id], 'categoria_producto');
        }

        if (class_exists('CLC_Cache')) {
            CLC_Cache::limpiar_si_corresponde();
        }

        $mensaje = count($cambios) . ' artículos reclasificados a la estructura nueva.';
        wp_safe_redirect(add_query_arg('aplicado', urlencode($mensaje), admin_url('edit.php?post_type=articulo&page=clc-migracion2')));
        exit;
    }
}
