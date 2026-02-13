// Configurador de Collar con 3 Pasos
// Obtener URL base de los assets (viene de WordPress)
const ASSETS_BASE_URL = typeof collarConfig !== 'undefined' ? collarConfig.assetsUrl : 'assets/';

let collarSize = 'xl';
let collarColor = 'Negro';
let selectedColor = '#FFD700'; // Amarillo por defecto
let selectedColorFolder = 'amarillo'; // Carpeta del color
let currentCharms = [];
let maxCharms = 15;
let minCharms = 1;

// Estado del configurador
let enabledKeyboard = true;

// Mapeo de emojis disponibles por carpeta
const emojiMappingByFolder = {
    'lila': ['PAGINA WEB COLLARES-31.png', 'PAGINA WEB COLLARES-32.png', 'PAGINA WEB COLLARES-33.png', 'PAGINA WEB COLLARES-34.png', 'PAGINA WEB COLLARES-35.png', 'PAGINA WEB COLLARES-36.png', 'PAGINA WEB COLLARES-37.png', 'PAGINA WEB COLLARES-38.png', 'PAGINA WEB COLLARES-39.png'],
    'rojo': ['PAGINA WEB COLLARES-40.png', 'PAGINA WEB COLLARES-41.png', 'PAGINA WEB COLLARES-42.png', 'PAGINA WEB COLLARES-43.png', 'PAGINA WEB COLLARES-44.png', 'PAGINA WEB COLLARES-45.png', 'PAGINA WEB COLLARES-46.png', 'PAGINA WEB COLLARES-47.png', 'PAGINA WEB COLLARES-48.png'],
    'naranja': ['PAGINA WEB COLLARES-49.png', 'PAGINA WEB COLLARES-50.png', 'PAGINA WEB COLLARES-51.png', 'PAGINA WEB COLLARES-52.png', 'PAGINA WEB COLLARES-53.png', 'PAGINA WEB COLLARES-54.png', 'PAGINA WEB COLLARES-55.png', 'PAGINA WEB COLLARES-56.png', 'PAGINA WEB COLLARES-57.png'],
    'amarillo': ['PAGINA WEB COLLARES-58.png', 'PAGINA WEB COLLARES-59.png', 'PAGINA WEB COLLARES-60.png', 'PAGINA WEB COLLARES-61.png', 'PAGINA WEB COLLARES-62.png', 'PAGINA WEB COLLARES-63.png', 'PAGINA WEB COLLARES-64.png', 'PAGINA WEB COLLARES-65.png', 'PAGINA WEB COLLARES-66.png'],
    'verde': ['PAGINA WEB COLLARES-67.png', 'PAGINA WEB COLLARES-68.png', 'PAGINA WEB COLLARES-69.png', 'PAGINA WEB COLLARES-70.png', 'PAGINA WEB COLLARES-71.png', 'PAGINA WEB COLLARES-72.png', 'PAGINA WEB COLLARES-73.png', 'PAGINA WEB COLLARES-74.png', 'PAGINA WEB COLLARES-75.png'],
    'azul': ['PAGINA WEB COLLARES-76.png', 'PAGINA WEB COLLARES-77.png', 'PAGINA WEB COLLARES-78.png', 'PAGINA WEB COLLARES-79.png', 'PAGINA WEB COLLARES-80.png', 'PAGINA WEB COLLARES-81.png', 'PAGINA WEB COLLARES-82.png', 'PAGINA WEB COLLARES-83.png', 'PAGINA WEB COLLARES-84.png'],
    'azul_oscuro': ['PAGINA WEB COLLARES-85.png', 'PAGINA WEB COLLARES-86.png', 'PAGINA WEB COLLARES-87.png', 'PAGINA WEB COLLARES-88.png', 'PAGINA WEB COLLARES-89.png', 'PAGINA WEB COLLARES-90.png', 'PAGINA WEB COLLARES-91.png', 'PAGINA WEB COLLARES-92.png', 'PAGINA WEB COLLARES-94.png'],
    'rosa': ['PAGINA WEB COLLARES-95.png', 'PAGINA WEB COLLARES-96.png', 'PAGINA WEB COLLARES-97.png', 'PAGINA WEB COLLARES-98.png', 'PAGINA WEB COLLARES-99.png', 'PAGINA WEB COLLARES-100.png', 'PAGINA WEB COLLARES-101.png', 'PAGINA WEB COLLARES-102.png'],
    'plateado': ['PAGINA WEB COLLARES-103.png', 'PAGINA WEB COLLARES-104.png', 'PAGINA WEB COLLARES-105.png', 'PAGINA WEB COLLARES-106.png', 'PAGINA WEB COLLARES-107.png', 'PAGINA WEB COLLARES-108.png', 'PAGINA WEB COLLARES-109.png', 'PAGINA WEB COLLARES-110.png', 'PAGINA WEB COLLARES-111.png'],
    'dorado': ['PAGINA WEB COLLARES-112.png', 'PAGINA WEB COLLARES-113.png', 'PAGINA WEB COLLARES-114.png', 'PAGINA WEB COLLARES-115.png', 'PAGINA WEB COLLARES-116.png', 'PAGINA WEB COLLARES-117.png', 'PAGINA WEB COLLARES-118.png', 'PAGINA WEB COLLARES-119.png', 'PAGINA WEB COLLARES-120.png']
};

// Mapeo de colores disponibles (sin dorado ni plateado)
const colorMapping = {
    'amarillo': '#FFD700',
    'naranja': '#FF6600',
    'rojo': '#E31E24',
    'rosa': '#FF1493',
    'lila': '#9370DB',
    'azul': '#0066FF',
    'azul_oscuro': '#003366',
    'verde': '#00CC00'
};

// Función para cambiar el tamaño del collar (Paso 1)
function changeCollarSize(size) {
    // Remover clase active de todos los botones de tamaño
    document.querySelectorAll('.size-button').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Agregar clase active al botón seleccionado
    document.querySelector(`.size-${size}`).classList.add('active');
    
    // Actualizar variable global y campo oculto
    collarSize = size;
    document.getElementById('values-collar-size').value = size;
    
    console.log(`Tamaño seleccionado: ${size}`);
}

// Función para cambiar el color del collar (Paso 2)
function changeCollarColor(colorId, colorName) {
    // Remover clase active de todos los botones de color
    document.querySelectorAll('.collar-picker').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Agregar clase active al botón seleccionado
    document.getElementById(colorId).classList.add('active');
    
    // Actualizar variable global y campo oculto
    collarColor = colorName;
    document.getElementById('values-collar-color').value = colorName;
    
    // Cambiar la clase del collar en la vista previa
    const collarElement = document.querySelector('.collar');
    collarElement.className = `collar ${colorId}`;
    
    console.log(`Color seleccionado: ${colorName}`);
}

// Función para cambiar el color de los charms (Paso 3)
function changeKeysColor(colorClass) {
    // Remover clase active de todos los colores
    document.querySelectorAll('.color-picker').forEach(picker => {
        picker.classList.remove('active');
    });
    
    // Agregar clase active al color seleccionado
    document.getElementById(colorClass).classList.add('active');
    
    // Obtener el color y la carpeta del atributo data
    const colorElement = document.getElementById(colorClass);
    selectedColor = colorElement.getAttribute('data-color');
    selectedColorFolder = colorElement.getAttribute('data-folder');
    
    // Actualizar todos los botones del teclado con el nuevo color
    updateKeyboardColors();
    
    console.log(`Color de charm seleccionado: ${selectedColor} (${selectedColorFolder})`);
}

// Función para actualizar colores del teclado
function updateKeyboardColors() {
    // Actualizar letras y números
    const keyboardKeys = document.querySelectorAll('.keyboard_key:not(.delete-key):not(.emoji-key)');
    keyboardKeys.forEach(key => {
        key.style.color = selectedColor;
        // Cambiar la clase CSS también
        key.className = key.className.replace(/(lila|azul_oscuro|azul|plateado|amarillo|naranja|rojo|rosa|verde|dorado)/g, selectedColorFolder);
    });
    
    // Actualizar emojis
    const emojiKeys = document.querySelectorAll('.emoji-key');
    emojiKeys.forEach((key, index) => {
        const emojiFiles = emojiMappingByFolder[selectedColorFolder];
        if (emojiFiles && emojiFiles[index]) {
            const img = key.querySelector('img');
            if (img) {
                img.src = `${ASSETS_BASE_URL}emojis/${selectedColorFolder}/${emojiFiles[index]}`;
            }
        }
        // Cambiar la clase CSS también
        key.className = key.className.replace(/(lila|azul_oscuro|azul|plateado|amarillo|naranja|rojo|rosa|verde|dorado)/g, selectedColorFolder);
    });
}

// Función para añadir un charm
function addCharm(character) {
    if (!enabledKeyboard) return;
    
    if (currentCharms.length >= maxCharms) {
        showMessage(`Máximo ${maxCharms} caracteres permitidos`, 'warning');
        return;
    }
    
    let charm;
    
    // Verificar si es un emoji
    if (character.startsWith('emoji')) {
        const emojiIndex = parseInt(character.replace('emoji', '')) - 1;
        const emojiFiles = emojiMappingByFolder[selectedColorFolder];
        const emojiFile = emojiFiles && emojiFiles[emojiIndex] ? emojiFiles[emojiIndex] : null;
        
        if (emojiFile) {
            charm = {
                character: character,
                color: selectedColor,
                colorFolder: selectedColorFolder,
                isEmoji: true,
                emojiFile: emojiFile
            };
        } else {
            console.error(`Emoji ${character} no encontrado para color ${selectedColorFolder}`);
            return;
        }
    } else {
        // Es una letra o número
        charm = {
            character: character,
            color: selectedColor,
            colorFolder: selectedColorFolder,
            isEmoji: false
        };
    }
    
    currentCharms.push(charm);
    updateCharmDisplay();
    updateCharmsData();
    
    console.log(`Charm añadido: ${character} en color ${selectedColor} (${selectedColorFolder})`);
}

// Función para borrar el último charm
function deleteLastCharm() {
    if (currentCharms.length > 0) {
        currentCharms.pop();
        updateCharmDisplay();
        updateCharmsData();
    }
}

// Función para vaciar todos los charms
function emptyCharms() {
    currentCharms = [];
    updateCharmDisplay();
    updateCharmsData();
    console.log('Todos los charms han sido eliminados');
}

// Función para actualizar la visualización de los charms
function updateCharmDisplay() {
    const container = document.getElementById('charm-container');
    
    container.innerHTML = '';
    
    currentCharms.forEach((charm, index) => {
        const charmElement = document.createElement('div');
        charmElement.className = 'charm-display';
        charmElement.onclick = () => removeCharm(index);
        charmElement.title = 'Click para eliminar este charm';
        
        if (charm.isEmoji) {
            // Crear imagen para emoji
            const img = document.createElement('img');
            img.src = `${ASSETS_BASE_URL}emojis/${charm.colorFolder}/${charm.emojiFile}`;
            img.alt = charm.character;
            img.style.width = '30px';
            img.style.height = '30px';
            charmElement.appendChild(img);
        } else {
            // Texto para letras y números
            charmElement.style.color = charm.color;
            charmElement.textContent = charm.character;
        }
        
        container.appendChild(charmElement);
    });
    
    // Actualizar contador de caracteres
    document.getElementById('values-charms-number').value = currentCharms.length;
}

// Función para remover un charm específico
function removeCharm(index) {
    currentCharms.splice(index, 1);
    updateCharmDisplay();
    updateCharmsData();
}

// Función para actualizar los datos de los charms
function updateCharmsData() {
    const charmsConfig = currentCharms.map(charm => ({
        char: charm.character,
        color: charm.color,
        colorFolder: charm.colorFolder,
        isEmoji: charm.isEmoji,
        emojiFile: charm.emojiFile || null
    }));
    
    document.getElementById('values-charms-config').value = JSON.stringify(charmsConfig);
}



// Función para mostrar mensajes
function showMessage(message, type = 'info') {
    // Esta función se puede expandir para mostrar notificaciones
    console.log(`${type.toUpperCase()}: ${message}`);
}

// Función para validar la configuración
function validateConfiguration() {
    if (currentCharms.length < minCharms) {
        showMessage(`Mínimo ${minCharms} caracteres requeridos`, 'error');
        return false;
    }
    
    if (currentCharms.length > maxCharms) {
        showMessage(`Máximo ${maxCharms} caracteres permitidos`, 'error');
        return false;
    }
    
    return true;
}

// Función para añadir al carrito
function addToCart() {
    if (!validateConfiguration()) return;
    
    const configData = {
        size: collarSize,
        color: collarColor,
        charms: currentCharms,
        totalCharms: currentCharms.length
    };
    
    console.log('Configuración para añadir al carrito:', configData);
    showMessage('¡Producto añadido al carrito!', 'success');
    
    // Aquí se implementaría la integración con WooCommerce
    // window.collarConfigurator.addToCart(configData);
}

// Inicialización cuando se carga el documento
document.addEventListener('DOMContentLoaded', function() {
    console.log('Configurador de collar cargado');
    
    // Configurar eventos para el botón de añadir al carrito
    const addToCartBtn = document.getElementById('add-to-cart-configured');
    if (addToCartBtn) {
        addToCartBtn.addEventListener('click', addToCart);
    }
    
    // Configurar eventos de teclado
    document.addEventListener('keydown', function(event) {
        if (!enabledKeyboard) return;
        
        const key = event.key.toUpperCase();
        
        // Letras y números
        if (/^[A-ZÑ0-9]$/.test(key)) {
            addCharm(key);
            event.preventDefault();
        }
        // Backspace para borrar
        else if (event.key === 'Backspace') {
            deleteLastCharm();
            event.preventDefault();
        }
        // Escape para limpiar todo
        else if (event.key === 'Escape') {
            emptyCharms();
            event.preventDefault();
        }
    });
    
    console.log('Configurador inicializado');
    console.log(`Tamaño inicial: ${collarSize}`);
    console.log(`Color inicial: ${collarColor}`);
    console.log(`Color de charm inicial: ${selectedColor} (${selectedColorFolder})`);
});

// Exportar funciones para uso en WooCommerce
window.collarConfiguratorSteps = {
    changeCollarSize,
    changeCollarColor,
    changeKeysColor,
    addCharm,
    deleteLastCharm,
    emptyCharms,
    validateConfiguration,
    getCurrentConfiguration: () => ({
        size: collarSize,
        color: collarColor,
        charms: currentCharms,
        totalCharms: currentCharms.length
    })
};