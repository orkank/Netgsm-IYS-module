<?php
namespace IDangerous\NetgsmIYS\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractCommand extends Command
{
    protected function log(OutputInterface $output, string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $output->writeln(sprintf('[%s] %s', $timestamp, $message));
    }
}