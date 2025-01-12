<?php
namespace IDangerous\NetgsmIYS\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CleanLogsCommand extends AbstractCommand
{
    protected function configure()
    {
        $this->setName('idangerous:iys:clean-logs')
            ->setDescription('Clean IYS log files')
            ->addOption(
                'days',
                null,
                InputOption::VALUE_OPTIONAL,
                'Delete logs older than X days'
            )
            ->addOption(
                'all',
                null,
                InputOption::VALUE_NONE,
                'Delete all logs'
            )
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Force delete without confirmation'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $days = $input->getOption('days');
        $all = $input->getOption('all');
        $force = $input->getOption('force');

        if (!$days && !$all) {
            $this->log($output, 'Please specify either --days=X or --all option');
            return 1;
        }

        if ($all && !$force) {
            $this->log($output, 'Use --force to confirm deletion of all logs');
            return 1;
        }

        try {
            $logPath = BP . '/var/log/netgsm_iys/';
            if (!is_dir($logPath)) {
                $this->log($output, 'No logs directory found');
                return 0;
            }

            $this->cleanLogs($logPath, $days, $all, $output);
            return 0;
        } catch (\Exception $e) {
            $this->log($output, 'Error: ' . $e->getMessage());
            return 1;
        }
    }

    private function cleanLogs(string $path, ?int $days, bool $all, OutputInterface $output): void
    {
        $files = glob($path . '*.log');
        foreach ($files as $file) {
            if ($all || ($days && (time() - filemtime($file)) > ($days * 86400))) {
                unlink($file);
                $this->log($output, 'Deleted: ' . basename($file));
            }
        }
    }
}