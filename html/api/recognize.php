<?php
/**
 * recognize.php — Endpoint para reconocimiento vehicular
 * Plate Recognizer (placa) + Vehicle Vision (tipo + color)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$plateApiToken = getenv('PLATE_API_TOKEN') ?: 'c99cbae01effda79cc4e2b0df3d3efeb55de7a62';
$visionApiUrl  = getenv('VISION_API_URL') ?: 'http://vehicle_vision:5000/api/detect';

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No se recibió una imagen válida.']);
    exit;
}

try {
    $compressedPath = compressImage($_FILES['image']['tmp_name'], $_FILES['image']['type']);

    // ── 1. PLATE RECOGNIZER (placa) ──
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

    if ($plateHttpCode !== 200 && $plateHttpCode !== 201) {
        throw new Exception('Plate API respondió con código ' . $plateHttpCode);
    }

    $plateData = json_decode($plateResponse, true);
    if (!$plateData) {
        throw new Exception('Respuesta inválida de Plate API');
    }

    // ── 2. VEHICLE VISION (tipo + color) ──
    $visionResult = null;
    $visionCurl = curl_init($visionApiUrl);
    curl_setopt_array($visionCurl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => ['image' => new CURLFile($compressedPath, $_FILES['image']['type'], $_FILES['image']['name'])],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $visionResponse = curl_exec($visionCurl);
    $visionHttpCode = curl_getinfo($visionCurl, CURLINFO_HTTP_CODE);
    curl_close($visionCurl);

    if ($visionHttpCode === 200) {
        $visionData = json_decode($visionResponse, true);
        if ($visionData && !empty($visionData['vehicles'])) {
            $visionResult = $visionData['vehicles'][0]; // mejor detección
        }
    }

    // Limpiar temporal
    if ($compressedPath !== $_FILES['image']['tmp_name']) {
        @unlink($compressedPath);
    }

    // ── 3. ARMAR RESPUESTA ──
    $result = buildResponse($plateData, $visionResult);
    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

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

function buildResponse(array $plateData, ?array $visionResult): array
{
    $result = [
        'success' => true,
        'plate' => null,
        'brand' => null,
        'model' => null,
        'color' => null,
        'vehicleType' => null,
    ];

    // Placa desde Plate Recognizer
    if (!empty($plateData['results'][0]['plate'])) {
        $plateConf = $plateData['results'][0]['score'] ?? 0;
        $plateText = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $plateData['results'][0]['plate']));
        $result['plate'] = [
            'value' => $plateText,
            'confidence' => round($plateConf, 2),
            'autoComplete' => $plateConf >= 0.80,
        ];
    }

    // Marca/modelo solo si Plate Recognizer los devuelve (plan pago)
    if (!empty($plateData['results'][0]['vehicle']['make'][0]['name'])) {
        $bc = $plateData['results'][0]['vehicle']['make'][0]['score'] ?? 0;
        $result['brand'] = [
            'value' => $plateData['results'][0]['vehicle']['make'][0]['name'],
            'confidence' => round($bc, 2),
            'autoComplete' => $bc >= 0.80,
        ];
    }
    if (!empty($plateData['results'][0]['vehicle']['model'][0]['name'])) {
        $mc = $plateData['results'][0]['vehicle']['model'][0]['score'] ?? 0;
        $result['model'] = [
            'value' => $plateData['results'][0]['vehicle']['model'][0]['name'],
            'confidence' => round($mc, 2),
            'autoComplete' => $mc >= 0.70,
        ];
    }

    // Tipo y color desde Vehicle Vision (nuestro servicio)
    if ($visionResult) {
        $result['vehicleType'] = [
            'value' => $visionResult['type'],
            'confidence' => round($visionResult['detectionConfidence'], 2),
            'autoComplete' => $visionResult['detectionConfidence'] >= 0.70,
        ];

        $result['color'] = [
            'value' => $visionResult['color'],
            'confidence' => round($visionResult['colorConfidence'], 2),
            'autoComplete' => $visionResult['colorConfidence'] >= 0.60,
        ];
    }

    return $result;
}
