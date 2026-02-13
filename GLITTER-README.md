# Generador de Letras Glitter

Este sistema genera letras con efecto glitter (dorado y plateado) para usar en los charms del configurador de collares.

## 📋 Requisitos

- PHP 7.2+
- **Imagick** (extensión de PHP)
- Texturas en `assets/texturas/dorada/` y `assets/texturas/plateada/`

## 🔧 Verificar si Imagick está instalado

### En línea de comandos:
```bash
php -m | grep imagick
```

Si no aparece nada, Imagick NO está instalado.

### Instalar Imagick:

**En hosting compartido:** Contacta a tu proveedor (la mayoría lo tiene disponible).

**En servidor local/VPS:**
```bash
# Ubuntu/Debian
sudo apt-get install php-imagick
sudo service apache2 restart

# CentOS/RHEL
sudo yum install php-imagick
sudo systemctl restart httpd

# macOS (con Homebrew)
brew install imagemagick
pecl install imagick

# Windows (XAMPP/WAMP)
# Descargar DLL desde https://pecl.php.net/package/imagick
# Copiar a php/ext/ y habilitar en php.ini
```

## 🚀 Métodos de generación

### Método 1: Script standalone (recomendado para pruebas)

```bash
cd c:\Users\baque\Downloads\collar-configurator
php test-glitter-generator.php
```

Este script:
- Verifica que Imagick esté instalado
- Busca las texturas automáticamente
- Genera todas las letras A-Z, Ñ, 0-9
- Crea PNGs en 2 tamaños (128px y 256px)
- Guarda en `assets/charms/glitter/gold/` y `.../silver/`

### Método 2: Desde el admin de WordPress

1. Ve a **Ajustes → Configurador de Collar**
2. (Opcional) Sube una fuente TTF personalizada
3. Haz clic en **"Generar ahora"**
4. Espera a que se complete (puede tardar 1-2 minutos)

## 📁 Estructura de carpetas

```
assets/
├── texturas/
│   ├── dorada/          ← Poner aquí imágenes PNG con textura dorada
│   └── plateada/        ← Poner aquí imágenes PNG con textura plateada
├── fonts/
│   └── custom_font.ttf  ← (Opcional) Fuente personalizada
└── charms/
    └── glitter/
        ├── gold/        ← Letras doradas generadas (A_128.png, B_256.png, etc.)
        └── silver/      ← Letras plateadas generadas
```

## 🎨 Personalización

### Cambiar tamaños de salida

Edita `test-glitter-generator.php` o `collar-configurator.php`:

```php
$sizes = array(128, 256, 512); // Agregar más tamaños
```

### Agregar más caracteres

```php
$chars = array_merge(
    range('A','Z'), 
    array('Ñ'), 
    range('0','9'),
    array('@', '#', '&', '!')  // Agregar símbolos
);
```

### Usar fuente personalizada

1. Descarga una fuente TTF (ej: Arial Black, Impact, etc.)
2. Copia a `assets/fonts/custom_font.ttf`
3. Regenera las letras

## ✅ Verificación

Después de generar, verifica:

```bash
# Ver cuántas letras se generaron
ls assets/charms/glitter/gold/*.png | wc -l
ls assets/charms/glitter/silver/*.png | wc -l

# Debería mostrar: 74 archivos por carpeta (37 caracteres × 2 tamaños)
```

## 🐛 Solución de problemas

### "Imagick no está disponible"
→ Instala la extensión Imagick (ver arriba)

### "No se encontraron texturas"
→ Verifica que haya al menos 1 archivo PNG en `assets/texturas/dorada/` y `.../plateada/`

### Letras salen pixeladas
→ Usa texturas de mayor resolución (mínimo 512×512px, ideal 1024×1024px)

### Letras salen con bordes
→ Asegúrate de que la textura tenga fondo transparente o sin esquinas redondeadas

### Script tarda mucho
→ Normal. Genera 148 imágenes (37 caracteres × 2 tamaños × 2 colores). Puede tardar 1-3 minutos.

## 📝 Notas técnicas

- Las letras se generan con **canal alpha** (fondo transparente)
- La textura se aplica solo en la forma de la letra
- Se usan dos tamaños para optimizar rendimiento: 128px (vista previa) y 256px (detalle)
- El sistema usa la primera imagen PNG que encuentre en cada carpeta de textura

## 🔄 Regenerar letras

Si cambias las texturas o fuente, simplemente ejecuta de nuevo:

```bash
php test-glitter-generator.php
```

Los archivos existentes serán sobrescritos.
