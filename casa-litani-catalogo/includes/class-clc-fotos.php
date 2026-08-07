<?php
if (!defined('ABSPATH')) exit;

/**
 * Pantalla "Artículos → Fotos": mismo patrón que "Recetas Argentinas → Fotos de recetas"
 * en ratatuin.com.ar. Lista todos los artículos con su estado de foto, con:
 * - botón "Cargar N fotos ahora" (tanda manual vía Pexels)
 * - botón "Activar auto-cargado" (tandas chicas cada 3 min, mientras la pestaña esté abierta)
 * - subida manual de una foto propia, artículo por artículo, como respaldo
 * - tanda grande automática cada hora por WP-Cron, aunque no haya nadie mirando el admin
 */
class CLC_Fotos {

    const POR_PAGINA = 30;

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_post_clc_subir_foto', [__CLASS__, 'guardar_foto_manual']);
        add_action('admin_post_clc_restaurar_foto_anterior', [__CLASS__, 'restaurar_foto_anterior']);
        add_action('admin_post_clc_guardar_descripcion', [__CLASS__, 'guardar_descripcion_manual']);
        add_action('admin_post_clc_marcar_verificado', [__CLASS__, 'marcar_verificado']);
        add_action('wp_ajax_clc_pexels_tanda', [__CLASS__, 'ajax_tanda']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue']);
    }

    /** Guarda la foto actual (si hay) en un historial antes de reemplazarla, para poder deshacer. */
    private static function archivar_foto_anterior($post_id) {
        $actual = get_post_thumbnail_id($post_id);
        if (!$actual) {
            return;
        }
        $historial = get_post_meta($post_id, '_clc_historial_fotos', true);
        if (!is_array($historial)) {
            $historial = [];
        }
        array_unshift($historial, $actual);
        $historial = array_slice(array_unique($historial), 0, 5);
        update_post_meta($post_id, '_clc_historial_fotos', $historial);
    }

    public static function add_menu() {
        add_submenu_page(
            'edit.php?post_type=articulo',
            'Fotos y descripciones de artículos',
            'Fotos y descripciones',
            'manage_options',
            'clc-fotos',
            [__CLASS__, 'render_page']
        );
    }

    public static function enqueue($hook) {
        if ('articulo_page_clc-fotos' !== $hook) {
            return;
        }
        $sin_foto = self::contar_sin_foto();
        $total = wp_count_posts('articulo')->publish;
        wp_enqueue_script('jquery');
        wp_add_inline_script('jquery-core', 'window.CLC_PEXELS_AUTO = ' . wp_json_encode([
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('clc_pexels_tanda'),
            'intervaloMs' => 3 * 60 * 1000,
            'conFoto' => (int) $total - (int) $sin_foto,
            'total' => (int) $total,
        ]) . ';');
    }

    private static function contar_sin_foto() {
        $query = new WP_Query([
            'post_type' => 'articulo',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [['key' => '_thumbnail_id', 'compare' => 'NOT EXISTS']],
            'fields' => 'ids',
        ]);
        return $query->found_posts;
    }

    public static function ajax_tanda() {
        if (!current_user_can('manage_options') || !check_ajax_referer('clc_pexels_tanda', 'nonce', false)) {
            wp_send_json_error(['error' => 'No autorizado.'], 403);
        }
        $resumen = CLC_Pexels::procesar_tanda(CLC_Pexels::POR_TANDA_AUTO);
        wp_send_json_success($resumen);
    }

    public static function guardar_foto_manual() {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        check_admin_referer('clc_subir_foto', 'clc_foto_nonce');

        $post_id = (int) ($_POST['clc_post_id'] ?? 0);
        if (!$post_id) {
            wp_die('Falta el artículo');
        }

        if (!empty($_POST['clc_quitar_foto'])) {
            delete_post_thumbnail($post_id);
            wp_safe_redirect(add_query_arg('guardado', '1', wp_get_referer() ?: admin_url('edit.php?post_type=articulo&page=clc-fotos')));
            exit;
        }

        if (empty($_FILES['clc_foto']['name'])) {
            wp_safe_redirect(add_query_arg('error', 'sinarchivo', wp_get_referer() ?: admin_url('edit.php?post_type=articulo&page=clc-fotos')));
            exit;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $adjunto_id = media_handle_upload('clc_foto', $post_id);
        if (is_wp_error($adjunto_id)) {
            wp_safe_redirect(add_query_arg('error', 'upload', wp_get_referer() ?: admin_url('edit.php?post_type=articulo&page=clc-fotos')));
            exit;
        }

        self::archivar_foto_anterior($post_id);
        set_post_thumbnail($post_id, $adjunto_id);
        delete_post_meta($post_id, '_clc_pexels_fotografo');
        delete_post_meta($post_id, '_clc_pexels_fotografo_url');
        update_post_meta($post_id, '_clc_estado', 'activo');

        wp_safe_redirect(add_query_arg('guardado', '1', wp_get_referer() ?: admin_url('edit.php?post_type=articulo&page=clc-fotos')));
        exit;
    }

    /** Restaura la foto anterior guardada en el historial (deshacer un reemplazo). */
    public static function restaurar_foto_anterior() {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        check_admin_referer('clc_restaurar_foto_anterior', 'clc_restaurar_foto_nonce');

        $post_id = (int) ($_POST['clc_post_id'] ?? 0);
        if (!$post_id) {
            wp_die('Falta el artículo');
        }

        $historial = get_post_meta($post_id, '_clc_historial_fotos', true);
        if (!is_array($historial) || empty($historial)) {
            wp_safe_redirect(add_query_arg('error', 'sinhistorial', wp_get_referer() ?: admin_url('edit.php?post_type=articulo&page=clc-fotos')));
            exit;
        }

        $anterior_id = array_shift($historial);
        update_post_meta($post_id, '_clc_historial_fotos', $historial);
        set_post_thumbnail($post_id, $anterior_id);

        wp_safe_redirect(add_query_arg('guardado', '1', wp_get_referer() ?: admin_url('edit.php?post_type=articulo&page=clc-fotos')));
        exit;
    }

    /** Marca/desmarca un artículo como "verificado" — excluido de la carga automática por Pexels. */
    public static function marcar_verificado() {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        check_admin_referer('clc_marcar_verificado', 'clc_verificado_nonce');

        $post_id = (int) ($_POST['clc_post_id'] ?? 0);
        if (!$post_id) {
            wp_die('Falta el artículo');
        }

        if (!empty($_POST['clc_verificado'])) {
            update_post_meta($post_id, '_clc_verificado', '1');
        } else {
            delete_post_meta($post_id, '_clc_verificado');
        }

        wp_safe_redirect(add_query_arg('guardado', '1', wp_get_referer() ?: admin_url('edit.php?post_type=articulo&page=clc-fotos')));
        exit;
    }

    /** Guarda la descripción editada a mano en la tabla, o la regenera automáticamente si se pidió. */
    public static function guardar_descripcion_manual() {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        check_admin_referer('clc_guardar_descripcion', 'clc_desc_nonce');

        $post_id = (int) ($_POST['clc_post_id'] ?? 0);
        if (!$post_id) {
            wp_die('Falta el artículo');
        }

        if (!empty($_POST['clc_regenerar'])) {
            $descripcion = CLC_Descripciones::generar_descripcion($post_id);
        } else {
            $descripcion = sanitize_textarea_field($_POST['clc_descripcion'] ?? '');
        }

        wp_update_post([
            'ID' => $post_id,
            'post_content' => $descripcion,
        ]);
        update_post_meta($post_id, '_clc_descripcion_generada', '1');

        wp_safe_redirect(add_query_arg('guardado', '1', wp_get_referer() ?: admin_url('edit.php?post_type=articulo&page=clc-fotos')));
        exit;
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['clc_pexels_action']) && 'tanda' === $_POST['clc_pexels_action']
            && check_admin_referer('clc_pexels_tanda', 'clc_pexels_nonce', false)) {
            CLC_Pexels::procesar_tanda(CLC_Pexels::POR_TANDA_MANUAL);
            wp_safe_redirect(add_query_arg('corrida', '1', remove_query_arg(['guardado', 'error', 'corrida'])));
            exit;
        }

        $q = isset($_GET['clc_q']) ? sanitize_text_field(wp_unslash($_GET['clc_q'])) : '';
        $solo_sin = isset($_GET['clc_sin_foto']) && '1' === $_GET['clc_sin_foto'];
        $paged = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);

        $args = [
            'post_type' => 'articulo',
            'post_status' => 'publish',
            'posts_per_page' => self::POR_PAGINA,
            'paged' => $paged,
            's' => $q,
        ];
        if ($solo_sin) {
            $args['meta_query'] = [['key' => '_thumbnail_id', 'compare' => 'NOT EXISTS']];
        }
        $query = new WP_Query($args);

        $total_articulos = wp_count_posts('articulo')->publish;
        $sin_foto = self::contar_sin_foto();
        $con_foto = $total_articulos - $sin_foto;
        ?>
        <div class="wrap">
            <h1>Artículos — Fotos y descripciones</h1>

            <?php if (isset($_GET['guardado'])): ?>
                <div class="notice notice-success is-dismissible"><p>Guardado.</p></div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div class="notice notice-error is-dismissible"><p>No se pudo subir la foto.</p></div>
            <?php endif; ?>

            <p><strong><?php echo esc_html($con_foto); ?></strong> de <strong><?php echo esc_html($total_articulos); ?></strong> artículos ya tienen foto.</p>

            <div class="notice notice-info" style="padding:12px;">
                <p><strong>Fotos automáticas por Pexels.</strong> El sistema completa fotos solo, de a tandas grandes, cada una hora. Si preferís no esperar, tocá "Cargar 15 fotos ahora" o activá el auto-cargado, que va pidiendo tandas chicas cada 3 minutos solo, sin que hagas nada — dejá esta pestaña abierta y listo.</p>
                <form method="post" style="margin-bottom:8px;display:inline-block;margin-right:10px;">
                    <?php wp_nonce_field('clc_pexels_tanda', 'clc_pexels_nonce'); ?>
                    <input type="hidden" name="clc_pexels_action" value="tanda">
                    <button type="submit" class="button button-primary">Cargar 15 fotos ahora</button>
                </form>
                <button type="button" id="clc-pexels-auto-btn" class="button">▶ Activar auto-cargado (cada 3 min)</button>
                <p id="clc-pexels-auto-estado" style="margin-top:8px;color:#2271b1;"></p>
                <?php if (isset($_GET['corrida'])):
                    $r = get_option('clc_pexels_ultimo_resultado', []); ?>
                    <?php if (!empty($r)): ?>
                        <p>Revisados: <strong><?php echo (int) ($r['revisadas'] ?? 0); ?></strong> —
                        Fotos cargadas: <strong><?php echo (int) ($r['cargadas'] ?? 0); ?></strong> —
                        Sin resultado: <strong><?php echo (int) ($r['sin_match'] ?? 0); ?></strong></p>
                        <?php if (!empty($r['error'])): ?>
                            <p style="color:#a00;"><?php echo esc_html($r['error']); ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <form method="get" style="margin-bottom:14px;display:flex;gap:10px;align-items:center;">
                <input type="hidden" name="post_type" value="articulo">
                <input type="hidden" name="page" value="clc-fotos">
                <input type="search" name="clc_q" value="<?php echo esc_attr($q); ?>" placeholder="Buscar por nombre...">
                <label><input type="checkbox" name="clc_sin_foto" value="1" <?php checked($solo_sin); ?> onchange="this.form.submit()"> Solo sin foto</label>
                <button type="submit" class="button">Buscar</button>
            </form>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:70px;">Foto</th>
                        <th style="width:150px;">Artículo</th>
                        <th style="width:120px;">Categoría / Marca</th>
                        <th style="width:250px;">Acción foto</th>
                        <th>Descripción</th>
                        <th style="width:90px;">Verificado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($query->have_posts()): while ($query->have_posts()): $query->the_post();
                        $post_id = get_the_ID();
                        $foto_url = get_the_post_thumbnail_url($post_id, 'thumbnail');
                        $categorias = wp_get_post_terms($post_id, 'categoria_producto', ['fields' => 'names']);
                        $marcas = wp_get_post_terms($post_id, 'marca_producto', ['fields' => 'names']);
                        $descripcion_actual = get_post_field('post_content', $post_id);
                        $verificado = (bool) get_post_meta($post_id, '_clc_verificado', true);
                        $historial = get_post_meta($post_id, '_clc_historial_fotos', true);
                        $tiene_historial = is_array($historial) && !empty($historial);
                        ?>
                        <tr>
                            <td>
                                <?php if ($foto_url): ?>
                                    <img src="<?php echo esc_url($foto_url); ?>" style="width:60px;height:60px;object-fit:cover;border-radius:6px;">
                                <?php else: ?>
                                    <div style="width:60px;height:60px;border-radius:6px;background:#f1e6da;display:flex;align-items:center;justify-content:center;font-size:1.4rem;">📦</div>
                                <?php endif; ?>
                            </td>
                            <td><?php the_title(); ?></td>
                            <td><?php echo esc_html(implode(' / ', array_filter([$categorias[0] ?? '', $marcas[0] ?? '']))); ?></td>
                            <td>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                                    <input type="hidden" name="action" value="clc_subir_foto">
                                    <?php wp_nonce_field('clc_subir_foto', 'clc_foto_nonce'); ?>
                                    <input type="hidden" name="clc_post_id" value="<?php echo esc_attr($post_id); ?>">
                                    <input type="file" name="clc_foto" accept="image/png,image/jpeg" style="max-width:150px;">
                                    <button type="submit" class="button button-primary">Subir</button>
                                </form>
                                <?php if ($foto_url): ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:4px;display:inline-block;">
                                        <input type="hidden" name="action" value="clc_subir_foto">
                                        <?php wp_nonce_field('clc_subir_foto', 'clc_foto_nonce'); ?>
                                        <input type="hidden" name="clc_post_id" value="<?php echo esc_attr($post_id); ?>">
                                        <input type="hidden" name="clc_quitar_foto" value="1">
                                        <button type="submit" class="button button-link-delete">Quitar foto</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($tiene_historial): ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:4px;display:inline-block;">
                                        <input type="hidden" name="action" value="clc_restaurar_foto_anterior">
                                        <?php wp_nonce_field('clc_restaurar_foto_anterior', 'clc_restaurar_foto_nonce'); ?>
                                        <input type="hidden" name="clc_post_id" value="<?php echo esc_attr($post_id); ?>">
                                        <button type="submit" class="button" title="Vuelve a la foto que tenía antes del último reemplazo">↺ Deshacer foto</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                    <input type="hidden" name="action" value="clc_guardar_descripcion">
                                    <?php wp_nonce_field('clc_guardar_descripcion', 'clc_desc_nonce'); ?>
                                    <input type="hidden" name="clc_post_id" value="<?php echo esc_attr($post_id); ?>">
                                    <textarea name="clc_descripcion" maxlength="500" rows="3" style="width:100%;font-size:12px;" placeholder="Sin descripción todavía..."><?php echo esc_textarea($descripcion_actual); ?></textarea>
                                    <div style="display:flex;gap:6px;margin-top:4px;align-items:center;">
                                        <button type="submit" class="button button-primary">Guardar</button>
                                        <button type="submit" name="clc_regenerar" value="1" class="button" title="Genera de nuevo con marca/modelo/specs, pisa lo que esté escrito">↻ Regenerar</button>
                                        <span style="font-size:11px;color:#999;"><?php echo esc_html(mb_strlen($descripcion_actual)); ?>/500</span>
                                    </div>
                                </form>
                            </td>
                            <td>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                    <input type="hidden" name="action" value="clc_marcar_verificado">
                                    <?php wp_nonce_field('clc_marcar_verificado', 'clc_verificado_nonce'); ?>
                                    <input type="hidden" name="clc_post_id" value="<?php echo esc_attr($post_id); ?>">
                                    <label style="display:flex;align-items:center;gap:4px;font-size:11px;">
                                        <input type="checkbox" name="clc_verificado" value="1" onchange="this.form.submit()" <?php checked($verificado); ?>>
                                        ✔ Listo
                                    </label>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; wp_reset_postdata(); else: ?>
                        <tr><td colspan="6">No hay artículos con este filtro.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($query->max_num_pages > 1): ?>
                <p style="margin-top:14px;">
                    <?php for ($p = 1; $p <= $query->max_num_pages; $p++): ?>
                        <a class="button <?php echo $p === $paged ? 'button-primary' : ''; ?>" href="<?php echo esc_url(add_query_arg('paged', $p)); ?>"><?php echo esc_html($p); ?></a>
                    <?php endfor; ?>
                </p>
            <?php endif; ?>
        </div>
        <script>
        (function() {
            var cfg = window.CLC_PEXELS_AUTO;
            var btn = document.getElementById('clc-pexels-auto-btn');
            var estado = document.getElementById('clc-pexels-auto-estado');
            if (!cfg || !btn) return;

            var conFoto = cfg.conFoto;
            var timer = null;

            function pedirTanda() {
                estado.textContent = 'Pidiendo una tanda a Pexels...';
                var datos = new FormData();
                datos.append('action', 'clc_pexels_tanda');
                datos.append('nonce', cfg.nonce);
                fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: datos })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (!res.success) {
                            estado.textContent = 'Error: ' + (res.data && res.data.error ? res.data.error : 'no autorizado.');
                            detener();
                            return;
                        }
                        var d = res.data;
                        conFoto += d.cargadas || 0;
                        estado.textContent = 'Cargadas ' + conFoto + ' de ' + cfg.total + ' artículos.' + (d.error ? ' (' + d.error + ')' : '') + ' Próxima tanda en 3 min...';
                        if (d.error && (d.error.indexOf('rechazada') !== -1 || d.error.indexOf('límite') !== -1)) {
                            estado.textContent = d.error + ' Auto-cargado detenido.';
                            detener();
                        }
                        if (conFoto >= cfg.total) {
                            estado.textContent = '¡Listo! Todos los artículos ya tienen foto.';
                            detener();
                        }
                    })
                    .catch(function () {
                        estado.textContent = 'Error de conexión, se reintenta en 3 min.';
                    });
            }

            function iniciar() {
                btn.textContent = '⏸ Auto-cargado activo (cada 3 min)';
                btn.classList.add('button-primary');
                pedirTanda();
                timer = setInterval(pedirTanda, cfg.intervaloMs);
            }

            function detener() {
                btn.textContent = '▶ Activar auto-cargado (cada 3 min)';
                btn.classList.remove('button-primary');
                if (timer) clearInterval(timer);
                timer = null;
            }

            btn.addEventListener('click', function () {
                if (timer) {
                    detener();
                    estado.textContent = 'Auto-cargado detenido.';
                } else {
                    iniciar();
                }
            });
        })();
        </script>
        <?php
    }
}
