<?php
/**
 * Script de prueba para generar letras glitter
 * Ejecutar desde línea de comandos: php test-glitter-generator.php
 * O acceder desde navegador (requiere servidor web)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "🔧 Script de generación de letras glitter\n";
echo "==========================================\n\n";

// Verificar Imagick
if (!class_exists('Imagick')) {
    die("❌ ERROR: Imagick no está instalado en este servidor PHP.\n" .
        "   Instálalo con: pecl install imagick\n" .
        "   O contacta a tu proveedor de hosting.\n");
}

echo "✓ Imagick está disponible\n";
echo "  Versión: " . Imagick::getVersion()['versionString'] . "\n\n";

// Rutas
$base_dir = __DIR__;
$gold_texture_dir = $base_dir . '/assets/texturas/dorada/';
$silver_texture_dir = $base_dir . '/assets/texturas/plateada/';
$output_gold = $base_dir . '/assets/charms/glitter/gold/';
$output_silver = $base_dir . '/assets/charms/glitter/silver/';
$font_path = $base_dir . '/assets/fonts/custom_font.ttf';

// Buscar texturas
$gold_files = glob($gold_texture_dir . '*.png');
$silver_files = glob($silver_texture_dir . '*.png');

if (empty($gold_files)) {
    die("❌ No se encontró textura dorada en: $gold_texture_dir\n");
}
if (empty($silver_files)) {
    die("❌ No se encontró textura plateada en: $silver_texture_dir\n");
}

$gold_texture = $gold_files[0];
$silver_texture = $silver_files[0];

echo "✓ Textura dorada: " . basename($gold_texture) . "\n";
echo "✓ Textura plateada: " . basename($silver_texture) . "\n";

// Verificar fuente (opcional)
if (file_exists($font_path)) {
    echo "✓ Fuente personalizada: custom_font.ttf\n";
} else {
    echo "⚠ No hay fuente personalizada, usando fuente por defecto del sistema\n";
    $font_path = null;
}

echo "\n";

// Caracteres a generar
$chars = array_merge(range('A','Z'), array('Ñ'), range('0','9'));
$sizes = array(128, 256);

echo "📝 Generando " . count($chars) . " caracteres en " . count($sizes) . " tamaños...\n\n";

/**
 * Función de generación
 */
function generate_glitter_letter($texture_path, $output_path, $font_path, $char, $size) {
    try {
        // Crear canvas transparente
        $canvas = new Imagick();
        $canvas->newImage($size, $size, new ImagickPixel('transparent'));
        $canvas->setImageFormat('png');

        // Cargar y escalar textura
        $texture = new Imagick($texture_path);
        
        // Tile la textura si es más pequeña que el canvas
        $tex_width = $texture->getImageWidth();
        $tex_height = $texture->getImageHeight();
        
        if ($tex_width < $size || $tex_height < $size) {
            // Escalar textura para cubrir el canvas
            $scale = max($size / $tex_width, $size / $tex_height) * 1.5;
            $texture->scaleImage((int)($tex_width * $scale), (int)($tex_height * $scale));
        }
        
        // Recortar textura al tamaño del canvas (centrado)
        $texture->cropImage($size, $size, 
            (int)(($texture->getImageWidth() - $size) / 2), 
            (int)(($texture->getImageHeight() - $size) / 2)
        );

        // Crear máscara con la letra
        $mask = new Imagick();
        $mask->newImage($size, $size, new ImagickPixel('black'));
        $mask->setImageFormat('png');

        $draw = new ImagickDraw();
        
        // Configurar fuente
        if ($font_path && file_exists($font_path)) {
            $draw->setFont($font_path);
        }
        
        // Tamaño de fuente proporcional
        $font_size = (int)($size * 0.7);
        $draw->setFontSize($font_size);
        $draw->setFillColor(new ImagickPixel('white'));
        $draw->setGravity(Imagick::GRAVITY_CENTER);
        $draw->setFontWeight(700); // Bold

        // Dibujar letra en la máscara
        $mask->annotateImage($draw, 0, 0, 0, $char);

        // Aplicar textura usando la máscara
        // Copiar la textura al canvas
        $canvas->compositeImage($texture, Imagick::COMPOSITE_OVER, 0, 0);
        
        // Usar la máscara para recortar (mantener solo donde está la letra)
        $canvas->compositeImage($mask, Imagick::COMPOSITE_DSTIN, 0, 0);

        // Guardar
        $canvas->writeImage($output_path);

        // Limpiar memoria
        $canvas->clear();
        $mask->clear();
        $texture->clear();
        $draw->clear();

        return true;
    } catch (Exception $e) {
        echo "  ❌ Error en '$char': " . $e->getMessage() . "\n";
        return false;
    }
}

// Generar letras doradas
echo "🌟 Generando letras doradas...\n";
$count_gold = 0;
foreach ($sizes as $size) {
    foreach ($chars as $char) {
        $filename = $char . "_" . $size . ".png";
        $output_path = $output_gold . $filename;
        
        if (generate_glitter_letter($gold_texture, $output_path, $font_path, $char, $size)) {
            $count_gold++;
            echo "  ✓ $filename\n";
        }
    }
}

echo "\n";

// Generar letras plateadas
echo "⚪ Generando letras plateadas...\n";
$count_silver = 0;
foreach ($sizes as $size) {
    foreach ($chars as $char) {
        $filename = $char . "_" . $size . ".png";
        $output_path = $output_silver . $filename;
        
        if (generate_glitter_letter($silver_texture, $output_path, $font_path, $char, $size)) {
            $count_silver++;
            echo "  ✓ $filename\n";
        }
    }
}

echo "\n";
echo "==========================================\n";
echo "✅ COMPLETADO\n";
echo "   Letras doradas generadas: $count_gold\n";
echo "   Letras plateadas generadas: $count_silver\n";
echo "\n";
echo "📁 Ubicaciones:\n";
echo "   Gold: $output_gold\n";
echo "   Silver: $output_silver\n";
