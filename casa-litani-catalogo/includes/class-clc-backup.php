<?php
if (!defined('ABSPATH')) exit;

/**
 * Backup/restauración de artículos: descarga un .json con título, descripción,
 * categoría, marca y la foto (como URL + los bytes en base64) de cada artículo.
 * Pensado como red de seguridad manual antes de reimportar un Excel, reinstalar
 * el plugin, o tocar fotos en masa — se restaura por título, sin tocar nada
 * que no esté en el backup.
 */
class CLC_Backup {

    const CARPETA_AUTO = 'clc-backups';
    const MAX_AUTOMATICOS = 6;

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_post_clc_descargar_backup', [__CLASS__, 'descargar_backup']);
        add_action('admin_post_clc_restaurar_backup', [__CLASS__, 'restaurar_backup']);
        add_action('clc_backup_semanal', [__CLASS__, 'generar_backup_automatico']);
        add_action('wp', [__CLASS__, 'activar_cron_backup']);
        add_action('admin_post_clc_descargar_backup_auto', [__CLASS__, 'descargar_backup_auto']);
    }

    public static function activar_cron_backup() {
        if (!wp_next_scheduled('clc_backup_semanal')) {
            wp_schedule_event(time(), 'weekly', 'clc_backup_semanal');
        }
    }

    public static function desactivar_cron_backup() {
        wp_clear_scheduled_hook('clc_backup_semanal');
    }

    /** Arma el array con todos los artículos, foto incluida — usado por descarga manual y por el cron. */
    private static function armar_datos_backup() {
        $query = new WP_Query([
            'post_type' => 'articulo',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ]);

        $items = [];
        foreach ($query->posts as $post) {
            $post_id = $post->ID;
            $foto_base64 = null;
            $foto_mime = null;
            $thumb_id = get_post_thumbnail_id($post_id);
            if ($thumb_id) {
                $ruta = get_attached_file($thumb_id);
                if ($ruta && file_exists($ruta)) {
                    $foto_base64 = base64_encode(file_get_contents($ruta));
                    $foto_mime = get_post_mime_type($thumb_id) ?: 'image/jpeg';
                }
            }

            $items[] = [
                'titulo' => $post->post_title,
                'descripcion' => $post->post_content,
                'categoria' => wp_get_post_terms($post_id, 'categoria_producto', ['fields' => 'names']),
                'marca' => wp_get_post_terms($post_id, 'marca_producto', ['fields' => 'names']),
                'foto_base64' => $foto_base64,
                'foto_mime' => $foto_mime,
                'pexels_fotografo' => get_post_meta($post_id, '_clc_pexels_fotografo', true),
                'pexels_fotografo_url' => get_post_meta($post_id, '_clc_pexels_fotografo_url', true),
            ];
        }

        return ['generado' => gmdate('c'), 'articulos' => $items];
    }

    /** Corre por WP-Cron una vez por semana: guarda el backup en el servidor, sin depender de que alguien lo descargue. */
    public static function generar_backup_automatico() {
        $subida = wp_upload_dir();
        $carpeta = trailingslashit($subida['basedir']) . self::CARPETA_AUTO . '/';
        if (!file_exists($carpeta)) {
            wp_mkdir_p($carpeta);
        }

        $datos = self::armar_datos_backup();
        $archivo = $carpeta . 'clc-backup-' . gmdate('Y-m-d-His') . '.json';
        file_put_contents($archivo, wp_json_encode($datos));

        // Nos quedamos solo con los últimos N, para no llenar el disco del hosting.
        $existentes = glob($carpeta . 'clc-backup-*.json');
        if ($existentes && count($existentes) > self::MAX_AUTOMATICOS) {
            sort($existentes);
            $sobrantes = array_slice($existentes, 0, count($existentes) - self::MAX_AUTOMATICOS);
            foreach ($sobrantes as $viejo) {
                @unlink($viejo);
            }
        }
    }

    private static function listar_backups_automaticos() {
        $subida = wp_upload_dir();
        $carpeta = trailingslashit($subida['basedir']) . self::CARPETA_AUTO . '/';
        $archivos = glob($carpeta . 'clc-backup-*.json') ?: [];
        rsort($archivos);
        return $archivos;
    }

    public static function descargar_backup_auto() {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        check_admin_referer('clc_descargar_backup_auto', 'clc_backup_auto_nonce');

        $nombre = sanitize_file_name($_GET['archivo'] ?? '');
        $subida = wp_upload_dir();
        $ruta = trailingslashit($subida['basedir']) . self::CARPETA_AUTO . '/' . $nombre;

        if ('' === $nombre || !file_exists($ruta)) {
            wp_die('Backup no encontrado.');
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombre . '"');
        readfile($ruta);
        exit;
    }

    public static function add_menu() {
        add_submenu_page(
            'edit.php?post_type=articulo',
            'Backup de artículos',
            'Backup',
            'manage_options',
            'clc-backup',
            [__CLASS__, 'render_page']
        );
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>Backup de artículos</h1>

            <?php if (isset($_GET['restaurado'])): ?>
                <div class="notice notice-success"><p><?php echo esc_html(urldecode($_GET['restaurado'])); ?></p></div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div class="notice notice-error"><p><?php echo esc_html(urldecode($_GET['error'])); ?></p></div>
            <?php endif; ?>

            <h2>Descargar backup</h2>
            <p>Genera un archivo .json con título, categoría, marca, descripción y la foto de cada artículo publicado
            (la foto se incluye adentro del archivo, no hace falta guardar las imágenes aparte). Guardalo en tu PC
            antes de reimportar un Excel grande o reinstalar el plugin.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="clc_descargar_backup">
                <?php wp_nonce_field('clc_descargar_backup', 'clc_backup_nonce'); ?>
                <button type="submit" class="button button-primary">Descargar backup ahora</button>
            </form>

            <h2 style="margin-top:30px;">Restaurar desde un backup</h2>
            <p>Subí un .json generado con el botón de arriba (o uno de los automáticos de abajo). Por cada artículo
            del backup que coincida por título con uno que existe hoy, restaura su foto y descripción — no crea
            artículos nuevos ni toca los que no estén en el archivo.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="clc_restaurar_backup">
                <?php wp_nonce_field('clc_restaurar_backup', 'clc_restaurar_nonce'); ?>
                <p><input type="file" name="clc_backup_json" accept=".json" required></p>
                <p><button type="submit" class="button button-primary" onclick="return confirm('¿Restaurar fotos y descripciones desde este backup? Va a pisar la foto/descripción actual de los artículos que coincidan por título.');">Restaurar</button></p>
            </form>

            <h2 style="margin-top:30px;">Backups automáticos (uno por semana, sin que hagas nada)</h2>
            <p>El sistema guarda uno solo en el servidor cada semana y conserva los últimos <?php echo (int) self::MAX_AUTOMATICOS; ?>. Descargalo acá si necesitás volver a un punto anterior.</p>
            <?php $automaticos = self::listar_backups_automaticos(); ?>
            <?php if (empty($automaticos)): ?>
                <p><em>Todavía no se generó ninguno (se crea solo, una vez por semana).</em></p>
            <?php else: ?>
                <ul>
                    <?php foreach ($automaticos as $ruta):
                        $nombre = basename($ruta);
                        $fecha = date_i18n('d/m/Y H:i', filemtime($ruta));
                        $url = wp_nonce_url(add_query_arg(['action' => 'clc_descargar_backup_auto', 'archivo' => $nombre], admin_url('admin-post.php')), 'clc_descargar_backup_auto', 'clc_backup_auto_nonce');
                        ?>
                        <li><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($nombre); ?></a> — <?php echo esc_html($fecha); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function descargar_backup() {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        check_admin_referer('clc_descargar_backup', 'clc_backup_nonce');

        $datos = self::armar_datos_backup();
        $nombre_archivo = 'clc-backup-' . gmdate('Y-m-d-His') . '.json';
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
        echo wp_json_encode($datos);
        exit;
    }

    public static function restaurar_backup() {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        check_admin_referer('clc_restaurar_backup', 'clc_restaurar_nonce');

        if (empty($_FILES['clc_backup_json']['tmp_name'])) {
            self::redirigir_error('No se recibió ningún archivo.');
        }

        $contenido = file_get_contents($_FILES['clc_backup_json']['tmp_name']);
        $datos = json_decode($contenido, true);
        if (!$datos || empty($datos['articulos'])) {
            self::redirigir_error('El archivo no es un backup válido.');
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $restaurados = 0;
        $sin_coincidencia = 0;

        foreach ($datos['articulos'] as $item) {
            $titulo = $item['titulo'] ?? '';
            if ('' === $titulo) continue;

            $existente = get_page_by_title($titulo, OBJECT, 'articulo');
            if (!$existente) {
                $sin_coincidencia++;
                continue;
            }
            $post_id = $existente->ID;

            if (!empty($item['descripcion'])) {
                wp_update_post(['ID' => $post_id, 'post_content' => $item['descripcion']]);
            }

            if (!empty($item['foto_base64'])) {
                $bytes = base64_decode($item['foto_base64']);
                $extension = (strpos((string) ($item['foto_mime'] ?? ''), 'png') !== false) ? 'png' : 'jpg';
                $tmp = wp_tempnam($titulo . '.' . $extension);
                file_put_contents($tmp, $bytes);

                $archivo = ['name' => sanitize_file_name($titulo . '.' . $extension), 'tmp_name' => $tmp];
                $adjunto_id = media_handle_sideload($archivo, $post_id, $titulo);
                if (!is_wp_error($adjunto_id)) {
                    set_post_thumbnail($post_id, $adjunto_id);
                    if (!empty($item['pexels_fotografo'])) {
                        update_post_meta($post_id, '_clc_pexels_fotografo', $item['pexels_fotografo']);
                        update_post_meta($post_id, '_clc_pexels_fotografo_url', $item['pexels_fotografo_url'] ?? '');
                    }
                } else {
                    @unlink($tmp);
                }
            }

            $restaurados++;
        }

        $mensaje = "Restaurados {$restaurados} artículos.";
        if ($sin_coincidencia > 0) {
            $mensaje .= " {$sin_coincidencia} del backup no coinciden con ningún artículo actual (título distinto).";
        }
        wp_safe_redirect(add_query_arg('restaurado', urlencode($mensaje), admin_url('edit.php?post_type=articulo&page=clc-backup')));
        exit;
    }

    private static function redirigir_error($mensaje) {
        wp_safe_redirect(add_query_arg('error', urlencode($mensaje), admin_url('edit.php?post_type=articulo&page=clc-backup')));
        exit;
    }
}
