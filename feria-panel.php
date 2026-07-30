<?php
/**
 * Panel de Feria — configurador asistido para punto presencial.
 *
 * Herramienta de apoyo para que un operador personalice collares junto al cliente
 * en una feria y genere una ficha (PNG) con el diseño y los datos de contacto.
 *
 * NO toca WooCommerce: no crea pedidos, no cobra, no factura. Las fichas viven en
 * el navegador de la tablet (IndexedDB) y se exportan al final del evento. Esto es
 * deliberado: el WiFi de una feria es el punto de falla clásico, y el panel debe
 * seguir funcionando sin conexión.
 *
 * @package CollarConfigurator
 */

if (!defined('ABSPATH')) {
    exit;
}

class CollarFeriaPanel {

    /** PIN de acceso, hasheado con wp_hash_password(). Sin PIN el panel queda cerrado. */
    const OPT_PIN = 'collar_feria_pin';

    /** Nombre del evento en curso; se estampa en cada ficha. */
    const OPT_EVENT = 'collar_feria_evento';

    /** Colores de charm agotados para este evento (array de hex, ej. ['#GOLD']). */
    const OPT_SOLD_OUT = 'collar_feria_agotados';

    /** Versión de las rewrite rules. Al subirla se refrescan los permalinks. */
    const OPT_REWRITE_VER = 'collar_feria_rewrite_ver';
    const REWRITE_VER     = '1';

    const COOKIE       = 'collar_feria_auth';
    const SESSION_DAYS = 30;

    /** Un PIN de 4 dígitos son 10.000 combinaciones: sin límite de intentos es decorativo. */
    const MAX_FAILS     = 5;
    const LOCK_SECONDS  = 900;

    const CATALOG_TRANSIENT = 'collar_feria_catalogo';

    public function __construct() {
        add_action('init', array($this, 'add_rewrite'));
        add_filter('query_vars', array($this, 'add_query_var'));

        // Prioridad 5: hay que renderizar antes de redirect_canonical (prioridad 10),
        // que si no manda /feria a /feria/ y /feria-sw.js a /feria-sw.js/.
        add_action('template_redirect', array($this, 'maybe_render_panel'), 5);

        // Un service worker servido tras una redirección no se registra: el navegador
        // exige que la URL responda directamente. Por eso se desactiva la redirección
        // canónica en estas dos rutas y solo en ellas.
        add_filter('redirect_canonical', array($this, 'skip_canonical_redirect'), 10, 1);

        add_action('admin_menu', array($this, 'add_admin_menu'), 20);

        // El operador no es un usuario de WordPress: el desbloqueo va por nopriv.
        add_action('wp_ajax_nopriv_collar_feria_unlock', array($this, 'ajax_unlock'));
        add_action('wp_ajax_collar_feria_unlock', array($this, 'ajax_unlock'));
    }

    /* ---------------------------------------------------------------------
     * Ruta pública /feria
     * ------------------------------------------------------------------ */

    public function add_rewrite() {
        add_rewrite_rule('^feria/?$', 'index.php?collar_feria=1', 'top');

        // El service worker se sirve desde la raíz a propósito: su alcance debe
        // cubrir /wp-content/, donde están los charms. Si viviera bajo /feria/
        // no podría cachearlos.
        add_rewrite_rule('^feria-sw\.js$', 'index.php?collar_feria_sw=1', 'top');

        if (get_option(self::OPT_REWRITE_VER) !== self::REWRITE_VER) {
            flush_rewrite_rules();
            update_option(self::OPT_REWRITE_VER, self::REWRITE_VER);
        }
    }

    public function add_query_var($vars) {
        $vars[] = 'collar_feria';
        $vars[] = 'collar_feria_sw';
        return $vars;
    }

    /**
     * Desactiva la redirección canónica solo en las rutas del panel. El resto del
     * sitio conserva su comportamiento normal de barra final.
     */
    public function skip_canonical_redirect($redirect_url) {
        if (get_query_var('collar_feria') || get_query_var('collar_feria_sw')) {
            return false;
        }
        return $redirect_url;
    }

    /**
     * Entrega el service worker con las rutas reales inyectadas.
     */
    private function serve_service_worker() {
        $file = plugin_dir_path(__FILE__) . 'feria-sw.js';
        $js   = @file_get_contents($file);

        if ($js === false) {
            status_header(404);
            exit;
        }

        $assets_path = wp_parse_url(plugin_dir_url(__FILE__) . 'assets/', PHP_URL_PATH);
        $panel_path  = rtrim(wp_parse_url(home_url('/feria'), PHP_URL_PATH), '/');

        $js = str_replace(
            array('__FERIA_ASSETS__', '__FERIA_PATH__'),
            array($assets_path, $panel_path),
            $js
        );

        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: /');
        header('Cache-Control: no-cache, must-revalidate, max-age=0');
        $this->no_edge_cache();

        echo $js;
        exit;
    }

    /**
     * Excluye la respuesta del caché de borde (LiteSpeed en este hosting).
     *
     * Es obligatorio, no una optimización: /feria devuelve la pantalla de PIN o el
     * panel abierto según la cookie del operador. Si el borde cachea una de las dos
     * y la sirve a todo el mundo, o se filtra el panel desbloqueado o el operador
     * autenticado queda encerrado en la pantalla de PIN.
     */
    private function no_edge_cache() {
        if (!headers_sent()) {
            header('X-LiteSpeed-Cache-Control: no-cache');
        }
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
    }

    /**
     * Renderiza el panel fuera del tema: sin header, sin footer, sin plugins de
     * terceros inyectando nada. El operador necesita una pantalla limpia.
     */
    public function maybe_render_panel() {
        if (get_query_var('collar_feria_sw')) {
            $this->serve_service_worker();
        }

        if (!get_query_var('collar_feria')) {
            return;
        }

        nocache_headers();
        $this->no_edge_cache();

        $pin_hash = get_option(self::OPT_PIN, '');

        if (empty($pin_hash)) {
            // Sin PIN configurado el panel NO se abre. Denegar por defecto.
            $this->render_shell('<div class="feria-gate-msg">
                <h1>Panel de Feria</h1>
                <p>Todavía no se ha configurado un PIN de acceso.</p>
                <p class="feria-gate-hint">Un administrador debe definirlo en
                <strong>Collares &rsaquo; Panel de Feria</strong> dentro de WordPress.</p>
            </div>');
            exit;
        }

        if ($this->has_valid_session()) {
            $this->render_panel();
        } else {
            $this->render_lock_screen();
        }
        exit;
    }

    /* ---------------------------------------------------------------------
     * Sesión por cookie firmada
     * ------------------------------------------------------------------ */

    private function session_signature($expiry) {
        // Se firma con el hash del PIN: cambiar el PIN invalida todas las sesiones.
        return hash_hmac('sha256', $expiry . '|feria', wp_salt('auth') . get_option(self::OPT_PIN, ''));
    }

    private function has_valid_session() {
        if (empty($_COOKIE[self::COOKIE])) {
            return false;
        }

        $parts = explode('|', wp_unslash($_COOKIE[self::COOKIE]));
        if (count($parts) !== 2) {
            return false;
        }

        list($expiry, $signature) = $parts;

        if (!ctype_digit($expiry) || (int) $expiry < time()) {
            return false;
        }

        return hash_equals($this->session_signature($expiry), $signature);
    }

    private function start_session() {
        $expiry = time() + (self::SESSION_DAYS * DAY_IN_SECONDS);
        $value  = $expiry . '|' . $this->session_signature($expiry);

        setcookie(self::COOKIE, $value, $expiry, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true);
    }

    /* ---------------------------------------------------------------------
     * Desbloqueo con PIN + límite de intentos
     * ------------------------------------------------------------------ */

    private function client_ip() {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? wp_unslash($_SERVER['REMOTE_ADDR']) : '0.0.0.0';
        return md5($ip);
    }

    public function ajax_unlock() {
        check_ajax_referer('collar_feria_unlock', 'nonce');

        $key   = 'collar_feria_fails_' . $this->client_ip();
        $fails = (int) get_transient($key);

        if ($fails >= self::MAX_FAILS) {
            wp_send_json_error(array(
                'message' => 'Demasiados intentos fallidos. Espera 15 minutos e intenta de nuevo.',
            ));
        }

        $pin      = isset($_POST['pin']) ? sanitize_text_field(wp_unslash($_POST['pin'])) : '';
        $pin_hash = get_option(self::OPT_PIN, '');

        if (empty($pin_hash) || !wp_check_password($pin, $pin_hash)) {
            set_transient($key, $fails + 1, self::LOCK_SECONDS);
            $left = self::MAX_FAILS - ($fails + 1);
            wp_send_json_error(array(
                'message' => $left > 0
                    ? 'PIN incorrecto. Te quedan ' . $left . ' intento' . ($left === 1 ? '' : 's') . '.'
                    : 'PIN incorrecto. Acceso bloqueado por 15 minutos.',
            ));
        }

        delete_transient($key);
        $this->start_session();

        wp_send_json_success(array('message' => 'Acceso concedido'));
    }

    /* ---------------------------------------------------------------------
     * Catálogo: se lee del disco, nunca se hardcodea
     * ------------------------------------------------------------------ */

    /**
     * Escanea assets/ para construir el catálogo. Al leerlo del disco en vez de
     * mantener listas paralelas, el panel de feria no puede desincronizarse del
     * configurador de venta cuando se agregan o quitan charms.
     */
    public function get_catalog() {
        $cached = get_transient(self::CATALOG_TRANSIENT);
        if (is_array($cached)) {
            return $cached;
        }

        $base = plugin_dir_path(__FILE__) . 'assets/';

        // Emojis: una carpeta por color, el nombre de archivo es el identificador.
        $emojis = array();
        $emoji_dirs = glob($base . 'emojis/*', GLOB_ONLYDIR);
        if (is_array($emoji_dirs)) {
            foreach ($emoji_dirs as $dir) {
                $folder = basename($dir);
                $files  = glob($dir . '/*.png');
                if (!is_array($files) || empty($files)) {
                    continue;
                }
                $names = array_map('basename', $files);
                natsort($names);
                $emojis['#' . $folder] = array_values($names);
            }
        }

        // Letras con versión glitter (dorado/plateado).
        $glitter = array();
        $gold_files = glob($base . 'charms/glitter/gold/*_128.png');
        if (is_array($gold_files)) {
            foreach ($gold_files as $file) {
                $glitter[] = str_replace('_128.png', '', basename($file));
            }
            sort($glitter);
        }

        $catalog = array(
            'emojis'  => $emojis,
            'glitter' => $glitter,
        );

        set_transient(self::CATALOG_TRANSIENT, $catalog, 12 * HOUR_IN_SECONDS);

        return $catalog;
    }

    /**
     * Lista plana de URLs del catálogo, para el precache offline.
     */
    public function get_precache_urls() {
        $catalog = $this->get_catalog();
        $assets  = plugin_dir_url(__FILE__) . 'assets/';
        $urls    = array();

        foreach ($catalog['emojis'] as $hex => $files) {
            $folder = ltrim($hex, '#');
            foreach ($files as $file) {
                $urls[] = $assets . 'emojis/' . rawurlencode($folder) . '/' . rawurlencode($file);
            }
        }

        foreach ($catalog['glitter'] as $char) {
            $urls[] = $assets . 'charms/glitter/gold/' . rawurlencode($char) . '_128.png';
            $urls[] = $assets . 'charms/glitter/silver/' . rawurlencode($char) . '_128.png';
        }

        $urls[] = $assets . 'texturas/dorada/' . rawurlencode('PAGINA WEB COLLARES-11.png');
        $urls[] = $assets . 'texturas/plateada/' . rawurlencode('PAGINA WEB COLLARES-10.png');

        return $urls;
    }

    /**
     * Configuración que se entrega al JS del panel. Precios y límites se leen de
     * las constantes de CollarConfiguratorAJAX para tener una sola fuente de verdad.
     */
    public function get_js_config() {
        $catalog  = $this->get_catalog();
        $sold_out = get_option(self::OPT_SOLD_OUT, array());
        if (!is_array($sold_out)) {
            $sold_out = array();
        }

        return array(
            'assetsUrl'   => plugin_dir_url(__FILE__) . 'assets/',
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'evento'      => get_option(self::OPT_EVENT, ''),
            'basePrices'  => CollarConfiguratorAJAX::BASE_PRICES,
            'extraCharm'  => CollarConfiguratorAJAX::EXTRA_CHARM_PRICE,
            'baseCharms'  => CollarConfiguratorAJAX::BASE_CHARMS_INCLUDED,
            'minCharms'   => CollarConfiguratorAJAX::MIN_CHARMS,
            'maxBySize'   => CollarConfiguratorAJAX::MAX_CHARMS_BY_SIZE,
            'sizes'       => CollarConfiguratorAJAX::ALLOWED_SIZES,
            'xsColors'    => CollarConfiguratorAJAX::XS_ALLOWED_COLORS,
            'collarColors'=> $this->collar_colors(),
            'charmColors' => $this->charm_colors(),
            'emojis'      => $catalog['emojis'],
            'glitter'     => $catalog['glitter'],
            'soldOut'     => array_values($sold_out),
            'precache'    => $this->get_precache_urls(),
        );
    }

    /** Colores del collar. Los hex coinciden con .collar-band-preview del CSS de venta. */
    public function collar_colors() {
        return array(
            array('slug' => 'collar1', 'label' => 'Naranja',  'hex' => '#FC7433'),
            array('slug' => 'collar2', 'label' => 'Negro',    'hex' => '#000000'),
            array('slug' => 'collar3', 'label' => 'Azul',     'hex' => '#0347F2'),
            array('slug' => 'collar4', 'label' => 'Amarillo', 'hex' => '#F1FF3A'),
            array('slug' => 'collar5', 'label' => 'Rosa',     'hex' => '#FF136F'),
            array('slug' => 'collar6', 'label' => 'Rojo',     'hex' => '#D60C0C'),
        );
    }

    /** Colores de charm. GOLD y SILVER son texturas, no colores planos. */
    public function charm_colors() {
        return array(
            array('hex' => '#FF64DC', 'label' => 'Rosa'),
            array('hex' => '#BA0000', 'label' => 'Rojo'),
            array('hex' => '#FF5A00', 'label' => 'Naranja'),
            array('hex' => '#E1FF00', 'label' => 'Amarillo'),
            array('hex' => '#00FF51', 'label' => 'Verde'),
            array('hex' => '#00D6FF', 'label' => 'Azul claro'),
            array('hex' => '#1900FA', 'label' => 'Azul oscuro'),
            array('hex' => '#A080FF', 'label' => 'Lila'),
            array('hex' => '#SILVER', 'label' => 'Plateado'),
            array('hex' => '#GOLD',   'label' => 'Dorado'),
        );
    }

    /* ---------------------------------------------------------------------
     * Render
     * ------------------------------------------------------------------ */

    /**
     * Cascarón HTML común. El panel vive fuera del tema a propósito.
     */
    private function render_shell($body, $extra_head = '') {
        $css = @file_get_contents(plugin_dir_path(__FILE__) . 'feria-panel.css');
        ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow, noarchive">
<title>Panel de Feria — <?php bloginfo('name'); ?></title>
<style><?php echo $css; ?></style>
<?php echo $extra_head; ?>
</head>
<body class="feria-body">
<?php echo $body; ?>
</body>
</html><?php
    }

    private function render_lock_screen() {
        ob_start();
        ?>
        <div class="feria-lock">
            <div class="feria-lock-card">
                <h1>Panel de Feria</h1>
                <p class="feria-lock-sub">Ingresa el PIN para comenzar</p>

                <div class="feria-pin-dots" id="pin-dots">
                    <span></span><span></span><span></span><span></span>
                </div>

                <div class="feria-pin-error" id="pin-error"></div>

                <div class="feria-keypad">
                    <?php foreach (array(1,2,3,4,5,6,7,8,9) as $n): ?>
                        <button type="button" data-key="<?php echo $n; ?>"><?php echo $n; ?></button>
                    <?php endforeach; ?>
                    <button type="button" data-action="clear">C</button>
                    <button type="button" data-key="0">0</button>
                    <button type="button" data-action="back">&#9003;</button>
                </div>
            </div>
        </div>
        <script>
        (function () {
            var pin = '';
            var busy = false;
            var dots  = document.getElementById('pin-dots');
            var error = document.getElementById('pin-error');
            var AJAX  = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
            var NONCE = <?php echo wp_json_encode(wp_create_nonce('collar_feria_unlock')); ?>;

            function paint() {
                var spans = dots.querySelectorAll('span');
                for (var i = 0; i < spans.length; i++) {
                    spans[i].className = i < pin.length ? 'filled' : '';
                }
            }

            function submit() {
                busy = true;
                var body = new FormData();
                body.append('action', 'collar_feria_unlock');
                body.append('nonce', NONCE);
                body.append('pin', pin);

                fetch(AJAX, { method: 'POST', body: body, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (res && res.success) {
                            location.reload();
                            return;
                        }
                        error.textContent = (res && res.data && res.data.message) || 'No se pudo validar el PIN.';
                        pin = '';
                        busy = false;
                        paint();
                        dots.classList.add('shake');
                        setTimeout(function () { dots.classList.remove('shake'); }, 400);
                    })
                    .catch(function () {
                        error.textContent = 'Sin conexión con el servidor.';
                        pin = '';
                        busy = false;
                        paint();
                    });
            }

            document.querySelector('.feria-keypad').addEventListener('click', function (e) {
                var btn = e.target.closest('button');
                if (!btn || busy) return;

                var action = btn.getAttribute('data-action');
                if (action === 'clear')      pin = '';
                else if (action === 'back')  pin = pin.slice(0, -1);
                else if (pin.length < 4)     pin += btn.getAttribute('data-key');

                error.textContent = '';
                paint();

                if (pin.length === 4) submit();
            });

            document.addEventListener('keydown', function (e) {
                if (busy) return;
                if (e.key >= '0' && e.key <= '9' && pin.length < 4) {
                    pin += e.key;
                } else if (e.key === 'Backspace') {
                    pin = pin.slice(0, -1);
                } else {
                    return;
                }
                error.textContent = '';
                paint();
                if (pin.length === 4) submit();
            });
        })();
        </script>
        <?php
        $this->render_shell(ob_get_clean());
    }

    private function render_panel() {
        $config = $this->get_js_config();
        $js     = @file_get_contents(plugin_dir_path(__FILE__) . 'feria-panel.js');

        ob_start();
        include plugin_dir_path(__FILE__) . 'feria-panel-template.php';
        $body = ob_get_clean();

        $body .= '<script>window.feriaConfig = ' . wp_json_encode($config) . ';</script>';
        $body .= '<script>' . $js . '</script>';

        $this->render_shell($body);
    }

    /* ---------------------------------------------------------------------
     * Ajustes en el admin de WordPress
     * ------------------------------------------------------------------ */

    public function add_admin_menu() {
        add_submenu_page(
            'collar-configurator',
            'Panel de Feria',
            'Panel de Feria',
            'manage_options',
            'collar-feria',
            array($this, 'admin_page')
        );
    }

    public function admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Sin permisos.');
        }

        $notices = array();

        if (isset($_POST['collar_feria_save']) && check_admin_referer('collar_feria_settings')) {

            $pin = isset($_POST['feria_pin']) ? preg_replace('/\D/', '', wp_unslash($_POST['feria_pin'])) : '';
            if ($pin !== '') {
                if (strlen($pin) !== 4) {
                    $notices[] = array('error', 'El PIN debe tener exactamente 4 dígitos.');
                } else {
                    update_option(self::OPT_PIN, wp_hash_password($pin));
                    $notices[] = array('updated', 'PIN actualizado. Las sesiones abiertas en las tablets se cerraron.');
                }
            }

            $evento = isset($_POST['feria_evento']) ? sanitize_text_field(wp_unslash($_POST['feria_evento'])) : '';
            update_option(self::OPT_EVENT, $evento);

            $sold_out = isset($_POST['feria_agotados']) ? (array) wp_unslash($_POST['feria_agotados']) : array();
            $sold_out = array_map('sanitize_text_field', $sold_out);
            update_option(self::OPT_SOLD_OUT, $sold_out);

            $notices[] = array('updated', 'Ajustes guardados.');
        }

        if (isset($_POST['collar_feria_refresh']) && check_admin_referer('collar_feria_settings')) {
            delete_transient(self::CATALOG_TRANSIENT);
            $notices[] = array('updated', 'Catálogo releído desde los archivos.');
        }

        $catalog   = $this->get_catalog();
        $sold_out  = (array) get_option(self::OPT_SOLD_OUT, array());
        $has_pin   = (bool) get_option(self::OPT_PIN, '');
        $panel_url = home_url('/feria');

        $emoji_count = 0;
        foreach ($catalog['emojis'] as $files) {
            $emoji_count += count($files);
        }
        ?>
        <div class="wrap">
            <h1>Panel de Feria</h1>

            <?php foreach ($notices as $n): ?>
                <div class="notice notice-<?php echo esc_attr($n[0] === 'error' ? 'error' : 'success'); ?>">
                    <p><?php echo esc_html($n[1]); ?></p>
                </div>
            <?php endforeach; ?>

            <?php if (!$has_pin): ?>
                <div class="notice notice-warning">
                    <p><strong>El panel está cerrado.</strong> Define un PIN abajo para poder usarlo.</p>
                </div>
            <?php endif; ?>

            <p>
                Herramienta de apoyo para personalizar collares en punto presencial.
                No crea pedidos ni cobra: genera una ficha con el diseño y los datos del cliente.
            </p>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Dirección del panel</th>
                    <td>
                        <code style="font-size:15px;"><?php echo esc_html($panel_url); ?></code>
                        <p class="description">
                            Abre esta URL en la tablet y guárdala en la pantalla de inicio.
                            La página no aparece en Google (<code>noindex</code>) ni está enlazada desde el sitio.
                        </p>
                    </td>
                </tr>
            </table>

            <form method="post">
                <?php wp_nonce_field('collar_feria_settings'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="feria_pin">PIN de acceso</label></th>
                        <td>
                            <input type="text" id="feria_pin" name="feria_pin" maxlength="4"
                                   inputmode="numeric" pattern="\d{4}" class="regular-text"
                                   style="width:100px;letter-spacing:6px;font-size:18px;"
                                   autocomplete="off" placeholder="••••">
                            <p class="description">
                                4 dígitos.
                                <?php if ($has_pin): ?>
                                    Ya hay un PIN configurado — escribe uno nuevo solo si quieres cambiarlo.
                                <?php endif; ?>
                                Tras 5 intentos fallidos el acceso se bloquea 15 minutos.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="feria_evento">Nombre del evento</label></th>
                        <td>
                            <input type="text" id="feria_evento" name="feria_evento" class="regular-text"
                                   value="<?php echo esc_attr(get_option(self::OPT_EVENT, '')); ?>"
                                   placeholder="Ej: Expopet Bogotá 2026">
                            <p class="description">Se estampa en cada ficha para poder separar las ventas por evento.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Agotados en este evento</th>
                        <td>
                            <fieldset>
                                <?php foreach ($this->charm_colors() as $color): ?>
                                    <label style="display:inline-block;min-width:150px;margin-bottom:6px;">
                                        <input type="checkbox" name="feria_agotados[]"
                                               value="<?php echo esc_attr($color['hex']); ?>"
                                            <?php checked(in_array($color['hex'], $sold_out, true)); ?>>
                                        <?php echo esc_html($color['label']); ?>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                            <p class="description">
                                Marca los colores que no llevaron al stand. Aparecen deshabilitados en el panel
                                para que el operador no ofrezca algo que no tiene en el mostrador.
                            </p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" name="collar_feria_save" class="button button-primary">Guardar ajustes</button>
                    <button type="submit" name="collar_feria_refresh" class="button">Releer catálogo</button>
                </p>
            </form>

            <hr>

            <h2>Catálogo detectado</h2>
            <p>
                <strong><?php echo (int) $emoji_count; ?></strong> emojis en
                <strong><?php echo count($catalog['emojis']); ?></strong> colores &middot;
                <strong><?php echo count($catalog['glitter']); ?></strong> caracteres con versión glitter.
            </p>
            <p class="description">
                El panel lee estos archivos directamente de <code>assets/</code>, igual que el configurador de
                la tienda. Si agregas o quitas charms, usa «Releer catálogo» para que el panel los tome.
            </p>
        </div>
        <?php
    }
}

new CollarFeriaPanel();
