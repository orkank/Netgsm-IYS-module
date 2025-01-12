<?php
namespace IDangerous\NetgsmIYS\Controller\Adminhtml\Import;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Filesystem\Driver\File;

class DownloadSample extends Action
{
    /**
     * @var FileFactory
     */
    protected $fileFactory;

    /**
     * @var File
     */
    protected $fileDriver;

    /**
     * @param Context $context
     * @param FileFactory $fileFactory
     * @param File $fileDriver
     */
    public function __construct(
        Context $context,
        FileFactory $fileFactory,
        File $fileDriver
    ) {
        parent::__construct($context);
        $this->fileFactory = $fileFactory;
        $this->fileDriver = $fileDriver;
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $content = "type,value,status,userid,modified\n";
        $content .= "sms,+905321234567,1,1,2024-01-01 00:00:00\n";
        $content .= "email,test@example.com,1,2,2024-01-01 00:00:00\n";

        return $this->fileFactory->create(
            'iys_import_sample.csv',
            $content,
            \Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR,
            'text/csv'
        );
    }
}