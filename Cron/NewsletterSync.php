<?php
namespace IDangerous\NetgsmIYS\Cron;

use IDangerous\NetgsmIYS\Helper\Config;
use IDangerous\NetgsmIYS\Helper\Logger;
use IDangerous\NetgsmIYS\Model\IysDataFactory;
use IDangerous\NetgsmIYS\Model\ResourceModel\IysData\CollectionFactory as IysCollectionFactory;
use Magento\Newsletter\Model\ResourceModel\Subscriber\CollectionFactory as SubscriberCollectionFactory;
use Magento\Framework\Stdlib\DateTime\DateTime;

class NewsletterSync
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var IysDataFactory
     */
    private $iysDataFactory;

    /**
     * @var IysCollectionFactory
     */
    private $iysCollectionFactory;

    /**
     * @var SubscriberCollectionFactory
     */
    private $subscriberCollectionFactory;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @param Config $config
     * @param Logger $logger
     * @param IysDataFactory $iysDataFactory
     * @param IysCollectionFactory $iysCollectionFactory
     * @param SubscriberCollectionFactory $subscriberCollectionFactory
     * @param DateTime $dateTime
     */
    public function __construct(
        Config $config,
        Logger $logger,
        IysDataFactory $iysDataFactory,
        IysCollectionFactory $iysCollectionFactory,
        SubscriberCollectionFactory $subscriberCollectionFactory,
        DateTime $dateTime
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->iysDataFactory = $iysDataFactory;
        $this->iysCollectionFactory = $iysCollectionFactory;
        $this->subscriberCollectionFactory = $subscriberCollectionFactory;
        $this->dateTime = $dateTime;
    }

    /**
     * Execute newsletter sync
     *
     * @param int|null $limit
     * @return void
     */
    public function execute($limit = null)
    {
        // if (!$this->config->isEnabled()) {
        //     return;
        // }

        try {
            $stats = $this->syncFromNewsletter($limit);

            if ($this->config->isLoggingEnabled()) {
                $this->logger->info('Newsletter sync completed', $stats);
            }
        } catch (\Exception $e) {
            $this->logger->error('Newsletter sync error: ' . $e->getMessage());
        }
    }

    /**
     * Sync data from newsletter_subscriber table
     *
     * @param int|null $limit
     * @return array
     */
    private function syncFromNewsletter($limit = null)
    {
        $stats = [
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'empty_email' => 0
        ];

        // Get newsletter subscribers
        $subscribers = $this->subscriberCollectionFactory->create();

        // Exclude empty emails
        $subscribers->addFieldToFilter('subscriber_email', ['notnull' => true]);
        $subscribers->addFieldToFilter('subscriber_email', ['neq' => '']);

        // Apply limit if set
        if ($limit !== null) {
            $subscribers->getSelect()->limit($limit);
        }

        // Join with iys_data to check for existing records
        $subscribers->getSelect()->joinLeft(
            ['iys' => $subscribers->getTable('iys_data')],
            'main_table.subscriber_email = iys.value AND iys.type = "email"',
            ['iys_id' => 'iys.id', 'iys_modified' => 'iys.modified']
        );

        foreach ($subscribers as $subscriber) {
            $stats['processed']++;

            try {
                // Skip if IYS record exists and is newer
                if ($subscriber->getData('iys_id') &&
                    $subscriber->getData('iys_modified') &&
                    strtotime($subscriber->getData('iys_modified')) > strtotime($subscriber->getChangeStatusAt())) {
                    $stats['skipped']++;
                    continue;
                }

                $iysData = $subscriber->getData('iys_id')
                    ? $this->iysDataFactory->create()->load($subscriber->getData('iys_id'))
                    : $this->iysDataFactory->create();

                // Set basic data
                $iysData->setType('email');
                $iysData->setValue($subscriber->getSubscriberEmail());

                // Set user ID if available
                if ($subscriber->getCustomerId()) {
                    $iysData->setUserid($subscriber->getCustomerId());
                }

                // Map newsletter status to IYS status
                $iysData->setStatus(
                    $subscriber->getSubscriberStatus() == \Magento\Newsletter\Model\Subscriber::STATUS_SUBSCRIBED ? 1 : 2
                );

                // Set modified date from newsletter
                if ($subscriber->getChangeStatusAt()) {
                    $iysData->setModified($subscriber->getChangeStatusAt());
                }

                // Set IYS status to pending
                $iysData->setIysStatus(0);

                $iysData->save();

                if ($subscriber->getData('iys_id')) {
                    $stats['updated']++;
                } else {
                    $stats['created']++;
                }

            } catch (\Exception $e) {
                $this->logger->error('Error processing subscriber: ' . $e->getMessage(), [
                    'subscriber_id' => $subscriber->getId(),
                    'email' => $subscriber->getSubscriberEmail()
                ]);
            }
        }

        if ($this->config->isLoggingEnabled()) {
            $this->logger->info('Newsletter sync stats', $stats);
        }

        return $stats;
    }
}