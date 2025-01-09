<?php
namespace IDangerous\NetgsmIYS\Cron;

use IDangerous\NetgsmIYS\Model\ResourceModel\IysData\CollectionFactory;
use IDangerous\NetgsmIYS\Helper\Api as ApiHelper;
use IDangerous\NetgsmIYS\Helper\Logger;

class Sync
{
    private const BATCH_SIZE = 50;
    private const MAX_RETRIES = 3;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var ApiHelper
     */
    private $apiHelper;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param CollectionFactory $collectionFactory
     * @param ApiHelper $apiHelper
     * @param Logger $logger
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        ApiHelper $apiHelper,
        Logger $logger
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->apiHelper = $apiHelper;
        $this->logger = $logger;
    }

    /**
     * Execute cron job
     *
     * @return void
     */
    public function execute()
    {
        $this->logger->info('Starting IYS sync cron job');

        try {
            $collection = $this->collectionFactory->create();
            $collection->addFieldToFilter('iys_status', ['eq' => 0]);
            $collection->addFieldToFilter('sync_retries', ['lt' => self::MAX_RETRIES]);
            $collection->setPageSize(self::BATCH_SIZE);

            if ($collection->getSize() === 0) {
                $this->logger->info('No pending records found');
                return;
            }

            $processed = 0;
            $success = 0;

            foreach ($collection as $record) {
                $processed++;
                try {
                    $this->syncRecord($record);
                    $success++;
                } catch (\Exception $e) {
                    $this->logger->error(sprintf(
                        'Failed to sync record %d: %s',
                        $record->getId(),
                        $e->getMessage()
                    ));

                    $record->incrementSyncRetries();
                    $record->save();
                }
            }

            $this->logger->info(sprintf(
                'Processed %d records, %d successful',
                $processed,
                $success
            ));

        } catch (\Exception $e) {
            $this->logger->error('Cron sync error: ' . $e->getMessage());
        }
    }

    /**
     * Sync individual record
     *
     * @param \IDangerous\NetgsmIYS\Model\IysData $record
     * @throws \Exception
     */
    private function syncRecord($record)
    {
        $this->logger->info(sprintf('Processing record ID: %d', $record->getId()));

        $response = $this->apiHelper->syncRecord([
            'id' => $record->getId(),
            'value' => $record->getValue(),
            'type' => $record->getType(),
            'status' => $record->getStatus(),
            'modified' => $record->getModified()
        ]);

        $record->addIysResult($response);
        $record->setIysStatus(1);
        $record->save();

        $this->logger->info(sprintf('Successfully synced record ID: %d', $record->getId()));
    }
}