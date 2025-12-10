<?php

declare(strict_types=1);

/**
 * Simple courier client for the recruitment API.
 *
 * - uses the recruitment API documented in `docs.html`
 * - provides `newPackage(array $order, array $params)` and
 *   `packagePDF(string $trackingNumber)` as required by the task.
 */
class SpringCourier
{
    private const DEFAULT_API_URL = 'https://developers.baselinker.com/recruitment/api';

    private string $apiKey;

    private string $service;

    private string $labelFormat;

    public function __construct(string $apiKey, string $service = 'PPTT', string $labelFormat = 'PDF')
    {
        $this->apiKey = $apiKey;
        $this->service = $service;
        $this->labelFormat = $labelFormat;
    }

    /**
     * Create a shipment.
     * Returns array with API response on success, or throws RuntimeException on error.
     */
    public function newPackage(array $order, array $params = []): array
    {
        $service = $params['service'] ?? $this->service;

        $shipment = [];
        $shipment['Service'] = $service;
        $shipment['ShipperReference'] = $params['shipper_reference'] ?? ('REF' . time());
        $shipment['Weight'] = $params['weight'] ?? $order['weight'] ?? 1.0;
        $shipment['LabelFormat'] = $params['label_format'] ?? $this->labelFormat;

        // Consignor (sender) - accept multiple possible input keys
        $consignor = [];
        $consignor['FullName'] = $this->pick($order, ['sender_fullname', 'sender_name', 'sender_full_name'], '');
        // also keep 'Name' for consistency with some services
        $consignor['Name'] = $consignor['FullName'];
        $consignor['Company'] = $this->pick($order, ['sender_company', 'sender_org', 'sender_business'], '');
        $consignor['AddressLine1'] = $this->pick($order, ['sender_address', 'sender_address_line1', 'sender_street'], '');
        $consignor['AddressLine2'] = $this->pick($order, ['sender_address_line2', 'sender_address2'], '');
        $consignor['City'] = $this->pick($order, ['sender_city', 'sender_town', 'sender_locality'], '');
        $consignor['Zip'] = $this->pick($order, ['sender_postalcode', 'sender_zip', 'sender_zipcode'], '');
        $consignor['Country'] = $this->pick($order, ['sender_country', 'sender_country_code'], 'PL');
        $consignor['Phone'] = $this->pick($order, ['sender_phone', 'sender_telephone', 'sender_mobile'], '');
        $consignor['Email'] = $this->pick($order, ['sender_email', 'sender_mail'], '');

        // Consignee (recipient) - accept multiple possible input keys
        $consignee = [];
        $consignee['FullName'] = $this->pick($order, ['delivery_fullname', 'delivery_name', 'recipient_name', 'delivery_full_name'], '');
        $consignee['Name'] = $consignee['FullName'];
        $consignee['Company'] = $this->pick($order, ['delivery_company', 'recipient_company', 'delivery_org'], '');
        $consignee['AddressLine1'] = $this->pick($order, ['delivery_address', 'delivery_address_line1', 'recipient_address', 'delivery_street'], '');
        $consignee['AddressLine2'] = $this->pick($order, ['delivery_address_line2', 'recipient_address_line2'], '');
        $consignee['City'] = $this->pick($order, ['delivery_city', 'recipient_city', 'delivery_town'], '');
        $consignee['Zip'] = $this->pick($order, ['delivery_postalcode', 'delivery_zip', 'recipient_postalcode'], '');
        $consignee['Country'] = $this->pick($order, ['delivery_country', 'recipient_country', 'delivery_country_code'], '');
        $consignee['Phone'] = $this->pick($order, ['delivery_phone', 'recipient_phone', 'delivery_telephone'], '');
        $consignee['Email'] = $this->pick($order, ['delivery_email', 'recipient_email'], '');

        // Basic product if not provided
        $products = [];
        if (!empty($params['products']) && is_array($params['products'])) {
            $products = $params['products'];
        }

        if (empty($products)) {
            $products[] = [
                'Description' => $params['product_description'] ?? 'Shipment',
                'Quantity' => 1,
                'Weight' => $shipment['Weight'],
                'Value' => $params['product_value'] ?? 0.0,
            ];
        }

        // Apply simple truncation rules based on documented field limits
        $this->applyFieldLimits($service, $consignor, $consignee);

        $shipment['ConsignorAddress'] = $consignor;
        $shipment['ConsigneeAddress'] = $consignee;
        $shipment['Products'] = $products;

        $payload = [
            'Apikey' => $this->apiKey,
            'Command' => 'OrderShipment',
            'Shipment' => $shipment,
        ];

        $response = $this->post($payload);

        if (!is_array($response)) {
            throw new RuntimeException('Invalid response from API');
        }

        if (isset($response['ErrorLevel']) && (int) $response['ErrorLevel'] !== 0) {
            $err = $response['Error'] ?? 'Unknown error';
            throw new RuntimeException('API error: ' . $err);
        }

        return $response;
    }

    /**
     * Download (output) the shipment label for given tracking number.
     * This function will emit headers and print the label contents.
     * It throws RuntimeException on errors.
     */
    public function packagePDF(string $trackingNumber): void
    {
        $payload = [
            'Apikey' => $this->apiKey,
            'Command' => 'GetShipmentLabel',
            'Shipment' => [
                'TrackingNumber' => $trackingNumber,
                'LabelFormat' => $this->labelFormat,
            ],
        ];

        $response = $this->post($payload);

        if (!is_array($response)) {
            throw new RuntimeException('Invalid response from API');
        }

        if (isset($response['ErrorLevel']) && (int) $response['ErrorLevel'] !== 0) {
            $err = $response['Error'] ?? 'Unknown error';
            throw new RuntimeException('API error: ' . $err);
        }

        $shipment = $response['Shipment'] ?? null;
        if (!is_array($shipment) || empty($shipment['LabelImage'])) {
            throw new RuntimeException('Label image missing in API response');
        }

        $labelBase64 = $shipment['LabelImage'];
        $labelFormat = strtoupper($shipment['LabelFormat'] ?? $this->labelFormat);

        $content = base64_decode($labelBase64);
        if ($content === false) {
            throw new RuntimeException('Failed to decode label image');
        }

        // Choose content type and filename based on format
        $ct = 'application/octet-stream';
        $ext = 'bin';
        switch ($labelFormat) {
            case 'PDF':
                $ct = 'application/pdf';
                $ext = 'pdf';
                break;
            case 'PNG':
                $ct = 'image/png';
                $ext = 'png';
                break;
            case 'ZPL':
            case 'ZPL300':
            case 'ZPL200':
                $ct = 'text/plain';
                $ext = 'zpl';
                break;
            case 'EPL':
                $ct = 'text/plain';
                $ext = 'epl';
                break;
            default:
                $ct = 'application/octet-stream';
                $ext = 'bin';
                break;
        }

        // Emit headers for download
        if (!headers_sent()) {
            header('Content-Description: File Transfer');
            header('Content-Type: ' . $ct);
            header('Content-Disposition: attachment; filename="label_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $trackingNumber) . '.' . $ext . '"');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . (string) strlen($content));
        }

        echo $content;
        flush();
    }

    /**
     * Post JSON payload to API and return decoded response.
     * Throws RuntimeException on transport errors.
     */
    private function post(array $payload): array
    {
        $apiUrl = $this->getApiUrl();

        $ch = curl_init();
        $json = json_encode($payload);

        if ($json === false) {
            throw new RuntimeException('Failed to encode payload to JSON');
        }

        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json),
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $result = curl_exec($ch);
        if ($result === false) {
            $err = curl_error($ch);
            throw new RuntimeException('cURL error: ' . $err);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException('Non-2xx response from API: ' . $httpCode . ' - ' . $result);
        }

        $decoded = json_decode($result, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Failed to decode JSON response: ' . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Apply simple field-length truncation based on documented field limits.
     */
    private function applyFieldLimits(string $service, array &$consignor, array &$consignee): void
    {
        // Minimal limits for common fields (based on docs.html table)
        $limits = [
            'Name' => 30,
            'FullName' => 30,
            'Company' => 30,
            'AddressLine1' => 30,
            'AddressLine2' => 30,
            'City' => 30,
            'Zip' => 20,
            'Description' => 20,
            'HsCode' => 25,
            'Phone' => 15,
        ];

        foreach ($consignor as $k => $v) {
            if (isset($limits[$k]) && is_string($v)) {
                $consignor[$k] = $this->truncate($v, $limits[$k]);
            }
        }

        foreach ($consignee as $k => $v) {
            if (isset($limits[$k]) && is_string($v)) {
                $consignee[$k] = $this->truncate($v, $limits[$k]);
            }
        }
    }

    /**
     * Return the first present key from list of candidates in array, or default.
     */
    private function pick(array $data, array $candidates, string $default = ''): string
    {
        foreach ($candidates as $k) {
            if (is_array($data) && array_key_exists($k, $data) && $data[$k] !== null && $data[$k] !== '') {
                return (string) $data[$k];
            }
        }

        return $default;
    }

    private function truncate(string $value, int $length): string
    {
        $value = trim($value);
        if (mb_strlen($value) <= $length) {
            return $value;
        }

        return mb_substr($value, 0, $length);
    }

    private function getApiUrl(): string
    {
        $env = getenv('RECRUITMENT_API_URL');
        if ($env !== false && trim($env) !== '') {
            return rtrim($env, "/");
        }

        return self::DEFAULT_API_URL;
    }
}
