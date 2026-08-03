<?php
/**
 * Pantalla del Panel Interno de Personalización.
 *
 * Diseñada para tablet en horizontal, sin scroll en el área de trabajo: el operador
 * atiende de pie frente al cliente y no puede perder tiempo buscando controles.
 *
 * @package CollarConfigurator
 * @var array $config Configuración inyectada por CollarFeriaPanel::render_panel()
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="feria-app">

    <header class="feria-topbar">
        <div class="feria-brand">
            <span class="feria-logo"><?php echo esc_html(CollarFeriaPanel::TITLE); ?></span>
            <?php if (!empty($config['evento'])): ?>
                <span class="feria-evento"><?php echo esc_html($config['evento']); ?></span>
            <?php endif; ?>
        </div>

        <div class="feria-topbar-actions">
            <span class="feria-net" id="feria-net" title="Estado de conexión"></span>
            <button type="button" class="feria-btn feria-btn-ghost" id="feria-open-list">
                Fichas de hoy <span class="feria-badge" id="feria-count">0</span>
            </button>
            <button type="button" class="feria-btn feria-btn-ghost" id="feria-open-menu" aria-label="Más opciones">&#9776;</button>
        </div>
    </header>

    <main class="feria-main">

        <!-- ================= Columna izquierda: resultado ================= -->
        <section class="feria-col feria-col-preview">

            <div class="feria-preview-stage">
                <div class="feria-preview-band" id="feria-preview-band">
                    <div class="feria-preview-charms" id="feria-preview-charms"></div>
                </div>
                <p class="feria-preview-empty" id="feria-preview-empty">
                    Toca las letras o los emojis para armar el collar
                </p>
            </div>

            <div class="feria-preview-tools">
                <span class="feria-preview-hint" id="feria-preview-hint">
                    Toca un charm del collar para borrarlo
                </span>
                <button type="button" class="feria-undo-btn" id="feria-undo" disabled>
                    &#9003; Borrar último
                </button>
            </div>

            <div class="feria-summary">
                <div class="feria-summary-row">
                    <span>Talla</span><strong id="feria-sum-size">S</strong>
                </div>
                <div class="feria-summary-row">
                    <span>Color</span><strong id="feria-sum-color">Negro</strong>
                </div>
                <div class="feria-summary-row">
                    <span>Charms</span><strong id="feria-sum-charms">0 / 9</strong>
                </div>
                <div class="feria-summary-row feria-summary-price">
                    <span>Cotización</span><strong id="feria-sum-price">$0</strong>
                </div>
                <p class="feria-price-note" id="feria-price-note"></p>
            </div>

            <div class="feria-customer">
                <div class="feria-field">
                    <label for="feria-pet">Nombre del peludo</label>
                    <input type="text" id="feria-pet" autocomplete="off" placeholder="Ej: Coco">
                </div>
                <div class="feria-field">
                    <label for="feria-name">Cliente</label>
                    <input type="text" id="feria-name" autocomplete="off" placeholder="Nombre y apellido">
                </div>
                <div class="feria-field">
                    <label for="feria-phone">WhatsApp</label>
                    <input type="tel" id="feria-phone" inputmode="numeric" autocomplete="off" placeholder="300 000 0000">
                </div>
                <div class="feria-field">
                    <label for="feria-notes">Notas</label>
                    <input type="text" id="feria-notes" autocomplete="off" placeholder="Opcional">
                </div>
            </div>

            <div class="feria-actions">
                <button type="button" class="feria-btn feria-btn-secondary" id="feria-clear">Limpiar</button>
                <button type="button" class="feria-btn feria-btn-primary" id="feria-save">Guardar ficha</button>
            </div>
        </section>

        <!-- ================= Columna derecha: controles ================= -->
        <section class="feria-col feria-col-controls">

            <div class="feria-block">
                <h2 class="feria-block-title">Talla</h2>
                <div class="feria-size-row" id="feria-size-row"></div>
            </div>

            <div class="feria-block">
                <h2 class="feria-block-title">Color del collar</h2>
                <div class="feria-swatch-row" id="feria-collar-colors"></div>
            </div>

            <div class="feria-block feria-block-grow">
                <div class="feria-block-head">
                    <div class="feria-tabs" id="feria-tabs">
                        <button type="button" class="feria-tab is-active" data-tab="letters">Letras</button>
                        <button type="button" class="feria-tab" data-tab="emojis">Emojis</button>
                    </div>
                    <div class="feria-limit" id="feria-limit"></div>
                </div>

                <div class="feria-charm-colors" id="feria-charm-colors"></div>

                <div class="feria-tabpanel is-active" id="feria-panel-letters">
                    <div class="feria-keyboard" id="feria-keyboard"></div>
                </div>

                <div class="feria-tabpanel" id="feria-panel-emojis">
                    <div class="feria-emoji-grid" id="feria-emoji-grid"></div>
                </div>
            </div>
        </section>
    </main>

    <!-- ================= Cajón: fichas guardadas ================= -->
    <aside class="feria-drawer" id="feria-drawer" aria-hidden="true">
        <div class="feria-drawer-head">
            <h2>Fichas de hoy</h2>
            <button type="button" class="feria-drawer-close" id="feria-close-list" aria-label="Cerrar">&times;</button>
        </div>
        <div class="feria-drawer-body" id="feria-list"></div>
        <div class="feria-drawer-foot">
            <button type="button" class="feria-btn feria-btn-primary feria-btn-block" id="feria-export">
                Exportar todo
            </button>
        </div>
    </aside>

    <!-- ================= Cajón: opciones ================= -->
    <aside class="feria-drawer feria-drawer-menu" id="feria-menu" aria-hidden="true">
        <div class="feria-drawer-head">
            <h2>Opciones</h2>
            <button type="button" class="feria-drawer-close" id="feria-close-menu" aria-label="Cerrar">&times;</button>
        </div>
        <div class="feria-drawer-body">
            <button type="button" class="feria-menu-item" id="feria-precache">
                <strong>Preparar para trabajar sin internet</strong>
                <small>Descarga todo el catálogo en la tablet. Hazlo la noche antes, con buen WiFi.</small>
            </button>
            <div class="feria-progress" id="feria-precache-progress" hidden>
                <div class="feria-progress-bar"><span id="feria-precache-fill"></span></div>
                <p id="feria-precache-text">Preparando…</p>
            </div>

            <button type="button" class="feria-menu-item feria-menu-danger" id="feria-wipe">
                <strong>Borrar los datos del evento</strong>
                <small>Elimina de la tablet las fichas y los datos personales. Exporta antes.</small>
            </button>
        </div>
    </aside>

    <div class="feria-scrim" id="feria-scrim" hidden></div>
    <div class="feria-toast" id="feria-toast" hidden></div>

    <!-- Lienzo de trabajo para generar el PNG de la ficha. Nunca visible. -->
    <canvas id="feria-canvas" width="1200" height="1600" style="display:none;"></canvas>
</div>
