<?php
/**
 * Visor público de catálogos — /catalogo-prendedores, /catalogo-guapo, ...
 *
 * Sirve catálogos en PDF (que antes se mandaban por WhatsApp) como una tira vertical
 * de imágenes WebP que cargan a medida que se hace scroll. Un <iframe>/<embed> de PDF
 * no funciona en iOS ni en el navegador interno de Instagram, y estos PDF pesan
 * 40-70 MB. Lleva además el botón flotante de WhatsApp del sitio, porque esta clase
 * renderiza el documento HTML completo y nunca pasa por wp_footer().
 *
 * Genérico a propósito: cada catálogo nuevo es una instancia de CollarCatalogoViewer
 * al final de este archivo, no un módulo aparte. Las páginas viven en
 * assets/<carpeta>/pNN-720.webp y pNN-1080.webp; para actualizar un catálogo basta
 * reemplazar esas imágenes y, si cambia el número de páginas, el 'total_pages'.
 *
 * @package CollarConfigurator
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Un único flush de rewrite rules para todos los catálogos, aunque haya varias
 * instancias. Si cada instancia flusheara en su propio hook 'init' se arriesgaría a
 * escribir la tabla de reglas antes de que las demás instancias hubieran registrado
 * la suya (add_rewrite_rule de la última en construirse corre después). Por eso el
 * flush va en un hook aparte, a una prioridad posterior a la de todos los add_rewrite.
 */
function collar_catalogo_maybe_flush_rewrites() {
    // Subir este valor cuando se agregue o cambie el slug de algún catálogo.
    $ver = '3';
    if (get_option('collar_catalogo_rewrite_ver') !== $ver) {
        flush_rewrite_rules();
        update_option('collar_catalogo_rewrite_ver', $ver);
    }
}
add_action('init', 'collar_catalogo_maybe_flush_rewrites', 20);

class CollarCatalogoViewer {

    private $slug;
    private $query_var;
    private $folder;
    private $total_pages;
    private $title;
    private $description;
    private $wa_message;

    /** Proporción de página de los PDF de origen (1080 x 1350). */
    const PAGE_W = 1080;
    const PAGE_H = 1350;

    public function __construct(array $args) {
        $this->slug        = $args['slug'];
        $this->query_var   = $args['query_var'];
        $this->folder      = $args['folder'];
        $this->total_pages = (int) $args['total_pages'];
        $this->title       = $args['title'];
        $this->description = $args['description'];
        $this->wa_message  = $args['wa_message'];

        add_action('init', array($this, 'add_rewrite'));
        add_filter('query_vars', array($this, 'add_query_var'));

        // Prioridad 5: antes de redirect_canonical (10), para que Botcake/Instagram
        // reciban un 200 directo y no un 301 hacia la ruta con barra final.
        add_action('template_redirect', array($this, 'maybe_render'), 5);
        add_filter('redirect_canonical', array($this, 'skip_canonical_redirect'), 10, 1);
    }

    public function add_rewrite() {
        add_rewrite_rule('^' . $this->slug . '/?$', 'index.php?' . $this->query_var . '=1', 'top');
    }

    public function add_query_var($vars) {
        $vars[] = $this->query_var;
        return $vars;
    }

    public function skip_canonical_redirect($redirect_url) {
        if (get_query_var($this->query_var)) {
            return false;
        }
        return $redirect_url;
    }

    public function maybe_render() {
        if (!get_query_var($this->query_var)) {
            return;
        }

        status_header(200);
        header('Content-Type: text/html; charset=' . get_option('blog_charset'));
        // Contenido idéntico para todos: que el borde (LiteSpeed) y el navegador lo cacheen.
        header('Cache-Control: public, max-age=3600');

        $this->render();
        exit;
    }

    private function image_url($n, $width) {
        return plugin_dir_url(__FILE__) . sprintf('assets/%s/p%02d-%d.webp', $this->folder, $n, $width);
    }

    private function render() {
        $total   = $this->total_pages;
        $page_ur = home_url('/' . $this->slug);
        // Versión por fecha de modificación: si se reemplazan las imágenes, se rompe el caché del navegador.
        $ver     = @filemtime(plugin_dir_path(__FILE__) . "assets/{$this->folder}/p01-720.webp") ?: 1;
        ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="<?php echo esc_attr(get_option('blog_charset')); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html($this->title); ?></title>
<meta name="description" content="<?php echo esc_attr($this->description); ?>">
<link rel="canonical" href="<?php echo esc_url($page_ur); ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?php echo esc_attr($this->title); ?>">
<meta property="og:description" content="<?php echo esc_attr($this->description); ?>">
<meta property="og:url" content="<?php echo esc_url($page_ur); ?>">
<meta property="og:image" content="<?php echo esc_url($this->image_url(1, 1080) . '?v=' . $ver); ?>">
<meta name="theme-color" content="#efe7dc">
<link rel="preload" as="image" href="<?php echo esc_url($this->image_url(1, 720) . '?v=' . $ver); ?>"
      imagesrcset="<?php echo esc_attr($this->image_url(1, 720) . '?v=' . $ver . ' 720w, ' . $this->image_url(1, 1080) . '?v=' . $ver . ' 1080w'); ?>"
      imagesizes="(min-width: 640px) 640px, 100vw">
<style>
  :root { --bg: #efe7dc; --ink: #5b3a4a; }
  * { box-sizing: border-box; }
  html { background: var(--bg); }
  body {
    margin: 0;
    background: var(--bg);
    color: var(--ink);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    -webkit-text-size-adjust: 100%;
  }
  .cat { max-width: 640px; margin: 0 auto; }
  .cat-page { display: block; width: 100%; height: auto; background: #e4d9ca; }
  .cat-page + .cat-page { margin-top: 6px; }
  .cat-footer { padding: 28px 16px calc(40px + env(safe-area-inset-bottom)); text-align: center; }
  .cat-cta {
    display: inline-block; padding: 14px 28px; border-radius: 999px;
    background: var(--ink); color: #fff; font-weight: 600; font-size: 16px; text-decoration: none;
  }
  .cat-counter {
    position: fixed; top: calc(12px + env(safe-area-inset-top)); right: 12px; z-index: 10;
    padding: 6px 12px; border-radius: 999px; font-size: 13px; font-weight: 600;
    background: rgba(91, 58, 74, .85); color: #fff; pointer-events: none;
    font-variant-numeric: tabular-nums;
  }
  .cat-back {
    position: fixed; top: calc(12px + env(safe-area-inset-top)); left: 12px; z-index: 10;
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 14px 6px 12px; border-radius: 999px; font-size: 13px; font-weight: 600;
    background: rgba(91, 58, 74, .85); color: #fff; text-decoration: none;
  }
  .cat-back:hover, .cat-back:active { background: rgba(91, 58, 74, .95); color: #fff; }
  @media (min-width: 640px) { .cat { padding: 16px 0; } }
</style>
</head>
<body>
<a class="cat-back" href="<?php echo esc_url(home_url('/')); ?>" aria-label="Volver a guapolove.com">&larr; Volver</a>
<div class="cat-counter" aria-hidden="true"><span id="cat-n">1</span> / <?php echo (int) $total; ?></div>
<main class="cat">
<?php for ($n = 1; $n <= $total; $n++) :
    $src720  = esc_url($this->image_url($n, 720) . '?v=' . $ver);
    $src1080 = esc_url($this->image_url($n, 1080) . '?v=' . $ver);
    $eager   = ($n <= 2);
    ?>
  <img class="cat-page" data-n="<?php echo (int) $n; ?>"
       src="<?php echo $src720; ?>"
       srcset="<?php echo $src720; ?> 720w, <?php echo $src1080; ?> 1080w"
       sizes="(min-width: 640px) 640px, 100vw"
       width="<?php echo (int) self::PAGE_W; ?>" height="<?php echo (int) self::PAGE_H; ?>"
       alt="<?php echo esc_attr($this->title); ?>, página <?php echo (int) $n; ?> de <?php echo (int) $total; ?>"
       decoding="async"<?php echo $eager ? ' fetchpriority="' . ($n === 1 ? 'high' : 'auto') . '"' : ' loading="lazy"'; ?>>
<?php endfor; ?>
  <div class="cat-footer">
    <a class="cat-cta" href="<?php echo esc_url(home_url('/')); ?>">Ir a guapolove.com</a>
  </div>
</main>
<?php
        // Botón flotante de WhatsApp del sitio. Esta página imprime su propio HTML y
        // hace exit antes de llegar a wp_footer(), así que se llama a mano aquí.
        global $collar_ajax_instance;
        if ($collar_ajax_instance instanceof CollarConfiguratorAJAX) {
            $collar_ajax_instance->render_whatsapp_floating_button($this->wa_message);
        }
        ?>
<script>
(function () {
  var out = document.getElementById('cat-n');
  var imgs = document.querySelectorAll('.cat-page');
  if (!out || !('IntersectionObserver' in window)) { return; }
  // La página "actual" es la que cruza el centro de la pantalla.
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (e) {
      if (e.isIntersecting) { out.textContent = e.target.getAttribute('data-n'); }
    });
  }, { rootMargin: '-50% 0px -50% 0px' });
  imgs.forEach(function (img) { io.observe(img); });
})();
</script>
</body>
</html>
        <?php
    }
}

new CollarCatalogoViewer(array(
    'slug'        => 'catalogo-prendedores',
    'query_var'   => 'collar_catalogo_prendedores',
    'folder'      => 'catalogo-prendedores',
    'total_pages' => 17,
    'title'       => 'Catálogo de prendedores | Guapo Love',
    'description' => 'Prendedores bordados a mano, en edición limitada. Mira el catálogo completo.',
    'wa_message'  => 'Hola, quiero información sobre los prendedores del catálogo 💛',
));

new CollarCatalogoViewer(array(
    'slug'        => 'catalogo-guapo',
    'query_var'   => 'collar_catalogo_guapo',
    'folder'      => 'catalogo-guapo',
    'total_pages' => 18,
    'title'       => 'Catálogo general | Guapo Love',
    'description' => 'Ropa, accesorios y productos para tu peludo. Mira el catálogo completo de Guapo Love.',
    'wa_message'  => 'Hola, quiero información sobre los productos del catálogo de Guapo Love 💛',
));

new CollarCatalogoViewer(array(
    'slug'        => 'catalogo-panoletas',
    'query_var'   => 'collar_catalogo_panoletas',
    'folder'      => 'catalogo-panoletas',
    'total_pages' => 35,
    'title'       => 'Catálogo de pañoletas | Guapo Love',
    'description' => 'Pañoletas bordadas a mano y pañoletas en seda, Línea MAGIC WORLD. Mira el catálogo completo.',
    'wa_message'  => 'Hola, quiero información sobre las pañoletas del catálogo 💛',
));
