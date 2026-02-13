// Configurador de Collar - Sistema de Teclado con Colores
// Obtener URL base de los assets (viene de WordPress)
const ASSETS_BASE_URL = typeof collarConfig !== 'undefined' ? collarConfig.assetsUrl : 'assets/';

// Variables globales
let collarSize = 'xl';
let collarColor = 'Negro';
let currentCharms = [];
let maxCharms = 9;
let minCharms = 5;
let enabledKeyboard = true;
let phonecharm = false;
let currentKeyColor = 'color11'; // Color activo inicial

// Mapeo de colores (color11 - color21)
const colorKeyMap = {
    'color11': '#00D6FF',  // Lila
    'color12': '#00FF51',  // Azul
    'color13': '#1900FA',  // Azul claro
    'color14': '#A080FF',  // Blanco
    'color15': '#BA0000',  // Amarillo
    'color16': '#E1FF00',  // Naranja
    'color17': '#FF5A00',  // Rojo
    'color18': '#FF64DC',  // Rosa
};

// Función para cambiar el tamaño del collar (Paso 1)
function changeCollarSize(size) {
    const sizeMap = {
        'xs': 'XS',
        's': 'S',
        'm': 'M',
        'l': 'L',
        'xl': 'XL'
    };
    
    // Remover clase active de todos los botones de tamaño
    document.querySelectorAll('.size-button').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Agregar clase active al botón seleccionado
    const button = document.querySelector(`.size-${size}`);
    if (button) {
        button.classList.add('active');
    }
    
    // Actualizar variable global y campo oculto
    collarSize = sizeMap[size] || size;
    const sizeField = document.getElementById('values-collar-size');
    if (sizeField) {
        sizeField.value = collarSize;
    }
    
    // Actualizar display
    const displaySize = document.getElementById('display-size');
    if (displaySize) {
        displaySize.textContent = collarSize;
    }
    
    console.log(`Tamaño seleccionado: ${collarSize}`);
}

// Función para cambiar el color del collar (Paso 2)
function changeCollarColor(colorId, colorName) {
    // Remover clase active de todos los botones de color
    document.querySelectorAll('.collar-picker').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Agregar clase active al botón seleccionado
    const button = document.getElementById(colorId);
    if (button) {
        button.classList.add('active');
    }
    
    // Actualizar variable global y campo oculto
    collarColor = colorName;
    const colorField = document.getElementById('values-collar-color');
    if (colorField) {
        colorField.value = colorName;
    }
    
    // Cambiar la clase del collar en la vista previa
    const collarVisual = document.querySelector('.collar-visual');
    if (collarVisual) {
        // Remover todas las clases collar1-6
        collarVisual.className = collarVisual.className.replace(/collar\d+/g, '');
        collarVisual.classList.add('collar-visual', colorId);
    }
    
    // Actualizar display
    const displayColor = document.getElementById('display-color');
    if (displayColor) {
        displayColor.textContent = colorName;
    }
    
    console.log(`Color seleccionado: ${colorName}`);
}

// Función para cambiar el color de las teclas (Paso 3)
function changeKeysColor(colorClass) {
    // Remover clase active de todos los colores
    document.querySelectorAll('.color-picker').forEach(picker => {
        picker.classList.remove('active');
    });
    
    // Agregar clase active al color seleccionado
    const colorPicker = document.getElementById(colorClass);
    if (colorPicker) {
        colorPicker.classList.add('active');
    }
    
    // Actualizar el color actual
    currentKeyColor = colorClass;
    
    // Actualizar todas las teclas del teclado con el nuevo color
    document.querySelectorAll('.keyboard_key').forEach(key => {
        // Remover todas las clases de color previas
        for (let i = 11; i <= 21; i++) {
            key.classList.remove(`color${i}`);
        }
        // Agregar la nueva clase de color
        key.classList.add(colorClass);
    });
    
    console.log(`Color de teclas seleccionado: ${colorClass} (${colorKeyMap[colorClass]})`);
}

// Función para añadir un charm
function addCharm(character) {
    if (!enabledKeyboard) {
        console.log('Teclado deshabilitado');
        return;
    }
    
    // Verificar si es el charm de teléfono
    if (character === '#') {
        phonecharm = true;
        // Mostrar campos de teléfono
        const phoneTitle = document.getElementById('phone-title');
        const phoneInput = document.getElementById('phone');
        const phoneLevel = document.getElementById('phone-level');
        if (phoneTitle) phoneTitle.style.display = 'block';
        if (phoneInput) phoneInput.style.display = 'block';
        if (phoneLevel) phoneLevel.style.display = 'block';
        console.log('Phone charm activado');
        return;
    }
    
    if (currentCharms.length >= maxCharms) {
        showMessage(`Máximo ${maxCharms} caracteres permitidos`, 'warning');
        return;
    }
    
    const charm = {
        character: character,
        color: currentKeyColor,
        colorHex: colorKeyMap[currentKeyColor]
    };
    
    currentCharms.push(charm);
    updateCharmDisplay();
    updateCharmsData();
    
    console.log(`Charm añadido: ${character} en color ${currentKeyColor}`);
}

// Función para vaciar todos los charms
function emptyCharms() {
    currentCharms = [];
    phonecharm = false;
    
    // Ocultar campos de teléfono
    const phoneTitle = document.getElementById('phone-title');
    const phoneInput = document.getElementById('phone');
    const phoneLevel = document.getElementById('phone-level');
    if (phoneTitle) phoneTitle.style.display = 'none';
    if (phoneInput) {
        phoneInput.style.display = 'none';
        phoneInput.value = '';
    }
    if (phoneLevel) phoneLevel.style.display = 'none';
    
    updateCharmDisplay();
    updateCharmsData();
    
    // Habilitar teclado
    enabledKeyboard = true;
    
    console.log('Todos los charms han sido eliminados');
}

// Función para actualizar la visualización de los charms
function updateCharmDisplay() {
    // Actualizar contenedor principal (si existe)
    const container = document.getElementById('charm-container');
    if (container) {
        container.innerHTML = '';
        
        if (currentCharms.length === 0) {
            const emptyMessage = document.createElement('div');
            emptyMessage.className = 'empty-charms-message';
            emptyMessage.textContent = 'Selecciona tus charms';
            emptyMessage.style.color = '#999';
            emptyMessage.style.textAlign = 'center';
            emptyMessage.style.padding = '20px';
            container.appendChild(emptyMessage);
        } else {
            currentCharms.forEach((charm, index) => {
                const charmElement = document.createElement('div');
                charmElement.className = 'charm-display';
                charmElement.onclick = () => removeCharm(index);
                charmElement.title = 'Click para eliminar';
                charmElement.style.color = charm.colorHex;
                charmElement.style.cursor = 'pointer';
                charmElement.style.fontSize = '24px';
                charmElement.style.fontWeight = 'bold';
                charmElement.style.padding = '5px';
                charmElement.style.display = 'inline-block';
                charmElement.textContent = charm.character;
                
                container.appendChild(charmElement);
            });
        }
    }
    
    // Actualizar vista previa del collar
    const previewContainer = document.getElementById('charm-preview-container');
    if (previewContainer) {
        previewContainer.innerHTML = '';
        
        currentCharms.forEach((charm, index) => {
            const charmElement = document.createElement('div');
            charmElement.className = 'charm-preview';
            charmElement.onclick = () => removeCharm(index);
            charmElement.title = 'Click para eliminar';
            charmElement.style.color = charm.colorHex;
            charmElement.style.cursor = 'pointer';
            charmElement.style.fontSize = '28px';
            charmElement.style.fontWeight = 'bold';
            charmElement.style.padding = '8px';
            charmElement.style.background = 'rgba(255, 255, 255, 0.9)';
            charmElement.style.borderRadius = '8px';
            charmElement.style.border = `2px solid ${charm.colorHex}`;
            charmElement.style.transition = 'all 0.2s ease';
            charmElement.textContent = charm.character;
            
            charmElement.onmouseover = function() {
                this.style.transform = 'scale(1.1)';
            };
            charmElement.onmouseout = function() {
                this.style.transform = 'scale(1)';
            };
            
            previewContainer.appendChild(charmElement);
        });
    }
    
    // Actualizar contador de caracteres
    const charmsNumber = document.getElementById('values-charms-number');
    if (charmsNumber) {
        charmsNumber.value = currentCharms.length;
    }
    
    // Actualizar display de info
    const displayCharms = document.getElementById('display-charms');
    if (displayCharms) {
        displayCharms.textContent = `${currentCharms.length}/${maxCharms}`;
    }
    
    // Verificar límites
    checkCharmsLimit();
}

// Función para verificar límites de charms
function checkCharmsLimit() {
    const count = currentCharms.length;
    const message = document.querySelector('.elije-charms');
    
    if (count < minCharms) {
        if (message) message.textContent = `Elije tus charms (mínimo ${minCharms} - máximo ${maxCharms}) - Faltan ${minCharms - count}`;
    } else if (count >= maxCharms) {
        if (message) message.textContent = `Máximo de charms alcanzado (${maxCharms})`;
    } else {
        if (message) message.textContent = `Elije tus charms (mínimo ${minCharms} - máximo ${maxCharms}) - Tienes ${count}`;
    }
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
        colorHex: charm.colorHex
    }));
    
    const configField = document.getElementById('values-charms-config');
    if (configField) {
        configField.value = JSON.stringify(charmsConfig);
    }
}

// Función para mostrar mensajes
function showMessage(message, type = 'info') {
    console.log(`${type.toUpperCase()}: ${message}`);
    
    // Opcionalmente mostrar un alert o notification
    // alert(message);
}

// Función para validar la configuración
function validateConfiguration() {
    if (currentCharms.length < minCharms) {
        showMessage(`Mínimo ${minCharms} caracteres requeridos. Tienes ${currentCharms.length}`, 'error');
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
    if (!validateConfiguration()) {
        alert(`Debes seleccionar entre ${minCharms} y ${maxCharms} charms`);
        return;
    }
    
    const configData = {
        size: collarSize,
        color: collarColor,
        charms: currentCharms,
        totalCharms: currentCharms.length,
        phoneCharm: phonecharm
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
    
    // Configurar eventos de teclado físico
    document.addEventListener('keydown', function(event) {
        if (!enabledKeyboard) return;
        
        const key = event.key.toUpperCase();
        
        // Letras y números
        if (/^[A-ZÑ0-9]$/.test(key)) {
            addCharm(key);
            event.preventDefault();
        }
        // Backspace para borrar el último
        else if (event.key === 'Backspace') {
            if (currentCharms.length > 0) {
                removeCharm(currentCharms.length - 1);
            }
            event.preventDefault();
        }
        // Escape para limpiar todo
        else if (event.key === 'Escape') {
            emptyCharms();
            event.preventDefault();
        }
    });
    
    // Inicializar display vacío
    updateCharmDisplay();
    
    console.log('Configurador inicializado');
    console.log(`Tamaño inicial: ${collarSize}`);
    console.log(`Color inicial: ${collarColor}`);
    console.log(`Color de charm inicial: ${currentKeyColor}`);
});

// Exportar funciones para uso global
window.collarConfiguratorSteps = {
    changeCollarSize,
    changeCollarColor,
    changeKeysColor,
    addCharm,
    emptyCharms,
    validateConfiguration,
    getCurrentConfiguration: () => ({
        size: collarSize,
        color: collarColor,
        charms: currentCharms,
        totalCharms: currentCharms.length,
        phoneCharm: phonecharm
    })
};
