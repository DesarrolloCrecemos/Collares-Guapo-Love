<?php
/**
 * Plugin Name: Configurador de Collar
 * Description: Configurador interactivo de collares personalizados con WooCommerce
 * Version: 1.0.0
 * Author: Tu Nombre
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Registrar scripts y estilos
function collar_enqueue_assets() {
    // NO hacer nada aquí - solo cargar cuando se use el shortcode
}
add_action('wp_enqueue_scripts', 'collar_enqueue_assets');

// Shortcode del configurador
function collar_configurador_shortcode($atts) {
    $atts = shortcode_atts(array(
        'show_title' => 'true',
        'title' => '🐾 Personaliza tu Collar',
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
            <h2 style="text-align: center; color: #374151; margin-bottom: 20px;">
                <?php echo esc_html($atts['title']); ?>
            </h2>
        <?php endif; ?>
        
        <?php include(plugin_dir_path(__FILE__) . 'configurator-template-wawas.php'); ?>
    </div>
    
    <script type="text/javascript">
    // Variables de configuración para el configurador (GLOBAL)
    if (typeof collarConfig === 'undefined') {
        window.collarConfig = {
            pluginUrl: '<?php echo esc_js($plugin_url); ?>',
            assetsUrl: '<?php echo esc_js($plugin_url . 'assets/'); ?>',
            ajaxUrl: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
            nonce: '<?php echo wp_create_nonce('collar_config_nonce'); ?>'
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
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_save_collar_config', array($this, 'save_collar_config'));
        add_action('wp_ajax_nopriv_save_collar_config', array($this, 'save_collar_config'));
        add_action('wp_ajax_collar_add_to_cart', array($this, 'ajax_add_to_cart'));
        add_action('wp_ajax_nopriv_collar_add_to_cart', array($this, 'ajax_add_to_cart'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Mostrar datos personalizados en el carrito y checkout
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_cart_item_data'), 10, 3);
        add_filter('woocommerce_get_item_data', array($this, 'display_cart_item_data'), 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_order_item_data'), 10, 4);        
        // Hook para modificar el precio del producto en el carrito
        add_action('woocommerce_before_calculate_totals', array($this, 'update_cart_item_price'), 10, 1);
        
        // Guardar configuración en tabla personalizada al completar orden
        add_action('woocommerce_checkout_order_processed', array($this, 'save_collar_to_db'), 10, 3);
        add_action('woocommerce_order_status_changed', array($this, 'save_collar_on_status_change'), 10, 4);
        
        // Crear tabla personalizada al activar
        register_activation_hook(__FILE__, array($this, 'create_collar_orders_table'));
        
        // Crear tabla si no existe (por si se actualiza sin reactivar)
        add_action('admin_init', array($this, 'maybe_create_table'));
    }
    
    public function init() {
        // Registro de funciones necesarias
    }
    
    public function enqueue_scripts() {
        // Solo cargar en la página de charms
        if (is_page('charms') || is_page_template('page-charms.php')) {
            wp_enqueue_script('jquery');
        }
    }
    
    /**
     * Guardar configuración del collar y agregar al carrito
     */
    public function save_collar_config() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'collar_config_nonce')) {
            wp_die('Error de seguridad');
        }
        
        $collar_size = sanitize_text_field($_POST['collar_size']);
        $collar_color = sanitize_text_field($_POST['collar_color']);
        $collar_charms = sanitize_textarea_field($_POST['collar_charms']);
        $product_id = intval($_POST['product_id']);
        
        // Verificar que existe el producto
        if (!$product_id || !wc_get_product($product_id)) {
            wp_send_json_error('Producto no encontrado');
            return;
        }
        
        try {
            // Crear datos personalizados para WooCommerce
            $cart_item_data = array(
                'collar_configuration' => array(
                    'size' => $collar_size,
                    'color' => $collar_color,
                    'charms' => $collar_charms,
                    'configured_at' => current_time('mysql')
                ),
                'unique_key' => md5(microtime().rand()) // Para evitar agrupación
            );
            
            // Agregar al carrito
            $cart_item_key = WC()->cart->add_to_cart(
                $product_id,
                1, // cantidad
                0, // variation_id
                array(), // variation
                $cart_item_data
            );
            
            if ($cart_item_key) {
                wp_send_json_success(array(
                    'message' => 'Collar personalizado agregado al carrito',
                    'cart_item_key' => $cart_item_key,
                    'cart_url' => wc_get_cart_url()
                ));
            } else {
                wp_send_json_error('No se pudo agregar al carrito');
            }
            
        } catch (Exception $e) {
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Handler AJAX para añadir collar personalizado al carrito
     */
    public function ajax_add_to_cart() {
        error_log('=== COLLAR CONFIGURATOR: Add to Cart ===');
        error_log('POST data: ' . print_r($_POST, true));
        
        // Verificar nonce
        check_ajax_referer('collar_config_nonce', 'nonce');
        error_log('✓ Nonce verificado');
        
        // Validar datos
        $product_id = intval($_POST['product_id']);
        $collar_size = sanitize_text_field($_POST['collar_size']);
        $collar_color = sanitize_text_field($_POST['collar_color']);
        $charms_count = intval($_POST['charms_count']);
        $charms_data = sanitize_textarea_field($_POST['charms_data']);
        $custom_price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
        
        error_log('Product ID: ' . $product_id);
        error_log('Charms count: ' . $charms_count);
        error_log('Custom price: ' . $custom_price);
        
        // Verificar que existe el producto y WooCommerce está activo
        if (!function_exists('WC') || !$product_id || !wc_get_product($product_id)) {
            error_log('❌ Producto no encontrado o WooCommerce no activo');
            wp_send_json_error(array('message' => 'Producto no encontrado'));
            return;
        }
        
        error_log('✓ Producto encontrado');
        
        // Validar configuración
        if ($charms_count < 5) {
            error_log('❌ Mínimo 5 charms requeridos');
            wp_send_json_error(array('message' => 'Mínimo 5 charms requeridos'));
            return;
        }
        
        // Límite máximo técnico (por espacio en el collar)
        if ($charms_count > 20) {
            error_log('❌ Máximo 20 charms permitidos');
            wp_send_json_error(array('message' => 'Máximo 20 charms permitidos'));
            return;
        }
        
        error_log('✓ Validación pasada');
        
        try {
            // Preparar datos personalizados
            $cart_item_data = array(
                'collar_configuration' => array(
                    'size' => $collar_size,
                    'color' => $collar_color,
                    'charms_count' => $charms_count,
                    'charms_data' => $charms_data,
                    'custom_price' => $custom_price,
                    'configured_at' => current_time('mysql')
                ),
                'unique_key' => md5(microtime() . rand())
            );
            
            // Agregar al carrito
            error_log('Intentando agregar al carrito...');
            $cart_item_key = WC()->cart->add_to_cart(
                $product_id,
                1, // cantidad
                0, // variation_id
                array(), // variation
                $cart_item_data
            );
            
            if ($cart_item_key) {
                error_log('✓✓✓ PRODUCTO AGREGADO AL CARRITO: ' . $cart_item_key);
                wp_send_json_success(array(
                    'message' => '¡Añadido al carrito!',
                    'cart_url' => wc_get_cart_url(),
                    'cart_count' => WC()->cart->get_cart_contents_count()
                ));
            } else {
                error_log('❌❌❌ NO SE PUDO AGREGAR AL CARRITO');
                wp_send_json_error(array('message' => 'No se pudo añadir al carrito'));
            }
        } catch (Exception $e) {
            error_log('❌ EXCEPCIÓN: ' . $e->getMessage());
            error_log($e->getTraceAsString());
            wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
        }
    }
    
    /**
     * Agregar datos personalizados al item del carrito
     */
    public function add_cart_item_data($cart_item_data, $product_id, $variation_id) {
        if (isset($_POST['collar_configuration'])) {
            $cart_item_data['collar_configuration'] = $_POST['collar_configuration'];
            $cart_item_data['unique_key'] = md5(microtime() . rand());
        }
        return $cart_item_data;
    }
    
    /**
     * Mostrar datos personalizados en el carrito
     */
    public function display_cart_item_data($item_data, $cart_item) {
        if (isset($cart_item['collar_configuration'])) {
            $config = $cart_item['collar_configuration'];
            
            $item_data[] = array(
                'key' => 'Tamaño',
                'value' => ucfirst($config['size'])
            );
            
            $item_data[] = array(
                'key' => 'Color',
                'value' => $config['color']
            );
            
            $item_data[] = array(
                'key' => 'Charms',
                'value' => $config['charms_count'] . ' charms personalizados'
            );
        }
        
        return $item_data;
    }
    
    /**
     * Guardar datos personalizados en la orden
     */
    public function save_order_item_data($item, $cart_item_key, $values, $order) {
        if (isset($values['collar_configuration'])) {
            $config = $values['collar_configuration'];
            
            $item->add_meta_data('Tamaño del Collar', ucfirst($config['size']), true);
            $item->add_meta_data('Color del Collar', $config['color'], true);
            $item->add_meta_data('Cantidad de Charms', $config['charms_count'], true);
            
            if (!empty($config['charms_data'])) {
                $item->add_meta_data('Configuración de Charms', $config['charms_data'], true);
            }
        }
    }
    
    /**
     * Actualizar el precio del producto en el carrito según configuración
     */
    public function update_cart_item_price($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['collar_configuration']['custom_price'])) {
                $custom_price = floatval($cart_item['collar_configuration']['custom_price']);
                
                if ($custom_price > 0) {
                    $cart_item['data']->set_price($custom_price);
                    error_log('Precio personalizado aplicado: ' . $custom_price);
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

        // Guardar ID de producto
        if (isset($_POST['save_product'])) {
            update_option('collar_product_id', intval($_POST['collar_product_id']));
            $messages[] = 'ID de producto guardado.';
        }

        // Manejar subida de texturas / fuente
        if (isset($_POST['upload_textures'])) {
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

        $product_id = get_option('collar_product_id', '');
        ?>
        <div class="wrap">
            <h1>Configurador de Collar - Ajustes</h1>

            <?php foreach($messages as $m) { echo '<div class="notice notice-success"><p>' . esc_html($m) . '</p></div>'; } ?>

            <form method="post" action="" style="margin-bottom:20px;">
                <h2>ID del Producto</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">ID del Producto de Collar</th>
                        <td>
                            <input type="number" name="collar_product_id" value="<?php echo esc_attr($product_id); ?>" class="regular-text" />
                            <p class="description">Ingresa el ID del producto WooCommerce que representa el collar base</p>
                        </td>
                    </tr>
                </table>
                <input type="hidden" name="save_product" value="1" />
                <?php submit_button('Guardar ID de producto'); ?>
            </form>

            <h2>Subir texturas / fuente</h2>
            <form method="post" action="" enctype="multipart/form-data">
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
            $charms_config = $item->get_meta('Configuración de Charms');
            if (empty($charms_config)) continue;
            
            // Generar resumen de charms
            $charms_array = json_decode($charms_config, true);
            $summary = '';
            if (is_array($charms_array)) {
                $values = array_map(function($c) { return isset($c['value']) ? $c['value'] : '?'; }, $charms_array);
                $summary = implode(' ', $values);
            }
            
            $wpdb->insert($table_name, array(
                'order_id' => $order_id,
                'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'customer_email' => $order->get_billing_email(),
                'customer_phone' => $order->get_billing_phone(),
                'collar_size' => $item->get_meta('Tamaño del Collar'),
                'collar_color' => $item->get_meta('Color del Collar'),
                'charms_count' => intval($item->get_meta('Cantidad de Charms')),
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
                $charms_config = $item->get_meta('Configuración de Charms');
                if (empty($charms_config)) continue;
                
                $charms_array = json_decode($charms_config, true);
                $summary = '';
                if (is_array($charms_array)) {
                    $values = array_map(function($c) { return isset($c['value']) ? $c['value'] : '?'; }, $charms_array);
                    $summary = implode(' ', $values);
                }
                
                $wpdb->insert($table_name, array(
                    'order_id' => $order_id,
                    'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    'customer_email' => $order->get_billing_email(),
                    'customer_phone' => $order->get_billing_phone(),
                    'collar_size' => $item->get_meta('Tamaño del Collar'),
                    'collar_color' => $item->get_meta('Color del Collar'),
                    'charms_count' => intval($item->get_meta('Cantidad de Charms')),
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
        
        // Estadísticas generales
        $stats = $wpdb->get_row("SELECT 
            COUNT(*) as total,
            SUM(order_total) as revenue,
            AVG(charms_count) as avg_charms,
            MAX(charms_count) as max_charms
            FROM $table_name");
        
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
                <a href="<?php echo $base_url; ?>&export=csv&s=<?php echo urlencode($search); ?>&filter_size=<?php echo urlencode($filter_size); ?>&filter_color=<?php echo urlencode($filter_color); ?>&filter_status=<?php echo urlencode($filter_status); ?>" class="page-title-action">📥 Exportar CSV</a>
            </h1>
            
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
                
                <div class="filter-group">
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="🔍 Buscar por nombre, email, charms o # pedido..." class="collar-search-input">
                </div>
                
                <div class="filter-group">
                    <select name="filter_size" class="collar-filter-select">
                        <option value="">Todos los tamaños</option>
                        <?php foreach ($sizes_available as $s): ?>
                            <option value="<?php echo esc_attr($s); ?>" <?php selected($filter_size, $s); ?>><?php echo esc_html(strtoupper($s)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <select name="filter_color" class="collar-filter-select">
                        <option value="">Todos los colores</option>
                        <?php foreach ($colors_available as $c): ?>
                            <option value="<?php echo esc_attr($c); ?>" <?php selected($filter_color, $c); ?>><?php echo esc_html($c); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <select name="filter_status" class="collar-filter-select">
                        <option value="">Todos los estados</option>
                        <?php foreach ($statuses_available as $st): ?>
                            <option value="<?php echo esc_attr($st); ?>" <?php selected($filter_status, $st); ?>><?php echo esc_html(ucfirst($st)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="button button-primary">Filtrar</button>
                <a href="<?php echo $base_url; ?>" class="button">Limpiar</a>
            </form>
            
            <p class="collar-results-count">
                Mostrando <strong><?php echo count($results); ?></strong> de <strong><?php echo $total_items; ?></strong> registros
                <?php if (!empty($search) || !empty($filter_size) || !empty($filter_color) || !empty($filter_status)): ?>
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
                            <th>Cliente</th>
                            <th width="60">Tamaño</th>
                            <th width="90">Color</th>
                            <th width="60">Charms</th>
                            <th width="180">Resumen</th>
                            <th width="90">Total</th>
                            <th width="90">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $idx => $row): ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="<?php echo admin_url('post.php?post=' . $row->order_id . '&action=edit'); ?>" target="_blank">
                                            #<?php echo $row->order_id; ?>
                                        </a>
                                    </strong>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($row->created_at)); ?></td>
                                <td>
                                    <span class="order-status status-<?php echo esc_attr($row->order_status); ?>">
                                        <?php echo esc_html(ucfirst($row->order_status)); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($row->customer_name); ?></strong><br>
                                    <small><?php echo esc_html($row->customer_email); ?></small>
                                    <?php if (!empty($row->customer_phone)): ?>
                                        <br><small>📞 <?php echo esc_html($row->customer_phone); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo esc_html(strtoupper($row->collar_size)); ?></strong></td>
                                <td><?php echo esc_html($row->collar_color); ?></td>
                                <td style="text-align: center;"><strong><?php echo $row->charms_count; ?></strong></td>
                                <td>
                                    <code style="font-size: 13px; letter-spacing: 2px;"><?php echo esc_html($row->charms_summary); ?></code>
                                </td>
                                <td><strong><?php echo '$' . number_format($row->order_total, 0, ',', '.'); ?></strong></td>
                                <td>
                                    <button type="button" class="button button-small" onclick="toggleDetails('d<?php echo $row->id; ?>')">
                                        📋 Detalles
                                    </button>
                                </td>
                            </tr>
                            <tr id="d<?php echo $row->id; ?>" style="display: none;">
                                <td colspan="10" style="background: #f9f9f9; padding: 20px;">
                                    <div style="max-width: 1000px;">
                                        <h3 style="margin-top: 0;">🎨 Configuración Completa del Collar</h3>
                                        
                                        <div style="display: grid; grid-template-columns: 180px 1fr 180px 1fr; gap: 8px 15px; margin-bottom: 20px; background: white; padding: 15px; border-radius: 5px;">
                                            <div><strong>📦 Pedido:</strong></div>
                                            <div>#<?php echo $row->order_id; ?></div>
                                            <div><strong>📅 Fecha:</strong></div>
                                            <div><?php echo date('d/m/Y H:i', strtotime($row->created_at)); ?></div>
                                            
                                            <div><strong>👤 Cliente:</strong></div>
                                            <div><?php echo esc_html($row->customer_name); ?></div>
                                            <div><strong>📧 Email:</strong></div>
                                            <div><?php echo esc_html($row->customer_email); ?></div>
                                            
                                            <div><strong>📞 Teléfono:</strong></div>
                                            <div><?php echo esc_html($row->customer_phone ?: 'N/A'); ?></div>
                                            <div><strong>📊 Estado:</strong></div>
                                            <div><span class="order-status status-<?php echo esc_attr($row->order_status); ?>"><?php echo esc_html(ucfirst($row->order_status)); ?></span></div>
                                            
                                            <div><strong>📏 Tamaño:</strong></div>
                                            <div><?php echo esc_html(strtoupper($row->collar_size)); ?></div>
                                            <div><strong>🎨 Color:</strong></div>
                                            <div><?php echo esc_html($row->collar_color); ?></div>
                                            
                                            <div><strong>✨ Charms:</strong></div>
                                            <div><?php echo $row->charms_count; ?> charms</div>
                                            <div><strong>💰 Total:</strong></div>
                                            <div><strong><?php echo '$' . number_format($row->order_total, 0, ',', '.'); ?> COP</strong></div>
                                        </div>
                                        
                                        <h4>✨ Charms Seleccionados (en orden):</h4>
                                        <div style="background: white; padding: 15px; border: 1px solid #ddd; border-radius: 5px;">
                                            <?php
                                            $charms_array = json_decode($row->charms_data, true);
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
                                                        $type_label = '📷 Emoji';
                                                        $color_display = '#f0f0f0';
                                                        $text_color = '#333';
                                                    } else {
                                                        $type_label = 'Letra';
                                                        $color_display = $color;
                                                    }
                                                    
                                                    echo '<div style="border: 2px solid #ddd; padding: 8px 12px; border-radius: 8px; min-width: 80px; text-align: center; background: ' . esc_attr($color_display) . '; color: ' . $text_color . ';">';
                                                    echo '<div style="font-weight: bold; font-size: 11px; opacity: 0.7; margin-bottom: 3px;">#' . ($i + 1) . '</div>';
                                                    echo '<div style="font-size: 20px; font-weight: 900;">' . esc_html($value) . '</div>';
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
                                            <a href="<?php echo admin_url('post.php?post=' . $row->order_id . '&action=edit'); ?>" class="button button-primary" target="_blank">
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
                display: flex;
                gap: 10px;
                align-items: center;
                flex-wrap: wrap;
                background: white;
                padding: 15px;
                border: 1px solid #ddd;
                border-radius: 6px;
                margin: 15px 0;
            }
            .collar-search-input {
                min-width: 300px;
                padding: 6px 12px;
                font-size: 14px;
            }
            .collar-filter-select {
                min-width: 150px;
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
                $charms_config = $item->get_meta('Configuración de Charms');
                if (empty($charms_config)) continue;
                
                $charms_array = json_decode($charms_config, true);
                $summary = '';
                if (is_array($charms_array)) {
                    $values = array_map(function($c) { return isset($c['value']) ? $c['value'] : '?'; }, $charms_array);
                    $summary = implode(' ', $values);
                }
                
                $wpdb->insert($table_name, array(
                    'order_id' => $order_id,
                    'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    'customer_email' => $order->get_billing_email(),
                    'customer_phone' => $order->get_billing_phone(),
                    'collar_size' => $item->get_meta('Tamaño del Collar'),
                    'collar_color' => $item->get_meta('Color del Collar'),
                    'charms_count' => intval($item->get_meta('Cantidad de Charms')),
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
                date('d/m/Y H:i', strtotime($row->created_at)),
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