<?php

namespace App\Libraries;

use CodeIgniter\HTTP\CURLRequest;
use Config\Services;

class Evolution
{
    private string $apiUrl;
    private string $apiKey;
    private string $instanceName;

    /**
     * Constructor.
     *
     * @param string $apiUrl
     * @param string $apiKey
     * @param string $instanceName
     */
    public function __construct(string $apiUrl = '', string $apiKey = '', string $instanceName = '')
    {
        $this->apiUrl       = rtrim($apiUrl, '/');
        $this->apiKey       = $apiKey;
        $this->instanceName = $instanceName;
    }

    /**
     * Centralized request handler using CURLRequest.
     *
     * @param string $method
     * @param string $endpoint
     * @param array  $data
     * @return array
     * @throws \Exception
     */
    private function request(string $method, string $endpoint, array $data = []): array
    {
        if (empty($this->apiUrl)) {
            throw new \Exception("Evolution API URL is not configured.");
        }

        $url     = $this->apiUrl . '/' . ltrim($endpoint, '/');
        $headers = [
            'apikey'       => $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];

        /** @var CURLRequest $client */
        $client = Services::curlrequest([
            'timeout'     => 30,
            'headers'     => $headers,
            'http_errors' => false,
        ]);

        $options = [];
        if (!empty($data)) {
            if (strtoupper($method) === 'GET') {
                $options['query'] = $data;
            } else {
                $options['json'] = $data;
            }
        }

        try {
            log_message('debug', "Evolution API Request: {$method} {$url} " . json_encode($data));
            $response   = $client->request($method, $url, $options);
            $statusCode = $response->getStatusCode();
            $body       = $response->getBody();

            log_message('debug', "Evolution API Response [{$statusCode}]: {$body}");

            $decoded = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Invalid JSON response from Evolution API: " . $body);
            }

            if ($statusCode >= 400) {
                $message = $decoded['message'] ?? $decoded['error'] ?? 'API Error with status code: ' . $statusCode;
                if (is_array($message)) {
                    $message = implode(', ', $message);
                }
                throw new \Exception($message, $statusCode);
            }

            return $decoded;
        } catch (\Exception $e) {
            log_message('error', "Evolution API Request Exception: " . $e->getMessage() . " (URL: {$url})");
            throw $e;
        }
    }

    /**
     * Extracts a base64 QR Code image from various Evolution API response shapes.
     *
     * The API v1/v2 may return the QR in different fields:
     *  - $response['qrcode']['base64']
     *  - $response['qrcode']['code']
     *  - $response['base64']
     *  - $response['code']
     *
     * @param array $response Raw API response
     * @return string|null   Ready-to-use "data:image/png;base64,..." string, or null
     */
    public static function extractQrBase64(array $response): ?string
    {
        // Try nested qrcode object first (v2 create response)
        $candidates = [
            $response['qrcode']['base64'] ?? null,
            $response['qrcode']['code']   ?? null,
            $response['base64']           ?? null,
            $response['code']             ?? null,
        ];

        foreach ($candidates as $raw) {
            if (empty($raw) || !is_string($raw)) {
                continue;
            }

            // Already a full data URI
            if (str_starts_with($raw, 'data:')) {
                return $raw;
            }

            // Raw base64 string
            return 'data:image/png;base64,' . $raw;
        }

        return null;
    }

    /**
     * Test api connection.
     *
     * @return array
     * @throws \Exception
     */
    public function testConnection(): array
    {
        return $this->fetchInstances();
    }

    /**
     * Create instance on the Evolution API.
     * Returns the full response including qrcode when available.
     *
     * @param string $integration WHATSAPP-BAILEYS (default) or WHATSAPP-BUSINESS
     * @return array              Full API response (may include qrcode.base64)
     * @throws \Exception
     */
    public function createInstance(string $integration = 'WHATSAPP-BAILEYS'): array
    {
        $body = [
            'instanceName' => $this->instanceName,
            'qrcode'       => true,
            'integration'  => $integration,
        ];

        return $this->request('POST', 'instance/create', $body);
    }

    /**
     * Delete instance.
     *
     * @return array
     * @throws \Exception
     */
    public function deleteInstance(): array
    {
        return $this->request('DELETE', "instance/delete/{$this->instanceName}");
    }

    /**
     * Fetch all instances.
     *
     * @return array
     * @throws \Exception
     */
    public function fetchInstances(): array
    {
        return $this->request('GET', 'instance/fetchInstances');
    }

    /**
     * Get connection state.
     *
     * @return array
     * @throws \Exception
     */
    public function connectionState(): array
    {
        return $this->request('GET', "instance/connectionState/{$this->instanceName}");
    }

    /**
     * Get QR Code for the instance (GET /instance/connect/{name}).
     * Normalises all known response shapes into { base64: "data:image/png;..." }.
     *
     * @return array  ['base64' => 'data:image/png;base64,...', 'raw' => <original response>]
     * @throws \Exception
     */
    public function getQRCode(): array
    {
        $raw    = $this->request('GET', "instance/connect/{$this->instanceName}");
        $base64 = self::extractQrBase64($raw);

        return [
            'base64' => $base64,
            'raw'    => $raw,
        ];
    }

    /**
     * Restart instance.
     *
     * @return array
     * @throws \Exception
     */
    public function restart(): array
    {
        return $this->request('POST', "instance/restart/{$this->instanceName}");
    }

    /**
     * Logout instance.
     *
     * @return array
     * @throws \Exception
     */
    public function logout(): array
    {
        return $this->request('POST', "instance/logout/{$this->instanceName}");
    }

    /**
     * Fetch connected profile metadata.
     *
     * @return array
     * @throws \Exception
     */
    public function profile(): array
    {
        // Fetch all instances to extract owner information
        $instances = $this->fetchInstances();
        foreach ($instances as $inst) {
            if (($inst['instanceName'] ?? '') === $this->instanceName) {
                // Determine details from the object
                $phone      = $inst['phone']['number'] ?? $inst['owner'] ?? null;
                $pushName   = $inst['phone']['pushName'] ?? $inst['profileName'] ?? null;
                $profilePic = $inst['profilePicUrl'] ?? null;

                // Sometimes phone is formatted as an array/object
                if (is_array($phone)) {
                    $phone = $phone['number'] ?? null;
                }

                // If phone is standard JID (e.g. 559999999@s.whatsapp.net), strip suffix
                if ($phone && str_contains($phone, '@')) {
                    $parts = explode('@', $phone);
                    $phone = $parts[0];
                }

                return [
                    'phone'           => $phone,
                    'profile_name'    => $pushName,
                    'profile_picture' => $profilePic,
                ];
            }
        }

        throw new \Exception("Instance '{$this->instanceName}' not found in API instance list.");
    }

    /**
     * Send text message.
     *
     * @param string $number
     * @param string $text
     * @return array
     * @throws \Exception
     */
    public function sendMessage(string $number, string $text): array
    {
        $body = [
            'number' => $number,
            'text'   => $text,
        ];
        return $this->request('POST', "message/sendText/{$this->instanceName}", $body);
    }

    /**
     * Configure a webhook for the instance.
     *
     * @param string   $url    Webhook URL to receive events
     * @param string[] $events Events to subscribe to
     * @return array
     * @throws \Exception
     */
    public function setWebhook(string $url, array $events = []): array
    {
        if (empty($events)) {
            $events = [
                'APPLICATION_STARTUP',
                'QRCODE_UPDATED',
                'MESSAGES_UPSERT',
                'MESSAGES_UPDATE',
                'SEND_MESSAGE',
                'CONNECTION_UPDATE',
            ];
        }

        $body = [
            'webhook' => [
                'enabled'         => true,
                'url'             => $url,
                'webhookByEvents' => false,
                'events'          => $events,
            ],
        ];

        return $this->request('POST', "webhook/set/{$this->instanceName}", $body);
    }

    /**
     * Health check endpoint.
     *
     * @return array
     * @throws \Exception
     */
    public function health(): array
    {
        return $this->request('GET', '');
    }
}
