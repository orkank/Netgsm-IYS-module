<?php
namespace IDangerous\NetgsmIYS\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use IDangerous\NetgsmIYS\Model\ResourceModel\IysData\CollectionFactory;
use IDangerous\NetgsmIYS\Helper\Api as ApiHelper;

class SyncCommand extends AbstractCommand
{
    private const OPTION_ID = 'id';
    private const OPTION_VALUE = 'value';
    private const OPTION_TYPE = 'type';
    private const OPTION_DEBUG = 'debug';
    private const BATCH_SIZE = 50;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var ApiHelper
     */
    private $apiHelper;

    /**
     * @param CollectionFactory $collectionFactory
     * @param ApiHelper $apiHelper
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        ApiHelper $apiHelper
    ) {
        parent::__construct();
        $this->collectionFactory = $collectionFactory;
        $this->apiHelper = $apiHelper;
    }

    /**
     * Configure command
     */
    protected function configure()
    {
        $this->setName('idangerous:iys:sync')
            ->setDescription('Sync IYS data with Netgsm')
            ->addOption(
                self::OPTION_ID,
                null,
                InputOption::VALUE_OPTIONAL,
                'Sync specific record by ID'
            )
            ->addOption(
                self::OPTION_VALUE,
                null,
                InputOption::VALUE_OPTIONAL,
                'Sync records by value (phone/email)'
            )
            ->addOption(
                self::OPTION_TYPE,
                null,
                InputOption::VALUE_OPTIONAL,
                'Sync records by type (sms, call, email)'
            )
            ->addOption(
                self::OPTION_DEBUG,
                'd',
                InputOption::VALUE_NONE,
                'Show debug information without syncing'
            );
    }

    /**
     * Execute command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $id = $input->getOption(self::OPTION_ID);
            $value = $input->getOption(self::OPTION_VALUE);
            $type = $input->getOption(self::OPTION_TYPE);
            $debug = $input->getOption(self::OPTION_DEBUG);

            if ($id || $value || $type) {
                return $this->processSyncWithFilters($id, $value, $type, $output, $debug);
            }

            return $this->processBatchSync($output, $debug);

        } catch (\Exception $e) {
            $this->log($output, '<error>Error: ' . $e->getMessage() . '</error>');
            return 1;
        }
    }

    /**
     * Display debug information for a record
     *
     * @param \IDangerous\NetgsmIYS\Model\IysData $record
     * @param OutputInterface $output
     */
    private function displayDebugInfo($record, OutputInterface $output)
    {
        $output->writeln("\n<info>Debug Information for Record ID: " . $record->getId() . "</info>");

        // Display Record Details
        $recordTable = new Table($output);
        $recordTable->setHeaders(['Field', 'Value']);
        $recordTable->addRows([
            ['ID', $record->getId()],
            ['Type', $record->getTypeLabel()],
            ['Value', $record->getValue()],
            ['Status', $record->getStatusLabel()],
            ['User ID', $record->getUserid() ?: 'N/A'],
            ['Modified', $record->getModified()],
            ['IYS Status', $record->getIysStatusLabel()],
            ['Sync Retries', $record->getSyncRetries()]
        ]);
        $recordTable->render();

        // Get API payload
        $payload = $this->apiHelper->preparePayload([
            'id' => $record->getId(),
            'value' => $record->getValue(),
            'type' => $record->getType(),
            'status' => $record->getStatus(),
            'modified' => $record->getModified()
        ]);

        // Display API Payload
        $output->writeln("\n<info>API Payload to be sent:</info>");

        // Header section
        $output->writeln("\n<comment>Header:</comment>");
        $headerTable = new Table($output);
        $headerTable->setHeaders(['Field', 'Value']);
        foreach ($payload['header'] as $key => $value) {
            if ($key === 'password') {
                $value = '********'; // Mask password
            }
            $headerTable->addRow([$key, $value]);
        }
        $headerTable->render();

        // Body section
        $output->writeln("\n<comment>Body Data:</comment>");
        $bodyTable = new Table($output);
        $bodyTable->setHeaders(['Field', 'Value']);
        foreach ($payload['body']['data'][0] as $key => $value) {
            $bodyTable->addRow([$key, $value]);
        }
        $bodyTable->render();

        $output->writeln(''); // Empty line for spacing
    }

    /**
     * Process sync with filters
     *
     * @param int|null $id
     * @param string|null $value
     * @param string|null $type
     * @param OutputInterface $output
     * @param bool $debug
     * @return int
     */
    private function processSyncWithFilters($id, $value, $type, OutputInterface $output, $debug)
    {
        $collection = $this->collectionFactory->create();

        if ($id) {
            $collection->addFieldToFilter('id', ['eq' => $id]);
        }
        if ($value) {
            $collection->addFieldToFilter('value', ['eq' => $value]);
        }
        if ($type) {
            $collection->addFieldToFilter('type', ['eq' => $type]);
        }

        if ($collection->getSize() === 0) {
            $this->log($output, '<comment>No records found matching the criteria</comment>');
            return 1;
        }

        $processed = 0;
        $success = 0;

        foreach ($collection as $record) {
            $processed++;
            try {
                if ($debug) {
                    $this->displayDebugInfo($record, $output);
                    continue;
                }

                $this->syncRecord($record, $output);
                $success++;
            } catch (\Exception $e) {
                $this->log($output, sprintf('<error>Failed to sync record %d: %s</error>', $record->getId(), $e->getMessage()));
            }
        }

        if ($debug) {
            $this->log($output, sprintf('<info>Debug completed for %d records</info>', $processed));
            return 0;
        }

        $this->log($output, sprintf('<info>Processed %d records, %d successful</info>', $processed, $success));
        return ($success > 0) ? 0 : 1;
    }

    /**
     * Process batch sync
     *
     * @param OutputInterface $output
     * @param bool $debug
     * @return int
     */
    private function processBatchSync(OutputInterface $output, $debug)
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('iys_status', ['eq' => 0]);
        $collection->setPageSize(self::BATCH_SIZE);

        if ($collection->getSize() === 0) {
            $this->log($output, '<comment>No pending records found</comment>');
            return 0;
        }

        $processed = 0;
        $success = 0;

        foreach ($collection as $record) {
            $processed++;
            try {
                if ($debug) {
                    $this->displayDebugInfo($record, $output);
                    continue;
                }

                $this->syncRecord($record, $output);
                $success++;
            } catch (\Exception $e) {
                $this->log($output, sprintf('<error>Failed to sync record %d: %s</error>', $record->getId(), $e->getMessage()));
            }
        }

        if ($debug) {
            $this->log($output, sprintf('<info>Debug completed for %d records</info>', $processed));
            return 0;
        }

        $this->log($output, sprintf('<info>Processed %d records, %d successful</info>', $processed, $success));
        return ($success > 0) ? 0 : 1;
    }

    /**
     * Sync individual record
     *
     * @param \IDangerous\NetgsmIYS\Model\IysData $record
     * @param OutputInterface $output
     * @throws \Exception
     */
    private function syncRecord($record, OutputInterface $output)
    {
        $this->log($output, sprintf('<info>Processing record ID: %d</info>', $record->getId()));

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

        $this->log($output, sprintf('<info>Successfully synced record ID: %d</info>', $record->getId()));
    }
}