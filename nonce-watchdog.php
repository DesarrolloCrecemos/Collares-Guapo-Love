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

    const HOOK         = 'collar_nonce_watchdog';
    const OPT_STATUS   = 'collar_nonce_watchdog_status';
    const OPT_EMAIL    = 'collar_nonce_watchdog_email';
    const NONCE_ACTION = 'collar_config_nonce';

    /**
     * Destinatario por defecto de los avisos.
     *
     * NO se usa admin_email a propósito: ese es el buzón del cliente, y un correo
     * técnico sobre nonces y caché solo genera alarma en quien no puede actuar.
     * Esto es información para quien mantiene el sitio.
     */
    const DEFAULT_EMAIL = 'desarrollo@crecemoslab.com';

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

        update_option(self::OPT_STATUS, array(
            'time'     => current_time('mysql'),
            'checked'  => $checked,
            'problems' => $problems,
        ));

        if (empty($problems)) {
            return;
        }

        $purged   = $this->purge_cache();
        $resolved = $purged ? $this->recheck($problems) : false;

        update_option(self::OPT_STATUS, array(
            'time'     => current_time('mysql'),
            'checked'  => $checked,
            'problems' => $resolved ? array() : $problems,
            'last_fix' => current_time('mysql'),
            'fixes'    => (int) (isset($previous['fixes']) ? $previous['fixes'] : 0) + ($resolved ? 1 : 0),
            'resolved' => $resolved,
        ));

        // Solo se avisa si la purga NO resolvió. Que un nonce caduque es rutina:
        // el tick dura 12 h y la caché hasta 7 días, así que pasa a diario y el
        // front ya reintenta solo. Avisar de cada caso sería ruido garantizado.
        // Lo que sí merece una persona mirando es que el arreglo no funcione.
        if (!$resolved) {
            $this->notify($problems, $purged);
        }
    }

    /**
     * Purga la caché de LiteSpeed.
     *
     * Se usa el método estático y no do_action('litespeed_purge_all'): el hook
     * informa de éxito pero deja páginas en "hit" con objetos de horas —
     * comprobado en producción—, mientras que Purge::purge_all() sí deja la
     * caché en "miss".
     */
    private function purge_cache() {
        if (class_exists('LiteSpeed\Purge') && method_exists('LiteSpeed\Purge', 'purge_all')) {
            LiteSpeed\Purge::purge_all('collar nonce watchdog');
            return true;
        }

        if (has_action('litespeed_purge_all')) {
            do_action('litespeed_purge_all');
            return true;
        }

        return false;
    }

    /**
     * Vuelve a pedir las páginas para confirmar que la purga surtió efecto. Sin
     * esto el aviso afirmaría haber arreglado algo sin comprobarlo.
     */
    private function recheck($problems) {
        foreach ($problems as $p) {
            $res = wp_remote_get($p['url'], array(
                'timeout'   => 20,
                'sslverify' => false,
            ));

            if (is_wp_error($res) || wp_remote_retrieve_response_code($res) !== 200) {
                return false;
            }

            foreach ($this->extract_nonces(wp_remote_retrieve_body($res)) as $nonce) {
                if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function notify($problems, $purged) {
        $lines = array(
            'El vigilante detectó páginas cacheadas con un nonce vencido y NO pudo repararlo solo.',
            '',
        );

        $lines[] = $purged
            ? 'Se purgó la caché, pero al volver a revisar las páginas el nonce seguía vencido.'
            : 'No se pudo purgar la caché: LiteSpeed no está disponible o cambió su API.';

        $lines[] = '';
        $lines[] = 'Impacto para el cliente: ninguno inmediato. El configurador pide un nonce fresco';
        $lines[] = 'y reintenta solo, así que la compra funciona; solo cuesta una petición extra.';
        $lines[] = '';
        $lines[] = 'Páginas afectadas:';

        foreach ($problems as $p) {
            $lines[] = sprintf('  - %s (nonce %s, caché: %s)', $p['url'], $p['nonce'], $p['cache']);
        }

        $lines[] = '';
        $lines[] = 'Purga manual: wp litespeed-purge all';
        $lines[] = 'Estado del vigilante: Collares → Panel Interno.';

        wp_mail(
            $this->notify_email(),
            '[' . get_bloginfo('name') . '] El vigilante de nonces no pudo repararse solo',
            implode("
", $lines)
        );
    }

    /**
     * Destinatario de los avisos. Nunca admin_email: ese buzón es del cliente y
     * este contenido es técnico.
     */
    public function notify_email() {
        $email = get_option(self::OPT_EMAIL, '');

        if (!is_email($email)) {
            return self::DEFAULT_EMAIL;
        }

        // Guarda dura: aunque alguien escriba el buzón del cliente en los ajustes,
        // estos avisos no van ahí. Son técnicos y solo generan alarma en quien no
        // puede actuar sobre ellos. Los correos de pedidos de WooCommerce siguen
        // llegándole con normalidad: eso es otro camino y no se toca.
        $blocked = array_filter(array_map('strtolower', array(
            get_option('admin_email'),
            get_bloginfo('admin_email'),
        )));

        if (in_array(strtolower($email), $blocked, true)) {
            return self::DEFAULT_EMAIL;
        }

        return $email;
    }

    /** Estado legible para mostrar en el admin. */
    public static function status() {
        return get_option(self::OPT_STATUS, array());
    }
}

new CollarNonceWatchdog();
