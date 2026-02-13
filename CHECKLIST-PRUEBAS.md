# ✅ Checklist de Pruebas - Configurador de Collar

## 🎨 **Visual & Layout**

### Desktop (1200px+)
- ✅ Carrusel visible en columna izquierda (500px)
- ✅ Panel de configuración en columna derecha
- ✅ Carrusel con 2 imágenes (collarcoco.webp primero)
- ✅ Flechas de navegación (35px, pequeñas y discretas)
- ✅ Dots del carrusel funcionando
- ✅ Logos de marca pequeños (16px de altura)
- ✅ Precio y "Añadir al carrito" debajo del carrusel
- ✅ Logos debajo del botón de carrito

### Tablet (992px - 1200px)
- ✅ Layout a 1 columna
- ✅ Carrusel arriba (400px altura)
- ✅ Panel de config abajo

### Móvil (768px-)
- ✅ Carrusel más pequeño (300px altura)
- ✅ Flechas reducidas (30px)
- ✅ Grid de emojis en 4 columnas
- ✅ Tallas en 2 columnas
- ✅ Colores en 3 columnas

---

## 🎯 **Funcionalidades Core**

### Paso 1: Tamaño del Collar
- [ ] Clic en XS/S/M/L/XL cambia tamaño activo
- [ ] Botón activo muestra borde destacado
- [ ] Resumen actualiza "Tamaño: [X]"
- [ ] Precio se recalcula según tamaño

### Paso 2: Color del Collar
- [ ] 6 opciones de color disponibles
- [ ] Clic cambia color del collar en preview
- [ ] Marca visual en opción seleccionada
- [ ] Resumen actualiza "Color: [nombre]"
- [ ] Preview visual muestra el color correcto

**Preview del collar:**
- [ ] Se ve dentro del Paso 2 (después de colores)
- [ ] Collar empieza en 300px de ancho
- [ ] Se expande +50px por cada charm después de 6
- [ ] No se rompe con 15-20 charms
- [ ] Botón "Limpiar todo" funciona

### Paso 3: Charms

**Selector de colores de emoji (10 opciones):**
- [ ] 8 colores hex funcionan
- [ ] Botón DORADO muestra textura dorada (no gradiente)
- [ ] Botón PLATEADO muestra textura plateada (no gradiente)
- [ ] Clic cambia color activo (borde resaltado)

**Grid de emojis:**
- [ ] Muestra 9 emojis del color seleccionado
- [ ] Cambia dinámicamente al seleccionar otro color
- [ ] GOLD: muestra emojis 112-120.png de carpeta GOLD
- [ ] SILVER: muestra emojis 103-111.png de carpeta SILVER
- [ ] Clic en emoji lo agrega al collar

**Teclado de letras:**
- [ ] 26 letras + Ñ (27 teclas)
- [ ] **GOLD seleccionado:** fondo con textura dorada real, texto blanco con sombra
- [ ] **SILVER seleccionado:** fondo con textura plateada real, texto blanco con sombra
- [ ] Otros colores: fondo blanco, texto del color seleccionado
- [ ] Clic en letra la agrega al collar

**Charms en el collar:**
- [ ] Letras normales: texto con color hex
- [ ] Letras GOLD: imagen PNG con textura (o texto dorado si falla)
- [ ] Letras SILVER: imagen PNG con textura (o texto plateado si falla)
- [ ] Emojis: imagen PNG del color
- [ ] Letras con `font-weight: 900` (ultra-bold)
- [ ] **Hover en charm:** aparece ❌ rojo en esquina superior derecha
- [ ] **Hover en charm:** fondo semi-transparente + escala 1.15x
- [ ] **Clic en charm:** se elimina del collar

---

## 🎠 **Carrusel de Imágenes**

### Navegación
- [ ] **Imagen inicial:** collarcoco.webp (activa al cargar)
- [ ] **Siguiente:** collar-reference.jpg.png
- [ ] Flecha izquierda (❮) va a imagen anterior
- [ ] Flecha derecha (❯) va a imagen siguiente
- [ ] Flechas son pequeñas (35px) y discretas
- [ ] Clic en dot 1 va a collarcoco.webp
- [ ] Clic en dot 2 va a collar-reference
- [ ] Dot activo está resaltado (negro, escala 1.3x)

### Visual
- [ ] Imágenes usan `object-fit: contain` (se ven completas)
- [ ] Fondo gris claro (#f5f5f5)
- [ ] Transición suave (fade 0.5s)
- [ ] Ambas imágenes se ven enteras sin cortes

---

## 💰 **Precio y Carrito**

### Precio dinámico
- [ ] Precio base por tamaño:
  - XS: $65.900
  - S/M/L: $75.900
  - XL: $85.900
- [ ] Incluye 6 charms gratis
- [ ] Charm 7+: +$4.000 (XS) o +$5.000 (otros)
- [ ] Precio actualiza al agregar/quitar charms
- [ ] Precio actualiza al cambiar tamaño

### Validaciones
- [ ] Mínimo 5 charms requeridos
- [ ] Mensaje de error si < 5 charms
- [ ] Máximo 20 charms (técnico)
- [ ] Mensaje si intentas agregar más de 20

### Añadir al carrito
- [ ] Botón deshabilitado si < 5 charms
- [ ] Botón muestra "Añadiendo..." durante proceso
- [ ] Éxito: mensaje verde "✓ Añadido al carrito"
- [ ] Redirección a carrito en 1.5s
- [ ] Error: mensaje rojo con descripción

---

## 🏢 **Logos de Marca**

- [ ] Aparecen debajo del botón "Añadir al carrito"
- [ ] 2 logos visibles
- [ ] Altura reducida: 16px
- [ ] Fondo blanco con bordes redondeados
- [ ] Hover: pierde grayscale, sube 2px, sombra

---

## 🔧 **Funciones JavaScript**

### Globales expuestas
- [ ] `changeCollarSize(size)` funciona desde onclick
- [ ] `changeCollarColor(class, name)` funciona
- [ ] `loadEmojis(colorHex)` carga emojis dinámicamente
- [ ] `addCharm(item)` agrega letra/emoji
- [ ] `removeCharm(index)` elimina charm
- [ ] `emptyCharms()` limpia todos
- [ ] `addToCart()` procesa pedido
- [ ] `moveCarousel(direction)` cambia imagen
- [ ] `goToSlide(index)` va a imagen específica

### AJAX
- [ ] `collarConfig.ajaxUrl` está definido
- [ ] `collarConfig.nonce` es válido
- [ ] Petición POST a `collar_add_to_cart` funciona
- [ ] Respuesta JSON parsea correctamente

---

## 📱 **Responsive - Puntos de Quiebre**

### 1200px ↓
- [ ] Carrusel: 400px ancho
- [ ] Gap entre columnas: 30px

### 992px ↓
- [ ] Layout a 1 columna
- [ ] Carrusel: altura 350px
- [ ] Preview collar: altura 300px
- [ ] Position sticky eliminada

### 768px ↓
- [ ] Tallas: 2 columnas
- [ ] Colores collar: 3 columnas
- [ ] Emojis: 4 columnas
- [ ] Letras: más pequeñas (40px)
- [ ] Carrusel: 300px altura
- [ ] Flechas: 30px

---

## 🎨 **Texturas Glitter**

### Assets requeridos
- [ ] `assets/texturas/dorada/PAGINA WEB COLLARES-11.png` existe
- [ ] `assets/texturas/plateada/PAGINA WEB COLLARES-10.png` existe
- [ ] `assets/charms/glitter/gold/A_128.png` ... Z_128.png existen (37 chars × 2 tamaños = 74)
- [ ] `assets/charms/glitter/silver/A_128.png` ... Z_128.png existen (74 archivos)

### Aplicación
- [ ] Botones Gold/Silver usan textura como `backgroundImage`
- [ ] Teclado Gold usa textura dorada como fondo
- [ ] Teclado Silver usa textura plateada como fondo
- [ ] Letras Gold en collar muestran PNG con textura
- [ ] Letras Silver en collar muestran PNG con textura
- [ ] Fallback a texto si imagen no existe

---

## 🐛 **Posibles Problemas Conocidos**

### Resueltos:
- ✅ Selector CSS duplicado `.clear-all-btn` (eliminado)
- ✅ Selector CSS duplicado `.product-image-column` (mantener primero, eliminar segundo si existe)
- ✅ Variable `dots` sin usar en `goToSlide()` (eliminada)
- ✅ Dots del carrusel ahora son `<button>` con `aria-label` (accesibilidad)

### A verificar en navegador:
- [ ] Texturas glitter se cargan correctamente
- [ ] Rutas de assets son correctas (WordPress plugin_url)
- [ ] Collar no se desborda con 20 charms
- [ ] Precio calcula bien con múltiples charms
- [ ] WooCommerce agrega producto al carrito
- [ ] Metadata se guarda en orden de WooCommerce

---

## 📊 **Pruebas de Integración WordPress**

### Admin
- [ ] Panel "Collares" → "Ajustes" accesible
- [ ] Panel "Collares" → "Pedidos" muestra configuraciones
- [ ] ID de producto se guarda correctamente
- [ ] Generador de letras funciona (si hay Imagick)

### Frontend
- [ ] Shortcode `[collar_configurador]` renderiza
- [ ] `collarConfig.assetsUrl` tiene plugin URL correcto
- [ ] `collarConfig.ajaxUrl` apunta a admin-ajax.php
- [ ] `collarConfig.nonce` es válido

### WooCommerce
- [ ] Producto se agrega al carrito con metadata
- [ ] Metadata incluye: size, color, charms_count, charms_data
- [ ] Precio personalizado se aplica
- [ ] Orden guardada muestra configuración completa
- [ ] Panel "Pedidos" lista todos los collares personalizados

---

## ✨ **Experiencia de Usuario**

- [ ] No hay errores en consola del navegador (F12)
- [ ] No hay warnings de React/Vue (si aplica)
- [ ] Imágenes cargan rápido (< 2s)
- [ ] Transiciones son fluidas (no laggy)
- [ ] Hover states son claros
- [ ] Estados activos son obvios
- [ ] Mensajes de error son claros
- [ ] Feedback visual al agregar charm
- [ ] Loading state al añadir al carrito

---

## 🚀 **Performance**

- [ ] JavaScript carga sin bloquear render
- [ ] CSS no tiene estilos inline innecesarios
- [ ] Imágenes están optimizadas (<500KB cada una)
- [ ] No hay memory leaks (abrir/cerrar varias veces)
- [ ] Funciona en Chrome/Firefox/Safari/Edge

---

## 📝 **Checklist Manual Rápido**

**5 minutos de prueba:**
1. [ ] Carga la página → Carrusel muestra collarcoco primero
2. [ ] Click flechas → Cambia entre 2 imágenes
3. [ ] Selecciona tamaño M → Precio cambia
4. [ ] Selecciona color Azul → Collar cambia color
5. [ ] Click botón GOLD → Teclado tiene texturas doradas
6. [ ] Presiona letra A → Aparece en collar con textura
7. [ ] Hover en A → Aparece ❌ rojo
8. [ ] Click en A del collar → Se elimina
9. [ ] Agrega 10 charms → Collar se expande
10. [ ] Click "Añadir al carrito" → Redirige a carrito
11. [ ] Logos de marca se ven pequeños (16px)

---

## 🎯 **Estado Final Esperado**

```
┌─────────────────────┬────────────────────────┐
│   CARRUSEL          │   📏 Paso 1: Tamaño    │
│   • collarcoco.webp │   🎨 Paso 2: Color     │
│   • collar-ref.png  │   ┌──────────────────┐ │
│   [flechas 35px]    │   │ Preview Collar   │ │
│   [dots: ●○]        │   │ (extensible)     │ │
│                     │   │ [Limpiar todo]   │ │
│   💰 Precio: $X COP │   └──────────────────┘ │
│   🛒 Añadir carrito │   ✨ Paso 3: Charms   │
│   🏢 Logos (16px)   │   • 10 colores        │
└─────────────────────┴────────────────────────┘
```

**¿Todo funcionando?** ✅  
**¿Responsive OK?** ✅  
**¿Sin errores críticos?** ✅  
**¿Listo para producción?** 🚀
