<?php
namespace IDangerous\NetgsmIYS\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use IDangerous\NetgsmIYS\Model\IysDataFactory;
use IDangerous\NetgsmIYS\Model\ResourceModel\IysData as IysDataResource;
use IDangerous\NetgsmIYS\Helper\Logger;
use IDangerous\NetgsmIYS\Helper\PhoneNumber;

class CustomerSyncCommand extends Command
{
    /**
     * @var CustomerCollectionFactory
     */
    private $customerCollectionFactory;

    /**
     * @var IysDataFactory
     */
    private $iysDataFactory;

    /**
     * @var IysDataResource
     */
    private $iysDataResource;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var PhoneNumber
     */
    private $phoneHelper;

    public function __construct(
        CustomerCollectionFactory $customerCollectionFactory,
        IysDataFactory $iysDataFactory,
        IysDataResource $iysDataResource,
        Logger $logger,
        PhoneNumber $phoneHelper,
        string $name = null
    ) {
        parent::__construct($name);
        $this->customerCollectionFactory = $customerCollectionFactory;
        $this->iysDataFactory = $iysDataFactory;
        $this->iysDataResource = $iysDataResource;
        $this->logger = $logger;
        $this->phoneHelper = $phoneHelper;
    }

    protected function configure()
    {
        $this->setName('idangerous:iys:customer-sync')
            ->setDescription('Sync customer permissions to IYS records')
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_REQUIRED,
                'Limit number of records to process'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $output->writeln('<info>Starting customer permission sync...</info>');

            $limit = $input->getOption('limit');
            if ($limit !== null) {
                $limit = (int)$limit;
                $output->writeln(sprintf('<info>Processing limit: %d records</info>', $limit));
            }

            $stats = [
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
                'rejected' => 0,
                'skipped' => 0
            ];

            // Get customers with phone numbers and verified
            $customers = $this->customerCollectionFactory->create();
            $customers->addAttributeToSelect(['phone_number', 'allow_sms', 'allow_call', 'allow_whatsapp']);
            $customers->addAttributeToFilter('phone_verified', 1);
            $customers->addAttributeToFilter('phone_number', ['notnull' => true]);
            $customers->addAttributeToFilter('phone_number', ['neq' => '']);

            if ($limit) {
                $customers->setPageSize($limit);
            }

            foreach ($customers as $customer) {
                $stats['processed']++;
                $output->writeln(sprintf('<info>Processing customer ID: %d</info>', $customer->getId()));

                // Process SMS permissions
                $this->processPermission(
                    $customer,
                    'sms',
                    $customer->getData('allow_sms'),
                    $customer->getPhoneNumber(),
                    $stats,
                    $output
                );

                // Process Call permissions
                $this->processPermission(
                    $customer,
                    'call',
                    $customer->getData('allow_call'),
                    $customer->getPhoneNumber(),
                    $stats,
                    $output
                );

                // Process WhatsApp permissions
                $this->processPermission(
                    $customer,
                    'whatsapp',
                    $customer->getData('allow_whatsapp'),
                    $customer->getPhoneNumber(),
                    $stats,
                    $output
                );
            }

            $output->writeln([
                '',
                '<info>Sync completed:</info>',
                sprintf('Processed: %d', $stats['processed']),
                sprintf('Created: %d', $stats['created']),
                sprintf('Updated: %d', $stats['updated']),
                sprintf('Rejected: %d', $stats['rejected']),
                sprintf('Skipped: %d', $stats['skipped']),
                ''
            ]);

            return 0;
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return 1;
        }
    }

    private function processPermission($customer, $type, $allowed, $phoneNumber, &$stats, OutputInterface $output)
    {
        try {
            // Format and validate phone number
            $formattedNumber = $this->phoneHelper->format($phoneNumber);
            if (!$formattedNumber) {
                $output->writeln(sprintf('<error>Invalid phone number for customer ID %d: %s</error>', $customer->getId(), $phoneNumber));
                $stats['skipped']++;
                return;
            }

            // Load existing record using collection to filter by both value and type
            $collection = $this->iysDataFactory->create()->getCollection()
                ->addFieldToFilter('value', $formattedNumber)
                ->addFieldToFilter('type', $type);

            $iysData = $collection->getFirstItem();

            // Skip if no permission was ever granted and current permission is not allowed
            if (!$iysData->getId() && !$allowed) {
                $stats['skipped']++;
                return;
            }

            if (!$iysData->getId()) {
                // Create new record if allowed
                if ($allowed) {
                    $iysData->setData([
                        'type' => $type,
                        'value' => $formattedNumber,
                        'status' => 1, // Accepted
                        'userid' => $customer->getId(),
                        'iys_status' => 0, // Pending sync
                        'modified' => date('Y-m-d H:i:s')
                    ]);
                    $stats['created']++;
                    $output->writeln(sprintf('<info>Creating new %s permission for: %s</info>', $type, $formattedNumber));
                }
            } else {
                // Update existing record
                $needsUpdate = false;

                // Check if status needs to be updated
                if ($allowed && $iysData->getStatus() != 1) {
                    $iysData->setStatus(1);
                    $needsUpdate = true;
                } elseif (!$allowed && $iysData->getStatus() != 2) {
                    $iysData->setStatus(2); // Rejected
                    $needsUpdate = true;
                }

                // Update userid if changed
                if ($iysData->getUserid() != $customer->getId()) {
                    $iysData->setUserid($customer->getId());
                    $needsUpdate = true;
                }

                // Only update if changes were made
                if ($needsUpdate) {
                    $iysData->setIysStatus(0); // Mark for sync only if data changed
                    $iysData->setModified(date('Y-m-d H:i:s'));

                    if ($allowed) {
                        $stats['updated']++;
                        $output->writeln(sprintf('<info>Updating %s permission for: %s to accepted</info>', $type, $formattedNumber));
                    } else {
                        $stats['rejected']++;
                        $output->writeln(sprintf('<info>Updating %s permission for: %s to rejected</info>', $type, $formattedNumber));
                    }
                } else {
                    $stats['skipped']++;
                    $output->writeln(sprintf('<comment>Skipping %s permission for: %s (no changes)</comment>', $type, $formattedNumber));
                }
            }

            if ($iysData->hasDataChanges()) {
                $this->iysDataResource->save($iysData);
            }

        } catch (\Exception $e) {
            $this->logger->error('Error processing permission: ' . $e->getMessage(), [
                'customer_id' => $customer->getId(),
                'type' => $type,
                'phone' => $phoneNumber
            ]);
            throw $e;
        }
    }
}