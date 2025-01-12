<?php
namespace IDangerous\NetgsmIYS\Controller\Adminhtml\Import;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use IDangerous\NetgsmIYS\Model\IysDataFactory;
use IDangerous\NetgsmIYS\Helper\Logger;

class Process extends Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var IysDataFactory
     */
    protected $iysDataFactory;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param IysDataFactory $iysDataFactory
     * @param Logger $logger
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        IysDataFactory $iysDataFactory,
        Logger $logger
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->iysDataFactory = $iysDataFactory;
        $this->logger = $logger;
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        try {
            $file = $this->getRequest()->getFiles('import_file');

            if (empty($file['tmp_name'])) {
                throw new \Exception(__('Please select a file to import'));
            }

            $handle = fopen($file['tmp_name'], 'r');
            if ($handle === false) {
                throw new \Exception(__('Could not open file'));
            }

            // Skip header row
            fgetcsv($handle);

            $processed = 0;
            $created = 0;
            $updated = 0;
            $errors = 0;

            while (($data = fgetcsv($handle)) !== false) {
                try {
                    if (count($data) < 5) {
                        continue;
                    }

                    list($type, $value, $status, $userid, $modified) = $data;

                    $model = $this->iysDataFactory->create();
                    $model->load($value, 'value');

                    if (!$model->getId()) {
                        $created++;
                    } else {
                        $updated++;
                    }

                    $model->setType($type)
                        ->setValue($value)
                        ->setStatus((int)$status)
                        ->setUserid($userid ?: null)
                        ->setModified($modified)
                        ->setIysStatus(1)
                        ->save();

                    $processed++;
                } catch (\Exception $e) {
                    $errors++;
                    $this->logger->error('Import error: ' . $e->getMessage(), ['data' => $data]);
                }
            }

            fclose($handle);

            return $resultJson->setData([
                'success' => true,
                'message' => __(
                    'Import completed. Processed: %1, Created: %2, Updated: %3, Errors: %4',
                    $processed,
                    $created,
                    $updated,
                    $errors
                )
            ]);

        } catch (\Exception $e) {
            return $resultJson->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}