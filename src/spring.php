<?php

declare(strict_types=1);

require_once __DIR__ . '/SpringCourier.php';

$order = [
    'sender_company' => 'BaseLinker',
    'sender_fullname' => 'Jan Kowalski',
    'sender_address' => 'Kopernika 10',
    'sender_city' => 'Gdansk',
    'sender_postalcode' => '80208',
    'sender_email' => '',
    'sender_phone' => '666666666',

    'delivery_company' => 'Spring GDS',
    'delivery_fullname' => 'Maud Driant',
    'delivery_address' => 'Strada Foisorului, Nr. 16, Bl. F11C, Sc. 1, Ap. 10',
    'delivery_city' => 'Bucuresti, Sector 3',
    'delivery_postalcode' => '031179',
    'delivery_country' => 'RO',
    'delivery_email' => 'john@doe.com',
    'delivery_phone' => '555555555',
];

$params = [
    'label_format' => 'PDF',
    'service' => 'PPTT',
];

function loadApiKey(): ?string
{
    $envKey = getenv('API_KEY');
    if ($envKey !== false && trim($envKey) !== '') {
        return trim($envKey);
    }

    $dotEnv = __DIR__ . '/.env';
    if (is_readable($dotEnv)) {
        $lines = file($dotEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                [$k, $v] = explode('=', $line, 2);
                if (trim($k) === 'API_KEY') {
                    return trim($v);
                }
            }
        }
    }

    $apiKeyFile = __DIR__ . '/api.key';
    if (is_readable($apiKeyFile)) {
        $key = trim((string) file_get_contents($apiKeyFile));
        if ($key !== '') {
            return $key;
        }
    }

    return null;
}

$loadedKey = loadApiKey();
if ($loadedKey !== null) {
    $params['api_key'] = $loadedKey;
}

// Create courier object
$courier = new SpringCourier($params['api_key'], $params['service'], $params['label_format']);

// Main flow: create shipment and download label
try {
    $resp = $courier->newPackage($order, $params);

    // Expecting Shipment.TrackingNumber in response
    $tracking = $resp['Shipment']['TrackingNumber'] ?? null;
    if (empty($tracking)) {
        echo 'Shipment created but tracking number missing.';
        if (!empty($resp)) {
            echo '<pre>' . htmlspecialchars(json_encode($resp, JSON_PRETTY_PRINT)) . '</pre>';
        }
        exit;
    }

    // Output label (forces download)
    $courier->packagePDF((string) $tracking);
    exit;

} catch (RuntimeException $e) {
    // Readable error to browser as required by the task

    http_response_code(500);
    echo '<h1>Error creating shipment</h1>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    exit;
}


