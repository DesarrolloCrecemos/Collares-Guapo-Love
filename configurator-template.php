<div class="configurator-wrapper">
    <div id="pixelemos-variations" class="collar-para-charms">
        
        <!-- Layout tipo Wawas: Imagen izquierda + Configurador derecha -->
        <div class="wawas-layout">
            
            <!-- Columna izquierda: Imagen del collar -->
            <div class="collar-image-column">
                <div class="collar-display-area">
                    <div class="collar-preview-image">
                        <div class="collar-band-visual collar2"></div>
                        <div id="charms-on-collar" class="charms-on-collar"></div>
                    </div>
                    <div class="collar-info-summary">
                        <p><strong>Tamaño:</strong> <span id="info-size">XL</span></p>
                        <p><strong>Color:</strong> <span id="info-color">Negro</span></p>
                        <p><strong>Charms:</strong> <span id="info-charms-count">0/9</span></p>
                    </div>
                </div>
            </div>
            
            <!-- Columna derecha: Panel de configuración -->
            <div class="collar-config-panel">
                
                <!-- Paso 1: Tamaño del collar -->
                <div class="config-step">
                    <h4><strong>Paso 1️⃣:</strong> Tamaño del collar</h4>
                    <div id="size-container" class="size-buttons-grid">
                        <button class="size-btn size-s" onclick="changeCollarSize('s')">
                            <span class="size-label">S</span>
                            <span class="size-measure">25-40cm</span>
                        </button>
                        <button class="size-btn size-m" onclick="changeCollarSize('m')">
                            <span class="size-label">M</span>
                            <span class="size-measure">35-46cm</span>
                        </button>
                        <button class="size-btn size-l" onclick="changeCollarSize('l')">
                            <span class="size-label">L</span>
                            <span class="size-measure">40-55cm</span>
                        </button>
                        <button class="size-btn size-xl active" onclick="changeCollarSize('xl')">
                            <span class="size-label">XL</span>
                            <span class="size-measure">50-66cm</span>
                        </button>
                    </div>
                </div>
                
                <!-- Paso 2: Color del collar -->
                <div class="config-step">
                    <h4><strong>Paso 2️⃣:</strong> Color del collar</h4>
                    <div class="collar-color-grid">
                        <button class="collar-color-btn collar1" onclick="changeCollarColor('collar1','Naranja')" title="Naranja"></button>
                        <button class="collar-color-btn collar2 active" onclick="changeCollarColor('collar2','Negro')" title="Negro"></button>
                        <button class="collar-color-btn collar3" onclick="changeCollarColor('collar3','Azul')" title="Azul"></button>
                        <button class="collar-color-btn collar4" onclick="changeCollarColor('collar4','Amarillo')" title="Amarillo"></button>
                        <button class="collar-color-btn collar5" onclick="changeCollarColor('collar5','Rosa')" title="Rosa"></button>
                        <button class="collar-color-btn collar6" onclick="changeCollarColor('collar6','Rojo')" title="Rojo"></button>
                    </div>
                </div>
                
                <!-- Paso 3: Charms -->
                <div class="config-step">
                    <h4><strong>Paso 3️⃣:</strong> Personaliza tus charms</h4>
                    <p class="charms-instruction">Mínimo 5 - Máximo 9 charms</p>
                    
                    <!-- Botón de vaciar -->
                    <button class="btn-clear-charms" onclick="emptyCharms()">🗑️ Vaciar charms</button>
                    
                    <!-- Selector de color de emojis -->
                    <div class="emoji-color-selector">
                        <button class="emoji-color-btn active" data-color="lila" onclick="loadEmojis('lila')" title="Lila">
                            <span class="color-dot" style="background: #A080FF;"></span>
                        </button>
                        <button class="emoji-color-btn" data-color="azul" onclick="loadEmojis('azul')" title="Azul">
                            <span class="color-dot" style="background: #1900FA;"></span>
                        </button>
                        <button class="emoji-color-btn" data-color="verde" onclick="loadEmojis('verde')" title="Verde">
                            <span class="color-dot" style="background: #00FF00;"></span>
                        </button>
                        <button class="emoji-color-btn" data-color="amarillo" onclick="loadEmojis('amarillo')" title="Amarillo">
                            <span class="color-dot" style="background: #E1FF00;"></span>
                        </button>
                        <button class="emoji-color-btn" data-color="naranja" onclick="loadEmojis('naranja')" title="Naranja">
                            <span class="color-dot" style="background: #FF5A00;"></span>
                        </button>
                        <button class="emoji-color-btn" data-color="rojo" onclick="loadEmojis('rojo')" title="Rojo">
                            <span class="color-dot" style="background: #BA0000;"></span>
                        </button>
                        <button class="emoji-color-btn" data-color="rosa" onclick="loadEmojis('rosa')" title="Rosa">
                            <span class="color-dot" style="background: #FF64DC;"></span>
                        </button>
                        <button class="emoji-color-btn" data-color="blanco" onclick="loadEmojis('blanco')" title="Blanco">
                            <span class="color-dot" style="background: #FFFFFF; border: 1px solid #ddd;"></span>
                        </button>
                    </div>
                    
                    <!-- Grid de emojis -->
                    <div id="emoji-grid" class="emoji-grid"></div>
                    
                    <!-- Teclado de letras -->
                    <div class="letters-keyboard">
                        <div class="keyboard-row">
                            <button class="key-btn" onclick="addCharm('A')">A</button>
                            <button class="key-btn" onclick="addCharm('B')">B</button>
                            <button class="key-btn" onclick="addCharm('C')">C</button>
                            <button class="key-btn" onclick="addCharm('D')">D</button>
                            <button class="key-btn" onclick="addCharm('E')">E</button>
                            <button class="key-btn" onclick="addCharm('F')">F</button>
                            <button class="key-btn" onclick="addCharm('G')">G</button>
                        </div>
                        <div class="keyboard-row">
                            <button class="key-btn" onclick="addCharm('H')">H</button>
                            <button class="key-btn" onclick="addCharm('I')">I</button>
                            <button class="key-btn" onclick="addCharm('J')">J</button>
                            <button class="key-btn" onclick="addCharm('K')">K</button>
                            <button class="key-btn" onclick="addCharm('L')">L</button>
                            <button class="key-btn" onclick="addCharm('M')">M</button>
                            <button class="key-btn" onclick="addCharm('N')">N</button>
                        </div>
                        <div class="keyboard-row">
                            <button class="key-btn" onclick="addCharm('Ñ')">Ñ</button>
                            <button class="key-btn" onclick="addCharm('O')">O</button>
                            <button class="key-btn" onclick="addCharm('P')">P</button>
                            <button class="key-btn" onclick="addCharm('Q')">Q</button>
                            <button class="key-btn" onclick="addCharm('R')">R</button>
                            <button class="key-btn" onclick="addCharm('S')">S</button>
                            <button class="key-btn" onclick="addCharm('T')">T</button>
                        </div>
                        <div class="keyboard-row">
                            <button class="key-btn" onclick="addCharm('U')">U</button>
                            <button class="key-btn" onclick="addCharm('V')">V</button>
                            <button class="key-btn" onclick="addCharm('W')">W</button>
                            <button class="key-btn" onclick="addCharm('X')">X</button>
                            <button class="key-btn" onclick="addCharm('Y')">Y</button>
                            <button class="key-btn" onclick="addCharm('Z')">Z</button>
                        </div>
                        <div class="keyboard-row">
                            <button class="key-btn" onclick="addCharm('0')">0</button>
                            <button class="key-btn" onclick="addCharm('1')">1</button>
                            <button class="key-btn" onclick="addCharm('2')">2</button>
                            <button class="key-btn" onclick="addCharm('3')">3</button>
                            <button class="key-btn" onclick="addCharm('4')">4</button>
                            <button class="key-btn" onclick="addCharm('5')">5</button>
                            <button class="key-btn" onclick="addCharm('6')">6</button>
                        </div>
                        <div class="keyboard-row">
                            <button class="key-btn" onclick="addCharm('7')">7</button>
                            <button class="key-btn" onclick="addCharm('8')">8</button>
                            <button class="key-btn" onclick="addCharm('9')">9</button>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
                
                <!-- Paso 3: Personalizar charms -->
                <div class="config-section">
                    <div id="keyboard-container" class="charms-sml">
                        <div class="keyboard charms-keyboard">
                            <h4 class="elije-charms">Elije tus charms (mínimo 5 - máximo 9)</h4>
                
                <!-- Selector de color para charms (sistema antiguo - oculto) -->
                <div class="color-keyboard" style="display:none">
                    <div id="color1" class="color-picker color1" onclick="changeKeysColor('color1')">&nbsp;</div>
                    <div id="color2" class="color-picker color2" onclick="changeKeysColor('color2')">&nbsp;</div>
                    <div id="color3" class="color-picker color3" onclick="changeKeysColor('color3')">&nbsp;</div>
                    <div id="color4" class="color-picker color4" onclick="changeKeysColor('color4')">&nbsp;</div>
                    <div id="color5" class="color-picker color5" onclick="changeKeysColor('color5')">&nbsp;</div>
                    <div id="color6" class="color-picker color6" onclick="changeKeysColor('color6')">&nbsp;</div>
                    <div id="color7" class="color-picker color7" onclick="changeKeysColor('color7')">&nbsp;</div>
                    <div id="color8" class="color-picker color8" onclick="changeKeysColor('color8')">&nbsp;</div>
                    <div id="color9" class="color-picker color9" onclick="changeKeysColor('color9')">&nbsp;</div>
                </div>
                
                <!-- Nuevo selector de colores -->
                <div class="new-color-keyboard">
                    <div id="color11" class="color-picker color11" onclick="changeKeysColor('color11')">&nbsp;</div>
                    <div id="color12" class="color-picker color12" onclick="changeKeysColor('color12')">&nbsp;</div>
                    <div id="color13" class="color-picker color13" onclick="changeKeysColor('color13')">&nbsp;</div>
                    <div id="color14" class="color-picker color14" onclick="changeKeysColor('color14')">&nbsp;</div>
                    <div id="color15" class="color-picker color15" onclick="changeKeysColor('color15')">&nbsp;</div>
                    <div id="color16" class="color-picker color16" onclick="changeKeysColor('color16')">&nbsp;</div>
                    <div id="color17" class="color-picker color17" onclick="changeKeysColor('color17')">&nbsp;</div>
                    <div id="color18" class="color-picker color18" onclick="changeKeysColor('color18')">&nbsp;</div>
                    <div id="color19" class="color-picker color19" onclick="changeKeysColor('color19')">&nbsp;</div>
                    <div id="color20" class="color-picker color20" onclick="changeKeysColor('color20')">&nbsp;</div>
                    <div id="color21" class="color-picker color21" onclick="changeKeysColor('color21')">&nbsp;</div>
                </div>
                
                <!-- Teclado de letras -->
                <div class="row">
                    <div class="keyboard_key color11" id="charm-key-Q" onclick="addCharm('Q')">Q</div>
                    <div class="keyboard_key color11" id="charm-key-W" onclick="addCharm('W')">W</div>
                    <div class="keyboard_key color11" id="charm-key-E" onclick="addCharm('E')">E</div>
                    <div class="keyboard_key color11" id="charm-key-R" onclick="addCharm('R')">R</div>
                    <div class="keyboard_key color11" id="charm-key-T" onclick="addCharm('T')">T</div>
                    <div class="keyboard_key color11" id="charm-key-Y" onclick="addCharm('Y')">Y</div>
                    <div class="keyboard_key color11" id="charm-key-U" onclick="addCharm('U')">U</div>
                    <div class="keyboard_key color11" id="charm-key-I" onclick="addCharm('I')">I</div>
                    <div class="keyboard_key color11" id="charm-key-O" onclick="addCharm('O')">O</div>
                    <div class="keyboard_key color11" id="charm-key-P" onclick="addCharm('P')">P</div>
                </div>
                
                <div class="row">
                    <div class="keyboard_key color11" id="charm-key-A" onclick="addCharm('A')">A</div>
                    <div class="keyboard_key color11" id="charm-key-S" onclick="addCharm('S')">S</div>
                    <div class="keyboard_key color11" id="charm-key-D" onclick="addCharm('D')">D</div>
                    <div class="keyboard_key color11" id="charm-key-F" onclick="addCharm('F')">F</div>
                    <div class="keyboard_key color11" id="charm-key-G" onclick="addCharm('G')">G</div>
                    <div class="keyboard_key color11" id="charm-key-H" onclick="addCharm('H')">H</div>
                    <div class="keyboard_key color11" id="charm-key-J" onclick="addCharm('J')">J</div>
                    <div class="keyboard_key color11" id="charm-key-K" onclick="addCharm('K')">K</div>
                    <div class="keyboard_key color11" id="charm-key-L" onclick="addCharm('L')">L</div>
                    <div class="keyboard_key color11" id="charm-key-Ñ" onclick="addCharm('Ñ')">Ñ</div>
                </div>
                
                <div class="row">
                    <div class="keyboard_key color11" id="charm-key-Z" onclick="addCharm('Z')">Z</div>
                    <div class="keyboard_key color11" id="charm-key-X" onclick="addCharm('X')">X</div>
                    <div class="keyboard_key color11" id="charm-key-C" onclick="addCharm('C')">C</div>
                    <div class="keyboard_key color11" id="charm-key-V" onclick="addCharm('V')">V</div>
                    <div class="keyboard_key color11" id="charm-key-B" onclick="addCharm('B')">B</div>
                    <div class="keyboard_key color11" id="charm-key-N" onclick="addCharm('N')">N</div>
                    <div class="keyboard_key color11" id="charm-key-M" onclick="addCharm('M')">M</div>
                    <div class="keyboard_key color11" id="charm-key-1" onclick="addCharm('1')">1</div>
                    <div class="keyboard_key color11" id="charm-key-2" onclick="addCharm('2')">2</div>
                    <div class="keyboard_key color11" id="charm-key-3" onclick="addCharm('3')">3</div>
                </div>
                
                <div class="row">
                    <div class="keyboard_key color11" id="charm-key-4" onclick="addCharm('4')">4</div>
                    <div class="keyboard_key color11" id="charm-key-5" onclick="addCharm('5')">5</div>
                    <div class="keyboard_key color11" id="charm-key-6" onclick="addCharm('6')">6</div>
                    <div class="keyboard_key color11" id="charm-key-7" onclick="addCharm('7')">7</div>
                    <div class="keyboard_key color11" id="charm-key-8" onclick="addCharm('8')">8</div>
                    <div class="keyboard_key color11" id="charm-key-9" onclick="addCharm('9')">9</div>
                    <div class="keyboard_key color11" id="charm-key-0" onclick="addCharm('0')">0</div>
                    <div class="keyboard_key color11">&nbsp;</div>
                    <div class="keyboard_key color11">&nbsp;</div>
                    <div class="keyboard_key color11" id="charm-key-phone" onclick="if(!phonecharm)addCharm('#')">
                        <img src="https://cdn.shopify.com/s/files/1/0500/2946/1654/t/3/assets/phonecharm.png?v=2" width="32" height="32" alt="phone charm">
                    </div>
                </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Campos para teléfono (ocultos por defecto) -->
        <h4 id="phone-title" style="display:none">Teléfono (10 digitos)</h4>
        <input type="phone" id="phone" value="" style="display:none">
        <div id="phone-level" style="display: none; width: 0%; background: red;">&nbsp;</div>
        
        <!-- Valores generales (ocultos) -->
        <div id="general-values" class="invisible">
            <label for="values-collar-size">Tamaño Collar</label><br>
            <input type="text" id="values-collar-size" value="default"><br>
            <label for="values-collar-color">Color Collar</label><br>
            <input type="text" id="values-collar-color" value="No aplica"><br>
            <label for="values-charms-number">Número de charms</label><br>
            <input type="number" id="values-charms-number" value="0"><br>
        </div>

        <!-- Campos ocultos para guardar configuración -->
        <input type="hidden" id="collar-size-field" name="collar_size" value="xl">
        <input type="hidden" id="collar-color-field" name="collar_color" value="Negro">
        <input type="hidden" id="collar-charms-field" name="collar_charms" value="">
    </div>
</div>

<script>
// Asegurar que el plugin JS esté cargado
if (typeof loadAlphabet === 'undefined') {
    console.error('⚠️ El archivo collar-configurator-new.js no está cargado correctamente');
}
</script>