<?php
/**
 * Generador de letras glitter usando GD (incluido en XAMPP)
 * Genera A-Z, Ñ, 0-9 en tamaños 128px y 256px
 */

echo "🎨 Generador de letras glitter (GD)\n";
echo "====================================\n\n";

// Verificar que GD esté disponible
if (!extension_loaded('gd')) {
    die("❌ ERROR: GD no está instalado.\n");
}

echo "✓ GD está disponible\n\n";

// Configuración
$baseDir = __DIR__;
$texturesGold = $baseDir . '/assets/texturas/dorada';
$texturesSilver = $baseDir . '/assets/texturas/plateada';
$outputGold = $baseDir . '/assets/charms/glitter/gold';
$outputSilver = $baseDir . '/assets/charms/glitter/silver';

// Verificar que existan las texturas
if (!is_dir($texturesGold) || !is_dir($texturesSilver)) {
    die("❌ ERROR: No se encontraron las carpetas de texturas.\n");
}

// Buscar archivos de textura
$goldFiles = glob($texturesGold . '/*.png');
$silverFiles = glob($texturesSilver . '/*.png');

if (empty($goldFiles) || empty($silverFiles)) {
    die("❌ ERROR: No se encontraron archivos PNG en las carpetas de texturas.\n");
}

$goldTexture = $goldFiles[0];
$silverTexture = $silverFiles[0];

echo "📁 Usando texturas:\n";
echo "   Gold: " . basename($goldTexture) . "\n";
echo "   Silver: " . basename($silverTexture) . "\n\n";

// Caracteres a generar
$characters = array_merge(
    range('A', 'Z'),
    ['Ñ'],
    range('0', '9')
);

$sizes = [128, 256];

/**
 * Genera una letra glitter usando GD
 */
function generateGlitterLetter($char, $size, $texturePath, $outputPath) {
    // Crear imagen transparente
    $img = imagecreatetruecolor($size, $size);
    imagesavealpha($img, true);
    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $transparent);
    
    // Cargar textura
    $texture = imagecreatefrompng($texturePath);
    if (!$texture) {
        return false;
    }
    
    // Calcular tamaño de fuente (75% del tamaño de imagen)
    $fontSize = $size * 0.6;
    
    // Lista de fuentes del sistema (Windows)
    $fontPaths = [
        'C:/Windows/Fonts/segoeui.ttf',      // Segoe UI
        'C:/Windows/Fonts/arial.ttf',        // Arial
        'C:/Windows/Fonts/calibri.ttf',      // Calibri
        'C:/Windows/Fonts/verdana.ttf',      // Verdana
    ];
    
    $fontPath = null;
    foreach ($fontPaths as $path) {
        if (file_exists($path)) {
            $fontPath = $path;
            break;
        }
    }
    
    if (!$fontPath) {
        imagedestroy($img);
        imagedestroy($texture);
        return false;
    }
    
    // Crear máscara con la letra
    $mask = imagecreatetruecolor($size, $size);
    imagesavealpha($mask, true);
    $maskTransparent = imagecolorallocatealpha($mask, 0, 0, 0, 127);
    imagefill($mask, 0, 0, $maskTransparent);
    
    $white = imagecolorallocate($mask, 255, 255, 255);
    
    // Calcular posición centrada
    $bbox = imagettfbbox($fontSize, 0, $fontPath, $char);
    $textWidth = abs($bbox[4] - $bbox[0]);
    $textHeight = abs($bbox[5] - $bbox[1]);
    $x = ($size - $textWidth) / 2 - $bbox[0];
    $y = ($size - $textHeight) / 2 - $bbox[5];
    
    // Dibujar letra en blanco sobre fondo transparente
    imagettftext($mask, $fontSize, 0, $x, $y, $white, $fontPath, $char);
    
    // Redimensionar textura al tamaño necesario
    $textureResized = imagecreatetruecolor($size, $size);
    imagecopyresampled(
        $textureResized, $texture,
        0, 0, 0, 0,
        $size, $size,
        imagesx($texture), imagesy($texture)
    );
    
    // Aplicar textura usando la máscara
    for ($px = 0; $px < $size; $px++) {
        for ($py = 0; $py < $size; $py++) {
            $maskColor = imagecolorat($mask, $px, $py);
            $maskAlpha = ($maskColor >> 24) & 0xFF;
            
            // Si el pixel de la máscara es opaco (letra), copiar textura
            if ($maskAlpha < 127) {
                $textureColor = imagecolorat($textureResized, $px, $py);
                imagesetpixel($img, $px, $py, $textureColor);
            }
        }
    }
    
    // Guardar imagen
    $success = imagepng($img, $outputPath, 9);
    
    // Liberar memoria
    imagedestroy($img);
    imagedestroy($mask);
    imagedestroy($texture);
    imagedestroy($textureResized);
    
    return $success;
}

// Generar letras doradas
echo "🌟 Generando letras DORADAS...\n";
$goldCount = 0;
foreach ($sizes as $size) {
    foreach ($characters as $char) {
        $filename = "{$char}_{$size}.png";
        $outputPath = $outputGold . '/' . $filename;
        
        if (generateGlitterLetter($char, $size, $goldTexture, $outputPath)) {
            $goldCount++;
            echo "  ✓ {$filename}\n";
        } else {
            echo "  ✗ Error en {$filename}\n";
        }
    }
}

echo "\n";

// Generar letras plateadas
echo "⚪ Generando letras PLATEADAS...\n";
$silverCount = 0;
foreach ($sizes as $size) {
    foreach ($characters as $char) {
        $filename = "{$char}_{$size}.png";
        $outputPath = $outputSilver . '/' . $filename;
        
        if (generateGlitterLetter($char, $size, $silverTexture, $outputPath)) {
            $silverCount++;
            echo "  ✓ {$filename}\n";
        } else {
            echo "  ✗ Error en {$filename}\n";
        }
    }
}

echo "\n";
echo "====================================\n";
echo "✅ COMPLETADO\n";
echo "   Gold: {$goldCount} archivos\n";
echo "   Silver: {$silverCount} archivos\n";
echo "   Total: " . ($goldCount + $silverCount) . " archivos\n";
echo "\n";
echo "📁 Ubicación:\n";
echo "   {$outputGold}\n";
echo "   {$outputSilver}\n";
