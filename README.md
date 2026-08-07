# Casa Litani - Catálogo (plugin WordPress)

Catálogo de productos sin precios, con categorías, marcas, importador de Excel y botón de consulta por WhatsApp.

Autor: Durval Muñoz Codazzi — [Web Sobre Ruedas](https://websobreruedas.com)

## Versionado

Cada entrega sube la versión en dos lugares a la vez, que siempre deben coincidir:
- El header del plugin (`casa-litani-catalogo.php` → `Version:`)
- La constante `CLC_VERSION` (mismo archivo)

El `.zip` que se entrega para instalar se nombra `casa-litani-catalogo-X.Y.Z.zip` con esa misma versión, y cada entrega
queda taggeada en git (`vX.Y.Z`) para poder volver atrás si hace falta.

## Instalación

1. Comprimir la carpeta `casa-litani-catalogo/` en un `.zip`.
2. En WordPress: Plugins → Añadir nuevo → Subir plugin → seleccionar el `.zip` → Instalar → Activar.
   Al activarse se crea automáticamente la página **/catalogo/** con la grilla de categorías — no hay que armar nada a mano.
   Desde ahí la navegación Categoría → Marca → Artículo funciona sola.
3. El botón "Consultar" de cada ficha ya apunta al WhatsApp fijo de Casa Litani (+595985773704), no requiere configuración.
4. (Opcional, para importar el Excel) Instalar PhpSpreadsheet vía Composer dentro de la carpeta del plugin en el hosting (Plesk):
   ```
   cd wp-content/plugins/casa-litani-catalogo
   composer require phpoffice/phpspreadsheet
   ```
5. Ir a **Artículos → Importar Excel**, subir el archivo del cliente. El sistema crea categorías, marcas y artículos automáticamente según el mapeo de hojas definido en `includes/class-clc-importer.php`.
6. (Opcional, para fotos automáticas) Ir a **Artículos → Config. Fotos** y cargar una API Key gratuita de [Pexels](https://www.pexels.com/api/new/). Con eso, en **Artículos → Fotos** aparece el mismo sistema que en ratatuin.com.ar: tanda automática cada hora por WP-Cron, botón "Cargar 15 fotos ahora", auto-cargado cada 3 minutos, y subida manual artículo por artículo como respaldo. El crédito del fotógrafo se guarda y se muestra en la ficha (obligatorio por los términos de uso de Pexels).

## Estructura

- `includes/class-clc-post-type.php` — Custom Post Type "Artículo" + taxonomías Categoría/Marca.
- `includes/class-clc-whatsapp.php` — lógica de armado de link `wa.me` y rotación de números.
- `includes/class-clc-settings.php` — pantalla de administración de los números de WhatsApp.
- `includes/class-clc-importer.php` — importador de Excel (PhpSpreadsheet).
- `includes/class-clc-fotos.php` — pantalla "Artículos → Fotos" (listado, búsqueda, tanda manual, auto-cargado).
- `includes/class-clc-pexels.php` — búsqueda/descarga de fotos vía la API gratuita de Pexels + tanda automática por WP-Cron.
- `includes/class-clc-shortcodes.php` — shortcodes de frontend: `[clc_categorias]`, `[clc_marcas categoria="..."]`, `[clc_articulos categoria="..." marca="..."]`, `[clc_boton_whatsapp]`.
- `templates/single-articulo.php` — ficha de producto (imagen + descripción + botón WhatsApp).

## Categorías vigentes (acordadas con el cliente)

Celulares, Ordenadores (PC/Portátiles/Tablets), Audio, Gaming, Pantallas (TV/Proyectores), Hogar, Belleza, Movilidad, Accesorios Varios.

## Pendiente de definir con el cliente

- Ubicación final de Relojes/Smartwatches e Impresoras dentro de "Accesorios Varios" (hoy mapeados ahí).
- Carga de productos de la categoría "Belleza" (sin datos en el Excel actual).
- Proceso de captura/carga de imágenes por SKU (aparte del importador).
