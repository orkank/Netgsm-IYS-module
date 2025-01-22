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
    public function execute($limit = null, $customerId = null)
    {
        // if (!$this->config->isEnabled()) {
        //     return;
        // }
        try {
            $stats = $this->syncFromNewsletter($limit, $customerId);

            if ($this->config->isLoggingEnabled()) {
                $this->logger->info('Newsletter sync completed', $stats);
            }

            return $stats;
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
    private function syncFromNewsletter($limit = null, $customerId = null)
    {
        $stats = [
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'empty_email' => 0,
            'duplicate_skipped' => 0
        ];

        // Get newsletter subscribers
        $subscribers = $this->subscriberCollectionFactory->create();

        // Exclude empty emails
        $subscribers->addFieldToFilter('subscriber_email', ['notnull' => true]);
        $subscribers->addFieldToFilter('subscriber_email', ['neq' => '']);

        // Filter by customer ID if provided
        if ($customerId !== null) {
            $subscribers->addFieldToFilter('customer_id', $customerId);
        }

        // Join with iys_data to check for existing records
        $subscribers->getSelect()->joinLeft(
            ['iys' => $subscribers->getTable('iys_data')],
            'main_table.subscriber_email = iys.value AND iys.type = "email"',
            ['iys_id' => 'iys.id', 'iys_modified' => 'iys.modified']
        );

        // Order by priority:
        // 1. Records with customer_id (not null first)
        // 2. Most recent change_status_at
        $subscribers->getSelect()
            ->order(new \Zend_Db_Expr('CASE WHEN customer_id IS NULL THEN 1 ELSE 0 END'))  // NOT NULL first
            ->order('change_status_at DESC');

        // Apply limit if set
        if ($limit !== null) {
            $subscribers->getSelect()->limit($limit);
        }

        // Track processed emails to skip duplicates
        $processedEmails = [];

        foreach ($subscribers as $subscriber) {
            $stats['processed']++;

            // Skip empty emails
            if (empty($subscriber->getSubscriberEmail())) {
                $stats['empty_email']++;
                continue;
            }

            // Skip if email already processed (duplicate)
            if (in_array($subscriber->getSubscriberEmail(), $processedEmails)) {
                $stats['duplicate_skipped']++;
                continue;
            }

            try {
                // Skip if IYS record exists and is newer
                if ($subscriber->getData('iys_id') &&
                    $subscriber->getData('iys_modified') &&
                    strtotime($subscriber->getData('iys_modified')) > strtotime($subscriber->getChangeStatusAt())) {
                    $stats['skipped']++;
                    continue;
                }

                // If subscriber is unsubscribed and no existing IYS record, skip it
                if ($subscriber->getSubscriberStatus() != \Magento\Newsletter\Model\Subscriber::STATUS_SUBSCRIBED
                    && !$subscriber->getData('iys_id')) {
                    $stats['skipped']++;
                    continue;
                }

                $iysData = $subscriber->getData('iys_id')
                    ? $this->iysDataFactory->create()->load($subscriber->getData('iys_id'))
                    : $this->iysDataFactory->create();

                // If subscriber is unsubscribed and IYS record exists with status 1 (accepted)
                // Update the record and mark as synced (iys_status = 1)
                if ($subscriber->getSubscriberStatus() != \Magento\Newsletter\Model\Subscriber::STATUS_SUBSCRIBED
                    && $subscriber->getData('iys_id')
                    && $iysData->getStatus() == 1) {
                    $iysData->setStatus(2); // Set to rejected
                    $iysData->setModified($subscriber->getChangeStatusAt());
                    $iysData->setIysStatus(0); // Mark as synced
                    $iysData->save();
                    $stats['updated']++;
                    continue;
                }

                // Regular processing for other cases
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

                // Set IYS status
                $iysData->setIysStatus(0);
                $iysData->save();

                if ($subscriber->getData('iys_id')) {
                    $stats['updated']++;
                } else {
                    $stats['created']++;
                }

                // Add to processed emails list
                $processedEmails[] = $subscriber->getSubscriberEmail();

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