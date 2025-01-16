<?php
namespace IDangerous\NetgsmIYS\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use IDangerous\NetgsmIYS\Cron\NewsletterSync;

class NewsletterSyncCommand extends Command
{
    /**
     * @var NewsletterSync
     */
    private $newsletterSync;

    /**
     * @param NewsletterSync $newsletterSync
     * @param string|null $name
     */
    public function __construct(
        NewsletterSync $newsletterSync,
        string $name = null
    ) {
        parent::__construct($name);
        $this->newsletterSync = $newsletterSync;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('idangerous:iys:newsletter-sync')
            ->setDescription('Sync newsletter subscribers to IYS data table')
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_REQUIRED,
                'Limit number of records to process'
            )
            ->addOption(
                'customer-id',
                'c',
                InputOption::VALUE_REQUIRED,
                'Sync specific customer by ID'
            )
            ->addOption(
                'debug',
                'd',
                InputOption::VALUE_NONE,
                'Enable debug output'
            );

        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $output->writeln('<info>Starting newsletter sync...</info>');

            $limit = $input->getOption('limit');
            $customerId = $input->getOption('customer-id');

            if ($customerId) {
                $output->writeln(sprintf('<info>Processing specific customer ID: %d</info>', $customerId));
            }

            if ($limit !== null && !$customerId) {
                $limit = (int)$limit;
                $output->writeln(sprintf('<info>Processing limit: %d records</info>', $limit));
            }

            $this->newsletterSync->execute($limit, $customerId);

            $output->writeln('<info>Newsletter sync completed successfully.</info>');

            return 0;
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            if ($input->getOption('debug')) {
                $output->writeln('<error>' . $e->getTraceAsString() . '</error>');
            }
            return 1;
        }
    }
}