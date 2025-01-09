<?php
namespace IDangerous\NetgsmIYS\Controller\Webhook;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use IDangerous\NetgsmIYS\Helper\Config;
use IDangerous\NetgsmIYS\Helper\Logger;

/**
 * IYS Webhook Controller
 */
class Index implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private const STATUS_ONAY = 'ONAY';
    private const STATUS_RET = 'RET';

    /**
     * @var JsonFactory
     */
    private $jsonFactory;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @param JsonFactory $jsonFactory
     * @param Config $config
     * @param Logger $logger
     * @param RequestInterface $request
     */
    public function __construct(
        JsonFactory $jsonFactory,
        Config $config,
        Logger $logger,
        RequestInterface $request
    ) {
        $this->jsonFactory = $jsonFactory;
        $this->config = $config;
        $this->logger = $logger;
        $this->request = $request;
    }

    /**
     * Execute webhook action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->jsonFactory->create();

        try {
            if (!$this->validateToken()) {
                throw new \Exception('Invalid token', 403);
            }

            if (!$this->validateHost()) {
                throw new \Exception('Invalid host', 403);
            }

            $webhookData = $this->getWebhookData();
            if (empty($webhookData)) {
                throw new \Exception('Invalid request data', 400);
            }

            if ($this->config->isLoggingEnabled()) {
                $this->logger->logWebhook($webhookData);
            }

            $this->processWebhookData($webhookData);

            return $result->setData([
                'success' => true,
                'message' => 'Webhook processed successfully'
            ]);

        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage()
            ])->setHttpResponseCode($e->getCode() ?: 500);
        }
    }

    /**
     * Validate webhook token
     *
     * @return bool
     */
    private function validateToken(): bool
    {
        $configToken = $this->config->getWebhookToken();
        if (empty($configToken)) {
            return true;
        }

        $requestToken = $this->request->getHeader('X-Webhook-Token');
        return $configToken === $requestToken;
    }

    /**
     * Validate request host
     *
     * @return bool
     */
    private function validateHost(): bool
    {
        $allowedHosts = $this->config->getWebhookAllowedHosts();
        if (empty($allowedHosts)) {
          return true;
        }

        $remoteIp = $this->request->getRemoteIp();
        $allowedHostsArray = array_map('trim', explode("\n", $allowedHosts));

        foreach ($allowedHostsArray as $host) {
            if (empty($host)) {
                continue;
            }

            if (filter_var($host, FILTER_VALIDATE_IP)) {
                if ($host === $remoteIp) {
                    return true;
                }
            } else {
                $hostIps = gethostbynamel($host);
                if ($hostIps && in_array($remoteIp, $hostIps)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get webhook data from request
     *
     * @return array
     */
    private function getWebhookData(): array
    {
        $content = $this->request->getContent();
        $data = json_decode($content, true);

        if (isset($data[0])) {
            return $data;
        }

        return [$data];
    }

    /**
     * Process webhook data
     *
     * @param array $webhookData
     * @return void
     */
    private function processWebhookData(array $webhookData): void
    {
        foreach ($webhookData as $data) {
            try {
                $this->validateWebhookData($data);

                $status = $this->mapIysStatus($data['status']);

                $this->updateIysRecord(
                    $data['recipient'],
                    $this->mapIysType($data['type']),
                    $status,
                    $data
                );

            } catch (\Exception $e) {
                $this->logger->error('Webhook processing error: ' . $e->getMessage(), $data);
                continue;
            }
        }
    }

    /**
     * Validate webhook data
     *
     * @param array $data
     * @return void
     * @throws \Exception
     */
    private function validateWebhookData(array $data): void
    {
        $requiredFields = ['recipient', 'type', 'status'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new \Exception("Missing required field: {$field}");
            }
        }
    }

    /**
     * Map IYS status to internal status
     *
     * @param string $status
     * @return int
     */
    private function mapIysStatus(string $status): int
    {
        switch ($status) {
            case self::STATUS_ONAY:
                return 1;
            case self::STATUS_RET:
                return 3;
            default:
                return 0;
        }
    }

    /**
     * Map IYS type to internal type
     *
     * @param string $type
     * @return string
     * @throws \Exception
     */
    private function mapIysType(string $type): string
    {
        switch ($type) {
            case 'MESAJ':
                return 'sms';
            case 'ARAMA':
                return 'call';
            case 'EPOSTA':
                return 'email';
            default:
                throw new \Exception("Invalid IYS type: {$type}");
        }
    }

    /**
     * Create CSRF validation exception
     *
     * @param RequestInterface $request
     * @return ?InvalidRequestException
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Validate for CSRF
     *
     * @param RequestInterface $request
     * @return ?bool
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}