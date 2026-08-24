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
    const OPT_NOTIFY   = 'collar_nonce_watchdog_notify';

    /**
     * Ejecuciones seguidas con el problema antes de avisar.
     *
     * La purga de LiteSpeed no surte efecto dentro de la misma petición, así que
     * comprobar justo después daba falsos negativos y disparaba un correo cada
     * hora. En vez de reintentar en caliente se mira si el problema sigue en las
     * siguientes pasadas: si se arregló solo, nadie se entera.
     */
    const ALERT_AFTER  = 3;
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
            // Sin cookies ni cabeceras especiales: se pide igual que un visitante
            // anónimo, que es a quien LiteSpeed le entrega la copia cacheada.
            $res = wp_remote_get($url, array(
                'timeout'     => 20,
                'redirection' => 3,
                'sslverify'   => false,
            ));

            if (is_wp_error($res) || wp_remote_retrieve_response_code($res) !== 200) {
                continue;
            }

            $nonces = $this->extract_nonces(wp_remote_retrieve_body($res));
            if (empty($nonces)) {
                continue;
            }

            $checked++;

            foreach ($nonces as $nonce) {
                if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
                    $problems[] = array(
                        'url'   => $url,
                        'nonce' => $nonce,
                        'cache' => wp_remote_retrieve_header($res, 'x-litespeed-cache') ?: 'desconocido',
                    );
                    break;
                }
            }
        }

        $prev    = get_option(self::OPT_STATUS, array());
        $history = isset($prev['history']) && is_array($prev['history']) ? $prev['history'] : array();
        $purges  = isset($prev['purges']) ? (int) $prev['purges'] : 0;

        if (empty($problems)) {
            // Se resolvió (solo o tras la purga anterior). Silencio: no hubo impacto
            // para nadie, así que no hay nada que contarle a una persona.
            if (!empty($prev['problems'])) {
                $history[] = array(
                    'time'   => current_time('mysql'),
                    'evento' => 'resuelto',
                    'urls'   => count($prev['problems']),
                );
            }

            update_option(self::OPT_STATUS, array(
                'time'     => current_time('mysql'),
                'checked'  => $checked,
                'problems' => array(),
                'streak'   => 0,
                'purges'   => $purges,
                'notified' => false,
                'history'  => array_slice($history, -10),
            ));
            return;
        }

        $streak = (isset($prev['streak']) ? (int) $prev['streak'] : 0) + 1;

        // Purga solo las páginas afectadas. purge_all() vaciaba la caché del sitio
        // entero cada vez: para un problema sin impacto en el cliente, el remedio
        // salía más caro que la enfermedad.
        $this->purge_urls(wp_list_pluck($problems, 'url'));
        $purges++;

        $history[] = array(
            'time'   => current_time('mysql'),
            'evento' => 'purga',
            'urls'   => count($problems),
        );

        // Solo se avisa si persiste varias pasadas Y los correos están activados.
        // Por defecto están apagados: esto se auto-repara y no bloquea ninguna venta.
        $notified = isset($prev['notified']) ? (bool) $prev['notified'] : false;

        if (!$notified && $streak >= self::ALERT_AFTER && $this->notifications_enabled()) {
            $this->notify($problems, $streak);
            $notified = true;
        }

        update_option(self::OPT_STATUS, array(
            'time'     => current_time('mysql'),
            'checked'  => $checked,
            'problems' => $problems,
            'streak'   => $streak,
            'purges'   => $purges,
            'notified' => $notified,
            'history'  => array_slice($history, -10),
        ));
    }

    /** Los avisos por correo están desactivados salvo que se pidan expresamente. */
    public function notifications_enabled() {
        return get_option(self::OPT_NOTIFY, '') === '1';
    }

    /** Purga solo las URLs indicadas, sin tocar el resto de la caché del sitio. */
    private function purge_urls($urls) {
        if (!class_exists('LiteSpeed\Purge')) {
            return false;
        }

        foreach (array_unique($urls) as $url) {
            if (method_exists('LiteSpeed\Purge', 'purge_url')) {
                LiteSpeed\Purge::purge_url($url);
            }
        }

        return true;
    }

    private function notify($problems, $streak) {
        $lines = array(
            'El vigilante lleva varias horas detectando páginas cacheadas con un nonce vencido',
            'sin conseguir resolverlo.',
            '',
        );

        $lines[] = sprintf('Lleva %d revisiones seguidas sin resolverse pese a purgar las páginas.', $streak);

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
