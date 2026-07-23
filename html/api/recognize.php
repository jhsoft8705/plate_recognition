<?php
/**
 * recognize.php — Orquestador de reconocimiento vehicular
 * Plate Recognizer (placa externa) + Vehicle Vision (tipo, color, marca, placa propia)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$plateApiToken = getenv('PLATE_API_TOKEN') ?: 'c99cbae01effda79cc4e2b0df3d3efeb55de7a62';
$visionApiUrl  = getenv('VISION_API_URL') ?: 'http://vehicle_vision:5000/api/detect';
$plateApiUrl   = getenv('PLATE_API_URL') ?: 'http://vehicle_vision:5000/api/read-plate';

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No se recibió una imagen válida.']);
    exit;
}

try {
    $compressedPath = compressImage($_FILES['image']['tmp_name'], $_FILES['image']['type']);

    // ── 1. PLATE RECOGNIZER (placa externa, gratis 500/mes) ──
    $ch = curl_init('https://api.platerecognizer.com/v1/plate-reader/');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Authorization: Token ' . $plateApiToken],
        CURLOPT_POSTFIELDS => ['upload' => new CURLFile($compressedPath, $_FILES['image']['type'], $_FILES['image']['name'])],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $plateResponse = curl_exec($ch);
    $plateHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $plateData = ($plateHttpCode === 200 || $plateHttpCode === 201) ? json_decode($plateResponse, true) : null;

    // ── 2. VEHICLE VISION (tipo + color + marca) ──
    $visionResult = null;
    $visionCh = curl_init($visionApiUrl);
    curl_setopt_array($visionCh, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => ['image' => new CURLFile($compressedPath, $_FILES['image']['type'], $_FILES['image']['name'])],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $visionResponse = curl_exec($visionCh);
    $visionHttpCode = curl_getinfo($visionCh, CURLINFO_HTTP_CODE);
    curl_close($visionCh);

    if ($visionHttpCode === 200) {
        $visionData = json_decode($visionResponse, true);
        if ($visionData) {
            $visionResult = !empty($visionData['vehicles'][0]) ? $visionData['vehicles'][0] : null;
        }
    }

    // ── 3. PLATE PROPIO (nuestro detector de placas, si está entrenado) ──
    $ourPlate = null;
    $plateCh = curl_init($plateApiUrl);
    curl_setopt_array($plateCh, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => ['image' => new CURLFile($compressedPath, $_FILES['image']['type'], $_FILES['image']['name'])],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $ourPlateResponse = curl_exec($plateCh);
    $ourPlateHttpCode = curl_getinfo($plateCh, CURLINFO_HTTP_CODE);
    curl_close($plateCh);

    if ($ourPlateHttpCode === 200) {
        $ourPlateData = json_decode($ourPlateResponse, true);
        if ($ourPlateData && !empty($ourPlateData['plates'][0]['plate'])) {
            $ourPlate = $ourPlateData['plates'][0];
        }
    }

    // Limpiar temporal
    if ($compressedPath !== $_FILES['image']['tmp_name']) {
        @unlink($compressedPath);
    }

    // ── 4. ARMAR RESPUESTA ──
    $result = buildResponse($plateData, $visionResult, $ourPlate);
    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// ── Funciones ──

function compressImage(string $sourcePath, string $mimeType): string
{
    $maxSize = 900 * 1024;
    if (filesize($sourcePath) <= $maxSize) return $sourcePath;

    $info = getimagesize($sourcePath);
    if (!$info) return $sourcePath;

    $width = $info[0];
    $height = $info[1];

    switch ($mimeType) {
        case 'image/jpeg': case 'image/jpg': $src = @imagecreatefromjpeg($sourcePath); break;
        case 'image/png':  $src = @imagecreatefrompng($sourcePath); break;
        case 'image/webp': $src = @imagecreatefromwebp($sourcePath); break;
        default: return $sourcePath;
    }
    if (!$src) return $sourcePath;

    $maxDim = 1920;
    $ratio = min($maxDim / $width, $maxDim / $height, 1);
    if ($ratio < 1) {
        $newW = (int)($width * $ratio);
        $newH = (int)($height * $ratio);
        $dst = imagecreatetruecolor($newW, $newH);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);
        imagedestroy($src);
        $src = $dst;
    }

    $tmpPath = tempnam(sys_get_temp_dir(), 'plate_') . '.jpg';
    $quality = 80;
    imagejpeg($src, $tmpPath, $quality);
    imagedestroy($src);

    $attempts = 0;
    while (filesize($tmpPath) > $maxSize && $quality > 20 && $attempts < 5) {
        $quality -= 15;
        $img = imagecreatefromjpeg($tmpPath);
        imagejpeg($img, $tmpPath, $quality);
        imagedestroy($img);
        $attempts++;
    }
    return $tmpPath;
}

function buildResponse(?array $plateData, ?array $visionResult, ?array $ourPlate): array
{
    $result = [
        'success' => true,
        'plate' => null,
        'brand' => null,
        'model' => null,
        'color' => null,
        'vehicleType' => null,
    ];

    // ── Placa: prioridad a nuestro detector, fallback Plate Recognizer ──
    if ($ourPlate && !empty($ourPlate['plate'])) {
        $result['plate'] = [
            'value' => $ourPlate['plate'],
            'confidence' => round($ourPlate['confidence'], 2),
            'autoComplete' => $ourPlate['confidence'] >= 0.80,
            'source' => 'propio'
        ];
    } elseif ($plateData && !empty($plateData['results'][0]['plate'])) {
        $plateConf = $plateData['results'][0]['score'] ?? 0;
        $plateText = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $plateData['results'][0]['plate']));
        $result['plate'] = [
            'value' => $plateText,
            'confidence' => round($plateConf, 2),
            'autoComplete' => $plateConf >= 0.80,
            'source' => 'platerecognizer'
        ];
    }

    // ── Marca desde Plate Recognizer (solo plan pago) o nuestro modelo ──
    if ($plateData && !empty($plateData['results'][0]['vehicle']['make'][0]['name'])) {
        $bc = $plateData['results'][0]['vehicle']['make'][0]['score'] ?? 0;
        $result['brand'] = [
            'value' => $plateData['results'][0]['vehicle']['make'][0]['name'],
            'confidence' => round($bc, 2),
            'autoComplete' => $bc >= 0.80,
            'source' => 'platerecognizer'
        ];
    } elseif ($visionResult && !empty($visionResult['brand'])) {
        $result['brand'] = [
            'value' => $visionResult['brand'],
            'confidence' => round($visionResult['brandConfidence'], 2),
            'autoComplete' => $visionResult['brandConfidence'] >= 0.70,
            'source' => 'vehiclevision'
        ];
    }

    // ── Modelo solo desde Plate Recognizer (plan pago) ──
    if ($plateData && !empty($plateData['results'][0]['vehicle']['model'][0]['name'])) {
        $mc = $plateData['results'][0]['vehicle']['model'][0]['score'] ?? 0;
        $result['model'] = [
            'value' => $plateData['results'][0]['vehicle']['model'][0]['name'],
            'confidence' => round($mc, 2),
            'autoComplete' => $mc >= 0.70,
        ];
    }

    // ── Tipo y color desde Vehicle Vision ──
    if ($visionResult) {
        $result['vehicleType'] = $visionResult['type'] ? [
            'value' => $visionResult['type'],
            'confidence' => round($visionResult['detectionConfidence'], 2),
            'autoComplete' => $visionResult['detectionConfidence'] >= 0.70,
        ] : null;

        $result['color'] = $visionResult['color'] ? [
            'value' => $visionResult['color'],
            'confidence' => round($visionResult['colorConfidence'], 2),
            'autoComplete' => $visionResult['colorConfidence'] >= 0.60,
        ] : null;
    }

    return $result;
}
