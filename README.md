# 🐾 Configurador de Collar Personalizado

Plugin de WordPress para crear collares personalizados con 3 pasos: Tamaño → Color → Charms

---

## 🚀 Instalación (3 pasos)

### 1. Subir archivos
Sube la carpeta completa a: `/wp-content/plugins/collar-configurator/`

### 2. Activar plugin
**Plugins > Plugins instalados** → Activar "Configurador de Collar"

### 3. Usar shortcode
Pega donde quieras el configurador: `[collar_configurador]`

---

## 📁 Estructura Requerida

```
collar-configurator/
├── collar-configurator.php          ← Plugin principal
├── configurator-template.php        ← Template HTML
├── collar-configurator-new.css      ← Estilos
├── collar-configurator-new.js       ← JavaScript
├── collar-configurator-new.html     ← Demo standalone (opcional)
└── assets/emojis/                   ← Imágenes de emojis (REQUERIDO)
    ├── lila/, azul/, verde/
    ├── amarillo/, naranja/, rojo/
    └── rosa/, blanco/
```

---

## 🎨 Uso con Elementor

1. Arrastra widget **"Shortcode"**
2. Pega: `[collar_configurador]`
3. ¡Listo! ✅

### Layout de 2 columnas:
- **Izquierda:** Galería de imágenes (Elementor)
- **Derecha:** Shortcode del configurador

---

## ⚙️ Personalización

```
[collar_configurador]                                 ← Con título
[collar_configurador show_title="false"]             ← Sin título
[collar_configurador title="Crea tu Collar Único"]   ← Título custom
```

---

## 🛒 WooCommerce (Opcional)

1. Crea producto "Collar Personalizado"
2. Copia el ID del producto
3. **Ajustes > Configurador de Collar** → Pegar ID
4. El carrito guardará automáticamente la personalización

---

## ❓ Problemas Comunes

### No se ve el configurador:
- ✅ Plugin activado
- ✅ Limpia caché (Ctrl + F5)
- ✅ Elementor: Tools > Regenerate Files

### No se ven emojis:
- ✅ Carpeta `assets/emojis/` completa
- ✅ Permisos: 755 (carpetas), 644 (archivos)

### Debugging:
- Abre consola (F12) → Busca errores
- Network tab → Verifica que CSS/JS carguen (200 OK)

---

## 🎯 Características

✅ 3 pasos intuitivos  
✅ Vista previa en tiempo real  
✅ 8 colores de charms  
✅ 6 colores de collar  
✅ Responsive (móvil/tablet/desktop)  
✅ Compatible con Elementor  
✅ Integración WooCommerce

---

## 📝 Versión 1.0.0

**Archivos esenciales:** Solo necesitas los 4 archivos principales + carpeta assets/emojis/  
**Uso:** Shortcode simple `[collar_configurador]`  
**Soporte:** Compatible con WordPress 5.0+ y WooCommerce 3.0+

---

¿Dudas? Abre F12 en tu navegador y revisa la consola para errores específicos.