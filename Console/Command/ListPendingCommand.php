<?php
namespace IDangerous\NetgsmIYS\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use IDangerous\NetgsmIYS\Model\ResourceModel\IysData\CollectionFactory;

class ListPendingCommand extends Command
{
    private const OPTION_COUNT = 'count';
    private const OPTION_LIMIT = 'limit';
    private const OPTION_PAGE = 'page';

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        CollectionFactory $collectionFactory
    ) {
        parent::__construct();
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * Configure command
     */
    protected function configure()
    {
        $this->setName('idangerous:iys:list-pending')
            ->setDescription('List pending IYS records')
            ->addOption(
                self::OPTION_COUNT,
                'c',
                InputOption::VALUE_NONE,
                'Show only count of pending records'
            )
            ->addOption(
                self::OPTION_LIMIT,
                'l',
                InputOption::VALUE_OPTIONAL,
                'Number of records to show',
                50
            )
            ->addOption(
                self::OPTION_PAGE,
                'p',
                InputOption::VALUE_OPTIONAL,
                'Page number',
                1
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
            $collection = $this->collectionFactory->create();
            $collection->addFieldToFilter('iys_status', ['eq' => 0]);

            if ($input->getOption(self::OPTION_COUNT)) {
                $count = $collection->getSize();
                $output->writeln(sprintf('<info>Pending records count: %d</info>', $count));
                return 0;
            }

            $limit = (int)$input->getOption(self::OPTION_LIMIT);
            $page = (int)$input->getOption(self::OPTION_PAGE);

            $collection->setPageSize($limit);
            $collection->setCurPage($page);

            if ($collection->getSize() === 0) {
                $output->writeln('<info>No pending records found</info>');
                return 0;
            }

            $output->writeln([
                '',
                sprintf(
                    '<info>Found %d pending records (showing page %d of %d)</info>',
                    $collection->getSize(),
                    $page,
                    $collection->getLastPageNumber()
                ),
                ''
            ]);

            $table = new Table($output);
            $table->setHeaders(['ID', 'Type', 'Value', 'Status', 'User ID', 'Modified']);

            foreach ($collection as $record) {
                $table->addRow([
                    $record->getId(),
                    $record->getTypeLabel(),
                    $record->getValue(),
                    $record->getStatusLabel(),
                    $record->getUserid() ?: 'N/A',
                    $record->getModified()
                ]);
            }

            $table->setStyle('box');
            $table->render();

            $output->writeln(''); // Add empty line at the end

            return 0;

        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));
            return 1;
        }
    }
}