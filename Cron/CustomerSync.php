<?php
namespace IDangerous\NetgsmIYS\Cron;

use IDangerous\NetgsmIYS\Console\Command\CustomerSyncCommand;
use IDangerous\NetgsmIYS\Helper\Logger;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class CustomerSync
{
    /**
     * @var CustomerSyncCommand
     */
    private $command;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param CustomerSyncCommand $command
     * @param Logger $logger
     */
    public function __construct(
        CustomerSyncCommand $command,
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
            $this->logger->info('Starting customer sync cron job');

            $input = new ArrayInput([]);
            $output = new NullOutput();

            $this->command->run($input, $output);

            $this->logger->info('Customer sync cron job completed');
        } catch (\Exception $e) {
            $this->logger->error('Customer sync cron error: ' . $e->getMessage());
        }
    }
}