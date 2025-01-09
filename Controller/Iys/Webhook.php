<?php
namespace IDangerous\NetgsmIYS\Controller\Iys;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Serialize\Serializer\Json;
use IDangerous\NetgsmIYS\Helper\Config;
use IDangerous\NetgsmIYS\Helper\Logger;
use IDangerous\NetgsmIYS\Api\WebhookInterface;
use IDangerous\NetgsmIYS\Model\ResourceModel\IysData\CollectionFactory;
use IDangerous\NetgsmIYS\Model\IysDataFactory;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;

class Webhook implements HttpPostActionInterface, CsrfAwareActionInterface, WebhookInterface
{
    /**
     * @var RequestInterface
     */
    private $request;

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
     * @var Json
     */
    private $json;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var IysDataFactory
     */
    private $iysDataFactory;

    /**
     * @var CustomerCollectionFactory
     */
    private $customerCollectionFactory;

    /**
     * @param RequestInterface $request
     * @param JsonFactory $jsonFactory
     * @param Config $config
     * @param Logger $logger
     * @param Json $json
     * @param CollectionFactory $collectionFactory
     * @param IysDataFactory $iysDataFactory
     * @param CustomerCollectionFactory $customerCollectionFactory
     */
    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        Config $config,
        Logger $logger,
        Json $json,
        CollectionFactory $collectionFactory,
        IysDataFactory $iysDataFactory,
        CustomerCollectionFactory $customerCollectionFactory
    ) {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->config = $config;
        $this->logger = $logger;
        $this->json = $json;
        $this->collectionFactory = $collectionFactory;
        $this->iysDataFactory = $iysDataFactory;
        $this->customerCollectionFactory = $customerCollectionFactory;
    }

    /**
     * Find customer by phone number
     *
     * @param string $phone
     * @return int|null
     */
    private function findCustomerByPhone(string $phone): ?int
    {
        // Remove +90 prefix and any non-digit characters
        $cleanPhone = preg_replace('/[^0-9]/', '', str_replace('+90', '', $phone));

        // Search in customer collection
        $collection = $this->customerCollectionFactory->create();
        $collection->addAttributeToSelect('*');

        // Try different phone number formats
        $phones = [
            $cleanPhone,
            '+90' . $cleanPhone,
            '0' . $cleanPhone
        ];

        $collection->addFieldToFilter('telephone', ['in' => $phones]);

        if ($collection->getSize() > 0) {
            $customer = $collection->getFirstItem();
            return (int)$customer->getId();
        }

        return null;
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->jsonFactory->create();

        try {
            // Validate token if configured
            $configuredToken = $this->config->getWebhookToken();
            if (!empty($configuredToken)) {
                $receivedToken = $this->request->getHeader('X-Webhook-Token');
                if ($receivedToken !== $configuredToken) {
                    throw new \Exception('Invalid webhook token');
                }
            }

            // Get and validate request content
            $content = $this->request->getContent();
            if (empty($content)) {
                throw new \Exception('Empty request content');
            }

            // Decode JSON content
            $data = $this->json->unserialize($content);

            // Ensure we have an array of records
            if (!isset($data[0])) {
                $data = [$data];
            }

            $processed = 0;
            $created = 0;
            $updated = 0;
            $errors = 0;

            // Process each record
            foreach ($data as $record) {
                try {
                    $this->processRecord($record);
                    $processed++;

                    if ($this->config->isLoggingEnabled()) {
                        $this->logger->info('Processed webhook record', $record);
                    }
                } catch (\Exception $e) {
                    $errors++;
                    $this->logger->error('Error processing webhook record: ' . $e->getMessage(), $record);
                }
            }

            return $result->setData([
                'success' => true,
                'message' => sprintf(
                    'Processed %d records (%d created, %d updated, %d errors)',
                    $processed,
                    $created,
                    $updated,
                    $errors
                )
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Webhook error: ' . $e->getMessage());

            return $result
                ->setHttpResponseCode(400)
                ->setData([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
        }
    }

    /**
     * Process individual record
     *
     * @param array $record
     * @throws \Exception
     */
    private function processRecord(array $record)
    {
        // Validate required fields
        if (empty($record['recipient']) || empty($record['type'])) {
            throw new \Exception('Missing required fields');
        }

        // Find existing record
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('value', $record['recipient']);
        $collection->addFieldToFilter('type', $this->mapIysTypeToInternal($record['type']));
        $iysData = $collection->getFirstItem();

        $isNew = !$iysData->getId();

        if ($isNew) {
            // Create new record
            $iysData = $this->iysDataFactory->create();
            $iysData->setValue($record['recipient']);
            $iysData->setType($this->mapIysTypeToInternal($record['type']));
        }

        // Try to find matching customer if no user is associated
        if (!$iysData->getUserid()) {
            if ($userId = $this->findCustomerByPhone($record['recipient'])) {
                $iysData->setUserid($userId);

                if ($this->config->isLoggingEnabled()) {
                    $this->logger->info(sprintf(
                        'Associated record with customer ID: %d for phone: %s',
                        $userId,
                        $record['recipient']
                    ));
                }
            }
        }

        // Update record
        $iysData->setModified(date('Y-m-d H:i:s'));
        $iysData->setIysStatus(1); // Mark as synced

        // Map status
        if ($record['status'] === 'ONAY') {
            $iysData->setStatus(1); // Accepted
        } elseif ($record['status'] === 'RET') {
            $iysData->setStatus(3); // IYS Rejected
        }

        // Add webhook result to history
        $iysData->addIysResult([
            'webhook' => true,
            'data' => $record,
            'timestamp' => date('Y-m-d H:i:s'),
            'is_new' => $isNew
        ]);

        $iysData->save();

        if ($this->config->isLoggingEnabled()) {
            $this->logger->info(
                sprintf(
                    '%s record processed: ID %d, Phone %s, Type %s, Status %s',
                    $isNew ? 'New' : 'Existing',
                    $iysData->getId(),
                    $record['recipient'],
                    $record['type'],
                    $record['status']
                )
            );
        }
    }

    /**
     * Map IYS type to internal type
     *
     * @param string $type
     * @return string
     */
    private function mapIysTypeToInternal(string $type): string
    {
        switch ($type) {
            case 'MESAJ':
                return 'sms';
            case 'ARAMA':
                return 'call';
            case 'EPOSTA':
                return 'email';
            default:
                return strtolower($type);
        }
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}