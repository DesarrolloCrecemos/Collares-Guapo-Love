<div class="configurator-wrapper">
    <div class="wawas-container">
        
        <!-- Layout estilo Wawas: Carrusel izquierda + Panel derecha -->
        <div class="wawas-layout">
            
            <!-- Columna izquierda: Carrusel de imágenes -->
            <div class="product-image-column">
                <div class="product-carousel">
                    <div class="carousel-container">
                        <button class="carousel-btn prev" onclick="moveCarousel(-1)">❮</button>
                        <div class="carousel-images">
                            <img src="<?php echo $plugin_url; ?>assets/collares/collarcoco.webp" alt="Collar frontal" class="carousel-image active">
                            <img src="<?php echo $plugin_url; ?>assets/collares/Lateral.png" alt="Collar lateral" class="carousel-image">
                            <img src="<?php echo $plugin_url; ?>assets/collares/piezas.png" alt="Piezas del collar" class="carousel-image">
                            <img src="<?php echo $plugin_url; ?>assets/collares/posterior.png" alt="Collar posterior" class="carousel-image">
                        </div>
                        <button class="carousel-btn next" onclick="moveCarousel(1)">❯</button>
                    </div>
                    <div class="carousel-dots">
                        <button class="dot active" onclick="goToSlide(0)" aria-label="Ir a imagen 1" type="button"></button>
                        <button class="dot" onclick="goToSlide(1)" aria-label="Ir a imagen 2" type="button"></button>
                        <button class="dot" onclick="goToSlide(2)" aria-label="Ir a imagen 3" type="button"></button>
                        <button class="dot" onclick="goToSlide(3)" aria-label="Ir a imagen 4" type="button"></button>
                    </div>
                </div>
                
                <!-- Marcas autorizadas -->
                <div class="authorized-brands">
                    <div class="brands-container">
                        <div class="brand-item">
                            <img src="<?php echo $plugin_url; ?>assets/logos/PAGINA WEB COLLARES-126.png" alt="Marca autorizada" class="brand-logo">
                        </div>
                        <div class="brand-item">
                            <img src="<?php echo $plugin_url; ?>assets/logos/PAGINA WEB COLLARES-127.png" alt="Marca autorizada" class="brand-logo">
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
                        <p>Más de 5 charms tienen un costo adicional: <strong>+$4.000 (XS)</strong> / <strong>+$5.000 (otros tamaños)</strong> por cada charm extra.</p>
                    </div>
                    <div class="tip-item">
                        <span class="tip-icon">🐾</span>
                        <p>Este es un producto <strong>totalmente personalizado</strong> y pensado por ti. ¡Hazlo único!</p>
                    </div>
                </div>
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
                            <div class="size-name">XS</div>
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
                    <p class="charms-info">Mínimo 5 charms incluidos. Puedes agregar más (+$4.000 XS / +$5.000 otros)</p>
                    
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
