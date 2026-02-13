// Configurador de Collar - Estilo Wawas
// URL base de assets (viene de WordPress)
const ASSETS_BASE_URL = typeof collarConfig !== 'undefined' ? collarConfig.assetsUrl : 'assets/';

// Variables globales
let collarSize = 'S';
let collarColor = 'Negro';
let currentEmojiColor = '#BA0000';
let currentCharmColor = '#BA0000'; // Color hex del charm seleccionado (rojo por defecto)
let currentCharms = [];
let baseCharms = 6; // Cantidad base de charms incluidos en el precio
let minCharms = 5; // Mínimo requerido para comprar
let maxCharms = 20; // Máximo técnico permitido (por espacio)

// Precios base (con 6 charms incluidos)
const basePrices = {
    'XS': 65900,
    'S': 75900,
    'M': 75900,
    'L': 75900,
    'XL': 75900
};

// Precio por charm extra
const extraCharmPrice = {
    'XS': 4000,
    'S': 5000,
    'M': 5000,
    'L': 5000,
    'XL': 5000
};

// Mapeo de colores hex a código de carpeta (sin #)
const emojiColorToFolder = {
    '#00D6FF': '00D6FF',
    '#00FF51': '00FF51',
    '#1900FA': '1900FA',
    '#A080FF': 'A080FF',
    '#BA0000': 'BA0000',
    '#E1FF00': 'E1FF00',
    '#FF5A00': 'FF5A00',
    '#FF64DC': 'FF64DC',
    '#GOLD': 'GOLD',
    '#SILVER': 'SILVER'
};

// Mapeo de archivos de emojis por carpeta hex (ACTUALIZADO con archivos reales)
const emojiFiles = {
    '#00D6FF': ['PAGINA WEB COLLARES-76.png', 'PAGINA WEB COLLARES-77.png', 'PAGINA WEB COLLARES-78.png', 'PAGINA WEB COLLARES-79.png', 'PAGINA WEB COLLARES-80.png', 'PAGINA WEB COLLARES-81.png', 'PAGINA WEB COLLARES-82.png', 'PAGINA WEB COLLARES-83.png', 'PAGINA WEB COLLARES-84.png'],
    '#00FF51': ['PAGINA WEB COLLARES-67.png', 'PAGINA WEB COLLARES-68.png', 'PAGINA WEB COLLARES-69.png', 'PAGINA WEB COLLARES-70.png', 'PAGINA WEB COLLARES-71.png', 'PAGINA WEB COLLARES-72.png', 'PAGINA WEB COLLARES-73.png', 'PAGINA WEB COLLARES-74.png', 'PAGINA WEB COLLARES-75.png'],
    '#1900FA': ['PAGINA WEB COLLARES-85.png', 'PAGINA WEB COLLARES-86.png', 'PAGINA WEB COLLARES-87.png', 'PAGINA WEB COLLARES-88.png', 'PAGINA WEB COLLARES-89.png', 'PAGINA WEB COLLARES-90.png', 'PAGINA WEB COLLARES-91.png', 'PAGINA WEB COLLARES-92.png', 'PAGINA WEB COLLARES-94.png'],
    '#A080FF': ['PAGINA WEB COLLARES-100.png', 'PAGINA WEB COLLARES-101.png', 'PAGINA WEB COLLARES-102.png', 'PAGINA WEB COLLARES-95.png', 'PAGINA WEB COLLARES-96.png', 'PAGINA WEB COLLARES-97.png', 'PAGINA WEB COLLARES-98.png', 'PAGINA WEB COLLARES-99.png'],
    '#BA0000': ['PAGINA WEB COLLARES-40.png', 'PAGINA WEB COLLARES-41.png', 'PAGINA WEB COLLARES-42.png', 'PAGINA WEB COLLARES-43.png', 'PAGINA WEB COLLARES-44.png', 'PAGINA WEB COLLARES-45.png', 'PAGINA WEB COLLARES-46.png', 'PAGINA WEB COLLARES-47.png', 'PAGINA WEB COLLARES-48.png'],
    '#E1FF00': ['PAGINA WEB COLLARES-58.png', 'PAGINA WEB COLLARES-59.png', 'PAGINA WEB COLLARES-60.png', 'PAGINA WEB COLLARES-61.png', 'PAGINA WEB COLLARES-62.png', 'PAGINA WEB COLLARES-63.png', 'PAGINA WEB COLLARES-64.png', 'PAGINA WEB COLLARES-65.png', 'PAGINA WEB COLLARES-66.png'],
    '#FF5A00': ['PAGINA WEB COLLARES-49.png', 'PAGINA WEB COLLARES-50.png', 'PAGINA WEB COLLARES-51.png', 'PAGINA WEB COLLARES-52.png', 'PAGINA WEB COLLARES-53.png', 'PAGINA WEB COLLARES-54.png', 'PAGINA WEB COLLARES-55.png', 'PAGINA WEB COLLARES-56.png', 'PAGINA WEB COLLARES-57.png'],
    '#FF64DC': ['PAGINA WEB COLLARES-31.png', 'PAGINA WEB COLLARES-32.png', 'PAGINA WEB COLLARES-33.png', 'PAGINA WEB COLLARES-34.png', 'PAGINA WEB COLLARES-35.png', 'PAGINA WEB COLLARES-36.png', 'PAGINA WEB COLLARES-37.png', 'PAGINA WEB COLLARES-38.png', 'PAGINA WEB COLLARES-39.png'],
    '#GOLD': ['PAGINA WEB COLLARES-112.png', 'PAGINA WEB COLLARES-113.png', 'PAGINA WEB COLLARES-114.png', 'PAGINA WEB COLLARES-115.png', 'PAGINA WEB COLLARES-116.png', 'PAGINA WEB COLLARES-117.png', 'PAGINA WEB COLLARES-118.png', 'PAGINA WEB COLLARES-119.png', 'PAGINA WEB COLLARES-120.png'],
    '#SILVER': ['PAGINA WEB COLLARES-103.png', 'PAGINA WEB COLLARES-104.png', 'PAGINA WEB COLLARES-105.png', 'PAGINA WEB COLLARES-106.png', 'PAGINA WEB COLLARES-107.png', 'PAGINA WEB COLLARES-108.png', 'PAGINA WEB COLLARES-109.png', 'PAGINA WEB COLLARES-110.png', 'PAGINA WEB COLLARES-111.png']
};

// Función: Cambiar tamaño del collar
function changeCollarSize(size) {
    const sizeNames = {
        'xs': 'XS',
        's': 'S',
        'm': 'M',
        'l': 'L',
        'xl': 'XL'
    };
    
    // Quitar active de todos
    document.querySelectorAll('.size-option-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Agregar active al seleccionado
    const selectedBtn = document.querySelector(`.size-${size}`);
    if (selectedBtn) {
        selectedBtn.classList.add('active');
    }
    
    // Actualizar variables y campos
    collarSize = sizeNames[size] || size.toUpperCase();
    const sizeField = document.getElementById('values-collar-size');
    if (sizeField) sizeField.value = collarSize;
    
    const summarySize = document.getElementById('summary-size');
    if (summarySize) summarySize.textContent = collarSize;
    
    // Actualizar precio según el nuevo tamaño
    updatePriceDisplay();
    
    console.log('Tamaño seleccionado:', collarSize);
}

// Función: Cambiar color del collar
function changeCollarColor(colorClass, colorName) {
    // Quitar active de todos
    document.querySelectorAll('.collar-color-option').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Agregar active al seleccionado
    const selectedBtn = document.querySelector(`.collar-color-option.${colorClass}`);
    if (selectedBtn) {
        selectedBtn.classList.add('active');
    }
    
    // Actualizar color visual
    const collarBand = document.querySelector('.collar-band-preview');
    if (collarBand) {
        collarBand.className = `collar-band-preview ${colorClass}`;
    }
    
    // Actualizar variables y campos
    collarColor = colorName;
    const colorField = document.getElementById('values-collar-color');
    if (colorField) colorField.value = colorName;
    
    const summaryColor = document.getElementById('summary-color');
    if (summaryColor) summaryColor.textContent = colorName;
    
    console.log('Color seleccionado:', colorName);
}

// Función: Cargar emojis de un color específico
function loadEmojis(colorHex) {
    console.log('=== CARGANDO EMOJIS ===');
    console.log('Color solicitado:', colorHex);
    console.log('ASSETS_BASE_URL:', ASSETS_BASE_URL);
    console.log('Objeto emojiFiles completo:', emojiFiles);
    
    // Actualizar color activo
    currentEmojiColor = colorHex;
    currentCharmColor = colorHex;
    
    // Quitar active de todos los botones de color
    document.querySelectorAll('.emoji-color-selector').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Agregar active al color seleccionado
    const activeBtn = document.querySelector(`.emoji-color-selector[data-color="${colorHex}"]`);
    console.log('Botón encontrado:', activeBtn);
    if (activeBtn) {
        activeBtn.classList.add('active');
    }
    
    // Cambiar estilo de las letras del teclado según el color
    document.querySelectorAll('.letter-key').forEach(key => {
        // Resetear estilos inline
        key.style.removeProperty('color');
        key.style.removeProperty('border-color');
        key.style.removeProperty('background');
        key.style.removeProperty('text-shadow');
        key.style.removeProperty('box-shadow');
        
        // Aplicar estilos según el color
        if (colorHex === '#GOLD') {
            key.style.backgroundImage = `url('${ASSETS_BASE_URL}texturas/dorada/PAGINA WEB COLLARES-11.png')`;
            key.style.backgroundSize = 'cover';
            key.style.backgroundPosition = 'center';
            key.style.borderColor = '#DAA520';
            key.style.color = '#FFFFFF';
            key.style.fontWeight = '700';
            key.style.textShadow = '0 2px 4px rgba(0, 0, 0, 0.8), 0 0 8px rgba(139, 69, 19, 0.6)';
            key.style.boxShadow = '0 2px 8px rgba(255, 215, 0, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.3)';
        } else if (colorHex === '#SILVER') {
            key.style.backgroundImage = `url('${ASSETS_BASE_URL}texturas/plateada/PAGINA WEB COLLARES-10.png')`;
            key.style.backgroundSize = 'cover';
            key.style.backgroundPosition = 'center';
            key.style.borderColor = '#A9A9A9';
            key.style.color = '#FFFFFF';
            key.style.fontWeight = '700';
            key.style.textShadow = '0 2px 4px rgba(0, 0, 0, 0.8), 0 0 8px rgba(64, 64, 64, 0.6)';
            key.style.boxShadow = '0 2px 8px rgba(192, 192, 192, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.5)';
        } else {
            // Colores normales - resetear texturas
            key.style.backgroundImage = 'none';
            key.style.background = 'white';
            key.style.color = currentCharmColor;
            key.style.borderColor = currentCharmColor;
            key.style.textShadow = 'none';
            key.style.boxShadow = 'none';
        }
    });
    
    // Obtener contenedor de emojis
    const emojiGrid = document.getElementById('emojis-display-grid');
    console.log('Contenedor emojiGrid:', emojiGrid);
    if (!emojiGrid) {
        console.error('❌ NO SE ENCONTRÓ EL CONTENEDOR DE EMOJIS');
        return;
    }
    
    // Limpiar grid
    emojiGrid.innerHTML = '';
    
    // Obtener archivos de este color
    const files = emojiFiles[colorHex];
    console.log('Archivos encontrados para', colorHex, ':', files);
    
    if (!files || files.length === 0) {
        console.warn('⚠️ NO HAY EMOJIS PARA EL COLOR:', colorHex);
        console.log('Claves disponibles en emojiFiles:', Object.keys(emojiFiles));
        return;
    }
    
    // Crear botones para cada emoji
    files.forEach((filename, index) => {
        const emojiBtn = document.createElement('button');
        emojiBtn.className = 'emoji-item';
        emojiBtn.onclick = () => addCharm(filename, colorHex, true);
        emojiBtn.title = `Emoji ${index + 1}`;
        
        const img = document.createElement('img');
        // Usar carpeta sin # (ej: FF015B en lugar de #FF015B)
        const folderName = emojiColorToFolder[colorHex] || colorHex.replace('#', '');
        const fullUrl = `${ASSETS_BASE_URL}emojis/${folderName}/${filename}`;
        console.log(`Emoji ${index + 1} URL:`, fullUrl);
        
        img.src = fullUrl;
        img.alt = `Emoji ${index + 1}`;
        img.onerror = function() {
            console.error('❌ ERROR CARGANDO IMAGEN:', this.src);
            this.style.display = 'none';
        };
        
        emojiBtn.appendChild(img);
        emojiGrid.appendChild(emojiBtn);
    });
    
    console.log(`✓ Se crearon ${files.length} elementos de emoji para ${colorHex}`);
}

// Función: Añadir un charm (letra, número o emoji)
function addCharm(item, colorHex = null, isEmoji = false) {
    // Verificar límite máximo técnico (por espacio en el collar)
    if (currentCharms.length >= maxCharms) {
        alert(`Máximo ${maxCharms} charms permitidos por espacio en el collar`);
        return;
    }
    
    // Mostrar advertencia si pasa de los 6 incluidos (pero sí permitir)
    if (currentCharms.length >= baseCharms) {
        const extraPrice = extraCharmPrice[collarSize] || 5000;
        console.log(`Charm extra #${currentCharms.length + 1} (+$${extraPrice.toLocaleString('es-CO')} COP)`);
    }
    
    // Determinar si es letra glitter (Gold o Silver)
    const activeColor = isEmoji ? colorHex : currentCharmColor;
    const isGlitter = (activeColor === '#GOLD' || activeColor === '#SILVER');
    
    const charm = {
        value: item,
        isEmoji: isEmoji,
        colorHex: activeColor,
        isGlitter: isGlitter && !isEmoji, // Solo letras pueden ser glitter
        timestamp: Date.now()
    };
    
    currentCharms.push(charm);
    updateCharmsDisplay();
    updateSummary();
    saveConfiguration();
    
    console.log('Charm añadido:', charm);
}

// Función: Actualizar visualización de charms
function updateCharmsDisplay() {
    const container = document.getElementById('charms-visual');
    if (!container) return;
    
    container.innerHTML = '';
    
    currentCharms.forEach((charm, index) => {
        const charmDiv = document.createElement('div');
        charmDiv.className = 'charm-item-visual';
        charmDiv.onclick = () => removeCharm(index);
        charmDiv.title = 'Click para eliminar';
        
        if (charm.isEmoji) {
            // Es un emoji - mostrar imagen
            const img = document.createElement('img');
            // Usar carpeta sin # (ej: FF015B en lugar de #FF015B)
            const folderName = emojiColorToFolder[charm.colorHex] || charm.colorHex.replace('#', '');
            img.src = `${ASSETS_BASE_URL}emojis/${folderName}/${charm.value}`;
            img.alt = 'Emoji';
            charmDiv.appendChild(img);
        } else if (charm.isGlitter) {
            // Es letra glitter (Gold o Silver) - mostrar imagen generada
            const img = document.createElement('img');
            const glitterType = charm.colorHex === '#GOLD' ? 'gold' : 'silver';
            // Usar tamaño 128 para vista previa en el collar
            img.src = `${ASSETS_BASE_URL}charms/glitter/${glitterType}/${charm.value}_128.png`;
            img.alt = charm.value;
            img.onerror = function() {
                // Fallback: si la imagen no existe, mostrar texto con color especial
                console.warn(`Imagen glitter no encontrada: ${this.src}`);
                charmDiv.textContent = charm.value;
                charmDiv.style.color = charm.colorHex === '#GOLD' ? '#FFD700' : '#C0C0C0';
                charmDiv.style.textShadow = '0 0 10px currentColor';
            };
            charmDiv.appendChild(img);
        } else {
            // Es letra normal - mostrar texto con color
            charmDiv.textContent = charm.value;
            charmDiv.style.color = charm.colorHex;
        }
        
        container.appendChild(charmDiv);
    });
    
    // Ajustar ancho del collar según cantidad de charms
    const collarBand = document.querySelector('.collar-band-preview');
    if (collarBand) {
        const charmsCount = currentCharms.length;
        // Calcular ancho: mínimo 300px, aumenta ~50px por charm adicional después de 6
        let collarWidth = 300;
        if (charmsCount > 6) {
            collarWidth = 300 + ((charmsCount - 6) * 50);
        }
        // Máximo 90% del contenedor
        collarWidth = Math.min(collarWidth, window.innerWidth * 0.4);
        collarBand.style.width = collarWidth + 'px';
    }
}

// Función: Eliminar un charm específico
function removeCharm(index) {
    if (index >= 0 && index < currentCharms.length) {
        currentCharms.splice(index, 1);
        updateCharmsDisplay();
        updateSummary();
        saveConfiguration();
        console.log('Charm eliminado del índice:', index);
    }
}

// Función: Calcular precio según tamaño y cantidad de charms
function calculatePrice() {
    const charmsCount = currentCharms.length;
    
    // Si no hay suficientes charms, no mostrar precio aún
    if (charmsCount < minCharms) {
        return null;
    }
    
    // Obtener precio base (incluye 6 charms)
    const basePrice = basePrices[collarSize] || 75900;
    
    // Calcular charms extras (más de 6)
    let extraCharmsCount = Math.max(0, charmsCount - baseCharms);
    let extraCost = extraCharmsCount * (extraCharmPrice[collarSize] || 5000);
    
    // Si tiene menos de 6 charms, descontar por cada uno que falte
    // (esto es opcional, si quieres cobrar menos cuando tienen 5 en vez de 6)
    // Por ahora mantenemos precio base si tiene 5 o 6
    
    return basePrice + extraCost;
}

// Función: Actualizar display de precio
function updatePriceDisplay() {
    const priceElement = document.getElementById('product-price');
    if (!priceElement) return;
    
    const price = calculatePrice();
    
    if (price === null) {
        priceElement.textContent = '-- COP';
        priceElement.style.opacity = '0.5';
    } else {
        priceElement.textContent = `$${price.toLocaleString('es-CO')} COP`;
        priceElement.style.opacity = '1';
    }
}

// Función: Vaciar todos los charms
function emptyCharms() {
    if (currentCharms.length === 0) return;
    
    if (confirm('¿Seguro que quieres eliminar todos los charms?')) {
        currentCharms = [];
        updateCharmsDisplay();
        updateSummary();
        saveConfiguration();
        console.log('Todos los charms eliminados');
    }
}

// Función: Actualizar resumen
function updateSummary() {
    const count = currentCharms.length;
    const summaryCharms = document.getElementById('summary-charms');
    const summaryExtras = document.getElementById('summary-extras');
    const clearBtn = document.getElementById('clear-all-btn');
    
    // Mostrar/ocultar botón de limpiar según si hay charms
    if (clearBtn) {
        clearBtn.style.display = count > 0 ? 'flex' : 'none';
    }
    
    if (summaryCharms) {
        summaryCharms.textContent = count;
        
        // Cambiar color según estado
        if (count < minCharms) {
            summaryCharms.style.color = '#ff6b6b'; // Rojo - faltan charms
        } else if (count > baseCharms) {
            summaryCharms.style.color = '#9333ea'; // Morado - tiene extras
        } else {
            summaryCharms.style.color = '#4caf50'; // Verde - OK
        }
    }
    
    // Mostrar cantidad de extras
    if (summaryExtras) {
        const extras = Math.max(0, count - baseCharms);
        if (extras > 0) {
            const extraPrice = extraCharmPrice[collarSize] || 5000;
            summaryExtras.textContent = `(+${extras} extra${extras > 1 ? 's' : ''}: $${(extras * extraPrice).toLocaleString('es-CO')})`;
        } else {
            summaryExtras.textContent = '';
        }
    }
    
    // Actualizar precio dinámicamente
    updatePriceDisplay();
}

// Función: Guardar configuración en campos ocultos
function saveConfiguration() {
    const charmsNumber = document.getElementById('values-charms-number');
    if (charmsNumber) charmsNumber.value = currentCharms.length;
    
    const charmsConfig = document.getElementById('values-charms-config');
    if (charmsConfig) charmsConfig.value = JSON.stringify(currentCharms);
}

// Función: Validar antes de añadir al carrito
function validateConfiguration() {
    if (currentCharms.length < minCharms) {
        return {
            isValid: false,
            message: `Debes seleccionar al menos ${minCharms} charms. Tienes ${currentCharms.length}.`
        };
    }
    
    if (currentCharms.length > maxCharms) {
        return {
            isValid: false,
            message: `Máximo ${maxCharms} charms permitidos por espacio.`
        };
    }
    
    return {
        isValid: true,
        message: '¡Configuración válida!'
    };
}

// Función: Añadir al carrito de WooCommerce
function addToCart() {
    console.log('=== Añadiendo al carrito ===');
    
    // Validar configuración
    const validation = validateConfiguration();
    const messageDiv = document.getElementById('validation-message');
    const addButton = document.getElementById('add-to-cart-btn');
    
    console.log('Validación:', validation);
    
    if (!validation.isValid) {
        messageDiv.textContent = validation.message;
        messageDiv.className = 'validation-message error';
        return;
    }
    
    // Deshabilitar botón mientras procesa
    addButton.disabled = true;
    addButton.innerHTML = '<span class="cart-icon">⏳</span><span class="cart-text">Añadiendo...</span>';
    
    // Preparar datos
    const productId = document.getElementById('product-id')?.value;
    console.log('Product ID:', productId);
    console.log('collarConfig:', collarConfig);
    
    if (!productId || productId === '') {
        messageDiv.textContent = 'Error: No se ha configurado el ID del producto. Ve a Ajustes → Configurador de Collar';
        messageDiv.className = 'validation-message error';
        addButton.disabled = false;
        addButton.innerHTML = '<span class="cart-icon">🛒</span><span class="cart-text">Añadir al carrito</span>';
        return;
    }
    
    // Enviar al servidor via AJAX
    const formData = new FormData();
    formData.append('action', 'collar_add_to_cart');
    formData.append('nonce', collarConfig.nonce);
    formData.append('product_id', productId);
    formData.append('collar_size', collarSize);
    formData.append('collar_color', collarColor);
    formData.append('charms_count', currentCharms.length);
    formData.append('charms_data', JSON.stringify(currentCharms));
    formData.append('price', calculatePrice()); // Enviar el precio calculado
    
    fetch(collarConfig.ajaxUrl, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Respuesta del servidor:', response);
        return response.json();
    })
    .then(data => {
        console.log('Datos recibidos:', data);
        if (data.success) {
            messageDiv.textContent = '✓ ¡Añadido al carrito con éxito!';
            messageDiv.className = 'validation-message success';
            
            // Redirigir al carrito después de 1 segundo
            setTimeout(() => {
                window.location.href = data.data.cart_url || '/carrito';
            }, 1500);
        } else {
            console.error('Error del servidor:', data);
            messageDiv.textContent = data.data.message || 'Error al añadir al carrito';
            messageDiv.className = 'validation-message error';
            addButton.disabled = false;
            addButton.innerHTML = '<span class="cart-icon">🛒</span><span class="cart-text">Añadir al carrito</span>';
        }
    })
    .catch(error => {
        console.error('Error de conexión:', error);
        messageDiv.textContent = 'Error de conexión. Intenta de nuevo.';
        messageDiv.className = 'validation-message error';
        addButton.disabled = false;
        addButton.innerHTML = '<span class="cart-icon">🛒</span><span class="cart-text">Añadir al carrito</span>';
    });
}

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    console.log('Configurador de collar Wawas inicializado');
    console.log('Assets URL:', ASSETS_BASE_URL);
    
    // Inicializar displays
    updateCharmsDisplay();
    updateSummary();
    
    // Cargar emojis del primer color por defecto (#BA0000 - rojo, que sí tiene imágenes)
    console.log('Cargando emojis iniciales...');
    loadEmojis('#BA0000');
    
    console.log('Estado inicial:');
    console.log('- Tamaño:', collarSize);
    console.log('- Color:', collarColor);
    console.log('- Charms:', currentCharms.length);
});

// Exportar funciones globalmente
window.changeCollarSize = changeCollarSize;
window.changeCollarColor = changeCollarColor;
window.loadEmojis = loadEmojis;
window.addCharm = addCharm;
window.removeCharm = removeCharm;
window.emptyCharms = emptyCharms;
window.addToCart = addToCart;

window.collarConfigurator = {
    changeCollarSize,
    changeCollarColor,
    loadEmojis,
    addCharm,
    removeCharm,
    emptyCharms,
    addToCart,
    validateConfiguration,
    getConfiguration: () => ({
        size: collarSize,
        color: collarColor,
        charms: currentCharms,
        charmsCount: currentCharms.length
    })
};

// Aplicar texturas a los botones Gold y Silver
document.addEventListener('DOMContentLoaded', function() {
    const goldButton = document.querySelector('.emoji-gold .color-circle');
    const silverButton = document.querySelector('.emoji-silver .color-circle');
    
    if (goldButton) {
        goldButton.style.backgroundImage = `url('${ASSETS_BASE_URL}texturas/dorada/PAGINA WEB COLLARES-11.png')`;
        goldButton.style.backgroundSize = 'cover';
        goldButton.style.backgroundPosition = 'center';
    }
    
    if (silverButton) {
        silverButton.style.backgroundImage = `url('${ASSETS_BASE_URL}texturas/plateada/PAGINA WEB COLLARES-10.png')`;
        silverButton.style.backgroundSize = 'cover';
        silverButton.style.backgroundPosition = 'center';
    }
    
    // Inicializar carrusel
    initCarousel();
});

// Carrusel de imágenes
let currentSlide = 0;

function initCarousel() {
    showSlide(currentSlide);
}

function moveCarousel(direction) {
    const images = document.querySelectorAll('.carousel-image');
    
    currentSlide += direction;
    
    if (currentSlide >= images.length) {
        currentSlide = 0;
    } else if (currentSlide < 0) {
        currentSlide = images.length - 1;
    }
    
    showSlide(currentSlide);
}

function goToSlide(index) {
    currentSlide = index;
    showSlide(currentSlide);
}

function showSlide(index) {
    const images = document.querySelectorAll('.carousel-image');
    const dots = document.querySelectorAll('.dot');
    
    images.forEach((img, i) => {
        img.classList.toggle('active', i === index);
    });
    
    dots.forEach((dot, i) => {
        dot.classList.toggle('active', i === index);
    });
}

// Hacer funciones globales
window.moveCarousel = moveCarousel;
window.goToSlide = goToSlide;

