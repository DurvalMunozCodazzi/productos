<?php
if (!defined('ABSPATH')) exit;

/**
 * Header/footer propios del catálogo — copian exactamente el menú y el pie de
 * la landing (Home Litani) para que se vea como una sola web, en vez de
 * depender del theme (Divi), que le agrega una barra lateral y rompe el diseño.
 */
class CLC_Layout {

    private static function logo_url() {
        return home_url('/wp-content/plugins/home-litani/assets/logo.jpeg');
    }

    public static function abrir_pagina($titulo = 'Casa Litani', $descripcion = '', $imagen = '') {
        $descripcion = $descripcion ?: 'Catálogo de electrónica de Casa Litani en Encarnación, Paraguay: celulares, notebooks, audio, gaming y más. Consultá disponibilidad por WhatsApp.';
        $imagen = $imagen ?: self::logo_url();
        ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html($titulo); ?> | Casa Litani</title>
<meta name="description" content="<?php echo esc_attr(wp_trim_words($descripcion, 30)); ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?php echo esc_attr($titulo); ?> | Casa Litani">
<meta property="og:description" content="<?php echo esc_attr(wp_trim_words($descripcion, 30)); ?>">
<meta property="og:image" content="<?php echo esc_url($imagen); ?>">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap">
<?php wp_head(); ?>
<style>
  * { box-sizing: border-box; }
  html, body { max-width: 100%; overflow-x: hidden; }
  body { margin: 0; background: #fffaf9; }
  img, svg, table { max-width: 100%; }
  a { text-decoration: none; color: inherit; }
  a:hover { color: #c92f52; }
  .cl { --accent:#f9526b; --accent-dark:#c92f52; --accent-2:#b44086; --coral:#ff7b67;
        --wa:#25d366; --wa-dark:#1da851; --r:14px;
        --bg:#fffaf9; --bg-alt:#fbedef; --surface:#fff; --border:#f0dbe0; --text:#241417; --dim:#71585d;
        background:var(--bg); color:var(--text); font-family:Inter,system-ui,sans-serif; line-height:1.6;
        max-width: 100%; overflow-x: hidden; }
  .cl h1, .cl h2, .cl h3 { font-family:Sora,Inter,sans-serif; margin:0; }
  .wrap { max-width:1180px; margin:0 auto; padding:0 24px; }
  .btn2 { display:inline-flex; align-items:center; gap:8px; padding:12px 22px; border-radius:999px;
          font-weight:600; font-size:.95rem; border:1px solid transparent; white-space:nowrap;
          transition:transform .15s ease, box-shadow .15s ease, background .15s ease; }
  .btn2:hover { transform:translateY(-2px); }
  .btn2 svg { width:18px; height:18px; fill:currentColor; }
  .btn-wa { background:var(--wa); color:#06210f; }
  .btn-wa:hover { background:var(--wa-dark); color:#06210f; box-shadow:0 8px 24px rgba(37,211,102,.35); }
  .btn-ol { background:transparent; color:var(--text); border-color:var(--border); }
  .btn-ol:hover { border-color:var(--accent); color:var(--accent); }
  .clc-cat-strip { display:flex; gap:8px; overflow-x:auto; padding-bottom:12px; margin-bottom:20px; }
  .clc-cat-chip { display:inline-flex; align-items:center; white-space:nowrap; border:1px solid var(--border);
                  background:#fff; color:#555; font-weight:600; font-size:13px; padding:8px 14px; border-radius:100px; }
  .clc-cat-chip:hover { border-color:var(--accent); color:var(--accent); }
  .clc-cat-chip-activo { background:var(--accent); border-color:var(--accent); color:#fff; }

  .clc-nav-links { display:flex; gap:28px; margin-left:auto; font-size:.95rem; font-weight:500; color:var(--dim); }
  .clc-nav-toggle { display:none; }
  @media (max-width: 780px) {
    .clc-nav-links {
      display:none; position:absolute; top:100%; left:0; right:0; flex-direction:column; gap:0;
      background:#fff; border-bottom:1px solid var(--border); box-shadow:0 12px 24px rgba(0,0,0,.08);
    }
    .clc-nav-links.clc-nav-abierto { display:flex; }
    .clc-nav-links a { padding:14px 24px; border-top:1px solid var(--border); }
    .clc-nav-toggle {
      display:flex; align-items:center; justify-content:center; width:40px; height:40px;
      border:1px solid var(--border); border-radius:8px; background:#fff; cursor:pointer; margin-left:auto;
    }
    .clc-nav-toggle svg { width:22px; height:22px; }
    .clc-header-wa-texto { display:none; }
    .clc-header-wa { padding:12px !important; }
  }
  @media (max-width: 480px) {
    .wrap { padding-left:18px; padding-right:18px; }
    .clc-contenido { padding-top:28px; }
    .clc-card { padding:10px; }
    .clc-grid { gap:12px; }
  }
</style>
</head>
<body <?php body_class(); ?>>
<div class="cl">

  <div style="position:sticky;top:0;z-index:100;background:rgba(255,250,249,.85);backdrop-filter:blur(10px);border-bottom:1px solid var(--border)">
    <div class="wrap" style="position:relative;display:flex;align-items:center;justify-content:space-between;gap:24px;padding-top:14px;padding-bottom:14px">
      <a href="<?php echo esc_url(home_url('/')); ?>" aria-label="Casa Litani — Inicio" style="display:flex;align-items:center">
        <img src="<?php echo esc_url(self::logo_url()); ?>" alt="Casa Litani Electrónica" style="width:60px;height:60px;border-radius:50%;object-fit:cover;box-shadow:0 0 0 2px rgba(0,0,0,.08),0 6px 16px rgba(0,0,0,.2);display:block">
      </a>
      <nav class="clc-nav-links" id="clc-nav-links">
        <a href="<?php echo esc_url(home_url('/')); ?>">Inicio</a>
        <a href="<?php echo esc_url(home_url('/catalogo/')); ?>" style="color:var(--accent)">Productos</a>
        <a href="<?php echo esc_url(home_url('/instalaciones/')); ?>">Instalaciones</a>
        <a href="<?php echo esc_url(home_url('/ubicacion/')); ?>">Ubicación</a>
        <a href="<?php echo esc_url(home_url('/contacto/')); ?>">Contacto</a>
      </nav>
      <div style="display:flex;align-items:center;gap:14px;margin-left:12px">
        <a href="https://wa.me/595985773704?text=Hola%20Casa%20Litani%2C%20quiero%20hacer%20una%20consulta" class="btn2 btn-wa clc-header-wa" target="_blank" rel="noopener">
          <svg viewBox="0 0 24 24"><path d="M17.5 14.4c-.3-.1-1.7-.9-2-1-.3-.1-.5-.1-.7.1s-.7 1-.9 1.1c-.2.2-.3.2-.6.1s-1.3-.5-2.4-1.5c-.9-.8-1.5-1.8-1.7-2.1s0-.5.1-.6c.1-.1.3-.3.4-.5.1-.1.2-.3.3-.4.1-.2 0-.4 0-.5s-.7-1.6-.9-2.2c-.2-.5-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.4s1 2.8 1.2 3c.1.2 2 3 4.8 4.3.7.3 1.2.5 1.6.6.7.2 1.3.2 1.8.1.5-.1 1.7-.7 1.9-1.4.2-.7.2-1.2.2-1.3-.1-.1-.3-.2-.6-.3z"></path><path d="M12 2C6.5 2 2 6.5 2 12c0 1.9.5 3.7 1.5 5.3L2 22l4.8-1.3c1.5.8 3.3 1.3 5.2 1.3 5.5 0 10-4.5 10-10S17.5 2 12 2zm0 18.3c-1.7 0-3.4-.5-4.8-1.3l-.3-.2-3.2.8.9-3.1-.2-.3C3.5 14.7 3 13.4 3 12c0-5 4-9 9-9s9 4 9 9-4 9-9 9z"></path></svg>
          <span class="clc-header-wa-texto">WhatsApp</span>
        </a>
        <button type="button" class="clc-nav-toggle" id="clc-nav-toggle" aria-label="Abrir menú" aria-expanded="false" aria-controls="clc-nav-links">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"></path></svg>
        </button>
      </div>
    </div>
  </div>

  <div class="wrap clc-contenido" style="padding-top:40px;padding-bottom:60px;">
        <?php
    }

    public static function cerrar_pagina() {
        ?>
  </div>

  <div style="border-top:1px solid var(--border);padding:48px 0 28px;background:var(--bg-alt);">
    <div class="wrap" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:32px">
      <div>
        <a href="<?php echo esc_url(home_url('/')); ?>" aria-label="Casa Litani — Inicio" style="display:flex;align-items:center">
          <img src="<?php echo esc_url(self::logo_url()); ?>" alt="Casa Litani Electrónica" style="width:56px;height:56px;border-radius:50%;object-fit:cover;box-shadow:0 0 0 2px rgba(0,0,0,.08),0 6px 16px rgba(0,0,0,.2);display:block">
        </a>
        <p style="color:var(--dim);font-size:.9rem;margin-top:8px;max-width:260px">Conectamos tu mundo, con lo mejor de la tecnología.</p>
      </div>
      <div>
        <strong style="font-size:.85rem;text-transform:uppercase;letter-spacing:.05em;color:var(--accent);">Escribinos por WhatsApp</strong>
        <div style="display:flex;flex-direction:column;gap:8px;margin-top:10px;">
          <a href="https://wa.me/595975602440?text=Hola%20Casa%20Litani%2C%20quiero%20hacer%20una%20consulta" target="_blank" rel="noopener" style="color:var(--dim);">Marcelo · +595 975 602 440</a>
          <a href="https://wa.me/595987111110?text=Hola%20Casa%20Litani%2C%20quiero%20hacer%20una%20consulta" target="_blank" rel="noopener" style="color:var(--dim);">Hamudi · +595 987 111 110</a>
          <a href="https://wa.me/595985773704?text=Hola%20Casa%20Litani%2C%20quiero%20hacer%20una%20consulta" target="_blank" rel="noopener" style="color:var(--dim);">Corporativo · +595 985 773 704</a>
        </div>
      </div>
      <div style="display:flex;gap:22px;flex-wrap:wrap;color:var(--dim);font-size:.9rem">
        <a href="<?php echo esc_url(home_url('/catalogo/')); ?>">Productos</a>
        <a href="<?php echo esc_url(home_url('/instalaciones/')); ?>">Instalaciones</a>
        <a href="<?php echo esc_url(home_url('/ubicacion/')); ?>">Ubicación</a>
        <a href="<?php echo esc_url(home_url('/contacto/')); ?>">Contacto</a>
      </div>
      <p style="width:100%;color:var(--dim);font-size:.8rem;margin-top:24px">© <?php echo esc_html(date('Y')); ?> Casa Litani S.R.L. — Encarnación, Paraguay.</p>
      <div style="width:100%;margin-top:20px;padding-top:20px;border-top:1px solid var(--border)">
        <?php echo do_shortcode('[clc_credito]'); ?>
      </div>
    </div>
  </div>

  <a href="https://wa.me/595985773704?text=Hola%20Casa%20Litani%2C%20quiero%20hacer%20una%20consulta" style="position:fixed;bottom:24px;right:24px;width:56px;height:56px;border-radius:50%;background:#25d366;display:flex;align-items:center;justify-content:center;box-shadow:0 8px 24px rgba(0,0,0,.25);z-index:200;" target="_blank" rel="noopener" aria-label="Escribir por WhatsApp">
    <svg viewBox="0 0 24 24" style="width:28px;height:28px;fill:#06210f;"><path d="M17.5 14.4c-.3-.1-1.7-.9-2-1-.3-.1-.5-.1-.7.1s-.7 1-.9 1.1c-.2.2-.3.2-.6.1s-1.3-.5-2.4-1.5c-.9-.8-1.5-1.8-1.7-2.1s0-.5.1-.6c.1-.1.3-.3.4-.5.1-.1.2-.3.3-.4.1-.2 0-.4 0-.5s-.7-1.6-.9-2.2c-.2-.5-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.4s1 2.8 1.2 3c.1.2 2 3 4.8 4.3.7.3 1.2.5 1.6.6.7.2 1.3.2 1.8.1.5-.1 1.7-.7 1.9-1.4.2-.7.2-1.2.2-1.3-.1-.1-.3-.2-.6-.3z"></path><path d="M12 2C6.5 2 2 6.5 2 12c0 1.9.5 3.7 1.5 5.3L2 22l4.8-1.3c1.5.8 3.3 1.3 5.2 1.3 5.5 0 10-4.5 10-10S17.5 2 12 2zm0 18.3c-1.7 0-3.4-.5-4.8-1.3l-.3-.2-3.2.8.9-3.1-.2-.3C3.5 14.7 3 13.4 3 12c0-5 4-9 9-9s9 4 9 9-4 9-9 9z"></path></svg>
  </a>

</div>
<script>
(function () {
  var btn = document.getElementById('clc-nav-toggle');
  var menu = document.getElementById('clc-nav-links');
  if (!btn || !menu) return;
  btn.addEventListener('click', function () {
    var abierto = menu.classList.toggle('clc-nav-abierto');
    btn.setAttribute('aria-expanded', abierto ? 'true' : 'false');
  });
})();
</script>
<?php wp_footer(); ?>
</body>
</html>
        <?php
    }
}
