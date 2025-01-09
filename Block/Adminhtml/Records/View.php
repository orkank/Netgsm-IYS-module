<?php
namespace IDangerous\NetgsmIYS\Block\Adminhtml\Records;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use IDangerous\NetgsmIYS\Model\IysDataFactory;
use Magento\Framework\Serialize\Serializer\Json;

class View extends Template
{
    /**
     * @var IysDataFactory
     */
    protected $iysDataFactory;

    /**
     * @var Json
     */
    protected $json;

    /**
     * @param Context $context
     * @param IysDataFactory $iysDataFactory
     * @param Json $json
     * @param array $data
     */
    public function __construct(
        Context $context,
        IysDataFactory $iysDataFactory,
        Json $json,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->iysDataFactory = $iysDataFactory;
        $this->json = $json;
    }

    /**
     * Get current record
     *
     * @return \IDangerous\NetgsmIYS\Model\IysData
     */
    public function getRecord()
    {
        $id = $this->getRequest()->getParam('id');
        $model = $this->iysDataFactory->create();
        if ($id) {
            $model->load($id);
        }
        return $model;
    }

    /**
     * Format IYS result for display
     *
     * @param string|null $result
     * @return array
     */
    public function formatIysResult($result)
    {
        if (empty($result)) {
            return [];
        }

        try {
            if (is_string($result)) {
                return $this->json->unserialize($result);
            }
            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }
}