# 🎨 Guía de Recursos Decorativos

## 📁 Estructura de Carpetas

Organiza tus recursos en estas carpetas dentro de `assets/`:

```
assets/
├── brands/              (Logos de marcas autorizadas)
│   ├── brand-1.png
│   └── brand-2.png
│
├── decorations/         (Iconos decorativos)
│   ├── paw-left.png    (Huellita esquina superior izquierda)
│   ├── paw-right.png   (Huellita esquina superior derecha)
│   ├── star-top.png    (Estrella arriba del collar)
│   ├── heart-left.png  (Corazón izquierdo del collar - con animación pulse)
│   ├── heart-right.png (Corazón derecho del collar - con animación pulse)
│   └── bone-divider.png (Separador entre pasos - huesito decorativo)
│
└── collar-reference.jpg (Imagen de ejemplo al final del panel)
```

## 🎯 Ubicaciones Creativas Implementadas

### 1. **Esquinas Superiores** (Sutil y elegante)
- **Ubicación:** Esquinas superiores del configurador completo
- **Archivos:** `paw-left.png`, `paw-right.png`
- **Efecto:** Animación flotante suave
- **Sugerencia:** Huellitas de perro/gato, estrellas pequeñas, o iconos de tu marca
- **Tamaño recomendado:** 60x60px (PNG con transparencia)

### 2. **Alrededor del Collar** (Dinámico y llamativo)
- **Ubicación:** 
  - Arriba del collar: `star-top.png`
  - Izquierda del collar: `heart-left.png`
  - Derecha del collar: `heart-right.png`
- **Efectos:** 
  - Top: Flotación suave
  - Lados: Animación de pulso (latido)
- **Sugerencia:** Corazones, estrellas, brillos, rayos, o elementos relacionados con mascotas
- **Tamaño recomendado:** 35x35px

### 3. **Separadores entre Pasos** (Organizado y temático)
- **Ubicación:** Entre Paso 1-2 y Paso 2-3
- **Archivo:** `bone-divider.png`
- **Efecto:** Estático con opacidad suave
- **Sugerencia:** Huesito, línea con huellitas, flecha decorativa
- **Tamaño recomendado:** 80px ancho x altura proporcional

### 4. **Marcas Autorizadas** (Profesional y confiable)
- **Ubicación:** Debajo del teclado de letras
- **Archivos:** `brand-1.png`, `brand-2.png`
- **Efecto:** Hover con elevación y color
- **Sugerencia:** Logos de marcas, certificaciones, sellos de calidad
- **Tamaño recomendado:** Alto 40px, ancho proporcional

### 5. **Imagen de Referencia** (Inspiracional)
- **Ubicación:** Final del panel derecho
- **Archivo:** `collar-reference.jpg`
- **Efecto:** Bordes redondeados con sombra
- **Sugerencia:** Foto de collar terminado, mascota con collar, o collage
- **Tamaño recomendado:** Ancho 600px mínimo

## 🎨 Ideas Creativas por Tipo de Icono

### Si tienes **Huellitas** 🐾
```
✅ Esquinas superiores (flotantes)
✅ Alrededor del collar (pequeñas)
✅ Separadores (fila de huellitas)
```

### Si tienes **Corazones** 💖
```
✅ Lados del collar (con pulso)
✅ Esquinas superiores
✅ Entre secciones
```

### Si tienes **Estrellas** ⭐
```
✅ Arriba del collar (flotante)
✅ Esquinas del configurador
✅ Junto a títulos de pasos
```

### Si tienes **Huesitos** 🦴
```
✅ Separadores entre pasos (horizontal)
✅ Esquinas inferiores
✅ Alrededor del botón "Limpiar todo"
```

### Si tienes **Elementos geométricos**
```
✅ Fondos sutiles con opacidad baja
✅ Patrones repetitivos en bordes
✅ Marcos decorativos
```

## 💡 Consejos de Diseño

### Colores
- Usa PNG con transparencia
- Si tus iconos son monocromáticos, considera versiones en los colores del collar
- Mantén consistencia visual con la paleta: #FF015B, #00D6FF, #E1FF00, etc.

### Tamaños
- **Iconos esquinas:** 50-70px
- **Iconos collar:** 30-40px
- **Separadores:** 60-100px ancho
- **Logos marcas:** 35-50px alto
- **Imagen referencia:** Mínimo 600px ancho

### Estilo
- Mantén el mismo estilo gráfico (flat, line art, o ilustrado)
- Usa sombras sutiles si necesitas profundidad
- Evita saturar con demasiados elementos

## 🔧 Personalización Adicional

### Para agregar más iconos decorativos:

1. **En el panel de visualización:**
```php
<div class="custom-deco-spot">
    <img src="<?php echo plugins_url('assets/decorations/tu-icono.png', __FILE__); ?>" alt="">
</div>
```

2. **En los headers de pasos:**
Edita cada `<h4>` en el template y agrega:
```php
<h4>
    <img src="..." class="step-icon">
    <strong>Paso 1:</strong> Tamaño del collar
</h4>
```

3. **Como fondo del contenedor:**
```css
.wawas-container {
    background-image: url('assets/decorations/pattern-bg.png');
    background-size: 100px;
    background-repeat: repeat;
    opacity: 0.05;
}
```

## 📋 Checklist de Implementación

- [ ] Crear carpeta `assets/brands/`
- [ ] Crear carpeta `assets/decorations/`
- [ ] Colocar 2 logos en `brands/`
- [ ] Colocar 6 iconos decorativos en `decorations/`
- [ ] Colocar imagen de referencia `collar-reference.jpg`
- [ ] Verificar que todos los PNG tengan transparencia
- [ ] Probar hover en marcas autorizadas
- [ ] Verificar animaciones flotantes
- [ ] Ajustar tamaños si es necesario

## 🎬 Animaciones Disponibles

Ya implementadas y listas para usar:
- **float:** Movimiento vertical suave (iconos esquinas y collar top)
- **pulse:** Latido de escala y opacidad (corazones laterales)

¿Quieres animaciones adicionales? Puedo agregar:
- Rotación suave
- Brillo intermitente
- Efecto de entrada
- Parallax en scroll

---

💌 **Tip Final:** Menos es más. Empieza con los elementos esenciales y ve agregando gradualmente para mantener un diseño limpio y profesional.
