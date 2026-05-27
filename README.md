# Configurador de Collar — Plugin WordPress/WooCommerce

Plugin para configurar collares de mascotas con charms personalizables (letras, números, emojis, glitter), integrado con WooCommerce.

## Archivos del plugin

| Archivo | Función |
|---|---|
| `collar-configurator.php` | Plugin principal. Toda la lógica PHP. |
| `configurator-template-wawas.php` | Plantilla HTML del configurador (frontend). |
| `collar-configurator-wawas.css` | Estilos del configurador. |
| `collar-configurator-wawas.js` | Lógica del configurador (selección, render). |
| `whatsapp-logo-*.webp` | Icono del botón flotante de WhatsApp. |
| `assets/` | Imágenes (emojis, logos, texturas, charms glitter generados). |

## Shortcodes

- `[collar_configurador]` — configurador completo. Úsalo en la página `/personalizar-collar/`.
- `[collar_configurador show_title="false"]` — sin título.
- `[collar_gummy]` — página del Collar Gummy (producto fijo con tallas).

## Páginas del admin

- **Collares → Ajustes**: IDs de productos, URLs, WhatsApp, texturas/fuentes, generador glitter.
- **Collares → Pedidos**: lista de pedidos del configurador con filtros, búsqueda, mini-preview de charms, detalle visual completo, export CSV.

## Lógica de precios (autoritativa en PHP)

Las constantes viven en `CollarConfiguratorAJAX` (collar-configurator.php). **Si cambias precios en el JS, sincroniza acá:**

```php
const BASE_PRICES = array('XS' => 69800, 'S/M/L/XL' => 75800);
const EXTRA_CHARM_PRICE = array('XS' => 3000, otros => 4000);
const BASE_CHARMS_INCLUDED = 6;
const BUNDLE_GUMMY_PRICE = array('XS' => 38000, otros => 37300);
```

El precio que envía el cliente vía AJAX se **ignora**. Se recalcula server-side a partir de `collar_size` + `charms_count`.

## Tabla personalizada en BD

`wp_collar_configurations` — guarda cada collar vendido con su configuración completa para el panel de despacho. Se sincroniza desde los pedidos de WooCommerce automáticamente (`woocommerce_checkout_order_processed` y `woocommerce_order_status_changed`).

## Hooks principales

- `wp_ajax_collar_add_to_cart` / `wp_ajax_nopriv_collar_add_to_cart` — añadir collar al carrito (con validación + precio server-side).
- `wp_ajax_gummy_add_to_cart` — añadir Collar Gummy al carrito.
- `woocommerce_email_after_order_table` — visual de charms + método de envío en correos.
- `woocommerce_thankyou` — página de gracias enriquecida con preview del collar.
- `woocommerce_hidden_order_itemmeta` — oculta `_collar_charms_data` y `Configuración de Charms` de vistas públicas.

## Estructura de meta

**En el line item del pedido:**
- `Tamaño`, `Color`, `Charms` — visibles al cliente y admin.
- `Diseño` — resumen legible tipo `K · E · N · Y · A · 1 emoji`.
- `_collar_charms_data` — JSON crudo de charms (oculto).

**En el pedido (post meta):**
- `_collar_shipping_zone` — `local` / `nacional` / `recoger`.
- `_collar_thankyou_reached_at` — timestamp cuando el cliente llegó a la página de gracias (útil para auditar Bold / Addi).

## Estado interno del proyecto

- **Seguridad**: precio blindado server-side, nonces en formularios admin, CSRF protegidos, validaciones de tamaño/charms.
- **Correos**: nuevo pedido se notifica a `linaguapo.love@gmail.com` (configurar en WC → Correos).
- **Pendientes operativos**: instalar WP Mail SMTP para entregabilidad, configurar UpdraftPlus para backups, plugin Bold ya integrado.

## Versionado

- **1.0.0** — Versión inicial.
- **1.5.0** — Seguridad blindada, panel Pedidos enriquecido, correos visuales, página de gracias custom.
