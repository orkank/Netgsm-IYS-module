<?php
namespace IDangerous\NetgsmIYS\Helper;

use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * API Helper for Netgsm IYS Integration
 */
class Api
{
    private const API_ENDPOINT = 'https://api.netgsm.com.tr/iys/add';
    private const SOURCE_TYPE = 'HS_WEB';
    private const RECIPIENT_TYPE = 'BIREYSEL';

    /**
     * @var Curl
     */
    private $curl;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param Curl $curl
     * @param Config $config
     * @param Json $json
     * @param Logger $logger
     */
    public function __construct(
        Curl $curl,
        Config $config,
        Json $json,
        Logger $logger
    ) {
        $this->curl = $curl;
        $this->config = $config;
        $this->json = $json;
        $this->logger = $logger;
    }

    /**
     * Prepare payload for API request
     *
     * @param array $data
     * @return array
     */
    public function preparePayload(array $data): array
    {
        // Format phone number if needed
        $recipient = $data['value'];
        if ($data['type'] !== 'email' && !str_starts_with($recipient, '+90')) {
            $recipient = '+90' . preg_replace('/[^0-9]/', '', $recipient);
        }

        // Use the modified date from the record for consentDate
        $consentDate = $data['modified'] ?? date('Y-m-d H:i:s');

        $payload = [
            'header' => [
                'username' => $this->config->getUsername(),
                'password' => $this->config->getPassword(),
                'brandCode' => $this->config->getBrandCode()
            ],
            'body' => [
                'data' => [
                    [
                        'type' => $this->mapTypeToIys($data['type']),
                        'recipient' => $recipient,
                        'recipientType' => self::RECIPIENT_TYPE,
                        'status' => $this->mapStatusToIys($data['status']),
                        'source' => self::SOURCE_TYPE,
                        'consentDate' => $consentDate
                    ]
                ]
            ]
        ];

        // Add app key only if configured
        $appKey = $this->config->getWebhookToken();
        if (!empty($appKey)) {
            $payload['header']['appkey'] = $appKey;
        }

        return $payload;
    }

    /**
     * Sync record with IYS
     *
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function syncRecord(array $data): array
    {
        try {
            $payload = $this->preparePayload($data);

            if ($this->config->isLoggingEnabled()) {
                $this->logger->info('IYS API Request', $payload);
            }

            $response = $this->sendRequest($payload);

            if ($this->config->isLoggingEnabled()) {
                $this->logger->info('IYS API Response', $response);
            }

            return $response;

        } catch (\Exception $e) {
            $this->logger->error('IYS API Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Map internal type to IYS type
     *
     * @param string $type
     * @return string
     * @throws \InvalidArgumentException
     */
    private function mapTypeToIys(string $type): string
    {
        switch ($type) {
            case 'sms':
                return 'MESAJ';
            case 'call':
                return 'ARAMA';
            case 'email':
                return 'EPOSTA';
            default:
                throw new \InvalidArgumentException("Invalid type: {$type}");
        }
    }

    /**
     * Map internal status to IYS status
     *
     * @param int $status
     * @return string
     * @throws \InvalidArgumentException
     */
    private function mapStatusToIys(int $status): string
    {
        switch ($status) {
            case 1:
                return 'ONAY';
            case 2:
                return 'RET';
            default:
                throw new \InvalidArgumentException("Invalid status: {$status}");
        }
    }

    /**
     * Send request to IYS API
     *
     * @param array $payload
     * @return array
     * @throws \Exception
     */
    private function sendRequest(array $payload): array
    {
        $this->curl->addHeader('Content-Type', 'application/json');
        $this->curl->post(self::API_ENDPOINT, $this->json->serialize($payload));

        $response = $this->json->unserialize($this->curl->getBody());

        if (!isset($response['code'])) {
            throw new \Exception('Invalid API response');
        }

        if ($response['code'] !== '0' && $response['code'] !== 0) {
            throw new \Exception(
                sprintf('API Error: Code %s - %s',
                    $response['code'],
                    $response['error'] ?? 'Unknown error'
                )
            );
        }

        return $response;
    }
}