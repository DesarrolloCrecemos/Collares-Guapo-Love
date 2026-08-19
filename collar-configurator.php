<?php
/**
 * Plugin Name: Configurador de Collar
 * Description: Configurador interactivo de collares personalizados con WooCommerce. Integra precios server-side, panel de pedidos con visualización de charms, correos enriquecidos, garantía de notificación de pedidos al admin y página de gracias personalizada.
 * Version: 1.6.2
 * Author: Guapolove
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Panel de Feria: configurador asistido para punto presencial. Módulo aditivo,
// no interviene el flujo de venta ni WooCommerce. Lee precios y límites de
// CollarConfiguratorAJAX (definida más abajo) solo en tiempo de ejecución.
require_once plugin_dir_path(__FILE__) . 'feria-panel.php';

// Registrar scripts y estilos
function collar_enqueue_assets() {
    // NO hacer nada aquí - solo cargar cuando se use el shortcode
}
add_action('wp_enqueue_scripts', 'collar_enqueue_assets');

// Shortcode del configurador
function collar_configurador_shortcode($atts) {
    $atts = shortcode_atts(array(
        'show_title' => 'true',
        'title' => '¡Hazlo irrepetible como tu peludo!',
    ), $atts);
    
    // Obtener URL base del plugin
    $plugin_url = plugin_dir_url(__FILE__);
    
    // Generar ID único para este configurador
    static $instance = 0;
    $instance++;
    $unique_id = 'collar-config-' . $instance;
    
    // Obtener contenido de CSS y JS
    $css_content = file_get_contents(plugin_dir_path(__FILE__) . 'collar-configurator-wawas.css');
    $js_content = file_get_contents(plugin_dir_path(__FILE__) . 'collar-configurator-wawas.js');
    
    ob_start();
    ?>
    <!-- Estilos del configurador -->
    <style type="text/css">
    <?php echo $css_content; ?>
    </style>
    
    <div class="collar-configurador-wrapper" id="<?php echo esc_attr($unique_id); ?>">
        <?php if ($atts['show_title'] === 'true'): ?>
            <h2 style="text-align:center;color:#374151;margin-bottom:20px;font-family:'Bebas Neue',sans-serif;font-weight:normal;font-size:32px;letter-spacing:1px;">
                <img src="https://guapolove.com/wp-content/uploads/2026/03/ICONO-huella-perro-scaled.png" style="height:30px;vertical-align:middle;margin-right:8px;" alt="">
                <?php echo esc_html(trim(str_replace('🐾', '', $atts['title']))); ?>
                <img src="https://guapolove.com/wp-content/uploads/2026/03/ICONO-huella-perro-scaled.png" style="height:30px;vertical-align:middle;margin-left:8px;" alt="">
            </h2>
        <?php endif; ?>
        
        <?php include(plugin_dir_path(__FILE__) . 'configurator-template-wawas.php'); ?>
    </div>
    
    <script type="text/javascript">
    // Variables de configuración para el configurador (GLOBAL)
    if (typeof collarConfig === 'undefined') {
        window.collarConfig = {
            pluginUrl:      '<?php echo esc_js($plugin_url); ?>',
            assetsUrl:      '<?php echo esc_js($plugin_url . 'assets/'); ?>',
            ajaxUrl:        '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
            nonce:          '<?php echo wp_create_nonce('collar_config_nonce'); ?>',
            gummyProductId: <?php echo (int) get_option('gummy_product_id', 0); ?>
        };
    }
    
    // Cargar el JavaScript del configurador (SIN ENVOLVER en función anónima)
    <?php echo $js_content; ?>
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('collar_configurador', 'collar_configurador_shortcode');

class CollarConfiguratorAJAX {

    // Precios autoritativos del servidor. NO confiar en valores del cliente.
    const ALLOWED_SIZES = array('XS', 'S', 'M', 'L', 'XL');

    const BASE_PRICES = array(
        'XS' => 69800,
        'S'  => 75800,
        'M'  => 75800,
        'L'  => 75800,
        'XL' => 75800,
    );

    const EXTRA_CHARM_PRICE = array(
        'XS' => 3000,
        'S'  => 4000,
        'M'  => 4000,
        'L'  => 4000,
        'XL' => 4000,
    );

    const BASE_CHARMS_INCLUDED = 6;
    const MIN_CHARMS = 6;
    const MAX_CHARMS = 9; // Tope general (fallback)

    // Máximo de charms por talla (por espacio físico en el collar).
    // Por ahora 9 para todas; ajustar cuando confirmen el máximo real de cada talla.
    const MAX_CHARMS_BY_SIZE = array(
        'XS' => 9,
        'S'  => 9,
        'M'  => 9,
        'L'  => 9,
        'XL' => 9,
    );

    // Colores de collar con stock por talla. Solo XS está restringido (stock limitado).
    // Las demás tallas admiten todos los colores, por eso no se listan aquí.
    const XS_ALLOWED_COLORS = array('Rosa', 'Rojo', 'Azul');

    // Precio del Gummy dentro del bundle, por tamaño del collar (10% off).
    const BUNDLE_GUMMY_PRICE = array(
        'XS' => 38000,
        'S'  => 37300,
        'M'  => 37300,
        'L'  => 37300,
        'XL' => 37300,
    );

    /**
     * Calcular precio del collar en servidor. Único cálculo confiable.
     */
    private function max_charms_for_size($size) {
        return isset(self::MAX_CHARMS_BY_SIZE[$size]) ? self::MAX_CHARMS_BY_SIZE[$size] : self::MAX_CHARMS;
    }

    private function calculate_collar_price($size, $charms_count) {
        if (!in_array($size, self::ALLOWED_SIZES, true)) return 0;
        if ($charms_count < self::MIN_CHARMS || $charms_count > $this->max_charms_for_size($size)) return 0;

        $base   = self::BASE_PRICES[$size];
        $extras = max(0, $charms_count - self::BASE_CHARMS_INCLUDED);
        return $base + ($extras * self::EXTRA_CHARM_PRICE[$size]);
    }

    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_collar_add_to_cart', array($this, 'ajax_add_to_cart'));
        add_action('wp_ajax_nopriv_collar_add_to_cart', array($this, 'ajax_add_to_cart'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Mostrar datos personalizados en el carrito y checkout
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_cart_item_data'), 10, 3);
        add_filter('woocommerce_get_item_data', array($this, 'display_cart_item_data'), 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_order_item_data'), 10, 4);        
        // Hook para modificar el precio del producto en el carrito
        add_action('woocommerce_before_calculate_totals', array($this, 'update_cart_item_price'), 10, 1);
        
        // Hacer el producto siempre "purchasable" aunque su precio base esté vacío.
        // WooCommerce verifica is_purchasable() antes de add_to_cart; si el precio base
        // es '' (vacío), retorna false para usuarios no logueados aunque los admins sí
        // pueden agregarlo (tienen edit_post). Nuestro precio real se aplica después
        // via woocommerce_before_calculate_totals.
        add_filter('woocommerce_is_purchasable', array($this, 'make_collar_purchasable'), 10, 2);

        // Envio: selector personalizado + fee segun seleccion (funciona con productos virtuales)
        add_action('woocommerce_checkout_after_customer_details', array($this, 'render_shipping_selector'));
        add_action('woocommerce_checkout_update_order_review',    array($this, 'save_shipping_zone_to_session'));
        add_action('woocommerce_cart_calculate_fees',             array($this, 'add_shipping_fee'));
        add_action('wp_footer',                                   array($this, 'enqueue_checkout_shipping_js'));
        add_action('wp_footer',                                   array($this, 'render_whatsapp_floating_button'), 99);
        add_action('woocommerce_checkout_process',                array($this, 'validate_shipping_address'));
        add_action('woocommerce_checkout_update_order_meta',      array($this, 'save_shipping_address_to_order'), 10, 2);

        // Calidad de datos en checkout: mejorar campo de celular, agregar confirmación
        // y validar formato (celular colombiano: 10 dígitos que empiezan por 3).
        add_filter('woocommerce_checkout_fields', array($this, 'enhance_checkout_fields'), 20);
        add_action('woocommerce_checkout_process', array($this, 'validate_billing_phone'));
        
        // Guardar configuración en tabla personalizada al completar orden
        add_action('woocommerce_checkout_order_processed', array($this, 'save_collar_to_db'), 10, 3);
        add_action('woocommerce_order_status_changed', array($this, 'save_collar_on_status_change'), 10, 4);
        
        // Crear tabla personalizada al activar
        register_activation_hook(__FILE__, array($this, 'create_collar_orders_table'));
        
        // Crear tabla si no existe (por si se actualiza sin reactivar)
        add_action('admin_init', array($this, 'maybe_create_table'));

        // Redirigir la página del producto al configurador
        add_action('template_redirect', array($this, 'redirect_product_to_configurator'));

        // En el listing: mostrar precio descriptivo y reemplazar botón
        add_filter('woocommerce_get_price_html',         array($this, 'collar_price_html'), 10, 2);
        add_filter('woocommerce_loop_add_to_cart_link',  array($this, 'collar_loop_button'), 10, 3);

        // Bloquear add-to-cart directo (sin pasar por el configurador)
        add_filter('woocommerce_add_to_cart_validation', array($this, 'block_direct_add_to_cart'), 10, 2);

        // Collar Gummy: AJAX add to cart
        add_action('wp_ajax_gummy_add_to_cart',        array($this, 'gummy_ajax_add_to_cart'));
        add_action('wp_ajax_nopriv_gummy_add_to_cart', array($this, 'gummy_ajax_add_to_cart'));

        // Entregar un nonce fresco (admin-ajax NO se cachea). Resuelve el 403 cuando la
        // página del configurador queda cacheada y el nonce impreso expira.
        add_action('wp_ajax_collar_refresh_nonce',        array($this, 'ajax_refresh_nonce'));
        add_action('wp_ajax_nopriv_collar_refresh_nonce', array($this, 'ajax_refresh_nonce'));

        // Ocultar metas técnicos del display en pedidos (admin, emails, gracias)
        add_filter('woocommerce_hidden_order_itemmeta', array($this, 'hide_internal_order_meta'));
        // Inyectar "Diseño" legible al vuelo para pedidos viejos que no lo tengan
        add_filter('woocommerce_order_item_get_formatted_meta_data', array($this, 'inject_design_meta'), 10, 2);
        // Renderizar visual de charms y método de envío dentro de los correos del pedido
        add_action('woocommerce_email_after_order_table', array($this, 'render_collar_visual_in_email'), 10, 4);
        // Página de gracias personalizada (preview + tracking de llegada después de Bold/Addi)
        add_action('woocommerce_thankyou', array($this, 'render_thankyou_collar_preview'), 5, 1);

        // Garantía de notificación al admin para TODOS los pedidos pagados (Bold, Addi,
        // etc.). El correo nativo "Nuevo pedido" no se completa en el request del webhook
        // de Bold (verificado en producción: ningún pedido real tiene el marcador
        // _new_order_email_sent que WooCommerce graba tras un envío exitoso, aunque el
        // mismo envío funciona perfecto disparado por CLI). Este respaldo reintenta en
        // tres momentos: cambio de estado, página de gracias (request aparte) y un
        // vigilante horario por cron. No duplica: trigger() de WC no reenvía si
        // _new_order_email_sent ya está marcado.
        add_action('woocommerce_order_status_changed', array($this, 'maybe_send_admin_email_on_status'), 20, 4);
        add_action('woocommerce_thankyou',             array($this, 'maybe_send_admin_email_on_thankyou'), 20, 1);
        add_action('init',                             array($this, 'schedule_email_watchdog'));
        add_action('collar_admin_email_watchdog',      array($this, 'run_email_watchdog'));
        register_deactivation_hook(__FILE__, array($this, 'unschedule_email_watchdog'));
    }

    /**
     * Envía el correo "Nuevo pedido" al admin UNA sola vez por pedido pagado, para
     * cualquier método de pago. WC_Email_New_Order::trigger() ya evita duplicados:
     * ignora la llamada si el pedido tiene _new_order_email_sent (que WooCommerce
     * marca solo tras un envío exitoso). Si el correo nativo funcionó, esto no hace nada.
     */
    private function send_admin_new_order_email_once($order) {
        if (!$order) return;

        // Solo estados que implican pago confirmado (evita falsos positivos).
        if (!in_array($order->get_status(), array('processing', 'on-hold', 'completed'), true)) return;

        // Ya enviado (por WooCommerce nativo, por este respaldo o por el botón manual).
        if ($order->get_meta('_collar_admin_email_sent') === 'yes') return;
        if ($order->get_meta('_new_order_email_sent')) return;

        if (!function_exists('WC')) return;
        $mailer = WC()->mailer();
        if (!$mailer || empty($mailer->emails['WC_Email_New_Order'])) return;

        $mailer->emails['WC_Email_New_Order']->trigger($order->get_id(), $order);

        // trigger() marca _new_order_email_sent solo si wp_mail() tuvo éxito. Si no quedó
        // marcado no grabamos nuestro meta, para que la página de gracias o el vigilante
        // horario lo reintenten en un request nuevo.
        $fresh = wc_get_order($order->get_id());
        if ($fresh && $fresh->get_meta('_new_order_email_sent')) {
            $fresh->update_meta_data('_collar_admin_email_sent', 'yes');
            $fresh->add_order_note('📧 Correo "Nuevo pedido" enviado al admin (respaldo del configurador).');
            $fresh->save();
        }
    }

    public function maybe_send_admin_email_on_status($order_id, $old_status, $new_status, $order) {
        $this->send_admin_new_order_email_once($order ?: wc_get_order($order_id));
    }

    public function maybe_send_admin_email_on_thankyou($order_id) {
        if (!$order_id) return;
        $this->send_admin_new_order_email_once(wc_get_order($order_id));
    }

    /**
     * Vigilante horario: reintenta el correo de pedidos pagados recientes que sigan sin
     * notificación (p. ej. el webhook murió y el cliente nunca volvió a la página de
     * gracias). Solo considera pedidos creados después de su primera ejecución, para no
     * bombardear con pedidos históricos.
     */
    public function schedule_email_watchdog() {
        if (!wp_next_scheduled('collar_admin_email_watchdog')) {
            wp_schedule_event(time() + 300, 'hourly', 'collar_admin_email_watchdog');
        }
    }

    public function unschedule_email_watchdog() {
        wp_clear_scheduled_hook('collar_admin_email_watchdog');
    }

    public function run_email_watchdog() {
        if (!function_exists('wc_get_orders')) return;

        $since = (int) get_option('collar_email_watchdog_since', 0);
        if (!$since) {
            // Primera ejecución: solo fija el punto de partida.
            update_option('collar_email_watchdog_since', time(), false);
            return;
        }

        $orders = wc_get_orders(array(
            'status'       => array('processing', 'on-hold', 'completed'),
            'date_created' => '>' . max($since, time() - 7 * DAY_IN_SECONDS),
            'limit'        => 25,
        ));
        foreach ($orders as $order) {
            $this->send_admin_new_order_email_once($order);
        }
    }

    /**
     * Render personalizado en la página de gracias.
     * También marca el pedido como "thank-you alcanzado" para auditar conversión post-pago.
     */
    public function render_thankyou_collar_preview($order_id) {
        if (!$order_id) return;
        $order = wc_get_order($order_id);
        if (!$order) return;

        // Tracking: marcar timestamp de llegada (solo la primera vez)
        if (!$order->get_meta('_collar_thankyou_reached_at')) {
            $order->update_meta_data('_collar_thankyou_reached_at', current_time('mysql'));
            $order->add_order_note('✅ Cliente llegó a la página de gracias (post-pago).');
            $order->save();
        }

        $assets_url = plugin_dir_url(__FILE__) . 'assets/';
        $zone = $this->get_order_shipping_zone($order);
        $zone_labels = array(
            'local'    => array('label' => 'Bogotá, Chía y Cota', 'eta' => '4–5 días hábiles'),
            'nacional' => array('label' => 'Nivel Nacional',       'eta' => '4–5 días hábiles'),
            'recoger'  => array('label' => 'Pasar a recoger',      'eta' => 'Te avisamos cuando esté listo'),
        );
        $wa = $this->get_whatsapp_contact_data('Hola, acabo de hacer un pedido (#' . $order_id . ') y quisiera consultar algo.');

        // Reunir collares de la orden
        $collars = array();
        foreach ($order->get_items() as $item) {
            $json = $item->get_meta('_collar_charms_data');
            if (empty($json)) $json = $item->get_meta('Configuración de Charms');
            if (empty($json)) continue;
            $charms = json_decode(wp_unslash($json), true);
            if (!is_array($charms) || empty($charms)) continue;
            $collars[] = array(
                'name'   => $item->get_name(),
                'size'   => $item->get_meta('Tamaño') ?: $item->get_meta('Tamaño del Collar'),
                'color'  => $item->get_meta('Color')  ?: $item->get_meta('Color del Collar'),
                'charms' => $charms,
            );
        }

        ?>
        <div class="collar-thankyou" style="margin:30px 0;padding:24px;background:linear-gradient(135deg,#fdeef8 0%,#fef3c7 100%);border-radius:12px;border:1px solid #f9d6e9;">
            <div style="text-align:center;margin-bottom:24px;">
                <div style="font-size:48px;line-height:1;">🐾✨</div>
                <h2 style="margin:8px 0 4px;font-family:'Bebas Neue',sans-serif;font-size:32px;color:#c90278;letter-spacing:1px;">¡Gracias por tu compra!</h2>
                <p style="margin:0;color:#374151;font-size:15px;">Tu pedido <strong>#<?php echo esc_html($order_id); ?></strong> ya está en nuestras manos. Pronto vas a tener un collar único para tu peludo.</p>
            </div>

            <?php if (!empty($collars)): ?>
                <?php foreach ($collars as $c): ?>
                    <div style="background:#fff;border-radius:10px;padding:18px;margin-bottom:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                        <h3 style="margin:0 0 8px;font-size:16px;color:#111;"><?php echo esc_html($c['name']); ?></h3>
                        <p style="margin:0 0 14px;color:#666;font-size:13px;">
                            <strong>Tamaño:</strong> <?php echo esc_html(strtoupper($c['size'])); ?>
                            &nbsp;·&nbsp;
                            <strong>Color del collar:</strong> <?php echo esc_html($c['color']); ?>
                        </p>
                        <div style="display:flex;flex-wrap:wrap;gap:8px;">
                            <?php foreach ($c['charms'] as $i => $ch):
                                $val = isset($ch['value']) ? $ch['value'] : '?';
                                $em  = !empty($ch['isEmoji']);
                                $gl  = !empty($ch['isGlitter']);
                                $h   = isset($ch['colorHex']) ? $ch['colorHex'] : '#000';
                                if ($gl && $h === '#GOLD')      { $bg = '#FFD700'; $fg = '#8B4513'; }
                                elseif ($gl && $h === '#SILVER'){ $bg = '#C0C0C0'; $fg = '#333'; }
                                elseif ($em)                    { $bg = '#f0f0f0'; $fg = '#333'; }
                                else                            { $bg = $h; $fg = '#fff'; }
                            ?>
                                <div style="border:1px solid #ddd;border-radius:8px;padding:6px;min-width:54px;text-align:center;background:<?php echo esc_attr($bg); ?>;color:<?php echo $fg; ?>;">
                                    <?php if ($em):
                                        $folder = ltrim($h, '#');
                                        $url    = $assets_url . 'emojis/' . $folder . '/' . $val;
                                    ?>
                                        <img src="<?php echo esc_url($url); ?>" alt="" style="width:36px;height:36px;object-fit:contain;display:block;margin:0 auto;" />
                                    <?php else: ?>
                                        <div style="font-size:20px;font-weight:900;line-height:1.2;"><?php echo esc_html($val); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (isset($zone_labels[$zone])): ?>
                <div style="background:#fff;border-radius:10px;padding:14px 18px;margin-bottom:14px;display:flex;align-items:center;gap:12px;">
                    <div style="font-size:28px;">🚚</div>
                    <div>
                        <div style="font-weight:700;color:#111;">
                            <?php echo $zone === 'recoger' ? 'Pasar a recoger' : 'Envío'; ?>:
                            <?php echo esc_html($zone_labels[$zone]['label']); ?>
                        </div>
                        <div style="color:#6b7280;font-size:13px;"><?php echo esc_html($zone_labels[$zone]['eta']); ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <div style="background:#fff;border-radius:10px;padding:14px 18px;display:flex;flex-direction:column;gap:8px;">
                <div style="font-weight:700;color:#111;">¿Tienes alguna duda o quieres coordinar la entrega?</div>
                <a href="<?php echo esc_url($wa['url']); ?>" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:8px;align-self:flex-start;background:#25D366;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none;font-weight:600;">
                    💬 Escríbenos por WhatsApp
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Render visual de charms + método de envío en correos de pedido (admin y cliente).
     * Se inyecta justo después de la tabla del pedido.
     */
    public function render_collar_visual_in_email($order, $sent_to_admin, $plain_text, $email) {
        if ($plain_text || !$order) return;

        $assets_url = plugin_dir_url(__FILE__) . 'assets/';
        $blocks = '';

        foreach ($order->get_items() as $item) {
            $json = $item->get_meta('_collar_charms_data');
            if (empty($json)) $json = $item->get_meta('Configuración de Charms');
            if (empty($json)) continue;

            $charms = json_decode(wp_unslash($json), true);
            if (!is_array($charms) || empty($charms)) continue;

            $size  = $item->get_meta('Tamaño') ?: $item->get_meta('Tamaño del Collar');
            $color = $item->get_meta('Color')  ?: $item->get_meta('Color del Collar');

            $cards = '';
            foreach ($charms as $i => $c) {
                $value     = isset($c['value']) ? $c['value'] : '?';
                $isEmoji   = !empty($c['isEmoji']);
                $isGlitter = !empty($c['isGlitter']);
                $cHex      = isset($c['colorHex']) ? $c['colorHex'] : '#000';
                $label     = 'Letra';
                $bg        = $cHex;
                $textColor = '#fff';

                if ($isGlitter && $cHex === '#GOLD') {
                    $label = 'Dorado'; $bg = '#FFD700'; $textColor = '#8B4513';
                } elseif ($isGlitter && $cHex === '#SILVER') {
                    $label = 'Plateado'; $bg = '#C0C0C0'; $textColor = '#333';
                } elseif ($isEmoji) {
                    $label = 'Emoji'; $bg = '#f0f0f0'; $textColor = '#333';
                }

                $inner = '';
                if ($isEmoji) {
                    $folder = ltrim($cHex, '#');
                    $url    = $assets_url . 'emojis/' . $folder . '/' . $value;
                    $inner  = '<img src="' . esc_url($url) . '" alt="" width="40" height="40" style="display:block;margin:0 auto;width:40px;height:40px;object-fit:contain;" />';
                } else {
                    $inner = '<div style="font-size:22px;font-weight:900;line-height:1;">' . esc_html($value) . '</div>';
                }

                $cards .= '<td style="padding:6px;">'
                       .  '<div style="border:1px solid #ddd;border-radius:6px;padding:8px;min-width:60px;text-align:center;background:' . esc_attr($bg) . ';color:' . $textColor . ';">'
                       .  '<div style="font-size:10px;opacity:.7;margin-bottom:4px;">#' . ($i + 1) . '</div>'
                       .  $inner
                       .  '<div style="font-size:10px;margin-top:4px;">' . esc_html($label) . '</div>'
                       .  '</div></td>';
            }

            $blocks .= '<div style="margin:24px 0;padding:16px;background:#fafafa;border:1px solid #e5e5e5;border-radius:6px;">'
                    .  '<h3 style="margin:0 0 12px;font-size:16px;color:#111;">Diseño del collar — ' . esc_html($item->get_name()) . '</h3>'
                    .  '<p style="margin:0 0 10px;color:#555;font-size:14px;"><strong>Tamaño:</strong> ' . esc_html($size) . ' · <strong>Color del collar:</strong> ' . esc_html($color) . '</p>'
                    .  '<table cellspacing="0" cellpadding="0" border="0"><tr>' . $cards . '</tr></table>'
                    .  '</div>';
        }

        // Método de envío
        $zone = $this->get_order_shipping_zone($order);
        if ($zone) {
            $zone_labels = array(
                'local'    => 'Envío Deprisa · Bogotá, Chía y Cota ($8.900)',
                'nacional' => 'Envío Deprisa · Nivel Nacional ($13.900)',
                'recoger'  => 'Pasar a recoger (Gratis)',
            );
            if (isset($zone_labels[$zone])) {
                $blocks .= '<div style="margin:16px 0;padding:12px 16px;background:#eef2ff;border-left:4px solid #6366f1;border-radius:4px;">'
                        .  '<strong style="color:#111;">Método de envío:</strong> ' . esc_html($zone_labels[$zone])
                        .  '</div>';
            }
        }

        if ($blocks !== '') {
            echo $blocks;
        }
    }

    /**
     * Marcar metas internos como ocultos para que no aparezcan en vistas de pedidos.
     */
    public function hide_internal_order_meta($hidden) {
        $hidden[] = '_collar_charms_data';
        $hidden[] = 'Configuración de Charms'; // clave legacy de pedidos antiguos
        return $hidden;
    }

    /**
     * Si el item tiene JSON de charms pero no tiene meta "Diseño",
     * lo agregamos al vuelo a la lista formateada para que se vea en pedidos viejos.
     */
    public function inject_design_meta($formatted_meta, $item) {
        // ¿Ya tiene "Diseño"? Nada que hacer.
        foreach ($formatted_meta as $meta) {
            if (isset($meta->key) && $meta->key === 'Diseño') return $formatted_meta;
        }

        $json = $item->get_meta('_collar_charms_data');
        if (empty($json)) {
            $json = $item->get_meta('Configuración de Charms');
        }
        if (empty($json)) return $formatted_meta;

        $summary = $this->build_charms_summary($json);
        if ($summary === '') return $formatted_meta;

        $design = (object) array(
            'key'           => 'Diseño',
            'value'         => $summary,
            'display_key'   => 'Diseño',
            'display_value' => $summary,
        );
        $formatted_meta[] = $design;
        return $formatted_meta;
    }
    
    public function init() {
        // Registro de funciones necesarias
    }

    /**
     * Zona de envío del pedido. Lee el meta de la orden (compatible con HPOS) y cae al
     * postmeta legacy para pedidos creados cuando se guardaba con update_post_meta
     * (con HPOS activo ese dato quedaba invisible para $order->get_meta()).
     */
    private function get_order_shipping_zone($order) {
        if (!$order) return '';
        $zone = $order->get_meta('_collar_shipping_zone');
        if (!$zone) {
            $zone = get_post_meta($order->get_id(), '_collar_shipping_zone', true);
        }
        return $zone;
    }

    /**
     * Obtener datos del WhatsApp de contacto para links y render.
     */
    private function get_whatsapp_contact_data($message = '') {
        $default_display = '+57 301 527 4105';
        $raw_number = trim((string) get_option('collar_whatsapp_number', $default_display));
        $digits = preg_replace('/\D+/', '', $raw_number);

        if (empty($digits)) {
            $digits = '573015274105';
        }

        $display = $raw_number !== '' ? $raw_number : $default_display;
        $url = 'https://api.whatsapp.com/send?phone=' . $digits;

        if ($message !== '') {
            $url .= '&text=' . rawurlencode($message);
        }

        return array(
            'display' => $display,
            'digits'  => $digits,
            'url'     => $url,
        );
    }

    /**
     * Botón flotante global de WhatsApp para contacto rápido.
     */
    public function render_whatsapp_floating_button() {
        if (is_admin()) return;

        $wa = $this->get_whatsapp_contact_data('Hola, quiero información sobre los collares personalizados.');
        $wa_icon_file = 'whatsapp-logo-whatsapp-icon-whatsapp-transparent-free-png.webp';
        $wa_icon_path = plugin_dir_path(__FILE__) . $wa_icon_file;
        $wa_icon_url  = plugin_dir_url(__FILE__) . $wa_icon_file;
        ?>
        <style id="collar-whatsapp-float-style">
            .collar-whatsapp-float {
                position: fixed;
                right: max(16px, env(safe-area-inset-right));
                bottom: max(16px, env(safe-area-inset-bottom));
                z-index: 99999;
                width: 62px;
                height: 62px;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 0;
                border-radius: 50%;
                background: #25D366;
                color: #ffffff;
                text-decoration: none;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                box-shadow: 0 10px 28px rgba(18, 140, 74, 0.35);
                transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
                overflow: hidden;
            }

            .collar-whatsapp-float:hover {
                background: #1ebe5d;
                transform: translateY(-2px);
                box-shadow: 0 14px 32px rgba(18, 140, 74, 0.45);
                color: #ffffff;
                text-decoration: none;
            }

            .collar-whatsapp-float:focus-visible {
                outline: 3px solid #166534;
                outline-offset: 3px;
            }

            .collar-whatsapp-float .wa-icon {
                width: 38px;
                height: 38px;
                display: block;
                flex-shrink: 0;
                color: #ffffff;
            }

            .collar-whatsapp-float img.wa-icon {
                object-fit: contain;
                filter: brightness(0) saturate(100%) invert(100%);
                transform: scale(1.95);
                transform-origin: center center;
            }

            .collar-whatsapp-float svg.wa-icon {
                width: 36px;
                height: 36px;
            }

            @media (max-width: 768px) {
                .collar-whatsapp-float {
                    width: 56px;
                    height: 56px;
                }

                .collar-whatsapp-float .wa-icon {
                    width: 34px;
                    height: 34px;
                }

                .collar-whatsapp-float img.wa-icon {
                    transform: scale(1.85);
                }

                .collar-whatsapp-float svg.wa-icon {
                    width: 32px;
                    height: 32px;
                }
            }
        </style>

        <a class="collar-whatsapp-float"
           href="<?php echo esc_url($wa['url']); ?>"
           target="_blank"
           rel="noopener noreferrer nofollow"
           aria-label="Escríbenos por WhatsApp">
            <?php if (file_exists($wa_icon_path)): ?>
                <img class="wa-icon" src="<?php echo esc_url($wa_icon_url); ?>" alt="" loading="lazy" decoding="async" />
            <?php else: ?>
            <svg class="wa-icon" viewBox="0 0 32 32" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M16.004 0C7.168 0 0 7.163 0 15.998c0 2.821.735 5.575 2.134 7.994L0 32l8.209-2.122a15.944 15.944 0 0 0 7.795 2.004h.006C24.846 31.882 32 24.719 32 15.882 32 7.046 24.84 0 16.004 0zm9.34 22.629c-.386 1.087-2.26 2.063-3.108 2.242-.847.18-1.92.256-6.196-1.417-5.467-2.138-8.992-7.54-9.267-7.908-.274-.367-2.22-2.947-2.22-5.62 0-2.672 1.4-3.985 1.894-4.531.494-.547 1.078-.684 1.438-.684.359 0 .719.003 1.033.018.333.017.779-.126 1.218.928.45 1.078 1.53 3.726 1.664 3.996.134.269.224.584.044.944-.18.36-.269.584-.539.899-.269.315-.566.705-.808.95-.269.27-.55.561-.236 1.099.315.539 1.4 2.306 3.003 3.737 2.06 1.837 3.797 2.405 4.336 2.674.539.269.853.224 1.167-.135.315-.36 1.347-1.571 1.706-2.11.359-.539.719-.449 1.212-.269.494.18 3.128 1.475 3.667 1.744.539.27.898.404 1.033.629.135.225.135 1.313-.25 2.4z"></path>
            </svg>
            <?php endif; ?>
        </a>
        <?php
    }

    /**
     * Redirigir páginas de producto al configurador o página personalizada.
     * Cubre tanto el collar personalizable como el Collar Gummy.
     */
    public function redirect_product_to_configurator() {
        if ( ! is_singular( 'product' ) ) return;

        $current_id = get_the_ID();

        // Collar personalizable → configurador
        $collar_product_id = (int) get_option( 'collar_product_id', 0 );
        if ( $collar_product_id && $current_id === $collar_product_id ) {
            $redirect_url = get_option( 'collar_configurator_page_url', '' );
            if ( ! empty( $redirect_url ) ) {
                wp_redirect( esc_url_raw( $redirect_url ), 301 );
                exit;
            }
        }

        // Collar Gummy → página del Gummy
        $gummy_product_id = (int) get_option( 'gummy_product_id', 0 );
        if ( $gummy_product_id && $current_id === $gummy_product_id ) {
            $redirect_url = get_option( 'gummy_page_url', '' );
            if ( ! empty( $redirect_url ) ) {
                wp_redirect( esc_url_raw( $redirect_url ), 301 );
                exit;
            }
        }
    }

    /**
     * Mostrar precio descriptivo en el listing en lugar de $0.
     */
    public function collar_price_html( $price, $product ) {
        $collar_product_id = (int) get_option( 'collar_product_id', 0 );
        if ( $collar_product_id && $product->get_id() === $collar_product_id ) {
            return '<span class="woocommerce-Price-amount amount">Desde $69.800 COP</span>';
        }
        return $price;
    }

    /**
     * Reemplazar el botón "Añadir al carrito" del listing por un enlace al configurador.
     */
    public function collar_loop_button( $html, $product, $args ) {
        $collar_product_id = (int) get_option( 'collar_product_id', 0 );
        if ( $collar_product_id && $product->get_id() === $collar_product_id ) {
            $url = get_option( 'collar_configurator_page_url', get_permalink( $product->get_id() ) );
            return '<a href="' . esc_url( $url ) . '" class="button">Personalizar collar</a>';
        }
        return $html;
    }

    /**
     * Bloquear add-to-cart directo del collar (sin pasar por el configurador).
     * Nuestro handler AJAX envía 'collar_size'; si no está presente es una compra directa.
     */
    public function block_direct_add_to_cart( $valid, $product_id ) {
        $collar_product_id = (int) get_option( 'collar_product_id', 0 );
        if ( $collar_product_id && (int) $product_id === $collar_product_id && empty( $_POST['collar_size'] ) ) {
            $url = get_option( 'collar_configurator_page_url', '' );
            $link = $url ? ' <a href="' . esc_url( $url ) . '">Personalízalo aquí</a>.' : '.';
            wc_add_notice( 'Para comprar este collar primero debes personalizarlo' . $link, 'error' );
            return false;
        }
        return $valid;
    }

    /**
     * Permite que el producto del collar se pueda comprar aunque su precio base
     * esté vacío en WooCommerce. El precio real se aplica en update_cart_item_price().
     */
    public function make_collar_purchasable( $purchasable, $product ) {
        $collar_product_id = (int) get_option( 'collar_product_id', 0 );
        if ( $collar_product_id && $product->get_id() === $collar_product_id ) {
            return $product->exists() && 'publish' === $product->get_status();
        }
        return $purchasable;
    }
    
    public function enqueue_scripts() {
        // Solo cargar en la página de charms
        if (is_page('charms') || is_page_template('page-charms.php')) {
            wp_enqueue_script('jquery');
        }
    }

    /**
     * Selector de envío personalizado mostrado después del formulario de cliente.
     * El cliente elige explícitamente su opción; no hay detección automática.
     */
    public function render_shipping_selector() {
        if (!is_checkout()) return;
        $sess       = WC()->session;
        $zone       = $sess ? $sess->get('collar_shipping_zone', '') : '';
        $s_address  = $sess ? esc_attr($sess->get('collar_shipping_address', '')) : '';
        $s_city     = $sess ? esc_attr($sess->get('collar_shipping_city', ''))    : '';
        $s_state    = $sess ? esc_attr($sess->get('collar_shipping_state', ''))   : '';
        $s_barrio   = $sess ? esc_attr($sess->get('collar_shipping_barrio', ''))  : '';
        $show_addr  = in_array($zone, array('local', 'nacional'), true);

        $options = array(
            'local'    => array('label' => 'Bogotá, Chía y Cota',  'price' => '$8.900',  'note' => '4–5 días hábiles', 'color' => '#374151'),
            'nacional' => array('label' => 'Resto del país',        'price' => '$13.900', 'note' => '4–5 días hábiles', 'color' => '#374151'),
            'recoger'  => array('label' => 'Pasar a recoger',       'price' => 'Gratis',  'note' => '',                 'color' => '#16a34a'),
        );
        $wa = $this->get_whatsapp_contact_data('Hola, quiero coordinar la recogida de mi pedido.');

        $field_style = 'width:100%;box-sizing:border-box;padding:10px 14px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;color:#374151;';
        $label_style = 'display:block;font-size:14px;font-weight:500;color:#374151;margin-bottom:4px;';
        ?>
        <div id="collar-shipping-selector" style="margin:24px 0 0;">
            <h3 style="font-size:16px;font-weight:600;color:#111827;margin-bottom:12px;">Método de envío</h3>
            <ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:8px;">
            <?php foreach ($options as $value => $opt): $sel = ($zone === $value); ?>
                <li>
                    <label style="display:flex;align-items:center;gap:12px;cursor:pointer;padding:12px 16px;border:2px solid <?php echo $sel ? '#374151' : '#e5e7eb'; ?>;border-radius:8px;background:#fff;transition:border-color .15s;">
                        <input type="radio" name="collar_shipping_zone" value="<?php echo esc_attr($value); ?>" <?php checked($sel); ?> style="margin:0;accent-color:#374151;">
                        <span style="flex:1;font-weight:500;color:#374151;"><?php echo esc_html($opt['label']); ?></span>
                        <?php if ($opt['note']): ?>
                            <small style="color:#9ca3af;"><?php echo esc_html($opt['note']); ?></small>
                        <?php endif; ?>
                        <strong style="color:<?php echo $opt['color']; ?>;"><?php echo esc_html($opt['price']); ?></strong>
                    </label>
                </li>
            <?php endforeach; ?>
            </ul>

            <!-- Campos de dirección de envío (visible cuando no es "recoger") -->
            <div id="collar-delivery-address" style="<?php echo $show_addr ? '' : 'display:none;'; ?>margin-top:16px;">
                <div style="margin-bottom:12px;">
                    <label for="collar_shipping_address" style="<?php echo $label_style; ?>">
                        Dirección de envío <abbr title="requerido" style="color:#ef4444;text-decoration:none;">*</abbr>
                    </label>
                    <input type="text" id="collar_shipping_address" name="collar_shipping_address"
                           value="<?php echo $s_address; ?>"
                           placeholder="Ej: Calle 123 # 45-67, Apto 8..."
                           style="<?php echo $field_style; ?>">
                </div>
                <div id="collar-city-state-fields" style="<?php echo ($zone === 'nacional') ? 'display:grid;' : 'display:none;'; ?>grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                    <div>
                        <label for="collar_shipping_city" style="<?php echo $label_style; ?>">
                            Ciudad <abbr title="requerido" style="color:#ef4444;text-decoration:none;">*</abbr>
                        </label>
                        <input type="text" id="collar_shipping_city" name="collar_shipping_city"
                               value="<?php echo $s_city; ?>"
                               placeholder="Ej: Medellín"
                               style="<?php echo $field_style; ?>">
                    </div>
                    <div>
                        <label for="collar_shipping_state" style="<?php echo $label_style; ?>">
                            Departamento <abbr title="requerido" style="color:#ef4444;text-decoration:none;">*</abbr>
                        </label>
                        <input type="text" id="collar_shipping_state" name="collar_shipping_state"
                               value="<?php echo $s_state; ?>"
                               placeholder="Ej: Antioquia"
                               style="<?php echo $field_style; ?>">
                    </div>
                </div>
                <div>
                    <label for="collar_shipping_barrio" style="<?php echo $label_style; ?>">
                        Barrio / Referencia adicional
                    </label>
                    <input type="text" id="collar_shipping_barrio" name="collar_shipping_barrio"
                           value="<?php echo $s_barrio; ?>"
                           placeholder="Barrio, referencia del lugar, indicaciones..."
                           style="<?php echo $field_style; ?>">
                </div>
            </div>

            <!-- Info punto de recogida (visible solo cuando se elige "recoger") -->
            <div id="collar-pickup-info" style="<?php echo ($zone === 'recoger') ? '' : 'display:none;'; ?>margin-top:10px;padding:12px 16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;font-size:14px;color:#166534;">
                Comunícate al número de WhatsApp <strong><a href="<?php echo esc_url($wa['url']); ?>" target="_blank" rel="noopener noreferrer" style="color:#166534;"><?php echo esc_html($wa['display']); ?></a></strong> para concretar la recogida de tu producto cuando esté listo.
            </div>
        </div>
        <?php
    }

    /**
     * Guardar la zona elegida por el cliente en la sesión de WooCommerce.
     * Se ejecuta en cada recálculo del checkout (update_checkout AJAX).
     */
    public function save_shipping_zone_to_session($posted_data) {
        parse_str($posted_data, $fields);
        if (!empty($fields['collar_shipping_zone'])) {
            $zone = sanitize_text_field($fields['collar_shipping_zone']);
            if (in_array($zone, array('local', 'nacional', 'recoger'), true)) {
                WC()->session->set('collar_shipping_zone', $zone);
            }
        }
        // Guardar campos de dirección en sesión para pre-llenar el formulario
        foreach (array('collar_shipping_address', 'collar_shipping_city', 'collar_shipping_state', 'collar_shipping_barrio') as $field) {
            if (isset($fields[$field])) {
                WC()->session->set($field, sanitize_text_field($fields[$field]));
            }
        }
    }

    /**
     * Mejorar campos del checkout: nombre/apellido obligatorios, celular con placeholder
     * y un campo de confirmación de celular justo después del original.
     */
    public function enhance_checkout_fields($fields) {
        if (isset($fields['billing']['billing_first_name'])) {
            $fields['billing']['billing_first_name']['required'] = true;
        }
        if (isset($fields['billing']['billing_last_name'])) {
            $fields['billing']['billing_last_name']['required'] = true;
        }

        if (isset($fields['billing']['billing_phone'])) {
            $fields['billing']['billing_phone']['label']       = 'Celular';
            $fields['billing']['billing_phone']['placeholder'] = 'Ej: 3001234567';
            $fields['billing']['billing_phone']['required']    = true;

            $phone_priority = isset($fields['billing']['billing_phone']['priority'])
                ? (int) $fields['billing']['billing_phone']['priority']
                : 100;

            // Campo de confirmación justo después del celular.
            $fields['billing']['billing_phone_confirm'] = array(
                'label'       => 'Confirma tu celular',
                'placeholder' => 'Vuelve a escribir tu celular',
                'required'    => true,
                'type'        => 'tel',
                'priority'    => $phone_priority + 1,
                'class'       => array('form-row-wide'),
            );
        }

        return $fields;
    }

    /**
     * Validar el celular en el checkout: formato colombiano (10 dígitos, empieza por 3)
     * y que coincida con el campo de confirmación. Evita envíos fallidos por datos erróneos.
     */
    public function validate_billing_phone() {
        $phone   = isset($_POST['billing_phone']) ? trim(wp_unslash($_POST['billing_phone'])) : '';
        $confirm = isset($_POST['billing_phone_confirm']) ? trim(wp_unslash($_POST['billing_phone_confirm'])) : '';

        $phone_digits   = preg_replace('/\D+/', '', $phone);
        $confirm_digits = preg_replace('/\D+/', '', $confirm);

        // Formato celular colombiano: exactamente 10 dígitos y empieza por 3.
        if ($phone_digits !== '' && (strlen($phone_digits) !== 10 || $phone_digits[0] !== '3')) {
            wc_add_notice('El número de celular debe tener 10 dígitos y empezar por 3 (ej: 3001234567).', 'error');
        }

        // Confirmación de celular.
        if ($phone_digits !== '') {
            if ($confirm_digits === '') {
                wc_add_notice('Por favor confirma tu número de celular.', 'error');
            } elseif ($phone_digits !== $confirm_digits) {
                wc_add_notice('Los números de celular no coinciden. Verifícalos por favor.', 'error');
            }
        }
    }

    /**
     * Validar que los campos de dirección estén completos antes de procesar el pago.
     */
    public function validate_shipping_address() {
        $zone = WC()->session ? WC()->session->get('collar_shipping_zone', '') : '';
        if (empty($zone)) {
            wc_add_notice('Por favor selecciona un método de envío.', 'error');
            return;
        }
        if (in_array($zone, array('local', 'nacional'), true)) {
            if (empty(trim($_POST['collar_shipping_address'] ?? ''))) {
                wc_add_notice('La dirección de envío es obligatoria.', 'error');
            }
            if ($zone === 'nacional') {
                if (empty(trim($_POST['collar_shipping_city'] ?? ''))) {
                    wc_add_notice('La ciudad de envío es obligatoria.', 'error');
                }
                if (empty(trim($_POST['collar_shipping_state'] ?? ''))) {
                    wc_add_notice('El departamento de envío es obligatorio.', 'error');
                }
            }
        }
    }

    /**
     * Guardar la dirección de envío personalizada en la orden de WooCommerce.
     */
    public function save_shipping_address_to_order($order_id, $data) {
        $zone  = WC()->session ? WC()->session->get('collar_shipping_zone', '') : '';
        $order = wc_get_order($order_id);
        if (!$order) return;

        if (!empty($zone)) {
            // Meta de la orden, no update_post_meta: con HPOS activo el postmeta queda
            // invisible para $order->get_meta() (página de gracias, correos, panel).
            $order->update_meta_data('_collar_shipping_zone', $zone);
        }

        if (in_array($zone, array('local', 'nacional'), true)) {
            $address = sanitize_text_field($_POST['collar_shipping_address'] ?? '');
            $city    = sanitize_text_field($_POST['collar_shipping_city']    ?? '');
            $state   = sanitize_text_field($_POST['collar_shipping_state']   ?? '');
            $barrio  = sanitize_text_field($_POST['collar_shipping_barrio']  ?? '');

            $order->set_shipping_address_1($address);
            $order->set_shipping_address_2($barrio);
            $order->set_shipping_city($city);
            $order->set_shipping_state($state);
            $order->set_shipping_country('CO');
        }

        $order->save();
    }

    /**
     * Agregar el costo de envío como fee según la opción elegida por el cliente.
     * Funciona aunque el producto sea virtual (woocommerce_package_rates no aplica).
     */
    public function add_shipping_fee() {
        if (is_admin() && !defined('DOING_AJAX')) return;
        if (!WC()->session) return;

        $zone = WC()->session->get('collar_shipping_zone', '');
        if (empty($zone)) return;

        $options = array(
            'local'    => array('label' => 'Envío Deprisa · Bogotá, Chía y Cota', 'cost' => 8900),
            'nacional' => array('label' => 'Envío Deprisa · Nivel Nacional',       'cost' => 13900),
            'recoger'  => array('label' => 'Pasar a recoger',                       'cost' => 0),
        );

        if (!isset($options[$zone]) || $options[$zone]['cost'] === 0) return;

        WC()->cart->add_fee($options[$zone]['label'], $options[$zone]['cost'], false);
    }

    /**
     * JS del checkout: resaltar opción activa, mostrar/ocultar campos de dirección
     * y recalcular totales al cambiar selección.
     */
    public function enqueue_checkout_shipping_js() {
        if (!is_checkout()) return;
        ?>
        <script>
        jQuery(function($) {
            function highlightSelected() {
                $('#collar-shipping-selector label').css('border-color', '#e5e7eb');
                $('#collar-shipping-selector input[type=radio]:checked').closest('label').css('border-color', '#374151');
            }

            function updateSections() {
                var zone = $('input[name="collar_shipping_zone"]:checked').val();
                if (zone === 'recoger') {
                    $('#collar-delivery-address').hide();
                    $('#collar-pickup-info').show();
                } else if (zone === 'local' || zone === 'nacional') {
                    $('#collar-delivery-address').show();
                    $('#collar-pickup-info').hide();
                    if (zone === 'nacional') {
                        $('#collar-city-state-fields').css({display: 'grid'});
                    } else {
                        $('#collar-city-state-fields').hide();
                    }
                } else {
                    $('#collar-delivery-address').hide();
                    $('#collar-pickup-info').hide();
                }
            }

            $(document).on('change', 'input[name="collar_shipping_zone"]', function() {
                highlightSelected();
                updateSections();
                $(document.body).trigger('update_checkout');
            });

            $(document.body).on('updated_checkout', function() {
                highlightSelected();
                updateSections();
            });

            highlightSelected();
            updateSections();
        });
        </script>
        <?php
    }

    /**
     * Handler AJAX para añadir collar personalizado al carrito
     */
    public function ajax_add_to_cart() {
        check_ajax_referer('collar_config_nonce', 'nonce');

        $product_id   = intval($_POST['product_id']);
        $collar_size  = strtoupper(sanitize_text_field($_POST['collar_size']));
        $collar_color = sanitize_text_field($_POST['collar_color']);
        $charms_count = intval($_POST['charms_count']);
        $charms_data  = sanitize_textarea_field($_POST['charms_data']);

        if (!function_exists('WC') || !$product_id || !wc_get_product($product_id)) {
            wp_send_json_error(array('message' => 'Producto no encontrado'));
            return;
        }

        // Solo se puede configurar EL collar. Sin esta comprobación, el precio del collar
        // (calculado server-side) se impondría a cualquier producto que el cliente indique
        // en product_id, permitiendo subpagar productos caros o inyectar productos ajenos.
        $collar_product_id = (int) get_option('collar_product_id', 0);
        if (!$collar_product_id || $product_id !== $collar_product_id) {
            wp_send_json_error(array('message' => 'Producto no válido para el configurador.'));
            return;
        }

        if (!in_array($collar_size, self::ALLOWED_SIZES, true)) {
            wp_send_json_error(array('message' => 'Tamaño no válido.'));
            return;
        }

        // XS tiene stock limitado de colores de collar (anti-manipulación).
        if ($collar_size === 'XS' && !in_array($collar_color, self::XS_ALLOWED_COLORS, true)) {
            wp_send_json_error(array('message' => 'Ese color no está disponible en talla XS. Colores disponibles: Rosa, Rojo y Azul.'));
            return;
        }

        if ($charms_count < self::MIN_CHARMS) {
            wp_send_json_error(array('message' => 'Debes seleccionar al menos ' . self::MIN_CHARMS . ' charms.'));
            return;
        }

        $max_for_size = $this->max_charms_for_size($collar_size);
        if ($charms_count > $max_for_size) {
            wp_send_json_error(array('message' => 'Máximo ' . $max_for_size . ' charms permitidos para talla ' . $collar_size . '.'));
            return;
        }

        // Calcular precio SERVER-SIDE. El valor enviado por el cliente se ignora.
        $custom_price = $this->calculate_collar_price($collar_size, $charms_count);
        if ($custom_price <= 0) {
            wp_send_json_error(array('message' => 'No se pudo calcular el precio. Revisa la configuración.'));
            return;
        }

        // Inicializar sesión y carrito para usuarios no logueados
        if (WC()->session && !WC()->session->has_session()) {
            WC()->session->set_customer_session_cookie(true);
        }
        if (WC()->cart && did_action('woocommerce_load_cart_from_session') === 0) {
            WC()->cart->get_cart_from_session();
        }

        try {
            $cart_item_data = array(
                'collar_configuration' => array(
                    'size'         => $collar_size,
                    'color'        => $collar_color,
                    'charms_count' => $charms_count,
                    'charms_data'  => $charms_data,
                    'custom_price' => $custom_price,
                    'configured_at'=> current_time('mysql'),
                ),
                'unique_key' => md5(microtime() . rand()),
            );

            $cart_item_key = WC()->cart->add_to_cart($product_id, 1, 0, array(), $cart_item_data);

            if ($cart_item_key) {
                wp_send_json_success(array(
                    'message'    => '¡Añadido al carrito!',
                    'cart_url'   => wc_get_cart_url(),
                    'cart_count' => WC()->cart->get_cart_contents_count(),
                ));
            } else {
                $wc_notices = wc_get_notices('error');
                $error_msg  = 'No se pudo añadir al carrito.';
                if (!empty($wc_notices)) {
                    $error_msg = wp_strip_all_tags($wc_notices[0]['notice']);
                    wc_clear_notices();
                }
                wp_send_json_error(array('message' => $error_msg));
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
        }
    }
    
    /**
     * Pass-through: la configuración del collar se construye SERVER-SIDE en
     * ajax_add_to_cart() y se inyecta directamente vía WC()->cart->add_to_cart().
     * NO leer $_POST['collar_configuration'] aquí: un atacante podría enviar
     * 'collar_configuration[custom_price]=1' y sobrescribir el precio validado.
     */
    public function add_cart_item_data($cart_item_data, $product_id, $variation_id) {
        return $cart_item_data;
    }
    
    /**
     * Mostrar datos personalizados en el carrito
     */
    public function display_cart_item_data($item_data, $cart_item) {
        if (isset($cart_item['collar_configuration'])) {
            $config = $cart_item['collar_configuration'];

            $item_data[] = array(
                'key'   => 'Tamaño',
                'value' => strtoupper($config['size']),
            );

            $item_data[] = array(
                'key'   => 'Color del collar',
                'value' => $config['color'],
            );

            $item_data[] = array(
                'key'   => 'Charms',
                'value' => $config['charms_count'],
            );

            if (!empty($config['charms_data'])) {
                $summary = $this->build_charms_summary($config['charms_data']);
                if ($summary !== '') {
                    $item_data[] = array(
                        'key'   => 'Diseño',
                        'value' => $summary,
                    );
                }
            }
        }

        if (isset($cart_item['gummy_size'])) {
            $item_data[] = array(
                'key'   => 'Talla',
                'value' => $cart_item['gummy_size'],
            );
        }
        if (isset($cart_item['gummy_bundle_price']) && $cart_item['gummy_bundle_price'] > 0) {
            $item_data[] = array(
                'key'   => 'Descuento',
                'value' => 'Bundle -10%',
            );
        }

        return $item_data;
    }
    
    /**
     * Mapeo de nombre del color del collar a hex para swatches en el admin.
     */
    private function collar_color_hex($name) {
        $map = array(
            'Negro'      => '#111111',
            'Blanco'     => '#FFFFFF',
            'Rosa'       => '#EC4899',
            'Amarillo'   => '#F59E0B',
            'Rojo'       => '#DC2626',
            'Azul'       => '#2563EB',
            'Verde'      => '#16A34A',
            'Morado'     => '#9333EA',
            'Naranja'    => '#FB923C',
            'Celeste'    => '#38BDF8',
            'Plateado'   => '#C0C0C0',
            'Dorado'     => '#D4AF37',
        );
        $key = trim((string) $name);
        return isset($map[$key]) ? $map[$key] : '';
    }

    /**
     * URL absoluta de WhatsApp click-to-chat a partir de un teléfono crudo.
     */
    private function whatsapp_url_from_phone($phone) {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (strlen($digits) < 7) return '';
        // Si no empieza por código de país, asume Colombia (+57).
        if (strlen($digits) === 10) $digits = '57' . $digits;
        return 'https://wa.me/' . $digits;
    }

    /**
     * Construir resumen legible de charms para mostrar al cliente.
     * Ej: "K · E · N · Y · A · 1 emoji"
     */
    private function build_charms_summary($charms_json) {
        $charms = json_decode(wp_unslash($charms_json), true);
        if (!is_array($charms) || empty($charms)) return '';

        $labels = array();
        $emoji_count = 0;
        foreach ($charms as $c) {
            if (!empty($c['isEmoji'])) {
                $emoji_count++;
            } elseif (isset($c['value'])) {
                $labels[] = $c['value'];
            }
        }
        if ($emoji_count > 0) {
            $labels[] = $emoji_count . ($emoji_count === 1 ? ' emoji' : ' emojis');
        }
        return implode(' · ', $labels);
    }

    /**
     * Guardar datos personalizados en la orden
     */
    public function save_order_item_data($item, $cart_item_key, $values, $order) {
        if (isset($values['collar_configuration'])) {
            $config = $values['collar_configuration'];

            $item->add_meta_data('Tamaño', strtoupper($config['size']), true);
            $item->add_meta_data('Color del collar', $config['color'], true);
            $item->add_meta_data('Charms', $config['charms_count'], true);

            if (!empty($config['charms_data'])) {
                $summary = $this->build_charms_summary($config['charms_data']);
                if ($summary !== '') {
                    $item->add_meta_data('Diseño', $summary, true);
                }
                // JSON crudo se guarda como meta oculto (prefijo _) para uso interno/admin.
                $item->add_meta_data('_collar_charms_data', $config['charms_data'], true);
            }
        }

        if (isset($values['gummy_size'])) {
            $item->add_meta_data('Talla Collar Gummy', $values['gummy_size'], true);
        }
        if (isset($values['gummy_bundle_price']) && $values['gummy_bundle_price'] > 0) {
            $item->add_meta_data('Bundle -10%', 'Sí', true);
        }
    }
    
    /**
     * Actualizar el precio del producto en el carrito según configuración
     */
    public function update_cart_item_price($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        // Defensa en profundidad: solo se repreciar los productos esperados. Aunque los
        // handlers AJAX ya validan el product_id, aquí volvemos a exigir que el ítem sea
        // realmente el collar/gummy configurado antes de sobreescribir su precio.
        $collar_product_id = (int) get_option('collar_product_id', 0);
        $gummy_product_id  = (int) get_option('gummy_product_id', 0);

        // Determinar si hay algún collar personalizado en el carrito
        $has_collar_in_cart = false;
        foreach ($cart->get_cart() as $item) {
            if (isset($item['collar_configuration']) && $collar_product_id && (int) $item['product_id'] === $collar_product_id) {
                $has_collar_in_cart = true;
                break;
            }
        }

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $item_product_id = (int) $cart_item['product_id'];

            if (isset($cart_item['collar_configuration']['custom_price'])
                && $collar_product_id && $item_product_id === $collar_product_id) {
                $custom_price = floatval($cart_item['collar_configuration']['custom_price']);
                if ($custom_price > 0) {
                    $cart_item['data']->set_price($custom_price);
                }
            }
            if (isset($cart_item['gummy_bundle_price']) && $cart_item['gummy_bundle_price'] > 0
                && $gummy_product_id && $item_product_id === $gummy_product_id) {
                if ($has_collar_in_cart) {
                    // Collar presente: aplicar precio bundle
                    $cart_item['data']->set_price(floatval($cart_item['gummy_bundle_price']));
                } else {
                    // Collar eliminado: cobrar precio normal del producto
                    $normal_price = floatval($cart_item['data']->get_regular_price() ?: $cart_item['data']->get_price());
                    if ($normal_price > 0) {
                        $cart_item['data']->set_price($normal_price);
                    }
                }
            }
        }
    }
    
    /**
     * Página de administración
     */
    public function add_admin_menu() {
        add_menu_page(
            'Configurador de Collar',
            'Collares',
            'manage_options',
            'collar-configurator',
            array($this, 'admin_page'),
            'dashicons-tag',
            56
        );
        
        add_submenu_page(
            'collar-configurator',
            'Ajustes',
            'Ajustes',
            'manage_options',
            'collar-configurator',
            array($this, 'admin_page')
        );
        
        add_submenu_page(
            'collar-configurator',
            'Configuraciones de Pedidos',
            'Pedidos',
            'manage_options',
            'collar-orders',
            array($this, 'orders_page')
        );
    }
    
    public function admin_page() {
        // Procesar envíos
        $messages = array();

        // Guardar ID de producto y URL del configurador
        if (isset($_POST['save_product'])) {
            check_admin_referer('collar_save_product', 'collar_settings_nonce');
            update_option('collar_product_id', intval($_POST['collar_product_id']));
            update_option('collar_configurator_page_url', esc_url_raw($_POST['collar_configurator_page_url'] ?? ''));
            update_option('gummy_product_id', intval($_POST['gummy_product_id'] ?? 0));
            update_option('gummy_page_url', esc_url_raw($_POST['gummy_page_url'] ?? ''));
            update_option('collar_whatsapp_number', sanitize_text_field($_POST['collar_whatsapp_number'] ?? '+57 301 527 4105'));
            $messages[] = 'Ajustes guardados.';
        }

        // Manejar subida de texturas / fuente
        if (isset($_POST['upload_textures'])) {
            check_admin_referer('collar_upload_textures', 'collar_upload_nonce');
            $upload_base = plugin_dir_path(__FILE__) . 'assets/texturas/';
            if (!file_exists($upload_base)) {
                wp_mkdir_p($upload_base);
            }

            // Dorada
            if (!empty($_FILES['texture_gold']['tmp_name'])) {
                $dst = $upload_base . 'dorada.png';
                if (move_uploaded_file($_FILES['texture_gold']['tmp_name'], $dst)) {
                    $messages[] = 'Textura dorada subida.';
                } else {
                    $messages[] = 'Error subiendo textura dorada.';
                }
            }

            // Plateada
            if (!empty($_FILES['texture_silver']['tmp_name'])) {
                $dst = $upload_base . 'plateada.png';
                if (move_uploaded_file($_FILES['texture_silver']['tmp_name'], $dst)) {
                    $messages[] = 'Textura plateada subida.';
                } else {
                    $messages[] = 'Error subiendo textura plateada.';
                }
            }

            // Fuente TTF
            if (!empty($_FILES['font_ttf']['tmp_name'])) {
                $fonts_dir = plugin_dir_path(__FILE__) . 'assets/fonts/';
                if (!file_exists($fonts_dir)) wp_mkdir_p($fonts_dir);
                $dstf = $fonts_dir . 'custom_font.ttf';
                if (move_uploaded_file($_FILES['font_ttf']['tmp_name'], $dstf)) {
                    $messages[] = 'Fuente subida correctamente.';
                } else {
                    $messages[] = 'Error subiendo la fuente.';
                }
            }
        }

        // Generar imágenes glitter (solo si se solicitó)
        if (isset($_POST['generate_glitter'])) {
            check_admin_referer('collar_generate_glitter', 'collar_glitter_nonce');
            $plugin_path = plugin_dir_path(__FILE__);
            
            // Buscar primera imagen en cada carpeta de textura
            $gold_dir = $plugin_path . 'assets/texturas/dorada/';
            $silver_dir = $plugin_path . 'assets/texturas/plateada/';
            
            $gold_files = glob($gold_dir . '*.png');
            $silver_files = glob($silver_dir . '*.png');
            
            $gold_texture = !empty($gold_files) ? $gold_files[0] : '';
            $silver_texture = !empty($silver_files) ? $silver_files[0] : '';
            
            $font_path = $plugin_path . 'assets/fonts/custom_font.ttf';
            
            $chars = array_merge(range('A','Z'), array('Ñ'), range('0','9'));
            $sizes = array(128, 256);

            if (!class_exists('Imagick')) {
                $messages[] = '⚠️ Imagick no está disponible en este servidor. Instálalo para generar imágenes server-side.';
            } elseif (empty($gold_texture) || empty($silver_texture)) {
                $messages[] = '⚠️ No se encontraron texturas en assets/texturas/dorada/ o .../plateada/';
            } else {
                // Crear carpetas de salida
                $out_base = $plugin_path . 'assets/charms/glitter/';
                if (!file_exists($out_base)) wp_mkdir_p($out_base);
                $gold_out = $out_base . 'gold/';
                $silver_out = $out_base . 'silver/';
                if (!file_exists($gold_out)) wp_mkdir_p($gold_out);
                if (!file_exists($silver_out)) wp_mkdir_p($silver_out);

                // Generar
                $ok1 = $this->generate_glitter_letters_imagick($gold_texture, $gold_out, $font_path, $chars, $sizes);
                $ok2 = $this->generate_glitter_letters_imagick($silver_texture, $silver_out, $font_path, $chars, $sizes);

                $messages[] = $ok1 ? 'Glitter dorado generado.' : 'Error generando dorado.';
                $messages[] = $ok2 ? 'Glitter plateado generado.' : 'Error generando plateado.';
            }
        }

        $product_id       = get_option('collar_product_id', '');
        $configurator_url = get_option('collar_configurator_page_url', '');
        $gummy_product_id = get_option('gummy_product_id', '');
        $gummy_page_url   = get_option('gummy_page_url', '');
        $whatsapp_number  = get_option('collar_whatsapp_number', '+57 301 527 4105');
        ?>
        <div class="wrap">
            <h1>Configurador de Collar - Ajustes</h1>

            <?php foreach($messages as $m) { echo '<div class="notice notice-success"><p>' . esc_html($m) . '</p></div>'; } ?>

            <form method="post" action="" style="margin-bottom:20px;">
                <?php wp_nonce_field('collar_save_product', 'collar_settings_nonce'); ?>
                <h2>ID del Producto</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">ID del Producto de Collar</th>
                        <td>
                            <input type="number" name="collar_product_id" value="<?php echo esc_attr($product_id); ?>" class="regular-text" />
                            <p class="description">Ingresa el ID del producto WooCommerce que representa el collar base</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">URL de la página del configurador</th>
                        <td>
                            <input type="url" name="collar_configurator_page_url" value="<?php echo esc_attr($configurator_url); ?>" class="large-text" placeholder="https://tutienda.com/personalizar-collar/" />
                            <p class="description">Cuando alguien visite la página del producto, será redirigido aquí automáticamente.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">ID del Producto Collar Gummy</th>
                        <td>
                            <input type="number" name="gummy_product_id" value="<?php echo esc_attr($gummy_product_id); ?>" class="regular-text" />
                            <p class="description">ID del producto WooCommerce del Collar Gummy ($49.900, tallas XS/M/L/XL). Úsalo con el shortcode <code>[collar_gummy]</code>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">URL de la página del Collar Gummy</th>
                        <td>
                            <input type="url" name="gummy_page_url" value="<?php echo esc_attr($gummy_page_url); ?>" class="large-text" placeholder="https://tutienda.com/collares-gummy/" />
                            <p class="description">Cuando alguien visite la página del producto Gummy en WooCommerce, será redirigido aquí automáticamente.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">WhatsApp de contacto</th>
                        <td>
                            <input type="text" name="collar_whatsapp_number" value="<?php echo esc_attr($whatsapp_number); ?>" class="regular-text" placeholder="+57 301 527 4105" />
                            <p class="description">Se usa en el botón flotante y en el mensaje de recogida de pedido.</p>
                        </td>
                    </tr>
                </table>
                <input type="hidden" name="save_product" value="1" />
                <?php submit_button('Guardar ID de producto'); ?>
            </form>

            <h2>Subir texturas / fuente</h2>
            <form method="post" action="" enctype="multipart/form-data">
                <?php wp_nonce_field('collar_upload_textures', 'collar_upload_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th>Textura Dorada</th>
                        <td><input type="file" name="texture_gold" accept="image/*" /></td>
                    </tr>
                    <tr>
                        <th>Textura Plateada</th>
                        <td><input type="file" name="texture_silver" accept="image/*" /></td>
                    </tr>
                    <tr>
                        <th>Fuente TTF</th>
                        <td><input type="file" name="font_ttf" accept=".ttf,.otf" /></td>
                    </tr>
                </table>
                <input type="hidden" name="upload_textures" value="1" />
                <?php submit_button('Subir archivos'); ?>
            </form>

            <h2>Generar letras glitter</h2>
            <form method="post" action="">
                <?php wp_nonce_field('collar_generate_glitter', 'collar_glitter_nonce'); ?>
                <p>Genera PNGs A–Z, Ñ y 0–9 en carpetas `assets/charms/glitter/gold` y `.../silver` (requiere Imagick)</p>
                <input type="hidden" name="generate_glitter" value="1" />
                <?php submit_button('Generar ahora'); ?>
            </form>

            <h2>Instrucciones</h2>
            <ol>
                <li>Crea un producto en WooCommerce para el "Collar Base"</li>
                <li>Copia el ID del producto (lo encuentras en la URL al editarlo)</li>
                <li>Pégalo en el campo de arriba y guarda</li>
                <li>Sube las texturas dorada/plateada y una fuente TTF si quieres control total</li>
                <li>Haz clic en "Generar ahora" para crear los PNGs de las letras</li>
            </ol>

            <h2>Shortcodes Disponibles</h2>
            <code>[collar_configurador]</code> - Configurador completo<br>
            <code>[collar_configurador show_title="false"]</code> - Sin título
        </div>
        <?php
    }
    
    /**
     * Crear tabla personalizada para almacenar configuraciones de collares
     */
    public function create_collar_orders_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'collar_configurations';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned DEFAULT NULL,
            customer_name varchar(200) DEFAULT '',
            customer_email varchar(200) DEFAULT '',
            customer_phone varchar(50) DEFAULT '',
            collar_size varchar(10) DEFAULT '',
            collar_color varchar(50) DEFAULT '',
            charms_count int(11) DEFAULT 0,
            charms_data longtext DEFAULT '',
            charms_summary varchar(500) DEFAULT '',
            custom_price decimal(10,2) DEFAULT 0.00,
            order_status varchar(50) DEFAULT '',
            order_total decimal(10,2) DEFAULT 0.00,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY customer_email (customer_email),
            KEY collar_size (collar_size),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        update_option('collar_db_version', '1.0');
    }
    
    /**
     * Crear tabla si no existe (al cargar admin)
     */
    public function maybe_create_table() {
        if (get_option('collar_db_version') !== '1.0') {
            $this->create_collar_orders_table();
        }
    }
    
    /**
     * Guardar configuración del collar en la BD al procesar el checkout
     */
    public function save_collar_to_db($order_id, $posted_data, $order) {
        if (!$order) return;
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'collar_configurations';
        
        foreach ($order->get_items() as $item) {
            $charms_config = $item->get_meta('_collar_charms_data');
            if (empty($charms_config)) {
                // Compatibilidad con pedidos anteriores al cambio de clave
                $charms_config = $item->get_meta('Configuración de Charms');
            }
            if (empty($charms_config)) continue;
            
            $summary = $this->build_charms_summary($charms_config);

            $wpdb->insert($table_name, array(
                'order_id' => $order_id,
                'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'customer_email' => $order->get_billing_email(),
                'customer_phone' => $order->get_billing_phone(),
                'collar_size' => $item->get_meta('Tamaño') ?: $item->get_meta('Tamaño del Collar'),
                'collar_color' => $item->get_meta('Color del collar') ?: $item->get_meta('Color') ?: $item->get_meta('Color del Collar'),
                'charms_count' => intval($item->get_meta('Charms') ?: $item->get_meta('Cantidad de Charms')),
                'charms_data' => $charms_config,
                'charms_summary' => $summary,
                'custom_price' => $item->get_total(),
                'order_status' => $order->get_status(),
                'order_total' => $order->get_total(),
                'created_at' => current_time('mysql')
            ), array('%d','%s','%s','%s','%s','%s','%d','%s','%s','%f','%s','%f','%s'));
        }
    }
    
    /**
     * Actualizar estado cuando cambia el estado de la orden
     */
    public function save_collar_on_status_change($order_id, $old_status, $new_status, $order) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'collar_configurations';
        
        // Actualizar estado
        $wpdb->update(
            $table_name,
            array('order_status' => $new_status),
            array('order_id' => $order_id),
            array('%s'),
            array('%d')
        );
        
        // Si no existe aún en la tabla, intentar guardarlo
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE order_id = %d", $order_id));
        if (!$exists && $order) {
            foreach ($order->get_items() as $item) {
                $charms_config = $item->get_meta('_collar_charms_data');
            if (empty($charms_config)) {
                // Compatibilidad con pedidos anteriores al cambio de clave
                $charms_config = $item->get_meta('Configuración de Charms');
            }
                if (empty($charms_config)) continue;
                
                $summary = $this->build_charms_summary($charms_config);

                $wpdb->insert($table_name, array(
                    'order_id' => $order_id,
                    'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    'customer_email' => $order->get_billing_email(),
                    'customer_phone' => $order->get_billing_phone(),
                    'collar_size' => $item->get_meta('Tamaño') ?: $item->get_meta('Tamaño del Collar'),
                    'collar_color' => $item->get_meta('Color del collar') ?: $item->get_meta('Color') ?: $item->get_meta('Color del Collar'),
                    'charms_count' => intval($item->get_meta('Charms') ?: $item->get_meta('Cantidad de Charms')),
                    'charms_data' => $charms_config,
                    'charms_summary' => $summary,
                    'custom_price' => $item->get_total(),
                    'order_status' => $new_status,
                    'order_total' => $order->get_total(),
                    'created_at' => $order->get_date_created() ? $order->get_date_created()->format('Y-m-d H:i:s') : current_time('mysql')
                ), array('%d','%s','%s','%s','%s','%s','%d','%s','%s','%f','%s','%f','%s'));
            }
        }
    }
    
    /**
     * Página de administración de pedidos con configuraciones
     */
    public function orders_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'collar_configurations';

        // Verificar si la tabla existe, si no, crearla y sincronizar desde WooCommerce
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        if (!$table_exists) {
            $this->create_collar_orders_table();
        }

        // Manejar eliminación individual (solo del registro local; no toca la orden de WC)
        $delete_notice = '';
        if (isset($_POST['collar_delete_id']) && current_user_can('manage_options')) {
            check_admin_referer('collar_delete_row', 'collar_delete_nonce');
            $del_id = intval($_POST['collar_delete_id']);
            if ($del_id > 0) {
                $deleted = $wpdb->delete($table_name, array('id' => $del_id), array('%d'));
                $delete_notice = $deleted
                    ? '<div class="notice notice-success is-dismissible"><p>✅ Registro eliminado del panel. La orden de WooCommerce no fue tocada.</p></div>'
                    : '<div class="notice notice-error"><p>No se pudo eliminar el registro.</p></div>';
            }
        }

        // Reenviar manualmente el correo "Nuevo pedido" a las cuentas del administrador
        // (las configuradas en WooCommerce → Ajustes → Correos → Nuevo pedido). Es el
        // mismo correo completo, con visual de charms, envío y total.
        $resend_notice = '';
        if (isset($_POST['collar_resend_order_id']) && current_user_can('manage_options')) {
            check_admin_referer('collar_resend_email', 'collar_resend_nonce');
            $resend_oid   = intval($_POST['collar_resend_order_id']);
            $resend_order = $resend_oid > 0 ? wc_get_order($resend_oid) : null;
            if ($resend_order && function_exists('WC')) {
                $mailer = WC()->mailer();
                if ($mailer && !empty($mailer->emails['WC_Email_New_Order'])) {
                    // Sin este filtro, desde WC 10 trigger() ignora la llamada si el pedido
                    // ya tiene _new_order_email_sent (mostraría éxito sin enviar nada).
                    add_filter('woocommerce_new_order_email_allows_resend', '__return_true');
                    $mailer->emails['WC_Email_New_Order']->trigger($resend_oid, $resend_order);
                    remove_filter('woocommerce_new_order_email_allows_resend', '__return_true');
                    $resend_order->update_meta_data('_collar_admin_email_sent', 'yes');
                    $resend_order->add_order_note('📧 Correo "Nuevo pedido" reenviado manualmente desde el panel de collares.');
                    $resend_order->save();
                    $resend_notice = '<div class="notice notice-success is-dismissible"><p>📧 Correo del pedido #' . $resend_oid . ' reenviado a las cuentas del administrador.</p></div>';
                } else {
                    $resend_notice = '<div class="notice notice-error"><p>No se pudo enviar el correo (servicio de correo no disponible).</p></div>';
                }
            } else {
                $resend_notice = '<div class="notice notice-error"><p>Pedido no encontrado.</p></div>';
            }
        }
        
        // Sincronizar datos desde WooCommerce si se pide o si la tabla está vacía
        $total_in_db = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        if (isset($_GET['sync']) || $total_in_db == 0) {
            $this->sync_orders_to_db();
            if (isset($_GET['sync'])) {
                echo '<div class="notice notice-success"><p>✅ Base de datos sincronizada correctamente con WooCommerce.</p></div>';
            }
        }
        
        // Exportar CSV
        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            $this->export_orders_csv();
            return;
        }
        
        // Filtros
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $filter_size = isset($_GET['filter_size']) ? sanitize_text_field($_GET['filter_size']) : '';
        $filter_color = isset($_GET['filter_color']) ? sanitize_text_field($_GET['filter_color']) : '';
        $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';
        $filter_from = isset($_GET['filter_from']) ? sanitize_text_field($_GET['filter_from']) : '';
        $filter_to   = isset($_GET['filter_to'])   ? sanitize_text_field($_GET['filter_to'])   : '';
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        // Construir query
        $where = "WHERE 1=1";
        $params = array();
        
        if (!empty($search)) {
            $where .= " AND (customer_name LIKE %s OR customer_email LIKE %s OR charms_summary LIKE %s OR order_id LIKE %s)";
            $like = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if (!empty($filter_size)) {
            $where .= " AND collar_size = %s";
            $params[] = $filter_size;
        }
        if (!empty($filter_color)) {
            $where .= " AND collar_color = %s";
            $params[] = $filter_color;
        }
        if (!empty($filter_status)) {
            $where .= " AND order_status = %s";
            $params[] = $filter_status;
        }
        if (!empty($filter_from)) {
            $where .= " AND created_at >= %s";
            $params[] = $filter_from . ' 00:00:00';
        }
        if (!empty($filter_to)) {
            $where .= " AND created_at <= %s";
            $params[] = $filter_to . ' 23:59:59';
        }

        // Total filtrado
        $count_query = "SELECT COUNT(*) FROM $table_name $where";
        if (!empty($params)) {
            $total_items = $wpdb->get_var($wpdb->prepare($count_query, ...$params));
        } else {
            $total_items = $wpdb->get_var($count_query);
        }
        $total_pages = ceil($total_items / $per_page);
        
        // Obtener resultados
        $query = "SELECT * FROM $table_name $where ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $query_params = array_merge($params, array($per_page, $offset));
        $results = $wpdb->get_results($wpdb->prepare($query, ...$query_params));
        
        // Estadísticas generales.
        // "revenue" suma el PRECIO POR COLLAR (custom_price) solo de pedidos COMPLETADOS,
        // así no se duplica cuando una orden tiene varios collares en una misma compra.
        $stats = $wpdb->get_row("SELECT
            COUNT(*) as total,
            SUM(CASE WHEN order_status = 'completed' THEN custom_price ELSE 0 END) as revenue,
            AVG(charms_count) as avg_charms,
            MAX(charms_count) as max_charms
            FROM $table_name");

        // Conteo de cuántos collares hay por pedido (para mostrar "1 de 2", "2 de 2", etc.).
        $order_collar_counts = $wpdb->get_results(
            "SELECT order_id, COUNT(*) as n FROM $table_name GROUP BY order_id",
            OBJECT_K
        );
        
        $popular_size = $wpdb->get_var("SELECT collar_size FROM $table_name GROUP BY collar_size ORDER BY COUNT(*) DESC LIMIT 1");
        $popular_color = $wpdb->get_var("SELECT collar_color FROM $table_name GROUP BY collar_color ORDER BY COUNT(*) DESC LIMIT 1");
        
        // Valores únicos para filtros
        $sizes_available = $wpdb->get_col("SELECT DISTINCT collar_size FROM $table_name WHERE collar_size != '' ORDER BY collar_size");
        $colors_available = $wpdb->get_col("SELECT DISTINCT collar_color FROM $table_name WHERE collar_color != '' ORDER BY collar_color");
        $statuses_available = $wpdb->get_col("SELECT DISTINCT order_status FROM $table_name WHERE order_status != '' ORDER BY order_status");
        
        $base_url = admin_url('admin.php?page=collar-orders');
        ?>
        <div class="wrap">
            <h1 style="display: flex; align-items: center; gap: 10px;">
                🐾 Base de Datos de Collares Personalizados
                <a href="<?php echo $base_url; ?>&sync=1" class="page-title-action">🔄 Sincronizar con WooCommerce</a>
                <a href="<?php echo $base_url; ?>&export=csv&s=<?php echo urlencode($search); ?>&filter_size=<?php echo urlencode($filter_size); ?>&filter_color=<?php echo urlencode($filter_color); ?>&filter_status=<?php echo urlencode($filter_status); ?>&filter_from=<?php echo urlencode($filter_from); ?>&filter_to=<?php echo urlencode($filter_to); ?>" class="page-title-action">📥 Exportar CSV</a>
            </h1>

            <?php echo $delete_notice; ?>
            <?php echo $resend_notice; ?>

            <!-- Dashboard de estadísticas -->
            <div class="collar-stats-dashboard">
                <div class="stat-card">
                    <div class="stat-number"><?php echo intval($stats->total ?? 0); ?></div>
                    <div class="stat-label">Collares vendidos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats->revenue ? '$' . number_format($stats->revenue, 0, ',', '.') : '$0'; ?></div>
                    <div class="stat-label">Ingresos totales</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats->avg_charms ? round($stats->avg_charms, 1) : '0'; ?></div>
                    <div class="stat-label">Promedio charms</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo esc_html($popular_size ?: '-'); ?></div>
                    <div class="stat-label">Tamaño más popular</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo esc_html($popular_color ?: '-'); ?></div>
                    <div class="stat-label">Color más popular</div>
                </div>
            </div>
            
            <!-- Barra de búsqueda y filtros -->
            <form method="get" action="<?php echo admin_url('admin.php'); ?>" class="collar-filters-bar">
                <input type="hidden" name="page" value="collar-orders">

                <div class="collar-filter-row collar-filter-search-row">
                    <div class="collar-filter-field" style="flex:1;min-width:280px;">
                        <label class="collar-filter-label">Buscar</label>
                        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Nombre, email, charms o # pedido..." class="collar-filter-input">
                    </div>
                </div>

                <div class="collar-filter-row">
                    <div class="collar-filter-field">
                        <label class="collar-filter-label">Tamaño</label>
                        <select name="filter_size" class="collar-filter-input">
                            <option value="">Todos</option>
                            <?php foreach ($sizes_available as $s): ?>
                                <option value="<?php echo esc_attr($s); ?>" <?php selected($filter_size, $s); ?>><?php echo esc_html(strtoupper($s)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="collar-filter-field">
                        <label class="collar-filter-label">Color</label>
                        <select name="filter_color" class="collar-filter-input">
                            <option value="">Todos</option>
                            <?php foreach ($colors_available as $c): ?>
                                <option value="<?php echo esc_attr($c); ?>" <?php selected($filter_color, $c); ?>><?php echo esc_html($c); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="collar-filter-field">
                        <label class="collar-filter-label">Estado</label>
                        <select name="filter_status" class="collar-filter-input">
                            <option value="">Todos</option>
                            <?php foreach ($statuses_available as $st): ?>
                                <option value="<?php echo esc_attr($st); ?>" <?php selected($filter_status, $st); ?>><?php echo esc_html(ucfirst($st)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="collar-filter-field">
                        <label class="collar-filter-label">Desde</label>
                        <input type="date" name="filter_from" value="<?php echo esc_attr($filter_from); ?>" class="collar-filter-input">
                    </div>

                    <div class="collar-filter-field">
                        <label class="collar-filter-label">Hasta</label>
                        <input type="date" name="filter_to" value="<?php echo esc_attr($filter_to); ?>" class="collar-filter-input">
                    </div>

                    <div class="collar-filter-actions">
                        <button type="submit" class="button button-primary">Aplicar filtros</button>
                        <a href="<?php echo $base_url; ?>" class="button">Limpiar</a>
                    </div>
                </div>
            </form>
            
            <p class="collar-results-count">
                Mostrando <strong><?php echo count($results); ?></strong> de <strong><?php echo $total_items; ?></strong> registros
                <?php if (!empty($search) || !empty($filter_size) || !empty($filter_color) || !empty($filter_status) || !empty($filter_from) || !empty($filter_to)): ?>
                    (filtrado)
                <?php endif; ?>
            </p>
            
            <?php if (empty($results)): ?>
                <div class="notice notice-info">
                    <p>No se encontraron collares con los filtros seleccionados.</p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
                    <thead>
                        <tr>
                            <th width="70">Pedido</th>
                            <th width="110">Fecha</th>
                            <th width="90">Estado</th>
                            <th width="90">Pago</th>
                            <th>Cliente</th>
                            <th width="60">Tamaño</th>
                            <th width="110">Color del collar</th>
                            <th width="60">Charms</th>
                            <th width="180">Resumen</th>
                            <th width="90">Total</th>
                            <th width="90">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $now_ts = current_time('timestamp');
                        $assets_url_row = plugin_dir_url(__FILE__) . 'assets/';
                        $gummy_pid_panel = (int) get_option('gummy_product_id', 0);
                        // Para numerar collares dentro de un mismo pedido (#1 de 2, #2 de 2)
                        $collar_index_in_order = array();
                        foreach ($results as $idx => $row):
                            // Posición del collar dentro de su pedido
                            $oid = $row->order_id;
                            if (!isset($collar_index_in_order[$oid])) $collar_index_in_order[$oid] = 0;
                            $collar_index_in_order[$oid]++;
                            $total_in_this_order = isset($order_collar_counts[$oid]) ? (int) $order_collar_counts[$oid]->n : 1;
                            $multi_badge = '';
                            if ($total_in_this_order > 1) {
                                $multi_badge = '<div style="display:inline-block;margin-top:3px;padding:2px 8px;background:#6366f1;color:#fff;border-radius:10px;font-size:10px;font-weight:600;">Collar ' . $collar_index_in_order[$oid] . ' de ' . $total_in_this_order . '</div>';
                            }
                            // Antigüedad para pedidos no completados/cancelados
                            $age_days   = floor(($now_ts - strtotime($row->created_at)) / DAY_IN_SECONDS);
                            $is_pending = in_array($row->order_status, array('pending','processing','on-hold'), true);
                            $age_badge  = '';
                            if ($is_pending && $age_days >= 3) {
                                $bg = $age_days >= 7 ? '#dc2626' : '#f59e0b';
                                $age_badge = '<div style="display:inline-block;margin-top:4px;padding:2px 8px;background:' . $bg . ';color:#fff;border-radius:10px;font-size:11px;font-weight:600;">⏱ ' . intval($age_days) . ' días</div>';
                            }
                            // Swatch del color del collar
                            $swatch_hex = $this->collar_color_hex($row->collar_color);
                            // Link WhatsApp
                            $wa_url = $this->whatsapp_url_from_phone($row->customer_phone);
                            // Orden de WooCommerce (para pago, link de edición HPOS-safe, etc.)
                            $row_order      = wc_get_order($row->order_id);
                            $order_edit_url = $row_order
                                ? $row_order->get_edit_order_url()
                                : admin_url('post.php?post=' . $row->order_id . '&action=edit');
                            // ¿El pedido incluye Collar Gummy? El panel solo lista collares con
                            // charms, así que sin esto el Gummy quedaría invisible.
                            $gummy_badge = '';
                            if ($row_order && $gummy_pid_panel) {
                                $gummy_qty = 0;
                                foreach ($row_order->get_items() as $li) {
                                    if ((int) $li->get_product_id() === $gummy_pid_panel) {
                                        $gummy_qty += (int) $li->get_quantity();
                                    }
                                }
                                if ($gummy_qty > 0) {
                                    $gummy_badge = '<div style="display:inline-block;margin-top:3px;padding:2px 8px;background:#0d9488;color:#fff;border-radius:10px;font-size:10px;font-weight:600;">🍬 + Gummy'
                                                 . ($gummy_qty > 1 ? ' x' . $gummy_qty : '') . '</div>';
                                }
                            }
                            // Mini-charms preview
                            $mini_charms = '';
                            $charms_for_mini = json_decode(wp_unslash($row->charms_data), true);
                            if (is_array($charms_for_mini)) {
                                $mini_charms .= '<div style="display:flex;flex-wrap:wrap;gap:3px;">';
                                foreach ($charms_for_mini as $c) {
                                    $cHex = isset($c['colorHex']) ? $c['colorHex'] : '#000';
                                    $val  = isset($c['value']) ? $c['value'] : '?';
                                    $em   = !empty($c['isEmoji']);
                                    $gl   = !empty($c['isGlitter']);
                                    if ($gl && $cHex === '#GOLD')      { $bg = '#FFD700'; $fg = '#8B4513'; }
                                    elseif ($gl && $cHex === '#SILVER'){ $bg = '#C0C0C0'; $fg = '#333'; }
                                    elseif ($em)                       { $bg = '#f0f0f0'; $fg = '#333'; }
                                    else                               { $bg = $cHex; $fg = '#fff'; }
                                    if ($em) {
                                        $folder = ltrim($cHex, '#');
                                        $url    = $assets_url_row . 'emojis/' . $folder . '/' . $val;
                                        $mini_charms .= '<span style="display:inline-block;width:26px;height:26px;border-radius:4px;background:' . esc_attr($bg) . ';overflow:hidden;border:1px solid #ddd;"><img src="' . esc_url($url) . '" style="width:26px;height:26px;object-fit:contain;display:block;" alt=""/></span>';
                                    } else {
                                        $mini_charms .= '<span style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:4px;background:' . esc_attr($bg) . ';color:' . $fg . ';font-weight:900;font-size:13px;border:1px solid #ddd;">' . esc_html($val) . '</span>';
                                    }
                                }
                                $mini_charms .= '</div>';
                            }
                        ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="<?php echo esc_url($order_edit_url); ?>" target="_blank">
                                            #<?php echo $row->order_id; ?>
                                        </a>
                                    </strong>
                                    <?php echo $multi_badge; ?>
                                    <?php echo $gummy_badge; ?>
                                </td>
                                <td>
                                    <?php echo wp_date('d/m/Y H:i', strtotime($row->created_at)); ?>
                                    <?php echo $age_badge; ?>
                                </td>
                                <td>
                                    <span class="order-status status-<?php echo esc_attr($row->order_status); ?>">
                                        <?php echo esc_html(ucfirst($row->order_status)); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $pay_title = $row_order ? $row_order->get_payment_method_title() : '';
                                    $pay_slug  = $row_order ? $row_order->get_payment_method() : '';
                                    $pay_color = '#6b7280';
                                    if (stripos($pay_slug, 'addi') !== false || stripos($pay_title, 'addi') !== false) {
                                        $pay_color = '#7c3aed';
                                    } elseif (stripos($pay_slug, 'bold') !== false || stripos($pay_title, 'bold') !== false) {
                                        $pay_color = '#0ea5e9';
                                    } elseif (stripos($pay_slug, 'cod') !== false) {
                                        $pay_color = '#16a34a';
                                    }
                                    ?>
                                    <?php if ($pay_title): ?>
                                        <span style="display:inline-block;padding:3px 8px;background:<?php echo esc_attr($pay_color); ?>;color:#fff;border-radius:10px;font-size:11px;font-weight:600;white-space:nowrap;">
                                            💳 <?php echo esc_html($pay_title); ?>
                                        </span>
                                    <?php else: ?>
                                        <small style="color:#9ca3af;">—</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($row->customer_name); ?></strong><br>
                                    <small><?php echo esc_html($row->customer_email); ?></small>
                                    <?php if (!empty($row->customer_phone)): ?>
                                        <br>
                                        <?php if ($wa_url): ?>
                                            <a href="<?php echo esc_url($wa_url); ?>" target="_blank" rel="noopener" style="text-decoration:none;font-size:12px;color:#25D366;font-weight:600;" title="Abrir WhatsApp">
                                                💬 <?php echo esc_html($row->customer_phone); ?>
                                            </a>
                                        <?php else: ?>
                                            <small>📞 <?php echo esc_html($row->customer_phone); ?></small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo esc_html(strtoupper($row->collar_size)); ?></strong></td>
                                <td>
                                    <?php if ($swatch_hex): ?>
                                        <span style="display:inline-block;width:14px;height:14px;border-radius:50%;background:<?php echo esc_attr($swatch_hex); ?>;border:1px solid #ccc;vertical-align:middle;margin-right:6px;"></span>
                                    <?php endif; ?>
                                    <?php echo esc_html($row->collar_color); ?>
                                </td>
                                <td style="text-align: center;"><strong><?php echo $row->charms_count; ?></strong></td>
                                <td>
                                    <?php echo $mini_charms; ?>
                                </td>
                                <td>
                                    <strong><?php echo '$' . number_format($row->custom_price, 0, ',', '.'); ?></strong>
                                    <?php // El total del pedido incluye otros collares, el Gummy y el envío. ?>
                                    <?php if (round((float) $row->order_total) > round((float) $row->custom_price)): ?>
                                        <div style="font-size:11px;color:#6b7280;margin-top:2px;">Pedido: $<?php echo number_format($row->order_total, 0, ',', '.'); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="button button-small" onclick="toggleDetails('d<?php echo $row->id; ?>')">
                                        📋 Detalles
                                    </button>
                                    <?php if (in_array($row->order_status, array('processing', 'on-hold', 'completed'), true)): ?>
                                    <form method="post" style="display:inline-block;margin-top:4px;" onsubmit="return confirm('¿Reenviar el correo de este pedido a la cuenta del administrador (Lina)?\n\nIncluye el detalle completo del collar (charms, tamaño, color, envío). No se envía nada al cliente.');">
                                        <?php wp_nonce_field('collar_resend_email', 'collar_resend_nonce'); ?>
                                        <input type="hidden" name="collar_resend_order_id" value="<?php echo (int) $row->order_id; ?>">
                                        <button type="submit" class="button button-small" style="color:#1d6fa5;border-color:#9cc3e0;">📧 Reenviar correo</button>
                                    </form>
                                    <?php endif; ?>
                                    <form method="post" style="display:inline-block;margin-top:4px;" onsubmit="return confirm('¿Eliminar este registro del panel?\n\nLa orden de WooCommerce NO se elimina, solo desaparece de esta lista. Si quieres borrar la orden completa, hazlo desde WooCommerce → Pedidos.');">
                                        <?php wp_nonce_field('collar_delete_row', 'collar_delete_nonce'); ?>
                                        <input type="hidden" name="collar_delete_id" value="<?php echo (int) $row->id; ?>">
                                        <button type="submit" class="button button-small" style="color:#b91c1c;border-color:#fca5a5;">🗑️ Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                            <tr id="d<?php echo $row->id; ?>" style="display: none;">
                                <td colspan="11" style="background: #f9f9f9; padding: 20px;">
                                    <?php
                                    // Cargar la orden de WooCommerce para datos de envío/pago
                                    $wc_order   = wc_get_order($row->order_id);
                                    $ship_zone  = $this->get_order_shipping_zone($wc_order);
                                    $zone_label = array(
                                        'local'    => 'Bogotá, Chía y Cota ($8.900)',
                                        'nacional' => 'Nivel Nacional ($13.900)',
                                        'recoger'  => 'Pasar a recoger (Gratis)',
                                    );
                                    $ship_method = isset($zone_label[$ship_zone]) ? $zone_label[$ship_zone] : '—';
                                    $payment     = $wc_order ? $wc_order->get_payment_method_title() : '—';
                                    $payment_slug = $wc_order ? $wc_order->get_payment_method() : '';
                                    $txn_id      = $wc_order ? $wc_order->get_transaction_id() : '';
                                    $admin_email_sent = $wc_order && (
                                        $wc_order->get_meta('_collar_admin_email_sent') === 'yes'
                                        || (bool) $wc_order->get_meta('_new_order_email_sent')
                                    );
                                    $ship_addr1  = $wc_order ? $wc_order->get_shipping_address_1() : '';
                                    $ship_addr2  = $wc_order ? $wc_order->get_shipping_address_2() : '';
                                    $ship_city   = $wc_order ? $wc_order->get_shipping_city() : '';
                                    $ship_state  = $wc_order ? $wc_order->get_shipping_state() : '';
                                    $cedula      = $wc_order ? $wc_order->get_meta('_billing_cedula') : '';
                                    if (!$cedula && $wc_order) $cedula = $wc_order->get_meta('billing_cedula');
                                    $doc_type    = $wc_order ? $wc_order->get_meta('_billing_document_type') : '';
                                    if (!$doc_type && $wc_order) $doc_type = $wc_order->get_meta('billing_document_type');
                                    ?>
                                    <div style="max-width: 1000px;">
                                        <h3 style="margin-top: 0;">🎨 Configuración Completa del Collar</h3>

                                        <div style="display: grid; grid-template-columns: 180px 1fr 180px 1fr; gap: 8px 15px; margin-bottom: 20px; background: white; padding: 15px; border-radius: 5px;">
                                            <div><strong>📦 Pedido:</strong></div>
                                            <div>#<?php echo $row->order_id; ?></div>
                                            <div><strong>📅 Fecha:</strong></div>
                                            <div><?php echo wp_date('d/m/Y H:i', strtotime($row->created_at)); ?></div>

                                            <div><strong>👤 Cliente:</strong></div>
                                            <div><?php echo esc_html($row->customer_name); ?></div>
                                            <div><strong>📧 Email:</strong></div>
                                            <div><?php echo esc_html($row->customer_email); ?></div>

                                            <div><strong>📞 Teléfono:</strong></div>
                                            <div><?php echo esc_html($row->customer_phone ?: 'N/A'); ?></div>
                                            <div><strong>🪪 Documento:</strong></div>
                                            <div><?php echo $cedula ? esc_html(trim(($doc_type ? $doc_type . ' ' : '') . $cedula)) : '—'; ?></div>

                                            <div><strong>📏 Tamaño:</strong></div>
                                            <div><?php echo esc_html(strtoupper($row->collar_size)); ?></div>
                                            <div><strong>🎨 Color del collar:</strong></div>
                                            <div><?php echo esc_html($row->collar_color); ?></div>

                                            <div><strong>✨ Charms:</strong></div>
                                            <div><?php echo $row->charms_count; ?> charms</div>
                                            <div><strong>📊 Estado:</strong></div>
                                            <div><span class="order-status status-<?php echo esc_attr($row->order_status); ?>"><?php echo esc_html(ucfirst($row->order_status)); ?></span></div>

                                            <div><strong>💳 Pago:</strong></div>
                                            <div>
                                                <?php echo esc_html($payment); ?>
                                                <?php if ($payment_slug): ?>
                                                    <small style="color:#9ca3af;">(<?php echo esc_html($payment_slug); ?>)</small>
                                                <?php endif; ?>
                                            </div>
                                            <div><strong>💰 Total:</strong></div>
                                            <div><strong><?php echo '$' . number_format($row->order_total, 0, ',', '.'); ?> COP</strong></div>

                                            <?php if ($txn_id): ?>
                                                <div><strong>🔖 Transacción:</strong></div>
                                                <div style="font-family:monospace;font-size:12px;word-break:break-all;"><?php echo esc_html($txn_id); ?></div>
                                            <?php endif; ?>

                                            <div><strong>📧 Correo admin:</strong></div>
                                            <div>
                                                <?php if ($admin_email_sent): ?>
                                                    <span style="color:#16a34a;font-size:12px;">✓ Enviado</span>
                                                <?php else: ?>
                                                    <span style="color:#dc2626;font-size:12px;">✗ Sin registro de envío — usa «Reenviar correo»</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Productos del pedido: desglose completo (collares, Gummy,
                                             envío y total). Sin esto el Gummy quedaba invisible y el
                                             valor del pedido no cuadraba con WooCommerce. -->
                                        <?php if ($wc_order): ?>
                                        <div style="background:white;padding:15px;border:1px solid #ddd;border-radius:5px;margin-bottom:20px;">
                                            <h4 style="margin:0 0 10px;">🛍️ Productos del pedido</h4>
                                            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                                                <thead>
                                                    <tr style="color:#6b7280;border-bottom:1px solid #e5e7eb;">
                                                        <th style="padding:6px 4px;text-align:left;">Artículo</th>
                                                        <th style="padding:6px 4px;text-align:center;width:60px;">Cant.</th>
                                                        <th style="padding:6px 4px;text-align:right;width:120px;">Importe</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                <?php
                                                $gummy_pid_detail = (int) get_option('gummy_product_id', 0);
                                                foreach ($wc_order->get_items() as $li) {
                                                    $is_gummy_item = $gummy_pid_detail && (int) $li->get_product_id() === $gummy_pid_detail;

                                                    // Metas visibles del artículo (Tamaño, Color, Talla Gummy...).
                                                    // Se omiten los internos (prefijo _) y el JSON de charms.
                                                    $li_details = array();
                                                    foreach ($li->get_meta_data() as $md) {
                                                        $mdata = $md->get_data();
                                                        $mkey  = isset($mdata['key']) ? (string) $mdata['key'] : '';
                                                        $mval  = isset($mdata['value']) ? $mdata['value'] : '';
                                                        if ($mkey === '' || $mkey[0] === '_') continue;
                                                        if ($mkey === 'Configuración de Charms') continue;
                                                        if (!is_scalar($mval) || $mval === '') continue;
                                                        $li_details[] = $mkey . ': ' . $mval;
                                                    }
                                                    ?>
                                                    <tr style="border-bottom:1px solid #f3f4f6;">
                                                        <td style="padding:8px 4px;">
                                                            <strong><?php echo esc_html($li->get_name()); ?></strong>
                                                            <?php if ($is_gummy_item): ?>
                                                                <span style="display:inline-block;margin-left:6px;padding:2px 8px;background:#0d9488;color:#fff;border-radius:10px;font-size:10px;font-weight:600;">🍬 Gummy</span>
                                                            <?php endif; ?>
                                                            <?php if (!empty($li_details)): ?>
                                                                <div style="color:#6b7280;font-size:12px;margin-top:3px;"><?php echo esc_html(implode(' · ', $li_details)); ?></div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td style="padding:8px 4px;text-align:center;"><?php echo (int) $li->get_quantity(); ?></td>
                                                        <td style="padding:8px 4px;text-align:right;">$<?php echo number_format((float) $li->get_total(), 0, ',', '.'); ?></td>
                                                    </tr>
                                                    <?php
                                                }

                                                // Envío cobrado como fee (selector propio) y como shipping nativo.
                                                foreach ($wc_order->get_items('fee') as $fee) { ?>
                                                    <tr style="border-bottom:1px solid #f3f4f6;color:#374151;">
                                                        <td style="padding:8px 4px;">🚚 <?php echo esc_html($fee->get_name()); ?></td>
                                                        <td style="padding:8px 4px;text-align:center;">—</td>
                                                        <td style="padding:8px 4px;text-align:right;">$<?php echo number_format((float) $fee->get_total(), 0, ',', '.'); ?></td>
                                                    </tr>
                                                <?php }
                                                foreach ($wc_order->get_items('shipping') as $ship) {
                                                    if (round((float) $ship->get_total()) <= 0) continue; ?>
                                                    <tr style="border-bottom:1px solid #f3f4f6;color:#374151;">
                                                        <td style="padding:8px 4px;">🚚 <?php echo esc_html($ship->get_name()); ?></td>
                                                        <td style="padding:8px 4px;text-align:center;">—</td>
                                                        <td style="padding:8px 4px;text-align:right;">$<?php echo number_format((float) $ship->get_total(), 0, ',', '.'); ?></td>
                                                    </tr>
                                                <?php } ?>
                                                    <tr>
                                                        <td colspan="2" style="padding:10px 4px;text-align:right;font-weight:700;">Total del pedido</td>
                                                        <td style="padding:10px 4px;text-align:right;font-weight:700;font-size:15px;">$<?php echo number_format((float) $wc_order->get_total(), 0, ',', '.'); ?></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                            <p style="margin:10px 0 0;color:#6b7280;font-size:12px;">
                                                Este es el pedido completo tal como está en WooCommerce. Arriba, «Charms seleccionados» corresponde solo a este collar.
                                            </p>
                                        </div>
                                        <?php endif; ?>

                                        <!-- Bloque de envío -->
                                        <div style="background:white;padding:15px;border:1px solid #ddd;border-radius:5px;margin-bottom:20px;">
                                            <h4 style="margin:0 0 10px;">🚚 Envío</h4>
                                            <div style="display:grid;grid-template-columns:180px 1fr;gap:6px 15px;">
                                                <div><strong>Método:</strong></div>
                                                <div><?php echo esc_html($ship_method); ?></div>
                                                <?php if ($ship_zone !== 'recoger' && ($ship_addr1 || $ship_city)): ?>
                                                    <div><strong>Dirección:</strong></div>
                                                    <div><?php echo esc_html($ship_addr1); ?></div>
                                                    <?php if ($ship_addr2): ?>
                                                        <div><strong>Barrio / Ref:</strong></div>
                                                        <div><?php echo esc_html($ship_addr2); ?></div>
                                                    <?php endif; ?>
                                                    <?php if ($ship_city): ?>
                                                        <div><strong>Ciudad:</strong></div>
                                                        <div><?php echo esc_html($ship_city); ?></div>
                                                    <?php endif; ?>
                                                    <?php if ($ship_state): ?>
                                                        <div><strong>Departamento:</strong></div>
                                                        <div><?php echo esc_html($ship_state); ?></div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <h4>✨ Charms Seleccionados (en orden):</h4>
                                        <div style="background: white; padding: 15px; border: 1px solid #ddd; border-radius: 5px;">
                                            <?php
                                            $charms_array = json_decode(wp_unslash($row->charms_data), true);
                                            $assets_url = plugin_dir_url(__FILE__) . 'assets/';
                                            if (is_array($charms_array) && !empty($charms_array)):
                                                echo '<div style="display: flex; flex-wrap: wrap; gap: 10px;">';
                                                foreach ($charms_array as $i => $charm):
                                                    $color = isset($charm['colorHex']) ? $charm['colorHex'] : '#000';
                                                    $value = isset($charm['value']) ? $charm['value'] : '?';
                                                    $isEmoji = isset($charm['isEmoji']) ? $charm['isEmoji'] : false;
                                                    $isGlitter = isset($charm['isGlitter']) ? $charm['isGlitter'] : false;

                                                    $type_label = '';
                                                    $text_color = '#fff';
                                                    if ($isGlitter && $color === '#GOLD') {
                                                        $type_label = '🌟 DORADO';
                                                        $color_display = 'linear-gradient(135deg, #FFD700, #FFA500)';
                                                        $text_color = '#8B4513';
                                                    } elseif ($isGlitter && $color === '#SILVER') {
                                                        $type_label = '⚪ PLATEADO';
                                                        $color_display = 'linear-gradient(135deg, #E8E8E8, #A9A9A9)';
                                                        $text_color = '#333';
                                                    } elseif ($isEmoji) {
                                                        $type_label = 'Emoji';
                                                        $color_display = '#f0f0f0';
                                                        $text_color = '#333';
                                                    } else {
                                                        $type_label = 'Letra';
                                                        $color_display = $color;
                                                    }

                                                    echo '<div style="border: 2px solid #ddd; padding: 8px 12px; border-radius: 8px; min-width: 80px; text-align: center; background: ' . esc_attr($color_display) . '; color: ' . $text_color . ';">';
                                                    echo '<div style="font-weight: bold; font-size: 11px; opacity: 0.7; margin-bottom: 3px;">#' . ($i + 1) . '</div>';
                                                    if ($isEmoji) {
                                                        $folder = ltrim($color, '#');
                                                        $emoji_url = $assets_url . 'emojis/' . $folder . '/' . $value;
                                                        echo '<img src="' . esc_url($emoji_url) . '" alt="emoji" style="width:48px;height:48px;object-fit:contain;display:block;margin:0 auto;" />';
                                                    } else {
                                                        echo '<div style="font-size: 20px; font-weight: 900;">' . esc_html($value) . '</div>';
                                                    }
                                                    echo '<div style="font-size: 10px; margin-top: 3px;">' . $type_label . '</div>';
                                                    echo '</div>';
                                                endforeach;
                                                echo '</div>';
                                            else:
                                                echo '<p><em>No se pudo decodificar la configuración de charms.</em></p>';
                                                echo '<p><small>Raw data: ' . esc_html(substr($row->charms_data, 0, 200)) . '</small></p>';
                                            endif;
                                            ?>
                                        </div>
                                        
                                        <div style="margin-top: 15px; display: flex; gap: 10px;">
                                            <a href="<?php echo esc_url($wc_order ? $wc_order->get_edit_order_url() : admin_url('post.php?post=' . $row->order_id . '&action=edit')); ?>" class="button button-primary" target="_blank">
                                                Ver pedido en WooCommerce
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Paginación -->
                <?php if ($total_pages > 1): ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <span class="displaying-num"><?php echo $total_items; ?> elementos</span>
                            <span class="pagination-links">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <?php
                                    $page_url = add_query_arg(array(
                                        'page' => 'collar-orders',
                                        'paged' => $i,
                                        's' => $search,
                                        'filter_size' => $filter_size,
                                        'filter_color' => $filter_color,
                                        'filter_status' => $filter_status,
                                        'filter_from' => $filter_from,
                                        'filter_to' => $filter_to,
                                    ), admin_url('admin.php'));
                                    ?>
                                    <?php if ($i == $current_page): ?>
                                        <span class="tablenav-pages-navspan button disabled" aria-hidden="true"><?php echo $i; ?></span>
                                    <?php else: ?>
                                        <a class="button" href="<?php echo esc_url($page_url); ?>"><?php echo $i; ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <style>
            .collar-stats-dashboard {
                display: flex;
                gap: 15px;
                margin: 20px 0;
                flex-wrap: wrap;
            }
            .stat-card {
                background: white;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 20px 25px;
                text-align: center;
                flex: 1;
                min-width: 140px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            }
            .stat-number {
                font-size: 28px;
                font-weight: 700;
                color: #1d2327;
                margin-bottom: 5px;
            }
            .stat-label {
                font-size: 13px;
                color: #646970;
            }
            .collar-filters-bar {
                background: white;
                padding: 18px 20px;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                margin: 18px 0;
                box-shadow: 0 1px 2px rgba(0,0,0,0.04);
            }
            .collar-filter-row {
                display: flex;
                flex-wrap: wrap;
                gap: 14px;
                align-items: flex-end;
            }
            .collar-filter-row + .collar-filter-row {
                margin-top: 14px;
                padding-top: 14px;
                border-top: 1px dashed #e5e7eb;
            }
            .collar-filter-field {
                display: flex;
                flex-direction: column;
                gap: 4px;
                min-width: 140px;
            }
            .collar-filter-label {
                font-size: 11px;
                font-weight: 600;
                color: #6b7280;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .collar-filter-input {
                padding: 7px 10px;
                font-size: 14px;
                border: 1px solid #d1d5db;
                border-radius: 6px;
                background: #fff;
                color: #111827;
                box-shadow: 0 1px 1px rgba(0,0,0,0.02) inset;
                height: 36px;
                box-sizing: border-box;
            }
            .collar-filter-input:focus {
                outline: none;
                border-color: #6366f1;
                box-shadow: 0 0 0 3px rgba(99,102,241,0.15);
            }
            .collar-filter-actions {
                display: flex;
                gap: 8px;
                align-items: center;
                margin-left: auto;
            }
            .collar-filter-actions .button {
                height: 36px;
                line-height: 34px;
                padding: 0 14px;
            }
            .collar-results-count {
                color: #646970;
                margin: 10px 0;
            }
            .order-status {
                padding: 4px 10px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: bold;
                white-space: nowrap;
            }
            .status-completed { background: #c6e1c6; color: #0e7c0e; }
            .status-processing { background: #c8d7e1; color: #2e4453; }
            .status-pending { background: #f8dda7; color: #94660c; }
            .status-on-hold { background: #f8dda7; color: #94660c; }
            .status-cancelled { background: #eba3a3; color: #761919; }
            .status-refunded { background: #eba3a3; color: #761919; }
            .status-failed { background: #eba3a3; color: #761919; }
        </style>
        
        <script>
            function toggleDetails(id) {
                var row = document.getElementById(id);
                if (row.style.display === 'none') {
                    row.style.display = 'table-row';
                } else {
                    row.style.display = 'none';
                }
            }
        </script>
        <?php
    }
    
    /**
     * Sincronizar pedidos existentes de WooCommerce a la tabla personalizada
     */
    private function sync_orders_to_db() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'collar_configurations';
        
        $orders = wc_get_orders(array(
            'limit' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ));
        
        foreach ($orders as $order) {
            $order_id = $order->get_id();
            
            // Verificar si ya existe
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE order_id = %d", $order_id));
            if ($exists) continue;
            
            foreach ($order->get_items() as $item) {
                $charms_config = $item->get_meta('_collar_charms_data');
                if (empty($charms_config)) {
                    $charms_config = $item->get_meta('Configuración de Charms');
                }
                if (empty($charms_config)) continue;

                $summary = $this->build_charms_summary($charms_config);

                $wpdb->insert($table_name, array(
                    'order_id' => $order_id,
                    'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    'customer_email' => $order->get_billing_email(),
                    'customer_phone' => $order->get_billing_phone(),
                    'collar_size' => $item->get_meta('Tamaño') ?: $item->get_meta('Tamaño del Collar'),
                    'collar_color' => $item->get_meta('Color del collar') ?: $item->get_meta('Color') ?: $item->get_meta('Color del Collar'),
                    'charms_count' => intval($item->get_meta('Charms') ?: $item->get_meta('Cantidad de Charms')),
                    'charms_data' => $charms_config,
                    'charms_summary' => $summary,
                    'custom_price' => $item->get_total(),
                    'order_status' => $order->get_status(),
                    'order_total' => $order->get_total(),
                    'created_at' => $order->get_date_created() ? $order->get_date_created()->format('Y-m-d H:i:s') : current_time('mysql')
                ), array('%d','%s','%s','%s','%s','%s','%d','%s','%s','%f','%s','%f','%s'));
            }
        }
    }

    /**
     * Exportar pedidos a CSV
     */
    private function export_orders_csv() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'collar_configurations';
        
        $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=collares-personalizados-' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        
        // BOM para UTF-8 en Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Encabezados
        fputcsv($output, array(
            'Pedido', 'Fecha', 'Estado', 'Cliente', 'Email', 'Teléfono',
            'Tamaño', 'Color', 'Charms', 'Resumen Charms', 'Total'
        ), ';');
        
        foreach ($results as $row) {
            fputcsv($output, array(
                '#' . $row->order_id,
                wp_date('d/m/Y H:i', strtotime($row->created_at)),
                ucfirst($row->order_status),
                $row->customer_name,
                $row->customer_email,
                $row->customer_phone,
                strtoupper($row->collar_size),
                $row->collar_color,
                $row->charms_count,
                $row->charms_summary,
                '$' . number_format($row->order_total, 0, ',', '.')
            ), ';');
        }
        
        fclose($output);
        exit;
    }

    /**
     * Devuelve un nonce fresco para las acciones del configurador. Se llama desde el
     * frontend cuando el nonce impreso en la página (potencialmente cacheada por
     * LiteSpeed) ya expiró y el add-to-cart devuelve 403. admin-ajax no se cachea,
     * por lo que este nonce siempre es válido para la sesión actual.
     */
    public function ajax_refresh_nonce() {
        wp_send_json_success(array('nonce' => wp_create_nonce('collar_config_nonce')));
    }

    /**
     * Handler AJAX para agregar Collar Gummy al carrito
     */
    public function gummy_ajax_add_to_cart() {
        check_ajax_referer('collar_config_nonce', 'nonce');

        $product_id    = intval($_POST['product_id']);
        $size          = strtoupper(sanitize_text_field($_POST['gummy_size']));
        // Flag: cliente indica intención de bundle. El precio real se calcula server-side.
        $bundle_intent = !empty($_POST['bundle_price']);

        if (empty($size) || !in_array($size, self::ALLOWED_SIZES, true)) {
            wp_send_json_error(array('message' => 'Por favor selecciona una talla.'));
            return;
        }

        if (!$product_id || !wc_get_product($product_id)) {
            wp_send_json_error(array('message' => 'Producto no encontrado.'));
            return;
        }

        // Solo se puede agregar por esta vía el Collar Gummy configurado. Evita que el
        // precio del bundle (server-side) se aplique a un producto ajeno enviado en product_id.
        $gummy_product_id = (int) get_option('gummy_product_id', 0);
        if (!$gummy_product_id || $product_id !== $gummy_product_id) {
            wp_send_json_error(array('message' => 'Producto no válido.'));
            return;
        }

        // Inicializar sesión y carrito para usuarios no logueados
        if (WC()->session && !WC()->session->has_session()) {
            WC()->session->set_customer_session_cookie(true);
        }
        if (WC()->cart && did_action('woocommerce_load_cart_from_session') === 0) {
            WC()->cart->get_cart_from_session();
        }

        $cart_meta = array(
            'gummy_size' => $size,
            'unique_key' => md5(microtime() . rand()),
        );

        // Precio bundle SERVER-SIDE: solo aplica si hay un collar personalizado en el carrito.
        if ($bundle_intent) {
            $collar_size_in_cart = null;
            foreach (WC()->cart->get_cart() as $cart_item) {
                if (isset($cart_item['collar_configuration']['size'])) {
                    $collar_size_in_cart = strtoupper($cart_item['collar_configuration']['size']);
                    break;
                }
            }
            if ($collar_size_in_cart && isset(self::BUNDLE_GUMMY_PRICE[$collar_size_in_cart])) {
                $cart_meta['gummy_bundle_price'] = self::BUNDLE_GUMMY_PRICE[$collar_size_in_cart];
            }
            // Si no hay collar en el carrito, no se aplica descuento (cobra precio normal del producto).
        }

        $cart_item_key = WC()->cart->add_to_cart($product_id, 1, 0, array(), $cart_meta);

        if ($cart_item_key) {
            wp_send_json_success(array(
                'message'    => '¡Añadido al carrito!',
                'cart_url'   => wc_get_cart_url(),
                'cart_count' => WC()->cart->get_cart_contents_count(),
            ));
        } else {
            $wc_notices = wc_get_notices('error');
            $error_msg  = 'No se pudo añadir al carrito.';
            if (!empty($wc_notices)) {
                $error_msg = wp_strip_all_tags($wc_notices[0]['notice']);
                wc_clear_notices();
            }
            wp_send_json_error(array('message' => $error_msg));
        }
    }

    /**
     * Generar letras con textura usando Imagick
     */
    public function generate_glitter_letters_imagick($texture_path, $output_dir, $font_path, $chars, $sizes = array(128)) {
        if (!class_exists('Imagick')) return false;

        try {
            foreach ($sizes as $size) {
                foreach ($chars as $c) {
                    $outname = $output_dir . $c . "_" . $size . ".png";

                    // Crear canvas y llenar con textura (tiled)
                    $canvas = new Imagick();
                    $canvas->newImage($size, $size, new ImagickPixel('transparent'));
                    $canvas->setImageFormat('png');

                    // Cargar textura y tilearla
                    if (file_exists($texture_path)) {
                        $tex = new Imagick($texture_path);
                        $tex->scaleImage($size, 0);
                        // Tile by compositing multiple times if needed
                        $canvas->compositeImage($tex, Imagick::COMPOSITE_DEFAULT, 0, 0);
                    }

                    // Crear máscara: fondo negro, texto blanco
                    $mask = new Imagick();
                    $mask->newImage($size, $size, new ImagickPixel('black'));
                    $mask->setImageFormat('png');

                    $draw = new ImagickDraw();
                    if (file_exists($font_path)) {
                        $draw->setFont($font_path);
                    }
                    // Ajustar tamaño de fuente proporcional
                    $font_size = intval($size * 0.75);
                    $draw->setFontSize($font_size);
                    $draw->setFillColor(new ImagickPixel('white'));
                    $draw->setGravity(Imagick::GRAVITY_CENTER);

                    $mask->annotateImage($draw, 0, 0, 0, $c);

                    // Aplicar máscara como alpha (mantener textura sólo donde está la letra)
                    $canvas->compositeImage($mask, Imagick::COMPOSITE_DSTIN, 0, 0);

                    // Guardar
                    $canvas->writeImage($outname);

                    // Liberar
                    $canvas->clear();
                    $mask->clear();
                }
            }

            return true;
        } catch (Exception $e) {
            error_log('Error generando glitter: ' . $e->getMessage());
            return false;
        }
    }
}

// Inicializar
$collar_ajax_instance = new CollarConfiguratorAJAX();

// ============================================================
// Shortcode del Collar Gummy
// Uso: [collar_gummy]
// ============================================================
function collar_gummy_shortcode() {
    $gummy_product_id = (int) get_option('gummy_product_id', 0);
    $ajax_url         = admin_url('admin-ajax.php');
    $nonce            = wp_create_nonce('collar_config_nonce');

    // Obtener imágenes y descripción desde WooCommerce
    $gallery_images = array();
    $product_desc   = '';
    if ($gummy_product_id) {
        $product = wc_get_product($gummy_product_id);
        if ($product) {
            $main_id = $product->get_image_id();
            if ($main_id) {
                $gallery_images[] = wp_get_attachment_image_url($main_id, 'large');
            }
            foreach ($product->get_gallery_image_ids() as $img_id) {
                $url = wp_get_attachment_image_url($img_id, 'large');
                if ($url) $gallery_images[] = $url;
            }
            $product_desc = $product->get_short_description();
        }
    }

    $sizes = array('XS', 'M', 'L', 'XL');

    ob_start();
    ?>
    <style>
    @import url('https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap');
    .gummy-page-wrapper {
        max-width: 920px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 40px;
        padding: 20px 0;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    @media (max-width: 700px) {
        .gummy-page-wrapper { grid-template-columns: 1fr; gap: 24px; }
    }
    .gummy-main-img {
        width: 100%;
        aspect-ratio: 1 / 1;
        object-fit: cover;
        border-radius: 16px;
        border: 1px solid #f0f0f0;
        display: block;
    }
    .gummy-thumbs {
        display: flex;
        gap: 8px;
        margin-top: 10px;
        flex-wrap: wrap;
    }
    .gummy-thumb-btn {
        width: 64px;
        height: 64px;
        border-radius: 8px;
        overflow: hidden;
        border: 2px solid transparent;
        cursor: pointer;
        padding: 0;
        background: none;
        transition: border-color .15s;
    }
    .gummy-thumb-btn.active, .gummy-thumb-btn:hover { border-color: #EB0296; }
    .gummy-thumb-btn img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .gummy-product-title { font-size: 34px; font-family: 'Bebas Neue', sans-serif; font-weight: normal; color: #111827; margin: 0 0 8px; letter-spacing: 1px; }
    .gummy-price-display { font-size: 30px; font-family: 'Bebas Neue', sans-serif; font-weight: normal; color: #EB0296; margin-bottom: 14px; letter-spacing: 0.5px; }
    .gummy-included-tag {
        display: inline-block;
        background: #fdeef8;
        color: #c90278;
        font-size: 12px;
        font-weight: 600;
        padding: 4px 12px;
        border-radius: 20px;
        margin-bottom: 18px;
        border: 1px solid #F3B5D2;
    }
    .gummy-desc { font-size: 14px; color: #6b7280; line-height: 1.7; margin-bottom: 22px; }
    .gummy-size-label { font-size: 15px; font-family: 'Bebas Neue', sans-serif; font-weight: normal; color: #374151; margin-bottom: 10px; display: block; letter-spacing: 0.5px; }
    .gummy-size-options { display: flex; gap: 10px; margin-bottom: 24px; flex-wrap: wrap; }
    .gummy-size-option {
        min-width: 56px;
        padding: 10px 18px;
        border: 2px solid #d1d5db;
        border-radius: 8px;
        background: white;
        font-family: 'Bebas Neue', sans-serif;
        font-size: 18px;
        font-weight: normal;
        letter-spacing: 0.5px;
        color: #374151;
        cursor: pointer;
        transition: border-color .15s, background .15s, color .15s;
    }
    .gummy-size-option:hover { border-color: #FDA303; }
    .gummy-size-option.selected { border-color: #FDA303; background: #FDA303; color: #111; }
    .gummy-add-btn {
        width: 100%;
        padding: 14px 20px;
        background: #c90278;
        color: white;
        border: none;
        border-radius: 10px;
        font-family: 'Bebas Neue', sans-serif;
        font-size: 20px;
        font-weight: normal;
        letter-spacing: 1px;
        cursor: pointer;
        transition: background .2s;
        box-shadow: 0 2px 8px rgba(201, 2, 120, 0.25);
    }
    .gummy-add-btn:hover { background: #a8006a; box-shadow: 0 4px 14px rgba(201, 2, 120, 0.35); }
    .gummy-add-btn:disabled { background: #9ca3af; cursor: not-allowed; box-shadow: none; }
    .gummy-message {
        margin-top: 12px;
        padding: 10px 14px;
        border-radius: 8px;
        font-size: 14px;
        display: none;
    }
    .gummy-message.success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; display: block; }
    .gummy-message.error   { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; display: block; }
    .gummy-no-image {
        width: 100%; aspect-ratio: 1 / 1; background: #f3f4f6; border-radius: 16px;
        display: flex; align-items: center; justify-content: center;
        color: #9ca3af; font-size: 14px; text-align: center; padding: 20px;
    }
    </style>

    <div class="gummy-page-wrapper">

        <!-- Galería -->
        <div class="gummy-gallery-col">
            <?php if (!empty($gallery_images)): ?>
                <img src="<?php echo esc_url($gallery_images[0]); ?>" id="gummy-main-photo" class="gummy-main-img" alt="Collar Gummy" />
                <?php if (count($gallery_images) > 1): ?>
                <div class="gummy-thumbs">
                    <?php foreach ($gallery_images as $i => $img_url): ?>
                        <button class="gummy-thumb-btn <?php echo $i === 0 ? 'active' : ''; ?>"
                                data-src="<?php echo esc_url($img_url); ?>"
                                onclick="gummySetPhoto(this)" type="button">
                            <img src="<?php echo esc_url($img_url); ?>" alt="" />
                        </button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="gummy-no-image">Sin imagen — agrega imágenes al producto en WooCommerce</div>
            <?php endif; ?>
        </div>

        <!-- Info -->
        <div class="gummy-info-col">
            <h1 class="gummy-product-title">Collar Gummy</h1>
            <div class="gummy-price-display">$49.900 COP</div>
            <span class="gummy-included-tag">Charms incluidos</span>

            <?php if (!empty($product_desc)): ?>
                <div class="gummy-desc"><?php echo wp_kses_post($product_desc); ?></div>
            <?php else: ?>
                <p class="gummy-desc">Collar con diseño gummy y charms decorativos incluidos. Disponible en diferentes tallas para tu mascota.</p>
            <?php endif; ?>

            <span class="gummy-size-label">Talla del collar</span>
            <div class="gummy-size-options">
                <?php foreach ($sizes as $sz): ?>
                    <button class="gummy-size-option" data-size="<?php echo esc_attr($sz); ?>" type="button" onclick="gummySelectSize(this)">
                        <?php echo esc_html($sz); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <button class="gummy-add-btn" id="gummy-add-cart-btn" type="button" onclick="gummyAddToCart()">
                Agregar al carrito
            </button>
            <div class="gummy-message" id="gummy-cart-msg"></div>
        </div>
    </div>

    <script>
    (function() {
        var _gPid   = <?php echo (int) $gummy_product_id; ?>;
        var _gNonce = '<?php echo esc_js($nonce); ?>';
        var _gAjax  = '<?php echo esc_js($ajax_url); ?>';
        var _gSize  = '';

        window.gummySetPhoto = function(btn) {
            document.querySelectorAll('.gummy-thumb-btn').forEach(function(b) { b.classList.remove('active'); });
            btn.classList.add('active');
            document.getElementById('gummy-main-photo').src = btn.dataset.src;
        };

        window.gummySelectSize = function(btn) {
            document.querySelectorAll('.gummy-size-option').forEach(function(b) { b.classList.remove('selected'); });
            btn.classList.add('selected');
            _gSize = btn.dataset.size;
        };

        window.gummyAddToCart = function() {
            if (!_gSize) {
                gummyShowMsg('Por favor selecciona una talla.', 'error');
                return;
            }
            if (!_gPid) {
                gummyShowMsg('Producto no configurado. Agrega el ID en los ajustes del plugin.', 'error');
                return;
            }
            var btn = document.getElementById('gummy-add-cart-btn');
            btn.disabled = true;
            btn.textContent = 'Agregando...';

            gummyPost(_gNonce)
                .then(function(resp) {
                    // null = nonce rechazado: se pide uno fresco y se reintenta UNA vez.
                    if (resp !== null) return resp;
                    return gummyRefreshNonce().then(gummyPost);
                })
                .then(function(resp) {
                    if (resp === null) {
                        throw new Error('Tu sesión expiró. Recarga la página e intenta de nuevo.');
                    }
                    if (resp.success) {
                        gummyShowMsg('¡Añadido al carrito! <a href="' + resp.data.cart_url + '" style="color:inherit;font-weight:700;text-decoration:underline;">Ver carrito</a>', 'success');
                        document.querySelectorAll('.cart-contents-count, .count').forEach(function(el) {
                            el.textContent = resp.data.cart_count;
                        });
                    } else {
                        gummyShowMsg((resp.data && resp.data.message) || 'Error al agregar.', 'error');
                    }
                })
                .catch(function(err) {
                    gummyShowMsg(err && err.message ? err.message : 'Error de conexión.', 'error');
                })
                .finally(function() {
                    btn.disabled = false;
                    btn.textContent = 'Agregar al carrito';
                });
        };

        // POST al carrito. Devuelve el JSON, o null si el nonce fue rechazado
        // (403, o cuerpo no-JSON tipo "-1"), que es lo que pasa cuando LiteSpeed
        // sirve esta página cacheada con un nonce ya vencido.
        function gummyPost(nonce) {
            var body = new URLSearchParams({
                action: 'gummy_add_to_cart',
                nonce: nonce,
                product_id: _gPid,
                gummy_size: _gSize
            });

            return fetch(_gAjax, { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function(r) {
                    if (r.status === 403) return null;
                    return r.text().then(function(t) {
                        try { return JSON.parse(t); } catch (e) { return null; }
                    });
                });
        }

        // admin-ajax nunca se cachea, así que de aquí sale un nonce siempre válido.
        function gummyRefreshNonce() {
            return fetch(_gAjax, {
                method: 'POST',
                body: new URLSearchParams({ action: 'collar_refresh_nonce' }),
                credentials: 'same-origin'
            })
                .then(function(r) { return r.json(); })
                .then(function(resp) {
                    if (resp && resp.success && resp.data && resp.data.nonce) {
                        _gNonce = resp.data.nonce;
                        return _gNonce;
                    }
                    throw new Error('No se pudo actualizar la sesión. Recarga la página e intenta de nuevo.');
                });
        }

        function gummyShowMsg(msg, type) {
            var el = document.getElementById('gummy-cart-msg');
            el.innerHTML = msg;
            el.className = 'gummy-message ' + type;
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('collar_gummy', 'collar_gummy_shortcode');

// ============================================================
// Shortcode "Nuestros collares" para HOME
// Uso: [collares_home]
//      [collares_home titulo="Nuestros productos"]
// Muestra los 2 productos (Configurador y Gummy) en cards con imagen,
// título, descripción y botón que lleva a cada página de producto.
// ============================================================
function collares_home_shortcode($atts) {
    $atts = shortcode_atts(array(
        'titulo' => 'Nuestros collares',
    ), $atts);

    $collar_id   = (int) get_option('collar_product_id', 0);
    $gummy_id    = (int) get_option('gummy_product_id', 0);
    $collar_url  = get_option('collar_configurator_page_url', '');
    $gummy_url   = get_option('gummy_page_url', '');

    // Imagen + precio del configurador
    $collar_img = $collar_id ? get_the_post_thumbnail_url($collar_id, 'large') : '';
    if (!$collar_img) {
        $collar_img = 'https://guapolove.com/wp-content/uploads/2026/03/instories_EAEB202C-DC9C-45ED-A08C-42EC52E5FEAF.png';
    }
    // Imagen del Gummy
    $gummy_img = '';
    if ($gummy_id) {
        $gummy_img_id = get_post_thumbnail_id($gummy_id);
        if ($gummy_img_id) {
            $gummy_img = wp_get_attachment_image_url($gummy_img_id, 'large');
        }
    }

    ob_start();
    ?>
    <style>
    @import url('https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap');
    .collares-home-wrap {
        max-width: 1100px;
        margin: 30px auto;
        padding: 0 16px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    .collares-home-title {
        text-align: center;
        font-family: 'Bebas Neue', sans-serif;
        font-size: 38px;
        letter-spacing: 1px;
        color: #c90278;
        margin: 0 0 28px;
        font-weight: normal;
    }
    .collares-home-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 28px;
    }
    @media (max-width: 720px) {
        .collares-home-grid { grid-template-columns: 1fr; gap: 22px; }
        .collares-home-title { font-size: 30px; }
    }
    .collar-card {
        background: #fff;
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 4px 16px rgba(0,0,0,0.06);
        border: 1px solid #f3e3ed;
        display: flex;
        flex-direction: column;
        transition: transform .2s, box-shadow .2s;
    }
    .collar-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 24px rgba(201, 2, 120, 0.15);
    }
    .collar-card-img {
        width: 100%;
        aspect-ratio: 1 / 1;
        object-fit: cover;
        display: block;
        background: #faf3f7;
    }
    .collar-card-body {
        padding: 22px 22px 24px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        flex: 1;
    }
    .collar-card-tag {
        align-self: flex-start;
        background: #fdeef8;
        color: #c90278;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 4px 10px;
        border-radius: 20px;
        border: 1px solid #F3B5D2;
    }
    .collar-card-title {
        font-family: 'Bebas Neue', sans-serif;
        font-size: 26px;
        letter-spacing: 0.5px;
        color: #111827;
        margin: 0;
        font-weight: normal;
        line-height: 1.1;
    }
    .collar-card-desc {
        font-size: 14px;
        color: #6b7280;
        line-height: 1.55;
        margin: 0;
    }
    .collar-card-price {
        font-family: 'Bebas Neue', sans-serif;
        font-size: 22px;
        color: #c90278;
        letter-spacing: 0.5px;
        margin-top: 4px;
    }
    .collar-card-price small {
        font-family: -apple-system, sans-serif;
        font-size: 12px;
        color: #9ca3af;
        font-weight: 500;
        margin-left: 4px;
        letter-spacing: 0;
    }
    .collar-card-btn {
        margin-top: auto;
        display: inline-block;
        background: #FDA303;
        color: #111;
        text-align: center;
        font-family: 'Bebas Neue', sans-serif;
        font-size: 18px;
        letter-spacing: 1px;
        padding: 12px 18px;
        border-radius: 999px;
        text-decoration: none;
        transition: background .2s, transform .2s;
        font-weight: normal;
    }
    .collar-card-btn:hover {
        background: #e89102;
        text-decoration: none;
        color: #111;
        transform: translateY(-1px);
    }
    .collar-card-btn.btn-pink {
        background: #c90278;
        color: #fff;
    }
    .collar-card-btn.btn-pink:hover {
        background: #a8006a;
        color: #fff;
    }
    </style>

    <div class="collares-home-wrap">
        <?php if (!empty($atts['titulo'])): ?>
            <h2 class="collares-home-title"><?php echo esc_html($atts['titulo']); ?></h2>
        <?php endif; ?>

        <div class="collares-home-grid">

            <!-- Card 1: Configurador de collar personalizado -->
            <div class="collar-card">
                <img src="<?php echo esc_url($collar_img); ?>" alt="Collar personalizado" class="collar-card-img" loading="lazy" />
                <div class="collar-card-body">
                    <span class="collar-card-tag">✨ Personalizado</span>
                    <h3 class="collar-card-title">Collar de Charms Personalizado</h3>
                    <p class="collar-card-desc">Diseña un collar único para tu peludo: elige tamaño, color y arma su nombre con charms de letras, números y emojis.</p>
                    <div class="collar-card-price">Desde $69.800 <small>COP</small></div>
                    <a href="<?php echo esc_url($collar_url ?: '#'); ?>" class="collar-card-btn btn-pink">Personalizar ahora</a>
                </div>
            </div>

            <!-- Card 2: Collar Gummy -->
            <div class="collar-card">
                <?php if ($gummy_img): ?>
                    <img src="<?php echo esc_url($gummy_img); ?>" alt="Collar Gummy" class="collar-card-img" loading="lazy" />
                <?php else: ?>
                    <div class="collar-card-img" style="display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:13px;">
                        Configura imagen en WooCommerce
                    </div>
                <?php endif; ?>
                <div class="collar-card-body">
                    <span class="collar-card-tag" style="background:#fef3c7;color:#92400e;border-color:#fde68a;">🍬 Charms incluidos</span>
                    <h3 class="collar-card-title">Collar Gummy</h3>
                    <p class="collar-card-desc">Diseño divertido con charms decorativos ya incluidos. Disponible en varias tallas para tu mascota.</p>
                    <div class="collar-card-price">$49.900 <small>COP</small></div>
                    <a href="<?php echo esc_url($gummy_url ?: '#'); ?>" class="collar-card-btn">Comprar Gummy</a>
                </div>
            </div>

        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('collares_home', 'collares_home_shortcode');

// Evitar que WooCommerce agrupe productos personalizados
add_filter('woocommerce_cart_item_data_to_validate', function($cart_item_data) {
    if (isset($cart_item_data['collar_configuration'])) {
        $cart_item_data['unique_key'] = md5(microtime().rand());
    }
    return $cart_item_data;
});

// Incluir widgets de Elementor si está activo
if (did_action('elementor/loaded')) {
    include_once(plugin_dir_path(__FILE__) . 'elementor-collar-widget.php');
    include_once(plugin_dir_path(__FILE__) . 'elementor-gallery-widget.php');
}