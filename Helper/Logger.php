<?php
namespace IDangerous\NetgsmIYS\Helper;

use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Serialize\Serializer\Json;

class Logger
{
    private const LOG_DIR = 'netgsm_iys';
    private $file;
    private $json;
    private $config;

    public function __construct(
        File $file,
        Json $json,
        Config $config
    ) {
        $this->file = $file;
        $this->json = $json;
        $this->config = $config;
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    public function logWebhook(array $data): void
    {
        $this->log('WEBHOOK', 'Received webhook data', $data);
    }

    private function log(string $level, string $message, array $context = []): void
    {
        if (!$this->config->isLoggingEnabled()) {
            return;
        }

        $logDir = BP . '/var/log/' . self::LOG_DIR;
        if (!$this->file->isDirectory($logDir)) {
            $this->file->createDirectory($logDir);
        }

        $logFile = $logDir . '/' . date('Y-m-d') . '.log';
        $logEntry = sprintf(
            "[%s] %s: %s %s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            !empty($context) ? $this->json->serialize($context) : ''
        );

        $this->file->filePutContents($logFile, $logEntry, FILE_APPEND);
    }
}