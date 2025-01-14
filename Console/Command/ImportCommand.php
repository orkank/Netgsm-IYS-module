<?php
namespace IDangerous\NetgsmIYS\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use IDangerous\NetgsmIYS\Model\IysDataFactory;
use IDangerous\NetgsmIYS\Helper\Logger;

class ImportCommand extends Command
{
    /**
     * @var IysDataFactory
     */
    private $iysDataFactory;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param IysDataFactory $iysDataFactory
     * @param Logger $logger
     * @param string|null $name
     */
    public function __construct(
        IysDataFactory $iysDataFactory,
        Logger $logger,
        string $name = null
    ) {
        parent::__construct($name);
        $this->iysDataFactory = $iysDataFactory;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('idangerous:iys:import')
            ->setDescription('Import IYS records from CSV file')
            ->addOption(
                'file',
                'f',
                InputOption::VALUE_REQUIRED,
                'CSV file path'
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $filePath = $input->getOption('file');
            if (!$filePath) {
                throw new \Exception('Please provide a file path using --file option');
            }

            if (!file_exists($filePath)) {
                throw new \Exception('File not found: ' . $filePath);
            }

            $handle = fopen($filePath, 'r');
            if ($handle === false) {
                throw new \Exception('Could not open file');
            }

            // Skip header row
            fgetcsv($handle);

            $stats = [
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
                'errors' => 0
            ];

            while (($data = fgetcsv($handle)) !== false) {
                try {
                    if (count($data) < 5) {
                        $output->writeln("<error>Invalid row format: " . implode(',', $data) . "</error>");
                        $stats['errors']++;
                        continue;
                    }

                    list($type, $value, $status, $userid, $modified) = $data;

                    // Skip empty values
                    if (empty($value)) {
                        $output->writeln("<comment>Skipping empty value</comment>");
                        continue;
                    }

                    $model = $this->iysDataFactory->create();
                    $model->load($value, 'value');

                    if (!$model->getId()) {
                        $stats['created']++;
                        $output->writeln("<info>Creating new record for: {$value}</info>");
                    } else {
                        $stats['updated']++;
                        $output->writeln("<info>Updating existing record for: {$value}</info>");
                    }

                    $model->setType($type)
                        ->setValue($value)
                        ->setStatus((int)$status)
                        ->setUserid($userid ?: null)
                        ->setModified($modified)
                        ->setIysStatus($status)
                        ->save();

                    $stats['processed']++;

                } catch (\Exception $e) {
                    $stats['errors']++;
                    $output->writeln("<error>Error processing row: " . $e->getMessage() . "</error>");
                    $this->logger->error('Import error: ' . $e->getMessage(), ['data' => $data]);
                }
            }

            fclose($handle);

            $output->writeln([
                '',
                '<info>Import completed:</info>',
                sprintf('Processed: %d', $stats['processed']),
                sprintf('Created: %d', $stats['created']),
                sprintf('Updated: %d', $stats['updated']),
                sprintf('Errors: %d', $stats['errors']),
                ''
            ]);

            return 0;

        } catch (\Exception $e) {
            $output->writeln("<error>" . $e->getMessage() . "</error>");
            return 1;
        }
    }
}