<?php
/**
 * Panel Interno de Personalización — configurador asistido para punto presencial.
 *
 * Herramienta de apoyo para que un operador personalice collares junto al cliente
 * (en una feria o por WhatsApp) y genere una ficha (PNG) con el diseño y los datos
 * de contacto.
 *
 * Las fichas viven en el navegador (IndexedDB) y se exportan al final del evento.
 * Esto es deliberado: el WiFi de una feria es el punto de falla clásico, y el panel
 * debe seguir funcionando sin conexión.
 *
 * Lo único que toca WooCommerce es el "Link de pago" (ventas por WhatsApp): crea un
 * pedido pendiente con el collar armado y devuelve la página de pago del pedido para
 * mandársela al cliente. Necesita conexión, y el precio lo recalcula el servidor con
 * la misma fórmula de la tienda.
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
    const REWRITE_VER     = '2';

    /**
     * Slug público del panel y nombre visible.
     *
     * Los nombres de opción siguen diciendo "feria" a propósito: renombrarlos
     * perdería el PIN y los ajustes ya configurados. Solo cambia lo que ve el
     * usuario. Si se cambia SLUG hay que subir REWRITE_VER para refrescar los
     * permalinks, y el service worker se re-registra solo en la ruta nueva.
     */
    const SLUG  = 'panel-interno';
    const TITLE = 'Panel Interno de Personalización';

    /**
     * Tallas que el panel NO ofrece.
     *
     * Solo afecta al punto presencial: la tienda online sigue vendiendo XS con
     * normalidad, porque las constantes de CollarConfiguratorAJAX quedan intactas.
     * Para reponerla basta con vaciar este array.
     */
    const HIDDEN_SIZES = array('XS');

    /** Imágenes de referencia del producto que ve el asistente (IDs de adjuntos). */
    const OPT_GALLERY = 'collar_feria_galeria';

    const COOKIE       = 'collar_feria_auth';
    const SESSION_DAYS = 30;

    /** Un PIN de 4 dígitos son 10.000 combinaciones: sin límite de intentos es decorativo. */
    const MAX_FAILS     = 5;
    const LOCK_SECONDS  = 900;

    const CATALOG_TRANSIENT = 'collar_feria_catalogo';

    /** created_via de los pedidos creados con "Link de pago". Los identifica en WooCommerce. */
    const ORDER_VIA = 'panel-interno';

    /**
     * Días en que el cliente puede abrir el link de pago sin que WooCommerce le pida
     * confirmar su email. La clave del pedido en la URL ya es el secreto del link.
     */
    const PAY_LINK_DAYS = 30;

    public function __construct() {
        add_action('init', array($this, 'add_rewrite'));
        add_filter('query_vars', array($this, 'add_query_var'));

        // Prioridad 5: hay que renderizar antes de redirect_canonical (prioridad 10),
        // que si no manda /panel-interno a /panel-interno/ y el sw a .js/.
        add_action('template_redirect', array($this, 'maybe_render_panel'), 5);

        // Un service worker servido tras una redirección no se registra: el navegador
        // exige que la URL responda directamente. Por eso se desactiva la redirección
        // canónica en estas dos rutas y solo en ellas.
        add_filter('redirect_canonical', array($this, 'skip_canonical_redirect'), 10, 1);

        add_action('admin_menu', array($this, 'add_admin_menu'), 20);

        // El operador no es un usuario de WordPress: el desbloqueo va por nopriv.
        add_action('wp_ajax_nopriv_collar_feria_unlock', array($this, 'ajax_unlock'));
        add_action('wp_ajax_collar_feria_unlock', array($this, 'ajax_unlock'));

        // Link de pago para ventas por WhatsApp. Igual que el desbloqueo, lo autoriza
        // la cookie del panel, no un usuario de WordPress.
        add_action('wp_ajax_nopriv_collar_feria_create_order', array($this, 'ajax_create_order'));
        add_action('wp_ajax_collar_feria_create_order', array($this, 'ajax_create_order'));

        add_filter('woocommerce_order_email_verification_grace_period', array($this, 'pay_link_grace_period'), 10, 2);
    }

    /* ---------------------------------------------------------------------
     * Ruta pública del panel (self::SLUG)
     * ------------------------------------------------------------------ */

    public function add_rewrite() {
        add_rewrite_rule('^' . self::SLUG . '/?$', 'index.php?collar_feria=1', 'top');

        // El service worker se sirve desde la raíz a propósito: su alcance debe
        // cubrir /wp-content/, donde están los charms. Si viviera bajo el panel
        // no podría cachearlos.
        add_rewrite_rule('^' . self::SLUG . '-sw\.js$', 'index.php?collar_feria_sw=1', 'top');

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
        $panel_path  = rtrim(wp_parse_url(home_url('/' . self::SLUG), PHP_URL_PATH), '/');

        $uploads      = wp_get_upload_dir();
        $uploads_path = !empty($uploads['baseurl'])
            ? wp_parse_url(trailingslashit($uploads['baseurl']), PHP_URL_PATH)
            : '';

        $js = str_replace(
            array('__FERIA_ASSETS__', '__FERIA_UPLOADS__', '__FERIA_PATH__'),
            array($assets_path, $uploads_path, $panel_path),
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
     * Es obligatorio, no una optimización: el panel devuelve la pantalla de PIN o el
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
            $this->render_shell(
                '<div class="feria-gate-msg">'
                . '<h1>' . esc_html(self::TITLE) . '</h1>'
                . '<p>Todavía no se ha configurado un PIN de acceso.</p>'
                . '<p class="feria-gate-hint">Un administrador debe definirlo en '
                . '<strong>Collares &rsaquo; Panel Interno</strong> dentro de WordPress.</p>'
                . '</div>'
            );
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
     * Link de pago (ventas por WhatsApp)
     * ------------------------------------------------------------------ */

    /**
     * La petición viene de una página del propio sitio. La cookie del panel ya es
     * SameSite=Lax por defecto, pero un panel que crea pedidos merece el cinturón y
     * los tirantes. No se usa nonce a propósito: el panel queda abierto días en la
     * tablet y un nonce muere a las 24 horas.
     */
    private function is_same_origin() {
        $source = '';
        if (!empty($_SERVER['HTTP_ORIGIN'])) {
            $source = wp_unslash($_SERVER['HTTP_ORIGIN']);
        } elseif (!empty($_SERVER['HTTP_REFERER'])) {
            $source = wp_unslash($_SERVER['HTTP_REFERER']);
        }
        $host = wp_parse_url($source, PHP_URL_HOST);
        $home = wp_parse_url(home_url(), PHP_URL_HOST);
        return $host && $home && strtolower($host) === strtolower($home);
    }

    public function ajax_create_order() {
        nocache_headers();

        if (!$this->has_valid_session()) {
            wp_send_json_error(array('message' => 'La sesión del panel venció. Recarga la página e ingresa el PIN.'), 403);
        }
        if (!$this->is_same_origin()) {
            wp_send_json_error(array('message' => 'Solicitud no permitida.'), 403);
        }
        if (!function_exists('wc_create_order')) {
            wp_send_json_error(array('message' => 'WooCommerce no está disponible.'));
        }

        $data = isset($_POST['payload']) ? json_decode(wp_unslash($_POST['payload']), true) : null;
        if (!is_array($data)) {
            wp_send_json_error(array('message' => 'No llegaron los datos del pedido.'));
        }

        try {
            wp_send_json_success($this->create_pay_order($data));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Valida cada charm contra el catálogo del disco. Lo que guarda el pedido es lo
     * que verá producción, así que no se acepta nada que la tienda no venda.
     */
    private function sanitize_charms($raw) {
        if (!is_array($raw) || empty($raw)) {
            throw new Exception('El collar no tiene charms.');
        }
        if (count($raw) > CollarConfiguratorAJAX::MAX_CHARMS * 2) {
            throw new Exception('Demasiados charms.');
        }

        $catalog = $this->get_catalog();
        $colors  = wp_list_pluck($this->charm_colors(), 'hex');
        $out     = array();

        foreach ($raw as $c) {
            if (!is_array($c)) {
                throw new Exception('Diseño no válido.');
            }
            $value    = isset($c['value']) ? (string) $c['value'] : '';
            $hex      = isset($c['colorHex']) ? strtoupper((string) $c['colorHex']) : '';
            $is_emoji = !empty($c['isEmoji']);

            if (!in_array($hex, $colors, true)) {
                throw new Exception('Hay un charm con un color que no existe.');
            }
            if ($is_emoji) {
                if (empty($catalog['emojis'][$hex]) || !in_array($value, $catalog['emojis'][$hex], true)) {
                    throw new Exception('Hay un emoji que no está en el catálogo.');
                }
            } elseif (!in_array($value, $catalog['glitter'], true)) {
                throw new Exception('Hay una letra que no está en el catálogo.');
            }

            $agotada = CollarConfiguratorAJAX::charms_agotados(array(array('value' => $value, 'colorHex' => $hex)));
            if ($agotada) {
                throw new Exception('Pieza agotada: ' . $agotada[0] . '. Cámbiala por otro color.');
            }

            $out[] = array(
                'value'     => $value,
                'isEmoji'   => $is_emoji,
                'colorHex'  => $hex,
                'isGlitter' => !$is_emoji && in_array($hex, array('#GOLD', '#SILVER'), true),
            );
        }

        return $out;
    }

    /** Teléfono en formato wa.me (solo dígitos, con 57 si es un celular colombiano de 10). */
    private function whatsapp_digits($phone) {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (strlen($digits) === 10 && $digits[0] === '3') {
            $digits = '57' . $digits;
        }
        return $digits;
    }

    /**
     * Crea el pedido pendiente con el collar armado y devuelve el link de pago.
     * Replica lo que hace el checkout de la tienda (mismas metas del ítem, mismo
     * cargo de envío, misma meta de zona) para que el pedido se vea igual en el
     * admin, en los correos y en la tabla de configuraciones.
     */
    private function create_pay_order(array $data) {
        $product_id = (int) get_option('collar_product_id', 0);
        $product    = $product_id ? wc_get_product($product_id) : null;
        if (!$product) {
            throw new Exception('No hay un producto de collar configurado en la tienda.');
        }

        // --- Collar ---
        $size = strtoupper(sanitize_text_field(isset($data['size']) ? $data['size'] : ''));
        if (!in_array($size, $this->visible_sizes(), true)) {
            throw new Exception('Talla no válida.');
        }

        $color = sanitize_text_field(isset($data['color']) ? $data['color'] : '');
        if (!in_array($color, wp_list_pluck($this->collar_colors(), 'label'), true)) {
            throw new Exception('Color de collar no válido.');
        }

        $charms = $this->sanitize_charms(isset($data['charms']) ? $data['charms'] : null);
        $count  = count($charms);
        $price  = CollarConfiguratorAJAX::calculate_collar_price($size, $count);
        if ($price <= 0) {
            throw new Exception(sprintf(
                'El collar talla %s lleva entre %d y %d charms.',
                $size,
                CollarConfiguratorAJAX::MIN_CHARMS,
                CollarConfiguratorAJAX::max_charms_for_size($size)
            ));
        }

        // --- Cliente ---
        $c     = isset($data['customer']) && is_array($data['customer']) ? $data['customer'] : array();
        $name  = sanitize_text_field(isset($c['name']) ? $c['name'] : '');
        $pet   = sanitize_text_field(isset($c['pet']) ? $c['pet'] : '');
        $notes = sanitize_text_field(isset($c['notes']) ? $c['notes'] : '');
        $email = sanitize_email(isset($c['email']) ? $c['email'] : '');
        $phone = preg_replace('/\D+/', '', isset($c['phone']) ? (string) $c['phone'] : '');

        if ($name === '') {
            throw new Exception('Escribe el nombre del cliente.');
        }
        if (strlen($phone) < 10) {
            throw new Exception('Escribe el WhatsApp del cliente (10 dígitos).');
        }
        if (!empty($c['email']) && !is_email($email)) {
            throw new Exception('El email no es válido.');
        }

        // --- Envío ---
        $s       = isset($data['shipping']) && is_array($data['shipping']) ? $data['shipping'] : array();
        $zone    = sanitize_key(isset($s['zone']) ? $s['zone'] : '');
        $options = CollarConfiguratorAJAX::shipping_options();
        if (!isset($options[$zone])) {
            throw new Exception('Elige el método de envío.');
        }

        $address = sanitize_text_field(isset($s['address']) ? $s['address'] : '');
        $city    = sanitize_text_field(isset($s['city']) ? $s['city'] : '');
        $state   = sanitize_text_field(isset($s['state']) ? $s['state'] : '');
        $barrio  = sanitize_text_field(isset($s['barrio']) ? $s['barrio'] : '');

        if ($zone !== 'recoger' && $address === '') {
            throw new Exception('Escribe la dirección de envío.');
        }
        if ($zone === 'nacional' && ($city === '' || $state === '')) {
            throw new Exception('Para envío nacional escribe la ciudad y el departamento.');
        }
        $coupon = wc_format_coupon_code(sanitize_text_field(isset($data['coupon']) ? $data['coupon'] : ''));

        // --- Pedido ---
        $order = wc_create_order(array('created_via' => self::ORDER_VIA));
        if (is_wp_error($order)) {
            throw new Exception('WooCommerce no pudo crear el pedido.');
        }

        try {
            $item_id = $order->add_product($product, 1, array('subtotal' => $price, 'total' => $price));
            $item    = $order->get_item($item_id);
            if (!$item) {
                throw new Exception('No se pudo agregar el collar al pedido.');
            }

            $charms_json = wp_json_encode($charms);
            $item->add_meta_data('Tamaño', $size, true);
            $item->add_meta_data('Color del collar', $color, true);
            $item->add_meta_data('Charms', $count, true);
            $summary = CollarConfiguratorAJAX::build_charms_summary($charms_json);
            if ($summary !== '') {
                $item->add_meta_data('Diseño', $summary, true);
            }
            if ($pet !== '') {
                $item->add_meta_data('Nombre del peludo', $pet, true);
            }
            $item->add_meta_data('_collar_charms_data', $charms_json, true);
            $item->save();

            if ($options[$zone]['cost'] > 0) {
                $fee = new WC_Order_Item_Fee();
                $fee->set_name($options[$zone]['label']);
                $fee->set_amount($options[$zone]['cost']);
                $fee->set_total($options[$zone]['cost']);
                $fee->set_tax_status('none');
                $order->add_item($fee);
            }

            $parts = preg_split('/\s+/', $name, 2);
            $first = $parts[0];
            $last  = isset($parts[1]) ? $parts[1] : '';

            $order->set_billing_first_name($first);
            $order->set_billing_last_name($last);
            $order->set_billing_phone($phone);
            $order->set_billing_country('CO');
            if ($email !== '') {
                $order->set_billing_email($email);
            }

            $order->update_meta_data('_collar_shipping_zone', $zone);
            if ($zone !== 'recoger') {
                $order->set_shipping_first_name($first);
                $order->set_shipping_last_name($last);
                $order->set_shipping_address_1($address);
                $order->set_shipping_address_2($barrio);
                $order->set_shipping_city($city);
                $order->set_shipping_state($state);
                $order->set_shipping_country('CO');
                $order->set_billing_address_1($address);
                $order->set_billing_city($city);
                $order->set_billing_state($state);
            }

            $order->calculate_totals();
            $order->save();

            if ($coupon !== '') {
                $applied = $order->apply_coupon($coupon);
                if (is_wp_error($applied)) {
                    throw new Exception('Cupón: ' . wp_strip_all_tags($applied->get_error_message()));
                }
            }

            $evento = get_option(self::OPT_EVENT, '');
            $order->add_order_note(
                'Pedido creado desde el Panel Interno con "Link de pago" (venta por WhatsApp).'
                . ($evento !== '' ? ' Evento: ' . $evento . '.' : '')
                . ($notes !== '' ? ' Notas: ' . $notes : '')
            );
            $order->save();
        } catch (Exception $e) {
            // Sin pedidos a medio crear: si algo falla, el pedido no queda en el admin.
            $order->delete(true);
            throw $e;
        }

        $total      = (float) $order->get_total();
        $total_text = '$' . number_format($total, 0, ',', '.');
        $pay_url    = $order->get_checkout_payment_url();

        $message = '¡Hola ' . $first . '! Este es el link para pagar '
            . ($pet !== '' ? 'el collar de ' . $pet : 'tu collar Guapo')
            . ' (pedido #' . $order->get_order_number() . ', total ' . $total_text . '):' . "\n" . $pay_url;

        return array(
            'orderId'    => $order->get_id(),
            'number'     => $order->get_order_number(),
            'payUrl'     => $pay_url,
            'total'      => $total,
            'totalText'  => $total_text,
            'discount'   => (float) $order->get_discount_total(),
            'message'    => $message,
            'waUrl'      => 'https://wa.me/' . $this->whatsapp_digits($phone) . '?text=' . rawurlencode($message),
            'adminUrl'   => $order->get_edit_order_url(),
        );
    }

    /**
     * El cliente abre el link de pago horas o días después. Pasados 10 minutos de
     * creado el pedido, WooCommerce le pediría confirmar su email antes de pagar,
     * un paso que en una venta por WhatsApp solo hace perder la venta.
     */
    public function pay_link_grace_period($grace, $order) {
        if ($order instanceof WC_Order && $order->get_created_via() === self::ORDER_VIA) {
            return max((int) $grace, self::PAY_LINK_DAYS * DAY_IN_SECONDS);
        }
        return $grace;
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

        // Las referencias del producto también deben verse sin conexión: son para
        // enseñárselas al cliente, y el stand es justo donde falla el WiFi.
        foreach ($this->gallery_images() as $img) {
            $urls[] = $img['full'];
            if ($img['thumb'] !== $img['full']) {
                $urls[] = $img['thumb'];
            }
        }

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
            // Ruta del service worker: se calcula aquí para que renombrar SLUG no
            // deje al JS registrando una URL que ya no existe.
            'swUrl'       => wp_parse_url(home_url('/' . self::SLUG . '-sw.js'), PHP_URL_PATH),
            'evento'      => get_option(self::OPT_EVENT, ''),
            'basePrices'  => CollarConfiguratorAJAX::BASE_PRICES,
            'extraCharm'  => CollarConfiguratorAJAX::EXTRA_CHARM_PRICE,
            'baseCharms'  => CollarConfiguratorAJAX::BASE_CHARMS_INCLUDED,
            'minCharms'   => CollarConfiguratorAJAX::MIN_CHARMS,
            'maxBySize'   => CollarConfiguratorAJAX::MAX_CHARMS_BY_SIZE,
            'sizes'       => $this->visible_sizes(),
            'gallery'     => $this->gallery_images(),
            'xsColors'    => CollarConfiguratorAJAX::XS_ALLOWED_COLORS,
            'collarColors'=> $this->collar_colors(),
            'charmColors' => $this->charm_colors(),
            'emojis'      => $catalog['emojis'],
            'glitter'     => $catalog['glitter'],
            'soldOut'     => array_values($sold_out),
            // Letras y emojis sueltos sin stock por color (Collares → Ajustes).
            'piezasAgotadas' => (object) CollarConfiguratorAJAX::piezas_agotadas(),
            'precache'    => $this->get_precache_urls(),
            // Link de pago: tarifas de envío para la cotización del cajón de pago.
            'shipping'    => CollarConfiguratorAJAX::shipping_options(),
        );
    }

    /** Tallas ofrecidas en el panel, sin las ocultas. */
    public function visible_sizes() {
        $sizes = array_diff(CollarConfiguratorAJAX::ALLOWED_SIZES, self::HIDDEN_SIZES);
        return array_values($sizes);
    }

    /**
     * Imágenes de referencia del producto para mostrar al cliente en el stand.
     *
     * Se toman de la galería configurada a mano y, si está vacía, de la galería
     * del producto en WooCommerce: así hay algo que mostrar sin configurar nada.
     * Es solo lectura de imágenes; no crea ni toca pedidos.
     */
    public function gallery_images() {
        $ids = get_option(self::OPT_GALLERY, array());
        if (!is_array($ids)) {
            $ids = array();
        }
        $ids = array_filter(array_map('intval', $ids));

        if (empty($ids)) {
            $product_id = (int) get_option('collar_product_id', 0);
            if ($product_id) {
                $thumb = get_post_thumbnail_id($product_id);
                if ($thumb) {
                    $ids[] = (int) $thumb;
                }
                $gallery = get_post_meta($product_id, '_product_image_gallery', true);
                if (!empty($gallery)) {
                    foreach (explode(',', $gallery) as $gid) {
                        $gid = (int) $gid;
                        if ($gid) {
                            $ids[] = $gid;
                        }
                    }
                }
            }
        }

        $images = array();
        foreach (array_unique($ids) as $id) {
            $full = wp_get_attachment_image_src($id, 'large');
            if (!$full) {
                continue;
            }
            $thumb = wp_get_attachment_image_src($id, 'medium');
            $images[] = array(
                'full'    => $full[0],
                'thumb'   => $thumb ? $thumb[0] : $full[0],
                'caption' => wp_get_attachment_caption($id) ?: get_the_title($id),
            );
        }

        return $images;
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
<title><?php echo esc_html(self::TITLE); ?> — <?php bloginfo('name'); ?></title>
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
                <h1><?php echo esc_html(self::TITLE); ?></h1>
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
            self::TITLE,
            'Panel Interno',
            'manage_options',
            'collar-feria',
            array($this, 'admin_page')
        );
    }

    public function admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Sin permisos.');
        }

        // Selector de imágenes de la biblioteca de medios. Llamarlo aquí funciona:
        // los scripts se imprimen en el pie del admin, después de este render.
        wp_enqueue_media();

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

            update_option('collar_nonce_watchdog_notify', isset($_POST['feria_wd_notify']) ? '1' : '');
            $wd_email = isset($_POST['feria_wd_email']) ? sanitize_email(wp_unslash($_POST['feria_wd_email'])) : '';
            update_option('collar_nonce_watchdog_email', is_email($wd_email) ? $wd_email : '');

            $gallery = isset($_POST['feria_galeria']) ? wp_unslash($_POST['feria_galeria']) : '';
            $gallery = array_filter(array_map('intval', explode(',', $gallery)));
            update_option(self::OPT_GALLERY, array_values($gallery));

            $notices[] = array('updated', 'Ajustes guardados.');
        }

        if (isset($_POST['collar_feria_refresh']) && check_admin_referer('collar_feria_settings')) {
            delete_transient(self::CATALOG_TRANSIENT);
            $notices[] = array('updated', 'Catálogo releído desde los archivos.');
        }

        $catalog   = $this->get_catalog();
        $sold_out  = (array) get_option(self::OPT_SOLD_OUT, array());
        $has_pin   = (bool) get_option(self::OPT_PIN, '');
        $panel_url = home_url('/' . self::SLUG);

        $emoji_count = 0;
        foreach ($catalog['emojis'] as $files) {
            $emoji_count += count($files);
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(self::TITLE); ?></h1>

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
                Herramienta de apoyo para personalizar collares en punto presencial o por WhatsApp.
                «Guardar ficha» solo genera una ficha con el diseño y los datos del cliente.
                «Link de pago» crea un pedido pendiente en WooCommerce y entrega el link para que
                el cliente pague (Bold o Addi); el pedido aparece en WooCommerce como cualquier otro.
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
                        <th scope="row">Imágenes de referencia</th>
                        <td>
                            <div id="feria-galeria-preview" class="feria-galeria-admin"></div>
                            <input type="hidden" id="feria_galeria" name="feria_galeria"
                                   value="<?php echo esc_attr(implode(',', (array) get_option(self::OPT_GALLERY, array()))); ?>">
                            <p>
                                <button type="button" class="button" id="feria-galeria-elegir">Elegir imágenes</button>
                                <button type="button" class="button" id="feria-galeria-limpiar">Quitar todas</button>
                            </p>
                            <p class="description">
                                Fotos del producto que el asistente puede mostrarle al cliente en el stand.
                                Si no eliges ninguna, el panel usa la galería del producto en WooCommerce.
                                Se descargan con «Preparar para trabajar sin internet», así que también
                                se ven sin señal.
                            </p>
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

            <hr>

            <h2>Vigilante de nonces</h2>
            <?php
            $wd_ok      = class_exists('CollarNonceWatchdog');
            $wd         = $wd_ok ? CollarNonceWatchdog::status() : array();
            // Se resuelve una vez y con guarda: si el módulo del vigilante no cargara,
            // una referencia directa a la constante dejaría esta página en error fatal
            // y sin acceso a los ajustes del panel.
            $wd_default = $wd_ok ? CollarNonceWatchdog::DEFAULT_EMAIL : '';
            $next = wp_next_scheduled('collar_nonce_watchdog');
            ?>
            <?php if (empty($wd)): ?>
                <p>Aún no se ha ejecutado ninguna revisión.</p>
            <?php else: ?>
                <p>
                    <?php if (empty($wd['problems'])): ?>
                        <span style="color:#008a20;font-weight:600;">Todo correcto.</span>
                    <?php else: ?>
                        <span style="color:#b45309;font-weight:600;">Nonce vencido en caché</span>
                        (<?php echo (int) $wd['streak']; ?> revisión<?php echo ((int) $wd['streak']) === 1 ? '' : 'es'; ?> seguida<?php echo ((int) $wd['streak']) === 1 ? '' : 's'; ?>).
                        Sin impacto para el cliente: el configurador reintenta solo.
                    <?php endif; ?>
                    Última revisión: <?php echo esc_html($wd['time']); ?>
                    (<?php echo (int) $wd['checked']; ?> página<?php echo ((int) $wd['checked']) === 1 ? '' : 's'; ?>).
                </p>

                <?php if (!empty($wd['history'])): ?>
                    <table class="widefat striped" style="max-width:520px;margin-bottom:12px;">
                        <thead><tr><th>Cuándo</th><th>Qué pasó</th></tr></thead>
                        <tbody>
                        <?php foreach (array_reverse($wd['history']) as $h): ?>
                            <tr>
                                <td><?php echo esc_html($h['time']); ?></td>
                                <td>
                                    <?php echo $h['evento'] === 'purga'
                                        ? 'Nonce vencido — se purgaron ' . (int) $h['urls'] . ' página(s)'
                                        : 'Resuelto'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>

            <form method="post" style="margin:12px 0;">
                <?php wp_nonce_field('collar_feria_settings'); ?>
                <p>
                    <label>
                        <input type="checkbox" name="feria_wd_notify" value="1"
                            <?php checked(get_option('collar_nonce_watchdog_notify', ''), '1'); ?>>
                        <strong>Avisar por correo</strong>
                    </label>
                    <span class="description">
                        Desactivado por defecto. Esta situación se repara sola y no bloquea ninguna
                        venta, así que avisar de cada caso sería ruido. Actívalo solo si quieres
                        enterarte cuando persista más de <?php echo (int) (class_exists('CollarNonceWatchdog') ? CollarNonceWatchdog::ALERT_AFTER : 3); ?> revisiones seguidas.
                    </span>
                </p>
                <label for="feria_wd_email"><strong>Avisar a:</strong></label>
                <input type="email" id="feria_wd_email" name="feria_wd_email" class="regular-text"
                       value="<?php echo esc_attr(get_option('collar_nonce_watchdog_email', '')); ?>"
                       placeholder="<?php echo esc_attr($wd_default); ?>">
                <button type="submit" name="collar_feria_save" class="button">Guardar</button>
                <p class="description">
                    Correo técnico de quien mantiene el sitio. Si se deja vacío se usa
                    <code><?php echo esc_html($wd_default); ?></code>.
                    Nunca se envía al correo del administrador del sitio: es el buzón del cliente
                    y este aviso solo alarma a quien no puede actuar sobre él.
                </p>
            </form>

            <p class="description">
                Revisa cada hora las páginas con los shortcodes del collar tal como las recibe un
                visitante anónimo. Si el nonce servido ya venció, purga solo esas páginas. No avisa por correo salvo que se active arriba.
                LiteSpeed guarda el HTML hasta 7 días y un nonce vive 24 horas, así que sin esto una
                página cacheada puede quedar sirviendo un token muerto durante días.
                <?php if ($next): ?>
                    Próxima revisión: <?php echo esc_html(get_date_from_gmt(gmdate('Y-m-d H:i:s', $next), 'Y-m-d H:i')); ?>.
                <?php endif; ?>
            </p>
        </div>

        <style>
            .feria-galeria-admin {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                margin-bottom: 10px;
            }
            .feria-galeria-admin img {
                width: 80px;
                height: 80px;
                object-fit: cover;
                border-radius: 6px;
                border: 1px solid #dcdcde;
            }
            .feria-galeria-admin p { color: #646970; margin: 0; }
        </style>

        <script>
        jQuery(function ($) {
            var input   = $('#feria_galeria');
            var preview = $('#feria-galeria-preview');
            var frame   = null;

            function paint(images) {
                preview.empty();
                if (!images.length) {
                    preview.append($('<p>').text('Sin imágenes propias — se usará la galería del producto.'));
                    return;
                }
                images.forEach(function (src) {
                    preview.append($('<img>').attr('src', src).attr('alt', ''));
                });
            }

            // Estado inicial: se piden las miniaturas de los IDs ya guardados.
            var ids = input.val() ? input.val().split(',').filter(Boolean) : [];
            if (ids.length) {
                wp.media.query({ post__in: ids, posts_per_page: -1, type: 'image' })
                    .more().then(function () {
                        var srcs = [];
                        wp.media.query({ post__in: ids, posts_per_page: -1, type: 'image' })
                            .each(function (m) {
                                var sizes = m.get('sizes');
                                srcs.push(sizes && sizes.thumbnail ? sizes.thumbnail.url : m.get('url'));
                            });
                        paint(srcs);
                    });
            } else {
                paint([]);
            }

            $('#feria-galeria-elegir').on('click', function (e) {
                e.preventDefault();

                if (!frame) {
                    frame = wp.media({
                        title: 'Imágenes de referencia del producto',
                        button: { text: 'Usar estas imágenes' },
                        library: { type: 'image' },
                        multiple: true
                    });

                    frame.on('select', function () {
                        var sel  = frame.state().get('selection');
                        var ids  = [];
                        var srcs = [];
                        sel.each(function (m) {
                            ids.push(m.id);
                            var sizes = m.get('sizes');
                            srcs.push(sizes && sizes.thumbnail ? sizes.thumbnail.url : m.get('url'));
                        });
                        input.val(ids.join(','));
                        paint(srcs);
                    });
                }

                frame.open();
            });

            $('#feria-galeria-limpiar').on('click', function (e) {
                e.preventDefault();
                input.val('');
                paint([]);
            });
        });
        </script>
        <?php
    }
}

new CollarFeriaPanel();
