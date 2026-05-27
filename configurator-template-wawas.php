<div class="configurator-wrapper">
    <div class="wawas-container">
        
        <!-- Layout estilo Wawas: Carrusel izquierda + Panel derecha -->
        <div class="wawas-layout">
            
            <!-- Columna izquierda: Galería de imágenes -->
            <div class="product-image-column">
                <div class="product-gallery">
                    <img src="https://guapolove.com/wp-content/uploads/2026/03/instories_EAEB202C-DC9C-45ED-A08C-42EC52E5FEAF.png"
                         id="collar-main-photo" class="collar-main-img" alt="Collar personalizado" />
                    <div class="collar-thumbs">
                        <button class="collar-thumb-btn active"
                                data-src="https://guapolove.com/wp-content/uploads/2026/03/instories_EAEB202C-DC9C-45ED-A08C-42EC52E5FEAF.png"
                                onclick="setCollarPhoto(this)" type="button">
                            <img src="https://guapolove.com/wp-content/uploads/2026/03/instories_EAEB202C-DC9C-45ED-A08C-42EC52E5FEAF.png" alt="" />
                        </button>
                        <button class="collar-thumb-btn"
                                data-src="https://guapolove.com/wp-content/uploads/2026/03/instories_BCE18260-4220-4698-8799-DD8D61E4AF48.png"
                                onclick="setCollarPhoto(this)" type="button">
                            <img src="https://guapolove.com/wp-content/uploads/2026/03/instories_BCE18260-4220-4698-8799-DD8D61E4AF48.png" alt="" />
                        </button>
                        <button class="collar-thumb-btn"
                                data-src="https://guapolove.com/wp-content/uploads/2026/03/instories_688B8955-0E94-419F-88C1-9E23283D68A6.png"
                                onclick="setCollarPhoto(this)" type="button">
                            <img src="https://guapolove.com/wp-content/uploads/2026/03/instories_688B8955-0E94-419F-88C1-9E23283D68A6.png" alt="" />
                        </button>
                        <button class="collar-thumb-btn"
                                data-src="https://guapolove.com/wp-content/uploads/2026/03/instories_CCE6A1B8-8489-488D-B79E-FFB279D844C3.png"
                                onclick="setCollarPhoto(this)" type="button">
                            <img src="https://guapolove.com/wp-content/uploads/2026/03/instories_CCE6A1B8-8489-488D-B79E-FFB279D844C3.png" alt="" />
                        </button>
                        <button class="collar-thumb-btn"
                                data-src="https://guapolove.com/wp-content/uploads/2026/03/instories_0E81D7C0-676A-41DA-BB74-8F35EC4327A9.png"
                                onclick="setCollarPhoto(this)" type="button">
                            <img src="https://guapolove.com/wp-content/uploads/2026/03/instories_0E81D7C0-676A-41DA-BB74-8F35EC4327A9.png" alt="" />
                        </button>
                        <button class="collar-thumb-btn"
                                data-src="https://guapolove.com/wp-content/uploads/2026/03/instories_688B8955-0E94-419F-88C1-9E23283D68A6.png"
                                onclick="setCollarPhoto(this)" type="button">
                            <img src="https://guapolove.com/wp-content/uploads/2026/03/instories_688B8955-0E94-419F-88C1-9E23283D68A6.png" alt="" />
                        </button>
                    </div>
                </div>
                
                <!-- Marcas autorizadas -->
                <div class="authorized-brands">
                    <div class="brands-container">
                        <div class="brand-item">
                            <img src="<?php echo $plugin_url; ?>assets/logos/PAGINA WEB COLLARES-126.png" alt="Guapolove" class="brand-logo">
                        </div>
                        <div class="brand-item">
                            <img src="https://guapolove.com/wp-content/uploads/2026/03/IMG_6990.png" alt="Wawas" class="brand-logo">
                        </div>
                    </div>
                </div>
                
                <!-- Resumen del producto -->
                <div class="product-summary">
                    <h4 class="summary-title">📋 Resumen de tu collar</h4>
                    <p><strong>Tamaño:</strong> <span id="summary-size">S</span></p>
                    <p><strong>Color:</strong> <span id="summary-color">Negro</span></p>
                    <p><strong>Charms:</strong> <span id="summary-charms">0</span> <span id="summary-extras" style="color: #9333ea; font-size: 12px;"></span></p>
                </div>
                
                <!-- Indicaciones / Tips -->
                <div class="product-tips">
                    <div class="tip-item">
                        <span class="tip-icon">📸</span>
                        <p>Te recomendamos tomar un <strong>pantallazo de tu diseño</strong> antes de continuar con la compra, así recuerdas exactamente cómo armaste tu collar. ¡No te preocupes! Nosotros también almacenamos tu diseño correctamente.</p>
                    </div>
                    <div class="tip-item">
                        <span class="tip-icon">✨</span>
                        <p>Más de 6 charms tienen un costo adicional: <strong>+$3.000 (XS)</strong> / <strong>+$4.000 (otros tamaños)</strong> por cada charm extra.</p>
                    </div>
                    <div class="tip-item">
                        <span class="tip-icon">🐾</span>
                        <p>Este es un producto <strong>totalmente personalizado</strong> y pensado por ti. ¡Hazlo único!</p>
                    </div>
                </div>

                <!-- Preview Collar Gummy -->
                <?php
                $gummy_preview_img = '';
                $gummy_pid = (int) get_option('gummy_product_id', 0);
                if ($gummy_pid) {
                    $gummy_prod = wc_get_product($gummy_pid);
                    if ($gummy_prod) {
                        $gummy_img_id = $gummy_prod->get_image_id();
                        if ($gummy_img_id) {
                            $gummy_preview_img = wp_get_attachment_image_url($gummy_img_id, 'thumbnail');
                        }
                        if (!$gummy_preview_img) {
                            $gallery = $gummy_prod->get_gallery_image_ids();
                            if (!empty($gallery)) {
                                $gummy_preview_img = wp_get_attachment_image_url($gallery[0], 'thumbnail');
                            }
                        }
                    }
                }
                ?>
                <?php if ($gummy_preview_img): ?>
                <!-- Solo visible en desktop -->
                <a href="<?php echo esc_url(get_option('gummy_page_url', '#')); ?>" class="gummy-preview-card gummy-preview-desktop" target="_blank">
                    <div class="gummy-preview-badge">-10% con tu collar</div>
                    <img src="<?php echo esc_url($gummy_preview_img); ?>" class="gummy-preview-img" alt="Collar Gummy" />
                    <div class="gummy-preview-info">
                        <p class="gummy-preview-tag">También te puede gustar</p>
                        <p class="gummy-preview-name">Collar Gummy</p>
                        <p class="gummy-preview-price">$49.900 COP</p>
                        <p class="gummy-preview-cta">Ver más →</p>
                    </div>
                </a>
                <?php endif; ?>
            </div>

            <!-- Columna derecha: Panel de configuración -->
            <div class="config-panel-column">
                
                <!-- Paso 1: Tamaño -->
                <div class="config-step-box">
                    <h4>
                        <img src="<?php echo $plugin_url; ?>assets/icons/PAGINA WEB COLLARES-29.png" alt="" class="step-icon">
                        <strong>Paso 1:</strong> Tamaño del collar
                    </h4>
                    <div class="size-selector-grid">
                        <button class="size-option-btn size-xs" onclick="changeCollarSize('xs')">
                            <div class="size-name">🐱 XS</div>
                            <div class="size-cm">17-29cm</div>
                        </button>
                        <button class="size-option-btn size-s active" onclick="changeCollarSize('s')">
                            <div class="size-name">S</div>
                            <div class="size-cm">22-40cm</div>
                        </button>
                        <button class="size-option-btn size-m" onclick="changeCollarSize('m')">
                            <div class="size-name">M</div>
                            <div class="size-cm">35-46cm</div>
                        </button>
                        <button class="size-option-btn size-l" onclick="changeCollarSize('l')">
                            <div class="size-name">L</div>
                            <div class="size-cm">40-55cm</div>
                        </button>
                        <button class="size-option-btn size-xl" onclick="changeCollarSize('xl')">
                            <div class="size-name">XL</div>
                            <div class="size-cm">50-66cm</div>
                        </button>
                    </div>
                    <div id="xs-cat-notice" style="display:none;margin-top:10px;padding:10px 14px;background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;font-size:13px;color:#92400e;">
                        🐱 <strong>Talla para gatos.</strong> Los charms dorados no están disponibles en talla XS.
                    </div>
                </div>

                <!-- Paso 2: Color del collar -->
                <div class="config-step-box">
                    <h4>
                        <img src="<?php echo $plugin_url; ?>assets/icons/PAGINA WEB COLLARES-30.png" alt="" class="step-icon">
                        <strong>Paso 2:</strong> Color del collar
                    </h4>
                    <div class="collar-colors-grid">
                        <button class="collar-color-option collar1" onclick="changeCollarColor('collar1','Naranja')" title="Naranja"></button>
                        <button class="collar-color-option collar2 active" onclick="changeCollarColor('collar2','Negro')" title="Negro"></button>
                        <button class="collar-color-option collar3" onclick="changeCollarColor('collar3','Azul')" title="Azul"></button>
                        <button class="collar-color-option collar4" onclick="changeCollarColor('collar4','Amarillo')" title="Amarillo"></button>
                        <button class="collar-color-option collar5" onclick="changeCollarColor('collar5','Rosa')" title="Rosa"></button>
                        <button class="collar-color-option collar6" onclick="changeCollarColor('collar6','Rojo')" title="Rojo"></button>
                    </div>
                    
                    <!-- Visualizador del collar (movido aquí) -->
                    <div class="collar-preview-section">
                        <div class="collar-visual-display">
                            <div class="collar-band-preview collar2">
                                <div id="charms-visual" class="charms-on-collar-preview"></div>
                            </div>
                        </div>
                        
                        <button class="clear-all-btn" id="clear-all-btn" onclick="emptyCharms()">
                            <span>🗑️</span> Quitar todos los charms
                        </button>
                    </div>
                </div>
                
                <!-- Paso 3: Personalizar charms -->
                <div class="config-step-box">
                    <h4>
                        <img src="<?php echo $plugin_url; ?>assets/icons/IMG_6560.jpg.png" alt="" class="step-icon">
                        <strong>Paso 3:</strong> Personaliza tus charms
                    </h4>
                    <p class="charms-info">Incluye 6 charms. Puedes agregar más (+$3.000 XS / +$4.000 otros tamaños)</p>
                    
                    <!-- Selector de color de emojis (8 colores + Gold + Silver) -->
                    <div class="emoji-colors-row">
                        <button class="emoji-color-selector" data-color="#00D6FF" onclick="loadEmojis('#00D6FF')" title="Azul Claro">
                            <div class="color-circle" style="background: #00D6FF;"></div>
                        </button>
                        <button class="emoji-color-selector" data-color="#00FF51" onclick="loadEmojis('#00FF51')" title="Verde">
                            <div class="color-circle" style="background: #00FF51;"></div>
                        </button>
                        <button class="emoji-color-selector" data-color="#1900FA" onclick="loadEmojis('#1900FA')" title="Azul">
                            <div class="color-circle" style="background: #1900FA;"></div>
                        </button>
                        <button class="emoji-color-selector" data-color="#A080FF" onclick="loadEmojis('#A080FF')" title="Lila">
                            <div class="color-circle" style="background: #A080FF;"></div>
                        </button>
                        <button class="emoji-color-selector active" data-color="#BA0000" onclick="loadEmojis('#BA0000')" title="Rojo">
                            <div class="color-circle" style="background: #BA0000;"></div>
                        </button>
                        <button class="emoji-color-selector" data-color="#E1FF00" onclick="loadEmojis('#E1FF00')" title="Amarillo">
                            <div class="color-circle" style="background: #E1FF00;"></div>
                        </button>
                        <button class="emoji-color-selector" data-color="#FF5A00" onclick="loadEmojis('#FF5A00')" title="Naranja">
                            <div class="color-circle" style="background: #FF5A00;"></div>
                        </button>
                        <button class="emoji-color-selector" data-color="#FF64DC" onclick="loadEmojis('#FF64DC')" title="Rosa">
                            <div class="color-circle" style="background: #FF64DC;"></div>
                        </button>
                        <button class="emoji-color-selector emoji-gold" data-color="#GOLD" onclick="loadEmojis('#GOLD')" title="Dorado">
                            <div class="color-circle"></div>
                        </button>
                        <button class="emoji-color-selector emoji-silver" data-color="#SILVER" onclick="loadEmojis('#SILVER')" title="Plateado">
                            <div class="color-circle"></div>
                        </button>
                    </div>
                    
                    <!-- Grid de emojis dinámico -->
                    <div id="emojis-display-grid" class="emojis-grid"></div>
                    
                    <!-- Teclado de letras (sin números) -->
                    <div class="letters-number-keyboard">
                        <div class="keys-row">
                            <button class="letter-key" onclick="addCharm('A')">A</button>
                            <button class="letter-key" onclick="addCharm('B')">B</button>
                            <button class="letter-key" onclick="addCharm('C')">C</button>
                            <button class="letter-key" onclick="addCharm('D')">D</button>
                            <button class="letter-key" onclick="addCharm('E')">E</button>
                            <button class="letter-key" onclick="addCharm('F')">F</button>
                            <button class="letter-key" onclick="addCharm('G')">G</button>
                        </div>
                        <div class="keys-row">
                            <button class="letter-key" onclick="addCharm('H')">H</button>
                            <button class="letter-key" onclick="addCharm('I')">I</button>
                            <button class="letter-key" onclick="addCharm('J')">J</button>
                            <button class="letter-key" onclick="addCharm('K')">K</button>
                            <button class="letter-key" onclick="addCharm('L')">L</button>
                            <button class="letter-key" onclick="addCharm('M')">M</button>
                            <button class="letter-key" onclick="addCharm('N')">N</button>
                        </div>
                        <div class="keys-row">
                            <button class="letter-key" onclick="addCharm('Ñ')">Ñ</button>
                            <button class="letter-key" onclick="addCharm('O')">O</button>
                            <button class="letter-key" onclick="addCharm('P')">P</button>
                            <button class="letter-key" onclick="addCharm('Q')">Q</button>
                            <button class="letter-key" onclick="addCharm('R')">R</button>
                            <button class="letter-key" onclick="addCharm('S')">S</button>
                            <button class="letter-key" onclick="addCharm('T')">T</button>
                        </div>
                        <div class="keys-row">
                            <button class="letter-key" onclick="addCharm('U')">U</button>
                            <button class="letter-key" onclick="addCharm('V')">V</button>
                            <button class="letter-key" onclick="addCharm('W')">W</button>
                            <button class="letter-key" onclick="addCharm('X')">X</button>
                            <button class="letter-key" onclick="addCharm('Y')">Y</button>
                            <button class="letter-key" onclick="addCharm('Z')">Z</button>
                        </div>
                    </div>
                </div>
                
                <!-- Preview Gummy solo mobile (entre letras y carrito) -->
                <?php if ($gummy_preview_img): ?>
                <a href="<?php echo esc_url(get_option('gummy_page_url', '#')); ?>" class="gummy-preview-card gummy-preview-mobile" target="_blank">
                    <div class="gummy-preview-badge">-10% con tu collar</div>
                    <img src="<?php echo esc_url($gummy_preview_img); ?>" class="gummy-preview-img" alt="Collar Gummy" />
                    <div class="gummy-preview-info">
                        <p class="gummy-preview-tag">También te puede gustar</p>
                        <p class="gummy-preview-name">Collar Gummy</p>
                        <p class="gummy-preview-price">$49.900 COP</p>
                        <p class="gummy-preview-cta">Ver más →</p>
                    </div>
                </a>
                <?php endif; ?>

                <!-- Sección de precio y carrito -->
                <div class="cart-section">
                    <div class="price-display">
                        <span class="price-label">Precio:</span>
                        <span class="price-amount" id="product-price">$99.000 COP</span>
                    </div>
                    <button class="add-to-cart-btn" id="add-to-cart-btn" onclick="addToCart()">
                        <span class="cart-icon">🛒</span>
                        <span class="cart-text">Añadir al carrito</span>
                    </button>
                    <div class="validation-message" id="validation-message"></div>

                    <!-- Bundle upsell (dentro del carrito) -->
                    <div class="bundle-divider"><span>o llévate el combo</span></div>

                    <div class="bundle-upsell-section" id="bundle-upsell">
                        <div class="bundle-xs-offer" id="bundle-xs-offer" style="display:none">
                            <div class="bundle-inner">
                                <div class="bundle-left">
                                    <span class="bundle-badge">-10% OFF</span>
                                    <p class="bundle-title">COLLAR CHARMS XS<br>+ COLLAR GUMMY XS</p>
                                    <p class="bundle-note">* Precio base (6 charms). Extras se cobran aparte.</p>
                                </div>
                                <div class="bundle-right">
                                    <div class="bundle-price">$107.800</div>
                                    <button class="bundle-add-btn" onclick="addBundleToCart('XS', 38000)">Añadir combo</button>
                                    <a href="<?php echo esc_url(get_option('gummy_page_url', '#')); ?>" class="bundle-gummy-link" target="_blank">Ver Gummy →</a>
                                </div>
                            </div>
                        </div>
                        <div class="bundle-other-offer" id="bundle-other-offer" style="display:none">
                            <div class="bundle-inner">
                                <div class="bundle-left">
                                    <span class="bundle-badge">-10% OFF</span>
                                    <p class="bundle-title">COLLAR CHARMS <span id="bundle-collar-size">S</span><br>+ COLLAR GUMMY <span id="bundle-gummy-size">S</span></p>
                                    <p class="bundle-note">* Precio base (6 charms). Extras se cobran aparte.</p>
                                </div>
                                <div class="bundle-right">
                                    <div class="bundle-price">$113.100</div>
                                    <button class="bundle-add-btn" onclick="addBundleToCart(collarSize, 37300)">Añadir combo</button>
                                    <a href="<?php echo esc_url(get_option('gummy_page_url', '#')); ?>" class="bundle-gummy-link" target="_blank">Ver Gummy →</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="inventory-notice">
                        <span class="inventory-icon">⚠️</span>
                        <div>
                            <strong>Sujeto a disponibilidad de inventario.</strong><br>
                            <span>Los productos están disponibles según stock. Te contactaremos si hay cambios en tu pedido.</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        
        <!-- Campos ocultos para el formulario -->
        <input type="hidden" id="values-collar-size" name="collar_size" value="S">
        <input type="hidden" id="values-collar-color" name="collar_color" value="Negro">
        <input type="hidden" id="values-charms-number" name="charms_count" value="0">
        <input type="hidden" id="values-charms-config" name="charms_data" value="[]">
        <input type="hidden" id="product-id" name="product_id" value="<?php echo get_option('collar_product_id', ''); ?>">
        
    </div>
</div>
