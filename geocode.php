<?php
/**
 * Geocoding Proxy
 * Proxies geocoding requests to OpenStreetMap Nominatim API to avoid CORS issues
 */

// Set headers to prevent caching and allow CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get parameters
$locationName = isset($_GET['q']) ? trim($_GET['q']) : '';
$isInstitution = isset($_GET['institution']) ? filter_var($_GET['institution'], FILTER_VALIDATE_BOOLEAN) : false;
$countryHint = isset($_GET['country']) ? trim($_GET['country']) : 'in';

if (empty($locationName)) {
    http_response_code(400);
    echo json_encode(['error' => 'Location name is required']);
    exit;
}

// Build query
$query = $locationName;
if (!$isInstitution && !stripos($query, 'india') && $countryHint) {
    $query = $query . ', India';
}

// Build URL
$params = [
    'q' => $query,
    'format' => 'json',
    'limit' => '1',
    'addressdetails' => '1'
];

if (!$isInstitution && $countryHint) {
    $params['countrycodes'] = $countryHint;
}

$url = 'https://nominatim.openstreetmap.org/search?' . http_build_query($params);

// Initialize cURL
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_USERAGENT => 'TechVyom-Alumni-Map/1.0 (Contact: admin@techvyom.com)',
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Accept-Language: en-US,en;q=0.9'
    ],
    CURLOPT_TIMEOUT => 10,
    CURLOPT_CONNECTTIMEOUT => 5
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    http_response_code(500);
    echo json_encode(['error' => 'Geocoding service error: ' . $error]);
    exit;
}

if ($httpCode !== 200) {
    // Log the error but return empty array instead of failing completely
    error_log("Geocoding failed for '{$locationName}': HTTP {$httpCode}");
    http_response_code(200); // Return 200 with empty array
    echo json_encode([]);
    exit;
}

$data = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("Invalid JSON response for '{$locationName}': " . json_last_error_msg());
    http_response_code(200); // Return 200 with empty array
    echo json_encode([]);
    exit;
}

// Return the response (could be empty array if no results)
if (empty($data)) {
    echo json_encode([]);
} else {
    echo json_encode($data);
}

