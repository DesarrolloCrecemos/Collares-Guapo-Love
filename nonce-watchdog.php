<?php
/**
 * Vigilante de nonces en páginas cacheadas.
 *
 * LiteSpeed guarda el HTML hasta 7 días, pero un nonce de WordPress muere a las
 * 24 horas. Cuando la copia cacheada sobrevive a su propio nonce, admin-ajax
 * empieza a responder 403 con cuerpo "-1" y el cliente ve un error de red que no
 * tiene nada que ver con su conexión: le pasa a todo el mundo y bloquea la venta.
 *
 * El front ya reintenta con un nonce fresco, así que el fallo se auto-repara. Este
 * vigilante es la segunda capa: revisa las páginas como lo haría un visitante
 * anónimo y, si el nonce servido ya no vale, purga la caché y avisa. Cubre también
 * las páginas o endpoints que se agreguen más adelante.
 *
 * @package CollarConfigurator
 */

if (!defined('ABSPATH')) {
    exit;
}

class CollarNonceWatchdog {

    const HOOK        = 'collar_nonce_watchdog';
    const OPT_STATUS  = 'collar_nonce_watchdog_status';
    const NONCE_ACTION = 'collar_config_nonce';

    public function __construct() {
        add_action('init', array($this, 'schedule'));
        add_action(self::HOOK, array($this, 'run'));
        register_deactivation_hook(
            plugin_dir_path(__FILE__) . 'collar-configurator.php',
            array($this, 'unschedule')
        );
    }

    public function schedule() {
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + 300, 'hourly', self::HOOK);
        }
    }

    public function unschedule() {
        $ts = wp_next_scheduled(self::HOOK);
        if ($ts) {
            wp_unschedule_event($ts, self::HOOK);
        }
    }

    /**
     * Páginas a vigilar: las que llevan nuestros shortcodes. Se detectan solas para
     * que una página nueva quede cubierta sin tener que acordarse de registrarla.
     */
    public function watched_urls() {
        $urls = array();

        $pages = get_posts(array(
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'fields'         => 'ids',
        ));

        foreach ($pages as $id) {
            $content = get_post_field('post_content', $id);
            if (has_shortcode($content, 'collar_gummy') || has_shortcode($content, 'collar_configurador')) {
                $urls[] = get_permalink($id);
            }
        }

        return array_values(array_unique(array_filter($urls)));
    }

    /**
     * Extrae los nonces que imprimimos nosotros. Se buscan los dos patrones
     * concretos del plugin y no cualquier cadena hexadecimal: otros plugins
     * imprimen nonces de acciones distintas y darían falsas alarmas.
     */
    public function extract_nonces($html) {
        $found = array();

        // collarConfig: nonce: 'xxxxxxxxxx'
        // El (?<!\w) es imprescindible: sin el, "ajax_nonce:" de cualquier otro
        // plugin tambien encaja, y el vigilante purgaria la cache y mandaria
        // correos por nonces ajenos que jamas validaran contra nuestra accion.
        if (preg_match_all('/(?<!\w)nonce\s*:\s*[\'"]([a-f0-9]{8,12})[\'"]/i', $html, $m)) {
            $found = array_merge($found, $m[1]);
        }
        // shortcode Gummy: var _gNonce = 'xxxxxxxxxx'
        if (preg_match_all('/_gNonce\s*=\s*[\'"]([a-f0-9]{8,12})[\'"]/i', $html, $m)) {
            $found = array_merge($found, $m[1]);
        }

        return array_values(array_unique($found));
    }

    public function run() {
        $problems = array();
        $checked  = 0;

        foreach ($this->watched_urls() as $url) {
            // Sin cookies y sin cabeceras especiales: se pide igual que un visitante
            // anónimo, que es justo a quien LiteSpeed le entrega la copia cacheada.
            $res = wp_remote_get($url, array(
                'timeout'     => 20,
                'redirection' => 3,
                'sslverify'   => false,
                'headers'     => array('Cache-Control' => ''),
            ));

            if (is_wp_error($res) || wp_remote_retrieve_response_code($res) !== 200) {
                continue;
            }

            $html   = wp_remote_retrieve_body($res);
            $cache  = wp_remote_retrieve_header($res, 'x-litespeed-cache');
            $nonces = $this->extract_nonces($html);

            if (empty($nonces)) {
                continue;
            }

            $checked++;

            foreach ($nonces as $nonce) {
                if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
                    $problems[] = array(
                        'url'   => $url,
                        'nonce' => $nonce,
                        'cache' => $cache ? $cache : 'desconocido',
                    );
                    break;
                }
            }
        }

        $previous = get_option(self::OPT_STATUS, array());
        $was_ok   = empty($previous['problems']);

        update_option(self::OPT_STATUS, array(
            'time'     => current_time('mysql'),
            'checked'  => $checked,
            'problems' => $problems,
        ));

        if (empty($problems)) {
            return;
        }

        // Purgar regenera las páginas con un nonce fresco.
        do_action('litespeed_purge_all');

        // Solo se avisa al pasar de bien a mal: si el problema persiste no tiene
        // sentido mandar un correo cada hora.
        if ($was_ok) {
            $this->notify($problems);
        }
    }

    private function notify($problems) {
        $lines = array(
            'Se detectaron páginas cacheadas con un nonce vencido. La caché ya se purgó automáticamente.',
            '',
            'Efecto si no se corrige: al agregar al carrito, admin-ajax responde 403 y el cliente ve',
            '"Error de conexión". Afecta a todos los visitantes que reciban esa copia cacheada.',
            '',
        );

        foreach ($problems as $p) {
            $lines[] = sprintf('- %s (nonce %s, caché: %s)', $p['url'], $p['nonce'], $p['cache']);
        }

        $lines[] = '';
        $lines[] = 'Revisa en Collares → Panel Interno el estado del vigilante.';

        wp_mail(
            get_option('admin_email'),
            '[' . get_bloginfo('name') . '] Nonce vencido en página cacheada',
            implode("\n", $lines)
        );
    }

    /** Estado legible para mostrar en el admin. */
    public static function status() {
        return get_option(self::OPT_STATUS, array());
    }
}

new CollarNonceWatchdog();
