<?php
/**
 * Líneas de producto con variantes — prendedores, pañoletas, ...
 *
 * Cada línea es un producto VARIABLE de WooCommerce (una variación por referencia o
 * diseño) mostrado con una página propia al estilo del Collar Gummy: foto grande,
 * selector visual de variantes y "Agregar al carrito" por AJAX.
 *
 * Precio, stock, fotos y nombres viven en WooCommerce, no en este archivo: el equipo
 * los edita desde el admin sin tocar código. Este módulo solo los presenta y valida
 * que lo que llega al carrito sea una variación real, comprable y con stock.
 *
 * Shortcode: [guapo_linea linea="prendedores"]
 * Las líneas se registran en la opción OPT_LINEAS (slug => ID de producto) y su página
 * en OPT_PAGES (slug => ID de página). Las deja configuradas el script de carga.
 *
 * @package CollarConfigurator
 */

if (!defined('ABSPATH')) {
    exit;
}

class CollarLineasTienda {

    /** slug de línea => ID del producto variable. */
    const OPT_LINEAS = 'guapo_lineas';

    /** slug de línea => ID de la página que lleva el shortcode. */
    const OPT_PAGES = 'guapo_lineas_pages';

    /** Meta de variación con la familia (p. ej. "Bordada" / "Seda") para agrupar el selector. */
    const META_FAMILIA = '_guapo_familia';

    /** Mismo nonce que el configurador: así sirven el refresco y el vigilante que ya existen. */
    const NONCE_ACTION = 'collar_config_nonce';

    public function __construct() {
        add_shortcode('guapo_linea', array($this, 'shortcode'));

        add_action('wp_ajax_guapo_linea_add_to_cart',        array($this, 'ajax_add_to_cart'));
        add_action('wp_ajax_nopriv_guapo_linea_add_to_cart', array($this, 'ajax_add_to_cart'));

        // La ficha estándar de WooCommerce no conoce el selector visual: se manda a la página propia.
        add_action('template_redirect', array($this, 'redirect_product_page'));

        // La página se cachea en LiteSpeed con el stock pintado. Al venderse una pieza
        // única hay que purgarla, o seguiría mostrándose disponible hasta que expire.
        add_action('woocommerce_variation_set_stock',        array($this, 'purge_on_stock_change'));
        add_action('woocommerce_variation_set_stock_status', array($this, 'purge_on_stock_change'));
        add_action('woocommerce_update_product',             array($this, 'purge_on_stock_change'));
    }

    /* ---------------------------------------------------------------------
     * Registro de líneas
     * ------------------------------------------------------------------ */

    private function lineas() {
        $lineas = get_option(self::OPT_LINEAS, array());
        return is_array($lineas) ? array_map('intval', $lineas) : array();
    }

    /** slug de la línea a la que pertenece un producto o variación, o null. */
    private function linea_of($product_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return null;
        }
        $parent_id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
        $slug = array_search((int) $parent_id, $this->lineas(), true);
        return $slug === false ? null : $slug;
    }

    private function page_url($slug) {
        $pages = get_option(self::OPT_PAGES, array());
        return !empty($pages[$slug]) ? get_permalink((int) $pages[$slug]) : '';
    }

    public function redirect_product_page() {
        if (!is_singular('product')) {
            return;
        }
        $slug = $this->linea_of(get_the_ID());
        $url  = $slug ? $this->page_url($slug) : '';
        if ($url) {
            wp_safe_redirect($url, 302);
            exit;
        }
    }

    public function purge_on_stock_change($product) {
        $id   = is_object($product) ? $product->get_id() : (int) $product;
        $slug = $this->linea_of($id);
        if (!$slug) {
            return;
        }
        $pages = get_option(self::OPT_PAGES, array());
        if (!empty($pages[$slug])) {
            do_action('litespeed_purge_post', (int) $pages[$slug]);
        }
    }

    /* ---------------------------------------------------------------------
     * Datos de las variaciones
     * ------------------------------------------------------------------ */

    private static function format_price($amount) {
        return '$' . number_format((float) $amount, 0, ',', '.');
    }

    /**
     * Variaciones listas para pintar. Se ordenan como quedaron en el producto
     * (menu_order), que es el orden del catálogo.
     */
    private function variants(WC_Product_Variable $product) {
        $attr_key = '';
        foreach ($product->get_attributes() as $key => $attribute) {
            if ($attribute->get_variation()) {
                $attr_key = $key;
                break;
            }
        }

        $out = array();
        foreach ($product->get_children() as $child_id) {
            $v = wc_get_product($child_id);
            if (!$v || !$v->exists() || $v->get_status() !== 'publish') {
                continue;
            }

            $attrs = $v->get_attributes();
            $label = isset($attrs[$attr_key]) ? $attrs[$attr_key] : $v->get_name();
            $price = $v->get_price();
            $img   = $v->get_image_id() ?: $product->get_image_id();

            $out[] = array(
                'id'        => $v->get_id(),
                'label'     => $label,
                'slug'      => sanitize_title($label),
                'family'    => (string) $v->get_meta(self::META_FAMILIA),
                // Descripción de la variación (referencia, medidas): se muestra bajo el precio.
                'note'      => trim(wp_strip_all_tags($v->get_description())),
                'price'     => $price === '' ? null : (float) $price,
                'priceText' => $price === '' ? '' : self::format_price($price),
                'available' => $v->is_purchasable() && $v->is_in_stock() && $price !== '',
                'soldOut'   => !$v->is_in_stock(),
                'image'     => $img ? wp_get_attachment_image_url($img, 'large') : '',
                'thumb'     => $img ? wp_get_attachment_image_url($img, 'medium') : '',
            );
        }
        return $out;
    }

    /* ---------------------------------------------------------------------
     * Shortcode
     * ------------------------------------------------------------------ */

    public function shortcode($atts) {
        $atts   = shortcode_atts(array('linea' => ''), $atts);
        $slug   = sanitize_key($atts['linea']);
        $lineas = $this->lineas();

        if (empty($lineas[$slug])) {
            return current_user_can('manage_options')
                ? '<p>[guapo_linea] Línea "' . esc_html($slug) . '" no configurada.</p>'
                : '';
        }

        $product = wc_get_product($lineas[$slug]);
        if (!$product || !$product->is_type('variable')) {
            return '';
        }

        $variants = $this->variants($product);
        if (!$variants) {
            return '';
        }

        $gallery = array();
        foreach ($product->get_gallery_image_ids() as $img_id) {
            $large = wp_get_attachment_image_url($img_id, 'large');
            if ($large) {
                $gallery[] = array('image' => $large, 'thumb' => wp_get_attachment_image_url($img_id, 'medium'));
            }
        }

        $attr_label = '';
        foreach ($product->get_attributes() as $attribute) {
            if ($attribute->get_variation()) {
                $attr_label = wc_attribute_label($attribute->get_name());
                break;
            }
        }

        // Familias en orden de aparición; si solo hay una (o ninguna) no se muestran encabezados.
        $families = array_values(array_unique(array_map(function ($v) { return $v['family']; }, $variants)));
        $grouped  = count($families) > 1;

        $prices = array_filter(array_map(function ($v) { return $v['price']; }, $variants), 'is_numeric');
        $from   = $prices ? (min($prices) === max($prices) ? self::format_price(min($prices)) : 'Desde ' . self::format_price(min($prices))) : '';

        $config = array(
            'ajax'      => admin_url('admin-ajax.php'),
            'productId' => $product->get_id(),
            'variants'  => $variants,
            'fromPrice' => $from,
        );

        $uid = 'gl-' . $slug;

        ob_start();
        ?>
        <style>
        @import url('https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap');
        /* minmax(0, ...) y min-width: 0 son imprescindibles: sin ellos la columna crece
           hasta el ancho de la fila de miniaturas y empuja todo fuera de pantalla. */
        .gl-wrap {
            box-sizing: border-box; width: 100%; max-width: 980px; margin: 0 auto; padding: 24px 16px;
            display: grid; grid-template-columns: minmax(0, 440px) minmax(0, 1fr); gap: 48px;
            align-items: start; justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .gl-wrap *, .gl-wrap *::before, .gl-wrap *::after { box-sizing: border-box; }
        .gl-gallery, .gl-info { min-width: 0; }
        @media (max-width: 760px) { .gl-wrap { grid-template-columns: minmax(0, 1fr); gap: 20px; padding: 8px 16px 24px; } }

        .gl-gallery { position: sticky; top: 20px; }
        @media (max-width: 760px) { .gl-gallery { position: static; } }
        .gl-main {
            position: relative; width: 100%; max-width: 440px; margin: 0 auto; aspect-ratio: 1 / 1;
            border-radius: 16px; overflow: hidden; background: #faf3f7; border: 1px solid #f3e3ed;
        }
        @media (max-width: 760px) { .gl-main { max-width: 340px; } }
        .gl-main img { width: 100%; height: 100%; object-fit: contain; display: block; }
        .gl-main img.is-photo { object-fit: cover; }
        .gl-main-badge {
            position: absolute; top: 14px; left: 14px; padding: 4px 12px; border-radius: 20px;
            background: #111827; color: #fff; font-size: 12px; font-weight: 700; letter-spacing: .5px;
            text-transform: uppercase; display: none;
        }
        .gl-main.is-soldout .gl-main-badge { display: block; }
        .gl-main.is-soldout img { opacity: .55; }
        .gl-thumbs { display: flex; gap: 8px; margin-top: 10px; overflow-x: auto; padding-bottom: 4px; max-width: 100%; }
        .gl-thumb {
            flex: 0 0 56px; width: 56px; height: 56px; padding: 0; border-radius: 8px; overflow: hidden;
            border: 2px solid transparent; background: #faf3f7; cursor: pointer; transition: border-color .15s;
        }
        .gl-thumb.active, .gl-thumb:hover { border-color: #EB0296; }
        .gl-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }

        .gl-title { font-family: 'Bebas Neue', sans-serif; font-weight: normal; font-size: 36px; letter-spacing: 1px; line-height: 1.05; color: #111827; margin: 0 0 6px; }
        .gl-price { font-family: 'Bebas Neue', sans-serif; font-size: 30px; letter-spacing: .5px; color: #EB0296; margin-bottom: 12px; min-height: 36px; }
        .gl-price small { font-family: -apple-system, sans-serif; font-size: 12px; color: #9ca3af; font-weight: 500; letter-spacing: 0; margin-left: 4px; }
        .gl-note { font-size: 13px; font-weight: 600; color: #6b7280; margin: -6px 0 14px; }
        .gl-note:empty { display: none; }
        .gl-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 16px; }
        .gl-tag {
            display: inline-block; background: #fdeef8; color: #c90278; font-size: 12px; font-weight: 600;
            padding: 4px 12px; border-radius: 20px; border: 1px solid #F3B5D2;
        }
        .gl-desc { font-size: 14px; color: #6b7280; line-height: 1.7; margin-bottom: 22px; }
        .gl-desc p { margin: 0 0 10px; }

        .gl-pick-label { display: flex; justify-content: space-between; align-items: baseline; gap: 10px; margin-bottom: 10px; }
        .gl-pick-label span { font-family: 'Bebas Neue', sans-serif; font-size: 17px; letter-spacing: .5px; color: #374151; }
        .gl-pick-label strong { font-size: 14px; font-weight: 600; color: #c90278; text-align: right; }
        .gl-family { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: #9ca3af; margin: 14px 0 8px; }
        .gl-family:first-of-type { margin-top: 0; }
        .gl-swatches { display: grid; grid-template-columns: repeat(auto-fill, minmax(72px, 1fr)); gap: 8px; margin-bottom: 8px; }
        @media (max-width: 760px) { .gl-swatches { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        .gl-swatch {
            position: relative; display: flex; flex-direction: column; align-items: stretch; padding: 4px 4px 6px;
            border: 2px solid #ece4ea; border-radius: 10px; background: #fff; cursor: pointer;
            font: inherit; color: #374151; transition: border-color .15s, box-shadow .15s, transform .15s;
        }
        .gl-swatch:hover { border-color: #FDA303; }
        .gl-swatch.selected { border-color: #FDA303; box-shadow: 0 0 0 2px #FDA303 inset; }
        .gl-swatch:focus-visible { outline: 3px solid #c90278; outline-offset: 2px; }
        .gl-swatch img { width: 100%; aspect-ratio: 4 / 5; object-fit: contain; display: block; background: #faf3f7; border-radius: 6px; }
        /* El tema pone white-space: nowrap a los botones; aquí el nombre debe poder partirse. */
        .gl-swatch-name { margin-top: 4px; font-size: 11px; font-weight: 600; line-height: 1.2; text-align: center; white-space: normal; overflow-wrap: break-word; }
        .gl-swatch.is-soldout img { opacity: .4; filter: grayscale(.6); }
        .gl-swatch.is-soldout .gl-swatch-name { color: #9ca3af; text-decoration: line-through; }
        .gl-swatch.is-soldout::after {
            content: 'Agotado'; position: absolute; top: 34%; left: 50%; transform: translate(-50%, -50%) rotate(-12deg);
            background: #111827; color: #fff; font-size: 10px; font-weight: 700; letter-spacing: .4px;
            text-transform: uppercase; padding: 2px 6px; border-radius: 4px; white-space: nowrap;
        }

        .gl-add {
            width: 100%; margin-top: 18px; padding: 14px 20px; border: none; border-radius: 10px; cursor: pointer;
            background: #c90278; color: #fff; font-family: 'Bebas Neue', sans-serif; font-size: 20px; letter-spacing: 1px;
            box-shadow: 0 2px 8px rgba(201, 2, 120, 0.25); transition: background .2s, box-shadow .2s;
        }
        .gl-add:hover { background: #a8006a; box-shadow: 0 4px 14px rgba(201, 2, 120, 0.35); }
        .gl-add:disabled { background: #9ca3af; cursor: not-allowed; box-shadow: none; }
        .gl-msg { margin-top: 12px; padding: 10px 14px; border-radius: 8px; font-size: 14px; display: none; }
        .gl-msg.success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; display: block; }
        .gl-msg.error   { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; display: block; }
        .gl-msg a { color: inherit; font-weight: 700; text-decoration: underline; }
        </style>

        <div class="gl-wrap" id="<?php echo esc_attr($uid); ?>">
            <div class="gl-gallery">
                <div class="gl-main" data-gl="main">
                    <span class="gl-main-badge">Agotado</span>
                    <img data-gl="main-img" src="<?php echo esc_url($variants[0]['image']); ?>" alt="<?php echo esc_attr($product->get_name()); ?>" />
                </div>
                <?php if ($gallery): ?>
                <div class="gl-thumbs" aria-label="Fotos de ambiente">
                    <button type="button" class="gl-thumb active" data-gl="thumb-variant" aria-label="Ver la pieza seleccionada">
                        <img src="<?php echo esc_url($variants[0]['thumb']); ?>" alt="" />
                    </button>
                    <?php foreach ($gallery as $g): ?>
                        <button type="button" class="gl-thumb" data-gl="thumb" data-src="<?php echo esc_url($g['image']); ?>" aria-label="Ver foto de ambiente">
                            <img src="<?php echo esc_url($g['thumb']); ?>" alt="" loading="lazy" />
                        </button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="gl-info">
                <h1 class="gl-title"><?php echo esc_html($product->get_name()); ?></h1>
                <div class="gl-price" data-gl="price"><?php echo esc_html($from); ?> <small>COP</small></div>
                <div class="gl-note" data-gl="note"></div>

                <?php
                $tags = array_filter(array_map('trim', explode('|', (string) $product->get_meta('_guapo_tags'))));
                if ($tags): ?>
                    <div class="gl-tags">
                        <?php foreach ($tags as $tag): ?><span class="gl-tag"><?php echo esc_html($tag); ?></span><?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($product->get_short_description()): ?>
                    <div class="gl-desc"><?php echo wp_kses_post(wpautop($product->get_short_description())); ?></div>
                <?php endif; ?>

                <div class="gl-pick-label">
                    <span>Elige <?php echo esc_html(mb_strtolower($attr_label)); ?></span>
                    <strong data-gl="picked"></strong>
                </div>

                <?php
                $current_family = null;
                foreach ($variants as $i => $v):
                    if ($grouped && $v['family'] !== $current_family):
                        if ($current_family !== null) echo '</div>';
                        $current_family = $v['family']; ?>
                        <div class="gl-family"><?php echo esc_html($current_family); ?></div>
                        <div class="gl-swatches" role="radiogroup" aria-label="<?php echo esc_attr($attr_label . ' ' . $current_family); ?>">
                    <?php elseif ($i === 0 && !$grouped): ?>
                        <div class="gl-swatches" role="radiogroup" aria-label="<?php echo esc_attr($attr_label); ?>">
                    <?php endif; ?>
                    <button type="button" role="radio" aria-checked="false"
                            class="gl-swatch<?php echo $v['soldOut'] ? ' is-soldout' : ''; ?>"
                            data-gl="swatch" data-index="<?php echo (int) $i; ?>"
                            aria-label="<?php echo esc_attr($v['label'] . ($v['soldOut'] ? ' (agotado)' : '')); ?>">
                        <img src="<?php echo esc_url($v['thumb']); ?>" alt="" loading="lazy" />
                        <span class="gl-swatch-name"><?php echo esc_html($v['label']); ?></span>
                    </button>
                <?php endforeach; ?>
                </div>

                <button type="button" class="gl-add" data-gl="add">Agregar al carrito</button>
                <div class="gl-msg" data-gl="msg" role="status" aria-live="polite"></div>
            </div>
        </div>

        <script>
        (function () {
            var root = document.getElementById(<?php echo wp_json_encode($uid); ?>);
            if (!root) { return; }
            var cfg = <?php echo wp_json_encode($config); ?>;
            // Nonce aparte (y con esta forma) para que el vigilante de nonces lo reconozca.
            var state = { nonce: '<?php echo esc_js(wp_create_nonce(self::NONCE_ACTION)); ?>', index: -1, busy: false };

            var $ = function (sel) { return root.querySelector('[data-gl="' + sel + '"]'); };
            var main = $('main'), mainImg = $('main-img'), price = $('price'), picked = $('picked'), note = $('note'),
                addBtn = $('add'), msg = $('msg'), thumbVariant = $('thumb-variant');
            var swatches = root.querySelectorAll('[data-gl="swatch"]');
            var thumbs = root.querySelectorAll('.gl-thumb');

            function setActiveThumb(btn) {
                thumbs.forEach(function (t) { t.classList.toggle('active', t === btn); });
            }

            function showMsg(html, type) {
                msg.innerHTML = html;
                msg.className = 'gl-msg' + (type ? ' ' + type : '');
            }

            function select(i, fromUser) {
                var v = cfg.variants[i];
                if (!v) { return; }
                state.index = i;
                swatches.forEach(function (s) {
                    var on = +s.getAttribute('data-index') === i;
                    s.classList.toggle('selected', on);
                    s.setAttribute('aria-checked', on ? 'true' : 'false');
                });
                mainImg.src = v.image;
                mainImg.classList.remove('is-photo');
                mainImg.alt = v.label;
                main.classList.toggle('is-soldout', v.soldOut);
                if (thumbVariant) { thumbVariant.querySelector('img').src = v.thumb; setActiveThumb(thumbVariant); }
                picked.textContent = v.label;
                note.textContent = v.note || '';
                price.innerHTML = (v.priceText || 'Precio próximamente') + (v.priceText ? ' <small>COP</small>' : '');
                addBtn.disabled = !v.available;
                addBtn.textContent = v.soldOut ? 'Agotado' : (v.available ? 'Agregar al carrito' : 'No disponible');
                if (fromUser) { showMsg('', ''); }
                if (fromUser && history.replaceState) {
                    var url = new URL(location.href);
                    url.searchParams.set('ref', v.slug);
                    history.replaceState(null, '', url);
                }
            }

            swatches.forEach(function (s) {
                s.addEventListener('click', function () {
                    select(+s.getAttribute('data-index'), true);
                    if (window.innerWidth <= 760) { main.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
                });
            });

            if (thumbVariant) {
                thumbVariant.addEventListener('click', function () { select(state.index, false); });
            }
            root.querySelectorAll('[data-gl="thumb"]').forEach(function (t) {
                t.addEventListener('click', function () {
                    mainImg.src = t.getAttribute('data-src');
                    mainImg.classList.add('is-photo');
                    main.classList.remove('is-soldout');
                    setActiveThumb(t);
                });
            });

            // Selección inicial: la de ?ref= (enlaces desde Instagram/WhatsApp), si no la primera disponible.
            var wanted = new URLSearchParams(location.search).get('ref');
            var start = -1;
            cfg.variants.forEach(function (v, i) {
                if (start === -1 && wanted && v.slug === wanted) { start = i; }
            });
            if (start === -1) {
                cfg.variants.forEach(function (v, i) { if (start === -1 && v.available) { start = i; } });
            }
            select(start === -1 ? 0 : start, false);

            // POST al carrito. Devuelve el JSON, o null si el nonce fue rechazado (403 o
            // cuerpo "-1"): pasa cuando LiteSpeed sirve la página con un nonce vencido.
            function post(nonce) {
                var v = cfg.variants[state.index];
                return fetch(cfg.ajax, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: new URLSearchParams({
                        action: 'guapo_linea_add_to_cart',
                        nonce: nonce,
                        product_id: cfg.productId,
                        variation_id: v.id
                    })
                }).then(function (r) {
                    if (r.status === 403) { return null; }
                    return r.text().then(function (t) { try { return JSON.parse(t); } catch (e) { return null; } });
                });
            }

            function refreshNonce() {
                return fetch(cfg.ajax, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: new URLSearchParams({ action: 'collar_refresh_nonce' })
                }).then(function (r) { return r.json(); }).then(function (resp) {
                    if (resp && resp.success && resp.data && resp.data.nonce) {
                        state.nonce = resp.data.nonce;
                        return state.nonce;
                    }
                    throw new Error('No se pudo actualizar la sesión. Recarga la página e intenta de nuevo.');
                });
            }

            addBtn.addEventListener('click', function () {
                if (state.busy || state.index < 0) { return; }
                var v = cfg.variants[state.index];
                if (!v.available) { return; }
                state.busy = true;
                addBtn.disabled = true;
                addBtn.textContent = 'Agregando...';

                post(state.nonce)
                    .then(function (resp) { return resp !== null ? resp : refreshNonce().then(post); })
                    .then(function (resp) {
                        if (resp === null) { throw new Error('Tu sesión expiró. Recarga la página e intenta de nuevo.'); }
                        if (resp.success) {
                            showMsg('¡' + v.label + ' quedó en tu carrito! <a href="' + resp.data.cart_url + '">Ver carrito</a>', 'success');
                            document.querySelectorAll('.cart-contents-count, .count, .elementor-button-icon-qty').forEach(function (el) {
                                el.textContent = resp.data.cart_count;
                            });
                            if (window.jQuery) { window.jQuery(document.body).trigger('wc_fragment_refresh'); }
                        } else {
                            if (resp.data && resp.data.sold_out) {
                                v.soldOut = true; v.available = false;
                                swatches[state.index].classList.add('is-soldout');
                            }
                            showMsg((resp.data && resp.data.message) || 'No se pudo agregar al carrito.', 'error');
                        }
                    })
                    .catch(function (err) { showMsg(err && err.message ? err.message : 'Error de conexión.', 'error'); })
                    .finally(function () {
                        state.busy = false;
                        select(state.index, false);
                    });
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    /* ---------------------------------------------------------------------
     * AJAX: agregar al carrito
     * ------------------------------------------------------------------ */

    public function ajax_add_to_cart() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $product_id   = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $variation_id = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : 0;

        // Solo productos registrados como línea: este endpoint no es una puerta genérica al carrito.
        if (!$product_id || !in_array($product_id, $this->lineas(), true)) {
            wp_send_json_error(array('message' => 'Producto no válido.'));
        }

        $variation = wc_get_product($variation_id);
        if (!$variation || !$variation->is_type('variation') || $variation->get_parent_id() !== $product_id) {
            wp_send_json_error(array('message' => 'Esa opción no existe. Recarga la página e intenta de nuevo.'));
        }

        if (!$variation->is_in_stock()) {
            wp_send_json_error(array('message' => 'Esta pieza ya se vendió. Elige otra 💛', 'sold_out' => true));
        }
        if (!$variation->is_purchasable()) {
            wp_send_json_error(array('message' => 'Esta opción todavía no está disponible para la venta.'));
        }

        if (WC()->session && !WC()->session->has_session()) {
            WC()->session->set_customer_session_cookie(true);
        }
        if (WC()->cart && did_action('woocommerce_load_cart_from_session') === 0) {
            WC()->cart->get_cart_from_session();
        }

        // Pieza única ya en el carrito: WooCommerce rechazaría la cantidad con un mensaje
        // técnico. Se responde antes con uno claro.
        if ($variation->managing_stock() && $variation->get_stock_quantity() !== null) {
            $in_cart = 0;
            foreach (WC()->cart->get_cart() as $item) {
                if ((int) $item['variation_id'] === $variation_id) {
                    $in_cart += (int) $item['quantity'];
                }
            }
            if ($in_cart >= $variation->get_stock_quantity()) {
                wp_send_json_error(array(
                    'message' => 'Ya tienes esta pieza en tu carrito. <a href="' . esc_url(wc_get_cart_url()) . '">Ver carrito</a>',
                ));
            }
        }

        $key = WC()->cart->add_to_cart($product_id, 1, $variation_id, $variation->get_variation_attributes());

        if ($key) {
            wp_send_json_success(array(
                'cart_url'   => wc_get_cart_url(),
                'cart_count' => WC()->cart->get_cart_contents_count(),
            ));
        }

        $notices = wc_get_notices('error');
        wc_clear_notices();
        wp_send_json_error(array(
            'message' => $notices ? wp_strip_all_tags($notices[0]['notice']) : 'No se pudo agregar al carrito.',
        ));
    }
}

new CollarLineasTienda();
