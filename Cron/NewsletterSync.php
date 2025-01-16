<?php
namespace IDangerous\NetgsmIYS\Cron;

use IDangerous\NetgsmIYS\Console\Command\NewsletterSyncCommand;
use IDangerous\NetgsmIYS\Helper\Logger;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class NewsletterSync
{
    /**
     * @var NewsletterSyncCommand
     */
    private $command;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param NewsletterSyncCommand $command
     * @param Logger $logger
     */
    public function __construct(
        NewsletterSyncCommand $command,
        Logger $logger
    ) {
        $this->command = $command;
        $this->logger = $logger;
    }

    /**
     * Execute cron job
     *
     * @return void
     */
    public function execute()
    {
        try {
            $this->logger->info('Starting newsletter sync cron job');

            $input = new ArrayInput([]);
            $output = new NullOutput();

            $this->command->run($input, $output);

            $this->logger->info('Newsletter sync cron job completed');
        } catch (\Exception $e) {
            $this->logger->error('Newsletter sync cron error: ' . $e->getMessage());
        }
    }
}