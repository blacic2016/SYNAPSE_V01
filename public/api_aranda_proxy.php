<?php
/**
 * Aranda API Proxy for Testing - CMDB VILASECA
 * Location: /var/www/html/VILASECA/CMDBPRnew/public/api_aranda_proxy.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';

// Force authentication
require_login();

header('Content-Type: application/json; charset=utf-8');

// Read input
$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true);

if (!$data) {
    echo json_encode([
        'success' => false,
        'error' => 'Parámetros inválidos. Se requiere JSON en el cuerpo.'
    ]);
    exit;
}

$url = $data['url'] ?? '';
$method = strtoupper($data['method'] ?? 'GET');
$token = $data['token'] ?? '';
$tenant = $data['tenant'] ?? '';
$body = $data['body'] ?? '';

if (empty($url)) {
    echo json_encode([
        'success' => false,
        'error' => 'La URL es requerida.'
    ]);
    exit;
}

// Initialize cURL
$ch = curl_init();

// Set cURL options
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local/QA testing environments
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

// Build headers
$headers = [
    'content-type: application/json',
];

if (!empty($token)) {
    $headers[] = 'X-Authorization: Bearer ' . $token;
    $headers[] = 'Authorization: Bearer ' . $token;
}

if (!empty($tenant)) {
    $headers[] = 'x-aranda-tenant-alias: ' . $tenant;
}

curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// Set body for write methods
if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH']) && !empty($body)) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
}

// Track response headers
$response_headers = [];
curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$response_headers) {
    $len = strlen($header);
    $parts = explode(':', $header, 2);
    if (count($parts) === 2) {
        $response_headers[trim($parts[0])] = trim($parts[1]);
    }
    return $len;
});

// Execute request
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);

curl_close($ch);

if ($response === false) {
    echo json_encode([
        'success' => false,
        'status_code' => 0,
        'error' => 'Error de cURL: ' . $curl_error
    ]);
} else {
    // Try to decode response as JSON
    $decoded_response = json_decode($response, true);
    
    echo json_encode([
        'success' => true,
        'status_code' => $http_code,
        'response' => $decoded_response !== null ? $decoded_response : $response,
        'raw_response' => $response,
        'headers' => $response_headers
    ]);
}
exit;
