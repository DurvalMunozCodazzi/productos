<?php
if (!defined('ABSPATH')) exit;

/**
 * Sirve el service worker en la raíz del sitio (/clc-sw.js) para que su alcance
 * cubra todo el catálogo (/catalogo/, /categoria/..., fichas de producto), no
 * solo la carpeta del plugin — y lo registra en cada página del catálogo.
 */
class CLC_Offline {

    public static function init() {
        add_action('init', [__CLASS__, 'registrar_regla']);
        add_filter('query_vars', [__CLASS__, 'registrar_query_var']);
        add_action('template_redirect', [__CLASS__, 'servir_sw']);
        add_action('wp_footer', [__CLASS__, 'registrar_script']);
    }

    public static function registrar_regla() {
        add_rewrite_rule('^clc-sw\.js$', 'index.php?clc_sw=1', 'top');
    }

    public static function registrar_query_var($vars) {
        $vars[] = 'clc_sw';
        return $vars;
    }

    public static function servir_sw() {
        if (!get_query_var('clc_sw')) {
            return;
        }
        $ruta = CLC_PATH . 'assets/js/service-worker.js';
        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: /');
        if (file_exists($ruta)) {
            readfile($ruta);
        }
        exit;
    }

    public static function registrar_script() {
        if (!is_singular('articulo') && !is_tax('categoria_producto') && !is_page('catalogo')) {
            return;
        }
        ?>
        <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('<?php echo esc_url(home_url('/clc-sw.js')); ?>').catch(function () {});
            });
        }
        </script>
        <?php
    }
}
